<?php
require_once("header.php");
require_once("nav.php");
require_once("../functions.php"); // DB functions
// print_r($_REQUEST);
// Check if editing
$grn_id = $_POST['grn_id'] ?? null;
$editMode = !empty($grn_id);
$today = date('Y-m-d');
$grn = [];
$items = [];
if ($editMode) {
    $grn = exeSql("SELECT g.*, v.vendor_name, po.order_number, po.vendor_id
                   FROM goods_receipts g
                   JOIN vendors v ON g.vendor_id = v.vendor_id
                   JOIN purchase_orders po ON g.po_id = po.po_id
                   WHERE g.grn_id = $grn_id")[0] ?? null;
                    // print_r($grn);
                
    if (!$grn) {
        echo "<div class='alert alert-danger'>GRN not found.</div>";
        exit;
    }

    $items = exeSql("SELECT gri.*, i.item_name
                     FROM goods_receipt_items gri
                     LEFT JOIN items i ON gri.item_id = i.item_id
                     WHERE gri.grn_id = $grn_id");
    // $branchId = exeSql("SELECT DISTINCT branch_id FROM goods_receipt_items WHERE grn_id = $grn_id")[0]['branch_id'] ?? null;
    // $branchName = $branchId ? getFieldId('branches', 'branch_id', $branchId, 'branch_name') : '';
}
?>

<section class="section">
    <div class="row g-4">
        <div class="col-md-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <span><?= $editMode ? "Edit GRN" : "Create GRN" ?></span>
                    <a href="grn_report.php" class="btn btn-secondary btn-sm">Back</a>
                </div>
                <div class="card-body">
                    <form id="grnForm" enctype="multipart/form-data">
                        <?php if($editMode): ?>
                            <input type="hidden" name="grn_id" value="<?= htmlspecialchars($grn_id) ?>">
                        <?php endif; ?>

                        <div class="row g-4">

                           <!-- Vendor -->
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <select id="branch" name="branch_id" class="form-select select2" readonly>
                                        <option value="">Select Branch</option>
                                    </select>
                                    <!-- <label for="vendor">Vendor</label> -->
                                </div>
                            </div>

                            <!-- Vendor -->
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <select id="vendor" name="vendor_id" class="form-select select2" readonly>
                                        <option value="">Select Vendor</option>
                                    </select>
                                    <!-- <label for="vendor">Vendor</label> -->
                                </div>
                            </div>

                            <!-- Purchase Order -->
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <select id="purchaseOrder" name="po_id" class="form-select select2" readonly>
                                        <option value="">Select PO</option>
                                    </select>
                                    <!-- <label for="purchaseOrder">Purchase Order</label> -->
                                </div>
                            </div>

                            <!-- GRN Date -->
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="date" id="grnDate" name="grn_date" class="form-control" max="<?= $today ?>">
                                    <label for="grnDate">GRN Date</label>
                                </div>
                            </div>

                            <!-- Invoice Date -->
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="date" id="invoiceDate" name="invoice_date" class="form-control" max="<?= $today ?>">
                                    <label for="invoiceDate">Invoice Date</label>
                                </div>
                            </div>

                            <!-- Invoice Number -->
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="text" id="invoiceNumber" name="invoice_number" class="form-control">
                                    <label for="invoiceNumber">Invoice Number</label>
                                </div>
                            </div>

                            <!-- Transportation -->
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="number" step="any" id="transportation" name="transportation" class="form-control" placeholder="Transportation Charges">
                                    <label for="transportation">Transportation Charges</label>
                                </div>
                            </div>

                            <!-- Total Amount -->
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="number" id="totalAmount" name="total_amount" class="form-control" readonly>
                                    <label for="totalAmount">Total Amount</label>
                                </div>
                            </div>

                            <!-- Discount Amount -->
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="number" step="any" id="discountAmount" name="discount_amount" class="form-control" readonly>
                                    <label for="discountAmount">Discount Amount</label>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="number" step="any" id="taxAmount" name="tax_amount" class="form-control" readonly>
                                    <label for="taxAmount">Tax Amount</label>
                                </div>
                            </div>

                            <!-- Remarks -->
                            <div class="col-md-12">
                                <div class="form-floating">
                                    <textarea id="remarks" name="remarks" class="form-control"><?= $editMode ? htmlspecialchars($grn['remarks']) : '' ?></textarea>
                                    <label for="remarks">Remarks</label>
                                </div>
                            </div>
                        </div>

                        <!-- Items header with add button -->
                        <div class="d-flex justify-content-between align-items-center mt-4">
                          <h5>Items</h5>
                          <div>
                            <button type="button" id="openItemModalBtn" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#itemModal">
                              + Add Items
                            </button>
                          </div>
                        </div>

                        <!-- Items Table -->
                        <table id="itemsTable" class="table mt-3 table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Item</th>
                                    <th>Received Qty</th>
                                    <th>Unit Price</th>
                                    <th>Discount %</th>
                                    <th>Discount Amt</th>
                                    <th>Tax %</th>
                                    <th>Tax Amt</th>
                                    <th>Subtotal</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="itemsTableBody">
                                <!-- rows added dynamically -->
                            </tbody>
                        </table>

                        <!-- Document Upload -->
                        <div class="mt-4">
                            <h5>Document Upload</h5>
                            <div class="mb-3">
                                <input type="file" id="documentUpload" name="document_upload" class="form-control" accept=".png,.jpg,.jpeg,.pdf">
                                <?php if($editMode && !empty($grn['document_path'])): ?>
                                    <small>Current file: <a href="<?= htmlspecialchars($grn['document_path']) ?>" target="_blank">View</a></small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" id="resetBtn" class="btn btn-secondary">Reset</button>
                            <button type="submit" id="submitBtn" class="btn btn-success"><?= $editMode ? "Update GRN" : "Create GRN" ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Item Selection Modal -->
<div class="modal fade" id="itemModal" tabindex="-1" aria-labelledby="itemModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="itemModalLabel">Select Items from PO</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <small class="text-muted">Choose items from the currently selected PO. Select multiple rows and click "Add Selected".</small>
        </div>
        <table class="table table-bordered table-striped" id="poItemsTable">
          <thead>
            <tr>
              <th style="width:40px;"><input type="checkbox" id="selectAllPoItems"></th>
              <th>Item Name</th>
              <th>Ordered Qty</th>
              <th>Unit Price</th>
              <th>Tax %</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="addSelectedItemsBtn">Add Selected</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    const today = new Date().toISOString().split('T')[0];
    
    $('#grnDate').val('<?= $editMode ? $grn['grn_date'] : '' ?>' || today);
    
    $('#invoiceDate').val('<?= $editMode ? $grn['invoice_date'] : '' ?>' || today);
    $('#invoiceNumber').val('<?= $editMode ? $grn['invoice_number'] : '' ?>' || 0);
    $('#transportation').val('<?= $editMode ? $grn['transportation'] : '' ?>' || 0);

    // init select2 if available
    if ($.fn.select2) {
        $('.select2').select2({ width: '100%' });
    }

    // Preloaded items for edit (server-side)
    const initialItems = <?= json_encode($items) ?>;
    let itemCounter = 0;

    // Load Vendors
    $.getJSON('./api/vendors_api.php?action=simpleList', function(res) {
        const vendorSelect = $('#vendor');
        vendorSelect.empty().append('<option value="">Select Vendor</option>');
        if (res && res.data) {
            res.data.forEach(v => vendorSelect.append(`<option value="${v.vendor_id}" ${v.vendor_id == <?= $editMode ? intval($grn['vendor_id']) : 0 ?> ? 'selected' : ''}>${v.vendor_name}</option>`));
        }
        if(<?= $editMode ? 'true' : 'false' ?>){
            vendorSelect.trigger('change');
        }
    });

    $.getJSON('./api/branches_api.php?action=getActiveBranches', function(res) {
    const branchSelect = $('#branch');
    branchSelect.empty().append('<option value="">Select Branch</option>');

    if (res && res.data) {
        res.data.forEach(v => {
            branchSelect.append(`<option value="${v.branch_id}">${v.branch_name}</option>`);
        });
    }

    // Set selected value after all options are loaded
    <?php if($editMode): ?>
        const selectedBranch = <?= intval($grn['branch_id']) ?>;
        if(selectedBranch) {
            branchSelect.val(selectedBranch).trigger('change');
        }
    <?php endif; ?>
});


    // Load POs for selected vendor
    $('#vendor').on('change', function() {
        const vendorId = $(this).val();
        const purchaseSelect = $('#purchaseOrder');
        purchaseSelect.empty().append('<option value="">Select PO</option>');
        $('#poItemsTable tbody').empty(); // clear modal table
        if (!vendorId) return;

        $.ajax({
            url: './api/purchase_order_api.php?action=getByVendor',
            method: 'POST',
            data: { vendor_id: vendorId },
            dataType: 'json',
            success: function(res) {
                if (res && res.data) {
                    res.data.forEach(p => purchaseSelect.append(`<option value="${p.po_id}" ${p.po_id == <?= $editMode ? intval($grn['po_id']) : 0 ?> ? 'selected' : ''}>${p.order_number}</option>`));
                }
                if(<?= $editMode ? 'true' : 'false' ?>){
                    $('#purchaseOrder').trigger('change');
                }
            },
            error: function() {
                showAlert('Failed to fetch POs for vendor', 'danger');
            }
        });
    });

    // When PO changed, load PO items (for modal)
    $('#purchaseOrder').on('change', function() {
        const poId = $(this).val();
        const tbody = $('#poItemsTable tbody');
        tbody.empty();
        if (!poId) return;

        $.ajax({
            url: './api/purchase_order_api.php?action=getByPO',
            method: 'POST',
            data: { po_id: poId },
            dataType: 'json',
            success: function(res) {
                if (!res || !res.data) {
                    showAlert('No items found for PO', 'warning');
                    return;
                }
                // console.log(res.data);
                res.data.forEach(item => {
                    // Each row includes a data-item JSON in dataset
                    const row = $(`
                        <tr>
                            <td><input type="checkbox" class="select-po-item" data-item='${JSON.stringify(item).replace(/'/g,"&#39;")}'></td>
                            <td>${item.item_name || ''}</td>
                            <td>${item.quantity || 0}</td>
                            <td>${item.unit_price || 0}</td>
                            <td>${item.tax_percentage || 0}</td>
                        </tr>
                    `);
                    tbody.append(row);
                });
            },
            error: function() {
                showAlert('Failed to load PO items', 'danger');
            }
        });
    });

    // Select all in modal
    $(document).on('change', '#selectAllPoItems', function(){
        $('.select-po-item').prop('checked', this.checked);
    });

    
   // Add selected items from modal to items table
$('#addSelectedItemsBtn').on('click', function(){
    const selected = $('.select-po-item:checked');
    if (selected.length === 0) {
        showAlert('Select at least one item from PO', 'warning');
        return;
    }
    selected.each(function(){
        const d = $(this).data('item');
        

        // 👇 You’ll update this block
        if ($('#itemsTableBody tr[data-item-id="'+d.item_id+'"]').length > 0) {
            showAlert(`Item "${d.item_name}" already added.`, 'warning');
            return;
        }

        addItemRow({
            po_item_id: d.po_item_id || 0,   
            item_id: d.item_id,
            item_name: d.item_name,
            quantity: d.quantity || 1,
            unit_price: d.unit_price || 0,
            discount_percentage: d.discount_percentage || 0,
            discount_amount: 0,
            tax_percentage: d.tax_percentage || 0,
            tax_amount: 0,
            subjective_amount: 0
        });
    });
    $('#itemModal').modal('hide');
    calculateTotals();
});


    // Add initial items if edit mode
    if (initialItems && initialItems.length > 0) {
        initialItems.forEach(it => {
            addItemRow({
                po_item_id: it.po_item_id,
                item_id: it.item_id,
                item_name: it.item_name || '',
                quantity: it.qty_received || it.quantity || 1,
                unit_price: it.unit_price || 0,
                discount_percentage: it.discount_percentage || 0,
                discount_amount: it.discount_amount || 0,
                tax_percentage: it.tax_percentage || 0,
                tax_amount: it.tax_amount || 0,
                subjective_amount: it.subjective_amount || 0
            });
        });
        calculateTotals();
    }

    // Function to add a row in items table
    function addItemRow(data) {
        itemCounter++;
        const idx = itemCounter;
        const tbody = $('#itemsTableBody');
        const row = $(`
            <tr data-item-id="${data.item_id}">
                <td>
                     <input type="hidden" name="items[${idx}][item_id]" value="${data.item_id}">
  <input type="hidden" name="items[${idx}][po_item_id]" value="${data.po_item_id || 0}">
  ${escapeHtml(data.item_name)}
                </td>
                <td><input type="number" name="items[${idx}][quantity]" class="form-control item-quantity" value="${parseFloat(data.quantity).toFixed(2)}" min="any" step="any"></td>
                <td><input type="number" name="items[${idx}][unit_price]" class="form-control item-unit-price" value="${parseFloat(data.unit_price).toFixed(2)}" step="any"></td>
                <td><input type="number" name="items[${idx}][discount_pct]" class="form-control item-discount-pct" value="${parseFloat(data.discount_percentage || 0).toFixed(2)}" step="any"></td>
                <td><input type="number" name="items[${idx}][discount_amt]" class="form-control item-discount-amt" value="${parseFloat(data.discount_amount || 0).toFixed(2)}" readonly></td>
                <td><input type="number" name="items[${idx}][tax_pct]" class="form-control item-tax-pct" value="${parseFloat(data.tax_percentage || 0).toFixed(2)}" step="any"></td>
                <td><input type="number" name="items[${idx}][tax_amt]" class="form-control item-tax-amt" value="${parseFloat(data.tax_amount || 0).toFixed(2)}" readonly></td>
                <td><input type="number" name="items[${idx}][subjective_amt]" class="form-control item-line-total" value="${parseFloat(data.subjective_amount || 0).toFixed(2)}" readonly></td>
                <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
            </tr>
        `);

        // bind events
        row.find('.item-quantity, .item-unit-price, .item-discount-pct, .item-tax-pct').on('input change', function(){
            calculateRow(row);
        });

        row.find('.remove-row').on('click', function(){
            row.remove();
            calculateTotals();
        });

        tbody.append(row);
        calculateRow(row);
    }

    // row calculation
    function calculateRow(row) {
        const qty = parseFloat(row.find('.item-quantity').val()) || 0;
        const unitPrice = parseFloat(row.find('.item-unit-price').val()) || 0;
        const discountPct = parseFloat(row.find('.item-discount-pct').val()) || 0;
        const taxPct = parseFloat(row.find('.item-tax-pct').val()) || 0;

        const base = qty * unitPrice;
        const discountAmt = (discountPct / 100) * base;
        const taxable = base - discountAmt;
        const taxAmt = (taxPct / 100) * taxable;
        const total = taxable + taxAmt;

        row.find('.item-discount-amt').val(discountAmt.toFixed(2));
        row.find('.item-tax-amt').val(taxAmt.toFixed(2));
        row.find('.item-line-total').val(total.toFixed(2));

        calculateTotals();
    }

    // calculate totals for all rows
    function calculateTotals(){
        let total = 0;
        let discount = 0;
        let tax = 0;
        $('#itemsTableBody tr').each(function(){
            const line = parseFloat($(this).find('.item-line-total').val()) || 0;
            const damt = parseFloat($(this).find('.item-discount-amt').val()) || 0;
            const taxAmt = parseFloat($(this).find('.item-tax-amt').val()) || 0;
            total += line;
            discount += damt;
            tax += taxAmt
        });

        // add transportation if present
        // const trans = parseFloat($('#transportation').val()) || 0;
        // const finalTotal = total + trans;
        const finalTotal = total;

        $('#totalAmount').val(finalTotal.toFixed(2));
        $('#discountAmount').val(discount.toFixed(2));
        $('#taxAmount').val(tax.toFixed(2));
    }

    // recalc totals whenever transportation changes
    $('#transportation').on('input change', calculateTotals);

    // reset button
    $('#resetBtn').on('click', function(){
        if(confirm('Reset the form? All changes will be lost.')) {
            location.reload();
        }
    });

    // submit form via ajax
    $('#grnForm').on('submit', function(e){
        e.preventDefault();

        // validation
        if($('#itemsTableBody tr').length === 0){
            showAlert('Add at least one item before submitting.', 'warning');
            return;
        }

        // disable submit button
        $('#submitBtn').prop('disabled', true).text('Saving...');

        // Build items array from table rows and append to formdata as JSON
        let itemsArr = [];
        $('#itemsTableBody tr').each(function(){
            const row = $(this);
            itemsArr.push({
            item_id: row.find('input[name$="[item_id]"]').val(),
            po_item_id: row.find('input[name$="[po_item_id]"]').val() || 0, // ✅ Add this
            quantity: parseFloat(row.find('.item-quantity').val()) || 0,
            unit_price: parseFloat(row.find('.item-unit-price').val()) || 0,
            discount_pct: parseFloat(row.find('.item-discount-pct').val()) || 0,
            discount_amt: parseFloat(row.find('.item-discount-amt').val()) || 0,
            tax_pct: parseFloat(row.find('.item-tax-pct').val()) || 0,
            tax_amt: parseFloat(row.find('.item-tax-amt').val()) || 0,
            subjective_amt: parseFloat(row.find('.item-line-total').val()) || 0
});
        });

        const formData = new FormData(this);
        formData.append('action', '<?= $editMode ? 'update' : 'create' ?>');
        formData.append('items', JSON.stringify(itemsArr));

        $.ajax({
            url: './api/grn_api.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res){
                $('#submitBtn').prop('disabled', false).text('<?= $editMode ? "Update GRN" : "Create GRN" ?>');
                if(res && res.status === 'success'){
                    showAlert('GRN <?= $editMode ? "updated" : "created" ?> successfully!', 'success');
                    // redirect to report after a short pause
                    // setTimeout(function(){ window.location.href = 'grn_report.php'; }, 900);
                } else {
                    const msg = (res && res.message) ? res.message : 'Failed to save GRN.';
                    showAlert(msg, 'danger');
                }
            },
            error: function(xhr, status, err){
                $('#submitBtn').prop('disabled', false).text('<?= $editMode ? "Update GRN" : "Create GRN" ?>');
                showAlert('Request failed: ' + err, 'danger');
            }
        });
    });

    // small helper to escape HTML when injecting names
    function escapeHtml(text) {
      if (!text) return '';
      return $('<div>').text(text).html();
    }
});
</script>

<script src="./js/showAlert.js"></script>
<?php require_once("footer.php"); ?>
