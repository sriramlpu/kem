<?php
require_once("header.php");
require_once("nav.php");
require_once("../functions.php"); // DB functions
?>

<section class="container-fluid py-3">
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center bg-success text-white">
            <h5 class="mb-0">Goods Received Note (GRN) Details</h5>
            <div>
                <a href="grn_report.php" class="btn btn-secondary btn-sm">Back</a>
                <a href="#" class="btn btn-primary btn-sm"
                   onclick="postToForm('grn_form.php', {grn_id: '<?= $_REQUEST['grn_id'] ?>'})">
                   Edit
                </a>
            </div>
        </div>

        <div class="card-body">
            <?php
            $grn_id = intval($_REQUEST['grn_id']);

            $grn = exeSql("SELECT g.*, v.vendor_name, po.order_number
                           FROM goods_receipts g
                           JOIN vendors v ON g.vendor_id = v.vendor_id
                           JOIN purchase_orders po ON g.po_id = po.po_id
                           WHERE g.grn_id = $grn_id")[0] ?? null;

            if (!$grn) {
                echo "<div class='alert alert-danger'>GRN not found.</div>";
                exit;
            }

            $items = exeSql("SELECT * FROM goods_receipt_items WHERE grn_id = $grn_id");
            $branchId = exeSql("SELECT DISTINCT branch_id FROM goods_receipt_items WHERE grn_id = $grn_id")[0]['branch_id'];
            $branchName = getFieldId('branches', 'branch_id', $branchId, 'branch_name');

            // initialize totals
            $subtotal = 0;
            $returnedTotal = 0;
            ?>

            <div class="row mb-3">
                <div class="col-md-6">
                    <p><strong>GRN Number:</strong> <?= htmlspecialchars($grn['grn_number']) ?></p>
                    <p><strong>GRN Date:</strong> <?= htmlspecialchars($grn['grn_date']) ?></p>
                    <p><strong>Branch:</strong> <?= htmlspecialchars($branchName) ?></p>
                    <p><strong>Vendor:</strong> <?= htmlspecialchars($grn['vendor_name']) ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>PO Number:</strong> <?= htmlspecialchars($grn['order_number']) ?></p>
                    <p><strong>Invoice Number:</strong> <?= htmlspecialchars($grn['invoice_number']) ?></p>
                    <p><strong>Invoice Date:</strong> <?= htmlspecialchars($grn['invoice_date']) ?></p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Item Name</th>
                            <th>Quantity Received</th>
                            <th>Returned Quantity</th>
                            <th>Quantity Available</th>
                            <th>Unit Price (₹)</th>
                            <th>Discount (%)</th>
                            <th>Tax (%)</th>
                            <th>Received Items Total (₹)</th>
                            <th>Returned Items Total (₹)</th>
                            <th>Transportation (₹)</th>
                            <th>Total (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): 
                            $rItemId = $item['grn_item_id'];

                            // get return details
                            $grnReturned = exeSql("SELECT SUM(return_qty) as return_qty, SUM(total_amount) as total_amount
                                                   FROM goods_return_items
                                                   WHERE grn_item_id = $rItemId")[0] ?? null;

                            $returnQty = $grnReturned['return_qty'] ?? 0;
                            $returnAmt = $grnReturned['total_amount'] ?? 0;

                            // compute totals
                            $receivedTotal = (float)$item['subjective_amount'];
                            $subtotal += $receivedTotal;
                            $returnedTotal += $returnAmt;

                            // per-item transportation share (split evenly among all items)
                            $transportShare = count($items) > 0 ? $grn['transportation'] / count($items) : 0;
                            $totalAfterReturn = $receivedTotal - $returnAmt + $transportShare;
                        ?>
                            <tr>
                                <td><?= htmlspecialchars(getField('items', $item['item_id'], 'item_name', 'item_id')) ?></td>
                                <td><?= $item['qty_received'] ?></td>
                                <td><?= $returnQty ?></td>
                                <td><?= $item['qty_received'] - $returnQty ?></td>
                                <td><?= number_format($item['unit_price'], 2) ?></td>
                                <td><?= $item['discount_percentage'] ?></td>
                                <td><?= $item['tax_percentage'] ?></td>
                                <td><?= number_format($receivedTotal, 2) ?></td>
                                <td><?= number_format($returnAmt, 2) ?></td>
                                <td><?= number_format($transportShare, 2) ?></td>
                                <td><?= number_format($totalAfterReturn, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            $finalSubtotal = $subtotal - $returnedTotal;
            $grandTotal = $finalSubtotal + $grn['transportation'];
            ?>

            <div class="row mt-4">
                <div class="col-md-6 offset-md-6">
                    <table class="table table-bordered">
                        <tr>
                            <th>Subtotal</th>
                            <td>₹ <?= number_format($subtotal, 2) ?></td>
                        </tr>
                        <tr>
                            <th>Discount</th>
                            <td>₹ <?= number_format($grn['discount_amount'], 2) ?></td>
                        </tr>
                        <tr>
                            <th>Returned Amount</th>
                            <td>₹ <?= number_format($returnedTotal, 2) ?></td>
                        </tr>
                        <tr>
                            <th>Transportation</th>
                            <td>₹ <?= number_format($grn['transportation'], 2) ?></td>
                        </tr>
                        <tr class="table-success">
                            <th>Grand Total</th>
                            <td><strong>₹ <?= number_format($grandTotal, 2) ?></strong></td>
                        </tr>
                    </table>
                </div>
            </div>

            <?php if (!empty($grn['remarks'])): ?>
                <div class="mt-3">
                    <strong>Remarks:</strong>
                    <p><?= nl2br(htmlspecialchars($grn['remarks'])) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($grn['document_path'])): ?>
                <div class="mt-3">
                    <strong>Attached Document:</strong>
                    <?php 
                    $ext = pathinfo($grn['document_path'], PATHINFO_EXTENSION);
                    if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif'])): ?>
                        <img src="<?= $grn['document_path'] ?>" class="img-fluid rounded mt-2" style="max-height:150px;">
                    <?php elseif (strtolower($ext) == 'pdf'): ?>
                        <embed src="<?= $grn['document_path'] ?>" type="application/pdf" width="100%" height="400px">
                    <?php else: ?>
                        <p><a href="<?= $grn['document_path'] ?>" target="_blank">Download Document</a></p>
                    <?php endif; ?>
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
