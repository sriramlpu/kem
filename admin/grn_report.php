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
            <a href="grn_itemwise_report" class="btn btn-info btn-sm">
              <i class="fas fa-chart-bar me-1"></i>Item-wise Report
            </a>
            <button id="exportExcel" class="btn btn-success btn-sm">
              <i class="bi bi-file-earmark-excel"></i> Excel
            </button>
            <!--<button id="exportPdf" class="btn btn-danger btn-sm">-->
            <!--  <i class="bi bi-file-earmark-pdf"></i> PDF-->
            <!--</button>-->
            <button id="exportPrint" class="btn btn-primary btn-sm">
              <i class="bi bi-printer"></i> Print
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
                <input type="date" id="grnFrom" class="form-control">
                <input type="date" id="grnTo" class="form-control">
              </div>
            </div>
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
                <input type="date" id="invFrom" class="form-control">
                <input type="date" id="invTo" class="form-control">
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
                  <th>Total (After Discount)</th>
                  <th>Discount Applied</th>
                  <th>Transportation</th>
                  <th>Gross Total</th>
                  <th>Return Amount</th>
                  <th>Net Amount</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody></tbody>
              <tfoot class="table-secondary">
                <tr>
                  <th colspan="7" class="text-end">Page Totals:</th>
                  <th class="text-end"></th>
                  <th class="text-end"></th>
                  <th class="text-end"></th>
                  <th class="text-end"></th>
                  <th class="text-end"></th>
                  <th class="text-end"></th>
                  <th></th>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<iframe id="printFrame" style="display:none;"></iframe>

<script>
const userRole = '<?=$role?>';

