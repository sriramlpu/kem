<?php
/**
 * CASHIER: Dashboard Integrated with standard portal UI and DataTables.
 * Path: cdashboard.php
 * UPDATED: Synchronized Employee Breakdown with Simplified Payroll (OT, Incentives, TDS, LOP).
 * UPDATED: Added color variations (Red for LOP) in the details view.
 */
require_once("../auth.php");
requireRole(['Cashier', 'Admin']);
require_once("../functions.php");

$active = $_GET['tab'] ?? 'dashboard';

// Integrated UI Includes
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
    ?>
    <div class="d-flex justify-content-between align-items-center mb-3 mt-4">
        <h6 class="fw-bold text-muted text-uppercase small mb-0"><?= htmlspecialchars($heading) ?></h6>
    </div>

    <div class="card shadow-sm border-0 mb-4 rounded-4 overflow-hidden bg-white">
        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="<?= $tableId ?>" class="table table-hover align-middle mb-0 small datatable-init" style="width:100%">
                    <thead class="bg-light">
                        <tr class="text-uppercase fw-bold text-muted small">
                            <th>Req #</th>
                            <th>Category</th>
                            <th>Details / Payee</th>
                            <th>Approved Amount</th>
                            <th>Requested By</th>
                            <th>Last Update</th>
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
                                
                                // UPDATED: Detailed Snapshot for Cashier
                                if($type === 'employee'){
                                    $details .= "<div class='small text-muted mt-1' style='font-size:0.65rem;'>";
                                    $details .= "Gross: ₹".number_format((float)($p['gross_salary']??0),0)." | ";
                                    if((float)($p['ot_amount']??0) > 0) $details .= "OT: ₹".number_format((float)$p['ot_amount'],0)." | ";
                                    if((float)($p['incentives']??0) > 0) $details .= "Inc: ₹".number_format((float)$p['incentives'],0)." | ";
                                    $details .= "Deductions: ₹".number_format((float)($p['pf_deduction']??0) + (float)($p['esi_deduction']??0) + (float)($p['tax_deduction']??0) + (float)($p['tds_deduction']??0),0);
                                    if((float)($p['lop_amount']??0) > 0) $details .= " | <span class='text-danger fw-bold'>LOP: -₹".number_format((float)$p['lop_amount'],0)."</span>";
                                    $details .= "</div>";
                                }
                            } else {
                                $details = "Purpose: <strong>" . htmlspecialchars($p['purpose'] ?? ($p['custom_purpose'] ?? 'General')) . "</strong>";
                            }
                        ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?= (int)$r['request_id'] ?></td>
                                <td><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle text-uppercase" style="font-size:0.6rem;"><?= htmlspecialchars($type) ?></span></td>
                                <td><?= $details ?></td>
                                <td><strong class="text-primary">₹<?= number_format((float)$r['total_amount'], 2) ?></strong></td>
                                <td><div class="small fw-medium"><?= htmlspecialchars(user_name((int)$r['requested_by'])) ?></div></td>
                                <td class="small text-muted"><?= date('d M Y H:i', strtotime($r['updated_at'])) ?></td>
                                <td class="text-end">
                                    <?php if ($r['status'] === 'READY_FOR_CASHIER'): ?>
                                        <a class="btn btn-sm btn-primary rounded-pill px-4 fw-bold shadow-sm" style="font-size:0.7rem;" href="cpayment?rid=<?= $r['request_id'] ?>">Disburse</a>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:0.6rem;">PAID</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php } ?>

<style>
    .kpi-card { border-radius: 16px; background: #fff; border: 1px solid #eef2f3; transition: 0.2s; }
    .kpi-card .value { font-size: 28px; font-weight: 800; }
    .nav-pills-custom .nav-link { border-radius: 30px; font-weight: 600; padding: 8px 20px; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #0d6efd !important; color: white !important; border-radius: 50%; }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3 flex-wrap gap-3">
        <h2 class="fw-bold h4 mb-0 text-dark">Cashier Payment Desk</h2>
        
        <div class="header-actions d-flex gap-2 flex-wrap">
            <a href="advance_history" class="btn btn-outline-primary rounded px-3 fw-bold btn-sm">Advances</a>
            <a href="payments_view" class="btn btn-outline-dark rounded px-3 fw-bold btn-sm">Payment History</a>
            <a href="vendor_redemption_management" class="btn btn-outline-success rounded px-3 fw-bold btn-sm">Redemption</a>
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
                <div class="p-4 kpi-card shadow-sm border-start border-4 border-primary">
                    <div class="small fw-bold text-muted text-uppercase mb-1">Awaiting Disbursal</div>
                    <div class="value text-primary"><?= count($pendingRows) ?></div>
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
        render_cashier_table($pendingRows, "Awaiting Disbursal - " . ucfirst($active), "pendingTable");
        render_cashier_table($paidRows, "Recent Disbursals - " . ucfirst($active), "completedTable");
        ?>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.datatable-init').each(function() {
        $(this).DataTable({
            "order": [[ 0, "desc" ]],
            "pageLength": 10,
            "columnDefs": [{ "targets": "no-sort", "orderable": false }],
            "dom": '<"d-flex justify-content-between align-items-center mb-2"f>t<"d-flex justify-content-between align-items-center mt-3"ip>',
            "language": { "search": "", "searchPlaceholder": "Search list..." }
        });
    });
});
</script>

<?php require_once("footer.php"); ?>