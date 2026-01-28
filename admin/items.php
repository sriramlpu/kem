<?php
require_once("header.php");
require_once("nav.php");
require_once("../auth.php");
requireRole(['Admin','Requester']);
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.4.1/papaparse.min.js"></script>

<style>
  #itemTable {
    table-layout: fixed;
    width: 100% !important;
    white-space: normal;
  }
  .select2-container--open .select2-dropdown {
    z-index: 9999;
  }
</style>

<section class="container-fluid section">
<div class="row g-4">
  <!-- Left Card: Add Item -->
  <div class="col-md-3">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-success text-white fw-bold">Add Item</div>
      <div class="card-body">
        <form id="itemForm">
          <div class="form-floating mb-3">
            <select class="form-select" id="category_id" name="category_id" required>
              <option value="">Select Category</option>
            </select>
            <label for="category_id">Category</label>
          </div>
          <div class="form-floating mb-3">
            <select class="form-select" id="sub_category_id" name="sub_category_id" required disabled>
              <option value="">Select Sub-Category</option>
            </select>
            <label for="sub_category_id">Sub-Category</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="name" name="name" placeholder="Item Name" required>
            <label for="name">Item Name</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="item_code" name="item_code" placeholder="Item Code" required>
            <label for="item_code">Item Code</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="UOM" name="UOM" placeholder="UOM" required>
            <label for="UOM">UOM</label>
          </div>
          <div class="form-floating mb-3">
            <input type="number" class="form-control" id="Tax_percentage" name="Tax_percentage" placeholder="Tax Percentage" required step="any">
            <label for="Tax_percentage">Tax Percentage</label>
          </div>
          <div class="form-floating mb-3">
            <input type="number" class="form-control" id="unit_price" name="unit_price" placeholder="Unit Price" required step="any">
            <label for="unit_price">Unit Price</label>
          </div>
          <button type="submit" class="btn btn-orange w-100">Add Item</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Right Card: Items List -->
  <div class="col-md-9">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-success text-white fw-bold d-flex justify-content-between align-items-center">
        <span>Items List</span>
        <div>
             <button class="btn btn-sm btn-success" id="uploadExcelBtn">
    <i class="bi bi-upload"></i> Upload Excel
  </button>
  <button class="btn btn-sm btn-primary" id="downloadExcelBtn">
  <i class="bi bi-download"></i> Download Template
</button>
  <input type="file" id="excelFileInput" accept=".xls,.xlsx" style="display:none;">
          <button class="btn btn-sm btn-light text-primary" id="exportExcel">Excel</button>
          <button class="btn btn-sm btn-light text-secondary" id="exportCsv">CSV</button>
          <button class="btn btn-sm btn-light text-dark" id="printAll">Print</button>

        </div>
      </div>
      <div class="card-body">
        <div class="row g-3 mb-3">
          <div class="col-md-12">
            <input type="text" id="filter_search" class="form-control" placeholder="Search items" />
          </div>
        </div>
        <div class="table-responsive">
          <table id="itemTable" class="table table-hover align-middle mb-0">
            <thead>
              <tr class="table-primary">
                <th>S.No</th>
                <th>Category</th>
                <th>Sub-Category</th>
                <th>Item Name</th>
                <th>Item Code</th>
                <th>Unit Price</th>
                <th>Tax %</th>
                <th>UOM</th>
                <th>Status</th>
                <th class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody><!-- Filled by DataTables --></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
</section>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Edit Item</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="editItemForm">
          <input type="hidden" id="edit_id" name="id">
          <div class="form-floating mb-3">
            <select class="form-select" id="edit_category_id" name="category_id" required></select>
            <label for="edit_category_id">Category</label>
          </div>
          <div class="form-floating mb-3">
            <select class="form-select" id="edit_sub_category_id" name="sub_category_id" required></select>
            <label for="edit_sub_category_id">Sub-Category</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="edit_name" name="name" required>
            <label for="edit_name">Item Name</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="edit_item_code" name="item_code" required>
            <label for="edit_item_code">Item Code</label>
          </div>
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="edit_UOM" name="UOM" required>
            <label for="edit_UOM">UOM</label>
          </div>
          <div class="form-floating mb-3">
            <input type="number" class="form-control" id="edit_Tax_percentage" name="Tax_percentage" required step="any">
            <label for="edit_Tax_percentage">Tax Percentage</label>
          </div>
          <div class="form-floating mb-3">
            <input type="number" class="form-control" id="edit_unit_price" name="unit_price" required step="any">
            <label for="edit_unit_price">Unit Price</label>
          </div>
          <div class="form-floating mb-3">
            <select class="form-select" id="edit_status" name="status" required>
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>
            <label for="edit_status">Status</label>
          </div>
          <button type="submit" class="btn btn-orange w-100">Update Item</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
