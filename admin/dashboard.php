<?php

/**
 * REQUESTER: Dashboard with integrated ESI validation and Bulk Actions.
 * FEATURE: Bulk Send to Cashier for Approved requests.
 * STYLE: Solid fill for active navigation elements.
 */
session_start();
require_once("../functions.php");

/**
 * 1. ACTION HANDLERS (Must be BEFORE any HTML output)
 */
$tab = $_GET['tab'] ?? 'dashboard';
$sub = strtolower((string)($_GET['sub'] ?? 'pending'));

// Handle Bulk Send to Cashier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_send_cashier') {
    $ids = $_POST['selected_ids'] ?? [];
    if (!empty($ids)) {
        $now = date('Y-m-d H:i:s');
        $count = 0;
        foreach ($ids as $rid) {
            $rid = (int)$rid;
            // Security check: Only move if currently APPROVED
            $check = exeSql("SELECT status FROM payment_requests WHERE request_id=$rid LIMIT 1");
            if ($check && $check[0]['status'] === 'APPROVED') {
                upData('payment_requests', [
                    'status' => 'READY_FOR_CASHIER',
                    'updated_at' => $now
                ], ["request_id=$rid"]);
                $count++;
            }
        }
        header("Location: ?tab=$tab&sub=cashier&msg=bulk_forwarded&count=$count");
        exit;
    }
}

// Single Send Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_cashier') {
    $rid = (int)($_POST['request_id'] ?? 0);
    $row = exeSql("SELECT status FROM payment_requests WHERE request_id=$rid LIMIT 1");
    if ($row && $row[0]['status'] === 'APPROVED') {
        $now = date('Y-m-d H:i:s');
        upData('payment_requests', [
            'status' => 'READY_FOR_CASHIER',
            'updated_at' => $now
        ], ["request_id=$rid"]);
    }
    header("Location: ?tab=$tab&sub=cashier&msg=forwarded");
    exit;
}

/**
 * 2. ACCESS CONTROL
 */
if (!isset($_SESSION["roleName"]) || ($_SESSION['roleName'] !== 'Requester' && $_SESSION['roleName'] !== 'Admin')) {
    session_destroy();
    echo '<script>alert("Access denied."); window.location.href = "../login.php";</script>';
    exit;
}

$userId = $_SESSION["userId"];
require_once("header.php");
require_once("nav.php");

/**
 * 3. UTILS & LOOKUPS
 */
$activeTab = $tab;
$activeSub = $sub;
$mapStatus = [
    'pending' => 'SUBMITTED',
    'approved' => 'APPROVED',
    'cashier' => 'READY_FOR_CASHIER',
    'paid' => 'PAID',
    'returned' => 'RETURNED'
];
if (!isset($mapStatus[$activeSub])) $activeSub = 'pending';
$statusFilter = $mapStatus[$activeSub];

