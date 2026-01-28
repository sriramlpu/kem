<?php
require_once("header.php");
require_once("nav.php");
?>
<section class="container-fluid section">
  <div class="row g-4">
    <div class="col-md-12">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
          <h4 class="mb-0">Goods Received Notes (GRN) Report</h4>
          <div class="d-flex align-items-center gap-2">
            <button id="exportExcel" class="btn btn-success btn-sm">
              <i class="bi bi-file-earmark-excel"></i> Excel
            </button>
            <button id="exportPdf" class="btn btn-danger btn-sm">
              <i class="bi bi-file-earmark-pdf"></i> PDF
            </button>
            <a href="grn.php" class="btn btn-light">
              <i class="fas fa-plus me-2"></i>Create New GRN
            </a>
          </div>
        </div>

        <div class="card-body">
          <!-- Filters -->
          <div class="row g-3 mb-3 align-items-end">
            <div class="col-md-3">
              <label class="form-label fw-semibold">GRN Date</label>
              <div class="d-flex gap-2">
                <input type="date" id="grnFrom" class="form-control" placeholder="Start date">
                <input type="date" id="grnTo" class="form-control" placeholder="End date">
              </div>
            </div>

            <!-- PO Number as searchable datalist -->
            <div class="col-md-3">
              <label class="form-label fw-semibold">PO Number</label>
              <input type="text" id="filterPoNo" class="form-control" placeholder="Type PO Number" list="poNoList">
              <datalist id="poNoList"></datalist>
            </div>

            <div class="col-md-3">
              <label class="form-label fw-semibold">GRN Number</label>
              <input type="text" id="filterGrnNo" class="form-control" placeholder="Type GRN Number" list="grnNoList">
              <datalist id="grnNoList"></datalist>
            </div>

            <div class="col-md-3">
              <label class="form-label fw-semibold">Invoice Date</label>
              <div class="d-flex gap-2">
                <input type="date" id="invFrom" class="form-control" placeholder="Start date">
                <input type="date" id="invTo" class="form-control" placeholder="End date">
              </div>
            </div>

            <div class="col-md-3">
              <label class="form-label fw-semibold">Invoice Number</label>
              <input type="text" id="filterInvoiceNo" class="form-control" placeholder="Type Invoice Number" list="invNoList">
              <datalist id="invNoList"></datalist>
            </div>

            <div class="col-md-3">
              <label class="form-label fw-semibold">Branch</label>
              <select id="filterBranch" class="form-select">
                <option value="">Select Branch</option>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label fw-semibold">Vendor Name</label>
              <select id="filterVendor" class="form-select">
                <option value="">Select Vendor</option>
              </select>
            </div>

            <div class="col-md-3 d-flex gap-2 justify-content-end">
              <button id="resetFilters" class="btn btn-outline-secondary">Reset Filters</button>
            </div>
          </div>

          <!-- Table -->
          <div class="table-responsive">
            <table id="grnTable" class="table table-striped table-hover">
              <thead class="table-dark">
                <tr>
                  <th>GRN No</th>
                  <th>GRN Date</th>
                  <th>Vendor</th>
                  <th>Branch</th>
                  <th>PO No</th>
                  <th>Invoice No</th>
                  <th>Invoice Date</th>
                  <th>Total</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="d-flex justify-content-between align-items-center mt-3">
            <div>
              Showing <span id="from">0</span> to <span id="to">0</span> of <span id="total">0</span>
            </div>
            <nav>
              <ul class="pagination pagination-sm" id="pagination"></ul>
            </nav>
          </div>

          <!-- Hidden export table (used only for DataTables export) -->
          <div class="d-none">
            <table id="grnExportTable" class="table">
              <thead>
                <tr>
                  <th>GRN No</th>
                  <th>GRN Date</th>
                  <th>Vendor</th>
                  <th>Branch</th>
                  <th>PO No</th>
                  <th>Invoice No</th>
                  <th>Invoice Date</th>
                  <th>Total</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>

        </div>
      </div>
    </div>
  </div>
