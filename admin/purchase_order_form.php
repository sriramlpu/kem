<?php
require_once("header.php");
require_once("nav.php");
require_once("../functions.php");

// Fetch dropdowns
$branches = exeSql("SELECT branch_id, branch_name FROM branches");
$vendors  = exeSql("SELECT vendor_id, vendor_name FROM vendors");
$po_id = $_POST['po_id'] ?? null;

if ($po_id) {
  $po = getSubject("purchase_orders", "po_id=$po_id")[0];
  $items = exeSql("SELECT * FROM purchase_order_items WHERE po_id=$po_id");
} else {
  $po = null;
  $items = [];
}
?>
<style>
  @media print {
    body {
      -webkit-print-color-adjust: exact;
      /* for colors */
    }

    /* Hide elements that should not appear in print */
    .no-print {
      display: none !important;
    }
  }
</style>
<section class="container-fluid section">
  <div class="row g-4">
    <div class="col-md-12">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
          <span>Create Purchase Order</span>
          <a href="po_report.php" class="btn btn-danger">View Purchase Orders</a>
        </div>
        <div class="card-body">
          <form id="poForm">
            <input type="hidden" name="po_id" value="<?= $po_id ?? '' ?>">
            <input type="hidden" id="savedDelivery" value="<?= $po['delivery_address'] ?? '' ?>">
            <input type="hidden" id="savedBilling" value="<?= $po['billing_address'] ?? '' ?>">

            <div class="row mb-3">
              <div class="col-md-4">
                <label>PO Number</label>
                <input type="text" name="po_number" class="form-control"
                  value="<?= $po['order_number'] ?? '' ?>" readonly>
              </div>
              <div class="col-md-4">
                <label>Branch</label>
                <select name="branch_id_display" class="form-select" disabled>
                  <option value="">Select Branch</option>
                  <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['branch_id'] ?>" <?= isset($po['branch_id']) && $po['branch_id'] == $b['branch_id'] ? 'selected' : '' ?>>
                      <?= $b['branch_name'] ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <input type="hidden" name="branch_id" value="<?= $po['branch_id'] ?? '' ?>">
              </div>
              <div class="col-md-4">
                <label>Vendor</label>
                <select name="vendor_id_display" class="form-select">
                  <option value="">Select Vendor</option>
                  <?php foreach ($vendors as $v): ?>
                    <option value="<?= $v['vendor_id'] ?>" <?= isset($po['vendor_id']) && $po['vendor_id'] == $v['vendor_id'] ? 'selected' : '' ?>>
                      <?= $v['vendor_name'] ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <input type="hidden" name="vendor_id" value="<?= $po['vendor_id'] ?? '' ?>">
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-3">
                <label>PO Date</label>
                <input type="date" name="po_date" class="form-control" value="<?= $po['po_date'] ?? '' ?>">
              </div>
              <div class="col-md-3">
                <label>Expected Delivery</label>
                <input type="date" name="expected_delivery_date" class="form-control" value="<?= $po['expected_delivery_date'] ?? '' ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Item Location</label><br>
                <div class="form-check form-check-inline">
                  <input type="radio" name="item_location" value="Store" id="storeLocation" class="form-check-input" <?= ($po['item_location'] == "Store") ? "checked" : "" ?>>
                  <label for="storeLocation" class="form-check-label">Store</label>
                </div>
                <div class="form-check form-check-inline">
                  <input type="radio" name="item_location" value="Venue" id="venueLocation" class="form-check-input" <?= ($po['item_location'] == "Venue") ? "checked" : "" ?>>
                  <label for="venueLocation" class="form-check-label">Venue</label>
                </div>
              </div>
              <!-- Delivery Address (dropdown) -->
              <div class="col-md-3" id="deliveryAddressDiv">
                <label for="deliveryAddress">Delivery Address</label>
                <select id="deliveryAddress" name="delivery_address" class="form-select select2-address">
                  <option value="">Select Delivery Address</option>
                </select>
              </div>

              <!-- Venue Address -->
              <div class="col-md-3" id="venueAddressDiv" style="display: none;">
                <label for="venueAddress">Venue Location Address</label>
                <textarea id="venueAddress" name="venue_location_address" class="form-control" placeholder="Venue Address" rows="2" style="height: 100px;"><?= $po['venue_location_address'] ?></textarea>
              </div>
            </div>
            <div class="row mb-3">



              <!-- Billing Address -->
              <div class="col-md-3" id="billingAddressDiv">
                <label for="billingAddress">Billing Address</label>
                <select id="billingAddress" name="billing_address" class="form-select select2-address">
                  <option value="">Select Billing Address</option>
                </select>
              </div>
              <div class="col-md-3">
                <label>Total Amount (₹)</label>
                <input type="number" step="any" name="total_amount" class="form-control" value="<?= $po['total_amount'] ?? 0 ?>" readonly>
              </div>
              <div class="col-md-3">
                <label>Transportation (₹)</label>
                <input type="number" step="any" name="transportation" class="form-control" value="<?= $po['transportation'] ?? 0 ?>" readonly>
              </div>

              <!-- Remarks -->
              <div class="col-md-3">
                <label for="remarks">Remarks</label>
                <textarea id="remarks" name="remarks" class="form-control" placeholder="Remarks" rows="2" style="height: 100px;"><?= $po['remarks'] ?? '' ?></textarea>
              </div>
            </div>

            <h5>Items</h5>
            <table class="table table-bordered" id="itemsTable">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Qty</th>
                  <th>Unit Price</th>
                  <th>Discount ₹</th>
                  <th>Tax %</th>
                  <th>Tax ₹</th>
                  <th>Subtotal ₹</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="itemsTableBody">
                <?php
                $i = 0;
                foreach ($items as $it): $i++;
                ?>
                  <tr data-item-id="<?= $it['item_id'] ?>">
                    <td><?= getField("items", $it['item_id'], "item_name", "item_id"); ?>
                      <input type="hidden" name="items[<?= $i ?>][item_id]" value="<?= $it['item_id'] ?>">
                    </td>
                    <td><input type="number" step="any" name="items[<?= $i ?>][quantity]" class="form-control item-quantity" value="<?= $it['quantity'] ?>"></td>
                    <td><input type="number" step="any" name="items[<?= $i ?>][unit_price]" class="form-control item-unit-price" value="<?= $it['unit_price'] ?>"></td>
                    <td><input type="number" step="any" name="items[<?= $i ?>][discount_amount]" class="form-control item-discount-amt" value="<?= $it['discount_amount'] ?>"></td>
                    <td><input type="number" step="any" name="items[<?= $i ?>][tax_percentage]" class="form-control item-tax-pct" value="<?= $it['tax_percentage'] ?>"></td>
                    <td><input type="number" step="any" name="items[<?= $i ?>][tax_amount]" class="form-control item-tax-amt" value="<?= $it['tax_amount'] ?>"></td>
                    <td><input type="number" step="any" name="items[<?= $i ?>][subjective_amount]" class="form-control item-sub-amt" value="<?= $it['subjective_amount'] ?>"></td>
                    <td><button type="button" class="btn btn-danger btn-sm removeItemBtn">Remove</button></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>

            <button type="button" id="addItemBtn" class="btn btn-primary">Add Item</button>
            <button type="submit" class="btn btn-success">Update PO</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Big Item Selection Modal -->
