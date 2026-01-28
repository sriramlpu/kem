<?php
require_once("header.php");
require_once("nav.php");
require_once("../auth.php");
requireRole(['Admin','Requester']);
?>
<style>

  #branchTable {
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
  <!-- Left Card: Add Branch -->
  <div class="col-md-3">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-success text-white fw-bold">
        Add Branch
      </div>
      <div class="card-body">
        <form id="branchForm">
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="branch_code" name="branch_code" placeholder="Branch Code" required>
            <label for="branch_code">Branch Code</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="branch_name" name="branch_name" placeholder="Branch Name" required>
            <label for="branch_name">Branch Name</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="address" name="address" placeholder="Address" required>
            <label for="address">Address</label>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-floating mb-3">
                <input type="text" class="form-control" id="city" name="city" placeholder="City" required>
                <label for="city">City</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating mb-3">
                <input type="text" class="form-control" id="state" name="state" placeholder="State" required>
                <label for="state">State</label>
              </div>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-floating mb-3">
                <input type="text" class="form-control" id="country" name="country" placeholder="Country">
                <label for="country">Country</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating mb-3">
                <input type="text" class="form-control" id="pincode" name="pincode" placeholder="Pincode" required>
                <label for="pincode">Pincode</label>
              </div>
            </div>
          </div>
          <button type="submit" class="btn btn-orange w-100">Add Branch</button>
        </form>
      </div>
    </div>
  </div>
  <!-- Right Card: Branch List -->
  <div class="col-md-8">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-success text-white fw-bold d-flex justify-content-between align-items-center">
        <span>Branches List</span>
        <div>
          <button class="btn btn-sm btn-light text-primary" id="exportExcel">Excel</button>
          <button class="btn btn-sm btn-light text-secondary" id="exportCsv">CSV</button>
        </div>
      </div>
      <div class="card-body">
        <!-- Filters -->
        <div class="row g-3 mb-3">
          <div class="col-md-12">
            <input type="text" id="filter_search" class="form-control" placeholder="Search branches" />
          </div>
        </div>
        <!-- Table -->
        <div class="table-responsive">
          <table id="branchTable" class="table table-hover align-middle mb-0">
            <thead>
              <tr class="table-primary">
                <th class="sticky-col">S.No</th>
                <th>Branch Code</th>
                <th>Branch Name</th>
                <th>Address</th>
                <th>City</th>
                <th>State</th>
                <th>Country</th>
                <th>Pincode</th>
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
<!-- Edit Branch Modal -->
<div class="modal fade" id="editBranchModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Edit Branch</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="editBranchForm">
          <input type="hidden" id="edit_id" name="id">
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="edit_branch_code" name="branch_code" required>
            <label for="edit_branch_code">Branch Code</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="edit_branch_name" name="branch_name" required>
            <label for="edit_branch_name">Branch Name</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="edit_address" name="address">
            <label for="edit_address">Address</label>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-floating mb-3">
                <input type="text" class="form-control" id="edit_city" name="city">
                <label for="edit_city">City</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating mb-3">
                <input type="text" class="form-control" id="edit_state" name="state">
                <label for="edit_state">State</label>
              </div>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-floating mb-3">
                <input type="text" class="form-control" id="edit_country" name="country">
                <label for="edit_country">Country</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating mb-3">
                <input type="text" class="form-control" id="edit_pincode" name="pincode">
                <label for="edit_pincode">Pincode</label>
              </div>
            </div>
          </div>
          <button type="submit" class="btn btn-orange w-100">Update Branch</button>
        </form>
      </div>
    </div>
  </div>
</div>
 
<script>
  var table;
$(function(){
   table = $('#branchTable').DataTable({
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
      url: './api/branches_api.php',
      type: 'POST',
      data: function(d) {
        d.action = 'list';
        d.filter_search = $('#filter_search').val();
      }
    },
    columns: [
      { data: 'sno' },
      { data: 'branch_code' },
      { data: 'branch_name' },
      { data: 'address' },
      { data: 'city' },
      { data: 'state' },
      { data: 'country' },
      { data: 'pincode' },
      {
        data: 'id',
        orderable: false,
        className: 'text-center',
        render: function(id) {
          return `
            <button class="btn btn-sm btn-outline-primary me-1 edit-branch" data-id="${id}" title="Edit">
              <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger delete-branch" data-id="${id}" title="Delete">
              <i class="bi bi-trash"></i>
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
});
  // reload on filter
  $('#filter_search').on('keyup', function(){ table.ajax.reload(); });
  
  // form submit
  $('#branchForm').submit(function(e){
    e.preventDefault();
    var formData = $(this).serializeArray();
    formData.push({name: 'action', value: 'create'});
    $.ajax({
      url: './api/branches_api.php',
      type: 'POST',
      data: $.param(formData),
      dataType: 'json',
      success: function(res){
        if(res.status === 'success'){
          $('#branchForm')[0].reset();
          table.ajax.reload(null, false);
        } else {
            var mssg = res.message || 'Error occurred'
          showAlert(mssg, 'danger');
        }
      },
      error: function(xhr, error, status){ 
        showAlert('API request failed', 'danger');
    }
    });
  });
  
  // edit button click
$(document).on('click', '.edit-branch', function(){
  let branchId = $(this).data('id');
  
  // fetch branch details via API
  $.post('./api/branches_api.php', { action: 'getBranch', id: branchId }, function(res){
    if(res.status === 'success'){
      let d = res.data;
      $('#edit_id').val(d.id);
      $('#edit_branch_code').val(d.branch_code);
      $('#edit_branch_name').val(d.branch_name);
      $('#edit_address').val(d.address);
      $('#edit_city').val(d.city);
      $('#edit_state').val(d.state);
      $('#edit_country').val(d.country);
      $('#edit_pincode').val(d.pincode);
      $('#editBranchModal').modal('show');
    } else {
        var mssg = res.message || 'Failed to fetch branch data';
        showAlert(mssg, 'danger');
    }
  }, 'json');
});
  
  // save edited branch
  $('#editBranchForm').submit(function(e){
    e.preventDefault();
    var formData = $(this).serializeArray();
    formData.push({name: 'action', value: 'edit'});
    
    $.ajax({
      url: './api/branches_api.php',
      type: 'POST',
      data: $.param(formData),
      dataType: 'json',
      success: function(res){
        if(res.status === 'success'){
          $('#editBranchModal').modal('hide');
          table.ajax.reload(null, false);
        } else {
            var mssg = res.message || 'Update failed';
            showAlert(mssg, 'danger');
        }
      },
      error: function(xhr, status, error){
      showAlert('API request failed', 'danger');
      }
    });
  });
  
  // delete button
  $(document).on('click', '.delete-branch', function(){
    let branchId = $(this).data('id');
    if(confirm('Are you sure you want to delete this branch?')){
      $.post('./api/branches_api.php', { action: 'delete', id: branchId }, function(res){
        if(res.status === 'success'){
          table.ajax.reload(null, false);
        } else {
             var mssg = res.message || 'Delete failed';
             showAlert(mssg, 'danger');
        }
      }, 'json');
    }
  });

</script>
<script src="./js/showAlert.js"></script>
<?php
require_once("footer.php");
?>
