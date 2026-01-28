<?php
require_once("header.php");
require_once("nav.php");
session_start();

// Access control checks (similar to your users code)
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
    /* Reusing your table styling */
    #accountTable {
        table-layout: fixed;
        width: 100% !important;
        white-space: normal;
    }
</style>
<section class="container-fluid section">
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white fw-bold">
                    Add Bank Account
                </div>
                <div class="card-body">
                    <form id="accountForm">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="bank_name" name="bank_name" placeholder="Bank Name" required>
                            <label for="bank_name">Bank Name</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="account_number" name="account_number" placeholder="Account Number" required>
                            <label for="account_number">Account Number</label>
                        </div>
                        <button type="submit" class="btn btn-orange w-100">Add Account</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white fw-bold d-flex justify-content-between align-items-center">
                    <span>Bank Accounts List</span>
                    <div>
                        <button class="btn btn-sm btn-light text-primary" id="exportExcel">Excel</button>
                        <button class="btn btn-sm btn-light text-secondary" id="exportCsv">CSV</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-12">
                            <input type="text" id="filter_search" class="form-control" placeholder="Search bank accounts" />
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="accountTable" class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="table-primary">
                                    <th class="sticky-col">S.No</th>
                                    <th>Bank Name</th>
                                    <th>Account Number</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="editAccountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Edit Bank Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editAccountForm">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="edit_bank_name" name="bank_name" required>
                        <label for="edit_bank_name">Bank Name</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="edit_account_number" name="account_number" required>
                        <label for="edit_account_number">Account Number</label>
                    </div>
                    <button type="submit" class="btn btn-orange w-100">Update Account</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    var table; // declare globally
    const API_URL = './api/bank_accounts_api'; // **NOTE: You need to create this API file**

    $(function(){
        // --- DataTables Initialization ---
        table = $('#accountTable').DataTable({
            processing: true,
            serverSide: true,
            searching: false, // Disable default search box
            autoWidth: false,
            dom: 'Brtip',
            buttons: [{
                extend: 'excelHtml5',
                className: 'buttons-excel d-none'
            },
            {
                extend: 'csvHtml5',
                className: 'buttons-csv d-none'
            }],
            ajax: {
                url: API_URL,
                type: 'POST',
                data: function(d) {
                    d.action = 'list';
                    d.filter_search = $('#filter_search').val(); // Custom filter
                }
            },
            columns: [
                { data: 'sno' },
                { data: 'bank_name' },
                { data: 'account_number' },
                // Removed Payment Code column from DataTables columns array
                {
                    data: 'id',
                    orderable: false,
                    className: 'text-center',
                    render: function(id) {
                        return `
                        <button class="btn btn-sm btn-outline-primary me-1 edit-account" data-id="${id}" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger delete-account" data-id="${id}" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                        `;
                    }
                }
            ],
            order: [[0, 'desc']]
        });

        // --- Export Button Handlers ---
        $('#exportExcel').on('click', function() {
            table.button('.buttons-excel').trigger();
        });

        $('#exportCsv').on('click', function() {
            table.button('.buttons-csv').trigger();
        });

        // --- Reload on Filter ---
        $('#filter_search').on('keyup', function() {
            table.ajax.reload();
        });

        // --- Add Account Form Submit ---
        $('#accountForm').submit(function(e) {
            e.preventDefault();
            var formData = $(this).serializeArray();
            formData.push({ name: 'action', value: 'create' });

            $.ajax({
                url: API_URL,
                type: 'POST',
                data: $.param(formData),
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        $('#accountForm')[0].reset();
                        table.ajax.reload(null, false); // Reload table data
                        showAlert('Bank Account Added Successfully!', 'success');
                    } else {
                        var mssg = res.message || 'Error occurred while adding account.';
                        showAlert(mssg, 'danger');
                    }
                },
                error: function() {
                    showAlert('API request failed. Check server and API endpoint.', 'danger');
                }
            });
        });

        // --- Edit Button Click (Fetch Data) ---
        $(document).on('click', '.edit-account', function() {
            let accountId = $(this).data('id');

            $.post(API_URL, {
                action: 'getAccount',
                id: accountId
            }, function(res) {
                if (res.status === 'success') {
                    let d = res.data;
                    $('#edit_id').val(d.id);
                    $('#edit_bank_name').val(d.bank_name);
                    $('#edit_account_number').val(d.account_number);
                    // Removed: $('#edit_payment_code').val(d.payment_code);
                    $('#editAccountModal').modal('show');
                } else {
                    var mssg = res.message || 'Failed to fetch account data.';
                    showAlert(mssg, 'danger');
                }
            }, 'json');
        });

        // --- Save Edited Account Form Submit ---
        $('#editAccountForm').submit(function(e) {
            e.preventDefault();
            var formData = $(this).serializeArray();
            formData.push({ name: 'action', value: 'edit' });

            $.ajax({
                url: API_URL,
                type: 'POST',
                data: $.param(formData),
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        $('#editAccountModal').modal('hide');
                        table.ajax.reload(null, false);
                        showAlert('Bank Account Updated Successfully!', 'success');
                    } else {
                        var mssg = res.message || 'Update failed.';
                        showAlert(mssg, 'danger');
                    }
                },
                error: function() {
                    showAlert('API request failed. Check server and API endpoint.', 'danger');
                }
            });
        });

        // --- Delete Button Click ---
        $(document).on('click', '.delete-account', function() {
            let accountId = $(this).data('id');
            if (confirm('Are you sure you want to delete this bank account?')) {
                $.post(API_URL, {
                    action: 'delete',
                    id: accountId
                }, function(res) {
                    if (res.status === 'success') {
                        table.ajax.reload(null, false);
                        showAlert('Bank Account Deleted Successfully!', 'success');
                    } else {
                        var mssg = res.message || 'Delete failed.';
                        showAlert(mssg, 'danger');
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