$(function(){
  var table = $('#itemTable').DataTable({
    processing: true,
    serverSide: true,
    autoWidth: false,
    dom: 'rtip',
   // buttons: [
    //  { extend: 'excelHtml5', className: 'buttons-excel d-none' },
      //{ extend: 'csvHtml5', className: 'buttons-csv d-none' }
    //],
    ajax: {
      url: './api/items_api.php',
      type: 'POST',
      data: function(d){ d.action = 'list'; d.filter_search = $('#filter_search').val(); }
    },
    columns: [
      { data: 'sno' },
      { data: 'category_name' },
      { data: 'subcategory_name' },
      { data: 'item_name' },
      { data: 'item_code' },
      { data: 'unit_price' },
      { data: 'Tax_percentage' },
      { data: 'UOM' },
      
      { data: 'status' },
      {
        data: 'item_id',
        orderable: false,
        className: 'text-center',
        render: function(id){
          return `
            <button class="btn btn-sm btn-outline-primary me-1 edit-item" data-id="${id}">
              <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger deactivate-item" data-id="${id}">
              <i class="bi bi-person-dash"></i>
            </button>`;
        }
      }
    ]
  });

  $('#exportExcel').click(()=>table.button('.buttons-excel').trigger());
  $('#exportCsv').click(()=>table.button('.buttons-csv').trigger());

  $('#filter_search').on('keyup', ()=>table.ajax.reload());

  // Add item
  $('#itemForm').submit(function(e){
    e.preventDefault();
    let formData = $(this).serializeArray();
    formData.push({name:'action',value:'create'});
    $.post('./api/items_api.php', $.param(formData), function(res){
      if(res.status==='success'){
        $('#itemForm')[0].reset();
        $('#sub_category_id').prop('disabled',true).html('<option value="">Select Sub-Category</option>');
        table.ajax.reload(null,false);
      } else showAlert(res.message,'danger');
    },'json');
  });

  // Edit item
  $(document).on('click','.edit-item',function(){
    let id = $(this).data('id');
    $.post('./api/items_api.php',{action:'getItem',id:id},function(res){
      if(res.status==='success'){
        let d=res.data;
        $('#edit_id').val(d.item_id);
        $('#edit_name').val(d.item_name);
        $('#edit_item_code').val(d.item_code);
        $('#edit_UOM').val(d.uom);
        $('#edit_Tax_percentage').val(d.tax_percentage);
        $('#edit_status').val(d.status);
        $('#edit_category_id').val(d.category_id);
        $('#edit_unit_price').val(d.unit_price);
        loadSubCategories(d.category_id,$('#edit_sub_category_id'),d.subcategory_id);
        $('#editItemModal').modal('show');
      } else showAlert(res.message,'danger');
    },'json');
  });

  $('#editItemForm').submit(function(e){
    e.preventDefault();
    let formData=$(this).serializeArray();
    formData.push({name:'action',value:'edit'});
    $.post('./api/items_api.php',$.param(formData),function(res){
      if(res.status==='success'){ $('#editItemModal').modal('hide'); table.ajax.reload(null,false);}
      else showAlert(res.message,'danger');
    },'json');
  });

  $(document).on('click','.deactivate-item',function(){
    if(confirm('Deactivate this item?')){
      $.post('./api/items_api.php',{action:'deactivate',id:$(this).data('id')},function(res){
        if(res.status==='success') table.ajax.reload(null,false);
        else showAlert(res.message,'danger');
      },'json');
    }
  });

  // loadCategories function reused
  function loadCategories(sel){ $.post('./api/items_api.php',{action:'getCategories'},function(res){
    if(res.status==='success'){ let opts='<option value="">Select Category</option>'; $.each(res.data,(i,c)=>{opts+=`<option value="${c.category_id}">${c.category_name}</option>`}); sel.html(opts);}
  },'json'); }
  function loadSubCategories(cid,sel,sid=null){ if(!cid){sel.prop('disabled',true).html('<option value="">Select Sub-Category</option>');return;}
    $.post('./api/items_api.php',{action:'getSubCategories',category_id:cid},function(res){
      if(res.status==='success'){ let opts='<option value="">Select Sub-Category</option>'; $.each(res.data,(i,s)=>{let selx=(sid && sid==s.subcategory_id)?'selected':''; opts+=`<option value="${s.subcategory_id}" ${selx}>${s.subcategory_name}</option>`}); sel.prop('disabled',false).html(opts);}
    },'json'); }

  loadCategories($('#category_id')); loadCategories($('#edit_category_id'));
  $('#category_id').change(()=>loadSubCategories($('#category_id').val(),$('#sub_category_id')));
  $('#edit_category_id').change(()=>loadSubCategories($('#edit_category_id').val(),$('#edit_sub_category_id')));
});
// When button is clicked, open file dialog
$('#uploadExcelBtn').click(function(){
  $('#excelFileInput').click();
});

