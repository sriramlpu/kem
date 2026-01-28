<?php
/**
 * APPROVER: Dashboard with Integrated Nav and Bootstrap 5.
 * - Based on working appdashboard1.php logic.
 * - Added: Bulk Approval for Employees.
 * - Fixed: Server-side navigation parameters.
 */
session_start();
require_once("../auth.php");
requireRole(['Approver', 'Admin']);
require_once("../functions.php");

$userRole = strtolower(trim($_SESSION['roleName'] ?? ''));
$userName = ($_SESSION['userName'] ?? 'Approver');
$userId = ($_SESSION['userId'] ?? 0);

/* ---------------- Helpers ---------------- */
if (!function_exists('h')) { function h($x) { return htmlspecialchars((string)$x, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }

/* ---------------- Lookups ---------------- */
$_CACHE = ['V' => [], 'E' => [], 'U' => []];

function vendor_name(?int $id): string {
    global $_CACHE;
    if (!$id) return 'N/A';
    if (isset($_CACHE['V'][$id])) return $_CACHE['V'][$id];
    $r = exeSql("SELECT vendor_name FROM vendors WHERE vendor_id=$id LIMIT 1");
    return $_CACHE['V'][$id] = (string)($r[0]['vendor_name'] ?? 'Unknown');
}

function employee_info(?int $id): array {
    global $_CACHE;
    if (!$id) return ['name' => 'N/A'];
    if (isset($_CACHE['E'][$id])) return $_CACHE['E'][$id];
    $r = exeSql("SELECT employee_name AS name FROM employees WHERE id=$id LIMIT 1");
    return $_CACHE['E'][$id] = ['name' => (string)($r[0]['name'] ?? 'Unknown')];
}

function username_lookup(?int $id): string {
    global $_CACHE;
    if (!$id) return 'System';
    if (isset($_CACHE['U'][$id])) return $_CACHE['U'][$id];
    $r = exeSql("SELECT username FROM users WHERE user_id=$id LIMIT 1");
    return $_CACHE['U'][$id] = (string)($r[0]['username'] ?? 'User#' . $id);
}

/* ---------------- Data Parameters ---------------- */
$activeTab = $_GET['tab'] ?? 'dashboard';
$activeSub = strtolower((string)($_GET['sub'] ?? 'pending'));
$mapStatus = ['pending' => 'SUBMITTED', 'approved' => 'APPROVED', 'returned' => 'RETURNED'];
if (!isset($mapStatus[$activeSub])) $activeSub = 'pending';
$statusFilter = $mapStatus[$activeSub];

$valid_tabs = ['dashboard', 'vendor', 'employee', 'expenses', 'fixed'];
if (!in_array($activeTab, $valid_tabs)) $activeTab = 'dashboard';

/* ---------------- BULK ACTION HANDLER ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_approve') {
    $ids = $_POST['selected_ids'] ?? [];
    if (!empty($ids)) {
        $now = date('Y-m-d H:i:s');
        $approver_id = (int)($_SESSION['userId'] ?? 1);
        $count = 0;

        foreach ($ids as $rid) {
            $rid = (int)$rid;
            $check = exeSql("SELECT status, total_amount, payload_json FROM payment_requests WHERE request_id=$rid LIMIT 1");
            if ($check && $check[0]['status'] === 'SUBMITTED') {
                $total = (float)$check[0]['total_amount'];
                $p = json_decode($check[0]['payload_json'] ?? '{}', true) ?: [];
                $p['pay_now'] = $total; // Record that full amount was approved
                
                upData('payment_requests', [
                    'status' => 'APPROVED',
                    'approved_by' => $approver_id,
                    'approved_at' => $now,
                    'payload_json' => json_encode($p),
                    'updated_at' => $now
                ], ["request_id=$rid"]);

                $count++;
            }
        }
        header("Location: ?tab=$activeTab&sub=$activeSub&bulk_msg=Successfully Approved $count Requests");
        exit;
    }
}

/* ---------------- Global Data Fetch ---------------- */
$ACTIVE_STATUSES = "('SUBMITTED','APPROVED','RETURNED')";
$allRows = exeSql("SELECT * FROM payment_requests WHERE status IN $ACTIVE_STATUSES ORDER BY updated_at DESC") ?: [];

/**
 * Table Renderer
 */
function render_approver_table(array $rows, string $heading, bool $allowBulk = false) {
    if (empty($rows)) {
        echo '<div class="alert bg-white border shadow-sm mt-3 mb-4 text-center py-5 rounded-4"><p class="text-muted mb-0 italic">No ' . strtolower($heading) . ' found.</p></div>';
        return;
    }
    ?>
    <div class="d-flex justify-content-between align-items-end mt-4 mb-3">
        <h6 class="mb-0 fw-bold text-uppercase small text-muted letter-spacing-1"><?= h($heading) ?></h6>
        <?php if ($allowBulk): ?>
            <button type="submit" form="bulkForm" class="btn btn-sm btn-success rounded-pill px-4 fw-bold shadow-sm">Bulk Approve Selected</button>
        <?php endif; ?>
    </div>

    <form id="bulkForm" method="post">
        <input type="hidden" name="action" value="bulk_approve">
        <div class="card shadow-sm border-0 mb-3 rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="small text-uppercase fw-bold text-secondary">
                            <?php if ($allowBulk): ?>
                                <th class="ps-4" style="width: 40px;"><input type="checkbox" id="checkAll" class="form-check-input"></th>
                            <?php else: ?>
                                <th class="ps-4">Req #</th>
                            <?php endif; ?>
                            <th>Category</th>
                            <th>Details / Purpose</th>
                            <th>Requested By</th>
                            <th>Net Amount</th>
                            <th>Status</th>
                            <th class="pe-4 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($rows as $r):
                        $type = (string)($r['request_type'] ?? 'unknown');
                        $p = json_decode($r['payload_json'] ?? '{}', true) ?: [];
                        $requestor = username_lookup((int)($r['requested_by'] ?? 0));
                        
                        if ($type === 'employee') {
                            $details = "<strong>Payroll: " . h(employee_info((int)$r['employee_id'])['name']) . "</strong><br>";
                            $details .= "<small class='text-muted'>Gross: ₹" . ($p['gross_salary'] ?? 0) . " | LOP: ₹" . ($p['lop_amount'] ?? 0) . " | PF: ₹" . ($p['pf_deduction'] ?? 0) . "</small>";
                        } elseif ($type === 'vendor') {
                            $details = "Vendor: " . h(vendor_name((int)$r['vendor_id']));
                        } elseif ($type === 'fixed') {
                            $details = "Fixed: " . h($p['purpose'] ?? ($p['fixed_id'] ?? 'Recurring obligation'));
                        } else {
                            $details = "Purpose: " . h($p['purpose'] ?? 'N/A');
                        }
                    ?>
                        <tr>
                            <td class="ps-4">
                                <?php if ($allowBulk): ?>
                                    <input type="checkbox" name="selected_ids[]" value="<?= (int)$r['request_id'] ?>" class="form-check-input row-check">
                                <?php else: ?>
                                    <span class="fw-bold text-dark">#<?= (int)$r['request_id'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><?= ucfirst($type) ?></span></td>
                            <td><div class="small"><?= $details ?></div></td>
                            <td><div class="small fw-medium"><?= h($requestor) ?></div></td>
                            <td><strong class="text-primary">₹<?= number_format((float)$r['total_amount'], 2) ?></strong></td>
                            <td>
                                <?php 
                                    $c = match($r['status']){ 'SUBMITTED'=>'primary', 'APPROVED'=>'success', 'RETURNED'=>'danger', default=>'light' };
                                    echo '<span class="badge bg-'.$c.'-subtle text-'.$c.' border border-'.$c.'-subtle shadow-none">'.h($r['status']).'</span>';
                                ?>
                            </td>
                            <td class="pe-4 text-end">
                                <?php if ($r['status'] === 'SUBMITTED'): ?>
                                    <a class="btn btn-sm btn-primary rounded-pill px-3 fw-bold shadow-sm" href="apppayment?rid=<?= (int)$r['request_id'] ?>">Review</a>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
<?php } ?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Approver Desk - KMK</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafb; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        .navbar { background: #fff !important; box-shadow: 0 1px 15px rgba(0,0,0,0.04); }
        .nav-tabs .nav-link { border: none; padding: 14px 24px; font-weight: 700; color: #7f8c8d; transition: all 0.2s; border-radius: 0; }
        .nav-tabs .nav-link.active { color: #0d6efd; border-bottom: 3px solid #0d6efd !important; background: transparent; }
        .kpi-card { transition: 0.2s; background: #fff; border: 1px solid #eef2f3; border-radius: 16px; cursor: pointer; }
        .kpi-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.06) !important; }
        .value { font-size: 34px; font-weight: 800; color: #2c3e50; }
        .letter-spacing-1 { letter-spacing: 0.5px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold text-primary" href="?tab=dashboard">KMK FINANCE</a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link active" href="?tab=dashboard">Approver Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="../admin/dashboard">Requester Panel</a></li>
                <li class="nav-item"><a class="nav-link" href="../cashier/dashboard">Cashier Desk</a></li>
            </ul>
            <div class="d-flex align-items-center">
                <div class="text-end me-3">
                    <div class="fw-bold small"><?= h($userName) ?></div>
                    <div class="text-muted small" style="font-size: 0.7rem;">ID: <?= $userId ?> | Approver</div>
                </div>
                <a href="../logout.php" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold">Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 py-4">
    <?php if (isset($_GET['bulk_msg'])): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4">
            <strong>Success:</strong> <?= h($_GET['bulk_msg']) ?>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <h2 class="mb-0 text-dark fw-bold h4">Approval Worklist</h2>
        <div class="d-flex gap-2">
            <a href="/kem/finance/" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold shadow-sm">Finance Dashboard</a>
            <a href="payments_view.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold shadow-sm">Payment History</a>
        </div>
    </div>

    <!-- Category Tabs -->
    <ul class="nav nav-tabs mb-4 border-0">
        <?php foreach ($valid_tabs as $tab): ?>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === $tab ? 'active' : '' ?>" href="?tab=<?= h($tab) ?>&sub=pending">
                    <?= h(ucfirst($tab)) ?><?= $tab === 'dashboard' ? ' (All)' : '' ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">
        <!-- Dashboard Summary -->
        <div class="row g-3 mb-4 text-center">
            <?php 
            $displayRows = ($activeTab === 'dashboard') ? $allRows : array_filter($allRows, fn($r) => $r['request_type'] === $activeTab);
            $tabKPI = ['SUBMITTED' => 0, 'APPROVED' => 0, 'RETURNED' => 0];
            foreach ($displayRows as $r) { if (isset($tabKPI[$r['status']])) $tabKPI[$r['status']]++; }
            
            foreach (['SUBMITTED' => ['Awaiting Action', 'primary', 'pending'], 'APPROVED' => ['Approved Funds', 'success', 'approved'], 'RETURNED' => ['Returned Requests', 'danger', 'returned']] as $st => $cfg): 
            ?>
                <div class="col-md-4">
                    <a class="text-decoration-none" href="?tab=<?= h($activeTab) ?>&sub=<?= $cfg[2] ?>">
                        <div class="p-4 border kpi-card h-100 shadow-sm">
                            <div class="small fw-bold text-muted text-uppercase mb-2 letter-spacing-1"><?= $cfg[0] ?></div>
                            <div class="value text-<?= $cfg[1] ?>"><?= $tabKPI[$st] ?></div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Filter Pills -->
        <ul class="nav nav-pills mb-4 gap-2">
            <?php foreach ($mapStatus as $key => $st): ?>
                <li class="nav-item">
                    <a class="nav-link rounded-pill px-4 fw-bold <?= $activeSub === $key ? 'active shadow-sm' : 'bg-white border text-dark' ?>" 
                       href="?tab=<?= h($activeTab) ?>&sub=<?= $key ?>">
                        <?= ucfirst($key) ?> View
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php
        $tableData = array_filter($displayRows, fn($r) => $r['status'] === $statusFilter);
        $showBulk = ($activeSub === 'pending' && count($tableData) > 0);
        render_approver_table($tableData, ucfirst($activeSub) . " " . ucfirst($activeTab) . " Requests", $showBulk);
        ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Check All functionality
    const checkAll = document.getElementById('checkAll');
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
        });
    }
</script>
</body>
</html>