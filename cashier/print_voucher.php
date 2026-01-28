<?php
/**
 * CASHIER: Payment Voucher Print
 * FIXED: Dynamic WHERE clause construction to handle empty Voucher/Invoice parameters.
 * FIXED: Improved lookup resilience and added debug info for "Record Not Found" scenarios.
 */

require_once(__DIR__ . '/../functions.php');
require_once("../auth.php");
requireRole(['Cashier', 'Admin']);

// Set error reporting for debugging if needed, but keep display off for production feel
ini_set('display_errors', '0');
error_reporting(E_ALL);

function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

/**
 * Indian Number to Words Conversion
 */
function rupees_in_words(float $amount): string {
    $rupees = (int)floor($amount + 0.00001);
    if ($rupees === 0) return 'Zero rupees only';
    
    $ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
    $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
    
    $n = $rupees;
    $parts = [];
    
    // Crore
    $crore = intdiv($n, 10000000); $n %= 10000000;
    // Lakh
    $lakh = intdiv($n, 100000); $n %= 100000;
    // Thousand
    $thou = intdiv($n, 1000); $n %= 1000;
    // Hundred
    $hundred = intdiv($n, 100); $n %= 100;
    
    $twoDigits = function($x) use($ones, $tens) {
        if ($x == 0) return '';
        if ($x < 20) return $ones[$x];
        return $tens[intdiv($x, 10)] . ($x % 10 ? ' ' . $ones[$x % 10] : '');
    };

    if ($crore > 0) {
        // Simple handling for crores up to 99
        $parts[] = $twoDigits($crore) . " crore";
    }
    if ($lakh > 0) $parts[] = $twoDigits($lakh) . " lakh";
    if ($thou > 0) $parts[] = $twoDigits($thou) . " thousand";
    if ($hundred > 0) $parts[] = $ones[$hundred] . " hundred";
    if ($n > 0) $parts[] = $twoDigits($n);
    
    $result = implode(' ', array_filter($parts));
    return ucfirst(trim($result)) . " rupees only";
}

/* -------------------- inputs -------------------- */
$id = (int)($_GET['id'] ?? 0);
$type = strtolower(trim($_GET['type'] ?? ''));
$v_no = trim($_GET['voucher'] ?? '');
$i_no = trim($_GET['invoice'] ?? '');

$data = null;
// Determine search order: if type is provided, check it first.
$ledgers = array_filter(array_unique([$type, 'vendor', 'employee', 'expense']));

