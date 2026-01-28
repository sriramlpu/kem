<?php
require_once("header.php");
require_once("nav.php");
require_once("../functions.php");

// print_r($_REQUEST);

// Get grn_id from URL if provided
$preselect_grn_id = isset($_GET['grn_id']) ? intval($_GET['grn_id']) : 0;

// Fetch all GRNs
$grns = exeSql("SELECT grn_id, grn_number FROM goods_receipts ORDER BY grn_id DESC");
?>

<section class="container-fluid section">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Create Goods Return Note</h5>
            <a href="grn_report.php" class="btn btn-light btn-sm">
                <i class="fas fa-arrow-left me-2"></i>Back to GRN Report
            </a>
        </div>
        <div class="card-body">
            <form id="returnForm">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="grn_id" class="form-label">Select GRN <span class="text-danger">*</span></label>
                        <select id="grn_id" name="grn_id" class="form-select select2" required>
                            <option value="">Select GRN</option>
                            <?php foreach ($grns as $g): ?>
                                <option value="<?= $g['grn_id'] ?>" <?= ($preselect_grn_id == $g['grn_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($g['grn_number']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="vendor_name" class="form-label">Vendor</label>
                        <input type="text" id="vendor_name" class="form-control" readonly>
                        <input type="hidden" id="vendor_id" name="vendor_id">
                    </div>
                    <div class="col-md-4">
                        <label for="return_date" class="form-label">Return Date <span class="text-danger">*</span></label>
                        <input type="date" id="return_date" name="return_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="returnItemsTable">
                        <thead class="table-dark">
                            <tr>
                                <th>Item</th>
                                <th>Received Qty</th>
                                <th>Already Returned</th>
                                <th>Available to Return</th>
                                <th>Return Qty <span class="text-danger">*</span></th>
                                <th>Unit Price</th>
                                <th>Discount</th>
                                <th>Tax</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <tr>
                                <td colspan="9" class="text-center text-muted">
                                    <i class="fas fa-info-circle me-2"></i>Please select a GRN to load items
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="row mt-3">
                    <div class="col-md-8">
                        <label for="remarks" class="form-label">Remarks</label>
                        <textarea id="remarks" name="remarks" class="form-control" rows="2" placeholder="Enter any remarks or reason for return..."></textarea>
                    </div>
                    <div class="col-md-4">
                        <div class="card p-3 border bg-light">
                            <h6 class="text-center mb-3">Return Summary</h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span> <strong id="subtotal">₹0.00</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Discount:</span> <strong id="discountTotal" class="text-success">₹0.00</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tax:</span> <strong id="taxTotal" class="text-info">₹0.00</strong>
                            </div>
                            <div class="d-flex justify-content-between border-top pt-2 mt-2">
                                <span class="fw-bold">Grand Total:</span> <strong id="grandTotal" class="text-danger fs-5">₹0.00</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-end mt-4">
                    <a href="grn_report.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-danger" id="submitBtn">
                        <i class="fas fa-save me-2"></i>Submit Return
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    // Initialize Select2
    if ($.fn.select2) {
        $('#grn_id').select2({
            placeholder: 'Select GRN',
            allowClear: true
        });
    }

    function resetTotals() {
        $('#subtotal, #discountTotal, #taxTotal, #grandTotal').text('₹0.00');
    }

    function recalcTotals() {
        let subtotal = 0, totalDiscount = 0, totalTax = 0;

        $('#itemsBody tr').each(function(){
            const qty = parseFloat($(this).find('.return_qty').val()) || 0;
            const price = parseFloat($(this).find('.unit_price').text()) || 0;
            const perUnitDiscount = parseFloat($(this).data('per-unit-discount')) || 0;
            const perUnitTax = parseFloat($(this).data('per-unit-tax')) || 0;

            const lineSubtotal = qty * price;
            const discAmt = qty * perUnitDiscount;
            const taxAmt = qty * perUnitTax;
            const lineTotal = lineSubtotal - discAmt + taxAmt;

            subtotal += lineSubtotal;
            totalDiscount += discAmt;
            totalTax += taxAmt;

            $(this).find('.discount_amt_display').text('₹' + discAmt.toFixed(2));
            $(this).find('.tax_amt_display').text('₹' + taxAmt.toFixed(2));
            $(this).find('.item_total').text('₹' + lineTotal.toFixed(2));
        });

        $('#subtotal').text('₹' + subtotal.toFixed(2));
        $('#discountTotal').text('₹' + totalDiscount.toFixed(2));
        $('#taxTotal').text('₹' + totalTax.toFixed(2));
        $('#grandTotal').text('₹' + (subtotal - totalDiscount + totalTax).toFixed(2));
    }

    // On GRN change
    $('#grn_id').change(function(){
        const grnId = $(this).val();

        if (!grnId) {
            $('#itemsBody').html('<tr><td colspan="9" class="text-center text-muted"><i class="fas fa-info-circle me-2"></i>Please select a GRN to load items</td></tr>');
            $('#vendor_id, #vendor_name').val('');
            resetTotals();
            return;
        }

        $('#itemsBody').html('<tr><td colspan="9" class="text-center"><div class="spinner-border spinner-border-sm me-2"></div>Loading items...</td></tr>');

        $.ajax({
            url: './api/grn_return_api.php',
            method: 'POST',
            data: {action: 'getGrnDetails', grn_id: grnId},
            dataType: 'json',
            success: function(res){
                if (res.status === 'success') {
                    const grn = res.data.header;
                    const items = res.data.items;

                    $('#vendor_id').val(grn.vendor_id);
                    $('#vendor_name').val(grn.vendor_name);

                    if (items.length === 0) {
                        $('#itemsBody').html('<tr><td colspan="9" class="text-center text-warning"><i class="fas fa-exclamation-triangle me-2"></i>No items available for return</td></tr>');
                        resetTotals();
                        return;
                    }

                    let html = '';
                    items.forEach(it => {
                        const maxReturn = parseFloat(it.received_qty) - parseFloat(it.already_returned);
                        if (maxReturn > 0) {
                            html += `
                                <tr data-item-id="${it.item_id}" 
                                    data-grn-item-id="${it.grn_item_id}"
                                    data-per-unit-discount="${it.per_unit_discount}"
                                    data-per-unit-tax="${it.per_unit_tax}">
                                    <td>${it.item_name}</td>
                                    <td>${parseFloat(it.received_qty).toFixed(4)}</td>
                                    <td class="text-danger">${parseFloat(it.already_returned).toFixed(2)}</td>
                                    <td class="fw-bold text-success">${maxReturn.toFixed(4)}</td>
                                    <td><input type="number" class="form-control return_qty" value="0" min="0" max="${maxReturn}" step="any"></td>
                                    <td>₹<span class="unit_price">${parseFloat(it.unit_price).toFixed(2)}</span></td>
                                    <td class="discount_amt_display">₹0.00</td>
                                    <td class="tax_amt_display">₹0.00</td>
                                    <td class="item_total fw-bold">₹0.00</td>
                                </tr>`;
                        }
                    });

                    if (html === '') {
                        html = '<tr><td colspan="9" class="text-center text-warning"><i class="fas fa-exclamation-triangle me-2"></i>All items have been fully returned</td></tr>';
                    }

                    $('#itemsBody').html(html);
                    recalcTotals();
                } else {
                    showAlert(res.message || 'Error loading GRN details.', 'danger');
                    $('#itemsBody').html('<tr><td colspan="9" class="text-center text-danger"><i class="fas fa-times-circle me-2"></i>Failed to load items</td></tr>');
                }
            },
            error: function(){
                showAlert('Failed to fetch GRN details.', 'danger');
                $('#itemsBody').html('<tr><td colspan="9" class="text-center text-danger"><i class="fas fa-times-circle me-2"></i>Error loading items</td></tr>');
            }
        });
    });

    $(document).on('input', '.return_qty', function(){
        const max = parseFloat($(this).attr('max')) || 0;
        let val = parseFloat($(this).val()) || 0;

        if (val > max) {
            $(this).val(max);
            showAlert(`Cannot return more than ${max} units`, 'warning');
        }
        if (val < 0) $(this).val(0);

        recalcTotals();
    });

    $('#returnForm').submit(function(e){
        e.preventDefault();

        const items = [];
        $('#itemsBody tr').each(function(){
            const qty = parseFloat($(this).find('.return_qty').val());
            if (qty > 0) {
                const price = parseFloat($(this).find('.unit_price').text());
                const perUnitDiscount = parseFloat($(this).data('per-unit-discount'));
                const perUnitTax = parseFloat($(this).data('per-unit-tax'));
                const taxAmt = qty * perUnitTax;
                const discAmt = qty * perUnitDiscount;

                items.push({
                    grn_item_id: $(this).data('grn-item-id'),
                    item_id: $(this).data('item-id'),
                    return_qty: qty,
                    unit_price: price,
                    discount_amt: discAmt,
                    tax_amt: taxAmt
                });
            }
        });

        if (items.length === 0) {
            showAlert('Please enter at least one item to return', 'danger');
            return;
        }

        const data = {
            action: 'create',
            grn_id: $('#grn_id').val(),
            vendor_id: $('#vendor_id').val(),
            return_date: $('#return_date').val(),
            remarks: $('#remarks').val(),
            total_amount: parseFloat($('#subtotal').text().replace('₹', '')),
            discount_amount: parseFloat($('#discountTotal').text().replace('₹', '')),
            tax_amount: parseFloat($('#taxTotal').text().replace('₹', '')),
            items: items
        };

        $('#submitBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Processing...');

        $.ajax({
            url: './api/grn_return_api.php',
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            dataType: 'json',
            success: function(res){
                if (res.status === 'success') {
                    showAlert('Return created successfully: ' + res.return_number, 'success');
                    setTimeout(() => window.location.href = 'grn_report.php', 1500);
                } else {
                    showAlert(res.message || 'Failed to create return.', 'danger');
                    $('#submitBtn').prop('disabled', false).html('<i class="fas fa-save me-2"></i>Submit Return');
                }
            },
            error: function(){
                showAlert('Failed to submit return. Please try again.', 'danger');
                $('#submitBtn').prop('disabled', false).html('<i class="fas fa-save me-2"></i>Submit Return');
            }
        });
    });

    // Auto-load items if GRN is preselected
    <?php if ($preselect_grn_id > 0): ?>
        $('#grn_id').trigger('change');
    <?php endif; ?>
}); // ✅ properly closes $(document).ready
</script>

<script src="./js/showAlert.js"></script>
<?php require_once("footer.php"); ?>