$(function(){

  // Load Branch & Vendor dropdowns
  $.getJSON('./api/branches_api.php',{action:'getActiveBranches'}, res=>{ 
    if(res.status==='success') res.data.forEach(b=>$('#filterBranch').append(`<option value="${b.branch_id}">${b.branch_name}</option>`)); 
  });
  $.getJSON('./api/vendors_api.php',{action:'simpleList'}, res=>{ 
    if(res.status==='success') res.data.forEach(v=>$('#filterVendor').append(`<option value="${v.vendor_id}">${v.vendor_name}</option>`)); 
  });

  // Initialize GRN DataTable
  const grnTable = $('#grnTable').DataTable({
    serverSide: true,
    processing: true,
    pageLength: 25,
    lengthChange: true,
    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
    ajax: {
      url: './api/grn_api.php',
      type: 'POST',
      data: function(d){
        d.action = 'list';
        d.po_number = $('#filterPoNo').val().trim();
        d.grn_number = $('#filterGrnNo').val().trim();
        d.branch_id = $('#filterBranch').val();
        d.vendor_id = $('#filterVendor').val();
        d.invoice_number = $('#filterInvoiceNo').val().trim();
        d.start_date = $('#grnFrom').val();
        d.end_date = $('#grnTo').val();
        d.invoice_from = $('#invFrom').val();
        d.invoice_to = $('#invTo').val();
      },
      dataSrc: function(res){
        console.log("📊 GRN LIST RESPONSE:", res);
        return res.data || [];
      }
    },
    columns: [
      { data: 'grn_number' },
      { data: 'grn_date' },
      { data: 'vendor_name' },
      { data: 'branch_name' },
      { data: 'po_number' },
      { data: 'invoice_number' },
      { data: 'invoice_date' },
      { 
        data: 'total_amount', 
        render: d => `₹${Number(d||0).toFixed(2)}`, 
        className: 'text-end' 
      },
      {
        data: 'discount_amount', 
        render: d => {
          const amt = Number(d||0);
          return amt > 0 ? `<span class="text-warning">-₹${amt.toFixed(2)}</span>` : '₹0.00';
        }, 
        className: 'text-end' 
      },
      { 
        data: 'transportation', 
        render: d => {
          const amt = Number(d||0);
          return amt > 0 ? `<span class="text-info">+₹${amt.toFixed(2)}</span>` : '₹0.00';
        }, 
        className: 'text-end' 
      },
      { 
        data: 'gross_total', 
        render: d => `<strong>₹${Number(d||0).toFixed(2)}</strong>`, 
        className: 'text-end' 
      },
      { 
        data: 'return_amount', 
        render: d => {
          const amt = Number(d||0);
          return amt > 0 ? `<span class="text-danger fw-bold">-₹${amt.toFixed(2)}</span>` : '₹0.00';
        }, 
        className: 'text-end' 
      },
      { 
        data: 'net_amount', 
        render: d => `<strong class="text-success">₹${Number(d||0).toFixed(2)}</strong>`, 
        className: 'text-end bg-light-success' 
      },
      { 
        data: 'grn_id', 
        orderable: false,
        className: 'text-center',
        render: (id, type, row) => {
          let editBtn = '', deleteBtn = '', returnBtn = '';
          if(userRole === 'Admin'){
            editBtn = `<button class="btn btn-sm btn-warning edit-grn" data-id="${id}" title="Edit"><i class="fas fa-edit"></i></button> `;
            deleteBtn = `<button class="btn btn-sm btn-danger delete-grn" data-id="${id}" title="Delete"><i class="fas fa-trash-alt"></i></button>`;
          }
          
          returnBtn = `<button class="btn btn-sm btn-info create-return" data-id="${id}" title="Create Return"><i class="fas fa-undo"></i></button> `;
          
          return `<div class="d-flex gap-1 justify-content-center flex-wrap">
            <button class="btn btn-sm btn-primary view-grn" data-id="${id}" title="View"><i class="fas fa-eye"></i></button>
            ${editBtn}
            <button class="btn btn-sm btn-success print-grn" data-id="${id}" title="Print"><i class="fas fa-print"></i></button>
            ${returnBtn}
            ${deleteBtn}
          </div>`;
        }
      }
    ],
    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
    footerCallback: function(row, data, start, end, display) {
      const api = this.api();
      
      // Calculate totals for current page
      let totalAmt = 0, discountAmt = 0, transportAmt = 0, 
          grossAmt = 0, returnAmt = 0, netAmt = 0;
      
      // Loop through all rows in current page
      api.rows({page: 'current'}).every(function(){
        const d = this.data();
        totalAmt += Number(d.total_amount || 0);
        discountAmt += Number(d.discount_amount || 0);
        transportAmt += Number(d.transportation || 0);
        grossAmt += Number(d.gross_total || 0);
        returnAmt += Number(d.return_amount || 0);
        netAmt += Number(d.net_amount || 0);
      });
      
      // Update footer
      $(api.column(7).footer()).html(`<strong>₹${totalAmt.toFixed(2)}</strong>`);
      $(api.column(8).footer()).html(`<strong class="text-warning">₹${discountAmt.toFixed(2)}</strong>`);
      $(api.column(9).footer()).html(`<strong class="text-info">+₹${transportAmt.toFixed(2)}</strong>`);
      $(api.column(10).footer()).html(`<strong>₹${grossAmt.toFixed(2)}</strong>`);
      $(api.column(11).footer()).html(`<strong class="text-danger">-₹${returnAmt.toFixed(2)}</strong>`);
      $(api.column(12).footer()).html(`<strong class="text-success">₹${netAmt.toFixed(2)}</strong>`);
    }
  });

  // ========================================
  // OPTIMIZED EXPORT HANDLERS
  // ========================================

  // Build export URL with current filters
  function buildExportUrl(type) {
    const params = new URLSearchParams({
      type: type,
      po_number: $('#filterPoNo').val().trim(),
      grn_number: $('#filterGrnNo').val().trim(),
      branch_id: $('#filterBranch').val(),
      vendor_id: $('#filterVendor').val(),
      invoice_number: $('#filterInvoiceNo').val().trim(),
      start_date: $('#grnFrom').val(),
      end_date: $('#grnTo').val(),
      invoice_from: $('#invFrom').val(),
      invoice_to: $('#invTo').val()
    });
    
    return './api/grn_export_api.php?' + params.toString();
  }

  // Excel Export - Direct server-side download
  $('#exportExcel').click(function() {
    const btn = $(this);
    const originalText = btn.html();
    
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Exporting...');
    
    // Create hidden iframe for download
    const downloadUrl = buildExportUrl('excel');
    const iframe = $('<iframe>', {
      src: downloadUrl,
      style: 'display:none;'
    }).appendTo('body');
    
    // Re-enable button after delay
    setTimeout(() => {
      btn.prop('disabled', false).html(originalText);
      iframe.remove();
    }, 2000);
  });

  // CSV Export (alternative format)
  $('#exportPdf').click(function() {
    if(!confirm('PDF export will generate a large file. Continue?')) return;
    
    const btn = $(this);
    const originalText = btn.html();
    
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Generating PDF...');
    
    // Create hidden iframe for download
    const downloadUrl = buildExportUrl('pdf');
    const iframe = $('<iframe>', {
      src: downloadUrl,
      style: 'display:none;'
    }).appendTo('body');
    
    // Re-enable button after delay
    setTimeout(() => {
      btn.prop('disabled', false).html(originalText);
      iframe.remove();
    }, 3000);
  });

  // Print Export - Print directly from current page
  $('#exportPrint').click(async function() {
    // Ask user how many records to print
    const maxRecords = prompt('How many records to print?\n(Enter a number or leave empty for all records)', '50');
    
    if(maxRecords === null) return; // User cancelled
    
    const btn = $(this);
    const originalText = btn.html();
    
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Preparing...');
    
    try {
      // Fetch print data
      const params = new URLSearchParams({
        type: 'print_json',
        po_number: $('#filterPoNo').val().trim(),
        grn_number: $('#filterGrnNo').val().trim(),
        branch_id: $('#filterBranch').val(),
        vendor_id: $('#filterVendor').val(),
        invoice_number: $('#filterInvoiceNo').val().trim(),
        start_date: $('#grnFrom').val(),
        end_date: $('#grnTo').val(),
        invoice_from: $('#invFrom').val(),
        invoice_to: $('#invTo').val()
      });
      
      if(maxRecords && maxRecords.trim() !== '' && !isNaN(maxRecords)) {
        params.append('limit', parseInt(maxRecords));
      }
      
      const response = await fetch('./api/grn_export_api.php?' + params.toString());
      const data = await response.json();
      
      if (!data.success || !data.records || data.records.length === 0) {
        alert('No records found to print.');
        btn.prop('disabled', false).html(originalText);
        return;
      }
      
      // Create print content
      const printContent = generatePrintHTML(data.records, data.totals);
      
      // Create hidden iframe for printing
      let printFrame = document.getElementById('printFrame');
      if (!printFrame) {
        printFrame = document.createElement('iframe');
        printFrame.id = 'printFrame';
        printFrame.style.display = 'none';
        document.body.appendChild(printFrame);
      }
      
      const doc = printFrame.contentDocument || printFrame.contentWindow.document;
      doc.open();
      doc.write(printContent);
      doc.close();
      
      // Wait for content to load, then print
      printFrame.contentWindow.focus();
      setTimeout(() => {
        printFrame.contentWindow.print();
        btn.prop('disabled', false).html(originalText);
      }, 500);
      
    } catch (error) {
      console.error('Print error:', error);
      alert('Error preparing print: ' + error.message);
      btn.prop('disabled', false).html(originalText);
    }
  });
  
  // Generate print HTML
  function generatePrintHTML(records, totals) {
    let html = `<!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      <title>GRN Report - ${new Date().toLocaleDateString()}</title>
      <style>
        @media print {
          @page { 
            margin: 0.5cm; 
            size: landscape;
          }
        }
        body { 
          font-family: Arial, sans-serif; 
          margin: 20px;
          font-size: 10px;
        }
        h2 { 
          color: #28a745; 
          margin: 10px 0 5px 0; 
          font-size: 18px;
        }
        .info { 
          margin-bottom: 15px; 
          color: #666; 
          font-size: 11px;
        }
        table { 
          border-collapse: collapse; 
          width: 100%; 
        }
        th { 
          background-color: #28a745; 
          color: white; 
          font-weight: bold; 
          padding: 8px 4px; 
          border: 1px solid #ddd; 
          text-align: left;
          font-size: 10px;
        }
        td { 
          padding: 5px 4px; 
          border: 1px solid #ddd;
          font-size: 9px;
        }
        .number { text-align: right; }
        .total-row { 
          background-color: #e9ecef; 
          font-weight: bold; 
          border-top: 2px solid #000;
        }
      </style>
    </head>
    <body>
      <h2>Goods Received Notes (GRN) Report</h2>
      <div class="info">
        <strong>Generated:</strong> ${new Date().toLocaleString()} | 
        <strong>Records:</strong> ${records.length}
      </div>
      
      <table>
        <thead>
          <tr>
            <th style="width: 40px;">S.N</th>
            <th style="width: 150px;">Vendor</th>
            <th style="width: 120px;">Item Description</th>
            <th style="width: 80px;">Invoice Date</th>
            <th style="width: 90px;">Invoice No</th>
            <th style="width: 60px;">Qty</th>
            <th style="width: 80px;">Basic Value</th>
            <th style="width: 60px;">GST %</th>
            <th style="width: 80px;">GST Amount</th>
            <th style="width: 90px;">Invoice Value</th>
            <th style="width: 70px;">Less TDS</th>
            <th style="width: 90px;">Total Value</th>
          </tr>
        </thead>
        <tbody>`;
    
    let sn = 1;
    records.forEach(row => {
      const basicValue = parseFloat(row.basic_value_after_discount || 0);
      html += `<tr>
        <td class="number">${sn++}</td>
        <td>${row.vendor_name || ''}</td>
        <td>${row.item_name || ''}</td>
        <td>${row.invoice_date || ''}</td>
        <td>${row.invoice_number || ''}</td>
        <td class="number">${row.balance_qty || 0}</td>
        <td class="number">₹${basicValue.toFixed(2)}</td>
        <td class="number">${parseFloat(row.gst_percentage || 0).toFixed(2)}%</td>
        <td class="number">₹${parseFloat(row.gst_amount || 0).toFixed(2)}</td>
        <td class="number">₹${parseFloat(row.total_invoice_value || 0).toFixed(2)}</td>
        <td class="number">₹${parseFloat(row.less_tds || 0).toFixed(2)}</td>
        <td class="number">₹${parseFloat(row.total_invoice_value_after_tds || 0).toFixed(2)}</td>
      </tr>`;
    });
    
    html += `</tbody>
        <tfoot>
          <tr class="total-row">
            <td colspan="6" style="text-align: right;"><strong>GRAND TOTALS:</strong></td>
            <td class="number"><strong>₹${parseFloat(totals.basic_value || 0).toFixed(2)}</strong></td>
            <td></td>
            <td class="number"><strong>₹${parseFloat(totals.gst_amount || 0).toFixed(2)}</strong></td>
            <td class="number"><strong>₹${parseFloat(totals.total_invoice_value || 0).toFixed(2)}</strong></td>
            <td class="number"><strong>₹${parseFloat(totals.less_tds || 0).toFixed(2)}</strong></td>
            <td class="number"><strong>₹${parseFloat(totals.total_invoice_value_after_tds || 0).toFixed(2)}</strong></td>
          </tr>
        </tfoot>
      </table>
    </body>
    </html>`;
    
    return html;
  }

  // ========================================
  // FILTERS
  // ========================================

  $('#resetFilters').click(() => {
    $('#filterPoNo, #filterGrnNo, #filterInvoiceNo').val('');
    $('#filterBranch, #filterVendor').val('');
    $('#grnFrom, #grnTo, #invFrom, #invTo').val('');
    grnTable.ajax.reload();
  });
  
  let filterTimeout;
  $('#filterPoNo, #filterGrnNo, #filterInvoiceNo, #filterBranch, #filterVendor, #grnFrom, #grnTo, #invFrom, #invTo').on('change keyup', function() {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(() => grnTable.ajax.reload(), 500);
  });

  // ========================================
  // GRN ACTIONS
  // ========================================

  $(document).on('click', '.view-grn', function(){
    const grnId = $(this).data('id');
    $('<form>', {method: 'POST', action: 'view_grn'})
      .append($('<input>', {type: 'hidden', name: 'grn_id', value: grnId}))
      .appendTo('body').submit();
  });
  
  $(document).on('click', '.print-grn', function(){
    const grnId = $(this).data('id');
    $.get(`grn_print?grn_id=${grnId}`, html => {
      const iframe = document.getElementById('printFrame');
      const doc = iframe.contentDocument || iframe.contentWindow.document;
      doc.open(); 
      doc.write(html); 
      doc.close();
      iframe.contentWindow.focus(); 
      iframe.contentWindow.print();
    });
  });
  
  $(document).on('click', '.delete-grn', function(){
    const id = $(this).data('id');
    if(confirm('Are you sure you want to delete this GRN?')){
      $.post('./api/grn_api.php', {action: 'delete', grn_id: id}, res => {
        if(res?.status === 'success'){
          alert('GRN deleted successfully!');
          grnTable.ajax.reload();
        } else {
          alert('Failed: ' + (res?.message || 'Unknown error'));
        }
      }, 'json');
    }
  });
  
  $(document).on('click', '.edit-grn', function(e){
    e.preventDefault();
    const grnId = $(this).data('id');
    $('<form>', {action: 'grn_form', method: 'POST'})
      .append($('<input>', {type: 'hidden', name: 'grn_id', value: grnId}))
      .appendTo('body').submit();
  });
  
  $(document).on('click', '.create-return', function(e){
    e.preventDefault();
    const grnId = $(this).data('id');
    window.location.href = 'grn_return?grn_id=' + grnId;
  });

});
</script>

<style>
.cursor-pointer { cursor: pointer; }
.btn-group-vertical .btn { margin-bottom: 2px; }
.grn-details { font-size: 0.9rem; }
.details-content { animation: slideDown 0.3s ease-out; }
@keyframes slideDown {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}
.text-success {
  color: #198754 !important;
}
#grnTable tfoot th {
  font-weight: 600;
  border-top: 2px solid #dee2e6;
}
/* Action buttons styling */
#grnTable td .btn {
  margin: 1px;
  white-space: nowrap;
}
#grnTable td .d-flex {
  min-width: 200px;
}
</style>

<?php
require_once("footer.php");
?>