foreach ($ledgers as $t) {
    // Build dynamic conditions to avoid the "'' != ''" logic error
    $conditions = [];
    if ($id > 0) {
        $conditions[] = "p.id = $id";
    } else {
        if ($v_no !== '') $conditions[] = "p.voucher_no = '" . addslashes($v_no) . "'";
        if ($i_no !== '') $conditions[] = "p.invoice_no = '" . addslashes($i_no) . "'";
    }

    if (empty($conditions)) continue;
    $where_clause = "(" . implode(" OR ", $conditions) . ")";

    if ($t === 'vendor') {
        $sql = "SELECT p.*, v.vendor_name, v.account_number, v.ifsc, g.grn_number FROM vendor_grn_payments p 
                LEFT JOIN vendors v ON v.vendor_id = p.vendor_id 
                LEFT JOIN goods_receipts g ON g.grn_id = p.grn_id 
                WHERE $where_clause LIMIT 1";
                // echo $sql;
        $r = exeSql($sql);
        if ($r) {
            $row = $r[0];
            $data = [
                'title' => 'PAYMENT ADVICE', 
                'party' => $row['vendor_name'], 
                'v_no' => $row['voucher_no'], 
                'dt' => $row['paid_at'],
                'ac' => ($row['account_number'] ?: $row['bank_account']), 
                'ifsc' => ($row['ifsc'] ?: $row['bank_ifsc']),
                'purpose' => 'GRN Payment: ' . ($row['grn_number'] ?: $row['invoice_no']), 
                'method' => $row['method'], 
                'ref' => $row['payment_reference'],
                'breakdown' => [
                    'Gross Invoice Amount' => (float)$row['amount'] + (float)$row['advance_used'] + (float)$row['redemption_used'], 
                    '(-) Advance Adjusted' => -(float)$row['advance_used'], 
                    '(-) Redemption Used' => -(float)$row['redemption_used'],
                    'NET PAID' => (float)$row['amount']
                ],
                'total' => (float)$row['amount']
            ];
            break;
        }
    }
    
    if ($t === 'employee') {
        // Note: For employees, p is employee_salary_payments. Alias conditions for 'p' works.
        $sql = "SELECT p.*, e.employee_name, e.bank_name, e.ifsc_code FROM employee_salary_payments p 
                LEFT JOIN employees e ON e.id = p.employee_id 
                WHERE $where_clause LIMIT 1";
        $r = exeSql($sql);
        if ($r) {
            $row = $r[0];
            $data = [
                'title' => 'PAYSLIP', 
                'party' => $row['employee_name'], 
                'v_no' => $row['voucher_no'], 
                'dt' => $row['paid_at'],
                'ac' => $row['bank_name'], 
                'ifsc' => $row['ifsc_code'], 
                'purpose' => 'Salary Disbursal: ' . $row['pay_period'],
                'method' => 'Bank Transfer', 
                'ref' => 'Disbursed',
                'breakdown' => [
                    'Earnings Gross' => (float)$row['amount'] + (float)$row['advance'], 
                    '(-) Advance Recovery' => -(float)$row['advance'], 
                    'NET TAKE HOME' => (float)$row['amount']
                ],
                'total' => (float)$row['amount']
            ];
            break;
        }
    }
    
    if ($t === 'expense') {
        // Expenses uses 'e' as alias in some queries, but here we use 'p' to match the $where_clause builder
        $sql = "SELECT p.* FROM expenses p WHERE $where_clause LIMIT 1";
        $r = exeSql($sql);
        if ($r) {
            $row = $r[0];
            $data = [
                'title' => 'VOUCHER', 
                'party' => $row['purpose'], 
                'v_no' => $row['voucher_no'], 
                'dt' => $row['paid_at'],
                'ac' => $row['account_no'], 
                'ifsc' => $row['ifsc_code'], 
                'purpose' => $row['remark'] ?: $row['purpose'], 
                'method' => $row['method'], 
                'ref' => $row['payment_reference'],
                'breakdown' => [
                    'Gross Amount' => (float)$row['amount'] + (float)$row['advance'], 
                    '(-) Adjust' => -(float)$row['advance'], 
                    'NET DISBURSED' => (float)$row['amount']
                ],
                'total' => (float)$row['amount']
            ];
            break;
        }
    }
}

