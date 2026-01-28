<?php
require_once("header.php");
require_once("nav.php");
?>
<section class="container-fluid section">
  <div class="row g-4">
    <div class="col-md-12">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
          <h4 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Item-wise GRN Report</h4>
          <div class="d-flex align-items-center gap-2">
            <button id="exportExcel" class="btn btn-success btn-sm">
              <i class="bi bi-file-earmark-excel"></i> Excel
            </button>
            <button id="exportPdf" class="btn btn-danger btn-sm">
              <i class="bi bi-file-earmark-pdf"></i> PDF
            </button>
            <button id="exportPrint" class="btn btn-primary btn-sm">
              <i class="bi bi-printer"></i> Print
            </button>
            <a href="grn_report.php" class="btn btn-light">
              <i class="fas fa-arrow-left me-2"></i>Back to GRN Report
            </a>
          </div>
        </div>

        <div class="card-body">
          <!-- Filters -->
          <div class="row g-3 mb-3 align-items-end">
            <div class="col-md-3">
              <label class="form-label fw-semibold">Date Range</label>
              <div class="d-flex gap-2">
                <input type="date" id="dateFrom" class="form-control" placeholder="From">
                <input type="date" id="dateTo" class="form-control" placeholder="To">
              </div>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Item Category</label>
              <select id="filterCategory" class="form-select">
                <option value="">All Categories</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Item Name</label>
              <input type="text" id="filterItem" class="form-control" placeholder="Search item...">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Vendor</label>
              <select id="filterVendor" class="form-select">
                <option value="">All Vendors</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Branch</label>
              <select id="filterBranch" class="form-select">
                <option value="">All Branches</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Sort By</label>
              <select id="sortBy" class="form-select">
                <option value="total_spent">Total Spent (High to Low)</option>
                <option value="quantity">Quantity (High to Low)</option>
                <option value="item_name">Item Name (A-Z)</option>
              </select>
            </div>
            <div class="col-md-3 d-flex gap-2 justify-content-end">
              <button id="applyFilters" class="btn btn-primary">
                <i class="fas fa-filter me-1"></i>Apply
              </button>
              <button id="resetFilters" class="btn btn-outline-secondary">
                <i class="fas fa-redo me-1"></i>Reset
              </button>
            </div>
          </div>

          <!-- Summary Cards -->
          <div class="row g-3 mb-4">
            <div class="col-md-3">
              <div class="card bg-primary text-white">
                <div class="card-body">
                  <h6 class="card-title mb-2">Total Items</h6>
                  <h3 class="mb-0" id="totalItems">0</h3>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card bg-success text-white">
                <div class="card-body">
                  <h6 class="card-title mb-2">Total Spent</h6>
                  <h3 class="mb-0" id="totalSpent">₹0.00</h3>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card bg-info text-white">
                <div class="card-body">
                  <h6 class="card-title mb-2">Total Quantity</h6>
                  <h3 class="mb-0" id="totalQty">0</h3>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card bg-warning text-white">
                <div class="card-body">
                  <h6 class="card-title mb-2">Avg. Price/Item</h6>
                  <h3 class="mb-0" id="avgPrice">₹0.00</h3>
                </div>
              </div>
            </div>
          </div>

          <!-- Table -->
          <div class="table-responsive">
            <table id="itemwiseTable" class="table table-striped table-hover">
              <thead class="table-dark">
                <tr>
                  <th>S.No</th>
                  <th>Item Name</th>
                  <th>Category</th>
                  <th>Total Qty Purchased</th>
                  <th>Total Qty Returned</th>
                  <th>Net Quantity</th>
                  <th>Avg Unit Price</th>
                  <th>Total Amount (Before Discount)</th>
                  <th>Total Discount</th>
                  <th>Total GST</th>
                  <th>Total Spent (Net)</th>
                  <th>Last Purchase Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody></tbody>
              <tfoot class="table-secondary">
                <tr>
                  <th colspan="3" class="text-end">Grand Totals:</th>
                  <th class="text-end"></th>
                  <th class="text-end"></th>
                  <th class="text-end"></th>
                  <th class="text-end"></th>
                  <th class="text-end"></th>
                  <th class="text-end"></th>
                  <th class="text-end"></th>
                  <th class="text-end"></th>
                  <th colspan="2"></th>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Item Detail Modal -->
<div class="modal fade" id="itemDetailModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Item Purchase History</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="itemDetailContent">
        <div class="text-center p-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<iframe id="printFrame" style="display:none;"></iframe>

