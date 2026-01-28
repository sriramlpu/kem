<?php
require_once("header.php");
require_once("nav.php");
?>
<style>
  #subCategoryTable { table-layout: fixed; width: 100% !important; white-space: normal; }
</style>

<section class="container-fluid section">
  <div class="row g-4">
    <div class="col-12">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-success text-white fw-bold d-flex justify-content-between align-items-center">
          <span>Branch Wise Stock Filters</span>
          <div>
            <button class="btn btn-sm btn-light text-primary" id="exportExcel">Excel</button>
            <button class="btn btn-sm btn-light text-secondary" id="exportCsv">CSV</button>
          </div>
        </div>

        <div class="card-body">
          <!-- Filters -->
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <select id="filter_category" class="form-select">
                <option value="">All Categories</option>
              </select>
            </div>
            <div class="col-md-3">
              <select id="filter_status" class="form-select">
                <option value="">All Status</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
              </select>
            </div>
            <div class="col-md-5">
              <input type="text" id="filter_search" class="form-control" placeholder="Search sub-categories">
            </div>
          </div>

          <div class="table-responsive">
            <table id="subCategoryTable" class="table table-hover align-middle mb-0 w-100">
              <thead>
                <tr class="table-primary">
                  <th style="width:80px">S.No</th>
                  <th>Category</th>
                  <th>Sub-Category Name</th>
                  <th>Sub-Category Code</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody><!-- filled by DataTables --></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once("footer.php"); ?>

<script>
$(function(){
  // Load categories for the filter
  $.post('./api/sub_categories_api.php', { action:'getCategories' }, function(res){
    if(res && res.status === 'success' && Array.isArray(res.data)){
      var $c = $('#filter_category');
      res.data.forEach(function(cat){
        $c.append('<option value="'+cat.category_id+'">'+cat.category_name+'</option>');
      });
    }
  }, 'json');

  // DataTable (removed Actions column)
  var table = $('#subCategoryTable').DataTable({
    processing: true,
    serverSide: true,
    autoWidth: false,
    dom: 'Brtip',
    buttons: [
      { extend: 'excelHtml5', className: 'buttons-excel d-none', title: 'SubCategories' },
      { extend: 'csvHtml5',   className: 'buttons-csv d-none',   title: 'SubCategories' }
    ],
    ajax: {
      url: './api/sub_categories_api.php',
      type: 'POST',
      data: function(d){
        d.action              = 'list';
        d.filter_category_id  = $('#filter_category').val();
        d.filter_status       = $('#filter_status').val();
        d.filter_search       = $('#filter_search').val();
      }
    },
    columns: [
      { data: 'sno' },
      { data: 'category_name' },
      { data: 'subcategory_name' },
      { data: 'subcategory_code' },
      { data: 'status' }
    ],
    order: [[0, 'desc']]
  });

  // Export triggers
  $('#exportExcel').on('click', function(){ table.button('.buttons-excel').trigger(); });
  $('#exportCsv').on('click',   function(){ table.button('.buttons-csv').trigger(); });

  // Filter events
  $('#filter_category, #filter_status').on('change', function(){ table.ajax.reload(); });

  // Debounced search
  var keyTimer;
  $('#filter_search').on('keyup', function(){
    clearTimeout(keyTimer);
    keyTimer = setTimeout(function(){ table.ajax.reload(); }, 300);
  });
});
</script>
