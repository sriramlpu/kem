<?php
require_once("header.php");
require_once("nav.php");
require_once("../auth.php");
requireRole(['Requester','Admin']);
?>
<section class="container-fluid section">
  <div class="row">
    <div class="col-12">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
          <span>Indents List</span>
          <div>
            <a href="create_indent.php" class="btn btn-light btn-sm">
              <i class="bi bi-plus-circle"></i> Create New Indent
            </a>
          </div>
        </div>
        <div class="card-body">
          <div class="row mb-3">
            <div class="col-md-6">
              <div class="d-flex align-items-center">
                <span class="me-3">Status:</span>
                <span class="badge bg-light text-warning me-2">Opened: <span id="count_opened">0</span></span>
                <span class="badge bg-light text-primary me-2">Closed: <span id="count_closed">0</span></span>
                <span class="badge bg-light text-danger">CANCELLED: <span id="count_cancelled">0</span></span>
              </div>
            </div>
            <div class="col-md-6">
              <input type="text" id="filter_search" class="form-control" placeholder="Search by indent number" />
            </div>
          </div>
          <table id="indentTable" class="table table-hover align-middle">
            <thead>
              <tr>
                <th>S.No</th>
                <th>Indent Number</th>
                <th>Branch</th>
                <th>Requested By</th>
                <th>Indent Against</th>
                <th>Indent Date</th>
                <th>Status</th>
                <th class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</section>
<!-- Edit Indent Modal -->
<div class="modal fade" id="editIndentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content shadow-lg">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Edit Indent</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="editIndentForm">
          <input type="hidden" id="edit_indent_id" name="indent_id">
          
          <!-- Section 1: Basic Information -->
          <div class="mb-4">
            <h6 class="text-primary mb-3">Basic Information</h6>
            <div class="row">
              <div class="col-md-6">
                <div class="form-floating mb-3">
                  <select class="form-select" id="edit_raised_by" name="raised_by" required>
                    <option value="">Select User</option>
                  </select>
                  <label for="edit_raised_by">Raised By</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-floating mb-3">
                  <input type="date" class="form-control" id="edit_indent_date" name="indent_date" required>
                  <label for="edit_indent_date">Indent Date</label>
                </div>
              </div>
            </div>
            
            <div class="row">
              <div class="col-md-6">
                <div class="form-floating mb-3">
                  <select class="form-select" id="edit_branch" name="branch" required>
                    <option value="">Select Branch</option>
                  </select>
                  <label for="edit_branch">Branch</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-floating mb-3">
                  <select class="form-select" id="edit_indent_against" name="indent_against">
                    <option value="">Select Indent Against</option>
                    <option value="Direct">Direct</option>
                  </select>
                  <label for="edit_indent_against">Indent Against</label>
                </div>
              </div>
            </div>
            
            <div class="row">
              <div class="col-md-12">
                <div class="form-floating mb-3">
                  <textarea class="form-control" id="edit_remarks" name="remarks" rows="2" placeholder="Remarks"></textarea>
                  <label for="edit_remarks">Remarks</label>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Section 2: Items -->
          <div class="mb-4">
            <h6 class="text-primary mb-3">Items</h6>
            <div id="editItemsContainer">
               Items will be loaded here
            </div>
            <button type="button" id="addEditItemBtn" class="btn btn-outline-success btn-sm mt-2">+ Add Item</button>
          </div>
          
          <!-- Section 3: Actions -->
          <div class="mb-3">
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-success flex-fill">Update Indent</button>
              <button type="button" class="btn btn-outline-secondary flex-fill" data-bs-dismiss="modal">Cancel</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<!-- Indent Items Modal -->