if (!function_exists('h')) {
    function h($x)
    {
        return htmlspecialchars((string)$x, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$_CACHE = ['V' => [], 'E' => []];

function vendor_name(?int $id): string
{
    global $_CACHE;
    if (!$id) return 'N/A';
    if (isset($_CACHE['V'][$id])) return $_CACHE['V'][$id];
    $r = exeSql("SELECT vendor_name FROM vendors WHERE vendor_id=$id LIMIT 1");
    return $_CACHE['V'][$id] = (string)($r[0]['vendor_name'] ?? 'Unknown');
}

function employee_info(?int $id): array
{
    global $_CACHE;
    if (!$id) return ['name' => 'N/A'];
    if (isset($_CACHE['E'][$id])) return $_CACHE['E'][$id];
    $r = exeSql("SELECT employee_name AS name FROM employees WHERE id=$id LIMIT 1");
    return $_CACHE['E'][$id] = ['name' => (string)($r[0]['name'] ?? 'Unknown')];
}

/**
 * 4. DATA FETCHING
 */
$allRows = exeSql("SELECT * FROM payment_requests ORDER BY updated_at DESC, request_id DESC") ?: [];

/**
 * 5. RENDERER
 */
function render_requester_table(array $rows, string $emptyMsg, string $activeSub)
{
    $isApprovedView = ($activeSub === 'approved');
?>
    <form method="post" id="bulkForm">
        <input type="hidden" name="action" value="bulk_send_cashier">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold text-muted text-uppercase small mb-0">Listings</h6>
            <?php if ($isApprovedView && count($rows) > 0): ?>
                <button type="submit" class="btn btn-sm btn-success rounded-pill px-4 fw-bold shadow-sm">
                    <i class="bi bi-send-check-fill me-1"></i> Send Selected to Cashier
                </button>
            <?php endif; ?>
        </div>

        <div class="card shadow-sm border-0 mb-3 rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="bg-light">
                        <tr class="text-uppercase fw-bold text-muted small">
                            <?php if ($isApprovedView): ?>
                                <th class="ps-4" style="width: 40px;"><input type="checkbox" id="checkAll" class="form-check-input"></th>
                            <?php endif; ?>
                            <th class="<?= !$isApprovedView ? 'ps-4' : '' ?>">Type</th>
                            <th>Details / Breakdown</th>
                            <th>Net Amount</th>
                            <th>Status</th>
                            <th class="pe-4 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?><tr>
                                <td colspan="6" class="text-center py-5 text-muted italic"><?= h($emptyMsg) ?></td>
                            </tr>
                            <?php else: foreach ($rows as $r):
                                $type = strtolower(trim((string)($r['request_type'] ?? 'other')));
                                $p = json_decode($r['payload_json'] ?? '{}', true) ?: [];
                                $hasError = false;
                                $typeLabel = strtoupper($type);

                                if ($type === 'employee') {
                                    $empId = (int)($r['employee_id'] ?? 0);
                                    $details = "<strong>Payroll: " . h(employee_info($empId)['name']) . "</strong><br>";
                                    $details .= "<small class='text-muted'>Gross: ₹" . number_format((float)($p['gross_salary'] ?? 0), 2) . " | PF: ₹" . ($p['pf_deduction'] ?? 0) . " | ESI: ₹" . number_format((float)($p['esi_deduction'] ?? 0), 2) . "</small>";
                                } elseif ($type === 'vendor') {
                                    $details = "Vendor: <strong>" . h(vendor_name((int)$r['vendor_id'])) . "</strong>";
                                } elseif ($type === 'advance') {
                                    $empName = h(employee_info((int)$r['employee_id'])['name']);
                                    $details = "Advance Salary: <strong>$empName</strong><br>";
                                    $details .= "<small class='text-muted'>" . h($p['notes'] ?? 'Salary Advance') . "</small>";
                                    $typeLabel = "ADVANCE";
                                } else {
                                    $details = "Purpose: <strong>" . h($p['purpose'] ?? ($p['custom_purpose'] ?? 'General Expense')) . "</strong>";
                                    $typeLabel = "OTHER";
                                }
                            ?>
                                <tr>
                                    <?php if ($isApprovedView): ?>
                                        <td class="ps-4"><input type="checkbox" name="selected_ids[]" value="<?= (int)$r['request_id'] ?>" class="form-check-input row-check"></td>
                                    <?php endif; ?>
                                    <td class="<?= !$isApprovedView ? 'ps-4' : '' ?>">
                                        <span class="badge bg-secondary text-white border-0 text-uppercase" style="font-size: 0.65rem; padding: 5px 10px;">
                                            <?= $typeLabel ?>
                                        </span>
                                    </td>
                                    <td><?= $details ?></td>
                                    <td><strong class="text-primary">₹<?= number_format((float)$r['total_amount'], 2) ?></strong></td>
                                    <td>
                                        <?php
                                        $c = match ($r['status']) {
                                            'SUBMITTED' => 'primary',
                                            'APPROVED' => 'success',
                                            'READY_FOR_CASHIER' => 'info',
                                            'PAID' => 'secondary',
                                            'RETURNED' => 'danger',
                                            default => 'light'
                                        };
                                        echo '<span class="badge bg-' . $c . ' shadow-none text-uppercase" style="font-size: 0.65rem; padding: 5px 10px;">' . h($r['status']) . '</span>';
                                        ?>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <?php if ($r['status'] === 'APPROVED'): ?>
                                            <button type="button" onclick="sendSingle(<?= (int)$r['request_id'] ?>)" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm fw-bold" style="font-size: 0.7rem;">Send</button>
                                        <?php elseif (in_array($r['status'], ['SUBMITTED', 'RETURNED'])): ?>
                                            <a class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold" style="font-size: 0.7rem;" href="<?= ($type === 'employee' ? 'payroll.php' : 'payment.php') ?>?rid=<?= $r['request_id'] ?>">Edit</a>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                        <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
<?php } ?>

<style>
    body {
        background: #f8fafb;
        font-family: 'Inter', system-ui, sans-serif;
    }

    /* SOLID FILL TAB STYLE */
    .nav-tabs .nav-link {
        border: none;
        padding: 12px 25px;
        font-weight: 700;
        color: #6c757d;
        border-radius: 12px 12px 0 0;
        transition: 0.2s;
    }

    .nav-tabs .nav-link:hover {
        background: #f1f3f5;
    }

    .nav-tabs .nav-link.active {
        background-color: #0d6efd !important;
        color: #fff !important;
        box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.05);
    }

    /* SOLID FILL PILL STYLE */
    .nav-pills .nav-link {
        font-weight: 700;
        color: #495057;
        border: 1px solid #dee2e6;
        margin-right: 5px;
        background: #fff;
        border-radius: 30px;
        padding: 8px 20px;
    }

    .nav-pills .nav-link.active {
        background-color: #0d6efd !important;
        color: #fff !important;
        border-color: #0d6efd;
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25);
    }

    .kpi-card {
        transition: 0.2s;
        background: #fff;
        border: 1px solid #eef2f3;
        border-radius: 16px;
        position: relative;
        cursor: pointer;
    }

    .kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05) !important;
    }
