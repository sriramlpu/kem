<?php
// kmk/finance/fixed_expenses.php
declare(strict_types=1);

$expense_types = [
  'current_bill' => 'Current Bill',
  'rent'         => 'Rent',
  'water_bill'   => 'Water Bill',
  'pf'           => 'PF',
  'wifi_bill'    => 'Wifi Bill',
  'other'        => 'Other Fixed Expense'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Fixed Expenses</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.10/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

<style>
body { background:#f6f8fb; }
.dataTables_wrapper .top-bar { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
#fixedExpensesTable_wrapper .dt-buttons .btn {
  display:inline-flex; align-items:center; gap:.4rem; padding:.35rem .65rem;
  font-size:.875rem; font-weight:500; border-radius:.375rem; border:1px solid #ddd; color:#fff;
}
#fixedExpensesTable_wrapper .dt-buttons .buttons-excel { background:#198754; border-color:#198754; }
#fixedExpensesTable_wrapper .dt-buttons .buttons-excel:hover { background:#157347; border-color:#157347; }
#fixedExpensesTable_wrapper .dt-buttons .buttons-print { background:#0dcaf0; border-color:#0dcaf0; }
#fixedExpensesTable_wrapper .dt-buttons .buttons-print:hover { background:#31d2f2; border-color:#31d2f2; }
#dt-filter-row { margin-bottom:10px; }
#dt-filter-row .form-control, #dt-filter-row .form-select { height: calc(1.5em + .75rem + 2px); font-size:.875rem; }
tfoot td { font-weight:700; background:#f8f9fa; }
.badge-status { font-size:.8rem; }
.badge-status.bg-success { background-color: #198754 !important; }
.badge-status.bg-warning { background-color: #ffc107 !important; }
.table-responsive { overflow-x: visible !important; }
.modal .form-label { font-weight: 600; }
.actions-cell .btn { min-width:70px; }
</style>
</head>
<body>

<?php include 'nav.php'; ?>

<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-3">
    <div>
      <h3 class="mb-1">Recurring Fixed Expenses</h3>
      <div class="text-muted small">Master records for fixed recurring operational expenses</div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary btn-sm" id="btn-add"><i class="fa fa-plus me-1"></i> Add Recurring Expense</button>
      <a class="btn btn-outline-secondary btn-sm" href="fixed_expenses_add.php">Full Add Page</a>
    </div>
  </div>

  <div id="dt-filter-row" class="row gx-2 mb-3">
    <div class="col-lg-4 col-md-6">
      <select class="form-select" id="filter-type">
        <option value="all">Filter by Expense Type</option>
        <?php foreach ($expense_types as $key => $name): ?>
          <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($name); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-lg-4 col-md-6 mt-2 mt-md-0">
      <input type="text" class="form-control" id="filter-notes" placeholder="Filter by Notes/Remark">
    </div>
    <div class="col-lg-4 col-md-4 mt-2 mt-lg-0 d-grid">
      <button type="button" id="btn-clear-filters" class="btn btn-outline-secondary">Clear Filters</button>
    </div>
  </div>

  <div class="table-responsive">
    <table id="fixedExpensesTable" class="table table-bordered table-striped table-hover align-middle w-100 nowrap">
      <thead class="table-dark">
        <tr>
          <th class="text-center">S.No</th>
          <th class="text-center">Type</th>
          <th class="text-center">Frequency</th>
          <th class="text-center">Due Day</th>
          <th class="text-center">Notes</th>
          <th class="text-center">Total Amount (₹)</th>
          <th class="text-center">Paid (₹)</th>
          <th class="text-center">Balance (₹)</th>
          <th class="text-center">Next Due Date</th>
          <th class="text-center">Status</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody></tbody>
      <tfoot>
        <tr>
          <td colspan="5" class="text-end">Totals:</td>
          <td id="ft-total-amount" class="text-end">₹ 0</td>
          <td id="ft-total-paid" class="text-end">₹ 0</td>
          <td id="ft-total-remaining" class="text-end">₹ 0</td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <div id="errBox" class="alert alert-danger d-none mt-3"></div>
</div>

<!-- Create / Edit Modal -->
<div class="modal fade" id="expenseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="expenseForm">
        <div class="modal-header">
          <h5 class="modal-title">Add Recurring Expense</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="fe-id">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Expense Type</label>
              <select class="form-select" name="expense_type" id="fe-type" required>
                <option value="">Select</option>
                <?php foreach ($expense_types as $key => $name): ?>
                  <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($name); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Amount (₹)</label>
              <input type="number" class="form-control" name="amount" id="fe-amount" min="1" step="0.01" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Start Date</label>
              <input type="date" class="form-control" name="start_date" id="fe-start" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Frequency</label>
              <select class="form-select" name="frequency" id="fe-frequency" required>
                <option value="">Select</option>
                <option>Monthly</option>
                <option>Quarterly</option>
                <option>Half-Yearly</option>
                <option>Annually</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Due Day (1–31)</label>
              <input type="number" class="form-control" name="due_day" id="fe-due" min="1" max="31" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Paid so far (₹)</label>
              <input type="number" class="form-control" name="balance_paid" id="fe-paid" min="0" step="0.01" value="0">
            </div>
            <div class="col-12">
              <label class="form-label">Notes</label>
              <input type="text" class="form-control" name="notes" id="fe-notes" maxlength="500">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
          <button class="btn btn-primary" type="submit">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.10/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.10/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
jQuery(function($){
  const API = 'api/fixed_expenses_api.php';

  let latestTotals = { total_amount: 0, total_paid: 0, total_remaining: 0 };
  const expenseTypesMap = {
    'current_bill': 'Current Bill',
    'rent': 'Rent',
    'water_bill': 'Water Bill',
    'pf': 'PF',
    'wifi_bill': 'Wifi Bill',
    'other': 'Other Fixed Expense'
  };
  const currency = (n) => '₹ ' + (parseFloat(n) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  function showError(msg){
    const box = document.getElementById('errBox');
    box.textContent = msg;
    box.classList.remove('d-none');
  }
  function hideError(){ document.getElementById('errBox').classList.add('d-none'); }

  const table = $('#fixedExpensesTable').DataTable({
    ajax: {
      url: API + '?action=list_all',
      data: function(d){
        d.type  = $('#filter-type').val() || '';
        d.notes = $('#filter-notes').val() || '';
      },
      dataSrc: function(json){
        if (json && json.totals){ latestTotals = json.totals; }
        if (json && (json.status === 'error' || json.error)) {
          showError('API Error: ' + (json.message || json.error));
          return [];
        }
        return json && json.data ? json.data : [];
      },
      error: function(xhr){
        let msg = 'AJAX Request Failed';
        if (xhr.status) msg += ` (HTTP ${xhr.status})`;
        if (xhr.responseText) {
          try {
            const j = JSON.parse(xhr.responseText);
            if (j.message) msg += `: ${j.message}`;
            else if (j.error) msg += `: ${j.error}`;
          } catch(e) {
            msg += ` — Raw: ${xhr.responseText.substring(0, 300)}...`;
          }
        }
        showError(msg + '. Check server logs.');
      }
    },
    columns: [
      { data: 'sno', className:'text-center' },
      { data: 'expense_type', className:'text-start', render: d => expenseTypesMap[d] || d },
      { data: 'frequency', className:'text-center' },
      { data: 'due_day', className:'text-center' },
      { data: 'notes', className:'text-start', defaultContent: '' },
      { data: 'amount', className:'text-end', render: d => currency(d) },
      { data: 'balance_paid', className:'text-end', render: d => currency(d) },
      { data: 'remaining_balance', className:'text-end', render: d => currency(d) },
      { data: 'next_due_date', className:'text-center', defaultContent: 'N/A' },
      { data: 'status', className:'text-center',
        render: function(d){
          const isPaid = String(d).includes('Paid');
          const cls = isPaid ? 'bg-success' : 'bg-warning text-dark';
          return '<span class="badge badge-status '+cls+'">'+d+'</span>';
        }
      },
      { data: null, orderable:false, searchable:false, className:'text-center actions-cell',
        render: function(row){
          const id = row.id;
          return `
            <div class="d-inline-flex gap-2">
              <button type="button" class="btn btn-sm btn-primary" data-action="edit" data-id="${id}">Edit</button>
              <button type="button" class="btn btn-sm btn-danger" data-action="delete" data-id="${id}">Delete</button>
            </div>
          `;
        }
      }
    ],
    pageLength: 10,
    lengthMenu: [10,25,50,100],
    order: [[0,'asc']],
    dom: '<"top-bar"lBf>rtip',
    language: { emptyTable: 'No recurring fixed expenses found' },
    drawCallback: function(){
      $('#ft-total-amount').text(currency(latestTotals.total_amount || 0));
      $('#ft-total-paid').text(currency(latestTotals.total_paid || 0));
      $('#ft-total-remaining').text(currency(latestTotals.total_remaining || 0));
    },
    buttons: [
      { extend: 'excelHtml5', text: '<i class="fas fa-file-excel me-1"></i> Export Excel', className: 'btn btn-success', exportOptions: { columns: [0,1,2,3,4,5,6,7,8,9] } },
      { extend: 'print', text: '<i class="fas fa-print me-1"></i> Print', className: 'btn btn-info', title: 'Recurring Fixed Expense Details', exportOptions: { columns: [0,1,2,3,4,5,6,7,8,9] } }
    ]
  });

  // filters
  $('#filter-type, #filter-notes').on('keyup change', function(){
    table.columns(4).search($('#filter-notes').val()).draw();
    table.ajax.reload(null, true);
  });
  $('#btn-clear-filters').on('click', function(){
    $('#filter-type').val('all').trigger('change');
    $('#filter-notes').val('');
    table.columns(4).search('').draw();
    table.ajax.reload(null, true);
  });

  // modal logic
  const modalEl = document.getElementById('expenseModal');
  const bsModal = new bootstrap.Modal(modalEl);

  function resetForm(){
    $('#fe-id').val('');
    $('#fe-type').val('');
    $('#fe-amount').val('');
    $('#fe-start').val('');
    $('#fe-frequency').val('');
    $('#fe-due').val('');
    $('#fe-paid').val('0');
    $('#fe-notes').val('');
    $('.modal-title', modalEl).text('Add Recurring Expense');
  }

  $('#btn-add').on('click', function(){
    hideError();
    resetForm();
    bsModal.show();
  });

  // Load for edit
  $('#fixedExpensesTable').on('click', 'button[data-action="edit"]', function(){
    hideError();
    const id = $(this).data('id');
    $.getJSON(API, { action: 'get', id }, function(resp){
      if (resp.status === 'error') { showError(resp.message || 'Load failed'); return; }
      const e = resp.expense;
      $('#fe-id').val(e.id);
      $('#fe-type').val(e.expense_type);
      $('#fe-amount').val(e.amount);
      $('#fe-start').val(e.start_date);
      $('#fe-frequency').val(e.frequency);
      $('#fe-due').val(e.due_day);
      $('#fe-paid').val(e.balance_paid);
      $('#fe-notes').val(e.notes || '');
      $('.modal-title', modalEl).text('Edit Recurring Expense');
      bsModal.show();
    }).fail(function(xhr){
      showError('Load failed: ' + (xhr.responseText || 'network error'));
    });
  });

  // Delete
  $('#fixedExpensesTable').on('click', 'button[data-action="delete"]', function(){
    const id = $(this).data('id');
    if (!confirm('Delete this recurring expense (ID: ' + id + ')? This cannot be undone.')) return;
    $.ajax({
      url: API + '?action=delete',
      type: 'POST',
      dataType: 'json',
      data: { id },
      success: function(resp){
        if (resp.status === 'success') {
          table.ajax_reload = table.ajax.reload(null, false);
        } else {
          showError(resp.message || 'Delete failed');
        }
      },
      error: function(xhr){
        showError('Delete failed: ' + (xhr.responseText || 'network error'));
      }
    });
  });

  // Date helper
  function toYmd(s) {
    if (!s) return '';
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;
    const m = s.match(/^(\d{2})-(\d{2})-(\d{4})$/);
    if (m) return `${m[3]}-${m[2]}-${m[1]}`;
    try { return new Date(s).toISOString().slice(0,10); } catch(e) { return s; }
  }

  // Submit (create/update) — NOTE: we do NOT send remaining_balance
  $('#expenseForm').on('submit', function(e){
    e.preventDefault();
    hideError();

    const form = Object.fromEntries(new FormData(this).entries());
    const isUpdate = !!form.id;
    const action = isUpdate ? 'update' : 'create';

    form.start_date = toYmd(form.start_date);
    form.action = action;

    $.ajax({
      url: API + '?action=' + action,
      type: 'POST',
      dataType: 'json',
      data: form,
      success: function(resp){
        if (resp.status === 'success') {
          bsModal.hide();
          table.ajax.reload(null, false);
        } else {
          showError(resp.message || 'Save failed');
        }
      },
      error: function(xhr){
        let message = 'Save failed';
        if (xhr.responseText) {
          try { const j = JSON.parse(xhr.responseText); if (j.message) message = j.message; } catch(e){
            message += ' — Raw: ' + xhr.responseText.substring(0, 300);
          }
        }
        showError(message);
      }
    });
  });
});
</script>
</body>
</html>