</section>

<!-- View GRN Modal (full details + document preview + items) -->
<div class="modal fade" id="grnModal" tabindex="-1" aria-labelledby="grnModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="grnModalLabel">GRN Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="row mb-3">
          <div class="col-md-6">
            <h6 class="fw-semibold">GRN Information</h6>
            <table class="table table-sm table-borderless mb-0">
              <tr><th>GRN Number:</th><td id="modalGrnNumber"></td></tr>
              <tr><th>GRN Date:</th><td id="modalGrnDate"></td></tr>
              <tr><th>Vendor:</th><td id="modalVendor"></td></tr>
              <tr><th>Branch:</th><td id="modalBranch"></td></tr>
              <tr><th>PO Number:</th><td id="modalPoNumber"></td></tr>
              <tr><th>Status:</th><td id="modalStatus"></td></tr>
            </table>
          </div>
          <div class="col-md-6">
            <h6 class="fw-semibold">Invoice</h6>
            <table class="table table-sm table-borderless mb-0">
              <tr><th>Invoice Number:</th><td id="modalInvoiceNumber"></td></tr>
              <tr><th>Invoice Date:</th><td id="modalInvoiceDate"></td></tr>
              <tr><th>Total Amount:</th><td id="modalTotalAmount"></td></tr>
            </table>

            <h6 class="fw-semibold mt-3">Remarks</h6>
            <p id="modalRemarks" class="bg-light p-3 rounded small mb-0"></p>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-12">
            <h6 class="fw-semibold">Document</h6>
            <div id="modalDocument" class="border rounded p-2 bg-light"></div>
          </div>
        </div>

        <h6 class="fw-semibold">Items</h6>
        <div class="table-responsive">
          <table class="table table-sm table-striped">
            <thead>
              <tr>
                <th>Item Name</th>
                <th>Quantity Received</th>
                <th>Unit Price</th>
                <th>Amount</th>
              </tr>
            </thead>
            <tbody id="modalItems"></tbody>
          </table>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
