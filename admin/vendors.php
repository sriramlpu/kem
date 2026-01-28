<?php
require_once("header.php");
require_once("nav.php");
require_once("../auth.php");
requireRole(['Admin','Requester']);
?>
<style>
  #vendorTable {
    table-layout: fixed;
    width: 100% !important;
    white-space: normal;
  }
  /* For Select2 dropdown to appear above */
  .select2-container--open .select2-dropdown {
    z-index: 9999;
  }
</style>

<div class="row g-4">
  <!-- Left Card: Add Vendor -->
  <div class="col-md-4">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-success text-white fw-bold">
        Add Vendor
      </div>
      <div class="card-body">
        <form id="vendorForm">
          <div class="form-floating mb-3">
            <input type="text" class="form-control" name="vendor_name" id="vendor_name" placeholder="Vendor Name" required>
            <label for="vendor_name">Vendor Name</label>
          </div>
          <div class="form-floating mb-3">
            <input type="email" class="form-control" name="email" id="email" placeholder="Email">
            <label for="email">Email</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" name="phone" id="phone" placeholder="Phone">
            <label for="phone">Phone</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" name="gstin" id="gstin" placeholder="GSTIN">
            <label for="gstin">GSTIN</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" name="address" id="address" placeholder="Address">
            <label for="address">Address</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" name="city" id="city" placeholder="City">
            <label for="city">City</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" name="state" id="state" placeholder="State">
            <label for="state">State</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" name="country" id="country" placeholder="Country">
            <label for="country">Country</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" name="pincode" id="pincode" placeholder="Pincode">
            <label for="pincode">Pincode</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" name="account_number" id="account_number" placeholder="Account Number">
            <label for="account_number">Account Number</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" name="branch" id="Branch" placeholder="Bank Branch ">
            <label for="branch">Branch Of Bank</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" name="ifsc" id="IFSC" placeholder="IFSC ">
            <label for="ifsc"> IFSC Code</label>
          </div>
          <button type="submit" class="btn btn-orange w-100">Add Vendor</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Right Card: Vendor List -->
  <div class="col-md-8">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-success text-white fw-bold d-flex justify-content-between align-items-center">
        <span>Vendors List</span>
        <div>
          <button class="btn btn-sm btn-light text-primary" id="exportExcel">Excel</button>
          <button class="btn btn-sm btn-light text-secondary" id="exportCsv">CSV</button>
        </div>
      </div>
      <div class="card-body">
        <div class="row g-3 mb-3">
          <div class="col-md-12">
            <input type="text" id="filter_search" class="form-control" placeholder="Search vendors" />
          </div>
        </div>

        <div class="table-responsive">
          <table id="vendorTable" class="table table-hover align-middle mb-0">
            <thead>
              <tr class="table-primary">
                <th class="sticky-col">S.No</th>
                <th>Vendor Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Account Number</th>
                <th>Bank OfbBranch</th>
                <th>IFSC</th>
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
</div>

<!-- Edit Vendor Modal -->
<div class="modal fade" id="editVendorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content shadow-lg">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Edit Vendor</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="editVendorForm">
          <input type="hidden" name="vendor_id" id="edit_vendor_id">
          <div class="row g-3">
            <div class="col-md-6 form-floating">
              <input type="text" class="form-control" name="vendor_name" id="edit_vendor_name" required>
              <label for="edit_vendor_name">Vendor Name</label>
            </div>
            <div class="col-md-6 form-floating">
              <input type="email" class="form-control" name="email" id="edit_email">
              <label for="edit_email">Email</label>
            </div>
            <div class="col-md-6 form-floating">
              <input type="text" class="form-control" name="phone" id="edit_phone">
              <label for="edit_phone">Phone</label>
            </div>
            <div class="col-md-6 form-floating">
              <input type="text" class="form-control" name="gstin" id="edit_gstin">
              <label for="edit_gstin">GSTIN</label>
            </div>
            
            <div class="col-md-4 form-floating">
              <input type="text" class="form-control" name="branch" id="edit_branch">
              <label for="edit_branch">Branch</label>
            </div>
            <div class="col-md-4 form-floating">
              <input type="text" class="form-control" name="account_number" id="edit_account_number">
              <label for="edit_account_number">Account Number</label>
            </div>
            <div class="col-md-4 form-floating">
              <input type="text" class="form-control" name="ifsc" id="edit_ifsc_code">
              <label for="edit_ifsc_code">IFSC Code</label>
            </div>
            <div class="col-md-12 form-floating">
              <input type="text" class="form-control" name="address" id="edit_address">
              <label for="edit_address">Address</label>
            </div>
            <div class="col-md-4 form-floating">
              <input type="text" class="form-control" name="city" id="edit_city">
              <label for="edit_city">City</label>
            </div>
            <div class="col-md-4 form-floating">
              <input type="text" class="form-control" name="state" id="edit_state">
              <label for="edit_state">State</label>
            </div>
            <div class="col-md-4 form-floating">
              <input type="text" class="form-control" name="country" id="edit_country">
              <label for="edit_country">Country</label>
            </div>
            <div class="col-md-4 form-floating">
              <input type="text" class="form-control" name="pincode" id="edit_pincode">
              <label for="edit_pincode">Pincode</label>
            </div>
            <div class="col-md-4 form-floating">
              <select class="form-select" name="status" id="edit_status">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
              </select>
              <label for="edit_status">Status</label>
            </div>
          </div>
          <button type="submit" class="btn btn-orange w-100 mt-3">Update Vendor</button>
        </form>
      </div>
    </div>
  </div>
