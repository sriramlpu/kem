<?php
require_once("header.php");
require_once("nav.php");
$today = date('Y-m-d');
?>
<section class="container-fluid section">
    <div class="row g-4">
        <div class="col-md-12">
            <div class="card shadow-sm border-0">
                <div class="card-header  bg-success text-white d-flex justify-content-between align-items-center"><span>Create GRN</span>
                    <a href="grn_report.php" class="btn btn-danger">View GRN</a>
                </div>
                <div class="card-body">
                    <form id="grnForm">
                        <div class="row g-4">
                            <div class="col-md-3">
                                <div class="form-floating">
                                    <select id="branch" name="branch_id" class="form-select select2" required>
                                        <option value="">Select Branch</option>
                                    </select>
                                </div>
                            </div>
                            <!-- Vendor -->
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <select id="vendor" name="vendor_id" class="form-select select2">
                                        <option value="">Select Vendor</option>
                                    </select>
                                    <!-- <label for="vendor">Vendor</label> -->
                                </div>
                            </div>

                            <!-- Purchase Order -->
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <select id="purchaseOrder" name="po_id" class="form-select select2" required>
                                        <option value="">Select PO</option>
                                    </select>
                                    <!-- <label for="purchaseOrder">Purchase Order</label> -->
                                </div>
                            </div>

                            <!-- GRN Date -->
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="date" id="grnDate" name="grn_date" class="form-control" required max="<?= $today ?>">
                                    <label for="grnDate">GRN Date</label>
                                </div>
                            </div>


                            <!-- Invoice Date -->
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="date" id="invoiceDate" name="invoice_date" class="form-control" required max="<?= $today ?>">
                                    <label for="invoiceDate">Invoice Date</label>
                                </div>
                            </div>

                            <!-- Invoice Number -->
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="text" id="invoiceNumber" name="invoice_number" class="form-control" required>
                                    <label for="invoiceNumber">Invoice Number</label>
                                </div>
                            </div>

                            <!-- Total Amount -->
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="number" id="totalAmount" name="total_amount" class="form-control" readonly>
                                    <label for="totalAmount">Total Amount (₹)</label>
                                </div>
                            </div>
                            <!-- Discount Amount -->
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="number" step="any" min="0" inputmode="decimal"
                                        id="discountAmount" name="discount_amount"
                                        class="form-control" placeholder="Discount Amount" readonly>
                                    <label for="discountAmount">Discount Amount(₹)</label>
                                </div>
                            </div>
                            
                            <!-- Total Tax Amount -->
<div class="col-md-4">
    <div class="form-floating">
        <input type="number" id="totalTaxAmount" name="total_tax_amount"
               class="form-control" readonly>
        <label for="totalTaxAmount">Total Tax Amount (₹)</label>
    </div>
