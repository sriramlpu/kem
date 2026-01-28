<?php
require_once("header.php");
require_once("nav.php");
require_once("../auth.php");
requireRole(['Admin','Requester']);
?>
<style>

  #subCategoryTable {
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
  <!-- Left Card: Add Sub-Category -->
  <div class="col-md-3">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-success text-white fw-bold">
        Add Sub-Category
      </div>
      <div class="card-body">
        <form id="subCategoryForm">
          <div class="form-floating mb-3">
            <select class="form-select" id="category_id" name="category_id" required>
              <option value="">Select Category</option>
            </select>
            <label for="category_id">Category</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="name" name="name" placeholder="Sub-Category Name" required>
            <label for="name">Sub-Category Name</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="sub_category_code" name="sub_category_code" placeholder="Sub-Category Code" required>
            <label for="sub_category_code">Sub-Category Code</label>
          </div>
          <button type="submit" class="btn btn-orange w-100">Add Sub-Category</button>
        </form>
      </div>
    </div>
  </div>
  <!-- Right Card: Sub-Category List -->
  <div class="col-md-9">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-success text-white fw-bold d-flex justify-content-between align-items-center">
        <span>Sub-Categories List</span>
        <div>
          <button class="btn btn-sm btn-light text-primary" id="exportExcel">Excel</button>
          <button class="btn btn-sm btn-light text-secondary" id="exportCsv">CSV</button>
        </div>
      </div>
      <div class="card-body">
        <!-- Filters -->
        <div class="row g-3 mb-3">
          <div class="col-md-12">
            <input type="text" id="filter_search" class="form-control" placeholder="Search sub-categories" />
          </div>
        </div>
        <!-- Table -->
        <div class="table-responsive">
          <table id="subCategoryTable" class="table table-hover align-middle mb-0">
            <thead>
              <tr class="table-primary">
                <th class="sticky-col">S.No</th>
                <th>Category</th>
                <th>Sub-Category Name</th>
                <th>Sub-Category Code</th>
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
<!-- Edit Sub-Category Modal -->
<div class="modal fade" id="editSubCategoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Edit Sub-Category</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="editSubCategoryForm">
          <input type="hidden" id="edit_id" name="id">
          <div class="form-floating mb-3">
            <select class="form-select" id="edit_category_id" name="category_id" required>
              <option value="">Select Category</option>
            </select>
            <label for="edit_category_id">Category</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="edit_name" name="name" required>
            <label for="edit_name">Sub-Category Name</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="edit_sub_category_code" name="sub_category_code" required>
            <label for="edit_sub_category_code">Sub-Category Code</label>
          </div>
          <div class="form-floating mb-3">
            <select class="form-select" id="edit_status" name="status" required>
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>
            <label for="edit_status">Status</label>
          </div>
          <button type="submit" class="btn btn-orange w-100">Update Sub-Category</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
$(function(){
  // Load categories dropdown
  function loadCategories(selectElement) {
    $.post('./api/sub_categories_api.php', { action: 'getCategories' }, function(res){
      if(res.status === 'success'){
        let options = '<option value="">Select Category</option>';
        $.each(res.data, function(i, cat){
          options += `<option value="${cat.category_id}">${cat.category_name}</option>`;
        });
        selectElement.html(options);
      }
    }, 'json');
  }
  
  // Initialize category dropdowns
  loadCategories($('#category_id'));
  loadCategories($('#edit_category_id'));
  
 var table;

$(function(){

 table = $('#subCategoryTable').DataTable({
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
      url: './api/sub_categories_api.php',
      type: 'POST',
      data: function(d) {
        d.action = 'list';
        d.filter_search = $('#filter_search').val();
      }
    },
    columns: [
      { data: 'sno' },
      { data: 'category_name' },
      { data: 'subcategory_name' },
      { data: 'subcategory_code' },
      { data: 'status' },
      {
        data: 'subcategory_id',
        orderable: false,
        className: 'text-center',
        render: function(id) {
          return `
            <button class="btn btn-sm btn-outline-primary me-1 edit-sub-category" data-id="${id}" title="Edit">
              <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger deactivate-sub-category" data-id="${id}" title="Deactivate">
              <i class="bi bi-person-dash"></i>
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
  $('#subCategoryForm').submit(function(e){
    e.preventDefault();
    var formData = $(this).serializeArray();
    formData.push({name: 'action', value: 'create'});
    $.ajax({
      url: './api/sub_categories_api.php',
      type: 'POST',
      data: $.param(formData),
      dataType: 'json',
      success: function(res){
        if(res.status === 'success'){
          $('#subCategoryForm')[0].reset();
          loadCategories($('#category_id')); // Reload categories in case of changes
          table.ajax.reload(null, false);
        } else {
          var mssg = res.message || 'Error occurred';
          showAlert(mssg);
        }
      },
      error: function(xhr, status, error){ 
        showAlert('API request failed', 'danger');
      }
    });
  });
  
  // edit button click
  $(document).on('click', '.edit-sub-category', function(){
    let subCategoryId = $(this).data('id');
    
    // fetch sub-category details via API
    $.post('./api/sub_categories_api.php', { action: 'getSubCategory', id: subCategoryId }, function(res){
      if(res.status === 'success'){
        let d = res.data;
        $('#edit_id').val(d.subcategory_id);
        $('#edit_name').val(d.subcategory_name);
        $('#edit_sub_category_code').val(d.subcategory_code);
        $('#edit_status').val(d.status);
        
        // Set category dropdown value
        $('#edit_category_id').val(d.category_id);
        
        $('#editSubCategoryModal').modal('show');
      } else {
        var mssg = res.message || 'Failed to fetch sub-category data';
        showAlert(mssg, 'danger');
      }
    }, 'json');
  });
  
  // save edited sub-category
  $('#editSubCategoryForm').submit(function(e){
    e.preventDefault();
    var formData = $(this).serializeArray();
    formData.push({name: 'action', value: 'edit'});
    
    $.ajax({
      url: './api/sub_categories_api.php',
      type: 'POST',
      data: $.param(formData),
      dataType: 'json',
      success: function(res){
        if(res.status === 'success'){
          $('#editSubCategoryModal').modal('hide');
          table.ajax.reload(null, false);
        } else {
          var mssg = res.message || 'Update failed';
          showAlert(mssg);
        }
      },
      error: function(xhr, status, error){
        showAlert('API request failed', 'danger');
      }
    });
  });
  
  // deactivate button
  $(document).on('click', '.deactivate-sub-category', function(){
    let subCategoryId = $(this).data('id');
    if(confirm('Are you sure you want to deactivate this sub-category?')){
      $.post('./api/sub_categories_api.php', { action: 'deactivate', id: subCategoryId }, function(res){
        if(res.status === 'success'){
          table.ajax.reload(null, false);
        } else {
          var mssg = res.message || 'Error occurred'
          showAlert(mssg, 'danger');
        }
      }, 'json');
    }
  });
});
</script>
</script> <script src="./js/showAlert.js"></script>
<?php
require_once("footer.php");
?>