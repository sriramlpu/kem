<?php
/**
 * APPROVER: Dashboard Integrated with standard portal UI.
 * FIXED: Advance Salary requests now correctly show the Employee Name.
 * FIXED: SQL Lookup uses entity_id for advances table compliance.
 * UPDATED: Integrated DataTables for better list management.
 */
session_start();
require_once("../auth.php");
requireRole(['Approver', 'Admin']);
require_once("../functions.php");

$userName = ($_SESSION['userName'] ?? 'Approver');
$userId = ($_SESSION['userId'] ?? 0);

/* ---------------- Helpers ---------------- */
if (!function_exists('h')) { 
    function h($x) { return htmlspecialchars((string)$x, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } 
}

/* ---------------- Lookups ---------------- */
$_CACHE = ['V' => [], 'E' => [], 'U' => [], 'ADV' => []];

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

/**
 * Fetch Outstanding Advance balance using correct schema columns
 */
function employee_advance_balance(?int $id): float {
    global $_CACHE;
    if (!$id) return 0.0;
    if (isset($_CACHE['ADV'][$id])) return $_CACHE['ADV'][$id];
    // Column in advances table is entity_id, not employee_id
    $r = exeSql("SELECT SUM(amount - IFNULL(recovered_amount, 0)) as bal FROM advances WHERE entity_id=$id AND entity_type='employee' AND status='Active'");
    return $_CACHE['ADV'][$id] = (float)($r[0]['bal'] ?? 0);
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
            $check = exeSql("SELECT status, total_amount, payload_json, request_type FROM payment_requests WHERE request_id=$rid LIMIT 1");
            if ($check && $check[0]['status'] === 'SUBMITTED') {
                $p = json_decode($check[0]['payload_json'] ?? '{}', true) ?: [];
                if ($check[0]['request_type'] === 'employee') {
                    $gross = (float)($p['gross_salary'] ?? 0);
                    $esi = (float)($p['esi_deduction'] ?? 0);
                    if ($gross > 21000 && $esi > 0) continue; 
                }
                upData('payment_requests', [
                    'status' => 'APPROVED',
                    'approved_by' => $approver_id,
                    'approved_at' => $now,
                    'updated_at' => $now
                ], ["request_id=$rid"]);
                $count++;
            }
        }
        header("Location: ?tab=$activeTab&sub=$activeSub&bulk_msg=Successfully Approved $count Requests.");
        exit;
    }
}