<div class="modal fade" id="indentItemsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content shadow-lg">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Indent Items</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="current_indent_id" value="">
        <table id="indentItemsTable" class="table table-bordered table-striped">
          <thead>
            <tr>
              <th>S.No</th>
              <th>Item Name</th>
              <th>Description</th>
              <th>Quantity Requested</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <!-- Filled dynamically -->
          </tbody>
        </table>
        <!-- <button class="btn btn-success mt-3" id="addIndentItem">Add Item</button> -->
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.datatables.net/2.0.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.0.6/js/dataTables.bootstrap5.min.js"></script>
<script>
  $(document).ready(function() {
    // Load users for dropdowns
    function loadUsers(selectId) {
      $.getJSON('./api/users_api.php?action=getUsers', function(res) {
        let sel = $(selectId);
        sel.empty();
        sel.append('<option value="">Select User</option>');
        res.data.forEach(function(user) {
          sel.append(`<option value="${user.user_id}">${user.username}</option>`);
        });
      });
    }
    
    // Load branches for dropdowns
    function loadBranches(selectId) {
      $.getJSON('./api/branches_api.php?action=getActiveBranches', function(res) {
        let sel = $(selectId);
        sel.empty();
        sel.append('<option value="">Select Branch</option>');
        res.data.forEach(function(branch) {
          sel.append(`<option value="${branch.branch_id}">${branch.branch_name}</option>`);
        });
      });
    }
    
    // Load items for dropdowns
    function loadItems() {
      return $.getJSON('./api/items_api.php?action=getActiveItems');
    }
    
    // Initialize dropdowns
    loadUsers('#edit_raised_by');
    loadBranches('#edit_branch');
    
    // Initialize items data
    let itemsPromise = loadItems();
    
    itemsPromise.done(function(itemsRes) {
      if (itemsRes.status === 'success' && itemsRes.data) {
        window.items = itemsRes.data;
      } else {
        showAlert('Failed to load items', 'danger');
      }
    }).fail(function(xhr, status, error) {
      console.error('Failed to load items:', error);
      showAlert('Failed to load items', 'danger');
    });
    
    var table;
    $(function() {
      table = $('#indentTable').DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        dom: 'Brtip', // Include buttons but hide them visually
        buttons: [{
            extend: 'excelHtml5',
            className: 'buttons-excel d-none'
          },
          {
            extend: 'csvHtml5',
            className: 'buttons-csv d-none'
          }
        ],
        ajax: {
          url: './api/indent_api.php',
          type: 'POST',
          data: function(d) {
            d.action = 'list';
            d.filter_search = $('#filter_search').val();
          },
          dataSrc: function(json) {
            // Update status counts
            if (json.status_counts) {
              $('#count_opened').text(json.status_counts.Opened || 0);
              $('#count_closed').text(json.status_counts.Closed || 0);
              $('#count_cancelled').text(json.status_counts.Cancelled || 0);
            }
            return json.data;
          }
        },
        columns: [{
            data: 'sno'
          },
          {
            data: 'indent_number'
          },
          {
            data: 'branch_name'
          },
          {
            data: 'requested_name'
          },
          {
            data: 'indent_against'
          },
          {
            data: 'indent_date'
          },
          {
            data: 'status',
  render: function(status) {
    console.log(status)
    const map = {
      'Opened': 'warning',
      'Closed': 'success',
      'Cancelled': 'danger'
    };
    const cls = map[status] || 'light text-dark';
    return `<span class="badge bg-${cls}">${status || ''}</span>`;
  }
          },
          {
            data: 'indent_id',
            orderable: false,
            className: 'text-center',
            render: function(id, type, row) {
              return `
                <button class="btn btn-sm btn-outline-info view-items" data-id="${id}" title="View Items">
                  <i class="bi bi-list"></i>
                </button>
                <button class="btn btn-sm btn-outline-primary edit-indent" data-id="${id}" title="Edit">
                  <i class="bi bi-pencil"></i>
                </button>
                <select class="form-select form-select-sm d-inline-block w-auto indent-status" 
            data-id="${id}">
      <option value="Opened" ${row.status==='Opened'?'selected':''}>Opened</option>
      <option value="Closed" ${row.status==='Closed'?'selected':''}>Closed</option>
      <option value="Cancelled" ${row.status==='Cancelled'?'selected':''}>Cancelled</option>
    </select>

              `;
            }
          }
        ],
        order: [
          [0, 'desc']
        ]
      });
    });
    
    // Filter search
    $('#filter_search').on('keyup', function() {
      table.ajax.reload();
    });
    
    // Counter for item rows in edit modal
    let editItemCount = 0;
    
    // Function to add a new item row in edit modal
    function addEditItemRow(itemId = '', quantity = '', description = '') {
      const container = $('#editItemsContainer');
      const index = editItemCount++;
      
      const rowHtml = `
        <div class="row mb-3 edit-item-row" data-index="${index}">
          <div class="col-md-5">
            <div class="form-floating">
              <select class="form-select" id="edit_item_name_${index}" name="item_name[${index}]" required>
                <option value="">Select Item</option>
              </select>
              <label for="edit_item_name_${index}">Item Name</label>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-floating">
              <input type="number" class="form-control" id="edit_quantity_${index}" name="quantity[${index}]" min="1" step="0.01" value="${quantity}" required>
              <label for="edit_quantity_${index}">Quantity</label>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-floating">
              <input type="text" class="form-control" id="edit_description_${index}" name="description[${index}]" value="${description}" placeholder="Description">
              <label for="edit_description_${index}">Description</label>
            </div>
          </div>
          <div class="col-md-1 d-flex align-items-center">
            <button type="button" class="btn btn-outline-danger remove-edit-item-btn" data-index="${index}">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </div>
      `;
      
      container.append(rowHtml);
      
      // Populate items dropdown
      const itemSelect = $(`#edit_item_name_${index}`);
      if (window.items) {
        window.items.forEach(function(item) {
          itemSelect.append(`<option value="${item.item_id}">${item.item_name}</option>`);
        });
        // Set selected item if provided
        if (itemId) {
          itemSelect.val(itemId);
        }
      }
    }
    
    // Add item button click handler in edit modal
    $('#addEditItemBtn').on('click', function() {
      addEditItemRow();
    });
    
    // Remove item button handler in edit modal
    $('#editItemsContainer').on('click', '.remove-edit-item-btn', function() {
      const index = $(this).data('index');
      $(`.edit-item-row[data-index="${index}"]`).remove();
    });
    
    // Edit Indent button
    $(document).on('click', '.edit-indent', function() {
      let id = $(this).data('id');
      $.post('./api/indent_api.php', {
        action: 'getIndent',
        indent_id: id
      }, function(res) {
        if (res.status === 'success') {
          let d = res.data;
          $('#edit_indent_id').val(d.indent_id);
          $('#edit_raised_by').val(d.raised_by);
          $('#edit_indent_date').val(d.indent_date);
          $('#edit_branch').val(d.branch_id);
          $('#edit_indent_against').val(d.indent_against);
          $('#edit_remarks').val(d.remarks);
          
          // Clear existing items
          $('#editItemsContainer').empty();
          editItemCount = 0;
          
          // Load existing items
          if (d.items && d.items.length > 0) {
            d.items.forEach(function(item) {
              addEditItemRow(item.item_id, item.qty_requested, item.description);
            });
          } else {
            // Add at least one empty row
            addEditItemRow();
          }
          
          $('#editIndentModal').modal('show');
        } else {
          showAlert(res.message || 'Failed to fetch data', 'danger');
        }
      }, 'json');
    });
    
    // Update Indent
    $('#editIndentForm').submit(function(e) {
      e.preventDefault();
      
      // Validate required fields
      let isValid = true;
      $(this).find('[required]').each(function() {
        if (!$(this).val()) {
          isValid = false;
          $(this).addClass('is-invalid');
        } else {
          $(this).removeClass('is-invalid');
        }
      });
      
      if (!isValid) {
        showAlert('Please fill all required fields', 'danger');
        return false;
      }
      
      // Collect items data
      var items = [];
      $('.edit-item-row').each(function() {
        const index = $(this).data('index');
        const itemId = $(`#edit_item_name_${index}`).val();
        const quantity = $(`#edit_quantity_${index}`).val();
        const description = $(`#edit_description_${index}`).val();
        
        if (itemId && quantity && quantity > 0) {
          items.push({
            item_id: parseInt(itemId),
            quantity: parseFloat(quantity),
            description: description || ''
          });
        }
      });
      
      // Create FormData object
      var formData = new FormData(this);
      formData.append('action', 'edit');
      formData.append('items', JSON.stringify(items));
      
      // Show loading state
      const submitBtn = $(this).find('button[type="submit"]');
      const originalText = submitBtn.text();
      submitBtn.prop('disabled', true).text('Updating...');
      
      // Submit via AJAX
      $.ajax({
        url: './api/indent_api.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        processData: false,
        contentType: false,
        success: function(res) {
          if (res.status === 'success') {
            showAlert(res.message || 'Indent updated successfully');
            $('#editIndentModal').modal('hide');
            table.ajax.reload();
          } else {
            showAlert(res.message || 'Update failed', 'danger');
          }
          submitBtn.prop('disabled', false).text(originalText);
        },
        error: function(xhr, status, error) {
          try {
            var response = JSON.parse(xhr.responseText);
            showAlert(response.message || 'An error occurred', 'danger');
          } catch (e) {
            showAlert('An error occurred while updating the indent', 'danger');
          }
          submitBtn.prop('disabled', false).text(originalText);
        }
      });
    });
    
    // Cancel Indent
    $(document).on('click', '.cancel-indent', function() {
      if (confirm('Are you sure you want to cancel this indent?')) {
        let id = $(this).data('id');
        $.post('./api/indent_api.php', {
          action: 'cancel',
          indent_id: id
        }, function(res) {
          if (res.status === 'success') {
            showAlert('Indent cancelled successfully');
            table.ajax.reload();
          } else {
            showAlert(res.message || 'Cancel failed', 'danger');
          }
        }, 'json');
      }
    });
    
    // View Items
    $(document).on('click', '.view-items', function() {
      let indentId = $(this).data('id');
      $('#current_indent_id').val(indentId);
      $('#indentItemsModal').modal('show');
      loadIndentItems(indentId);
    });
    
    function loadIndentItems(indentId) {
      $.post('./api/indent_items_api.php', {
        action: 'list',
        indent_id: indentId
      }, function(res) {
        if (res && res.status === 'success' && Array.isArray(res.data)) {
          let tbody = '';
          if (res.data.length === 0) {
            tbody = '<tr><td colspan="5" class="text-center">No items found</td></tr>';
          } else {
            res.data.forEach(function(item, index) {
              // Create badge for status
              let statusBadge = '';
              switch(item.line_status) {
                case 'OPEN':
                  statusBadge = '<span class="badge bg-success">OPEN</span>';
                  break;
                case 'CLOSED':
                  statusBadge = '<span class="badge bg-secondary">CLOSED</span>';
                  break;
                case 'CANCELLED':
                  statusBadge = '<span class="badge bg-danger">CANCELLED</span>';
                  break;
                default:
                  statusBadge = '<span class="badge bg-light text-dark">' + item.line_status + '</span>';
              }
              
              tbody += `
                <tr>
                  <td>${index+1}</td>
                  <td>${item.item_name || 'N/A'}</td>
                  <td>${item.description || ''}</td>
                  <td>${item.qty_requested || 0}</td>
                  <td>${statusBadge}</td>
                </tr>
              `;
            });
          }
          $('#indentItemsTable tbody').html(tbody);
        } else {
          showAlert(res.message || 'Failed to load items', 'danger');
        }
      }, 'json');
    }
    
    // Add Item from Items Modal
    // $('#addIndentItem').click(function() {
    //   const indentId = $('#current_indent_id').val();
    //   if (indentId) {
    //     // Redirect to edit page with this indent ID
    //     window.location.href = `create_indent.php?id=${indentId}`;
    //   }
    // });
  });

// Update indent status from Action column

$(document).on('change', '.indent-status', function () {
  const id = $(this).data('id');
  const newStatus = $(this).val();
  const table = $('#indentTable').DataTable();

  $.post('./api/indent_api.php', {
    action: 'updateStatus',
    indent_id: id,
    status: newStatus
  }, function (res) {
    if (res?.status === 'success') {
      showAlert('Status updated successfully', 'success');

      // Make sure API returns updated status from DB
      const updatedStatus = res.updated_status || newStatus;

      // Find row in DataTable
      const row = table.row($(`.indent-status[data-id="${id}"]`).closest('tr'));
      let rowData = row.data();

      // Update with DB-confirmed status
      rowData.status = updatedStatus;

      // Update DataTable row and redraw (badge refreshes)
      row.data(rowData).invalidate().draw(false);

      // Sync dropdown to DB status
      $(`.indent-status[data-id="${id}"]`).val(updatedStatus);
    } else {
      showAlert(res?.message || 'Failed to update status', 'danger');
      // Reset dropdown back to old value if failed
      table.ajax.reload(null, false);
    }
  }, 'json');
});

</script>


<script src="./js/showAlert.js"></script>
<?php
require_once("footer.php");
?>