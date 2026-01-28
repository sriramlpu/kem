<?php
require_once("header.php");
require_once("nav.php");
session_start();



if (!isset($_SESSION["userId"] )) {
    echo '<script>window.location.href = "../login.php";</script>';
    exit;
}
if (!isset($_SESSION["roleName"]) || $_SESSION["roleName"] !== 'Admin') {
    // Destroy session to prevent access
    session_destroy();
    echo '<script>alert("Access denied. Only Authorized can login."); window.location.href = "../login.php";</script>';
    exit;
}


$userId = $_SESSION["userId"];
?>
<style>

  #userTable {
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
    <!-- Left Card: Add User -->
    <div class="col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-success text-white fw-bold">
          Add User
        </div>
        <div class="card-body">
          <form id="userForm">
            <div class="form-floating mb-3">
              <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
              <label for="username">Username</label>
            </div>
            <div class="form-floating mb-3">
              <input type="text" class="form-control" id="phone" name="phone" placeholder="Phone" required>
              <label for="phone">Phone</label>
            </div>
            <div class="form-floating mb-3">
              <input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
              <label for="email">Email</label>
            </div>
            <div class="form-floating mb-3">
              <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
              <label for="password">Password</label>
            </div>
            <!--<div class="form-floating mb-3">-->
            <!--  <select class="form-select" id="dept_id" name="dept_id"  >-->
            <!--    <option value="">Select Department</option>-->
            <!--  </select>-->
            <!--  <label for="dept_id">Department</label>-->
            <!--</div>-->
            <div class="form-floating mb-3">
              <select class="form-select" id="role_id" name="role_id" required>
                <option value="">Select Role</option>
              </select>
              <label for="role_id">Role</label>
            </div>
            <div class="form-floating mb-3">
              <select class="form-select" id="branch_id" name="branch_id" >
                <option value="">Select Branch</option>
              </select>
              <label for="branch_id">Branch</label>
            </div>

            <div class="form-floating mb-3">
              <select class="form-select" id="status" name="status" required>
                <option value="active">Active</option>
                <option value="disabled">Disabled</option>
              </select>
              <label for="status">Status</label>
            </div>
            <button type="submit" class="btn btn-orange w-100">Add User</button>
          </form>
        </div>
      </div>
    </div>
    <!-- Right Card: Users List -->
    <div class="col-md-9">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-success text-white fw-bold d-flex justify-content-between align-items-center">
          <span>Users List</span>
          <div>
            <button class="btn btn-sm btn-light text-primary" id="exportExcel">Excel</button>
            <button class="btn btn-sm btn-light text-secondary" id="exportCsv">CSV</button>
          </div>
        </div>
        <div class="card-body">
          <!-- Filters -->
          <div class="row g-3 mb-3">
            <div class="col-md-12">
              <input type="text" id="filter_search" class="form-control" placeholder="Search users" />
            </div>
          </div>
          <!-- Table -->
          <div class="table-responsive">
            <table id="userTable" class="table table-hover align-middle mb-0">
              <thead>
                <tr class="table-primary">
                  <th class="sticky-col">S.No</th>
                  <th>Username</th>
                  <th>Phone</th>
                  <th>Email</th>
                  <th>Branch</th>
                  <th>Role</th>
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
<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Edit User</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="editUserForm">
          <input type="hidden" id="edit_id" name="id">
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="edit_username" name="username" required>
            <label for="edit_username">Username</label>
          </div>
          <div class="form-floating mb-3">
            <input type="email" class="form-control" id="edit_email" name="email" required>
            <label for="edit_email">Email</label>
          </div>
          <!-- <div class="form-floating mb-3">
            <input type="password" class="form-control" id="edit_password" name="password" placeholder="Leave blank to keep current password">
            <label for="edit_password">Password</label>
          </div> -->
          <div class="form-floating mb-3">
            <select class="form-select" id="edit_role_id" name="role_id" required>
              <option value="">Select Role</option>
            </select>
            <label for="edit_role_id">Role</label>
          </div>
          <!--<div class="form-floating mb-3">-->
          <!--  <select class="form-select" id="edit_dept_id" name="dept_id" required>-->
          <!--    <option value="">Select Department</option>-->
          <!--  </select>-->
          <!--  <label for="edit_dept_id">Department</label>-->
          <!--</div>-->

          <div class="form-floating mb-3">
            <select class="form-select" id="edit_branch_id" name="branch_id" required>
              <option value="">Select Branch</option>
            </select>
            <label for="edit_branch_id">Department</label>
          </div>
          <div class="form-floating mb-3">
            <select class="form-select" id="edit_status" name="status" required>
              <option value="Active">Active</option>
              <option value="Disabled">Disabled</option>
            </select>
            <label for="edit_status">Status</label>
          </div>
          <button type="submit" class="btn btn-orange w-100">Update User</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  $(function() {
    // Load roles for dropdowns
    function loadRoles() {
      $.get('./api/roles_api.php', {
        action: 'list'
      }, function(res) {
        if (res.status === 'success') {
          let options = '<option value="">Select Role</option>';
          res.data.forEach(role => {
            options += `<option value="${role.role_id}">${role.role_name}</option>`;
          });
          $('#role_id, #edit_role_id').html(options);
        }
      }, 'json');
    }

    loadRoles();

    // function loadDepartments() {
    //   $.get('./api/departments_api.php', {
    //     action: 'list'
    //   }, function(res) {
    //     if (res.status === 'success') {
    //       let options = '<option value="">Select Department</option>';
    //       res.data.forEach(dept => {
    //         options += `<option value="${dept.dept_id}">${dept.dept_name}</option>`;
    //       });
    //       $('#dept_id, #edit_dept_id').html(options);
    //     }
    //   }, 'json');
    // }

    // loadDepartments();


    function loadBranches() {
      $.get('./api/branches_api.php', {
        action: 'list'
      }, function(res) {
        
          let options = '<option value="">Select Branch</option>';
          res.data.forEach(branch => {
            options += `<option value="${branch.id}">${branch.branch_name}</option>`;
          });
          $('#branch_id, #edit_branch_id').html(options);
        
      }, 'json');
    }

    loadBranches();
var table; // declare globally

$(function(){
    table = $('#userTable').DataTable({
        processing: true,
        serverSide: true,
        searching: false, // Disable default search box
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
          url: './api/users_api',
          type: 'POST',
          data: function(d) {
            d.action = 'list';
            d.filter_search = $('#filter_search').val(); // Your custom filter
          }
        },
        columns: [{
            data: 'sno'
          },
          {
            data: 'username'
          },
           {
            data: 'phone'
          },
          {
            data: 'email'
          },
          {
            data: 'branch_name'
          },
          {
            data: 'role_name'
          },
          {
            data: 'status'
          },
          
          {
            data: 'id',
            orderable: false,
            className: 'text-center',
            render: function(id) {
              return `
          <button class="btn btn-sm btn-outline-primary me-1 edit-user" data-id="${id}" title="Edit">
            <i class="bi bi-pencil"></i>
          </button>
          <button class="btn btn-sm btn-outline-danger delete-user" data-id="${id}" title="Delete">
            <i class="bi bi-trash"></i>
          </button>
        `;
            }
          }
        ],
        order: [
          [0, 'desc']
        ]
      });

      $('#exportExcel').on('click', function() {
  table.button('.buttons-excel').trigger();
});

$('#exportCsv').on('click', function() {
  table.button('.buttons-csv').trigger();
});


    });



    // reload on filter
    $('#filter_search').on('keyup', function() {
      table.ajax.reload();
    });

    // form submit
    $('#userForm').submit(function(e) {
      e.preventDefault();
      var formData = $(this).serializeArray();
      formData.push({
        name: 'action',
        value: 'create'
      });
      $.ajax({
        url: './api/users_api',
        type: 'POST',
        data: $.param(formData),
        dataType: 'json',
        success: function(res) {
          if (res.status === 'success') {
            $('#userForm')[0].reset();
            table.ajax.reload(null, false)
          } else {
            var mssg = res.message || 'Error occurred'
            showAlert(mssg, 'danger');
          }
        },
        error: function(xhr, error, status) {
          showAlert('API request failed', 'danger');
        }
      });
    });
    

    // edit button click
    $(document).on('click', '.edit-user', function() {
      let userId = $(this).data('id');

      // fetch user details via API
      $.post('./api/users_api', {
        action: 'getUser',
        id: userId
      }, function(res) {
        if (res.status === 'success') {
          let d = res.data;
          $('#edit_id').val(d.id);
          $('#edit_username').val(d.username);
          $('#edit_email').val(d.email);
          $('#edit_role_id').val(d.role_id);
          $('#edit_status').val(d.status);
          $('#editUserModal').modal('show');
        } else {
          var mssg = res.message || 'Failed to fetch user data';
          showAlert(mssg, 'danger');
        }
      }, 'json');
    });

    // save edited user
    $('#editUserForm').submit(function(e) {
      e.preventDefault();
      var formData = $(this).serializeArray();
      formData.push({
        name: 'action',
        value: 'edit'
      });

      $.ajax({
        url: './api/users_api',
        type: 'POST',
        data: $.param(formData),
        dataType: 'json',
        success: function(res) {
          if (res.status === 'success') {
            $('#editUserModal').modal('hide');
            table.ajax.reload(null, false);
          } else {
            var mssg = res.message || 'Update failed';
            showAlert(mssg, 'danger');
          }
        },
        error: function(xhr, status, error) {
          showAlert('API request failed', 'danger');
        }
      });
    });

    // delete button
    $(document).on('click', '.delete-user', function() {
      let userId = $(this).data('id');
      if (confirm('Are you sure you want to delete this user?')) {
        $.post('./api/users_api.php', {
          action: 'delete',
          id: userId
        }, function(res) {
          if (res.status === 'success') {
            table.ajax.reload(null, false);
          } else {
            var mssg = res.message || 'Delete failed';
            showAlert(mssg, 'danger');
          }
        }, 'json');
      }
    });
  });

   $.getJSON('./api/branches_api.php?action=getActiveBranches', function(res) {
        if (res.status === 'success') {
            const branchSelect = $('#branch_id');
            branchSelect.empty().append('<option value="">Select Branch</option>');
            res.data.forEach(function(branch) {
                branchSelect.append(`<option value="${branch.branch_id}">${branch.branch_name}</option>`);
            });
        }
    });
</script>
<script src="./js/showAlert.js"></script>
<?php
require_once("footer.php");
?>