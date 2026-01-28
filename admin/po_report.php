<?php
require_once("header.php");
require_once("nav.php");
require_once("../auth.php");
requireRole(['Requester','Admin']);
?>

<div class="container-fluid py-3">
  <div class="card shadow">
    <div class="card-header d-flex justify-content-between align-items-center bg-success text-white">
      <h5 class="mb-0">Purchase Orders</h5>
      <div>
        <a href="purchase_order.php" class="btn btn-danger btn-sm me-2">Add New PO</a>
        <button id="exportExcel" class="btn btn-success btn-sm me-1"><i class="bi bi-file-earmark-excel"></i> Excel</button>
        <button id="exportPdf" class="btn btn-danger btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
      </div>
    </div>

    <div class="card-body">

      <!-- Status Summary -->
      <div class="row mb-3" id="poStatusSummary">
        <div class="col-md-2"><span class="badge bg-warning status-filter" data-status="" style="cursor:pointer;">All: <strong id="allCount">0</strong></span></div>
        <div class="col-md-2"><span class="badge bg-warning status-filter" data-status="Pending" style="cursor:pointer;">Pending: <strong id="pendingCount">0</strong></span></div>
        <div class="col-md-2"><span class="badge bg-info status-filter" data-status="Partially Fulfilled" style="cursor:pointer;">Partially Fulfilled: <strong id="partialCount">0</strong></span></div>
        <div class="col-md-2"><span class="badge bg-success status-filter" data-status="Completed" style="cursor:pointer;">Completed: <strong id="completedCount">0</strong></span></div>
        <div class="col-md-2"><span class="badge bg-secondary status-filter" data-status="Cancelled" style="cursor:pointer;">Cancelled: <strong id="cancelledCount">0</strong></span></div>
      </div>

      <!-- Filters -->
      <div class="row g-2 mb-3">
        <div class="col-md-2"><input type="text" id="filterPoNumber" class="form-control" placeholder="PO Number"></div>
        <div class="col-md-2">
          <select id="filterPoType" class="form-select select2">
            <option value="">All Types</option>
            <option value="WITH INDENT">WITH INDENT</option>
            <option value="WITHOUT INDENT">WITHOUT INDENT</option>
          </select>
        </div>
        <div class="col-md-2"><select id="filterBranch" class="form-select select2"></select></div>
        <div class="col-md-2"><select id="filterVendor" class="form-select select2"></select></div>
        <div class="col-md-2"><input type="date" id="filterStartDate" class="form-control"></div>
        <div class="col-md-2"><input type="date" id="filterEndDate" class="form-control"></div>
      </div>

      <div class="row mb-3">
        <div class="col text-end">
          <button id="resetFilters" class="btn btn-outline-secondary btn-sm">Reset Filters</button>
        </div>
      </div>

      <!-- Main DataTable -->
      <table id="purchaseOrdersTable" class="table table-bordered table-striped w-100">
        <thead class="table-light">
          <tr>
            <th>PO No</th>
            <th>Date</th>
            <th>Type</th>
            <th>Branch</th>
            <th>Vendor</th>
            <th>Expected Delivery</th>
            <th>Total</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
      </table>

    </div>
  </div>
</div>

<iframe id="printFrame" style="display:none;"></iframe>

<?php require_once("footer.php"); ?>

