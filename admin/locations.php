<?php
require_once("header.php");
require_once("nav.php");
?>
<style>

  #locationTable {
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
  <!-- Left Card: Add Location -->
  <div class="col-md-3">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-success text-white fw-bold">
        Add Location
      </div>
      <div class="card-body">
        <form id="locationForm">
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="zone_name" name="zone_name" placeholder="Zone Name" required>
            <label for="zone_name">Zone Name</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="zone_code" name="zone_code" placeholder="Zone Code" required>
            <label for="zone_code">Zone Code</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="location_name" name="location_name" placeholder="Location Name" required>
            <label for="location_name">Location Name</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="location_code" name="location_code" placeholder="Location Code" required>
            <label for="location_code">Location Code</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="location_address" name="location_address" placeholder="Location Address">
            <label for="location_address">Location Address</label>
          </div>
          <button type="submit" class="btn btn-orange w-100">Add Location</button>
        </form>
      </div>
    </div>
  </div>
  <!-- Right Card: Location List -->
  <div class="col-md-8">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-success text-white fw-bold d-flex justify-content-between align-items-center">
        <span>Locations List</span>
        <div>
          <button class="btn btn-sm btn-light text-primary" id="exportExcel">Excel</button>
          <button class="btn btn-sm btn-light text-secondary" id="exportCsv">CSV</button>
        </div>
      </div>
      <div class="card-body">
        <!-- Filters -->
        <div class="row g-3 mb-3">
          <div class="col-md-12">
            <input type="text" id="filter_search" class="form-control" placeholder="Search locations" />
          </div>
        </div>
        <!-- Table -->
        <div class="table-responsive">
          <table id="locationTable" class="table table-hover align-middle mb-0">
            <thead>
              <tr class="table-primary">
                <th class="sticky-col">S.No</th>
                <th>Zone Name</th>
                <th>Zone Code</th>
                <th>Location Name</th>
                <th>Location Code</th>
                <th>Location Address</th>
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
<!-- Edit Location Modal -->
<div class="modal fade" id="editLocationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Edit Location</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="editLocationForm">
          <input type="hidden" id="edit_id" name="id">
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="edit_zone_name" name="zone_name" required>
            <label for="edit_zone_name">Zone Name</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="edit_zone_code" name="zone_code" required>
            <label for="edit_zone_code">Zone Code</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="edit_location_name" name="location_name" required>
            <label for="edit_location_name">Location Name</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="edit_location_code" name="location_code" required>
            <label for="edit_location_code">Location Code</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="edit_location_address" name="location_address">
            <label for="edit_location_address">Location Address</label>
          </div>
          <button type="submit" class="btn btn-orange w-100">Update Location</button>
        </form>
      </div>
    </div>
  </div>
</div>
 
<script>
  var table;
$(function(){
   table = $('#locationTable').DataTable({
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
      url: './api/locations_api.php',
      type: 'POST',
      data: function(d) {
        d.action = 'list';
        d.filter_search = $('#filter_search').val();
      }
    },
    columns: [
      { data: 'sno' },
      { data: 'zone_name' },
      { data: 'zone_code' },
      { data: 'location_name' },
      { data: 'location_code' },
      { data: 'location_address' },
      {
        data: 'id',
        orderable: false,
        className: 'text-center',
        render: function(id) {
          return `
            <button class="btn btn-sm btn-outline-primary me-1 edit-location" data-id="${id}" title="Edit">
              <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger delete-location" data-id="${id}" title="Delete">
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
  $('#locationForm').submit(function(e){
    e.preventDefault();
    var formData = $(this).serializeArray();
    formData.push({name: 'action', value: 'create'});
    $.ajax({
      url: './api/locations_api.php',
      type: 'POST',
      data: $.param(formData),
      dataType: 'json',
      success: function(res){
        if(res.status === 'success'){
          $('#locationForm')[0].reset();
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
$(document).on('click', '.edit-location', function(){
  let locationId = $(this).data('id');
  
  // fetch location details via API
  $.post('./api/locations_api.php', { action: 'getLocation', id: locationId }, function(res){
    if(res.status === 'success'){
      let d = res.data;
      $('#edit_id').val(d.id);
      $('#edit_zone_name').val(d.zone_name);
      $('#edit_zone_code').val(d.zone_code);
      $('#edit_location_name').val(d.location_name);
      $('#edit_location_code').val(d.location_code);
      $('#edit_location_address').val(d.location_address);
      $('#editLocationModal').modal('show');
    } else {
        var mssg = res.message || 'Failed to fetch location data';
        showAlert(mssg, 'danger');
    }
  }, 'json');
});
  
  // save edited location
  $('#editLocationForm').submit(function(e){
    e.preventDefault();
    var formData = $(this).serializeArray();
    formData.push({name: 'action', value: 'edit'});
    
    $.ajax({
      url: './api/locations_api.php',
      type: 'POST',
      data: $.param(formData),
      dataType: 'json',
      success: function(res){
        if(res.status === 'success'){
          $('#editLocationModal').modal('hide');
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
  $(document).on('click', '.delete-location', function(){
    let locationId = $(this).data('id');
    if(confirm('Are you sure you want to delete this location?')){
      $.post('./api/locations_api.php', { action: 'delete', id: locationId }, function(res){
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