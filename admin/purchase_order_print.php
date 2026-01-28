<?php
require_once("../functions.php");

$po_id = $_GET['po_id'] ?? 0;

// Fetch PO main info
$po = exeSql("SELECT po.*, b.branch_name, v.vendor_name, v.address AS vendor_address, v.phone AS vendor_phone
             FROM purchase_orders po
             JOIN branches b ON b.branch_id = po.branch_id
             JOIN vendors v ON v.vendor_id = po.vendor_id
             WHERE po_id = $po_id")[0];

// Fetch PO items
$items = exeSql("SELECT poi.*, i.item_name 
                 FROM purchase_order_items poi 
                 INNER JOIN items i ON i.item_id = poi.item_id 
                 WHERE poi.po_id = $po_id", true);

$totalQty = $totalAmt = $totalDiscount = $totalFinal = 0;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Order #<?= htmlspecialchars($po['order_number']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 13px; margin: 40px; color: #000; }
        .header {
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .header img {
            height: 60px;
            vertical-align: middle;
        }
        .header h2 {
            display: inline-block;
            margin: 0;
            margin-left: 15px;
            vertical-align: middle;
            letter-spacing: 1px;
        }
        .header-right {
            float: right;
            text-align: right;
        }
        .section {
            width: 100%;
            margin-top: 15px;
        }
        .section td {
            vertical-align: top;
            padding: 5px 0;
        }
        .section strong {
            font-size: 13px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            border: 1px solid #000;
            padding: 6px 8px;
            text-align: left;
        }
        th {
            background: #f5f5f5;
        }
        .text-right { text-align: right; }
        .totals td {
            font-weight: bold;
            background: #fafafa;
        }
        .footer {
            border-top: 1px solid #000;
            margin-top: 25px;
            padding-top: 5px;
            font-size: 12px;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="header">
        <img src="../assets/img/logo.jpg" alt="KMK Logo">
        <h2>KMK GLOBAL LIMITED</h2>
        <div class="header-right">
            <h3>PURCHASE ORDER</h3>
            <p>Date: <?= date("d.m.Y", strtotime($po['po_date'])) ?><br>
               Order No: <?= htmlspecialchars($po['order_number']) ?><br>
               <strong>Total : ₹ <?= number_format($po['total_amount'] + $po['transportation'], 2) ?></strong></p>
        </div>
        <div style="clear: both;"></div>
    </div>

    <!-- Buyer & Vendor Info -->
    <table class="section">
        <tr>
            <td width="50%">
                <strong class="ms-2">Buyer Details :</strong><br>
                KMK GLOBAL LIMITED<br>
                71-4-8/1, Ground Floor, Vijayawada,<br>
                Andhra Pradesh, 520007, INDIA
            </td>
            <td width="50%">
                <strong class="ms-2">Vendor Details :</strong><br>
                <?= htmlspecialchars($po['vendor_name']) ?><br>
                <?= nl2br(htmlspecialchars($po['vendor_address'])) ?><br>
                <?= htmlspecialchars($po['vendor_phone']) ?>
            </td>
        </tr>
    </table>


    <div style="margin-top: 20px;">
        <strong>Remarks</strong>
        <br>
        <p><?=$po['remarks'] ?></p>
    </div>
    <p><strong>Due Date:</strong> <?= date("d.m.Y", strtotime($po['expected_delivery_date'])) ?></p>

    <!-- Items Table -->
    <table>
        <thead>
            <tr>
                <th style="width:5%">S.No</th>
                <th style="width:25%">Item Name</th>
                <th style="width:10%">Unit Price</th>
                <th style="width:10%">Quantity</th>
                <th style="width:10%">Total</th>
                <th style="width:10%">Discount (%)</th>
                <th style="width:15%">Discount Amount</th>
                <th style="width:15%">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php $i=1; foreach($items as $item): 
                $total = $item['quantity'] * $item['unit_price'];
                $discountAmt = $item['discount_amount'] ?? 0;
                $finalAmt = $item['subjective_amount'] ?? ($total - $discountAmt);

                $totalQty += $item['quantity'];
                $totalAmt += $total;
                $totalDiscount += $discountAmt;
                $totalFinal += $finalAmt;
            ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($item['item_name']) ?></td>
                <td class="text-right"><?= number_format($item['unit_price'], 2) ?></td>
                <td class="text-right"><?= $item['quantity'] ?></td>
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
                <td class="text-right"><?= number_format($po['transportation'], 2) ?></td>
            </tr>
            <tr class="totals">
                <td colspan="7" class="text-right"><strong>Grand Total</strong></td>
                <td class="text-right"><strong><?= number_format($totalFinal + $po['transportation'], 2) ?></strong></td>
            </tr>
        </tfoot>
    </table>

    

    <div class="footer">
        71-4-8/1, Ground Floor, Vijayawada, Andhra Pradesh, 520007, INDIA
    </div>

</body>
</html>