</div>


                            <!-- Transportation -->
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="number" step="any" id="transportation" name="transportation" class="form-control" placeholder="Transportation">
                                    <label for="transportation">Transportation Charges(₹)</label>
                                </div>
                            </div>

                            <!-- Remarks -->
                            <div class="col-md-12">
                                <div class="form-floating">
                                    <textarea id="remarks" name="remarks" class="form-control" placeholder="Remarks"></textarea>
                                    <label for="remarks">Remarks</label>
                                </div>
                            </div>
                        </div>

                        <!-- Items Table -->
                        <table id="itemsTable" class="table mt-4 table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Item</th>
                                    <th>Received Qty</th>
                                    <th>Unit Price</th>
                                    <th>Discount Amt</th>
                                    <th>Tax Percentage</th>
                                    <th>Tax Amt</th>
                                    <th>Subjective Amount</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="itemsTableBody"></tbody>
                        </table>

                        <!-- Document Upload -->
                        <div class="mt-4">
                            <h5>Document Upload</h5>
                            <div class="mb-3">
                                <input type="file" id="documentUpload" name="document_upload" class="form-control" accept=".png,.jpg,.jpeg,.pdf" required>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" id="resetBtn" class="btn btn-secondary">Reset</button>
                            <button type="submit" class="btn btn-success">Create GRN</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    $(document).ready(function() {
        const today = new Date().toISOString().split('T')[0];
        $('#grnDate').val(today);
        $('#invoiceDate').val(today);

        // Load Vendors
        $.getJSON('./api/vendors_api.php?action=simpleList', function(res) {
            const vendorSelect = $('#vendor');
            vendorSelect.empty().append('<option value="">Select Vendor</option>');
            res.data.forEach(v => vendorSelect.append(`<option value="${v.vendor_id}">${v.vendor_name}</option>`));
        });
        $.getJSON('./api/branches_api.php?action=getActiveBranches', function(res) {
            if (res.status === 'success') {
                const branchSelect = $('#branch');
                branchSelect.empty().append('<option value="">Select Branch</option>');
                res.data.forEach(function(branch) {
                    branchSelect.append(`<option value="${branch.branch_id}">${branch.branch_name}</option>`);
                });
            }
        });
        // Load POs when vendor selected
        $('#vendor').on('change', function() {
            const vendorId = $(this).val();
            const purchaseSelect = $('#purchaseOrder');
            purchaseSelect.empty().append('<option value="">Select PO</option>');
            if (!vendorId) return;
            $.ajax({
                url: './api/purchase_order_api.php',
                method: 'POST', 
                data: {
                    vendor_id: vendorId,
                    action: 'getActivePOs'
                },
                dataType: 'json',
                success: function(res) {
                    res.data.forEach(p => purchaseSelect.append(`<option value="${p.po_id}">${p.order_number}</option>`));
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching purchase orders:', error);
                }
            });

        });

        // Load Items when PO selected
        $('#purchaseOrder').on('change', function() {
            const poId = $(this).val();
            $('#itemsTableBody').empty();
            if (!poId) return;
            $.post('./api/purchase_order_api.php', {
                action: 'getByPO',
                po_id: poId
            }, function(res) {
                if (res.status === 'success') {
                    let count = 0;

                    res.data.forEach(item => {
                        count++;

                        let orderedQty = parseFloat(item.quantity) || 0;
                        let sentQty = parseFloat(item.sent_quantity) || 0; // fallback to 0 if null
                        let remaining_quantity = orderedQty - sentQty;

                        let disabled = "";
                        let note = "";

                        if (remaining_quantity <= 0) {
                            disabled = "disabled";
                            note = `<small class="text-danger">This item is already completed</small>`;
                            remaining_quantity = 0;
                        }

                        const row = $(`
                <tr>
                    <td>
                    <input type="hidden" name="items[${count}][po_item_id]" value="${item.po_item_id}">
                        <input type="hidden" name="items[${count}][item_id]" value="${item.item_id}">
                        ${item.item_name} ${note}
                    </td>
                    <td><input type="number" step = "any" name="items[${count}][quantity]" class="form-control item-quantity" value="${remaining_quantity}" min="any" 
         max="${remaining_quantity}" ${disabled}></td>
                    <td><input type="number" step="any" name="items[${count}][unit_price]" class="form-control item-unit-price" value="${item.unit_price}" readonly ${disabled}></td>
                    <td><input type="number" name="items[${count}][discount_amt]" class="form-control item-discount-amt" value="${item.discount_amount||0}"></td>
                    <td><input type="number" name="items[${count}][tax_pct]" class="form-control item-tax-pct" value="${item.tax_percentage||0}" readonly ${disabled}></td>
                    <td><input type="number" name="items[${count}][tax_amt]" class="form-control item-tax-amt" value="0" readonly ${disabled}></td>
                    <td><input type="number" name="items[${count}][subjective_amt]" class="form-control item-line-total" value="0" readonly ${disabled}></td>
                    <td><button type="button" class="btn btn-danger btn-sm remove-row" ${disabled}>Remove</button></td>
                </tr>
            `);

                        // Trigger calculation
                        calculateRow(row);

                        row.find('.item-quantity, .item-unit-price, .item-discount-amt').on('input change', function() {
                            calculateRow(row);
                        });

                        row.find('.remove-row').on('click', function() {
                            row.remove();
                            calculateTotals();
                        });

                        $('#itemsTableBody').append(row);
                    });
                    calculateTotals();
                }
            }, 'json');
        });

        // Row calculation
        function calculateRow(row) {
            const qty = parseFloat(row.find('.item-quantity').val()) || 0;
            const unitPrice = parseFloat(row.find('.item-unit-price').val()) || 0;
            const discountAmt = parseFloat(row.find('.item-discount-amt').val()) || 0;
            const taxRate = parseFloat(row.find('.item-tax-pct').val()) || 0;

            const base = qty * unitPrice;
            const taxable = base - discountAmt;
            const taxAmt = (taxRate / 100) * taxable;
            const total = taxable + taxAmt;

            row.find('.item-tax-amt').val(taxAmt.toFixed(2));
            row.find('.item-line-total').val(total.toFixed(2));

            calculateTotals();
        }

        // Grand total
        function calculateTotals() {
    let total = 0;
    let discount = 0;
    let totalTax = 0;

    $('#itemsTableBody tr').each(function() {
        total += parseFloat($(this).find('.item-line-total').val()) || 0;
        discount += parseFloat($(this).find('.item-discount-amt').val()) || 0;
        totalTax += parseFloat($(this).find('.item-tax-amt').val()) || 0;
    });

    $('#totalAmount').val(total.toFixed(2));
    $('#discountAmount').val(discount.toFixed(2));
    $('#totalTaxAmount').val(totalTax.toFixed(2));
}


        // Reset
        $('#resetBtn').on('click', function() {
            $('#grnForm')[0].reset();
            $('#itemsTableBody').empty();
            $('#grnDate').val(today);
            $('#invoiceDate').val(today);
            calculateTotals();
        });

        // Submit
        $('#grnForm').on('submit', function(e) {
            e.preventDefault();
            
            
            if ($('#itemsTableBody tr').length === 0) {
                showAlert('Please add at least one item.', 'danger');
                return;
            }
            let items = [];
            $('#itemsTableBody tr').each(function() {
                const row = $(this);

                let qty = parseFloat(row.find('.item-quantity').val()) || 0;
                if (qty <= 0) return;
                items.push({
                    po_item_id: row.find('input[name$="[po_item_id]"]').val(),
                    item_id: row.find('input[name$="[item_id]"]').val(),
                    quantity: parseFloat(row.find('.item-quantity').val()) || 0,
                    unit_price: parseFloat(row.find('.item-unit-price').val()) || 0,
                    discount_amt: parseFloat(row.find('.item-discount-amt').val()) || 0,
                    tax_pct: parseFloat(row.find('.item-tax-pct').val()) || 0,
                    tax_amt: parseFloat(row.find('.item-tax-amt').val()) || 0,
                    subjective_amt: parseFloat(row.find('.item-line-total').val()) || 0
                });
            });
            if (items.length === 0) {
                showAlert('Please enter valid received quantities (greater than 0).', 'danger');
                return;
            }

            const formData = new FormData(this);
            formData.append('action', 'create');
            formData.append('total_amount', $('#totalAmount').val());
            formData.append('transportation', $('#transportation').val());
            formData.append('items', JSON.stringify(items));
            formData.append('total_tax_amount', $('#totalTaxAmount').val());
const $submitBtn = $(this).find('button[type="submit"]');
    $submitBtn.prop('disabled', true).text('Creating...');
            $.ajax({
                url: './api/grn_api.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    if (res.status === 'success') {
                        showAlert('GRN Created! No: ' + res.grn_number, 'success');
                        $('#grnForm')[0].reset();
                        $('#itemsTableBody').empty();
                        calculateTotals();

// reset Select2 dropdowns
$('#branch').val('').trigger('change');
$('#vendor').val('').trigger('change');
$('#purchaseOrder').val('').trigger('change');

// reset dates
$('#grnDate').val(today);
$('#invoiceDate').val(today);

                    } else {
                        showAlert('Error: ' + res.message, 'danger');
                    }
                },
                error: function() {
                    showAlert('Failed to create GRN.', 'danger');
                },
        complete: function() {
            // ✅ Re-enable button after request completes
            $submitBtn.prop('disabled', false).text('Create GRN');
        }
            });
        });
    });
</script>
<script src="./js/showAlert.js"></script>
<?php require_once("footer.php"); ?>