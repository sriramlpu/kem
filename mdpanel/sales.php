<?php
require_once("header.php");
require_once("nav.php");
?>
<style>
  #salesTable {
    table-layout: fixed;
    width: 100% !important;
    white-space: normal;
  }
  /* For Select2 dropdown to appear above */
  .select2-container--open .select2-dropdown {
    z-index: 9999;
  }
</style>
<section class="container-fluid section">
<div class="row g-4">
  <!-- Left Card: Sales Filters -->
  <div class="col-md-3">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-success text-white fw-bold">
        Sales Filters
      </div>
      <div class="card-body">
        <form id="salesFilters">
          <div class="form-floating mb-3">
            <select class="form-select" id="branch_id" name="branch_id">
              <option value="">All Branches</option>
            </select>
            <label for="branch_id">Branch</label>
          </div>
          <div class="form-floating mb-3">
            <input type="date" class="form-control" id="start_date" name="start_date">
            <label for="start_date">Start Date</label>
          </div>
          <div class="form-floating mb-3">
            <input type="date" class="form-control" id="end_date" name="end_date">
            <label for="end_date">End Date</label>
          </div>
          <div class="form-floating mb-3">
            <select class="form-select" id="status" name="status">
              <option value="">All Status</option>
              <option value="completed">Completed</option>
              <option value="pending">Pending</option>
              <option value="cancelled">Cancelled</option>
            </select>
            <label for="status">Status</label>
          </div>
          <button type="button" class="btn btn-orange w-100" id="applyFilters">Apply Filters</button>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Right Card: Sales Data -->
  <div class="col-md-8">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-success text-white fw-bold d-flex justify-content-between align-items-center">
        <span>Sales Data</span>
        <div>
          <button class="btn btn-sm btn-light text-primary" id="exportExcel">Excel</button>
          <button class="btn btn-sm btn-light text-secondary" id="exportCsv">CSV</button>
        </div>
      </div>
      <div class="card-body">
        <!-- Search -->
        <div class="row g-3 mb-3">
          <div class="col-md-12">
            <input type="text" id="filter_search" class="form-control" placeholder="Search sales..." />
          </div>
        </div>
        
        <!-- Sales Table -->
        <div class="table-responsive">
          <table id="salesTable" class="table table-hover align-middle mb-0">
            <thead>
              <tr class="table-primary">
                <th class="sticky-col">S.No</th>
                <th>Date</th>
                <th>Invoice #</th>
                <th>Customer</th>
                <th>Branch</th>
                <th>Amount</th>
                <th>Status</th>
                <th class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <!-- Data filled by DataTables AJAX -->
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
</section>

<!-- View Sale Modal -->
<div class="modal fade" id="viewSaleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content shadow-lg">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Sale Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row mb-3">
          <div class="col-md-6">
            <p><strong>Invoice #:</strong> <span id="view_sale_invoice"></span></p>
            <p><strong>Date:</strong> <span id="view_sale_date"></span></p>
            <p><strong>Customer:</strong> <span id="view_sale_customer"></span></p>
          </div>
          <div class="col-md-6">
            <p><strong>Branch:</strong> <span id="view_sale_branch"></span></p>
            <p><strong>Amount:</strong> <span id="view_sale_amount"></span></p>
            <p><strong>Status:</strong> <span id="view_sale_status"></span></p>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Item</th>
                <th>Quantity</th>
                <th>Price</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody id="view_sale_items">
              <!-- Items will be loaded here -->
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
 
<script>
$(function(){
  // Load branches dropdown
  function loadBranches() {
    $.post('./api/common_api.php', { action: 'getBranches' }, function(res){
      if(res.status === 'success'){
        let options = '<option value="">All Branches</option>';
        $.each(res.data, function(i, branch){
          options += `<option value="${branch.branch_id}">${branch.branch_name}</option>`;
        });
        $('#branch_id').html(options);
      }
    }, 'json');
  }
  
  // Initialize branches
  loadBranches();
  
  // Set default dates (last 30 days)
  var today = new Date();
  var thirtyDaysAgo = new Date(today);
  thirtyDaysAgo.setDate(today.getDate() - 30);
  
  $('#start_date').val(formatDate(thirtyDaysAgo));
  $('#end_date').val(formatDate(today));
  
  // Helper function to format date as YYYY-MM-DD
  function formatDate(date) {
    var d = new Date(date),
        month = '' + (d.getMonth() + 1),
        day = '' + d.getDate(),
        year = d.getFullYear();
    
    if (month.length < 2) month = '0' + month;
    if (day.length < 2) day = '0' + day;
    
    return [year, month, day].join('-');
  }
  
  // Initialize DataTable
  var table = $('#salesTable').DataTable({
    processing: true,
    serverSide: true,
    autoWidth: false,
    dom: 'Brtip',
    buttons: [
      {
        extend: 'excelHtml5',
        className: 'buttons-excel d-none'
      },
      {
        extend: 'csvHtml5',
        className: 'buttons-csv d-none'
      }
    ],
    ajax: {
      url: './api/sales_api.php',
      type: 'POST',
      data: function(d) {
        d.action = 'getSales';
        d.filter_search = $('#filter_search').val();
        d.branch_id = $('#branch_id').val();
        d.start_date = $('#start_date').val();
        d.end_date = $('#end_date').val();
        d.status = $('#status').val();
      }
    },
    columns: [
      { data: 'sno' },
      { data: 'date' },
      { data: 'invoice_no' },
      { data: 'customer_name' },
      { data: 'branch_name' },
      { data: 'amount' },
      { data: 'status' },
      {
        data: 'sale_id',
        orderable: false,
        className: 'text-center',
        render: function(id) {
          return `
            <button class="btn btn-sm btn-outline-primary me-1 view-sale" data-id="${id}" title="View Details">
              <i class="bi bi-eye"></i>
            </button>
          `;
        }
      }
    ],
    order: [[0, 'desc']]
  });
  
  // Export buttons
  $('#exportExcel').on('click', function(){ table.button('.buttons-excel').trigger(); });
  $('#exportCsv').on('click', function(){ table.button('.buttons-csv').trigger(); });
  
  // Reload on search
  $('#filter_search').on('keyup', function(){ table.ajax.reload(); });
  
  // Apply filters
  $('#applyFilters').on('click', function(){
    table.ajax.reload();
  });
  
  // View sale details
  $(document).on('click', '.view-sale', function(){
    let saleId = $(this).data('id');
    
    $.post('./api/sales_api.php', { action: 'getSaleDetails', id: saleId }, function(res){
      if(res.status === 'success'){
        let d = res.data;
        $('#view_sale_invoice').text(d.invoice_no);
        $('#view_sale_date').text(d.date);
        $('#view_sale_customer').text(d.customer_name);
        $('#view_sale_branch').text(d.branch_name);
        $('#view_sale_amount').text(d.amount);
        $('#view_sale_status').text(d.status);
        
        // Load sale items
        let itemsHtml = '';
        $.each(d.items, function(i, item){
          itemsHtml += `
            <tr>
              <td>${item.item_name}</td>
              <td>${item.quantity}</td>
              <td>${item.price}</td>
              <td>${item.total}</td>
            </tr>
          `;
        });
        $('#view_sale_items').html(itemsHtml);
        
        $('#viewSaleModal').modal('show');
      } else {
        var mssg = res.message || 'Failed to fetch sale data';
        showAlert(mssg, 'danger');
      }
    }, 'json');
  });
});
</script> 
<script src="./js/showAlert.js"></script>
<?php
require_once("footer.php");
?>