</div>


<script>
  var table;
$(function(){
 table = $('#vendorTable').DataTable({
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
      url: './api/vendors_api.php',
      type: 'POST',
      data: function(d){ d.action='list'; d.filter_search=$('#filter_search').val(); }
    },
    columns: [
      {data:'sno'},
      {data:'vendor_name'},
      {data:'email'},
      {data:'phone'},
       {data:'account_number'},   // new
  {data:'branch'},           // new
  {data:'ifsc'},      
      {data:'status'},
      {
        data:'vendor_id',
        orderable:false,
        className:'text-center',
        render:function(id){
          return `<button class="btn btn-sm btn-outline-primary me-1 edit-vendor" data-id="${id}"><i class="bi bi-pencil"></i></button>
                  <button class="btn btn-sm btn-outline-danger deactivate-vendor" data-id="${id}"><i class="bi bi-person-dash"></i></button>`;
        }
      }
    ],
    order:[[0,'desc']]
  });

  // Export buttons
  $('#exportExcel').on('click', function(){ table.button('.buttons-excel').trigger(); });
  $('#exportCsv').on('click', function(){ table.button('.buttons-csv').trigger(); });
});
  // Search filter
  $('#filter_search').on('keyup', function(){ table.ajax.reload(); });

  // Add Vendor
  $('#vendorForm').submit(function(e){
    e.preventDefault();
    $.post('./api/vendors_api.php', $(this).serialize() + '&action=create', function(res){
      if(res.status==='success'){ $('#vendorForm')[0].reset(); table.ajax.reload(null,false); } 
      else showAlert(res.message || 'Error','danger');
    },'json');
  });

  // Edit Vendor button
  $(document).on('click','.edit-vendor',function(){
    let id = $(this).data('id');
    $.post('./api/vendors_api.php', {action:'getVendor', vendor_id:id}, function(res){
      if(res.status==='success'){
        let d=res.data;
        $('#edit_vendor_id').val(d.vendor_id);
        $('#edit_vendor_name').val(d.vendor_name);
        $('#edit_email').val(d.email);
        $('#edit_phone').val(d.phone);
        $('#edit_gstin').val(d.gstin);
        $('#edit_address').val(d.address);
        $('#edit_city').val(d.city);
        $('#edit_state').val(d.state);
        $('#edit_country').val(d.country);
        $('#edit_pincode').val(d.pincode);
        $('#edit_branch').val(d.branch);
        $('#edit_account_number').val(d.account_number);
        $('#edit_ifsc_code').val(d.ifsc);
        $('#edit_status').val(d.status);
        $('#editVendorModal').modal('show');
      } else showAlert(res.message,'danger');
    },'json');
  });

  // Update Vendor
  $('#editVendorForm').submit(function(e){
    e.preventDefault();
    $.post('./api/vendors_api.php', $(this).serialize() + '&action=edit', function(res){
      if(res.status==='success'){ $('#editVendorModal').modal('hide'); 
        table.ajax.reload(null,false); } 
      else showAlert(res.message || 'Update failed','danger');
    },'json');
  });

  // Deactivate Vendor
  $(document).on('click','.deactivate-vendor',function(){
    let id=$(this).data('id');
    if(confirm('Are you sure to deactivate this vendor?')){
      $.post('./api/vendors_api.php',{action:'deactivate', vendor_id:id},function(res){
        if(res.status==='success') table.ajax.reload(null,false); else showAlert(res.message,'danger');
      },'json');
    }
  });

</script>
<script src="./js/showAlert.js"></script>
<?php require_once("footer.php"); ?>