<div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Select Items</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <!-- Category -->
          <div class="col-md-4">
            <label for="itemCategory" class="form-label">Category</label>
            <select id="itemCategory" class="form-select select2">
              <option value="">Select Category</option>
            </select>
          </div>
          <!-- Sub Category -->
          <div class="col-md-4">
            <label for="itemSubCategory" class="form-label">Sub Category</label>
            <select id="itemSubCategory" class="form-select select2" disabled>
              <option value="">Select Sub Category</option>
            </select>
          </div>
        </div>

        <hr>
        
        <!-- Search Items -->
<div class="row g-3 mb-2">
  <div class="col-md-4">
    <input type="text" id="modalItemSearch" class="form-control" placeholder="Search items ...">
  </div>
</div>

        <!-- Items Table -->
        <div class="table-responsive" style="max-height:400px; overflow:auto;">
          <table class="table table-bordered table-hover" id="modalItemsTable">
            <thead class="table-light">
              <tr>
                <th><input type="checkbox" id="selectAllItems"></th>
                <th>Item Name</th>
                <th>Item Code</th>
                <th>Tax</th>
                <th>UOM</th>
              </tr>
            </thead>
            <tbody id="modalItemsTableBody"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="addCheckedItemsBtn" class="btn btn-primary">Add Selected Items</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>


<script>
// after $(document).ready(function() { ... ) start
// create a Bootstrap Modal instance for #itemModal
const itemModalEl = document.getElementById('itemModal');
let itemModal = null;

