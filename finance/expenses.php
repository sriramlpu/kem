<?php
// kmk/finance/expenses.php
declare(strict_types=1);
require_once __DIR__ . '/../functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Expenses</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.10/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
<link href="https://kit.fontawesome.com/a076d05399.css" rel="stylesheet">

<style>
body { background:#f6f8fb; }
.dataTables_wrapper .top-bar {
  display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;
}
#expensesTable_wrapper .dt-buttons .btn {
  display:inline-flex; align-items:center; gap:.4rem; padding:.35rem .65rem;
  font-size:.875rem; font-weight:500; border-radius:.375rem; border:1px solid #ddd; color:#fff;
}
#expensesTable_wrapper .dt-buttons .buttons-excel { background:#198754; border-color:#198754; }
#expensesTable_wrapper .dt-buttons .buttons-excel:hover { background:#157347; border-color:#157347; }
#expensesTable_wrapper .dt-buttons .buttons-print { background:#0dcaf0; border-color:#0dcaf0; }
#expensesTable_wrapper .dt-buttons .buttons-print:hover { background:#31d2f2; border-color:#31d2f2; }

#dt-filter-row { margin-bottom:10px; }
#dt-filter-row .form-control { height: calc(1.5em + .75rem + 2px); font-size:.875rem; }