$ACTIVE_STATUSES = "('SUBMITTED','APPROVED','RETURNED')";
$allRows = exeSql("SELECT * FROM payment_requests WHERE status IN $ACTIVE_STATUSES ORDER BY updated_at DESC") ?: [];

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
        <div class="card shadow-sm border-0 mb-3 rounded-4 overflow-hidden bg-white p-3">
            <div class="table-responsive">
                <table id="approverTable" class="table table-hover align-middle mb-0 display" style="width:100%">
                    <thead class="bg-light">
                        <tr class="small text-uppercase fw-bold text-secondary">
                            <?php if ($allowBulk): ?>
                                <th class="ps-4 no-sort" style="width: 40px;"><input type="checkbox" id="checkAll" class="form-check-input"></th>
                            <?php else: ?>
                                <th class="ps-4">Req #</th>
                            <?php endif; ?>
                            <th>Category</th>
                            <th>Details / Breakdown</th>
                            <th>Requested By</th>
                            <th>Net Amount</th>
                            <th>Status</th>
                            <th class="pe-4 text-end no-sort">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($rows as $r):
                        $type = (string)($r['request_type'] ?? 'unknown');
                        $p = json_decode($r['payload_json'] ?? '{}', true) ?: [];
                        $requestor = username_lookup((int)($r['requested_by'] ?? 0));
                        $hasError = false;
                        $errorMsg = "";

                        if ($type === 'employee') {
                            $empId = (int)$r['employee_id'];
                            $gross = (float)($p['gross_salary'] ?? 0);
                            $esi = (float)($p['esi_deduction'] ?? 0);
                            $advBal = employee_advance_balance($empId);
                            if ($gross > 21000 && $esi > 0) { $hasError = true; $errorMsg = "ESI Deduction must be 0 for Gross > 21k."; }

                            $details = "<strong>Payroll: " . h(employee_info($empId)['name']) . "</strong><br>";
                            $details .= "<small class='text-muted'>Gross: ₹" . number_format($gross, 2) . " | PF: ₹" . ($p['pf_deduction'] ?? 0) . " | ESI: ₹" . number_format($esi, 2) . "</small>";
                            if ($hasError) $details .= "<br><span class='badge bg-danger rounded-pill small mt-1'>$errorMsg</span>";
                            if ($advBal > 0) $details .= "<br><span class='badge bg-warning-subtle text-dark border-warning small mt-1'>Adv Bal: ₹" . number_format($advBal, 2) . "</span>";
                        } elseif ($type === 'vendor') {
                            $details = "Vendor: <strong>" . h(vendor_name((int)$r['vendor_id'])) . "</strong>";
                        } elseif ($type === 'advance') {
                            $empId = (int)($r['employee_id'] ?? 0);
                            $details = "Advance Salary: <strong>" . h(employee_info($empId)['name']) . "</strong><br>";
                            $details .= "<small class='text-muted'>" . h($p['notes'] ?? 'Personal/Salary Advance') . "</small>";
                        } else {
                            $details = "Purpose: " . h($p['purpose'] ?? ($p['custom_purpose'] ?? 'N/A'));
                        }
                    ?>
                        <tr class="<?= $hasError ? 'table-danger-subtle' : '' ?>">
                            <td class="ps-4">
                                <?php if ($allowBulk): ?>
                                    <input type="checkbox" name="selected_ids[]" value="<?= (int)$r['request_id'] ?>" class="form-check-input row-check" <?= $hasError ? 'disabled' : '' ?>>
                                <?php else: ?>
                                    <span class="fw-bold text-dark">#<?= (int)$r['request_id'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle text-uppercase" style="font-size: 0.65rem;"><?= h($type) ?></span></td>
                            <td><div class="small"><?= $details ?></div></td>
                            <td><div class="small fw-medium"><?= h($requestor) ?></div></td>
                            <td><strong class="text-primary">₹<?= number_format((float)$r['total_amount'], 2) ?></strong></td>
                            <td>
                                <?php 
                                    $c = match($r['status']){ 'SUBMITTED'=>'primary', 'APPROVED'=>'success', 'RETURNED'=>'danger', default=>'light' };
                                    echo '<span class="badge bg-'.$c.' shadow-none text-uppercase" style="font-size: 0.65rem; padding: 5px 10px;">'.h($r['status']).'</span>';
                                ?>
                            </td>
                            <td class="pe-4 text-end">
                                <a class="btn btn-sm <?= $hasError ? 'btn-danger' : 'btn-outline-primary' ?> rounded-pill px-3 fw-bold shadow-sm" style="font-size: 0.7rem;" href="apppayment?rid=<?= (int)$r['request_id'] ?>">Review</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
<?php } ?>

