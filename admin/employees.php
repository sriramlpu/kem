<?php
/**
 * FINANCE: Employee Directory & Monthly Payout Status
 * Path: finance/employees.php
 * UPDATED: Synchronized with Simplified Payroll (Removed HRA/DA/Basic).
 */
require_once("../auth.php");
requireRole(['Admin', 'Finance']);
require_once("../functions.php");

include 'header.php';
include 'nav.php';

$branches = exeSql("SELECT branch_id, branch_name FROM branches ORDER BY branch_name") ?: [];
?>

<div class="container-fluid px-4 mt-2">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <div>
            <h2 class="fw-bold h4 mb-0 text-dark">Employee Disbursal Tracker</h2>
            <p class="text-muted small mb-0">Monthly payroll tracking with statutory deductions and net payout oversight.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="manage_employees.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                <i class="bi bi-person-gear me-1"></i> Manage Employees
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body p-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Pay Period</label>
                    <div class="input-group">
                        <button class="btn btn-outline-secondary" id="btn-prev"><i class="bi bi-chevron-left"></i></button>
                        <input type="month" id="monthPicker" class="form-control text-center fw-bold" value="<?= date('Y-m') ?>">
                        <button class="btn btn-outline-secondary" id="btn-next"><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted text-uppercase">Branch Filter</label>
                    <select id="branchFilter" class="form-select select2">
                        <option value="0">All Branches</option>
                        <?php foreach($branches as $b): ?>
                            <option value="<?= $b['branch_id'] ?>"><?= h($b['branch_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 ms-auto">
                    <button class="btn btn-dark w-100 rounded-pill fw-bold" onclick="table.ajax.reload()">
                        <i class="bi bi-arrow-repeat me-1"></i> Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="employeesTable" class="table table-hover align-middle mb-0" style="width:100%">
                    <thead>
                        <tr>
                            <th class="ps-4">Employee Info</th>
                            <th class="text-end">Master Gross</th>
                            <th class="text-end">LOP / OT</th>
                            <th class="text-end">Inc.</th>
                            <th class="text-end text-danger">Statutory (PF/ESI)</th>
                            <th class="text-end text-secondary">TDS</th>
                            <th class="text-end text-warning">Adv. Bal</th>
                            <th class="text-end text-success pe-4">Disbursed Amt</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
let table;
$(document).ready(function() {
    $('.select2').select2({ theme: 'bootstrap4', width: '100%' });

    table = $('#employeesTable').DataTable({
        ajax: {
            url: 'api/employee_api.php?action=list',
            data: function(d) {
                d.branch_id = $('#branchFilter').val();
                d.yyyymm = $('#monthPicker').val().replace('-', '');
            }
        },
        columns: [
            { data: null, className: 'ps-4', render: r => `
                <div class="fw-bold text-dark text-uppercase" style="font-size:0.85rem;">${r.employee_name}</div>
                <div class="small text-muted text-uppercase" style="font-size:0.6rem;">${r.role} • ${r.branch_name}</div>` 
            },
            { data: 'salary', className: 'text-end fw-bold', render: d => '₹' + d.toLocaleString('en-IN') },
            { data: null, className: 'text-end', render: r => `
                <div class="small text-danger fw-bold">-₹${r.lop.toLocaleString()} <span class="text-muted" style="font-size:0.5rem;">(LOP)</span></div>
                <div class="small text-success fw-bold">+₹${r.ot.toLocaleString()} <span class="text-muted" style="font-size:0.5rem;">(OT)</span></div>`
            },
            { data: 'inc', className: 'text-end text-success fw-bold', render: d => '₹' + d.toLocaleString('en-IN') },
            { data: null, className: 'text-end text-danger fw-bold', render: r => `
                <div class="small">PF: ₹${r.pf.toLocaleString()}</div>
                <div class="small">ESI: ₹${r.esi.toLocaleString()}</div>`
            },
            { data: 'tds', className: 'text-end text-secondary fw-bold', render: d => '₹' + d.toLocaleString('en-IN') },
            { data: 'advance', className: 'text-end text-dark fw-bold bg-warning-subtle', render: d => '₹' + d.toLocaleString('en-IN') },
            { data: 'paid', className: 'text-end text-success fw-bold pe-4', render: d => '₹' + d.toLocaleString('en-IN', {minimumFractionDigits: 2}) }
        ],
        pageLength: 25,
        dom: '<"p-3 d-flex justify-content-between align-items-center"f>rt<"p-3 d-flex justify-content-between align-items-center"ip>',
        language: { search: "", searchPlaceholder: "Find staff..." }
    });

    $('#branchFilter, #monthPicker').on('change', () => table.ajax.reload());
    $('#btn-prev').click(() => shiftMonth(-1));
    $('#btn-next').click(() => shiftMonth(1));
    
    function shiftMonth(delta) {
        let [y, m] = $('#monthPicker').val().split('-').map(Number);
        let d = new Date(y, m - 1 + delta);
        $('#monthPicker').val(d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0'));
        table.ajax.reload();
    }
});
</script>

<?php include 'footer.php'; ?>