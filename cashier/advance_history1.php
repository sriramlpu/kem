<?php
// =========================================================
// 1. DATABASE CONFIGURATION
// =========================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'kmkglobal_web');    // Default XAMPP/WAMP username
define('DB_PASS', 'tI]rfPhdOo9zHdKw');         // Default XAMPP/WAMP password
define('DB_NAME', 'kmkglobal_web');    // Your specified database name
require_once("../auth.php");
requireRole(['Cashier','Admin']);
/**
 * Establishes a PDO database connection.
 * @return PDO|null The PDO object or null on failure.
 */
function connectDB() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        // Display a connection error message
        echo "<div style='color:red;padding:15px;border:1px solid red;'>Connection failed: Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        return null;
    }
}

// =========================================================
// 2. FILTERING AND DATA RETRIEVAL LOGIC
// =========================================================
$advance_history = [];
$error_message = '';
$pdo = connectDB();

// --- 2a. Get Filter Parameters from URL or set defaults ---
$filter_type = $_GET['type'] ?? 'all';
$filter_entity_id = $_GET['entity_id'] ?? 'all'; 
$filter_start_date = $_GET['start_date'] ?? date('Y-m-01'); // Start of month
$filter_end_date = $_GET['end_date'] ?? date('Y-m-d');      // Today

// --- 2b. Data Fetching for Dropdowns ---
$vendors = [];
$employees = [];
if ($pdo) {
    try {
        // Fetch Vendors
        $vendor_sql = "SELECT vendor_id, vendor_name FROM vendors WHERE status = 'Active' ORDER BY vendor_name";
        $stmt_vendors = $pdo->prepare($vendor_sql);
        $stmt_vendors->execute();
        $vendors = $stmt_vendors->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Employees 
        $employee_sql = "SELECT id, employee_name, employee_uid FROM employees ORDER BY employee_name";
        $stmt_employees = $pdo->prepare($employee_sql);
        $stmt_employees->execute();
        $employees = $stmt_employees->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error fetching entities for dropdown: " . $e->getMessage());
    }
}


if ($pdo) {
    // --- 2c. Build the SQL Query with Dynamic Filtering ---
    $sql = "
        SELECT
            a.advance_id,
            a.entity_type,
            a.amount,
            a.notes,
            a.payment_method,        -- Payment Method
            a.ref_number,            -- Reference Number
            a.payment_proof_path,    -- Proof Path
            a.advance_date,          -- Advance Date
            a.payment_date AS record_datetime, -- When record was created
            CASE a.entity_type
                WHEN 'vendor' THEN v.vendor_name
                WHEN 'employee' THEN e.employee_name
                ELSE 'N/A'
            END AS entity_name
        FROM
            advances a
        LEFT JOIN
            vendors v ON a.entity_type = 'vendor' AND a.entity_id = v.vendor_id
        LEFT JOIN
            employees e ON a.entity_type = 'employee' AND a.entity_id = e.id
        WHERE
            DATE(a.advance_date) BETWEEN :start_date AND :end_date 
    ";
    
    // Add entity type filter
    if ($filter_type !== 'all') {
        $sql .= " AND a.entity_type = :entity_type";
    }

    // Add specific entity ID filter (for Vendor or Employee)
    if (($filter_type === 'vendor' || $filter_type === 'employee') && $filter_entity_id !== 'all') {
        $sql .= " AND a.entity_id = :entity_id";
    }

    // Sort by advance_id DESC to ensure newest is at the top (Serial number will reverse this)
    $sql .= " ORDER BY a.advance_id DESC"; 

    try {
        $stmt = $pdo->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':start_date', $filter_start_date);
        $stmt->bindParam(':end_date', $filter_end_date);
        
        if ($filter_type !== 'all') {
            $stmt->bindParam(':entity_type', $filter_type);
        }
        
        if (($filter_type === 'vendor' || $filter_type === 'employee') && $filter_entity_id !== 'all') {
            // Ensure the ID is cast to an integer for safety/correctness
            $entity_id_int = (int)$filter_entity_id;
            $stmt->bindParam(':entity_id', $entity_id_int, PDO::PARAM_INT);
        }

        $stmt->execute();
        $advance_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "Error fetching advance history: " . htmlspecialchars($e->getMessage());
    }
}

