<?php
/**
 * FINANCE: Master Employee Directory (Simplified)
 * Path: finance/manage_employees.php
 * UPDATED: Strictly removed Basic Pay, HRA, and DA.
 */
require_once("../auth.php");
requireRole(['Admin', 'Finance']);
require_once("../functions.php");

include 'header.php';
include 'nav.php';

$branches = exeSql("SELECT branch_id, branch_name FROM branches ORDER BY branch_name") ?: [];
?>

<style>
    .modal-xl { max-width: 1000px; }
    .calc-box { background: #0f172a; color: #fff; border-radius: 16px; padding: 25px; border: 1px solid #1e293b; }
    .form-label { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; }
    .badge-soft-success { background: #dcfce7; color: #15803d; }
    .badge-soft-danger { background: #fee2e2; color: #b91c1c; }
    .amount-hint { font-size: 0.75rem; font-weight: 700; margin-top: 4px; display: block; }
    .section-title { font-size: 0.75rem; font-weight: 900; color: #3b82f6; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #eff6ff; padding-bottom: 8px; margin-bottom: 20px; }
</style>

<div class="container-fluid px-4 mt-2">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <div>
            <h2 class="fw-bold h4 mb-0 text-dark">Employee Master Registry</h2>
            <p class="text-muted small mb-0">Manage profile identities and core salary structures.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="employees.php" class="btn btn-outline-secondary rounded-pill px-4 fw-bold shadow-sm">
                <i class="bi bi-cash-stack me-1"></i> Payout History
            </a>
            <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="openEmpModal()">
                <i class="bi bi-person-plus me-1"></i> Add New Employee
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body p-3 text-center">
            <div class="row g-3 align-items-end justify-content-center">
                <div class="col-md-4 text-start">
                    <label class="form-label ps-1">Filter by Branch</label>
                    <select id="branchFilter" class="form-select select2">
                        <option value="0">All Branches</option>
                        <?php foreach($branches as $b): ?>
                            <option value="<?= $b['branch_id'] ?>"><?= h($b['branch_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
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
                <table id="masterEmployeesTable" class="table table-hover align-middle mb-0" style="width:100%">
                    <thead>
                        <tr>
                            <th class="ps-4">UID / Name</th>
                            <th>Contact Info</th>
                            <th>Designation / Branch</th>
                            <th class="text-end">Monthly Gross</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="empModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title fw-bold" id="modalLabel">Employee Master Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <form id="empForm">
                    <input type="hidden" name="id" id="e_id">
                    <div class="row g-4">
                        <div class="col-lg-7">
                            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                                <h6 class="section-title">1. Identity & Position</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" name="employee_name" id="e_name" class="form-control border-2 shadow-sm" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Employee UID (Auto)</label>
                                        <input type="text" id="e_uid" class="form-control bg-light border-0 fw-bold text-muted" readonly placeholder="System Generated">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Mobile Number</label>
                                        <input type="text" name="mobile_number" id="e_mobile" class="form-control shadow-sm">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Role / Designation</label>
                                        <input type="text" name="role" id="e_role" class="form-control shadow-sm" placeholder="e.g. Master Chef">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Assigned Branch *</label>
                                        <select name="branch_id" id="e_branch" class="form-select shadow-sm" required>
                                            <option value="">-- Choose --</option>
                                            <?php foreach($branches as $b): ?>
                                                <option value="<?= $b['branch_id'] ?>"><?= h($b['branch_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm rounded-4 p-4">
                                <h6 class="section-title">2. Financial Structure (Simplified)</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label text-primary">Monthly Gross Salary *</label>
                                        <input type="number" step="0.01" name="salary" id="gross_salary" class="form-control form-control-lg border-primary fw-bold shadow-sm" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Professional Tax (Monthly)</label>
                                        <input type="number" step="0.01" name="professional_tax" id="pt_tax" class="form-control shadow-sm border-2">
                                    </div>
                                    <div class="col-md-6 mt-4">
                                        <div class="p-3 bg-primary-subtle rounded-3 border border-primary-subtle">
                                            <label class="form-label text-primary">PF Percentage (%)</label>
                                            <div class="input-group">
                                                <input type="number" step="0.01" name="pf_percent" id="pf_percent" class="form-control fw-bold" value="12.00">
                                                <span class="input-group-text">%</span>
                                            </div>
                                            <span id="pf_hint" class="amount-hint text-primary">(= ₹0.00)</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mt-4">
                                        <div class="p-3 bg-danger-subtle rounded-3 border border-danger-subtle">
                                            <label class="form-label text-danger">ESI Percentage (%)</label>
                                            <div class="input-group">
                                                <input type="number" step="0.01" name="esi_percent" id="esi_percent" class="form-control fw-bold" value="0.75">
                                                <span class="input-group-text">%</span>
                                            </div>
                                            <span id="esi_hint" class="amount-hint text-danger">(= ₹0.00)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5">
                            <div class="calc-box mb-4 shadow-lg">
                                <h6 class="fw-bold small text-uppercase opacity-50 mb-3 border-bottom border-white border-opacity-10 pb-2 text-center">Net Take-Home Preview</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="small opacity-75">PF Deduction:</span>
                                    <span class="fw-bold text-warning">- ₹<span id="pf_preview_val">0</span></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="small opacity-75">ESI Deduction:</span>
                                    <span class="fw-bold text-warning">- ₹<span id="esi_preview_val">0</span></span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="small opacity-75">Prof. Tax:</span>
                                    <span class="fw-bold text-warning">- ₹<span id="pt_preview_val">0</span></span>
                                </div>
                                <div class="text-center pt-3 border-top border-white border-opacity-20">
                                    <div class="h2 fw-bold text-success mb-0">₹<span id="net_preview_val">0</span></div>
                                    <small class="opacity-25" style="font-size:0.55rem;">* Estimation for 100% attendance</small>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm rounded-4 p-4">
                                <h6 class="section-title">3. Disbursement Info</h6>
                                <div class="mb-3">
                                    <label class="form-label">Bank Name</label>
                                    <input type="text" name="bank_name" id="e_bank" class="form-control" placeholder="e.g. ICICI Bank">
                                </div>
                                <div class="mb-0">
                                    <label class="form-label">IFSC Code</label>
                                    <input type="text" name="ifsc_code" id="e_ifsc" class="form-control" placeholder="ICIC0001234">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-white border-0 p-4">
                <button type="button" class="btn btn-light px-4 rounded-pill fw-bold shadow-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="empForm" class="btn btn-primary px-5 rounded-pill shadow-lg fw-bold py-2">Commit Master Record</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
let table;
let empModal;

$(document).ready(function() {
    $('.select2').select2({ theme: 'bootstrap4', width: '100%' });
    empModal = new bootstrap.Modal(document.getElementById('empModal'));

    table = $('#masterEmployeesTable').DataTable({
        ajax: {
            url: 'api/employee_api.php?action=master_list',
            data: function(d) { d.branch_id = $('#branchFilter').val(); }
        },
        columns: [
            { data: null, className: 'ps-4', render: r => `
                <div class="fw-bold text-dark">${r.employee_name}</div>
                <div class="small text-muted fw-bold" style="font-size:0.65rem;">UID: ${r.employee_uid}</div>` 
            },
            { data: null, render: r => `
                <div class="small fw-medium">${r.mobile_number || '-'}</div>
                <div class="small text-muted" style="font-size:0.7rem;">${r.email || '-'}</div>`
            },
            { data: null, render: r => `
                <div class="small fw-bold text-primary">${r.role}</div>
                <div class="small text-muted">${r.branch_name || 'N/A'}</div>`
            },
            { data: 'salary', className: 'text-end fw-bold', render: d => '₹' + parseFloat(d).toLocaleString('en-IN') },
            { data: 'status', className: 'text-center', render: d => `<span class="badge rounded-pill ${d === 'Active' ? 'badge-soft-success' : 'badge-soft-danger'}">${d.toUpperCase()}</span>` },
            { data: null, className: 'text-end pe-4', render: r => `
                <div class="btn-group shadow-sm border rounded-pill overflow-hidden bg-white">
                    <button class="btn btn-sm btn-white border-0" onclick="openEmpModal(${r.id})"><i class="bi bi-pencil-square text-primary"></i></button>
                    <button class="btn btn-sm btn-white border-0 btn-delete" data-id="${r.id}"><i class="bi bi-person-x text-danger"></i></button>
                </div>`
            }
        ],
        pageLength: 25,
        dom: '<"p-3 d-flex justify-content-between align-items-center"f>rt<"p-3 d-flex justify-content-between align-items-center"ip>',
        language: { search: "", searchPlaceholder: "Search names..." }
    });

    $('#branchFilter').on('change', () => table.ajax.reload());

    $('#gross_salary').on('input', function() {
        const gross = parseFloat($(this).val() || 0);
        if(gross > 0) {
            let pt = 0;
            if(gross >= 20000) pt = 200;
            else if(gross >= 15000) pt = 150;
            $('#pt_tax').val(pt);
        }
        calculate();
    });

    $('#pf_percent, #esi_percent, #pt_tax').on('input', calculate);

    $('#empForm').on('submit', async function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        const isEdit = $('#e_id').val();
        fd.append('action', isEdit ? 'updateEmployee' : 'create');

        const res = await fetch('api/employee_api.php', { method: 'POST', body: fd }).then(r => r.json());
        if (res.status === 'success') {
            empModal.hide();
            table.ajax.reload();
        } else alert(res.message);
    });

    $('#masterEmployeesTable').on('click', '.btn-delete', async function() {
        const id = $(this).data('id');
        if (!confirm('Deactivate employee?')) return;
        const fd = new FormData();
        fd.append('action', 'deleteEmployee');
        fd.append('id', id);
        await fetch('api/employee_api.php', { method: 'POST', body: fd });
        table.ajax.reload();
    });
});

async function openEmpModal(id = null) {
    $('#empForm')[0].reset();
    $('#e_id').val('');
    $('#e_uid').val('');
    $('#modalLabel').text(id ? 'Modify Employee Profile' : 'Add New Employee Record');

    if (id) {
        const res = await fetch(`api/employee_api.php?action=getEmployee&id=${id}`).then(r => r.json());
        if (res.status === 'success') {
            const e = res.employee;
            $('#e_id').val(e.id); $('#e_name').val(e.employee_name);
            $('#e_uid').val(e.employee_uid); $('#e_mobile').val(e.mobile_number);
            $('#e_role').val(e.role); $('#e_branch').val(e.branch_id);
            $('#gross_salary').val(e.salary); $('#pf_percent').val(e.pf_percent);
            $('#esi_percent').val(e.esi_percent); $('#pt_tax').val(e.professional_tax);
            $('#e_bank').val(e.bank_name); $('#e_ifsc').val(e.ifsc_code);
            calculate();
        }
    } else {
        $('#pf_percent').val('12.00');
        $('#esi_percent').val('0.75');
        calculate();
    }
    empModal.show();
}

function calculate() {
    const gross = parseFloat($('#gross_salary').val() || 0);
    const pfP   = parseFloat($('#pf_percent').val() || 0);
    const esiP  = parseFloat($('#esi_percent').val() || 0);
    const pt    = parseFloat($('#pt_tax').val() || 0);
    
    // PF Calculation directly on Gross Salary
    const pfVal = Math.round(gross * (pfP / 100));
    // ESI Calculation on Gross (Threshold 21,000)
    const esiVal = (gross <= 21000) ? Math.ceil(gross * (esiP / 100)) : 0;
    
    $('#pf_hint').text(`(= ₹${pfVal.toLocaleString('en-IN')})`);
    $('#esi_hint').text(`(= ₹${esiVal.toLocaleString('en-IN')})`);
    
    $('#pf_preview_val').text(pfVal.toLocaleString());
    $('#esi_preview_val').text(esiVal.toLocaleString());
    $('#pt_preview_val').text(pt.toLocaleString());
    
    const net = Math.max(0, gross - pfVal - esiVal - pt);
    $('#net_preview_val').text(net.toLocaleString('en-IN', {minimumFractionDigits: 2}));
}
</script>

<?php include 'footer.php'; ?>