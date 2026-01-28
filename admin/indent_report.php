<?php
require_once("header.php");
require_once("nav.php");
?>
<section class="container-fluid section">
  <div class="row">
    <div class="col-12">
      <div class="card shadow-sm border-0">
        <!-- Header -->
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Indent Report</h5>
          <div class="d-flex gap-2">
            <button id="exportExcel" class="btn btn-success btn-sm">
              <i class="bi bi-file-earmark-excel"></i> Excel
            </button>
            <button id="exportPdf" class="btn btn-danger btn-sm">
              <i class="bi bi-file-earmark-pdf"></i> PDF
            </button>
          </div>
        </div>

        <div class="card-body">
          <!-- Filters -->
          <div class="row g-2 mb-2">
            <div class="col-md-2">
              <input type="text" id="filterIndentNo" class="form-control" placeholder="Indent Number">
            </div>
            <div class="col-md-2">
              <select id="filterBranch" class="form-select">
                <option value="">All Branches</option>
              </select>
            </div>
            <div class="col-md-2">
              <select id="filterStatus" class="form-select">
                <option value="">All Status</option>
                <option value="OPEN">OPEN</option>
                <option value="CLOSED">CLOSED</option>
                <option value="CANCELLED">CANCELLED</option>
              </select>
            </div>
            <div class="col-md-2">
              <select id="filterRequestedBy" class="form-select">
                <option value="">Requested By</option>
              </select>
            </div>
            <div class="col-md-2">
              <input type="date" id="filterStartDate" class="form-control" placeholder="From">
            </div>
            <div class="col-md-2">
              <input type="date" id="filterEndDate" class="form-control" placeholder="To">
            </div>
          </div>
          <div class="row mb-3">
            <div class="col text-end">
              <button id="resetFilters" class="btn btn-outline-secondary">Reset Filters</button>
            </div>
          </div>

          <!-- Table -->
          <table id="indentReportTable" class="table table-bordered table-striped w-100">
            <thead class="table-light">
              <tr>
                <th>S.No</th>
                <th>Indent Number</th>
                <th>Branch</th>
                <th>Requested By</th>
                <th>Indent Against</th>
                <th>Indent Date</th>
                <th>Status</th>
                <th>Details</th>
              </tr>
            </thead>
          </table>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once("footer.php"); ?>

