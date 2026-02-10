<?php
/**
 * FINANCE: Payment Voucher Print
 * UPDATED: Integrated Indian Currency to Words conversion and layout from print_voucher1.php.
 */
require_once("../functions.php");
require_once("../auth.php");
requireRole(['Finance', 'Admin']);

function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

/**
 * Indian Number to Words Conversion Logic
 */
function inr_words(float $amount): string {
    $amount = round($amount, 2);
    $rupees = (int)$amount;
    $paise = round(($amount - $rupees) * 100);

    $convert = function($n) {
        $ones = ["", "One", "Two", "Three", "Four", "Five", "Six", "Seven", "Eight", "Nine", "Ten", "Eleven", "Twelve", "Thirteen", "Fourteen", "Fifteen", "Sixteen", "Seventeen", "Eighteen", "Nineteen"];
        $tens = ["", "", "Twenty", "Thirty", "Forty", "Fifty", "Sixty", "Seventy", "Eighty", "Ninety"];
        if ($n < 20) return $ones[$n];
        if ($n < 100) return $tens[(int)($n / 10)] . ($n % 10 ? " " . $ones[$n % 10] : "");
        if ($n < 1000) return $ones[(int)($n / 100)] . " Hundred" . ($n % 100 ? " and " . inr_words($n % 100) : "");
        return "";
    };

    $res = "";
    if ($rupees >= 10000000) { $res .= inr_words((int)($rupees / 10000000)) . " Crore "; $rupees %= 10000000; }
    if ($rupees >= 100000) { $res .= inr_words((int)($rupees / 100000)) . " Lakh "; $rupees %= 100000; }
    if ($rupees >= 1000) { $res .= inr_words((int)($rupees / 1000)) . " Thousand "; $rupees %= 1000; }
    if ($rupees >= 100) { $res .= inr_words((int)($rupees / 100)) . " Hundred "; $rupees %= 100; }
    
    // Tiny helper for small digits
    $ones = ["", "One", "Two", "Three", "Four", "Five", "Six", "Seven", "Eight", "Nine", "Ten", "Eleven", "Twelve", "Thirteen", "Fourteen", "Fifteen", "Sixteen", "Seventeen", "Eighteen", "Nineteen"];
    $tens = ["", "", "Twenty", "Thirty", "Forty", "Fifty", "Sixty", "Seventy", "Eighty", "Ninety"];
    if ($rupees > 0) {
        if ($rupees < 20) $res .= $ones[$rupees];
        else $res .= $tens[(int)($rupees / 10)] . ($rupees % 10 ? " " . $ones[$rupees % 10] : "");
    }

    $final = trim($res) . " Rupees";
    if ($paise > 0) $final .= " and " . trim($convert((int)$paise)) . " Paise";
    return $final . " Only";
}

/* -------------------- Data Fetching -------------------- */
$id    = i($_GET['id']);
$type  = s($_GET['type']);
$v_no  = s($_GET['voucher']);

$data = null;
$where = $id > 0 ? "p.id = $id" : "p.voucher_no = '" . addslashes($v_no) . "'";

if ($type === 'vendor') {
    $sql = "SELECT p.*, v.vendor_name, v.account_number, v.ifsc, g.grn_number 
            FROM vendor_grn_payments p 
            LEFT JOIN vendors v ON v.vendor_id = p.vendor_id 
            LEFT JOIN goods_receipts g ON g.grn_id = p.grn_id WHERE $where LIMIT 1";
    $r = exeSql($sql);
    if ($r) {
        $row = $r[0];
        $data = [
            'party' => $row['vendor_name'], 'voucher' => $row['voucher_no'], 'date' => $row['paid_at'],
            'bank' => $row['account_number'] ?: $row['bank_account'], 'ifsc' => $row['ifsc'] ?: $row['bank_ifsc'],
            'purpose' => "Vendor Payment for GRN: " . ($row['grn_number'] ?: 'N/A'),
            'mode' => $row['method'], 'ref' => $row['payment_reference'], 'by' => $row['payment_by'],
            'amount' => (float)$row['amount'], 'adjustment' => (float)$row['advance_used'] + (float)$row['redemption_used']
        ];
    }
} elseif ($type === 'employee') {
    $sql = "SELECT p.*, e.employee_name, e.bank_name, e.ifsc_code FROM employee_salary_payments p 
            LEFT JOIN employees e ON e.id = p.employee_id WHERE $where LIMIT 1";
    $r = exeSql($sql);
    if ($r) {
        $row = $r[0];
        $data = [
            'party' => $row['employee_name'], 'voucher' => $row['voucher_no'], 'date' => $row['paid_at'],
            'bank' => $row['bank_name'], 'ifsc' => $row['ifsc_code'],
            'purpose' => "Staff Salary: " . $row['pay_period'],
            'mode' => 'Bank Transfer', 'ref' => 'Disbursed', 'by' => $row['payment_by'],
            'amount' => (float)$row['amount'], 'adjustment' => (float)$row['advance']
        ];
    }
}