tfoot td { font-weight:700; background:#f8f9fa; }
.badge-status { font-size:.8rem; }

/* Actions: two compact buttons side-by-side */
.actions-cell .btn { min-width:70px; }
</style>
</head>
<body>
<?php include 'nav.php'; ?>

<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-3">
    <div>
      <h3 class="mb-1">Expenses</h3>
      <div class="text-muted small">All recorded expenses</div>
    </div>
    <div>
      <a class="btn btn-primary btn-sm ms-2" href="expenses_add.php"> Add Expense</a>
    </div>
  </div>

  <!-- Quick filters -->
  <div id="dt-filter-row" class="row gx-2 mb-3">
    <div class="col-lg-3 col-md-6">
      <input type="text" class="form-control" id="filter-purpose" placeholder="Filter by Purpose">
    </div>
    <div class="col-lg-3 col-md-6">
      <input type="text" class="form-control" id="filter-remark" placeholder="Filter by Remark">
    </div>
    <div class="col-lg-2 col-md-4 mt-2 mt-lg-0">
      <input type="date" class="form-control" id="filter-fromdate" placeholder="From Date">
    </div>
    <div class="col-lg-2 col-md-4 mt-2 mt-lg-0">
      <input type="date" class="form-control" id="filter-todate" placeholder="To Date">
    </div>
    <div class="col-lg-2 col-md-4 mt-2 mt-lg-0 d-grid">
      <button type="button" id="btn-clear-filters" class="btn btn-outline-secondary">Clear</button>
    </div>
  </div>

  <!-- Table -->
  <div class="table-responsive">
    <table id="expensesTable" class="table table-bordered table-striped table-hover align-middle w-100 nowrap">
      <thead class="table-dark">
        <tr>
          <th class="text-center">S.No</th>
          <th class="text-center">Date</th>
          <th class="text-center">Purpose</th>
          <th class="text-center">Remark</th>
          <th class="text-center">Amount (₹)</th>
          <th class="text-center">Total Paid (₹)</th>
          <th class="text-center">Remaining Balance (₹)</th>
          <th class="text-center">Status</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody></tbody>
      <tfoot>
        <tr>
          <td colspan="4" class="text-end">Totals:</td>
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
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

<script>
jQuery(function($){
  const API = 'api/expenses_api.php';
  let latestTotals = { total_amount: 0, total_paid: 0, total_remaining: 0 };
  const currency = (n) => '₹ ' + (parseInt(n,10) || 0).toLocaleString('en-IN');

  const table = $('#expensesTable').DataTable({
    ajax: {
      url: API + '?action=list_all',
      data: function(d){
        d.purpose  = $('#filter-purpose').val() || '';
        d.remark   = $('#filter-remark').val()  || '';
        d.fromdate = $('#filter-fromdate').val() || '';
        d.todate   = $('#filter-todate').val()   || '';
      },
      dataSrc: function(json){
        if (json && json.totals){ latestTotals = json.totals; }
        return json && json.data ? json.data : [];
      },
      error: function(xhr){
        const box = document.getElementById('errBox');
        box.textContent = 'Ajax error: ' + xhr.status + ' ' + (xhr.statusText || '') +
                          (xhr.responseText ? ' – ' + xhr.responseText : '');
        box.classList.remove('d-none');
      }
    },
    columns: [
      { data: 'sno', className:'text-center' },
      { data: 'created_date', className:'text-center' },
      { data: 'purpose', className:'text-start' },
      { data: 'remark', className:'text-start' },
      { data: 'amount', className:'text-end', render: d => currency(d) },
      { data: 'total_paid', className:'text-end', render: d => currency(d) },
      { data: 'remaining_balance', className:'text-end', render: d => currency(d) },
      { data: 'status', className:'text-center',
        render: function(d){
          const done = String(d).toLowerCase() === 'complete';
          const cls  = done ? 'bg-success' : 'bg-warning text-dark';
          return '<span class="badge badge-status '+cls+'">'+d+'</span>';
        }
      },
      { data: null, orderable:false, searchable:false, className:'text-center actions-cell',
        render: function(row){
          const id = row.id;
          return `
            <div class="d-inline-flex gap-2">
              <a href="expenses_edit.php?id=${id}" class="btn btn-sm btn-primary">Edit</a>
              <a href="expenses_delete.php?id=${id}" class="btn btn-sm btn-danger"
                 onclick="return confirm('Are you sure you want to delete this expense?');">Delete</a>
            </div>
          `;
        }
      }
    ],
    pageLength: 10,
    lengthMenu: [10,25,50,100],
    order: [[1,'desc'], [0,'asc']], // Date desc then S.No
    scrollX: true,
    dom: '<"top-bar"lBf>rtip',
    language: { emptyTable: 'No expenses found' },
    drawCallback: function(){
      $('#ft-total-amount').text(currency(latestTotals.total_amount || 0));
      $('#ft-total-paid').text(currency(latestTotals.total_paid || 0));
      $('#ft-total-remaining').text(currency(latestTotals.total_remaining || 0));
    },
    buttons: [
      { extend: 'excelHtml5', text: '<i class="fas fa-file-excel me-1"></i> Export Excel', className: 'btn btn-success' },
      {
        extend: 'print',
        text: '<i class="fas fa-print me-1"></i> Print',
        className: 'btn btn-info',
        title: '',
        exportOptions: { columns: [0,1,2,3,4,5,6,7,8] },

        customize: function (win) {
          const api = $('#expensesTable').DataTable();

          // Helper to parse numeric from cell text (₹, commas, HTML)
          const numeric = (s) => parseFloat(String(s).replace(/<[^>]*>/g,'').replace(/[^\d.\-]/g,'')) || 0;
          const sumCol = (idx) => api.column(idx, { search:'applied', page:'all' })
                                   .data().toArray().reduce((a,v)=>a+numeric(v),0);

          // Prefer server totals if provided; else compute from table
          const totalAmount  = (latestTotals.total_amount  != null) ? '₹ ' + (parseInt(latestTotals.total_amount,10)  || 0).toLocaleString('en-IN') : '₹ ' + sumCol(4).toFixed(2);
          const totalPaid    = (latestTotals.total_paid    != null) ? '₹ ' + (parseInt(latestTotals.total_paid,10)    || 0).toLocaleString('en-IN') : '₹ ' + sumCol(5).toFixed(2);
          const totalRemain  = (latestTotals.total_remaining != null) ? '₹ ' + (parseInt(latestTotals.total_remaining,10) || 0).toLocaleString('en-IN') : '₹ ' + sumCol(6).toFixed(2);

          const nowStr = new Date().toLocaleString();
          const $doc   = $(win.document);

          // ======== Header (VERY TOP): Company name big + logo below; date & totals on right ========
          const headerHtml = `
            <div id="print-brand">
              <div class="brand-top">
                <div class="company">KMK GLOBAL LIMITED</div>
                <img src="../assets/img/logo.jpg" alt="Logo">
              </div>

              <div class="top-row">
                <div class="left"><span class="section-title">EXPENSE DETAILS</span></div>
                <div class="right">
                  <div class="totals">
                    <div class="now">${nowStr}</div>
                    <div><strong>Total Amount:</strong> ${totalAmount}</div>
                    <div><strong>Total Paid:</strong> ${totalPaid}</div>
                    <div><strong>Remaining Balance:</strong> ${totalRemain}</div>
                  </div>
                </div>
              </div>

              <hr class="rule">
            </div>
          `;

          // ======== Footer (edit address as needed) ========
          const footerHtml = `
            <div id="print-footer">
              <hr class="rule">
              71-4-671, Ground Floor, Vijayawada, Andhra Pradesh, 520007, India
            </div>
          `;

          $doc.find('body').prepend(headerHtml);
          $doc.find('body').append(footerHtml);

          // ======== Print CSS ========
          $doc.find('head').append(`
            <style>
              @page { margin: 12mm; }
              body { font-size: 10pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

              /* Big header with company name and logo */
              #print-brand .brand-top {
                display: flex; flex-direction: column; align-items: center; margin: 0 0 4px 0;
              }
              #print-brand .brand-top .company {
                font-weight: 900; font-size: 36pt; /* BIG name */
                text-transform: uppercase; letter-spacing: 1px; text-align: center; margin-bottom: 6px;
              }
              #print-brand .brand-top img {
                height: 80px; object-fit: contain; /* BIG logo */
              }

              #print-brand .top-row {
                display: flex; justify-content: space-between; align-items: flex-end; gap: 8px; margin-top: 6px;
              }
              .section-title { font-weight: 800; font-size: 16pt; letter-spacing: .4px; } /* BIG title */
              .totals { text-align: right; font-size: 10pt; line-height: 1.25; }
              .totals .now { margin-bottom: 2px; }

              .rule { border: 1px solid #000; margin: 6px 0 10px; }

              table.dataTable:first-of-type { margin-top: 2mm; }
              table.dataTable th, table.dataTable td { white-space: nowrap; }

              #print-footer {
                position: fixed; bottom: 10mm; left: 0; right: 0; text-align: center; font-size: 10pt;
              }
            </style>
          `);

          // Compact table font in print
          $doc.find('table').addClass('compact').css('font-size','inherit');
        }
      }
    ]
  });

  // Filters
  $('#filter-purpose, #filter-remark, #filter-fromdate, #filter-todate').on('keyup change', function(){
    table.ajax.reload(null, true);
  });
  $('#btn-clear-filters').on('click', function(){
    $('#filter-purpose, #filter-remark, #filter-fromdate, #filter-todate').val('');
    table.ajax.reload(null, true);
  });
});
</script>
</body>
</html>
