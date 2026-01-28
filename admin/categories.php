<?php
require_once("header.php");
require_once("nav.php");
require_once("../auth.php");
requireRole(['Admin','Requester']);
?>
<style>
  #categoryTable {
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
  <!-- Left Card: Add Category -->
  <div class="col-md-3">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-success text-white fw-bold">
        Add Category
      </div>
      <div class="card-body">
        <form id="categoryForm">
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="name" name="name" placeholder="Category Name" required>
            <label for="name">Category Name</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="category_code" name="category_code" placeholder="Category Code" required>
            <label for="category_code">Category Code</label>
          </div>
          <button type="submit" class="btn btn-orange w-100">Add Category</button>
        </form>
      </div>
    </div>
  </div>
  <!-- Right Card: Category List -->
  <div class="col-md-9">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-success text-white fw-bold d-flex justify-content-between align-items-center">
        <span>Categories List</span>
        <div>
          <button class="btn btn-sm btn-light text-primary" id="exportExcel">Excel</button>
          <button class="btn btn-sm btn-light text-secondary" id="exportCsv">CSV</button>
        </div>
      </div>
      <div class="card-body">
        <!-- Filters -->
        <div class="row g-3 mb-3">
          <div class="col-md-12">
            <input type="text" id="filter_search" class="form-control" placeholder="Search categories" />
          </div>
        </div>
        <!-- Table -->
        <div class="table-responsive">
          <table id="categoryTable" class="table table-hover align-middle mb-0">
            <thead>
              <tr class="table-primary">
                <th class="sticky-col">S.No</th>
                <th>Category Name</th>
                <th>Category Code</th>
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

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Edit Category</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="editCategoryForm">
          <input type="hidden" id="edit_id" name="id">
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="edit_name" name="name" required>
            <label for="edit_name">Category Name</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="edit_category_code" name="category_code" required>
            <label for="edit_category_code">Category Code</label>
          </div>
          <div class="form-floating mb-3">
            <select class="form-select" id="edit_status" name="status" required>
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>
            <label for="edit_status">Status</label>
          </div>
          <button type="submit" class="btn btn-orange w-100">Update Category</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
var table; // declare globally so it's accessible in all handlers

$(function(){
    // Initialize DataTable
    table = $('#categoryTable').DataTable({
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
            url: './api/categories_api.php',
            type: 'POST',
            data: function(d) {
                d.action = 'list';
                d.filter_search = $('#filter_search').val();
            }
        },
        columns: [
            { data: 'sno' },
            { data: 'category_name' },
            { data: 'category_code' },
            { data: 'status' },
            {
                data: 'category_id',
                orderable: false,
                className: 'text-center',
                render: function(id) {
                    return `
                        <button class="btn btn-sm btn-outline-primary me-1 edit-category" data-id="${id}" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger deactivate-category" data-id="${id}" title="Deactivate">
                            <i class="bi bi-person-dash"></i>
                        </button>`;
                }
            }
        ],
        order: [[0, 'desc']]
    });

    // Export buttons
    $('#exportExcel').on('click', function() {
        table.button('.buttons-excel').trigger();
    });

    $('#exportCsv').on('click', function() {
        table.button('.buttons-csv').trigger();
    });

    // Filter search reload
    $('#filter_search').on('keyup', function(){
        table.ajax.reload();
    });

    // Add category form submission
    $('#categoryForm').submit(function(e){
        e.preventDefault();
        var formData = $(this).serializeArray();
        formData.push({name: 'action', value: 'create'});
        $.ajax({
            url: './api/categories_api.php',
            type: 'POST',
            data: $.param(formData),
            dataType: 'json',
            success: function(res){
                if(res.status === 'success'){
                    $('#categoryForm')[0].reset();
                    table.ajax.reload(null, false);  // Now accessible
                } else {
                    var mssg = res.message || 'Error occurred';
                    showAlert(mssg, 'danger');
                }
            },
            error: function(xhr, status, error){ 
                showAlert('API request failed', 'danger');
            }
        });
    });

    // Edit category button
    $(document).on('click', '.edit-category', function(){
        let categoryId = $(this).data('id');
        $.post('./api/categories_api.php', { action: 'getCategory', id: categoryId }, function(res){
            if(res.status === 'success'){
                let d = res.data;
                $('#edit_id').val(d.category_id);
                $('#edit_name').val(d.category_name);
                $('#edit_category_code').val(d.category_code);
                $('#edit_status').val(d.status);
                $('#editCategoryModal').modal('show');
            } else {
                var mssg = res.message || 'Failed to fetch category data';
                showAlert(mssg, 'danger');
            }
        }, 'json');
    });

    // Edit category form submission
    $('#editCategoryForm').submit(function(e){
        e.preventDefault();
        var formData = $(this).serializeArray();
        formData.push({name: 'action', value: 'edit'});
        $.ajax({
            url: './api/categories_api.php',
            type: 'POST',
            data: $.param(formData),
            dataType: 'json',
            success: function(res){
                if(res.status === 'success'){
                    $('#editCategoryModal').modal('hide');
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

    // Deactivate category
    $(document).on('click', '.deactivate-category', function(){
        let categoryId = $(this).data('id');
        if(confirm('Are you sure you want to deactivate this category?')){
            $.post('./api/categories_api.php', { action: 'deactivate', id: categoryId }, function(res){
                if(res.status === 'success'){
                    table.ajax.reload(null, false);
                } else {
                    var mssg = res.message || 'Error occurred';
                    showAlert(mssg);
                }
            }, 'json');
        }
    });
});
</script>

<script src="./js/showAlert.js"></script>
<?php
require_once("footer.php");
?>
