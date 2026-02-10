<?php
/**
 * FINANCE: Expenses & Obligations Directory
 * Path: finance/expenses.php
 * UPDATED: Integrated both Fixed and General expenses into a single view.
 */
require_once("../auth.php");
requireRole(['Admin', 'Finance', 'Cashier']);
require_once("../functions.php");

include 'header.php';
include 'nav.php';

// Fetch unique purposes from both sources for the filter dropdown
$p1 = exeSql("SELECT DISTINCT purpose FROM expenses WHERE purpose IS NOT NULL AND purpose <> ''") ?: [];
$p2 = exeSql("SELECT DISTINCT expense_type as purpose FROM fixed_expenses WHERE expense_type IS NOT NULL AND expense_type <> ''") ?: [];
$dbPurposes = array_unique(array_merge(array_column($p1, 'purpose'), array_column($p2, 'purpose')));
sort($dbPurposes);
?>

<div class="container-fluid px-4 mt-2">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <div>
            <h2 class="fw-bold h4 mb-0 text-dark">Unified Expenses Console</h2>
            <p class="text-muted small mb-0">Consolidated ledger of fixed obligations and direct disbursements.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="expenses_create.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Add Expense
            </a>
            <a href="fixed_expenses_create.php" class="btn btn-outline-primary rounded-pill px-4 fw-bold shadow-sm">
                <i class="bi bi-clock-history me-1"></i> Add Fixed
            </a>
        </div>
    </div>

    <!-- Filter Component -->
    <div class="card shadow-sm mb-4 border-0 rounded-4">
        <div class="card-body p-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Purpose / Category</label>
                    <select id="f_purpose" class="form-select select2">
                        <option value="">All Categories</option>
                        <?php foreach($dbPurposes as $p): ?>
                            <option value="<?= h($p) ?>"><?= h(str_replace('_', ' ', strtoupper($p))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted text-uppercase">From Date</label>
                    <input type="date" id="f_from" class="form-control" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted text-uppercase">To Date</label>
                    <input type="date" id="f_to" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-2 ms-auto">
                    <button class="btn btn-dark w-100 rounded-pill fw-bold shadow-sm" onclick="table.ajax.reload()">
                        <i class="bi bi-search me-1"></i> Search
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="expensesTable" class="table table-hover align-middle mb-0" style="width:100%">
                    <thead>
                        <tr>
                            <th class="ps-4">Date</th>
                            <th>Type</th>
                            <th>Purpose & Remarks</th>
                            <th>Bank Account</th>
                            <th class="text-end">Total Amount</th>
                            <th class="text-end text-success">Total Paid</th>
                            <th class="text-end text-danger">Remaining</th>
                            <th class="text-center">State</th>
                            <th class="text-end pe-4">Actions</th>
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

    table = $('#expensesTable').DataTable({
        ajax: {
            url: 'api/expenses_api.php?action=list',
            data: function(d) {
                d.purpose = $('#f_purpose').val();
                d.from = $('#f_from').val();
                d.to = $('#f_to').val();
            }
        },
        columns: [
            { data: 'date', className: 'ps-4 fw-semibold text-dark' },
            { data: 'type', render: d => {
                const c = d === 'Fixed' ? 'bg-primary' : 'bg-secondary';
                return `<span class="badge ${c} border-0 text-uppercase" style="font-size:0.6rem; padding:4px 8px;">${d}</span>`;
            }},
            { data: null, render: r => `
                <div class="fw-bold text-dark">${r.purpose}</div>
                <div class="small text-muted italic">${r.remark}</div>` 
            },
            { data: null, render: r => `
                <div class="small fw-medium">${r.account_no}</div>
                <div class="small text-muted" style="font-size:0.7rem;">${r.ifsc_code}</div>` 
            },
            { data: 'amount', className: 'text-end fw-semibold', render: d => '₹' + d.toLocaleString('en-IN', {minimumFractionDigits: 2}) },
            { data: 'paid', className: 'text-end text-success fw-semibold', render: d => '₹' + d.toLocaleString('en-IN', {minimumFractionDigits: 2}) },
            { data: 'balance', className: 'text-end text-danger fw-bold', render: d => '₹' + d.toLocaleString('en-IN', {minimumFractionDigits: 2}) },
            { data: 'balance', className: 'text-center', render: d => `
                <span class="badge rounded-pill ${d <= 0.01 ? 'badge-soft-success' : 'badge-soft-warning'}">
                    ${d <= 0.01 ? 'SETTLED' : 'PENDING'}
                </span>`
            },
            { data: null, className: 'text-end pe-4', orderable: false, render: r => `
                <div class="btn-group shadow-sm border rounded-pill overflow-hidden bg-white">
                    <a href="${r.type === 'Fixed' ? 'fixed_expenses_edit.php' : 'expenses_edit.php'}?id=${r.id}" class="btn btn-sm btn-white border-0"><i class="bi bi-pencil text-primary"></i></a>
                    <button class="btn btn-sm btn-white border-0 btn-delete" data-id="${r.id}" data-type="${r.type}"><i class="bi bi-trash text-danger"></i></button>
                </div>`
            }
        ],
        dom: '<"p-3 d-flex justify-content-between align-items-center"fB>rt<"p-3 d-flex justify-content-between align-items-center"ip>',
        buttons: [
            { extend: 'excel', className: 'btn btn-sm btn-outline-success rounded-pill px-3 border-0', text: '<i class="bi bi-file-earmark-excel"></i>' },
            { extend: 'print', className: 'btn btn-sm btn-outline-info rounded-pill px-3 border-0', text: '<i class="bi bi-printer"></i>' }
        ],
        pageLength: 25,
        order: [[0, 'desc']]
    });

    $('#expensesTable').on('click', '.btn-delete', async function() {
        if (!confirm('Are you sure you want to permanently delete this record?')) return;
        const id = $(this).data('id');
        const type = $(this).data('type');
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        fd.append('type', type);
        try {
            const res = await fetch('api/expenses_api.php', { method: 'POST', body: fd });
            const j = await res.json();
            if (j.status === 'success') table.ajax.reload();
            else alert(j.message);
        } catch (e) { alert('Server error'); }
    });
});
</script>

<?php include 'footer.php'; ?>