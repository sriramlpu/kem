<?php
require_once("header.php");
require_once("nav.php");
?>
<style>
  /* Keep Select2 above modals/menus */
  .select2-container--open .select2-dropdown {
    z-index: 9999;
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
        <!-- <div class="card-header bg-success text-white">Create Purchase Order</div> -->
        <div class="card-body">
          <form id="poForm">
            <div class="row g-4">
              <!-- PO Type -->
              <div class="col-md-3">
                <div class="form-floating">
                  <select id="poType" name="po_type" class="form-select select2">
                    <option value="WITHOUT INDENT">WITHOUT INDENT</option>
                    <option value="WITH INDENT">WITH INDENT</option>
                  </select>
                </div>
              </div>

              <!-- Branch (shows only branch name in dropdown) -->
              <div class="col-md-3">
                <div class="form-floating">
                  <select id="branch" name="branch_id" class="form-select select2" required>
                    <option value="">Select Branch</option>
                  </select>
                </div>
              </div>

              <!-- Indent Number -->
              <div class="col-md-3">
                <div class="form-floating">
                  <select id="indentNumber" name="indent_number" class="form-select select2" disabled>
                    <option value="">Select Indent</option>
                  </select>
                </div>
              </div>

              <!-- Vendor -->
              <!-- <div class="col-md-3">
                <div class="form-floating">
                  <select id="vendor" name="vendor_id" class="form-select select2" required>
                    <option value="">Select Vendor</option>
                  </select>
                </div>
              </div> -->

              <!-- PO Date -->
              <div class="col-md-3">
                <div class="form-floating">
                  <input type="date" id="poDate" name="po_date" class="form-control" placeholder="PO Date" required>
                  <label for="poDate">PO Date</label>
                </div>
              </div>

              <!-- Expected Date -->
              <div class="col-md-3">
                <div class="form-floating">
                  <input type="date" id="expectedDate" name="expected_delivery_date" class="form-control" placeholder="Expected Date" required>
                  <label for="expectedDate">Expected Delivery Date</label>
                </div>
              </div>

              <!-- Item Location -->
              <div class="col-md-3">
                <label class="form-label">Item Location</label><br>
                <div class="form-check form-check-inline">
                  <input type="radio" name="item_location" value="Store" id="storeLocation" class="form-check-input" checked>
                  <label for="storeLocation" class="form-check-label">Store</label>
                </div>
                <div class="form-check form-check-inline">
                  <input type="radio" name="item_location" value="Venue" id="venueLocation" class="form-check-input">
                  <label for="venueLocation" class="form-check-label">Venue</label>
                </div>
              </div>

              <!-- Delivery Address (dropdown) -->
              <div class="col-md-3" id="deliveryAddressDiv">
                <div class="form-floating">
                  <select id="deliveryAddress" name="delivery_address" class="form-select select2-address">
                    <option value="">Select Delivery Address</option>
                  </select>
                  <label for="deliveryAddress">Delivery Address</label>
                </div>
              </div>

              <!-- Venue Address (shown only for Venue) -->
              <div class="col-md-3" id="venueAddressDiv" style="display: none;">
                <div class="form-floating">
                  <textarea id="venueAddress" name="venue_location_address" class="form-control" placeholder="Venue Address" rows="2" style="height: 100px;"></textarea>
                  <label for="venueAddress">Venue Location Address</label>
                </div>
              </div>

              <!-- Billing Address (dropdown) -->
              <div class="col-md-3" id="billingAddressDiv">
                <div class="form-floating">
                  <select id="billingAddress" name="billing_address" class="form-select select2-address">
                    <option value="">Select Billing Address</option>
                  </select>
                  <label for="billingAddress">Billing Address</label>
                </div>
              </div>

              <!-- Total Amount -->
              <div class="col-md-3">
                <div class="form-floating">
                  <input type="number" step="1" id="totalAmount" name="total_amount" class="form-control" placeholder="Total Amount" readonly>
                  <label for="totalAmount">Total Amount(₹)</label>
                </div>
              </div>

              <!-- Discount Amount -->
              <div class="col-md-3">
                <div class="form-floating">
                  <input type="number" step="0.01" min="0" inputmode="decimal"
                    id="discountAmount" name="discount_amount"
                    class="form-control" placeholder="Discount Amount">
                  <label for="discountAmount">Discount Amount(₹)</label>
                </div>
              </div>

              <!-- Transportation -->
              <div class="col-md-3">
                <div class="form-floating">
                  <input type="number" step="0.01" id="transportation" name="transportation" class="form-control" placeholder="Transportation">
                  <label for="transportation">Transportation Charges(₹)</label>
                </div>
              </div>

              <!-- Remarks -->
              <div class="col-md-3">
                <div class="form-floating">
                  <textarea id="remarks" name="remarks" class="form-control" placeholder="Remarks" rows="2" style="height: 100px;"></textarea>
                  <label for="remarks">Remarks</label>
                </div>
              </div>
            </div>

            <!-- Items Section -->
            <div class="mt-4">
              <h5>Purchase Order Items</h5>
              <div class="table-responsive">
                <table class="table table-bordered" id="itemsTable">
                  <thead>
                    <tr>
                      <th>Item</th>
                      <th>Vendor</th>
                      <th>Quantity</th>
                      <th>Unit Price(₹)</th>
                      <th>Discount %</th>
                      <th>Discount Amt(₹)</th>
                      <th>Tax Percentage</th>
                      <th>Tax Amt(₹)</th>
                      <th>Subjective Amount</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody id="itemsTableBody"></tbody>
                </table>
              </div>

              <div class="d-flex justify-content-between mt-4">

                <button type="button" id="addItemBtn" class="btn btn-primary">Add Item</button>
                <div>
                  <button type="submit" class="btn btn-success">Create PO</button>
                </div>
              </div>
            </div>

          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Item Selection Modal -->
<div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Select Item</h5>
        <button type="button" id="addSelectedItemsBtn" class="btn btn-primary ms-5">Add Selected Items</button>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="itemCategory" class="form-label">Category</label>
          <select id="itemCategory" class="form-select select2">
            <option value="">Select Category</option>
          </select>
        </div>
        <div class="mb-3">
          <label for="itemSubCategory" class="form-label">Sub Category</label>
          <select id="itemSubCategory" class="form-select select2" disabled>
            <option value="">Select Sub Category</option>
          </select>
        </div>
        <div class="mb-3">
          <label for="itemSelect" class="form-label">Items</label>
          <select id="itemSelect" class="form-select select2-multi" multiple="multiple">
            <option value="">Select Item</option>
          </select>
        </div>
        <div id="selectedItemsPreview" class="mt-3" style="display: none;">
          <h6>Selected Items:</h6>
          <ul id="selectedItemsList" class="list-group list-group-flush"></ul>
        </div>
      </div>
      <!-- <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        
      </div> -->
    </div>
  </div>
</div>

<script src="./js/showAlert.js"></script>
<script>
 $(document).ready(function() {
  let itemCount = 0;
  const itemModal = new bootstrap.Modal(document.getElementById('itemModal'));
  let itemsById = {};
  let allBranchAddresses = [];

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
      data: { action: 'getAllAddresses' },
      dataType: 'json',
      success: function(res) {
        processAddressResponse(res);
      },
      error: function() {
        $.ajax({
          url: './api/branches_api.php',
          method: 'POST',
          data: { action: 'getAllAddresses' },
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
      data: { action: 'getActiveBranches' },
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
    const deliverySelect = $('#deliveryAddress');
    deliverySelect.empty().append('<option value="">Select Delivery Address</option>');

    const billingSelect = $('#billingAddress');
    billingSelect.empty().append('<option value="">Select Billing Address</option>');

    if (allBranchAddresses.length === 0) {
      const defaultOption = '<option value="" data-branch="No Address">No addresses available</option>';
      deliverySelect.append(defaultOption);
      billingSelect.append(defaultOption);
    } else {
      allBranchAddresses.forEach(function(branch) {
        const parts = [branch.address, branch.city, branch.state, branch.pincode].filter(Boolean);
        const full = parts.join(', ');
        const option = `<option value="${full}" data-branch="${branch.branch_name}">${full}</option>`;
        deliverySelect.append(option);
        billingSelect.append(option);
      });
    }

    deliverySelect.trigger('change');
    billingSelect.trigger('change');
  }

  // Initialize address loading
  loadAllBranchAddresses();

  // Toggle location
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

  // PO Type → indent enable
  $('#poType').on('change', function() {
    const selected = $(this).val();
    if (selected === 'WITH INDENT') {
      $('#indentNumber').prop('disabled', false);
      $('#addItemBtn').addClass('d-none');
    } else {
      $('#indentNumber').prop('disabled', true).val('');
      $('#addItemBtn').removeClass('d-none');
      clearItemsTable();
    }
  });

  // Branch → load indents
  $('#branch').on('change', function() {
    const branchId = $(this).val();
    const indentSelect = $('#indentNumber');
    indentSelect.empty().append('<option value="">Select Indent</option>');
    if (!branchId) return;
    $.getJSON('./api/indent_api.php?action=getIndentsByBranch&branch_id=' + branchId, function(res) {
      if (res.status === 'success') {
        res.data.forEach(function(indent) {
          indentSelect.append(`<option value="${indent.indent_id}">${indent.indent_number}</option>`);
        });
      } else {
        showAlert('Failed to load indents: ' + (res.message || 'Unknown error'), 'danger');
      }
    }).fail(function(_, __, error) {
      showAlert('Failed to load indents: ' + error, 'danger');
    });
  });

  // Indent → load items
  $('#indentNumber').on('change', function() {
    if ($('#poType').val() === 'WITH INDENT') {
      loadIndentItems();
    }
  });

  function loadIndentItems() {
    const indentId = $('#indentNumber').val();
    if (!indentId) {
      clearItemsTable();
      return;
    }
    $.ajax({
      url: './api/indent_items_api.php',
      method: 'POST',
      data: { action: 'getByIndent', indent_id: indentId },
      dataType: 'json',
      success: function(res) {
        if (res.status === 'success') {
          try {
            const items = JSON.parse(res.data);
            clearItemsTable();
            if (items.length > 0) {
              items.forEach(function(item) {
                const indentItemId = item['item_id'];
                const itemName = itemsById[indentItemId]['item_name'] || 'Unknown Item';
                const taxPercentage = itemsById[indentItemId]['tax_percentage'] || '0';
                const UOM = itemsById[indentItemId]['uom'] || 'NA';
                addItemRow({
                  item_id: item.item_id,
                  item_name: itemName,
                  quantity: item.qty_requested,
                  unit_price: 0,
                  discount_percentage: 0,
                  discount_amount: 0,
                  tax_percentage: taxPercentage,
                  tax_amount: 0,
                  subjective_amount: 0
                });
              });
            }
          } catch (e) {
            showAlert('Error parsing items.', 'danger');
          }
        } else {
          showAlert('No items found for this indent.', 'warning');
        }
      },
      error: function() {
        showAlert('Failed to load items.', 'danger');
      }
    });
  }

  function clearItemsTable() {
    $('#itemsTableBody').empty();
    itemCount = 0;
    calculateTotal();
  }

  // Load branches
  $.getJSON('./api/branches_api.php?action=getActiveBranches', function(res) {
    if (res.status === 'success') {
      const branchSelect = $('#branch');
      branchSelect.empty().append('<option value="">Select Branch</option>');
      res.data.forEach(function(branch) {
        branchSelect.append(`<option value="${branch.branch_id}">${branch.branch_name}</option>`);
      });
    }
  });

  // Load vendors for row dropdowns
  let allVendors = [];
  
  function loadVendors() {
    return $.ajax({
      url: './api/vendors_api.php',
      method: 'GET',
      data: { action: 'list' },
      dataType: 'json',
      success: function(res) {
        if (res && res.data) {
          allVendors = res.data;
          console.log('Vendors loaded:', allVendors);
        } else {
          console.error('Invalid vendor response:', res);
          showAlert('Failed to load vendors', 'warning');
        }
      },
      error: function(xhr, status, error) {
        console.error('Vendor loading error:', error);
        showAlert('Error loading vendors: ' + error, 'danger');
      }
    });
  }
  
  // Load vendors immediately
  loadVendors();

  // Load items map
  function loadAllItems() {
    $.ajax({
      url: './api/items_api.php',
      method: 'POST',
      data: { action: 'getActiveItems' },
      dataType: 'json',
      success: function(res) {
        if (res.status === 'success') {
          res.data.forEach(function(item) {
            itemsById[item.item_id] = item;
          });
        }
      }
    });
  }
  loadAllItems();

  // Add Item button
  $('#addItemBtn').on('click', function() {
    resetItemModal();
    loadCategories();
    itemModal.show();
  });

  function resetItemModal() {
    $('#itemCategory').val('').trigger('change');
    $('#itemSubCategory').val('').trigger('change');
    $('#itemSelect').val(null).trigger('change');
    $('#itemSubCategory').prop('disabled', true);
    $('#selectedItemsPreview').hide();
    $('#selectedItemsList').empty();
  }

  function loadCategories() {
    $.ajax({
      url: './api/categories_api.php',
      method: 'POST',
      data: { action: 'list' },
      dataType: 'json',
      success: function(res) {
        if (res.status === 'success') {
          const categorySelect = $('#itemCategory');
          categorySelect.empty().append('<option value="">Select Category</option>');
          res.data.forEach(function(category) {
            categorySelect.append(`<option value="${category.category_id}">${category.category_name}</option>`);
          });
          categorySelect.trigger('change');
        } else {
          showAlert('Failed to load categories.', 'danger');
        }
      },
      error: function() {
        showAlert('Error loading categories.', 'danger');
      }
    });
  }

  $('#itemCategory').on('change', function() {
    const categoryId = $(this).val();
    const subCategorySelect = $('#itemSubCategory');
    const itemSelect = $('#itemSelect');

    subCategorySelect.empty().append('<option value="">Select Sub Category</option>');
    itemSelect.empty();
    subCategorySelect.prop('disabled', true);
    $('#selectedItemsPreview').hide();
    $('#selectedItemsList').empty();

    if (!categoryId) return;

    $.ajax({
      url: './api/items_api.php',
      method: 'POST',
      data: { category_id: categoryId, action: 'getSubCategories' },
      dataType: 'json',
      success: function(res) {
        if (res.status === 'success') {
          subCategorySelect.prop('disabled', false);
          res.data.forEach(function(sub) {
            subCategorySelect.append(`<option value="${sub.subcategory_id}">${sub.subcategory_name}</option>`);
          });
          subCategorySelect.trigger('change');
        } else {
          showAlert('No subcategories found.', 'warning');
        }
      },
      error: function() {
        showAlert('Error loading subcategories.', 'danger');
      }
    });
  });

  $('#itemSubCategory').on('change', function() {
    const subCategoryId = $(this).val();
    const itemSelect = $('#itemSelect');
    itemSelect.empty();
    $('#selectedItemsPreview').hide();
    $('#selectedItemsList').empty();
    if (!subCategoryId) return;

    $.ajax({
      url: './api/items_api.php',
      method: 'POST',
      data: { subcategory_id: subCategoryId, action: 'getItemsBySubCategory' },
      dataType: 'json',
      success: function(res) {
        if (res.status === 'success') {
          res.data.forEach(function(item) {
            itemSelect.append(new Option(
              item.item_name + (item.item_code ? ' (' + item.item_code + ')' : ''),
              item.item_id
            ));
          });
          itemSelect.trigger('change');
        } else {
          showAlert('No items found for this subcategory.', 'warning');
        }
      },
      error: function() {
        showAlert('Error loading items.', 'danger');
      }
    });
  });

  $('#itemSelect').on('change', function() {
    const selectedItems = $(this).val();
    const selectedItemsList = $('#selectedItemsList');
    const selectedItemsPreview = $('#selectedItemsPreview');
    selectedItemsList.empty();
    if (selectedItems && selectedItems.length > 0) {
      selectedItemsPreview.show();
      selectedItems.forEach(function(itemId) {
        const itemText = $('#itemSelect option[value="' + itemId + '"]').text();
        selectedItemsList.append(`
          <li class="list-group-item d-flex justify-content-between align-items-center">
            ${itemText}
            <span class="badge bg-primary rounded-pill">1</span>
          </li>
        `);
      });
      $('#addSelectedItemsBtn').prop('disabled', false);
    } else {
      selectedItemsPreview.hide();
      $('#addSelectedItemsBtn').prop('disabled', true);
    }
  });

  $('#addSelectedItemsBtn').on('click', function() {
    const selectedItems = $('#itemSelect').val();
    if (!selectedItems || selectedItems.length === 0) {
      showAlert('Please select at least one item.', 'warning');
      return;
    }
    selectedItems.forEach(function(itemId) {
      const ItemName = itemsById[itemId]['item_name'] || 'Unknown Item';
      const itemtaxPercentage = itemsById[itemId]['tax_percentage'] || '0';
      const itemUOM = itemsById[itemId]['uom'] || 'NA';
      if ($(`#itemsTableBody tr[data-item-id="${itemId}"]`).length === 0) {
        const itemOption = $('#itemSelect option[value="' + itemId + '"]');
        const itemName = itemOption.text();
        addItemRow({
          item_id: itemId,
          item_name: itemName,
          quantity: 1,
          unit_price: 0,
          discount_percentage: 0,
          discount_amount: 0,
          tax_percentage: itemtaxPercentage,
          tax_amount: 0,
          subjective_amount: 0
        });
      }
    });
    itemModal.hide();
  });

  function addItemRow(item = {}) {
    const itemId = item.item_id || '';
    const itemName = item.item_name || '';

    itemCount++;
    const row = $(`
      <tr data-item-id="${item.item_id}">
        <td>${item.item_name}<input type="hidden" name="items[${itemCount}][item_id]" value="${item.item_id}"></td>
        <td>
          <select name="items[${itemCount}][vendor_id]" class="form-select item-vendor select2" required>
            <option value="">Select Vendor</option>
          </select>
        </td>
        <td><input type="number" step="0.01" name="items[${itemCount}][quantity]" class="form-control item-quantity" value="${item.quantity}" required></td>
        <td><input type="number" step="0.01" name="items[${itemCount}][unit_price]" class="form-control item-unit-price" value="0" required></td>
        <td><input type="number" step="0.01" name="items[${itemCount}][discount_percentage]" class="form-control item-discount-pct" value="0"></td>
        <td><input type="number" step="0.01" name="items[${itemCount}][discount_amount]" class="form-control item-discount-amt" value="0" readonly></td>
        <td><input type="number" step="0.01" name="items[${itemCount}][tax_percentage]" class="form-control item-tax-pct" value="${item.tax_percentage}"></td>
        <td><input type="number" step="0.01" name="items[${itemCount}][tax_amount]" class="form-control item-tax-amt" value="0" readonly></td>
        <td><input type="number" step="0.01" name="items[${itemCount}][subjective_amount]" class="form-control item-line-total" value="0" readonly></td>
        <td>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-danger btn-sm btn-split" title="Split">
              <i class="bi bi-scissors"></i>
            </button>
            <button type="button" class="btn btn-danger btn-sm remove-item" title="Remove">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </td>
      </tr>
    `);

    const vendorDropdown = row.find('.item-vendor');
    allVendors.forEach(vendor => {
      vendorDropdown.append(`<option value="${vendor.vendor_id}">${vendor.vendor_name}</option>`);
    });

    // Split button logic
    row.find('.btn-split').on('click', function() {
      const currentVendor = vendorDropdown.val();
      const usedVendors = [];

      $(`#itemsTableBody tr[data-item-id="${itemId}"]`).each(function() {
        const v = $(this).find('.item-vendor').val();
        if (v) usedVendors.push(v);
      });

      const currentRow = $(this).closest('tr');
      const newItemData = {
        item_id: itemId,
        item_name: itemName,
        quantity: 0,
        unit_price: currentRow.find('.item-unit-price').val(),
        discount_percentage: currentRow.find('.item-discount-pct').val(),
        discount_amount: currentRow.find('.item-discount-amt').val(),
        tax_percentage: currentRow.find('.item-tax-pct').val(),
        tax_amount: currentRow.find('.item-tax-amt').val(),
        subjective_amount: currentRow.find('.item-line-total').val()
      };

      const newRow = addItemRow(newItemData);

      newRow.find('.item-vendor option').each(function() {
        if (usedVendors.includes($(this).val())) {
          $(this).prop('disabled', true);
        }
      });
    });

    $('#itemsTableBody').append(row);
    
    // Initialize Select2 for the vendor dropdown in this row
    row.find('.item-vendor').select2({
      width: '100%',
      placeholder: 'Select Vendor',
      allowClear: true
    });
    
    return row;
  }

  $(document).on('click', '.remove-item', function() {
    $(this).closest('tr').remove();
    calculateTotal();
  });

  // ============ CALCULATION LOGIC (FIXED) ============
  
  // Helper function to clamp values
  function clamp(num, min, max) {
    return Math.min(Math.max(num, min), max);
  }

  // Update discount amount based on percentage
  function updateDiscountAmount(row) {
    const q = parseFloat(row.find('.item-quantity').val()) || 0;
    const up = parseFloat(row.find('.item-unit-price').val()) || 0;
    let pct = parseFloat(row.find('.item-discount-pct').val()) || 0;
    pct = clamp(pct, 0, 100);
    const base = q * up;
    const amt = (base * pct) / 100;
    row.find('.item-discount-amt').val(amt.toFixed(2));
  }

  // Update discount percentage based on amount
  function updateDiscountPercentage(row) {
    const q = parseFloat(row.find('.item-quantity').val()) || 0;
    const up = parseFloat(row.find('.item-unit-price').val()) || 0;
    const amt = parseFloat(row.find('.item-discount-amt').val()) || 0;
    const base = q * up;
    let pct = 0;
    if (base > 0) pct = (amt * 100) / base;
    row.find('.item-discount-pct').val(clamp(pct, 0, 100).toFixed(2));
  }

  // Update tax amount based on tax percentage
  function updateTaxAmount(row) {
    const q = parseFloat(row.find('.item-quantity').val()) || 0;
    const up = parseFloat(row.find('.item-unit-price').val()) || 0;
    const taxRate = parseFloat(row.find('.item-tax-pct').val()) || 0;
    const dam = parseFloat(row.find('.item-discount-amt').val()) || 0;
    
    let base = (q * up) - dam;
    if (base < 0) base = 0;
    const taxAmt = (base * taxRate) / 100;
    row.find('.item-tax-amt').val(taxAmt.toFixed(2));
  }

  // Calculate line total for a specific row
  function calculateLineTotal(row) {
    const q = parseFloat(row.find('.item-quantity').val()) || 0;
    const up = parseFloat(row.find('.item-unit-price').val()) || 0;
    const dam = parseFloat(row.find('.item-discount-amt').val()) || 0;
    const tam = parseFloat(row.find('.item-tax-amt').val()) || 0;

    const discounted = (q * up) - dam;
    const lineTotal = Math.max(0, discounted) + Math.max(0, tam);
    row.find('.item-line-total').val(lineTotal.toFixed(2));
    
    calculateTotal();
  }

  // Calculate overall total
  function calculateTotal(opts) {
    const normalize = !!(opts && opts.normalize);
    let total = 0;
    let totalDiscount = 0;
    
    $('#itemsTableBody tr').each(function() {
      total += parseFloat($(this).find('.item-line-total').val()) || 0;
      totalDiscount += parseFloat($(this).find('.item-discount-amt').val()) || 0;
    });
    
    const transportation = parseFloat($('#transportation').val()) || 0;
    $('#totalAmount').val(total.toFixed(2));
    $('#discountAmount').val(totalDiscount.toFixed(2));
  }

  // Event handlers for row calculations
  $(document).on('input', '.item-quantity, .item-unit-price', function() {
    const row = $(this).closest('tr');
    updateDiscountAmount(row);
    updateTaxAmount(row);
    calculateLineTotal(row);
  });

  $(document).on('input', '.item-discount-pct', function() {
    const row = $(this).closest('tr');
    updateDiscountAmount(row);
    updateTaxAmount(row);
    calculateLineTotal(row);
  });

  $(document).on('input', '.item-discount-amt', function() {
    const row = $(this).closest('tr');
    updateDiscountPercentage(row);
    updateTaxAmount(row);
    calculateLineTotal(row);
  });

  $(document).on('input', '.item-tax-pct', function() {
    const row = $(this).closest('tr');
    updateTaxAmount(row);
    calculateLineTotal(row);
  });

  $('#discountAmount, #transportation').on('input', function() {
    calculateTotal({ normalize: false });
  });

  $('#discountAmount, #transportation').on('blur change', function() {
    calculateTotal({ normalize: true });
  });

  // ============ END CALCULATION LOGIC ============

  // Submit form
  $('#poForm').on('submit', function(e) {
    e.preventDefault();
    calculateTotal({ normalize: true });

    if ($('#itemsTableBody tr').length === 0) {
      showAlert('Please add at least one item to the purchase order.', 'warning');
      return;
    }

    // Validate that all items have vendors selected
    let missingVendor = false;
    $('#itemsTableBody tr').each(function() {
      if (!$(this).find('.item-vendor').val()) {
        missingVendor = true;
        return false; // break loop
      }
    });

    if (missingVendor) {
      showAlert('Please select a vendor for all items.', 'warning');
      return;
    }

    const formData = new FormData(this);
    formData.append('action', 'create');

    $.ajax({
      url: './api/purchase_order_api.php',
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      dataType: 'json',
      success: function(response) {
        if (response.status === 'success') {
          let message = response.message;
          if (response.purchase_orders && response.purchase_orders.length > 0) {
            message += '<br><br><strong>Created POs:</strong><br>';
            response.purchase_orders.forEach(function(po) {
              message += `• ${po.po_number} (${po.item_count} items, ₹${po.total_amount.toFixed(2)})<br>`;
            });
          }
          showAlert(message, 'success');
          $('#poForm')[0].reset();
          clearItemsTable();
          $('#poDate').val(new Date().toISOString().split('T')[0]);
        } else {
          showAlert('Error: ' + response.message, 'danger');
        }
      },
      error: function(_, __, error) {
        showAlert('Failed to create Purchase Order: ' + error, 'danger');
      }
    });
  });
});
</script>

<script src="./js/showAlert.js"></script>
<?php
require_once("footer.php");
?>