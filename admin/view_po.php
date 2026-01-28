<?php
require_once("header.php");
require_once("nav.php");
require_once("../functions.php"); // include your DB functions
$po_id = intval($_GET['po_id']);
?>

<section class="container-fluid py-3">
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center bg-success text-white">
            <h5 class="mb-0">Purchase Order Details</h5>
            <div>
                <a href="po_report.php" class="btn btn-secondary btn-sm">Back</a>
                <!--                <a href="#" class="btn btn-primary btn-sm"-->
                <!--   onclick="postToForm('purchase_order_form.php', {po_id: '<?= $po_id ?>'})">-->
                <!--   Edit-->
                <!--</a>-->

            </div>
        </div>
        <div class="card-body">
            <?php

            $po = exeSql("SELECT po.*, b.*, v.vendor_name 
                          FROM purchase_orders po
                          JOIN branches b ON po.branch_id = b.branch_id
                          JOIN vendors v ON po.vendor_id = v.vendor_id
                          WHERE po.po_id = $po_id")[0] ?? null;

            if (!$po) {
                echo "<div class='alert alert-danger'>Purchase Order not found.</div>";
                exit;
            }

            $items = exeSql("SELECT * FROM purchase_order_items WHERE po_id = $po_id");
            ?>

            <div class="row mb-3">
                <div class="col-md-6">
                    <p><strong>PO Number:</strong> <?= htmlspecialchars($po['order_number']) ?></p>
                    <p><strong>Branch:</strong> <?= htmlspecialchars($po['branch_name']) ?></p>
                    <p><strong>Vendor:</strong> <?= htmlspecialchars($po['vendor_name']) ?></p>
                    <p><strong>PO Date:</strong> <?= htmlspecialchars($po['po_date']) ?></p>
                    <p><strong>Expected Delivery:</strong> <?= $po['expected_delivery_date'] ?></p>


                    <?php
                    $parts = [];
                    if ($po['item_location'] == 'Store') {
                        $delivery = exeSql("SELECT * FROM branches WHERE branch_id = {$po['delivery_address']} LIMIT 1")[0];
                        if (!empty($delivery['address']) and isset($delivery['address']))  $parts[] = $delivery['address'];
                        if (!empty($delivery['city']) and isset($delivery['city']))     $parts[] = $delivery['city'];
                        if (!empty($delivery['state']) and isset($delivery['state']))    $parts[] = $delivery['state'];
                        if (!empty($delivery['pincode']) and isset($delivery['pincode']))  $parts[] = $delivery['pincode'];

                        $dfullAddress = implode(', ', $parts);
                    }
                    $parts1 = [];
                    $billing = exeSql("SELECT * FROM branches WHERE branch_id = {$po['billing_address']} LIMIT 1")[0];

                    if (!empty($billing['address']) and isset($billing['address']))  $parts1[] = $billing['address'];
                    if (!empty($billing['city']) and isset($billing['city']))     $parts1[] = $billing['city'];
                    if (!empty($billing['state']) and isset($billing['state']))    $parts1[] = $billing['state'];
                    if (!empty($billing['pincode']) and isset($billing['pincode']))  $parts1[] = $billing['pincode'];
                    $bfullAddress = implode(', ', $parts1);
                    
                    if ($po['item_location'] == 'Store') { ?>
                        <p><strong>Delivery Address: </strong> <?= $dfullAddress ?>
                        </p>
                    <?php
                    } else { ?>
                        <p><strong>Venue Address: </strong> <?= $po['venue_location_address'] ?>
                        </p>

                    <?php } ?>

                    <p><strong>Billing Address:</strong> <?= $bfullAddress ?></p>



                </div>
                <div class="col-md-6">
                    <p><strong>Status:</strong>
                        <span class="badge 
                            <?= $po['status'] == 'Pending' ? 'bg-warning' : '' ?>
                            <?= $po['status'] == 'Partially Fulfilled' ? 'bg-info' : '' ?>
                            <?= $po['status'] == 'Completed' ? 'bg-success' : '' ?>
                            <?= $po['status'] == 'Cancelled' ? 'bg-secondary' : '' ?>
                        ">
                            <?= htmlspecialchars($po['status']) ?>
                        </span>
                    </p>
                    <p><strong>Subtotal:</strong> ₹ <?= number_format($po['total_amount'], 2) ?></p>
                    <p><strong>Discount:</strong> ₹ <?= number_format($po['discount_amount'], 2) ?></p>
                    <p><strong>Transportation:</strong> ₹ <?= number_format($po['transportation'], 2) ?></p>
                    <p><strong>Grand Total:</strong> ₹ <?= number_format($po['total_amount'] + $po['transportation'], 2) ?></p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Item Name</th>
                            <th>Quantity</th>
                            <th>Unit Price(₹)</th>
                            <th>Discount Percentage(%)</th>
                            <th>Discount Amount (₹)</th>
                            <th>Tax Percentage(%)</th>
                            <th>Tax Amount (₹)</th>
                            <th>Total (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars(getField('items', $item['item_id'], 'item_name', 'item_id')) ?></td>
                                <td><?= htmlspecialchars($item['quantity']) ?></td>
                                <td><?= number_format($item['unit_price'], 2) ?></td>
                                <td><?= $item['discount_percentage'] ?></td>
                                <td><?= number_format($item['discount_amount'], 2) ?></td>
                                <td><?= $item['tax_percentage'] ?></td>
                                <td><?= number_format($item['tax_amount'], 2) ?></td>
                                <td><?= number_format($item['subjective_amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($po['remarks'])): ?>
                <div class="mt-3">
                    <strong>Remarks:</strong>
                    <p><?= nl2br(htmlspecialchars($po['remarks'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<script>
    function postToForm(path, params) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = path;

        for (const key in params) {
            if (params.hasOwnProperty(key)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = params[key];
                form.appendChild(input);
            }
        }

        document.body.appendChild(form);
        form.submit();
    }
</script>
<?php require_once("footer.php"); ?>