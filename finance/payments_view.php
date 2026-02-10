<?php
/**
 * FINANCE: Payments View (Unified Ledger)
 * UPDATED: Integrated logic from payments_view1.php with modern UI.
 * LOGIC: Combines Vendor, Employee, and Expense disbursements into one searchable table.
 */
require_once("../auth.php");
requireRole(['Admin', 'Finance']);
require_once("../functions.php");

// 1. AJAX Endpoint for DataTables
if (isset($_GET['ajax']) && $_GET['ajax'] === 'list') {
    $type      = s($_GET['type']);
    $vendor_id = i($_GET['vendor_id']);
    $emp_id    = i($_GET['employee_id']);
    $branch_id = i($_GET['branch_id']);
    $from      = s($_GET['from']) ?: date('Y-m-d', strtotime('-1 year'));
    $to        = s($_GET['to'])   ?: date('Y-m-d');

    $combined = [];

    // A. Vendor Payments
    if ($type === '' || $type === 'vendor') {
        $w = ["p.paid_at BETWEEN '$from 00:00:00' AND '$to 23:59:59'"];
        if ($vendor_id) $w[] = "p.vendor_id = $vendor_id";
        if ($branch_id) $w[] = "p.branch_id = $branch_id";
        
        $sql = "SELECT p.*, v.vendor_name, g.grn_number 
                FROM vendor_grn_payments p 
                LEFT JOIN vendors v ON v.vendor_id = p.vendor_id 
                LEFT JOIN goods_receipts g ON g.grn_id = p.grn_id 
                WHERE " . implode(' AND ', $w);
        $rows = exeSql($sql) ?: [];
        foreach ($rows as $r) {
            $combined[] = [
                'id'     => $r['id'],
                'source' => 'vendor',
                'date'   => date('d-M-Y', strtotime($r['paid_at'])),
                'party'  => $r['vendor_name'] ?? 'Vendor #'.$r['vendor_id'],
                'note'   => "GRN: ".($r['grn_number'] ?: 'N/A'),
                'mode'   => strtoupper($r['method']),
                'ref'    => $r['payment_reference'],
                'net'    => (float)$r['amount'],
                'adv'    => (float)$r['advance_used'],
                'total'  => (float)$r['amount'] + (float)$r['advance_used'] + (float)$r['redemption_used'],
                'v_no'   => $r['voucher_no'],
                'i_no'   => $r['invoice_no']
            ];
        }
    }

    // B. Employee Salary
    if ($type === '' || $type === 'employee') {
        $w = ["p.paid_at BETWEEN '$from 00:00:00' AND '$to 23:59:59'"];
        if ($emp_id) $w[] = "p.employee_id = $emp_id";
        
        $sql = "SELECT p.*, e.employee_name, e.role 
                FROM employee_salary_payments p 
                LEFT JOIN employees e ON e.id = p.employee_id 
                WHERE " . implode(' AND ', $w);
        $rows = exeSql($sql) ?: [];
        foreach ($rows as $r) {
            $combined[] = [
                'id'     => $r['id'],
                'source' => 'employee',
                'date'   => date('d-M-Y', strtotime($r['paid_at'])),
                'party'  => $r['employee_name'] ?? 'Emp #'.$r['employee_id'],
                'note'   => "Salary: ".$r['pay_period']." (".$r['role'].")",
                'mode'   => 'SALARY',
                'ref'    => $r['payment_reference'] ?: 'Payroll',
                'net'    => (float)$r['amount'],
                'adv'    => (float)$r['advance'],
                'total'  => (float)$r['amount'] + (float)$r['advance'],
                'v_no'   => $r['voucher_no'],
                'i_no'   => $r['invoice_no']
            ];
        }
    }

    // C. Office Expenses
    if ($type === '' || $type === 'expense') {
        $w = ["p.paid_at BETWEEN '$from 00:00:00' AND '$to 23:59:59'"];
        $sql = "SELECT * FROM expenses p WHERE " . implode(' AND ', $w);
        $rows = exeSql($sql) ?: [];
        foreach ($rows as $r) {
            $combined[] = [
                'id'     => $r['id'],
                'source' => 'expense',
                'date'   => date('d-M-Y', strtotime($r['paid_at'])),
                'party'  => $r['purpose'],
                'note'   => $r['remark'] ?: 'General',
                'mode'   => strtoupper($r['method']),
                'ref'    => $r['payment_reference'],
                'net'    => (float)$r['amount'],
                'adv'    => (float)$r['advance'],
                'total'  => (float)$r['amount'] + (float)$r['advance'],
                'v_no'   => $r['voucher_no'],
                'i_no'   => $r['invoice_no']
            ];
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['data' => $combined]);
    exit;
}

include 'header.php';
include 'nav.php';

$vendors = exeSql("SELECT vendor_id, vendor_name FROM vendors ORDER BY vendor_name");
$employees = exeSql("SELECT id, employee_name FROM employees ORDER BY employee_name");
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <div>
            <h2 class="fw-bold h4 mb-0 text-dark">Unified Payment History</h2>
            <p class="text-muted small mb-0">Consolidated list of all fund outflows from the system.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary rounded-pill px-4 fw-bold shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#filterPanel">
                <i class="bi bi-funnel"></i> Filters
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="collapse show" id="filterPanel">
        <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">Category</label>
                        <select id="f_type" class="form-select">
                            <option value="">All Types</option>
                            <option value="vendor">Vendor Payments</option>
                            <option value="employee">Employee Salary</option>
                            <option value="expense">Office Expenses</option>
                        </select>
                    </div>
                    <div class="col-md-2" id="vendor_wrap">
                        <label class="form-label small fw-bold text-muted">Vendor</label>
                        <select id="f_vendor" class="form-select select2">
                            <option value="">All Vendors</option>
                            <?php foreach($vendors as $v): ?><option value="<?= $v['vendor_id'] ?>"><?= h($v['vendor_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2" id="employee_wrap" style="display:none;">
                        <label class="form-label small fw-bold text-muted">Employee</label>
                        <select id="f_employee" class="form-select select2">
                            <option value="">All Employees</option>
                            <?php foreach($employees as $e): ?><option value="<?= $e['id'] ?>"><?= h($e['employee_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">From</label>
                        <input type="date" id="f_from" class="form-control" value="<?= date('Y-01-01') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">To</label>
                        <input type="date" id="f_to" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-2">
                        <button id="btn_refresh" class="btn btn-primary w-100 rounded-3 fw-bold">Search</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="paymentsTable" class="table table-hover align-middle mb-0" style="width:100%">
                    <thead>
                        <tr>
                            <th class="ps-4">Date</th>
                            <th>Category</th>
                            <th>Payee / Purpose</th>
                            <th>Voucher / Ref</th>
                            <th class="text-end">Cash/Bank</th>
                            <th class="text-end">Adjustment</th>
                            <th class="text-end">Total</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.select2').select2({ theme: 'bootstrap4', width: '100%' });

    let table = $('#paymentsTable').DataTable({
        ajax: {
            url: 'payments_view.php?ajax=list',
            data: function(d) {
                d.type = $('#f_type').val();
                d.vendor_id = $('#f_vendor').val();
                d.employee_id = $('#f_employee').val();
                d.from = $('#f_from').val();
                d.to = $('#f_to').val();
            }
        },
        columns: [
            { data: 'date', className: 'ps-4 fw-bold text-dark' },
            { data: 'source', render: d => {
                const colors = { vendor: 'danger', employee: 'primary', expense: 'secondary' };
                return `<span class="badge badge-soft-${colors[d]} text-uppercase" style="font-size:0.6rem;">${d}</span>`;
            }},
            { data: null, render: r => `<div><strong>${r.party}</strong></div><div class="small text-muted">${r.note}</div>` },
            { data: null, render: r => `<div><small class="fw-bold">${r.v_no}</small></div><div class="small text-muted">${r.ref || '-'}</div>` },
            { data: 'net', className: 'text-end fw-semibold', render: d => '₹' + d.toLocaleString('en-IN', {minimumFractionDigits: 2}) },
            { data: 'adv', className: 'text-end text-danger small', render: d => '₹' + d.toLocaleString('en-IN', {minimumFractionDigits: 2}) },
            { data: 'total', className: 'text-end fw-bold text-primary', render: d => '₹' + d.toLocaleString('en-IN', {minimumFractionDigits: 2}) },
            { data: null, className: 'text-center', orderable: false, render: r => {
                return `<a href="print_voucher.php?id=${r.id}&type=${r.source}&voucher=${encodeURIComponent(r.v_no)}&invoice=${encodeURIComponent(r.i_no)}" target="_blank" class="btn btn-sm btn-light border rounded-pill px-3 fw-bold">Print</a>`;
            }}
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        dom: '<"p-3 d-flex justify-content-between align-items-center"f>rt<"p-3 d-flex justify-content-between align-items-center"ip>',
        language: { search: "", searchPlaceholder: "Filter ledger..." }
    });

    $('#f_type').on('change', function() {
        const v = $(this).val();
        $('#vendor_wrap').toggle(v === '' || v === 'vendor');
        $('#employee_wrap').toggle(v === 'employee');
    });

    $('#btn_refresh').on('click', () => table.ajax.reload());
});
</script>

<?php include 'footer.php'; ?>