// Define the path to the cashier dashboard
$cashier_dashboard_url = 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advance History - KMK</title>
    <style>
        /* Basic Styling for readability and presentation */
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { max-width: 1400px; margin: 30px auto; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
        h2 { color: #333; margin: 0; }
        .btn-cashier {
            background-color: #28a745;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            display: inline-flex;
            align-items: center;
        }
        .btn-cashier:hover { background-color: #1e7e34; }
        .advance-history-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.9em; }
        .advance-history-table th, .advance-history-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .advance-history-table th { background-color: #007bff; color: white; }
        .advance-history-table tr:nth-child(even) { background-color: #f9f9f9; }
        .advance-history-table tr:hover { background-color: #e9ecef; }
        .badge { padding: 5px 10px; border-radius: 4px; font-weight: bold; font-size: 0.85em; display: inline-block; margin-top: 2px; }
        .badge-vendor { background-color: #dc3545; color: white; }
        .badge-employee { background-color: #007bff; color: white; }
        .badge-cash { background-color: #ffc107; color: #333; }
        .badge-bank { background-color: #17a2b8; color: white; }
        .alert-error { color: red; background-color: #fdd; padding: 15px; border: 1px solid #f00; border-radius: 5px; margin-top: 20px; }

        /* Filter bar styling - ensures filters stay in a stable row */
        .filter-bar { background-color: #e9ecef; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .filter-form-row { 
            display: flex; 
            align-items: center; 
            flex-wrap: wrap;
            /* 👇 FIXING ROW ALIGNMENT (Use space-between for distribution) */
            justify-content: flex-start; 
        }
        .filter-group { 
            margin-right: 10px; /* Reduced margin for space */
            margin-bottom: 10px; 
            /* 👇 Ensure uniform width for stability */
            min-width: 150px; 
        }
        .filter-group.w-200 { min-width: 200px; } /* Wider group for name selection */
        .filter-bar label { margin-right: 5px; font-weight: bold; color: #555; display: block; }
        .filter-bar select, .filter-bar input { padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box; }
        .filter-bar button { background-color: #007bff; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; }
        .filter-bar button:hover { background-color: #0056b3; }
    </style>
</head>
<body>

    <div class="container">

        <div class="header">
            <h2>Advance History</h2>
            <a href="<?= htmlspecialchars($cashier_dashboard_url) ?>" class="btn-cashier">
                <span style="margin-right: 8px;">➡️</span> Go to Cashier Dashboard
            </a>
        </div>

        <?php if (!empty($error_message)): ?>
            <p class="alert-error">Database Error: <?= $error_message ?></p>
        <?php endif; ?>

        <div class="filter-bar">
            <form method="GET" id="filterForm" class="filter-form-row">
                
                <div class="filter-group w-200">
                    <label for="type">Filter Type:</label>
                    <select id="type" name="type">
                        <option value="all" <?= $filter_type == 'all' ? 'selected' : '' ?>>All Entities</option>
                        <option value="vendor" <?= $filter_type == 'vendor' ? 'selected' : '' ?>>Vendors</option>
                        <option value="employee" <?= $filter_type == 'employee' ? 'selected' : '' ?>>Employees</option>
                    </select>
                </div>

                <div class="filter-group w-200" id="entity-filter-group" style="display: none;">
                    <label for="entity_id" id="entity-label">Specific Entity:</label>
                    
                    <select id="vendor-select" name="entity_id" style="display: none;">
                        <option value="all">All Vendors</option>
                        <?php foreach ($vendors as $vendor): ?>
                            <option 
                                value="<?= htmlspecialchars($vendor['vendor_id']) ?>" 
                                <?= $filter_type == 'vendor' && $filter_entity_id == $vendor['vendor_id'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($vendor['vendor_name']) ?> (ID: <?= $vendor['vendor_id'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select id="employee-select" name="entity_id_temp" style="display: none;">
                        <option value="all">All Employees</option>
                        <?php foreach ($employees as $employee): ?>
                            <option 
                                value="<?= htmlspecialchars($employee['id']) ?>" 
                                <?= $filter_type == 'employee' && $filter_entity_id == $employee['id'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($employee['employee_name']) ?> (UID: <?= $employee['employee_uid'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="start_date">Date Given (Start):</label>
                    <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($filter_start_date) ?>">
                </div>

                <div class="filter-group">
                    <label for="end_date">Date Given (End):</label>
                    <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($filter_end_date) ?>">
                </div>
                
                <div class="filter-group">
                    <label style="color:transparent;">_</label> <button type="submit">Apply Filters</button>
                </div>
            </form>
        </div>

        <?php if (empty($advance_history)): ?>
            <p style="padding: 15px; background-color: #ffc; border: 1px solid #ff0;">
                ✅ No advance payment history found for the selected filters.
            </p>
        <?php else: ?>
            <table class="advance-history-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">S.No.</th> 
                        <th>Entity Name</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Date Given</th>
                        <th>Method & Ref No</th>
                        <th>Proof</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $serial_number = 1;
                    // Define the correct base path for proof files
                    $base_proof_url = 'https://kmkglobal.in/kem/cashier/advance_proofs/';
                    
                    foreach ($advance_history as $advance): 
                        $type = htmlspecialchars($advance['entity_type']);
                        $type_class = ($type == 'vendor' ? 'badge-vendor' : 'badge-employee');
                        
                        $method = htmlspecialchars($advance['payment_method']);
                        $method_class = ($method == 'Cash' ? 'badge-cash' : 'badge-bank');
                        
                        $ref_number = htmlspecialchars($advance['ref_number'] ?? 'N/A');
                        $notes_display = htmlspecialchars(substr($advance['notes'] ?? '', 0, 50)) . (strlen($advance['notes'] ?? '') > 50 ? '...' : '');

                        // The database path might be incorrect, use basename() to extract only the filename
                        $db_path = $advance['payment_proof_path'] ?? '';
                        $filename = basename($db_path);
                        
                        // Construct the full URL using the defined base path and the extracted filename
                        $full_proof_url = $base_proof_url . $filename;
                        
                        $proof_display = !empty($db_path) 
                                                // Link uses the corrected full URL
                                                ? "<a href='{$full_proof_url}' target='_blank' style='color:#007bff;text-decoration:underline;'>View</a>" 
                                                : "N/A";
                    ?>
                    <tr>
                        <td><?= $serial_number++ ?></td>
                        <td>
                            <strong><?= htmlspecialchars($advance['entity_name'] ?? 'N/A') ?></strong>
                        </td>
                        <td>
                            <span class="badge <?= $type_class ?>">
                                <?= ucfirst($type) ?>
                            </span>
                        </td>
                        <td>**₹<?= number_format($advance['amount'], 2) ?>**</td>
                        
                        <td><?= htmlspecialchars($advance['advance_date']) ?></td> 
                        
                        <td>
                            <span class="badge <?= $method_class ?>"><?= $method ?></span>
                            <br><span style="font-size: 0.8em; color: #555;">Ref: <?= $ref_number ?></span>
                        </td>
                        
                        <td><?= $proof_display ?></td>
                        
                        <td title="<?= htmlspecialchars($advance['notes'] ?? '') ?>">
                            <?= $notes_display ?>
                        </td>
                        
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const typeSelect = document.getElementById('type');
            const entityGroup = document.getElementById('entity-filter-group');
            const entityLabel = document.getElementById('entity-label');
            const vendorSelect = document.getElementById('vendor-select');
            const employeeSelect = document.getElementById('employee-select');
            const form = document.getElementById('filterForm');
            
            function toggleEntityFilter() {
                const selectedType = typeSelect.value;
                
                // Reset/Hide entity-specific filters
                entityGroup.style.display = 'none';
                vendorSelect.style.display = 'none';
                employeeSelect.style.display = 'none';
                
                // Important: Ensure only one element has the 'name="entity_id"' attribute when submitting
                vendorSelect.name = 'entity_id_temp'; 
                employeeSelect.name = 'entity_id_temp';

                if (selectedType === 'vendor') {
                    entityGroup.style.display = 'block';
                    vendorSelect.style.display = 'block';
                    vendorSelect.name = 'entity_id'; // Set correct name for submission
                    entityLabel.textContent = 'Specific Vendor:';
                } else if (selectedType === 'employee') {
                    entityGroup.style.display = 'block';
                    employeeSelect.style.display = 'block';
                    employeeSelect.name = 'entity_id'; // Set correct name for submission
                    entityLabel.textContent = 'Specific Employee:';
                }
            }

            // Set initial state based on current filter value
            toggleEntityFilter();

            // Attach listener to change event
            typeSelect.addEventListener('change', toggleEntityFilter);
        });
    </script>

</body>
</html>