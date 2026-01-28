<?php
/**
 * CASHIER: Dashboard Integrated with standard portal UI and DataTables.
 * FIXED: Moved POST logic to the top to prevent "headers already sent" error.
 */
require_once("../auth.php");
requireRole(['Cashier', 'Admin']);
require_once("../functions.php"); // Ensure core DB functions are available for logic

/**
 * 1. ACTION HANDLERS (Must be before header/nav includes)
 */
$active = $_GET['tab'] ?? 'dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_pay') {
    $ids = $_POST['selected_ids'] ?? [];
    if (!empty($ids)) {
        $now = date('Y-m-d H:i:s');
        $count = 0;
        foreach ($ids as $rid) {
            $rid = (int)$rid;
            $check = exeSql("SELECT status, request_type, employee_id, total_amount FROM payment_requests WHERE request_id=$rid LIMIT 1");
            if ($check && $check[0]['status'] === 'READY_FOR_CASHIER') {
                $type = $check[0]['request_type'];
                $eid = (int)($check[0]['employee_id'] ?? 0);
                $amt = (float)$check[0]['total_amount'];

                if ($type === 'advance') {
                    insData('advances', [
                        'entity_id' => $eid, 
                        'entity_type' => 'employee', 
                        'amount' => $amt,
                        'status' => 'Active', 
                        'description' => 'Bulk Paid Adv #' . $rid, 
                        'created_at' => $now
                    ]);
                    exeSql("UPDATE employees SET advance = advance + $amt WHERE id=$eid LIMIT 1");
                }

                upData('payment_requests', ['status' => 'PAID', 'updated_at' => $now], ["request_id=$rid"]);
                $count++;
            }
        }
        header("Location: ?tab=$active&msg=bulk_paid&count=$count");
        exit;
    }
}

// 2. Integrated UI Includes (Starts HTML output)
require_once("header.php");
require_once("nav.php");

/* ---------------- Lookups ---------------- */
$_V = []; $_E = []; $_U = [];

function vendor_name(?int $id): string {
    global $_V;
    if (!$id) return 'N/A';
    if (isset($_V[$id])) return $_V[$id];
    $r = exeSql("SELECT vendor_name FROM vendors WHERE vendor_id=$id LIMIT 1");
    return $_V[$id] = (string)($r[0]['vendor_name'] ?? 'Unknown');
}

function employee_name(?int $id): string {
    global $_E;
    if (!$id) return 'N/A';
    if (isset($_E[$id])) return $_E[$id];
    $r = exeSql("SELECT employee_name FROM employees WHERE id=$id LIMIT 1");
    return $_E[$id] = (string)($r[0]['employee_name'] ?? 'Unknown');
}

function user_name(?int $id): string {
    global $_U;
    if (!$id) return 'System';
    if (isset($_U[$id])) return $_U[$id];
    $r = exeSql("SELECT username FROM users WHERE user_id=$id LIMIT 1");
    return $_U[$id] = (string)($r[0]['username'] ?? 'User#' . $id);
}

/* ---------------- Data Parameters ---------------- */
$valid_tabs = ['dashboard', 'vendor', 'employee', 'advance', 'expenses', 'fixed'];
if (!in_array($active, $valid_tabs)) $active = 'dashboard';

$allRows = exeSql("SELECT * FROM payment_requests WHERE status IN ('READY_FOR_CASHIER','PAID') ORDER BY updated_at DESC") ?: [];

/**
 * Table Renderer with DataTables support
 */
function render_cashier_table(array $rows, string $heading, string $tableId) { 
    $isPending = (strpos($heading, 'Ready') !== false);
    ?>
    <form method="post" class="bulk-pay-form">
        <input type="hidden" name="action" value="bulk_pay">
        
        <div class="d-flex justify-content-between align-items-center mb-3 mt-4">
            <h6 class="fw-bold text-muted text-uppercase small mb-0"><?= htmlspecialchars($heading) ?></h6>
            <?php if ($isPending && count($rows) > 0): ?>
                <button type="submit" class="btn btn-sm btn-success rounded-pill px-4 fw-bold shadow-sm">Bulk Mark as Paid</button>
            <?php endif; ?>
        </div>

        <div class="card shadow-sm border-0 mb-4 rounded-4 overflow-hidden bg-white">
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table id="<?= $tableId ?>" class="table table-hover align-middle mb-0 small datatable-init" style="width:100%">
                        <thead class="bg-light">
                            <tr class="text-uppercase fw-bold text-muted small">
                                <?php if ($isPending): ?>
                                    <th class="no-sort" style="width: 40px;"><input type="checkbox" class="checkAll form-check-input"></th>
                                <?php endif; ?>
                                <th>Req #</th>
                                <th>Category</th>
                                <th>Details / Payee</th>
                                <th>Amount</th>
                                <th>Requested By</th>
                                <th>Updated</th>
                                <th class="text-end no-sort">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r):
                                $type = $r['request_type'];
                                $p = json_decode($r['payload_json'] ?? '{}', true) ?: [];
                                
                                if ($type === 'vendor') {
                                    $details = "Vendor: <strong>" . htmlspecialchars(vendor_name((int)$r['vendor_id'])) . "</strong>";
                                } elseif ($type === 'employee' || $type === 'advance') {
                                    $details = ($type === 'advance' ? 'Advance: ' : 'Payroll: ') . "<strong>" . htmlspecialchars(employee_name((int)$r['employee_id'])) . "</strong>";
                                } else {
                                    $details = "Purpose: <strong>" . htmlspecialchars($p['purpose'] ?? ($p['custom_purpose'] ?? 'General')) . "</strong>";
                                }
                            ?>
                                <tr>
                                    <?php if ($isPending): ?>
                                        <td><input type="checkbox" name="selected_ids[]" value="<?= (int)$r['request_id'] ?>" class="row-check form-check-input"></td>
                                    <?php endif; ?>
                                    <td class="fw-bold text-muted">#<?= (int)$r['request_id'] ?></td>
                                    <td><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle text-uppercase" style="font-size:0.6rem;"><?= htmlspecialchars($type) ?></span></td>
                                    <td><?= $details ?></td>
                                    <td><strong class="text-primary">₹<?= number_format((float)$r['total_amount'], 2) ?></strong></td>
                                    <td><div class="small fw-medium"><?= htmlspecialchars(user_name((int)$r['requested_by'])) ?></div></td>
                                    <td class="small text-muted"><?= date('d M Y H:i', strtotime($r['updated_at'])) ?></td>
                                    <td class="text-end">
                                        <?php if ($r['status'] === 'READY_FOR_CASHIER'): ?>
                                            <a class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold" style="font-size:0.7rem;" href="cpayment.php?rid=<?= $r['request_id'] ?>">Pay Now</a>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:0.6rem;">COMPLETED</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </form>
