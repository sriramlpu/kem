<?php

/**
 * CASHIER: Vendor Redemption Point Management (Combined View and Logic).
 * Path: kmk/cashier/vendor_redemption_management.php
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- 1. Database Configuration and Connection Setup ---
$db_config = [
    'host' => 'localhost', // Typically 'localhost'
    'user' => 'kmkglobal_web', // e.g., 'root'
    'pass' => 'tI]rfPhdOo9zHdKw', // Your actual database password
    'db' => 'kmkglobal_web',
    'charset' => 'utf8mb4'
];
$pdo = null;
try {
    $pdo = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['db']};charset={$db_config['charset']}",
        $db_config['user'],
        $db_config['pass'],
        [
            PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES  => false,
        ]
    );
} catch (\PDOException $e) {
    error_log("DB Connection Error: " . $e->getMessage());
    die('<div style="padding: 20px; font-family: sans-serif; background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5;">
        <strong>System Error:</strong> Cannot connect to the database.
    </div>');
}

// --- 2. Integrated Database Helper Functions ---

function exeSql(string $sql, array $params = []): array
{
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if (stripos(trim($sql), 'SELECT') === 0) {
            return $stmt->fetchAll();
        }
        return [];
    } catch (\Throwable $e) {
        error_log("SQL Error: " . $e->getMessage() . " in query: " . $sql);
        throw new \RuntimeException("Database Query Failed: " . $e->getMessage());
    }
}

function table_exists(string $tableName): bool
{
    global $pdo, $db_config;
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1");
        $stmt->execute([$db_config['db'], $tableName]);
        return (bool)$stmt->fetch();
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Executes a safe UPDATE query. Returns the number of affected rows.
 */