<script>
$(function () {
  // Load filter dropdowns
  $.getJSON('./api/branches_api.php?action=getActiveBranches', function (res) {
    if (res?.status === 'success') {
      res.data.forEach(b => $('#filterBranch').append(`<option value="${b.branch_id}">${b.branch_name}</option>`));
    }
  });
  $.getJSON('./api/users_api.php?action=list', function (res) {
    if (res?.data) {
      res.data.forEach(u => $('#filterRequestedBy').append(`<option value="${u.id}">${u.username}</option>`));
    }
  });

  // DataTable
  const table = $('#indentReportTable').DataTable({
    processing: true,
    serverSide: true,
    autoWidth: false,
    dom: 'Brtip',              // keep buttons hidden; we trigger via header buttons
    buttons: [
      { extend: 'excelHtml5', className: 'buttons-excel d-none', title: 'Indent_Report' },
      { extend: 'pdfHtml5',   className: 'buttons-pdf d-none',   title: 'Indent_Report', orientation: 'landscape', pageSize: 'A4' }
    ],
    ajax: {
      url: './api/indent_api.php',
      type: 'POST',
      data: function (d) {
        d.action        = 'list';
        d.filter_search = $('#filterIndentNo').val();      // for backward compatibility
        // extra filters (add support in backend if not present yet)
        d.branch_id     = $('#filterBranch').val();
        d.status        = $('#filterStatus').val();
        d.requested_by  = $('#filterRequestedBy').val();
        d.start_date    = $('#filterStartDate').val();
        d.end_date      = $('#filterEndDate').val();
      }
    },
    columns: [
      { data: 'sno' },
      { data: 'indent_number' },
      { data: 'branch_name' },
      { data: 'requested_name' },
      { data: 'indent_against' },
      { data: 'indent_date' },
      {
        data: 'status',
        render: function (s) {
          const map = {
            'OPEN':      'success',
            'CLOSED':    'secondary',
            'CANCELLED': 'danger'
          };
          const cls = map[s] || 'light text-dark';
          return `<span class="badge bg-${cls}">${s || ''}</span>`;
        }
      },
      {
        data: 'indent_id',
        orderable: false,
        className: 'text-center',
        render: function (id) {
          return `<div class="indent-details-container" data-id="${id}">
                    <div class="text-center">
                      <div class="spinner-border spinner-border-sm" role="status">
                        <span class="visually-hidden">Loading...</span>
                      </div>
                    </div>
                  </div>`;
        }
      }
    ],
    order: [[0, 'desc']],
    drawCallback: function(settings) {
      // Load details for each row after the table is drawn
      $('.indent-details-container').each(function() {
        const container = $(this);
        const indentId = container.data('id');
        
        // Skip if already loaded
        if(container.hasClass('loaded')) return;
        
        // Fetch indent details
        $.post('./api/indent_api.php', { action: 'getIndent', indent_id: indentId }, function (res) {
          if (res?.status === 'success' && res.data) {
            const d = res.data;
            
            // Build details HTML - only showing additional information not in main table
            let detailsHtml = `
              <div class="indent-details">
                <div class="mb-2">
                  <strong>Remarks:</strong>
                  <p class="bg-light p-2 rounded small mb-0">${d.remarks || 'No remarks'}</p>
                </div>
                <div>
                  <strong>Items:</strong>
                  <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                      <thead class="table-light">
                        <tr>
                          <th>#</th>
                          <th>Item</th>
                          <th>Description</th>
                          <th>Qty Requested</th>
                          <th>Line Status</th>
                        </tr>
                      </thead>
                      <tbody>`;
            
            // Items
            const items = Array.isArray(d.items) ? d.items : [];
            if (!items.length) {
              detailsHtml += `<tr><td colspan="5" class="text-center">No items found</td></tr>`;
            } else {
              detailsHtml += items.map((it, idx) => {
                const s = it.line_status || '';
                const cls = s==='OPEN'?'success':(s==='CLOSED'?'secondary':(s==='CANCELLED'?'danger':'light text-dark'));
                return `
                  <tr>
                    <td>${idx+1}</td>
                    <td>${it.item_name || ''}</td>
                    <td>${it.description || ''}</td>
                    <td>${it.qty_requested || 0}</td>
                    <td><span class="badge bg-${cls}">${s}</span></td>
                  </tr>
                `;
              }).join('');
            }
            
            detailsHtml += `
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>`;
            
            container.html(detailsHtml).addClass('loaded');
          }
        }, 'json');
      });
    }
  });

  // Export triggers
  $('#exportExcel').on('click', () => table.button('.buttons-excel').trigger());
  $('#exportPdf').on('click',   () => table.button('.buttons-pdf').trigger());

  // Filters -> reload
  // debounce typing for indent number
  let keyTimer;
  $('#filterIndentNo').on('keyup', function () {
    clearTimeout(keyTimer);
    keyTimer = setTimeout(() => table.ajax.reload(), 400);
  });
  $('#filterBranch, #filterStatus, #filterRequestedBy, #filterStartDate, #filterEndDate')
    .on('change', () => table.ajax.reload());

  // Reset
  $('#resetFilters').on('click', function (e) {
    e.preventDefault();
    $('#filterIndentNo').val('');
    $('#filterBranch').val('');
    $('#filterStatus').val('');
    $('#filterRequestedBy').val('');
    $('#filterStartDate').val('');
    $('#filterEndDate').val('');
    table.ajax.reload();
  });
});
</script>
<script src="./js/showAlert.js"></script>

<style>
.indent-details-container {
  max-height: 400px;
  overflow-y: auto;
}

.indent-details {
  font-size: 0.85rem;
}

.indent-details .table {
  font-size: 0.8rem;
}

.indent-details .table th {
  background-color: #f8f9fa;
  position: sticky;
  top: 0;
  z-index: 1;
}
</style>