<?php 
require_once("header.php"); 
?>
<style>
    body { background: #f8fafb; }
    /* SOLID FILL TAB STYLE */
    .nav-tabs .nav-link { border: none; padding: 14px 24px; font-weight: 700; color: #7f8c8d; transition: 0.2s; }
    .nav-tabs .nav-link.active { background-color: #0d6efd !important; color: #fff !important; border-radius: 10px 10px 0 0; }

    /* SOLID FILL PILL STYLE */
    .nav-pills .nav-link { font-weight: 700; color: #495057; border: 1px solid #dee2e6; margin-right: 5px; background: #fff; border-radius: 30px; padding: 8px 20px; }
    .nav-pills .nav-link.active { background-color: #0d6efd !important; color: #fff !important; border-color: #0d6efd; box-shadow: 0 4px 12px rgba(13, 110, 253, 0.2); }

    .kpi-card { transition: 0.2s; background: #fff; border: 1px solid #eef2f3; border-radius: 16px; cursor: pointer; }
    .kpi-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.06) !important; }
    .value { font-size: 32px; font-weight: 800; }
    .table-danger-subtle { background-color: #fff5f5 !important; }

    /* DATATABLES CUSTOM STYLING */
    .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #0d6efd !important; color: white !important; border: 1px solid #0d6efd !important; border-radius: 50%; }
    .dataTables_wrapper .dataTables_filter input { border: 1px solid #dee2e6; border-radius: 30px; padding: 6px 15px; margin-bottom: 10px; }
    table.dataTable thead th { border-bottom: 2px solid #f1f3f5 !important; }
</style>
<?php 
require_once("nav.php"); 
?>

<div class="container-fluid">
    <?php if (isset($_GET['bulk_msg'])): ?>
        <div class="alert alert-info border-0 shadow-sm rounded-4 mb-4"><?= h($_GET['bulk_msg']) ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <h2 class="mb-0 text-dark fw-bold h4">Approval Worklist</h2>
        <!-- <div class="d-flex gap-2">
            <a href="payments_view" class="btn btn-sm btn-outline-secondary rounded-pill px-4 fw-bold shadow-sm">View History</a>
        </div> -->
    </div>

    <!-- Category Tabs (Filled) -->
    <ul class="nav nav-tabs mb-4 border-0">
        <?php foreach ($valid_tabs as $tab): ?>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === $tab ? 'active' : '' ?>" href="?tab=<?= h($tab) ?>&sub=pending">
                    <?= h(ucfirst($tab)) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">
        <div class="row g-3 mb-4 text-center">
            <?php 
            $displayRows = ($activeTab === 'dashboard') ? $allRows : array_filter($allRows, fn($r) => $r['request_type'] === $activeTab);
            $tabKPI = ['SUBMITTED' => 0, 'APPROVED' => 0, 'RETURNED' => 0];
            foreach ($displayRows as $r) { if (isset($tabKPI[$r['status']])) $tabKPI[$r['status']]++; }
            
            foreach (['SUBMITTED' => ['Pending', 'primary', 'pending'], 'APPROVED' => ['Approved', 'success', 'approved'], 'RETURNED' => ['Returned', 'danger', 'returned']] as $st => $cfg): 
            ?>
                <div class="col-md-4">
                    <a class="text-decoration-none" href="?tab=<?= h($activeTab) ?>&sub=<?= $cfg[2] ?>">
                        <div class="p-4 border kpi-card h-100 shadow-sm">
                            <div class="small fw-bold text-muted text-uppercase mb-2" style="font-size: 0.65rem;"><?= $cfg[0] ?> Requests</div>
                            <div class="value text-<?= $cfg[1] ?>"><?= $tabKPI[$st] ?></div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Status Pills (Filled) -->
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
        render_approver_table($tableData, ucfirst($activeSub) . " " . ucfirst($activeTab) . " List", $showBulk);
        ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize DataTables
        if (typeof jQuery !== 'undefined' && jQuery.fn.DataTable) {
            jQuery('#approverTable').DataTable({
                "pageLength": 10,
                "order": [], // Keep server-side sort by default
                "language": {
                    "search": "",
                    "searchPlaceholder": "Filter worklist..."
                },
                "columnDefs": [
                    { "orderable": false, "targets": "no-sort" }
                ],
                "dom": '<"d-flex justify-content-between align-items-center mb-2"f>t<"d-flex justify-content-between align-items-center mt-3"ip>'
            });
        }

        const checkAll = document.getElementById('checkAll');
        if (checkAll) {
            checkAll.addEventListener('change', function() {
                document.querySelectorAll('.row-check:not(:disabled)').forEach(cb => cb.checked = this.checked);
            });
        }
    });
</script>

<?php require_once("footer.php"); ?>