<?php } ?>

<style>
    .kpi-card { border-radius: 16px; background: #fff; border: 1px solid #eef2f3; transition: 0.2s; }
    .kpi-card .value { font-size: 28px; font-weight: 800; }
    .nav-pills-custom .nav-link { border-radius: 30px; font-weight: 600; padding: 8px 20px; }
    .header-actions .btn { font-size: 0.8rem; font-weight: 600; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #0d6efd !important; color: white !important; border-radius: 50%; }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3 flex-wrap gap-3">
        <h2 class="fw-bold h4 mb-0 text-dark">Cashier Dashboard</h2>
        
        <div class="header-actions d-flex gap-2 flex-wrap">
            <a href="advance_history" class="btn btn-outline-info rounded px-3">Advance History</a>
            <a href="payments_view" class="btn btn-warning text-dark rounded px-3">Payment View</a>
            <a href="vendor_redemption_management" class="btn btn-success rounded px-3">Manage Redemption</a>
            
            <?php if (isset($role) && $role === 'Admin'): ?>
            <div class="dropdown">
                <button class="btn btn-primary dropdown-toggle rounded px-3" type="button" data-bs-toggle="dropdown">Dashboards</button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><a class="dropdown-item" href="../admin/dashboard.php">Requester Panel</a></li>
                    <li><a class="dropdown-item" href="../approver/dashboard.php">Approver Panel</a></li>
                    <li><a class="dropdown-item" href="../finance/">Finance Overview</a></li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Type Filters -->
    <ul class="nav nav-pills nav-pills-custom mb-4 gap-2 border-0">
        <?php foreach ($valid_tabs as $t): ?>
            <li class="nav-item">
                <a class="nav-link <?= $active === $t ? 'active shadow' : 'bg-white border text-dark' ?>" href="?tab=<?= htmlspecialchars($t) ?>">
                    <?= htmlspecialchars(ucfirst($t)) ?><?= $t === 'dashboard' ? ' (All)' : '' ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">
        <?php 
        $filtered = ($active === 'dashboard') ? $allRows : array_filter($allRows, fn($r) => $r['request_type'] === $active);
        $pendingRows = array_filter($filtered, fn($r) => $r['status'] === 'READY_FOR_CASHIER');
        $paidRows = array_filter($filtered, fn($r) => $r['status'] === 'PAID');
        ?>

        <div class="row g-3 mb-2 text-center">
            <div class="col-md-6">
                <div class="p-4 kpi-card shadow-sm border-start border-4 border-warning">
                    <div class="small fw-bold text-muted text-uppercase mb-1">Awaiting Disbursal</div>
                    <div class="value text-warning"><?= count($pendingRows) ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-4 kpi-card shadow-sm border-start border-4 border-success">
                    <div class="small fw-bold text-muted text-uppercase mb-1">Completed Payments</div>
                    <div class="value text-success"><?= count($paidRows) ?></div>
                </div>
            </div>
        </div>

        <?php 
        render_cashier_table($pendingRows, "Ready for Payment - " . ucfirst($active), "pendingTable");
        render_cashier_table($paidRows, "Paid/Completed - " . ucfirst($active), "completedTable");
        ?>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.datatable-init').each(function() {
        $(this).DataTable({
            "order": [[ 1, "desc" ]],
            "pageLength": 10,
            "columnDefs": [{ "targets": "no-sort", "orderable": false }],
            "dom": '<"d-flex justify-content-between align-items-center mb-2"f>t<"d-flex justify-content-between align-items-center mt-3"ip>',
            "language": { "search": "", "searchPlaceholder": "Search list..." }
        });
    });

    $(document).on('change', '.checkAll', function() {
        const table = $(this).closest('table');
        table.find('.row-check').prop('checked', this.checked);
    });
});
</script>

<?php require_once("footer.php"); ?>