if (!$data) die("Voucher record not found.");
$total = $data['amount'] + $data['adjustment'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Voucher - <?= h($data['voucher']) ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; font-size: 13px; line-height: 1.6; padding: 40px; background: #f0f0f0; }
        .voucher { width: 800px; margin: auto; background: #fff; border: 1px solid #000; padding: 30px; position: relative; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 20px; }
        .logo { position: absolute; left: 30px; top: 30px; width: 80px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; border-bottom: 1px solid #000; padding: 10px 0; }
        .label { font-size: 10px; font-weight: 800; color: #666; text-transform: uppercase; margin-bottom: 5px; }
        .value { font-weight: 700; font-size: 14px; }
        .tbl { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .tbl th, .tbl td { border: 1px solid #000; padding: 12px; }
        .tbl th { background: #f8f8f8; text-align: left; font-size: 11px; }
        .total-row { background: #f0f0f0; font-weight: 800; font-size: 16px; }
        .sign-row { display: flex; justify-content: space-between; margin-top: 80px; text-align: center; }
        .sign-box { width: 22%; border-top: 1px solid #000; padding-top: 10px; font-size: 11px; font-weight: bold; }
        @media print { .no-print { display: none; } body { padding: 0; background: #fff; } .voucher { box-shadow: none; border: 1px solid #000; width: 100%; } }
    </style>
</head>
<body>
    <div class="no-print" style="text-align:center; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding:10px 40px; font-weight:bold; background:#198754; color:#fff; border:none; cursor:pointer; border-radius:30px;">PRINT VOUCHER</button>
    </div>
    
    <div class="voucher">
        <div class="header">
            <img src="../assets/img/logo.jpg" class="logo">
            <h2 style="margin:0;">KMK GLOBAL LIMITED</h2>
            <div style="font-size:11px;">71-4-8A/1, Ground Floor, Vijayawada - 520 007</div>
            <div style="margin-top:15px; text-decoration:underline; font-weight:bold; letter-spacing:2px;">PAYMENT VOUCHER</div>
        </div>

        <div class="grid">
            <div><div class="label">Payee / Recipient</div><div class="value"><?= h($data['party']) ?></div></div>
            <div style="text-align:right;"><div class="label">Voucher No</div><div class="value"><?= h($data['voucher']) ?></div></div>
        </div>

        <div class="grid">
            <div><div class="label">Bank Account Details</div><div>A/c: <?= h($data['bank']) ?> | IFSC: <?= h($data['ifsc']) ?></div></div>
            <div style="text-align:right;"><div class="label">Date of Payment</div><div class="value"><?= date('d-M-Y', strtotime($data['date'])) ?></div></div>
        </div>

        <div class="grid" style="border:none;">
            <div><div class="label">Purpose</div><div><?= h($data['purpose']) ?></div></div>
            <div style="text-align:right;"><div class="label">Mode / Reference</div><div><?= h(ucfirst($data['mode'])) ?> / <?= h($data['ref']) ?></div></div>
        </div>

        <table class="tbl">
            <thead><tr><th style="width:75%;">Description</th><th style="text-align:right;">Amount (₹)</th></tr></thead>
            <tbody>
                <tr><td>Direct Cash / Bank Disbursement</td><td style="text-align:right;"><?= number_format($data['amount'], 2) ?></td></tr>
                <tr><td>Internal Adjustments (Advance/Redemption)</td><td style="text-align:right;"><?= number_format($data['adjustment'], 2) ?></td></tr>
                <tr class="total-row"><td style="text-align:right;">TOTAL PAYABLE VALUE</td><td style="text-align:right;">₹<?= number_format($total, 2) ?></td></tr>
            </tbody>
        </table>

        <div style="margin-top:20px; font-weight:bold;">
            Amount in words: <span style="font-style:italic; font-weight:normal;"><?= inr_words($total) ?></span>
        </div>

        <div class="sign-row">
            <div class="sign-box">Requested by</div>
            <div class="sign-box">Verified by</div>
            <div class="sign-box">Payment by (<?= h($data['by']) ?>)</div>
            <div class="sign-box">Receiver Sign</div>
        </div>
    </div>
</body>
</html>