</style>

<div class="container-fluid px-4 mt-4">
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'bulk_forwarded'): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4">
            <strong>Success!</strong> Forwarded <?= (int)$_GET['count'] ?> requests to the cashier.
        </div>
    <?php endif; ?>

    <header class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
        <h2 class="mb-0 text-dark fw-bold h4">KMK Finance Workspace</h2>
        <div class="d-flex gap-2">
            <a href="payroll.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">🚀 Payroll Entry</a>
            <a href="payment.php" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">➕ Payment Entry</a>
        </div>
    </header>

    <ul class="nav nav-tabs mb-4 border-0">
        <?php foreach (['dashboard' => 'All Overview', 'vendor' => 'Vendors', 'employee' => 'Employees', 'expenses' => 'Expenses', 'fixed' => 'Fixed'] as $t => $label): ?>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === $t ? 'active' : '' ?>" href="?tab=<?= h($t) ?>&sub=pending">
                    <?= h($label) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">
        <div class="row g-3 mb-4 text-center">
            <?php
            $displayRows = ($activeTab === 'dashboard') ? $allRows : array_filter($allRows, fn($r) => $r['request_type'] === $activeTab);
            $tabKPI = ['SUBMITTED' => 0, 'APPROVED' => 0, 'READY_FOR_CASHIER' => 0, 'PAID' => 0, 'RETURNED' => 0];
            foreach ($displayRows as $r) {
                if (isset($tabKPI[$r['status']])) $tabKPI[$r['status']]++;
            }

            $kpiConfig = [
                'SUBMITTED' => ['Pending', 'primary', 'pending'],
                'APPROVED' => ['Approved', 'success', 'approved'],
                'READY_FOR_CASHIER' => ['At Cashier', 'info', 'cashier'],
                'PAID' => ['Paid', 'secondary', 'paid'],
                'RETURNED' => ['Returned', 'danger', 'returned']
            ];

            foreach ($kpiConfig as $st => $cfg): ?>
                <div class="col">
                    <a class="text-decoration-none" href="?tab=<?= h($activeTab) ?>&sub=<?= $cfg[2] ?>">
                        <div class="p-3 border kpi-card h-100 shadow-sm">
                            <div class="small fw-bold text-muted text-uppercase mb-1" style="font-size:0.65rem;"><?= $cfg[0] ?></div>
                            <div class="h3 mb-0 fw-bold text-<?= $cfg[1] ?>"><?= $tabKPI[$st] ?></div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <ul class="nav nav-pills mb-4 gap-2">
            <?php foreach ($mapStatus as $key => $st): ?>
                <li class="nav-item">
                    <a class="nav-link rounded-pill px-4 <?= $activeSub === $key ? 'active' : '' ?>" href="?tab=<?= h($activeTab) ?>&sub=<?= h($key) ?>">
                        <?= ucfirst($key) ?> View
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php
        $filtered = array_filter($displayRows, fn($r) => $r['status'] === $statusFilter);
        render_requester_table($filtered, "No $activeSub requests found.", $activeSub);
        ?>
    </div>
</div>

<!-- Form for single send via button -->
<form id="singleForm" method="post" style="display:none;">
    <input type="hidden" name="action" value="send_cashier">
    <input type="hidden" name="request_id" id="singleRid">
</form>

<script>
    function sendSingle(id) {
        if (confirm('Forward this request to cashier?')) {
            document.getElementById('singleRid').value = id;
            document.getElementById('singleForm').submit();
        }
    }

    const checkAll = document.getElementById('checkAll');
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
        });
    }
</script>

<?php require_once("footer.php"); ?>