if (itemModalEl && window.bootstrap && typeof window.bootstrap.Modal === 'function') {
  itemModal = new bootstrap.Modal(itemModalEl);
} else {
  // fallback for older bootstrap jQuery plugin (if you still have it)
  itemModal = {
    show: function(){ $('#itemModal').modal('show'); },
    hide: function(){ $('#itemModal').modal('hide'); }
  };
}

  $(document).ready(function() {
    let itemCount = $('#itemsTableBody tr').length;
    let itemsById = {};

    // Load all items map
    function loadAllItems(callback) {
      $.ajax({
        url: './api/items_api.php',
        method: 'POST',
        data: {
          action: 'getActiveItems'
        },
        dataType: 'json',
        success: function(res) {
          if (res.status === 'success') {
            res.data.forEach(item => itemsById[item.item_id] = item);
            if (callback) callback();
          }
        }
      });
    }
    loadAllItems();

    function recalcTotal() {
      let total = 0;
      $('.item-sub-amt').each(function() {
        total += parseFloat($(this).val()) || 0;
      });
      $('input[name="total_amount"]').val(total.toFixed(2));
    }



    function updateTaxAndTotal(row) {
      const q = parseFloat(row.find('.item-quantity').val()) || 0;
      const up = parseFloat(row.find('.item-unit-price').val()) || 0;
      const dam = parseFloat(row.find('.item-discount-amt').val()) || 0;
      const tp = parseFloat(row.find('.item-tax-pct').val()) || 0;
      const taxable = (q * up) - dam;
      const taxAmt = (taxable * tp) / 100;
      row.find('.item-tax-amt').val(taxAmt.toFixed(2));
      row.find('.item-sub-amt').val((taxable + taxAmt).toFixed(2));
    }

    function addItemRow(data) {
      itemCount++;
      $('#itemsTableBody').append(`
            <tr data-item-id="${data.item_id}">
                <td>${data.item_name}<input type="hidden" name="items[${itemCount}][item_id]" value="${data.item_id}"></td>
                <td><input type="number" step="any" name="items[${itemCount}][quantity]" class="form-control item-quantity" value="${data.quantity}"></td>
                <td><input type="number" step="any" name="items[${itemCount}][unit_price]" class="form-control item-unit-price" value="${data.unit_price}"></td>
                <td><input type="number" step="any" name="items[${itemCount}][discount_amount]" class="form-control item-discount-amt" value="${data.discount_amount}"></td>
                <td><input type="number" step="any" name="items[${itemCount}][tax_percentage]" class="form-control item-tax-pct" value="${data.tax_percentage}"></td>
                <td><input type="number" step="any" name="items[${itemCount}][tax_amount]" class="form-control item-tax-amt" value="${data.tax_amount}"></td>
                <td><input type="number" step="any" name="items[${itemCount}][subjective_amount]" class="form-control item-sub-amt" value="${data.subjective_amount}"></td>
                <td><button type="button" class="btn btn-danger btn-sm removeItemBtn">Remove</button></td>
            </tr>
        `);
    }

    // Add item button
    $('#addItemBtn').click(function() {
      itemModal.show();
      loadCategories();
    });

    $(document).on('click', '.removeItemBtn', function() {
      $(this).closest('tr').remove();
      recalcTotal();
    });

    // Input calculations
    $(document).on('input change', '.item-quantity, .item-unit-price, .item-discount-amt, .item-tax-pct', function() {
      const row = $(this).closest('tr');
      updateTaxAndTotal(row);
      recalcTotal();
    });
    $(document).on('input change', '.item-discount-amt', function() {
      const row = $(this).closest('tr');
      updateDiscountPercentage(row);
      updateTaxAndTotal(row);
      recalcTotal();
    });

    // Form submit
    $('#poForm').submit(function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      formData.append('action', 'update');
      $.ajax({
        url: './api/purchase_order_api.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(res) {
          if (res.status === 'success') {
            showAlert('PO updated successfully', 'success');
            location.reload();
          } else {
            showAlert(res.message, 'danger');
          }
        },
        error: function(_, __, err) {
          showAlert('Failed: ' + err, 'danger');
        }
      });
    });

    // // Modal functions
    // function resetItemModal() {
    //     $('#itemCategory').val('').trigger('change');
    //     $('#itemSubCategory').val('').trigger('change').prop('disabled', true);
    //     $('#itemSelect').val(null).trigger('change');
    //     $('#selectedItemsPreview').hide();
    //     $('#selectedItemsList').empty();
    // }

    // Load categories
    function loadCategories() {
      $.ajax({
        url: './api/categories_api.php',
        method: 'POST',
        data: {
          action: 'list'
        },
        dataType: 'json',
        success: function(res) {
          if (res.status === 'success') {
            const categorySelect = $('#itemCategory');
            categorySelect.empty().append('<option value="">Select Category</option>');
            res.data.forEach(cat => categorySelect.append(`<option value="${cat.category_id}">${cat.category_name}</option>`));
          }
        }
      });
    }

    // Load subcategories
    $('#itemCategory').on('change', function() {
      const catId = $(this).val();
      const subCatSelect = $('#itemSubCategory');
      subCatSelect.empty().append('<option value="">Select Sub Category</option>').prop('disabled', true);
      $('#modalItemsTableBody').empty();
      $('#selectAllItems').prop('checked', false);
      if (!catId) return;

      $.ajax({
        url: './api/items_api.php',
        method: 'POST',
        data: {
          category_id: catId,
          action: 'getSubCategories'
        },
        dataType: 'json',
        success: function(res) {
          if (res.status === 'success') {
            subCatSelect.prop('disabled', false);
            res.data.forEach(sub => subCatSelect.append(`<option value="${sub.subcategory_id}">${sub.subcategory_name}</option>`));
          }
        }
      });
    });

    // Load items by subcategory
    $('#itemSubCategory').on('change', function() {
      const subId = $(this).val();
      const tableBody = $('#modalItemsTableBody');
      tableBody.empty();
      $('#selectAllItems').prop('checked', false);
      if (!subId) return;

      $.ajax({
        url: './api/items_api.php',
        method: 'POST',
        data: {
          subcategory_id: subId,
          action: 'getItemsBySubCategory'
        },
        dataType: 'json',
        success: function(res) {
          if (res.status === 'success') {
            res.data.forEach(item => {
              itemsById[item.item_id] = item;
              const row = `<tr data-item-id="${item.item_id}">
              <td><input type="checkbox" class="item-check"></td>
              <td>${item.item_name}</td>
              <td>${item.item_code || ''}</td>
              <td>${item.tax_percentage || 0}</td>
              <td>${item.uom || ''}</td>
            </tr>`;
              tableBody.append(row);
              // Search inside modal items table
$('#modalItemSearch').on('keyup', function() {
    const search = $(this).val().toLowerCase();

    $('#modalItemsTableBody tr').each(function() {
        const rowText = $(this).text().toLowerCase();
        $(this).toggle(rowText.includes(search));
    });
});
            });
          }
        }
      });
    });

    // Select All checkbox
    $('#selectAllItems').on('change', function() {
      const checked = $(this).is(':checked');
      $('#modalItemsTableBody .item-check').prop('checked', checked);
    });

    $('#addCheckedItemsBtn').on('click', function() {
      $('#modalItemsTableBody .item-check:checked').each(function() {
        const row = $(this).closest('tr');
        const itemId = row.data('item-id');
        const item = itemsById[itemId];

        // Avoid duplicates
        if ($(`#itemsTableBody tr[data-item-id="${itemId}"]`).length === 0) {
          addItemRow({
            item_id: itemId,
            item_name: item.item_name,
            quantity: 1,
            unit_price: 0,
            discount_percentage: 0,
            discount_amount: 0,
            tax_percentage: item.tax_percentage || 0,
            tax_amount: 0,
            subjective_amount: 0
          });
        }
      });
      itemModal.hide();
    });
    $('#itemSelect').change(function() {
      const sel = $(this).val();
      const list = $('#selectedItemsList');
      list.empty();
      if (sel && sel.length > 0) {
        $('#selectedItemsPreview').show();
        sel.forEach(id => list.append(`<li class="list-group-item d-flex justify-content-between align-items-center">${$('#itemSelect option[value="'+id+'"]').text()}<span class="badge bg-primary rounded-pill">1</span></li>`));
        $('#addSelectedItemsBtn').prop('disabled', false);
      } else {
        $('#selectedItemsPreview').hide();
        $('#addSelectedItemsBtn').prop('disabled', true);
      }
    });

    $('#addSelectedItemsBtn').click(function() {
      const selected = $('#itemSelect').val();
      if (!selected || selected.length === 0) {
        showAlert('Select at least 1 item', 'warning');
        return;
      }
      selected.forEach(id => {
        const data = itemsById[id] || {
          item_name: 'Unknown',
          tax_percentage: 0,
          quantity: 1,
          unit_price: 0,
          discount_percentage: 0,
          discount_amount: 0,
          tax_amount: 0,
          subjective_amount: 0
        };
        if ($(`#itemsTableBody tr[data-item-id="${id}"]`).length === 0) addItemRow({
          item_id: id,
          item_name: data.item_name,
          quantity: 1,
          unit_price: 0,
          discount_percentage: 0,
          discount_amount: 0,
          tax_percentage: data.tax_percentage,
          tax_amount: 0,
          subjective_amount: 0
        });
      });
      itemModal.hide();
    });
  });

  function toggleLocation() {
    if ($('#storeLocation').is(':checked')) {
      $('#deliveryAddressDiv').show();
      $('#venueAddressDiv').hide();
    } else {
      $('#deliveryAddressDiv').hide();
      $('#venueAddressDiv').show();
    }
  }
  $('input[name="item_location"]').on('change', toggleLocation);
  toggleLocation();
  let allBranchAddresses = [];

  let itemsById = {}; // Loaded items
  let allVendors = []; // Vendors loaded
  let itemCount = 0; // For PO table rows

  // Default PO date
  $('#poDate').val(new Date().toISOString().split('T')[0]);

  // Format address for dropdown display
  function formatAddress(address) {
    if (!address.id) return address.text;
    const $container = $(
      '<div class="address-option">' +
      '<strong>' + address.element.getAttribute('data-branch') + '</strong><br>' +
      '<small>' + address.text + '</small>' +
      '</div>'
    );
    return $container;
  }

  // Format selected address
  function formatAddressSelection(address) {
    if (!address.id) return address.text;
    return address.element.getAttribute('data-branch') + ' - ' + address.text;
  }

  // Load all branch addresses
  function loadAllBranchAddresses() {
    $.ajax({
      url: './api/branches_api.php',
      method: 'GET',
      data: {
        action: 'getAllAddresses'
      },
      dataType: 'json',
      success: function(res) {
        processAddressResponse(res);
      },
      error: function() {
        $.ajax({
          url: './api/branches_api.php',
          method: 'POST',
          data: {
            action: 'getAllAddresses'
          },
          dataType: 'json',
          success: function(res) {
            processAddressResponse(res);
          },
          error: function() {
            loadBranchesDirectly();
          }
        });
      }
    });
  }

  // Process address response
  function processAddressResponse(res) {
    if (res && res.status === 'success' && res.data) {
      allBranchAddresses = res.data;
      populateAddressDropdowns();
    } else if (Array.isArray(res)) {
      allBranchAddresses = res;
      populateAddressDropdowns();
    } else {
      showAlert('Error: Unexpected response format when loading addresses', 'warning');
      loadBranchesDirectly();
    }
  }

  // Load branches directly as fallback
  function loadBranchesDirectly() {
    $.ajax({
      url: './api/branches_api.php',
      method: 'GET',
      data: {
        action: 'getActiveBranches'
      },
      dataType: 'json',
      success: function(res) {
        if (res && res.status === 'success' && res.data) {
          allBranchAddresses = res.data.map(branch => ({
            branch_id: branch.branch_id,
            branch_name: branch.branch_name,
            address: branch.address || '',
            city: branch.city || '',
            state: branch.state || '',
            pincode: branch.pincode || ''
          }));
          populateAddressDropdowns();
        } else {
          showAlert('Could not load branch addresses', 'warning');
        }
      },
      error: function() {
        showAlert('Could not load branch addresses', 'warning');
      }
    });
  }

  // Populate address dropdowns
  function populateAddressDropdowns() {

    let savedDelivery  = $('#savedDelivery').val();
    let savedBilling   = $('#savedBilling').val();

    const deliverySelect = $('#deliveryAddress');
    const billingSelect  = $('#billingAddress');

    deliverySelect.empty().append('<option value="">Select Delivery Address</option>');
    billingSelect.empty().append('<option value="">Select Billing Address</option>');

    if (allBranchAddresses.length === 0) {
        const defaultOption = '<option value="" data-branch="No Address">No addresses available</option>';
        deliverySelect.append(defaultOption);
        billingSelect.append(defaultOption);
    } else {
        allBranchAddresses.forEach(function(branch) {
            const parts = [branch.address, branch.city, branch.state, branch.pincode].filter(Boolean);
            const full  = parts.join(', ');

            const opt = `<option value="${branch.branch_id}" data-branch="${branch.branch_name}">${full}</option>`;

            deliverySelect.append(opt);
            billingSelect.append(opt);
        });
    }

    // ⭐ PRESELECT SAVED VALUES
    if (savedDelivery)  deliverySelect.val(savedDelivery);
    if (savedBilling)   billingSelect.val(savedBilling);

    // Refresh select2
    deliverySelect.trigger('change');
    billingSelect.trigger('change');
}


  // Initialize address loading
  loadAllBranchAddresses();
</script>

<script src="./js/showAlert.js"></script>
<?php require_once("footer.php"); ?>