<script>
$(function(){

  // Load dropdowns
  $.getJSON('./api/branches_api.php', {action:'getActiveBranches'}, res => { 
    if(res.status==='success') res.data.forEach(b => $('#filterBranch').append(`<option value="${b.branch_id}">${b.branch_name}</option>`)); 
  });
  
  $.getJSON('./api/vendors_api.php', {action:'simpleList'}, res => { 
    if(res.status==='success') res.data.forEach(v => $('#filterVendor').append(`<option value="${v.vendor_id}">${v.vendor_name}</option>`)); 
  });

  $.getJSON('./api/items_api.php', {action:'getCategories'}, res => {
    if(res.status==='success') res.data.forEach(c => $('#filterCategory').append(`<option value="${c.category_id}">${c.category_name}</option>`));
  });

  // Initialize DataTable
  const itemTable = $('#itemwiseTable').DataTable({
    serverSide: true,
    processing: true,
    pageLength: 25,
    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
    ajax: {
      url: './api/grn_itemwise_api.php',
      type: 'POST',
      data: function(d) {
        d.action = 'list';
        d.date_from = $('#dateFrom').val();
        d.date_to = $('#dateTo').val();
        d.category_id = $('#filterCategory').val();
        d.item_name = $('#filterItem').val();
        d.vendor_id = $('#filterVendor').val();
        d.branch_id = $('#filterBranch').val();
        d.sort_by = $('#sortBy').val();
      },
      error: function(xhr, error, thrown) {
        console.error('AJAX Error:', {xhr, error, thrown});
        console.error('Response Text:', xhr.responseText);
        alert('Error loading data. Check console for details.');
      },
      dataSrc: function(res) {
        console.log('📊 Response:', res);
        
        if (res.status === 'error') {
          alert('Error: ' + res.message);
          return [];
        }
        
        // Update summary cards
        if(res.summary) {
          $('#totalItems').text(res.summary.total_items || 0);
          $('#totalSpent').text('₹' + parseFloat(res.summary.total_spent || 0).toFixed(2));
          $('#totalQty').text(parseFloat(res.summary.total_quantity || 0).toFixed(2));
          $('#avgPrice').text('₹' + parseFloat(res.summary.avg_price || 0).toFixed(2));
        }
        
        return res.data || [];
      }
    },
    columns: [
      { 
        data: null,
        orderable: false,
        render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1
      },
      { data: 'item_name' },
      { data: 'category_name' },
      { 
        data: 'total_qty_purchased', 
        render: d => parseFloat(d || 0).toFixed(2),
        className: 'text-end'
      },
      { 
        data: 'total_qty_returned', 
        render: d => `<span class="text-danger">${parseFloat(d || 0).toFixed(2)}</span>`,
        className: 'text-end'
      },
      { 
        data: 'net_quantity', 
        render: d => `<strong>${parseFloat(d || 0).toFixed(2)}</strong>`,
        className: 'text-end'
      },
      { 
        data: 'avg_unit_price', 
        render: d => `₹${parseFloat(d || 0).toFixed(2)}`,
        className: 'text-end'
      },
      { 
        data: 'total_before_discount', 
        render: d => `₹${parseFloat(d || 0).toFixed(2)}`,
        className: 'text-end'
      },
      { 
        data: 'total_discount', 
        render: d => `<span class="text-warning">₹${parseFloat(d || 0).toFixed(2)}</span>`,
        className: 'text-end'
      },
      { 
        data: 'total_gst', 
        render: d => `<span class="text-info">₹${parseFloat(d || 0).toFixed(2)}</span>`,
        className: 'text-end'
      },
      { 
        data: 'total_spent', 
        render: d => `<strong class="text-success">₹${parseFloat(d || 0).toFixed(2)}</strong>`,
        className: 'text-end'
      },
      { data: 'last_purchase_date' },
      {
        data: 'item_id',
        orderable: false,
        render: (id, type, row) => `
          <button class="btn btn-sm btn-info view-detail" data-id="${id}" data-name="${row.item_name}" title="View Details">
            <i class="fas fa-eye"></i> Details
          </button>
        `
      }
    ],
    footerCallback: function(row, data, start, end, display) {
      const api = this.api();
      
      let totalPurchased = 0, totalReturned = 0, netQty = 0;
      let totalBeforeDiscount = 0, totalDiscount = 0, totalGst = 0, totalSpent = 0;
      
      api.rows({page: 'current'}).every(function() {
        const d = this.data();
        totalPurchased += parseFloat(d.total_qty_purchased || 0);
        totalReturned += parseFloat(d.total_qty_returned || 0);
        netQty += parseFloat(d.net_quantity || 0);
        totalBeforeDiscount += parseFloat(d.total_before_discount || 0);
        totalDiscount += parseFloat(d.total_discount || 0);
        totalGst += parseFloat(d.total_gst || 0);
        totalSpent += parseFloat(d.total_spent || 0);
      });
      
      $(api.column(3).footer()).html(`<strong>${totalPurchased.toFixed(2)}</strong>`);
      $(api.column(4).footer()).html(`<strong class="text-danger">${totalReturned.toFixed(2)}</strong>`);
      $(api.column(5).footer()).html(`<strong>${netQty.toFixed(2)}</strong>`);
      $(api.column(7).footer()).html(`<strong>₹${totalBeforeDiscount.toFixed(2)}</strong>`);
      $(api.column(8).footer()).html(`<strong class="text-warning">₹${totalDiscount.toFixed(2)}</strong>`);
      $(api.column(9).footer()).html(`<strong class="text-info">₹${totalGst.toFixed(2)}</strong>`);
      $(api.column(10).footer()).html(`<strong class="text-success">₹${totalSpent.toFixed(2)}</strong>`);
    }
  });

  // Filters
  $('#applyFilters, #sortBy').on('change', () => itemTable.ajax.reload());
  
  $('#resetFilters').click(() => {
    $('#dateFrom, #dateTo, #filterItem').val('');
    $('#filterCategory, #filterVendor, #filterBranch, #sortBy').val('');
    itemTable.ajax.reload();
  });

  let filterTimeout;
  $('#filterItem').on('keyup', function() {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(() => itemTable.ajax.reload(), 500);
  });

  // Export handlers
  function buildExportUrl(type) {
    const params = new URLSearchParams({
      type: type,
      date_from: $('#dateFrom').val(),
      date_to: $('#dateTo').val(),
      category_id: $('#filterCategory').val(),
      item_name: $('#filterItem').val(),
      vendor_id: $('#filterVendor').val(),
      branch_id: $('#filterBranch').val(),
      sort_by: $('#sortBy').val()
    });
    return './api/grn_itemwise_export_api.php?' + params.toString();
  }

  $('#exportExcel').click(function() {
    const btn = $(this);
    const originalText = btn.html();
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Exporting...');
    
    const iframe = $('<iframe>', {src: buildExportUrl('excel'), style: 'display:none;'}).appendTo('body');
    
    setTimeout(() => {
      btn.prop('disabled', false).html(originalText);
      iframe.remove();
    }, 2000);
  });

  $('#exportPdf').click(function() {
    window.open(buildExportUrl('pdf'), '_blank');
  });

  $('#exportPrint').click(function() {
    window.open(buildExportUrl('print'), '_blank');
  });

  // View item details
  $(document).on('click', '.view-detail', function() {
    const itemId = $(this).data('id');
    const itemName = $(this).data('name');
    
    $('#itemDetailModal').modal('show');
    $('#itemDetailContent').html('<div class="text-center p-5"><div class="spinner-border text-primary"></div></div>');
    
    $.post('./api/grn_itemwise_api.php', {
      action: 'getItemHistory',
      item_id: itemId,
      date_from: $('#dateFrom').val(),
      date_to: $('#dateTo').val()
    }, function(res) {
      if(res.status === 'success' && res.data) {
        let html = `<h5 class="mb-3">${itemName}</h5>
          <div class="table-responsive">
            <table class="table table-sm table-bordered">
              <thead class="table-light">
                <tr>
                  <th>GRN Date</th>
                  <th>GRN Number</th>
                  <th>Vendor</th>
                  <th>Invoice No</th>
                  <th>Qty Purchased</th>
                  <th>Qty Returned</th>
                  <th>Net Qty</th>
                  <th>Unit Price</th>
                  <th>Discount</th>
                  <th>GST</th>
                  <th>Total Amount</th>
                </tr>
              </thead>
              <tbody>`;
        
        res.data.forEach(row => {
          html += `<tr>
            <td>${row.grn_date}</td>
            <td>${row.grn_number}</td>
            <td>${row.vendor_name}</td>
            <td>${row.invoice_number}</td>
            <td class="text-end">${parseFloat(row.qty_purchased).toFixed(2)}</td>
            <td class="text-end text-danger">${parseFloat(row.qty_returned).toFixed(2)}</td>
            <td class="text-end"><strong>${parseFloat(row.net_qty).toFixed(2)}</strong></td>
            <td class="text-end">₹${parseFloat(row.unit_price).toFixed(2)}</td>
            <td class="text-end text-warning">₹${parseFloat(row.discount).toFixed(2)}</td>
            <td class="text-end text-info">₹${parseFloat(row.gst).toFixed(2)}</td>
            <td class="text-end text-success"><strong>₹${parseFloat(row.total_amount).toFixed(2)}</strong></td>
          </tr>`;
        });
        
        html += '</tbody></table></div>';
        $('#itemDetailContent').html(html);
      } else {
        $('#itemDetailContent').html('<div class="alert alert-warning">No purchase history found</div>');
      }
    }, 'json');
  });

});
</script>

<style>
.card-body h3 { font-size: 1.8rem; font-weight: bold; }
.table-responsive { max-height: 600px; }
#itemwiseTable tfoot th { font-weight: 600; border-top: 2px solid #dee2e6; }
</style>

<?php
require_once("footer.php");
?>