$(function(){
  let page=1, perPage=10, total=0;

  // --- EXPORT: hidden DataTable instance (never touch the visible table) ---
  let exportDT = null;
  function initExportDT(){
    if (exportDT) { exportDT.destroy(); }
    exportDT = $('#grnExportTable').DataTable({
      paging: false,
      searching: false,
      ordering: false,
      info: false,
      dom: 'Bfrtip',
      buttons: [
        { extend: 'excelHtml5', className: 'd-none', title: 'GRN_Report' },
        { extend: 'pdfHtml5',   className: 'd-none', title: 'GRN_Report', orientation: 'landscape', pageSize: 'A4' }
      ],
      destroy: true
    });
    // Hide the auto-rendered buttons UI
    $('.dt-buttons').hide();
  }
  function fillExportTable(rows){
    const $et = $('#grnExportTable tbody').empty();
    (rows||[]).forEach(r=>{
      $et.append(`
        <tr>
          <td>${r.grn_number||''}</td>
          <td>${r.grn_date||''}</td>
          <td>${r.vendor_name||''}</td>
          <td>${r.branch_name||''}</td>
          <td>${r.po_number||''}</td>
          <td>${r.invoice_number||''}</td>
          <td>${r.invoice_date||''}</td>
          <td>${r.total_amount ?? 0}</td>
          <td>${r.status||'Draft'}</td>
        </tr>
      `);
    });
    initExportDT();
  }

  // Lookups
  $.getJSON('./api/branches_api.php', {action:'getActiveBranches'}, res=>{
    if(res?.status==='success'){ (res.data||[]).forEach(b=> $('#filterBranch').append(`<option value="${b.branch_id}">${b.branch_name}</option>`)); }
  });
  $.getJSON('./api/vendors_api.php', {action:'simpleList'}, res=>{
    if(res?.status==='success'){ (res.data||[]).forEach(v=> $('#filterVendor').append(`<option value="${v.vendor_id}">${v.vendor_name}</option>`)); }
  });

  // Build suggestions: PO, GRN, Invoice
  function loadSuggestions(){
    // PO numbers
    $.getJSON('./api/purchase_order_api.php', {action:'getActivePOs'}, res=>{
      const $p = $('#poNoList').empty();
      if(res?.status==='success'){
        const seen = new Set();
        (res.data||[]).forEach(po=>{
          const no = po.order_number || '';
          if(no && !seen.has(no)){
            seen.add(no);
            $p.append(`<option value="${no}">`);
          }
        });
      }
    });

    // GRN/Invoice numbers (from GRNs)
    $.post('./api/grn_api.php', {action:'list', start:0, length:500}, function(res){
      if(res?.status==='success' && Array.isArray(res.data)){
        const $g = $('#grnNoList').empty(), $i = $('#invNoList').empty();
        const gset=new Set(), iset=new Set();
        res.data.forEach(r=>{
          if(r.grn_number && !gset.has(r.grn_number)){ gset.add(r.grn_number); $g.append(`<option value="${r.grn_number}">`); }
          if(r.invoice_number && !iset.has(r.invoice_number)){ iset.add(r.invoice_number); $i.append(`<option value="${r.invoice_number}">`); }
        });
      }
    }, 'json');
  }
  loadSuggestions();

  function loadGRNs(p=1){
    page=p;

    $.post('./api/grn_api.php',{
      action:'list',
      start:(page-1)*perPage,
      length:perPage,

      // dedicated filters
      po_number: ($('#filterPoNo').val() || '').trim(),
      grn_number: ($('#filterGrnNo').val() || '').trim(),
      branch_id: $('#filterBranch').val(),
      vendor_id: $('#filterVendor').val(),
      invoice_number: ($('#filterInvoiceNo').val() || '').trim(),

      // date filters
      dateFrom: $('#grnFrom').val(),
      dateTo:   $('#grnTo').val(),
      invoice_from: $('#invFrom').val(),
      invoice_to:   $('#invTo').val()
    }, function(res){
      if(res?.status==='success'){
        total = Number(res.recordsFiltered ?? res.recordsTotal ?? 0);
        renderRows(res.data||[]);
        renderPager();
        fillExportTable(res.data||[]); // sync hidden export table with what you see
      } else {
        renderRows([]); total=0; renderPager();
        fillExportTable([]);
      }
    }, 'json');
  }

  function renderRows(rows){
    const $tb = $('#grnTable tbody').empty();
    if(!rows.length){ $tb.append('<tr><td colspan="10" class="text-center">No records</td></tr>'); }
    (rows||[]).forEach(r=>{
      $tb.append(`
        <tr>
          <td>${r.grn_number||''}</td>
          <td>${r.grn_date||''}</td>
          <td>${r.vendor_name||''}</td>
          <td>${r.branch_name||''}</td>
          <td>${r.po_number||''}</td>
          <td>${r.invoice_number||''}</td>
          <td>${r.invoice_date||''}</td>
          <td>${r.total_amount ?? 0}</td>
          <td>${r.status||'Draft'}</td>
          <td>
            <button class="btn btn-sm btn-info view" data-id="${r.grn_id}" title="View"><i class="fas fa-eye"></i></button>
          </td>
        </tr>
      `);
    });
    $('#from').text(rows.length ? (page-1)*perPage+1 : 0);
    $('#to').text(Math.min(page*perPage, total));
    $('#total').text(total);
  }

  function renderPager(){
    const pages = Math.max(1, Math.ceil(total/perPage));
    const $p = $('#pagination').empty();
    for(let i=1;i<=pages;i++){
      $p.append(`<li class="page-item ${i===page?'active':''}"><a class="page-link" href="#" data-p="${i}">${i}</a></li>`);
    }
  }

  // View: show full details + document + items
  $(document).on('click','.view', function(){
    const id = $(this).data('id');

    $.post('./api/grn_api.php',{action:'get',grn_id:id}, function(res){
      if(!(res?.status==='success' && res.data)){ return; }
      const g = res.data;

      const badgeClass = (s=>{
        s=String(s||'').toLowerCase();
        if(s==='approved') return 'success';
        if(s==='pending') return 'warning';
        if(s==='rejected'||s==='cancelled') return 'danger';
        return 'secondary';
      })(g.status);

      $('#modalGrnNumber').text(g.grn_number||'');
      $('#modalGrnDate').text(g.grn_date||'');
      $('#modalVendor').text(g.vendor_name||'');
      $('#modalBranch').text(g.branch_name||'');
      $('#modalPoNumber').text(g.po_number||'');
      $('#modalInvoiceNumber').text(g.invoice_number||'');
      $('#modalInvoiceDate').text(g.invoice_date||'');
      $('#modalTotalAmount').text(g.total_amount||0);
      $('#modalStatus').html(`<span class="badge bg-${badgeClass}">${g.status||'Draft'}</span>`);
      $('#modalRemarks').text(g.remarks || 'No remarks');

      const $doc = $('#modalDocument').empty();
      if (g.document_path) {
        const url = g.document_path;
        const ext = String(url).split('.').pop().toLowerCase();
        if (['png','jpg','jpeg','gif','webp'].includes(ext)) {
          $doc.append(`<img src="${url}" alt="Document" class="img-fluid rounded">`);
        } else if (ext === 'pdf') {
          $doc.append(`<embed src="${url}" type="application/pdf" width="100%" height="360px" />`);
        } else {
          $doc.append(`<a href="${url}" target="_blank" class="btn btn-sm btn-info">Open Document</a>`);
        }
      } else {
        $doc.append('<p class="text-muted mb-0">No document attached</p>');
      }

      $.post('./api/grn_api.php', {action:'getItems', grn_id:id}, function(ri){
        const $tbody = $('#modalItems').empty();
        if(ri?.status==='success' && Array.isArray(ri.data) && ri.data.length){
          ri.data.forEach(it=>{
            $tbody.append(`
              <tr>
                <td>${it.item_name || 'N/A'}</td>
                <td>${Number(it.qty_received ?? it.quantity ?? 0)}</td>
                <td>${Number(it.unit_price ?? 0)}</td>
                <td>${Number(it.amount ?? 0)}</td>
              </tr>
            `);
          });
        }else{
          $tbody.append('<tr><td colspan="4" class="text-center">No items</td></tr>');
        }
        new bootstrap.Modal(document.getElementById('grnModal')).show();
      }, 'json');

    }, 'json');
  });

  // Pagination click
  $(document).on('click', '#pagination .page-link', function(e){ e.preventDefault(); loadGRNs(parseInt($(this).data('p'),10)); });

  // Reset
  $('#resetFilters').on('click', function(e){
    e.preventDefault();
    $('#grnFrom').val('');
    $('#grnTo').val('');
    $('#filterPoNo').val('');
    $('#filterGrnNo').val('');
    $('#invFrom').val('');
    $('#invTo').val('');
    $('#filterInvoiceNo').val('');
    $('#filterBranch').val('');
    $('#filterVendor').val('');
    loadGRNs(1);
  });

  // Auto-apply on change/typing
  $('#grnFrom,#grnTo,#invFrom,#invTo,#filterPoNo,#filterGrnNo,#filterInvoiceNo,#filterBranch,#filterVendor')
    .on('change keyup', ()=>loadGRNs(1));

  // Export triggers (operate on hidden DataTable)
  $('#exportExcel').on('click', function(){ if (exportDT) exportDT.button('.buttons-excel').trigger(); });
  $('#exportPdf').on('click',   function(){ if (exportDT) exportDT.button('.buttons-pdf').trigger(); });

  // Init
  loadGRNs();
});
</script>
<?php require_once("footer.php"); ?>
