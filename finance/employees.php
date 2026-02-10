<?php
/**
 * FINANCE: Employee Directory & Monthly Payout Status
 * Path: finance/employees.php
 * UPDATED: Focus on 8 core components using employee_salary_payments history.
 * FIXED: Filtering logic ensures ONLY processed employees show up.
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
            <h2 class="fw-bold h4 mb-0 text-dark">Salary Disbursal Audit</h2>
            <p class="text-muted small mb-0">List of employees processed for the selected pay period.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="manage_employees.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                <i class="bi bi-person-gear me-1"></i> Manage Employees
            </a>
        </div>
    </div>

    <!-- Filter Component -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body p-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Month</label>
                    <input type="month" id="monthPicker" class="form-control fw-bold" value="<?= date('Y-m') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted text-uppercase">Location</label>
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

    <!-- Data Grid -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="employeesTable" class="table table-hover align-middle mb-0" style="width:100%">
                    <thead>
                        <tr>
                            <th class="ps-4">Staff Info</th>
                            <th class="text-end">Base Gross</th>
                            <th class="text-end">Attendance (LOP)</th>
                            <th class="text-end">Additions (OT/Inc)</th>
                            <th class="text-end">Statutory (PF/ESI)</th>
                            <th class="text-end">Taxes (PT/TDS)</th>
                            <th class="text-end text-warning">Adv. Bal</th>
                            <th class="text-end text-success pe-4">Net Processed</th>
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
            },
            dataSrc: function(json) {
                if(json.status === 'error') {
                    console.error("API Error: " + json.message);
                    return [];
                }
                return json.data || [];
            },
            error: function(xhr, error, code) {
                console.log("XHR Response: ", xhr.responseText);
            }
        },
        columns: [
            { data: null, className: 'ps-4', render: r => {
                if(!r) return '';
                return `<div class="fw-bold text-dark text-uppercase" style="font-size:0.8rem;">${r.employee_name || 'N/A'}</div>
                        <div class="small text-muted" style="font-size:0.6rem;">${r.role || ''} • ${r.branch_name || ''}</div>`;
            }},
            { data: 'salary', className: 'text-end fw-semibold', render: d => '₹' + (parseFloat(d) || 0).toLocaleString('en-IN') },
            { data: 'lop', className: 'text-end text-danger fw-bold', render: d => d > 0 ? `-₹${(parseFloat(d)||0).toLocaleString()}` : '-' },
            { data: null, className: 'text-end', render: r => {
                if(!r) return '';
                return `<div class="small text-success fw-bold">+₹${(parseFloat(r.ot)||0).toLocaleString()} <span class="text-muted" style="font-size:0.5rem;">(OT)</span></div>
                        <div class="small text-success fw-bold">+₹${(parseFloat(r.inc)||0).toLocaleString()} <span class="text-muted" style="font-size:0.5rem;">(INC)</span></div>`;
            }},
            { data: null, className: 'text-end', render: r => {
                if(!r) return '';
                return `<div class="small fw-bold" style="color:#856404">PF: ₹${(parseFloat(r.pf)||0).toLocaleString()}</div>
                        <div class="small fw-bold" style="color:#055160">ESI: ₹${(parseFloat(r.esi)||0).toLocaleString()}</div>`;
            }},
            { data: null, className: 'text-end', render: r => {
                if(!r) return '';
                return `<div class="small text-secondary fw-bold">PT: ₹${(parseFloat(r.pt)||0).toLocaleString()}</div>
                        <div class="small text-secondary fw-bold">TDS: ₹${(parseFloat(r.tds)||0).toLocaleString()}</div>`;
            }},
            { data: 'advance_bal', className: 'text-end text-dark fw-bold bg-warning-subtle', render: d => '₹' + (parseFloat(d)||0).toLocaleString('en-IN') },
            { data: 'processed_net', className: 'text-end text-success fw-bold pe-4', render: d => '₹' + (parseFloat(d)||0).toLocaleString('en-IN', {minimumFractionDigits: 2}) }
        ],
        pageLength: 25,
        dom: '<"p-3 d-flex justify-content-between align-items-center"f>rt<"p-3 d-flex justify-content-between align-items-center"ip>',
        language: { 
            search: "", 
            searchPlaceholder: "Search processed staff...",
            emptyTable: "No payroll records found for the selected month."
        }
    });

    $('#branchFilter, #monthPicker').on('change', () => table.ajax.reload());
});
</script>

<?php include 'footer.php'; ?>