// When file is selected, upload automatically
$('#excelFileInput').on('change', function(){
  let file = this.files[0];
  if(!file) return;

  let formData = new FormData();
  formData.append('excel_file', file);
  formData.append('action', 'uploadExcel');

  $.ajax({
    url: './api/items_api.php',
    type: 'POST',
    data: formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function(res){
      if(res.status === 'success'){
        showAlert('✅ Excel uploaded successfully','success');
        $('#itemTable').DataTable().ajax.reload(null,false);
      } else {
        showAlert(res.message || 'Upload failed','danger');
      }
    },
    error: function(){
      showAlert('❌ Something went wrong','danger');
    }
  });
});
$('#downloadExcelBtn').click(function(){
    // Redirect to PHP script that generates the Excel
    window.location.href = './api/items_excel_format.php';
});

$('#exportExcel').click(function () {
    $.post('./api/items_api.php', { action: 'exportAll' }, function (res) {
        if (res.status === 'success') {
            // Convert JSON to Excel
            const ws = XLSX.utils.json_to_sheet(res.data);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Items");
            XLSX.writeFile(wb, "All_Items.xlsx");
        } else {
            showAlert(res.message, 'danger');
        }
    }, 'json');
});


$('#exportCsv').click(function () {
    $.post('./api/items_api.php', { action: 'exportAll' }, function (res) {
        if (res.status === 'success') {
            let csv = Papa.unparse(res.data);  // Use PapaParse
            let blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
            let link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = "All_Items.csv";
            link.click();
        } else {
            showAlert(res.message, 'danger');
        }
    }, 'json');
});

$('#printAll').click(function () {
    $.post('./api/items_api.php', { action: 'exportAll' }, function (res) {
        if (res.status === 'success') {

            let rows = res.data;

            let html = `
            <html>
            <head>
                <title>Items List</title>
                <style>
                    table { width:100%; border-collapse: collapse; font-size: 14px; }
                    th, td { border: 1px solid #777; padding: 6px; text-align: left; }
                    th { background: #e8f4ff; }
                    h2 { text-align:center; margin-bottom:20px; }
                </style>
            </head>
            <body>
                <h2>Items List</h2>
                <table>
                    <thead>
                        <tr>
                            <th>S.No</th>
                            <th>Category</th>
                            <th>Sub-Category</th>
                            <th>Item Name</th>
                            <th>Item Code</th>
                            <th>Unit Price</th>
                            <th>Tax %</th>
                            <th>UOM</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            rows.forEach((r, i) => {
                html += `
                <tr>
                    <td>${i + 1}</td>
                    <td>${r.category_name}</td>
                    <td>${r.subcategory_name}</td>
                    <td>${r.item_name}</td>
                    <td>${r.item_code}</td>
                    <td>${r.unit_price}</td>
                    <td>${r.tax_percentage}</td>
                    <td>${r.uom}</td>
                    <td>${r.status}</td>
                </tr>
                `;
            });

            html += `
                    </tbody>
                </table>
            </body>
            </html>`;

            // Open print window
            let printWindow = window.open('', '_blank');
            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            printWindow.close();

        } else {
            showAlert(res.message, 'danger');
        }
    }, 'json');
});

</script>

<script src="./js/showAlert.js"></script>
<?php require_once("footer.php"); ?>
