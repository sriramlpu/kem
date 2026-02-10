<?php
/**
 * FINANCE: Fixed Expenses (Recurring Obligations) Listing
 * Path: finance/fixed_expenses.php
 */
require_once("../auth.php");
requireRole(['Admin', 'Finance']);
require_once("../functions.php");

include 'header.php';
include 'nav.php';

$expense_types = [
    'current_bill' => 'Current Bill',
    'rent'         => 'Rent',
    'water_bill'   => 'Water Bill',
    'pf'           => 'PF/Statutory',
    'wifi_bill'    => 'Wifi/Internet',
    'other'        => 'Other Fixed'
];
?>

<div class="container-fluid px-4 mt-2">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <div>
            <h2 class="fw-bold h4 mb-0 text-dark">Fixed Obligations</h2>
            <p class="text-muted small mb-0">Manage recurring liabilities, schedules, and balances.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="fixed_expenses_create.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Add Obligation
            </a>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="fixedTable" class="table table-hover align-middle mb-0" style="width:100%">
                    <thead>
                        <tr>
                            <th class="ps-4">Type & Frequency</th>
                            <th>Schedule Details</th>
                            <th class="text-end">Base Amount</th>
                            <th class="text-end text-success">Total Paid</th>
                            <th class="text-end text-danger">Remaining</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
let table;
$(document).ready(function() {
    table = $('#fixedTable').DataTable({
        ajax: 'api/fixed_expenses_api.php?action=list',
        columns: [
            { data: null, className: 'ps-4', render: r => `
                <div class="fw-bold text-dark">${r.type}</div>
                <div class="small text-muted text-uppercase" style="font-size:0.65rem;">Cycle: ${r.frequency}</div>` 
            },
            { data: null, render: r => `
                <div class="small fw-medium">Due Day: ${r.due_day}</div>
                <div class="small text-muted italic">${r.start_date}</div>` 
            },
            { data: 'amount', className: 'text-end fw-semibold', render: d => '₹' + d.toLocaleString('en-IN', {minimumFractionDigits: 2}) },
            { data: 'paid', className: 'text-end text-success', render: d => '₹' + d.toLocaleString('en-IN', {minimumFractionDigits: 2}) },
            { data: 'balance', className: 'text-end text-danger fw-bold', render: d => '₹' + d.toLocaleString('en-IN', {minimumFractionDigits: 2}) },
            { data: null, className: 'text-end pe-4', orderable: false, render: r => `
                <div class="btn-group shadow-sm border rounded-pill overflow-hidden bg-white">
                    <a href="fixed_expenses_edit.php?id=${r.id}" class="btn btn-sm btn-white border-0"><i class="bi bi-pencil text-primary"></i></a>
                    <button class="btn btn-sm btn-white border-0 btn-delete" data-id="${r.id}"><i class="bi bi-trash text-danger"></i></button>
                </div>`
            }
        ],
        dom: '<"p-3 d-flex justify-content-between align-items-center"fB>rt<"p-3 d-flex justify-content-between align-items-center"ip>',
        buttons: [
            { extend: 'excel', className: 'btn btn-sm btn-outline-success rounded-pill px-3 border-0', text: '<i class="bi bi-file-earmark-excel"></i>' },
            { extend: 'print', className: 'btn btn-sm btn-outline-info rounded-pill px-3 border-0', text: '<i class="bi bi-printer"></i>' }
        ],
        pageLength: 25,
        order: [[4, 'desc']]
    });

    $('#fixedTable').on('click', '.btn-delete', async function() {
        if (!confirm('Are you sure? This will remove the obligation tracker. Past payment records remain in the ledger.')) return;
        const id = $(this).data('id');
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        
        try {
            const res = await fetch('api/fixed_expenses_api.php', { method: 'POST', body: fd });
            const j = await res.json();
            if (j.status === 'success') table.ajax.reload();
            else alert(j.message);
        } catch (e) { alert('Server error'); }
    });
});
</script>

<?php include 'footer.php'; ?>