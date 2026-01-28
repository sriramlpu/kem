<?php
require_once("../functions.php");

$grn_id = $_GET['grn_id'] ?? 0;

// ===================== GRN MAIN INFO ======================
$grn = exeSql("
    SELECT g.*, v.vendor_name, v.address AS vendor_address, v.phone AS vendor_phone, 
           b.branch_name, p.order_number AS po_number
    FROM goods_receipts g
    JOIN vendors v ON v.vendor_id = g.vendor_id
    JOIN branches b ON b.branch_id = g.branch_id
    LEFT JOIN purchase_orders p ON p.po_id = g.po_id
    WHERE g.grn_id = $grn_id
")[0];

// ===================== GRN ITEMS ==========================
$items = exeSql("
    SELECT gri.*, i.item_name
    FROM goods_receipt_items gri
    INNER JOIN items i ON i.item_id = gri.item_id
    WHERE gri.grn_id = $grn_id
", true);

$totalQty = $totalAmt = $totalDiscount = $totalFinal = 0;

// ===================== RETURN NOTES =======================
$returns = exeSql("
    SELECT *
    FROM goods_return_notes
    WHERE grn_id = $grn_id
", true);

$returnItems = [];
$returnTotal = 0;

if (!empty($returns)) {
    $returnItems = exeSql("
        SELECT gri.*, i.item_name, r.return_number, r.return_date
        FROM goods_return_items gri
        JOIN goods_return_notes r ON r.return_id = gri.return_id
        JOIN items i ON i.item_id = gri.item_id
        WHERE r.grn_id = $grn_id
    ", true);

    foreach ($returnItems as $rItem) {
        $returnTotal += $rItem['total_amount'];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>GRN #<?= htmlspecialchars($grn['grn_number']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 13px; margin: 40px; color: #000; }
        .header {
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .header img { height: 60px; vertical-align: middle; }
        .header h2 { display: inline-block; margin: 0; margin-left: 15px; vertical-align: middle; letter-spacing: 1px; }
        .header-right { float: right; text-align: right; }
        .section { width: 100%; margin-top: 15px; }
        .section td { vertical-align: top; padding: 5px 0; }
        .section strong { font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #000; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; }
        .text-right { text-align: right; }
        .totals td { font-weight: bold; background: #fafafa; }
        .footer { border-top: 1px solid #000; margin-top: 25px; padding-top: 5px; font-size: 12px; text-align: center; }
    </style>
</head>
<body>

<!-- ================== HEADER ================== -->
<div class="header">
    <img src="../assets/img/logo.jpg" alt="KMK Logo">
    <h2>KMK GLOBAL LIMITED</h2>
    <div class="header-right">
        <h3>GOODS RECEIPT NOTE</h3>
        <p>Date: <?= date("d.m.Y", strtotime($grn['grn_date'])) ?><br>
           GRN No: <?= htmlspecialchars($grn['grn_number']) ?><br>
           PO No: <?= htmlspecialchars($grn['po_number']) ?><br>
           <strong>Total : ₹ <?= number_format($grn['total_amount'] + $grn['transportation'], 2) ?></strong></p>
    </div>
    <div style="clear: both;"></div>
</div>

<!-- ================== VENDOR DETAILS ================== -->
<table class="section">
    <tr>
        <td width="50%">
            <strong>Buyer Details :</strong><br>
            KMK GLOBAL LIMITED<br>
            71-4-8/1, Ground Floor, Vijayawada,<br>
            Andhra Pradesh, 520007, INDIA
        </td>
        <td width="50%">
            <strong>Vendor Details :</strong><br>
            <?= htmlspecialchars($grn['vendor_name']) ?><br>
            <?= nl2br(htmlspecialchars($grn['vendor_address'])) ?><br>
            <?= htmlspecialchars($grn['vendor_phone']) ?>
        </td>
    </tr>
</table>

<p>
    <strong>Invoice No:</strong> <?= htmlspecialchars($grn['invoice_number']) ?> &nbsp;&nbsp;
    <strong>Invoice Date:</strong> <?= $grn['invoice_date'] ? date("d.m.Y", strtotime($grn['invoice_date'])) : '-' ?><br>
    <strong>Remarks:</strong> <?= htmlspecialchars($grn['remarks']) ?>
</p>

<!-- ================== GRN ITEMS ================== -->
<table>
    <thead>
        <tr>
            <th style="width:5%">S.No</th>
            <th style="width:25%">Item Name</th>
            <th style="width:10%">Unit Price</th>
            <th style="width:10%">Qty Received</th>
            <th style="width:10%">Total</th>
            <th style="width:10%">Discount (%)</th>
            <th style="width:15%">Discount Amount</th>
            <th style="width:15%">Amount</th>
        </tr>
    </thead>
    <tbody>
        <?php $i=1; foreach($items as $item): 
            $total = $item['qty_received'] * $item['unit_price'];
            $discountAmt = $item['discount_amount'] ?? 0;
            $finalAmt = $item['subjective_amount'] ?? ($total - $discountAmt);

            $totalQty += $item['qty_received'];
            $totalAmt += $total;
            $totalDiscount += $discountAmt;
            $totalFinal += $finalAmt;
        ?>
        <tr>
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($item['item_name']) ?></td>
            <td class="text-right"><?= number_format($item['unit_price'], 2) ?></td>
            <td class="text-right"><?= number_format($item['qty_received'], 2) ?></td>
            <td class="text-right"><?= number_format($total, 2) ?></td>
            <td class="text-right"><?= number_format($item['discount_percentage'], 2) ?></td>
            <td class="text-right"><?= number_format($discountAmt, 2) ?></td>
            <td class="text-right"><?= number_format($finalAmt, 2) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr class="totals">
            <td colspan="3" class="text-right">Total</td>
            <td class="text-right"><?= number_format($totalQty, 2) ?></td>
            <td class="text-right"><?= number_format($totalAmt, 2) ?></td>
            <td></td>
            <td class="text-right"><?= number_format($totalDiscount, 2) ?></td>
            <td class="text-right"><?= number_format($totalFinal, 2) ?></td>
        </tr>
        <tr>
            <td colspan="7" class="text-right">Transportation</td>
            <td class="text-right"><?= number_format($grn['transportation'], 2) ?></td>
        </tr>
        <tr class="totals">
            <td colspan="7" class="text-right"><strong>Grand Total</strong></td>
            <td class="text-right"><strong><?= number_format($totalFinal + $grn['transportation'], 2) ?></strong></td>
        </tr>
    </tfoot>
</table>

<!-- ================== RETURN SECTION ================== -->
<?php if (!empty($returnItems)): ?>
    <h3 style="margin-top:30px;">Returned Items</h3>
    <table>
        <thead>
            <tr>
                <th style="width:5%">S.No</th>
                <th style="width:20%">Return No</th>
                <th style="width:15%">Return Date</th>
                <th style="width:20%">Item Name</th>
                <th style="width:10%">Qty Returned</th>
                <th style="width:10%">Unit Price</th>
                <th style="width:10%">Discount</th>
                <th style="width:10%">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php $r=1; foreach($returnItems as $rItem): ?>
            <tr>
                <td><?= $r++ ?></td>
                <td><?= htmlspecialchars($rItem['return_number']) ?></td>
                <td><?= date("d.m.Y", strtotime($rItem['return_date'])) ?></td>
                <td><?= htmlspecialchars($rItem['item_name']) ?></td>
                <td class="text-right"><?= number_format($rItem['return_qty'], 2) ?></td>
                <td class="text-right"><?= number_format($rItem['unit_price'], 2) ?></td>
                <td class="text-right"><?= number_format($rItem['discount_amount'], 2) ?></td>
                <td class="text-right"><?= number_format($rItem['total_amount'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="totals">
                <td colspan="7" class="text-right"><strong>Total Return</strong></td>
                <td class="text-right"><strong><?= number_format($returnTotal, 2) ?></strong></td>
            </tr>
        </tfoot>
    </table>
<?php endif; ?>

<div class="footer">
    71-4-8/1, Ground Floor, Vijayawada, Andhra Pradesh, 520007, INDIA
</div>

</body>
</html>
