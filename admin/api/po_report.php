<?php
require_once("header.php");
require_once("nav.php");
?>

<div class="container-fluid py-3">
  <div class="card shadow">
    <!-- Card Header -->
    <div class="card-header d-flex justify-content-between align-items-center bg-success">
      <h5 class="mb-0">Purchase Orders</h5>
      <div>
        <button id="exportExcel" class="btn btn-success btn-sm me-2">
          <i class="bi bi-file-earmark-excel"></i> Excel
        </button>
        <button id="exportPdf" class="btn btn-danger btn-sm">
          <i class="bi bi-file-earmark-pdf"></i> PDF
        </button>
      </div>
    </div>

    <!-- Card Body -->
    <div class="card-body">
    <!-- Filters -->
    <div class="row g-2 mb-3">
      <div class="col-md-2">
        <input type="text" id="filterPoNumber" class="form-control" placeholder="PO Number">
      </div>
      <div class="col-md-2">
        <select id="filterPoType" class="form-select">
          <option value="">All Types</option>
          <option value="WITH INDENT">WITH INDENT</option>
          <option value="WITHOUT INDENT">WITHOUT INDENT</option>
        </select>
      </div>
      <div class="col-md-2">
        <select id="filterBranch" class="form-select"></select>
      </div>
      <div class="col-md-2">
        <select id="filterVendor" class="form-select"></select>
      </div>
      <div class="col-md-2">
        <input type="date" id="filterStartDate" class="form-control">
      </div>
      <div class="col-md-2">
        <input type="date" id="filterEndDate" class="form-control">
      </div>
    </div>

    <!-- Reset Button row aligned to right -->
    <div class="row mb-3">
      <div class="col text-end">
        <button id="resetFilters" class="btn btn-outline-secondary">Reset Filters</button>
      </div>
    </div>

      <!-- DataTable -->
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
            <th>Action</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="poModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content shadow-lg rounded-3 border-0">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Purchase Order Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="poInfo" class="mb-3"></div>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Discount %</th>
                <th>Tax %</th>
                <th>Amount</th>
              </tr>
            </thead>
            <tbody id="poItems"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php require_once("footer.php"); ?>

<script>
$(document).ready(function () {

  // Load Branches
  $.getJSON('./api/branches_api.php?action=getActiveBranches', function (res) {
    if (res.status === 'success') {
      $('#filterBranch').append('<option value="">All Branches</option>');
      res.data.forEach(b => $('#filterBranch').append(`<option value="${b.branch_id}">${b.branch_name}</option>`));
    }
  });

  // Load Vendors
  $.getJSON('./api/vendors_api.php?action=simpleList', function (res) {
    if (res.status === 'success') {
      $('#filterVendor').append('<option value="">All Vendors</option>');
      res.data.forEach(v => $('#filterVendor').append(`<option value="${v.vendor_id}">${v.vendor_name}</option>`));
    }
  });

  // Init DataTable
  let table = $('#purchaseOrdersTable').DataTable({
    processing: true,
    serverSide: true,
    searching: false,   // disable built-in search box
    ajax: {
      url: './api/purchase_order_api.php?action=list',
      type: 'POST',
      data: function (d) {
        d.po_number  = $('#filterPoNumber').val();
        d.po_type    = $('#filterPoType').val();
        d.branch_id  = $('#filterBranch').val();
        d.vendor_id  = $('#filterVendor').val();
        d.start_date = $('#filterStartDate').val();
        d.end_date   = $('#filterEndDate').val();
      }
    },
    columns: [
      { data: 'order_number' },
      { data: 'po_date' },
      { data: 'po_type' },
      { data: 'branch_name' },
      { data: 'vendor_name' },
      { data: 'expected_delivery_date' },
      { data: 'total_amount' },
      { 
        data: 'po_id',
        render: function (data) {
          return `<button class="btn btn-sm btn-success view-po" data-id="${data}"><i class="bi bi-eye"></i></button>`;
        }
      }
    ],
    dom: 'Bfrtip',
    buttons: [
      { extend: 'excelHtml5', className: 'd-none', title: 'PurchaseOrders' },
      { extend: 'pdfHtml5', className: 'd-none', title: 'PurchaseOrders' }
    ]
  });

  // Export
  $('#exportExcel').on('click', function () {
    table.button('.buttons-excel').trigger();
  });
  $('#exportPdf').on('click', function () {
    table.button('.buttons-pdf').trigger();
  });

  // View PO in Modal
  $(document).on('click', '.view-po', function () {
    let poId = $(this).data('id');
    $.getJSON('./api/purchase_order_api.php?action=get&po_id=' + poId, function (res) {
      if (res.status === 'success') {
        let po = res.data.po[0];
        let items = res.data.items;
        
        $('#poInfo').html(`
          <p><strong>PO Number:</strong> ${po.order_number}</p>
          <p><strong>Date:</strong> ${po.po_date}</p>
          <p><strong>Vendor:</strong> ${po.vendor_name}</p>
          <p><strong>Branch:</strong> ${po.branch_name}</p>
        `);

        let rows = '';
        items.forEach(i => {
          rows += `
            <tr>
              <td>${i.item_name}</td>
              <td>${i.quantity}</td>
              <td>${i.unit_price}</td>
              <td>${i.discount_percentage}</td>
              <td>${i.tax_percentage}</td>
              <td>${i.subjective_amount}</td>
            </tr>`;
        });
        $('#poItems').html(rows);

        let modal = new bootstrap.Modal(document.getElementById('poModal'));
        modal.show();
      }
    });
  });

  // Filters
  $('#filterPoNumber').on('keyup', function () {
    clearTimeout($.data(this, 'timer'));
    let wait = setTimeout(() => table.ajax.reload(), 500);
    $(this).data('timer', wait);
  });

  $('#filterPoType, #filterBranch, #filterVendor, #filterStartDate, #filterEndDate').on('change', function () {
    table.ajax.reload();
  });

});

  // Reset filters
  $('#resetFilters').on('click', function(e){
    e.preventDefault();
    // Clear all filters
    $('#filterPoNumber').val('');
    $('#filterPoType').val('');
    $('#filterBranch').val('');
    $('#filterVendor').val('');
    $('#filterStartDate').val('');
    $('#filterEndDate').val('');
    // Reload DataTable
    table.ajax.reload();
  });


</script>