<script>
const userRole = '<?= $role ?>';
$(document).ready(function() {
  let currentStatusFilter = '';

  // Load branches & vendors
  $.getJSON('./api/branches_api.php?action=getActiveBranches', res => {
    if(res.status==='success'){
      $('#filterBranch').append('<option value="">All Branches</option>');
      res.data.forEach(b => $('#filterBranch').append(`<option value="${b.branch_id}">${b.branch_name}</option>`));
    }
  });

  $.getJSON('./api/vendors_api.php?action=simpleList', res => {
    if(res.status==='success'){
      $('#filterVendor').append('<option value="">All Vendors</option>');
      res.data.forEach(v => $('#filterVendor').append(`<option value="${v.vendor_id}">${v.vendor_name}</option>`));
    }
  });

  // Initialize main DataTable with Buttons for Excel/PDF
  const table = $('#purchaseOrdersTable').DataTable({
    processing: true,
    serverSide: true,
    searching: false,
    ajax: {
      url: './api/purchase_order_api.php',
      type: 'POST',
      data: d => {
        d.action='list';
        d.status = currentStatusFilter;
        d.po_number = $('#filterPoNumber').val();
        d.po_type = $('#filterPoType').val();
        d.branch_id = $('#filterBranch').val();
        d.vendor_id = $('#filterVendor').val();
        d.start_date = $('#filterStartDate').val();
        d.end_date = $('#filterEndDate').val();
      },
      dataSrc: json => {
        $('#allCount').text(json.recordsTotal || 0);
        $('#pendingCount').text(json.counts.Pending || 0);
        $('#partialCount').text(json.counts['Partially Fulfilled'] || 0);
        $('#completedCount').text(json.counts.Completed || 0);
        $('#cancelledCount').text(json.counts.Cancelled || 0);
        return json.data;
      }
    },
    columns: [
      {data:'order_number'}, {data:'po_date'}, {data:'po_type'}, {data:'branch_name'}, {data:'vendor_name'},
      {data:'expected_delivery_date'}, {data:'total_amount'}, {data:'status'},
      {data:'po_id', render: (data,type,row)=>{
        let btns=`<div class="btn-group" role="group">
          <button class="btn btn-sm btn-secondary view-po" data-id="${data}"><i class="bi bi-eye"></i></button>`;
        if(userRole==='Requester'){
          if(row.po_edit_approval==='Yes'||row.grn_raised_count==0)
            btns+=`<button class="btn btn-sm btn-primary edit-po" data-id="${data}"><i class="bi bi-pencil-square"></i></button>`;
          else
            btns+=`<button class="btn btn-sm btn-warning request-po" data-id="${data}"><i class="bi bi-send"></i></button>`;
        }else btns+=`<button class="btn btn-sm btn-primary edit-po" data-id="${data}"><i class="bi bi-pencil-square"></i></button>`;
        btns+=`<button class="btn btn-sm btn-info print-po" data-id="${data}"><i class="bi bi-printer"></i></button>`;
        if(userRole==='Admin') btns+=`<button class="btn btn-sm btn-danger delete-po" data-id="${data}"><i class="bi bi-trash"></i></button>`;
        btns+='</div>'; return btns;
      }, orderable:false, searchable:false}
    ],
    pageLength:25,
    lengthMenu:[25,50,100],
    dom: 'Bfrtip',
    buttons:[
      { extend:'excelHtml5', className:'d-none', title:'PurchaseOrders', exportOptions:{columns:':not(:last-child)'} },
      { extend:'pdfHtml5', className:'d-none', title:'PurchaseOrders', orientation:'landscape', pageSize:'A4', exportOptions:{columns:':not(:last-child)'} }
    ]
  });

  // Filter events
  $('.status-filter').on('click', function(){ currentStatusFilter=$(this).data('status')||''; table.ajax.reload(); });
  $('#filterPoNumber').on('keyup', function(){ clearTimeout($.data(this,'timer')); const wait=setTimeout(()=>table.ajax.reload(),500); $(this).data('timer',wait); });
  $('#filterPoType,#filterBranch,#filterVendor,#filterStartDate,#filterEndDate').on('change', ()=>table.ajax.reload());
  $('#resetFilters').on('click', e=>{ e.preventDefault(); $('#filterPoNumber,#filterPoType,#filterBranch,#filterVendor,#filterStartDate,#filterEndDate').val(''); currentStatusFilter=''; table.ajax.reload(); });

  // Export buttons
  $('#exportExcel').on('click', ()=>table.button('.buttons-excel').trigger());
  $('#exportPdf').on('click', ()=>table.button('.buttons-pdf').trigger());

  // Actions
  $(document).on('click', '.edit-po', function(){
    const po_id=$(this).data('id');
    $('<form>',{action:'purchase_order_form', method:'POST'}).append($('<input>',{type:'hidden',name:'po_id',value:po_id})).appendTo('body').submit();
  });

  $(document).on('click', '.delete-po', function(){
    const poId=$(this).data('id');
    Swal.fire({title:'Are you sure?', text:'This will cancel the PO.', icon:'warning', showCancelButton:true, confirmButtonText:'Yes, cancel it'}).then(res=>{
      if(res.isConfirmed) $.post('./api/purchase_order_api.php',{action:'delete', po_id:poId}, r=>{ if(r.status==='success'){ Swal.fire('Cancelled!','PO Cancelled.','success'); table.ajax.reload(null,false); } else Swal.fire('Failed',r.message||'Unable to delete','error'); }, 'json');
    });
  });

  $(document).on('click', '.view-po', function(){ window.location.href=`view_po?po_id=${$(this).data('id')}`; });
  $(document).on('click', '.print-po', function(){
    const poId=$(this).data('id');
    $.get(`purchase_order_print?po_id=${poId}`, html=>{
      const iframe=document.getElementById('printFrame'); const doc=iframe.contentDocument||iframe.contentWindow.document;
      doc.open(); doc.write(html); doc.close(); iframe.contentWindow.focus(); iframe.contentWindow.print();
    });
  });

  $(document).on('click', '.request-po', function(){
    const poId=$(this).data('id'), reqBy='<?=$userId?>', reqDate=new Date().toISOString().slice(0,19).replace('T',' ');
    Swal.fire({title:'Request Edit Approval?', text:'Send edit approval request?', icon:'question', showCancelButton:true, confirmButtonText:'Yes, Request'}).then(res=>{
      if(res.isConfirmed) $.post('./api/purchase_order_api.php',{action:'request_edit_approval', po_id:poId, requested_by:reqBy, requested_date:reqDate}, r=>{
        if(r.status==='success'){ Swal.fire('Requested','Approval request sent!','success'); table.ajax.reload(null,false); } else Swal.fire('Failed',r.message||'Unable to send request','error');
      }, 'json');
    });
  });

});
</script>

<style>
.status-filter { font-size: 0.9rem; padding:6px 10px; }
.table td,.table th { vertical-align: middle !important; }
.btn-group .btn { margin-right: 4px; }
</style>


<?php
require_once("footer.php");
?>