function safe_update(string $table, array $data, string $whereClause): int
{
    global $pdo;
    if (empty($data)) { return 0; }
    
    $setParts = [];
    $params = [];
    
    foreach ($data as $key => $value) {
        if (is_string($value) && str_starts_with($value, 'RAW_SQL:')) {
             $raw_expression = substr($value, 8);
             $setParts[] = "`$key` = $raw_expression";
        } else {
             $setParts[] = "`$key` = ?";
             $params[] = $value;
        }
    }

    if (trim($whereClause) === '') {
        throw new \RuntimeException("safe_update() requires a WHERE clause.");
    }

    $sql = "UPDATE `$table` SET " . implode(', ', $setParts) . " WHERE $whereClause";
    
    $stmt = $pdo->prepare($sql);
    
    try {
        $stmt->execute($params);
        return $stmt->rowCount(); // Return affected rows count
    } catch (\PDOException $e) {
        error_log("safe_update failed for SQL: " . $sql . " with params: " . print_r($params, true) . " error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Executes a safe INSERT query. Returns an array with lastId and rowCount.
 * This is more robust for checking insertion within a transaction.
 */
function safe_insert(string $table, array $data): array
{
    global $pdo;
    if (empty($data)) { return ['lastId' => 0, 'rowCount' => 0]; }
    
    $keys = array_keys($data);
    $placeholders = array_fill(0, count($keys), '?');
    $values = array_values($data);

    $sql = "INSERT INTO `$table` (`" . implode('`, `', $keys) . "`) VALUES (" . implode(', ', $placeholders) . ")";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    
    return [
        'lastId' => (int)$pdo->lastInsertId(), 
        'rowCount' => $stmt->rowCount() // Returns 1 on success
    ]; 
}

// --- End of Integrated Database Helper Functions ---

/* ---------- general helpers ---------- */
function v($k, $d = null) { return $_POST[$k] ?? $_GET[$k] ?? $d; }
// Helper to ensure value is an integer
function i($x) { return is_numeric($x) ? (int)$x : 0; }
function f($x) { return is_numeric($x) ? (float)$x : 0.0; }
function s($x) { return trim((string)($x ?? '')); }
function h($x) { return htmlspecialchars((string)($x), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function now() { return date('Y-m-d H:i:s'); }
function uid(): int { return isset($_SESSION['userId']) ? (int)$_SESSION['userId'] : 1; }

/* ---------------------------------------------------- */
/* ---------- POST LOGIC: Update Redemption Points ---------- */
/* ---------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && s(v('action')) === 'update_points') {
    
    $vendorId = (int)v('vendor_id', 0);
    $updateType = s(v('update_type'));
    // Use i() helper to force integer points
    $pointsValue = i(v('points_value', 0)); 
    $note = s(v('note'));

    $redirectUrl = 'vendor_redemption_management.php?vendor_id=' . $vendorId;

    if ($vendorId <= 0) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Invalid Vendor ID.'];
        header("Location: " . $redirectUrl); exit;
    }
    if ($pointsValue <= 0 && $updateType === 'add') {
         $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Points value must be positive when adding.'];
         header("Location: " . $redirectUrl); exit;
    }
    if ($pointsValue < 0) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Points value cannot be negative.'];
        header("Location: " . $redirectUrl); exit;
    }
    if (empty($note)) {
         $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'A Note/Reference is mandatory for point management.'];
         header("Location: " . $redirectUrl); exit;
    }

    // Fetch the actual current balance
    $currentPointsDB = 0;
    $vendorExistsInTotals = false;
    try {
        $r = exeSql("SELECT redemption_points FROM vendor_totals WHERE vendor_id = ? LIMIT 1", [$vendorId]);
        if (is_array($r) && $r) {
            $currentPointsDB = (int)($r[0]['redemption_points'] ?? 0);
            $vendorExistsInTotals = true;
        }
    } catch (\Throwable $e) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Failed to read current points from DB: ' . $e->getMessage()];
        header("Location: " . $redirectUrl); exit;
    }
    
    $newPointsBalance = (int)$currentPointsDB;

    if ($updateType === 'add') {
        $newPointsBalance = $currentPointsDB + $pointsValue;
        $operation = 'ADD';
    } elseif ($updateType === 'set') {
        $newPointsBalance = $pointsValue;
        $operation = 'SET';
    } else {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Invalid update type selected.'];
        header("Location: " . $redirectUrl); exit;
    }
    
    // Ensure the new balance is not negative
    $newPointsBalance = max(0, $newPointsBalance); 
    // Calculate the change for logging
    $change = $newPointsBalance - $currentPointsDB; 

    global $pdo; 
    $pdo->beginTransaction(); 
    try {
        // 1. Update/Insert the vendor_totals table
        if (table_exists('vendor_totals')) {
            
            $updateData = [];
            $updateCount = 0;
            
            if ($updateType === 'add') {
                 // Use the RAW_SQL prefix for additive update
                 $updateData['redemption_points'] = "RAW_SQL: redemption_points + " . $pointsValue; 
            } elseif ($updateType === 'set') {
                 // For 'set', use the static value
                 $updateData['redemption_points'] = $newPointsBalance;
            }

            if ($vendorExistsInTotals) {
                 // Vendor exists, perform UPDATE
                 $updateCount = safe_update('vendor_totals', $updateData, "vendor_id=" . $vendorId . " LIMIT 1");
            } else {
                 // Vendor doesn't exist, perform INSERT
                 $insertData = [
                    'vendor_id' => $vendorId,
                    'redemption_points' => $newPointsBalance,
                    // IMPORTANT: Add other required columns from vendor_totals here if they have NO default value
                    // e.g., 'advance' => 0.00, 
                 ];
                 
                 $insertResult = safe_insert('vendor_totals', $insertData);
                 
                 // FIX: Check if the insertion affected 1 row
                 $updateCount = $insertResult['rowCount'];
            }

            if ($updateCount === 0) {
                 // Throw the error if nothing was updated/inserted
                 throw new \RuntimeException("Database total update/insert failed (Affected 0 rows or failed to insert).");
            }

        } else {
             throw new \RuntimeException("Vendor totals table not found or accessible.");
        }
        
        // 2. Insert into an audit/log table
        if (table_exists('redemption_point_logs')) {
             safe_insert('redemption_point_logs', [
                 'vendor_id' => $vendorId,
                 'action_type' => $operation,
                 'points_change' => (int)$change, 
                 'new_balance' => (int)$newPointsBalance, 
                 'acted_by' => uid(),
                 'note' => $note,
                 'acted_at' => now(),
             ]);
        }
        
        $pdo->commit();

        $_SESSION['flash_message'] = [
            'type' => 'success',
            'text' => 'Redemption points successfully ' . ($updateType === 'add' ? 'added.' : 'set.') . 
                      ' New Balance: ' . (int)$newPointsBalance . ' points.',
        ];

    } catch (\Throwable $e) {
        $pdo->rollBack();
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'text' => 'Point update failed: ' . h($e->getMessage()),
        ];
    }

    header("Location: " . $redirectUrl);
    exit;
}
/* ---------- END POST LOGIC ---------- */


/* ---------------------------------------------------- */
/* ---------- VIEW LOGIC AND RENDERING ---------- */
/* ---------------------------------------------------- */

// Fetch all vendors for the dropdown
try {
    $vendors = exeSql("SELECT vendor_id, vendor_name FROM vendors ORDER BY vendor_name ASC");
} catch (\Throwable $e) {
    $vendors = [];
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Failed to load vendors: Database error.'];
}

$vendors = is_array($vendors) ? $vendors : [];

$selectedVendorId = i(v('vendor_id', 0)); 
$currentPoints = 0; 
$flashMessage = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

$selectedVendorName = '';

// Fetch current points for the selected vendor AND identify the name
if ($selectedVendorId > 0) {
    // 1. Identify the vendor name
    foreach ($vendors as $v) {
        if ((int)$v['vendor_id'] == $selectedVendorId) {
            $selectedVendorName = $v['vendor_name'];
            break; 
        }
    }

    // 2. Fetch current points from DB
    try {
        $r = exeSql("SELECT redemption_points FROM vendor_totals WHERE vendor_id = ? LIMIT 1", [$selectedVendorId]);
        if (is_array($r) && $r) {
            $currentPoints = (int)($r[0]['redemption_points'] ?? 0);
        }
    } catch (\Throwable $e) {
        // Points data might be missing, but vendor exists
    }
}
?>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Manage Vendor Redemption Points</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: system-ui, Arial, sans-serif; padding: 20px; }
        .card { border: 1px solid #e5e7eb; border-radius: 12px; }
        .muted { color: #64748b; font-size: 13px }
    </style>
</head>
<body class="container">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">💰 Manage Vendor Redemption Points</h3>
    <a class="btn btn-outline-secondary" href="dashboard">Cashier Dashboard</a>
</div>

<?php if ($flashMessage): ?>
    <div class="alert alert-<?= h($flashMessage['type']) ?> alert-dismissible fade show" role="alert">
        <?= h($flashMessage['text']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card p-4 mb-4">
    <h4 class="card-title mb-3">Select Vendor</h4>
    
    <form method="get" action="vendor_redemption_management?vendor_id=' . <?php $vendorId ?>'" class="row g-3" id="vendor_select_form">
        <div class="col-md-6">
            <label for="vendor_id" class="form-label">Vendor Name</label>
            <select name="vendor_id" id="vendor_id" class="form-select" required>
                <option value="0">-- Select a Vendor --</option>
                <?php foreach ($vendors as $v): ?>
                    <option 
                        value="<?= (int)$v['vendor_id'] ?>" 
                        <?= (int)$v['vendor_id'] === $selectedVendorId ? 'selected' : '' ?>
                    >
                        <?= h($v['vendor_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 d-flex align-items-end">
             <?php if ($selectedVendorId > 0): ?>
                 <a href="vendor_redemption_management.php" class="btn btn-outline-danger w-100">Clear Selection</a>
             <?php endif; ?>
        </div>
    </form>
    
    <?php if ($selectedVendorId > 0 && !empty($selectedVendorName)): ?>
        <hr class="mt-4 mb-4">
        
        <h4 class="card-title mb-3">
            Current Points for **<?= h($selectedVendorName) ?>**: 
            <span class="text-success">₹<?= (int)$currentPoints ?></span>
            <small class="muted">(1 Point = ₹1)</small>
        </h4>

        <form method="post" action="vendor_redemption_management?vendor_id=' . <?php $vendorId ?>'" class="row g-3">
            <input type="hidden" name="action" value="update_points">
            <input type="hidden" name="vendor_id" value="<?= $selectedVendorId ?>">
            <input type="hidden" name="current_points" value="<?= (int)$currentPoints ?>"> 

            <div class="col-md-4">
                <label for="update_type" class="form-label">Update Type</label>
                <select name="update_type" id="update_type" class="form-select" required>
                    <option value="add">**Add Points** to Current Balance</option>
                    <option value="set">**Set New Total Points** (Overwrite)</option>
                </select>
            </div>
            
            <div class="col-md-4">
                <label for="points_value" class="form-label">Points Value (Enter/Edit)</label>
                <input type="number" step="1" min="0" name="points_value" id="points_value" class="form-control" placeholder="0" required>
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-success w-100">Update Points</button>
            </div>
            
            <div class="col-md-12">
                <label for="note" class="form-label">Note / Reference (Mandatory)</label>
                <input type="text" name="note" id="note" class="form-control" placeholder="e.g., Promotional Bonus / Manual Correction Ref: XYZ" required>
            </div>
        </form>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const vendorSelect = document.getElementById('vendor_id');
    const vendorSelectForm = document.getElementById('vendor_select_form'); 
    
    if (vendorSelect && vendorSelectForm) {
        vendorSelect.addEventListener('change', function() {
            if (this.value > 0) {
                vendorSelectForm.submit();
            } else {
                 window.location.href = 'vendor_redemption_management.php';
            }
        });
    }
});
</script>
</body>
</html>