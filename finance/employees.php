<?php
// kmk/finance/employees.php
require_once dirname(__DIR__) . '/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Employees</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.10/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" crossorigin="anonymous" />

<style>
 body { background:#f6f8fb; }
 .dataTables_wrapper .top-bar { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
 #employeesTable_wrapper .dt-buttons .btn { display:inline-flex; align-items:center; gap:.4rem; padding:.35rem .65rem; font-size:.875rem; font-weight:500; border-radius:.375rem; border:1px solid #ddd; color:#fff; cursor:pointer; transition:background .2s,color .2s; }
 #employeesTable_wrapper .dt-buttons .buttons-excel { background:#198754; border-color:#198754; }
 #employeesTable_wrapper .dt-buttons .buttons-excel:hover { background:#157347; border-color:#157347; }
 #employeesTable_wrapper .dt-buttons .buttons-print { background:#0dcaf0; border-color:#0dcaf0; }
 #employeesTable_wrapper .dt-buttons .buttons-print:hover { background:#31d2f2; border-color:#31d2f2; }
 #employeesTable_wrapper .dt-buttons { display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:10px; }
 #dt-filter-row { margin-bottom:10px; }
 #dt-filter-row .form-control { height: calc(1.5em + .75rem + 2px); font-size:.875rem; }
 tfoot td { font-weight:700; background:#f8f9fa; }
</style>
</head>
<body>
<?php include 'nav.php'; ?>

<div class="container py-4">
 <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-3">
  <div>
   <h3 class="mb-1">Employees</h3>
   <div class="text-muted small">View balances for a specific month</div>
  </div>
  <div class="d-flex align-items-center gap-2">
   <button id="btn-prev" class="btn btn-outline-secondary btn-sm" type="button" title="Previous month">
    <i class="fas fa-chevron-left"></i>
   </button>
   <input type="month" id="monthPicker" class="form-control form-control-sm" style="width: 180px;" />
   <button id="btn-next" class="btn btn-outline-secondary btn-sm" type="button" title="Next month">
    <i class="fas fa-chevron-right"></i>
   </button>
   <a class="btn btn-primary btn-sm ms-2" href="employee_create.php">+ Add Employee</a>
  </div>
 </div>

  <div id="dt-filter-row" class="row gx-2 mb-3">
  <div class="col-md-3">
   <input type="text" class="form-control" id="filter-name" placeholder="Filter by Name">
  </div>
  <div class="col-md-3">
   <input type="text" class="form-control" id="filter-id" placeholder="Filter by Employee ID">
  </div>
  <div class="col-md-3">
   <input type="text" class="form-control" id="filter-phone" placeholder="Filter by Phone Number">
  </div>
  <div class="col-md-3">
   <select class="form-control" id="filter-branch">
    <option value="">All Branches</option>
   </select>
  </div>
 </div>

  <div class="table-responsive">
  <table id="employeesTable" class="table table-bordered table-striped table-hover align-middle w-100 nowrap">
   <thead class="table-dark">
    <tr>
     <th class="text-center">S.No</th>
     <th class="text-center">Employee Name</th>
     <th class="text-center">Employee ID</th>
     <th class="text-center">Mobile</th>
     <th class="text-center">Email</th>
     <th class="text-center">Address</th>
     <th class="text-center">Branch</th>
     <th class="text-center">Role</th>
     <th class="text-center">Salary</th>
     <th class="text-center">Balance</th>
     <th class="text-center">Status</th>
     <th class="text-center">Actions</th>
    </tr>
   </thead>
   <tbody></tbody>
   <tfoot>
    <tr>
     <td colspan="8" class="text-end">Total:</td>
     <td id="ft-salary" class="text-end">₹ 0.00</td>
     <td id="ft-balance" class="text-end">₹ 0.00</td>
     <td></td>
     <td></td>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/js/all.min.js" crossorigin="anonymous"></script>

<script>
jQuery(function($){
 const API = 'api/employee_api.php';
 let latestTotals = { total_salary_all: '₹ 0.00', total_unpaid_balance: '₹ 0.00' };

 // Month helpers
 const pad2 = n => String(n).padStart(2,'0');
 const toYYYYMM = d => `${d.getFullYear()}${pad2(d.getMonth()+1)}`;
 const toInputMonth = yyyymm => `${yyyymm.slice(0,4)}-${yyyymm.slice(4,6)}`;
 const fromInputMonth = val => val.replace('-', '');

 // default month
 const now = new Date();
 let yyyymm = toYYYYMM(now);
 $('#monthPicker').val(toInputMonth(yyyymm));

 // Load branches into dropdown
 function loadBranches() {
  $.getJSON(API + '?action=branches', function(payload){
   const $sel = $('#filter-branch');
   if (Array.isArray(payload) && payload.length) {
    payload.forEach(r => {
     if (r && r.branch_id && r.branch_name){
      $sel.append('<option value="'+ String(r.branch_id) +'">'+ String(r.branch_name) +'</option>');
     }
    });
   }
  });
 }
 loadBranches();

 // DataTable
 const table = $('#employeesTable').DataTable({
  ajax: {
   url: API + '?action=list',
   data: function(d){
    d.yyyymm  = yyyymm;
    d.branch_id = $('#filter-branch').val() || '';
   },
   dataSrc: function(json){
    if (json && json.totals){ latestTotals = json.totals; }
    return json && json.data ? json.data : [];
   },
   error: function(xhr){
    const box = document.getElementById('errBox');
    box.textContent = 'Ajax error: ' + (xhr.status) + ' ' + (xhr.statusText || '') +
             (xhr.responseText ? ' – ' + xhr.responseText : '');
    box.classList.remove('d-none');
   }
  },
  columns: [
   { data: 'sno', className:'text-center' },
   { data: 'employee_name', className:'text-start' },
   { data: 'employee_uid', className:'text-center', render: d => '<strong>'+d+'</strong>' },
   { data: 'mobile_number', className:'text-center' },
   { data: 'email', className:'text-center' },
   { data: 'address', className:'text-start' },
   { data: 'branch', className:'text-center' },
   { data: 'role', className:'text-center' },
   { data: 'salary', className:'text-end' },
   { data: 'balance', className:'text-end' },
   { data: 'status', className:'text-center' },
   { data: 'actions', className:'text-center', orderable:false, searchable:false }
  ],
  pageLength: 10,
  lengthMenu: [10,25,50,100],
  order: [[1,'asc']],
  scrollX: true,
  dom: '<"top-bar"lBf>rtip',
  language: { emptyTable: 'No employees found' },
  drawCallback: function(){
   // Corrected footer spans (colspan 8 for 0-7, salary 8, balance 9)
      const table = $('#employeesTable').DataTable();
      const numCols = table.columns().visible().length;
      $('#employeesTable tfoot td:first-child').attr('colspan', numCols - 3); // -3 for Salary, Balance, Actions

   $('#ft-salary').text(latestTotals.total_salary_all || '₹ 0.00');
   $('#ft-balance').text(latestTotals.total_unpaid_balance || '₹ 0.00');
  },
  buttons: [
   { extend: 'excelHtml5', text: '<i class="fas fa-file-excel me-1"></i> Export Excel', className: 'btn btn-success' },
   {
    extend: 'print',
    text: '<i class="fas fa-print me-1"></i> Print',
    className: 'btn btn-info',
    title: '',
    exportOptions: { columns: [0,1,2,3,4,5,6,7,8,9,10] },
    customize: function (win) {
     const api = $('#employeesTable').DataTable();

     // Compute totals from table if API didn't provide
     const numeric = (s) => parseFloat(String(s).replace(/<[^>]*>/g,'').replace(/[^\d.\-]/g,'')) || 0;
     const sumCol = (idx) => api.column(idx, { search:'applied', page:'all' }).data().toArray().reduce((a,v)=>a+numeric(v),0);

     // Prefer latestTotals if they look valid; else compute
     const salaryTotal = (latestTotals.total_salary_all && /[\d]/.test(latestTotals.total_salary_all))
      ? latestTotals.total_salary_all
      : '₹ ' + sumCol(8).toFixed(2);
     const balanceTotal = (latestTotals.total_unpaid_balance && /[\d]/.test(latestTotals.total_unpaid_balance))
      ? latestTotals.total_unpaid_balance
      : '₹ ' + sumCol(9).toFixed(2);

     const nowStr = new Date().toLocaleString();
     const $doc = $(win.document);

     // ======== Header at VERY TOP: Company name big + logo below ========
     const headerHtml = `
      <div id="print-brand">
       <div class="brand-top">
        <div class="company">KMK GLOBAL LIMITED</div>
        <img src="../assets/img/logo.jpg" alt="Logo">
       </div>

       <div class="top-row">
        <div class="left"><span class="section-title">EMPLOYEE DETAILS</span></div>
        <div class="right">
         <div class="totals">
          <div class="now">${nowStr}</div>
          <div><strong>Total Salary:</strong> ${salaryTotal}</div>
          <div><strong>Total Balance:</strong> ${balanceTotal}</div>
         </div>
        </div>
       </div>

       <hr class="rule">
      </div>
     `;

     // ======== Footer (address) – edit as needed ========
     const footerHtml = `
      <div id="print-footer">
       <hr class="rule">
       71-4-671, Ground Floor, Vijayawada, Andhra Pradesh, 520007, India
      </div>
     `;

     $doc.find('body').prepend(headerHtml);
     $doc.find('body').append(footerHtml);

     // ======== Print-only CSS ========
     $doc.find('head').append(`
      <style>
       @page { margin: 12mm; }
       body { font-size: 10pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

       #print-brand .brand-top {
        display: flex; flex-direction: column; align-items: center; margin: 0 0 4px 0;
       }
       #print-brand .brand-top .company {
        font-weight: 900; font-size: 36pt; /* BIGGER NAME */
        text-transform: uppercase; letter-spacing: 1px; text-align: center; margin-bottom: 6px;
       }
       #print-brand .brand-top img {
        height: 80px; object-fit: contain; /* BIGGER LOGO */
       }

       #print-brand .top-row {
        display: flex; justify-content: space-between; align-items: flex-end; gap: 8px; margin-top: 6px;
       }
       .section-title { font-weight: 800; font-size: 16pt; letter-spacing: .4px; } /* BIGGER TITLE */
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

 // Text filters
 $('#filter-name').on('keyup change', function(){ table.column(1).search(this.value).draw(); });
 $('#filter-id').on('keyup change', function(){ table.column(2).search(this.value).draw(); });
 $('#filter-phone').on('keyup change', function(){ table.column(3).search(this.value).draw(); });

 // Branch filter (server-side)
 $('#filter-branch').on('change', function(){ table.ajax.reload(null, true); });

 // Month change
 $('#monthPicker').on('change', function(){
  const v = $(this).val();
  if (!v) return;
  yyyymm = fromInputMonth(v);
  table.ajax.reload(null, true);
 });

 // Prev / Next
 function shiftMonth(delta){
  const y = parseInt(yyyymm.slice(0,4), 10);
  const m = parseInt(yyyymm.slice(4,6), 10);
  const d = new Date(y, m - 1 + delta, 1);
  yyyymm = toYYYYMM(d);
  $('#monthPicker').val(toInputMonth(yyyymm));
  table.ajax.reload(null, true);
 }
 $('#btn-prev').on('click', () => shiftMonth(-1));
 $('#btn-next').on('click', () => shiftMonth(+1));

 // Delete (delegated)
 $('#employeesTable').on('click', '.btn-delete', async function(){
  const id = this.getAttribute('data-id');
  if (!id) return;
  if (!confirm('Delete this employee permanently? This will remove their payments too.')) return;

  try {
   const fd = new FormData();
   fd.append('action','deleteEmployee');
   fd.append('id', id);
   const res = await fetch('api/employee_api.php', { method:'POST', body: fd });
   const text = await res.text();
   let j;
   try { j = JSON.parse(text); } catch(e){ alert('Delete failed: ' + text.slice(0,200)); return; }
   if (j.status === 'success') {
    $('#employeesTable').DataTable().ajax.reload(null, false);
   } else {
    alert(j.message || 'Delete failed');
   }
  } catch (e){
   alert('Request failed while deleting.');
  }
 });
});
</script>
</body>
</html>