if (!$data): ?>
<!doctype html>
<html>
<head>
    <title>Error - Record Not Found</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 50px; background: #f8f9fa; }
        .error-container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); display: inline-block; max-width: 500px; width: 100%; }
        h2 { color: #dc3545; }
        p { color: #6c757d; margin-bottom: 25px; }
        .debug-info { text-align: left; background: #eee; padding: 15px; font-size: 11px; font-family: monospace; border-radius: 6px; margin-top: 20px; }
        .btn { text-decoration: none; background: #007bff; color: white; padding: 10px 20px; border-radius: 4px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="error-container">
        <h2>Record Not Found</h2>
        <p>We could not locate the payment record based on the provided parameters.</p>
        <a href="payments_view.php" class="btn">Back to Ledger</a>
        
        <div class="debug-info">
            <strong>Debug Parameters:</strong><br>
            ID: <?= (int)$id ?><br>
            Type Hint: <?= h($type) ?><br>
            Voucher: <?= h($v_no) ?><br>
            Invoice: <?= h($i_no) ?>
        </div>
    </div>
</body>
</html>
<?php exit; endif; ?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= $data['title'] ?> - KMK</title>
<style>
    body { font-family: sans-serif; font-size: 13px; line-height: 1.5; color: #333; background: #f4f7f6; }
    .wrap { width: 800px; margin: 20px auto; border: 1.5px solid #000; padding: 0; background: #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
    .header { text-align: center; border-bottom: 1.5px solid #000; padding: 20px; position: relative; }
    .logo { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); width: 70px; }
    .row { display: flex; border-bottom: 1.5px solid #000; }
    .cell { flex: 1; padding: 12px; border-left: 1.5px solid #000; }
    .cell:first-child { border-left: 0; }
    .tbl { width: 100%; border-collapse: collapse; margin-top: -1px; }
    .tbl th, .tbl td { border-bottom: 1px solid #000; border-left: 1.5px solid #000; padding: 10px; }
    .tbl th:first-child, .tbl td:first-child { border-left: 0; }
    .footer { padding: 20px; }
    .sign-row { display: flex; justify-content: space-between; margin-top: 60px; text-align: center; }
    .sign-box { width: 22%; border-top: 1px solid #000; padding-top: 8px; font-size: 11px; font-weight: bold; color: #555; }
    .bg-gray { background: #f8fafb; }
    @media print { 
        .no-print { display: none; } 
        .wrap { margin: 0; border: none; width: 100%; } 
        body { background: #fff; }
    }
</style>
</head>
<body>
<div class="no-print" style="text-align:center; padding:20px;">
    <button onclick="window.print()" style="padding:10px 25px; cursor:pointer; background: #28a745; color: white; border: none; border-radius: 30px; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">🖨️ Print Document</button>
    <a href="payments_view.php" style="margin-left:15px; color: #007bff; text-decoration: none; font-weight: bold;">← Back to Ledger</a>
</div>

<div class="wrap">
    <!-- Header -->
    <div class="header">
        <img src="../assets/img/logo.jpg" class="logo" onerror="this.style.display='none'">
        <h2 style="margin:0; letter-spacing: 1.5px;">KMK GLOBAL LIMITED</h2>
        <div style="font-size:11px; color: #555;">71-4-8A/1, Ground Floor, VIJAYAWADA - 520 007</div>
        <div style="margin-top:15px; font-weight:bold; text-decoration:underline; font-size:15px; letter-spacing: 2px;"><?= h($data['title']) ?></div>
    </div>

    <!-- Recipient and Doc Info -->
    <div class="row">
        <div class="cell"><strong>RECIPIENT / NAME</strong><br><span style="font-size:16px; font-weight:bold;"><?= h($data['party']) ?></span></div>
        <div class="cell"><strong>DOC / VOUCHER NO.</strong><br><span style="font-size:14px; font-weight:bold; color: #007bff;"><?= h($data['v_no'] ?: 'N/A') ?></span></div>
    </div>

    <!-- Bank and Date -->
    <div class="row">
        <div class="cell bg-gray"><strong>BANK DETAILS</strong><br>A/c: <?= h($data['ac'] ?: 'N/A') ?><br>IFSC: <?= h($data['ifsc'] ?: 'N/A') ?></div>
        <div class="cell"><strong>PAYMENT DATE</strong><br><span style="font-weight:bold;"><?= date('d-M-Y', strtotime($data['dt'])) ?></span></div>
    </div>

    <!-- Purpose and Mode -->
    <div class="row">
        <div class="cell"><strong>PURPOSE / NOTES</strong><br><?= h($data['purpose']) ?></div>
        <div class="cell bg-gray"><strong>PAYMENT INFO</strong><br>Mode: <?= h(ucfirst($data['method'])) ?><br>Ref: <?= h($data['ref'] ?: 'N/A') ?></div>
    </div>

    <!-- Breakdown -->
    <table class="tbl">
        <thead>
            <tr style="background:#f2f2f2;">
                <th style="text-align:left; width:70%; font-size:11px; text-transform:uppercase;">COMPONENT / DESCRIPTION</th>
                <th style="text-align:right; width:30%; font-size:11px; text-transform:uppercase;">AMOUNT (₹)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($data['breakdown'] as $label => $val): ?>
                <tr>
                    <td class="<?= (strpos($label, 'NET') !== false) ? 'bg-gray' : '' ?>">
                        <?= h($label) ?>
                    </td>
                    <td style="text-align:right;" class="<?= (strpos($label, 'NET') !== false) ? 'bg-gray' : '' ?>">
                        <span class="bold">₹<?= number_format(abs($val), 2) ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php for($i=count($data['breakdown']); $i<3; $i++): ?>
                <tr><td>&nbsp;</td><td>&nbsp;</td></tr>
            <?php endfor; ?>
        </tbody>
    </table>

    <!-- Net Total -->
    <div class="row" style="font-weight:bold; background:#eee; font-size:14px;">
        <div class="cell" style="text-align:right; border-right:1.5px solid #000; flex:none; width:70%;">TOTAL DISBURSED AMOUNT</div>
        <div class="cell" style="text-align:right;">₹<?= number_format($data['total'], 2) ?></div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div style="font-weight:bold; margin-bottom: 10px;">Rupees In Words: <span style="font-style:italic; font-weight:normal; text-transform: capitalize; color: #555;"><?= rupees_in_words($data['total']) ?></span></div>
        
        <div class="sign-row">
            <div class="sign-box">Requested by</div>
            <div class="sign-box">Verified by</div>
            <div class="sign-box">Finance Manager</div>
            <div class="sign-box">Receiver's Signature</div>
        </div>
    </div>
</div>

<div class="no-print" style="text-align:center; color: #888; font-size: 11px; margin-top: 20px;">
    This is a computer-generated document. No physical signature required for standard verification.
</div>

</body>
</html>