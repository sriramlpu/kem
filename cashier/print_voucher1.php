<?php





require_once(__DIR__ . '/../functions.php');

require_once("../auth.php");

requireRole(['Cashier','Admin']);

ini_set('display_errors','0');

ini_set('log_errors','1');

error_reporting(E_ALL);



/* -------------------- helpers -------------------- */

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

function t_exists(string $t): bool {

    $t = preg_replace('/[^a-zA-Z0-9_]/','',$t);

    $r = exeSql("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' LIMIT 1");

    return !empty($r);

}

function t_cols(string $t): array {

    $t = preg_replace('/[^a-zA-Z0-9_]/','',$t);

    $rows = exeSql("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t'");

    $o=[]; foreach ($rows as $r){ $o[$r['COLUMN_NAME']] = true; } return $o;

}



/* ---- integer (rupees) to Indian words ---- */

function inr_words_int(int $n): string {

    if ($n === 0) return 'zero';

    $ones = ['','one','two','three','four','five','six','seven','eight','nine','ten','eleven','twelve','thirteen','fourteen','fifteen','sixteen','seventeen','eighteen','nineteen'];

    $tens = ['','','twenty','thirty','forty','fifty','sixty','seventy','eighty','ninety'];



    $parts = [];

    $crore       = intdiv($n, 10000000); $n %= 10000000;

    $lakh        = intdiv($n, 100000);    $n %= 100000;

    $thou        = intdiv($n, 1000);      $n %= 1000;

    $hundred = intdiv($n, 100);          $n %= 100;



    $twoDigits = function(int $x) use($ones,$tens){

        if ($x===0) return '';

        if ($x<20) return $ones[$x];

        $t = $tens[intdiv($x,10)];

        $o = $x%10 ? ' '.$ones[$x%10] : '';

        return $t.$o;

    };



    if ($crore)       $parts[] = inr_words_int($crore).' crore';

    if ($lakh)         $parts[] = inr_words_int($lakh).' lakh';

    if ($thou)         $parts[] = inr_words_int($thou).' thousand';

    if ($hundred) $parts[] = $ones[$hundred].' hundred';

    if ($n)           $parts[] = $twoDigits($n);



    return trim(preg_replace('/\s+/', ' ', implode(' ', $parts)));

}

function rupees_in_words(float $amount): string {

    $rupees = (int)floor($amount + 0.00001);

    if ($rupees === 0) return 'Zero rupees only';

    $w = ucfirst(inr_words_int($rupees));

    return $w.' rupees only';

}



// NEW HELPER: Get the name of a user based on their user_id

function get_user_name(int $user_id): string {

    if ($user_id <= 0 || !t_exists('users')) return 'N/A';

    $r = exeSql("SELECT username FROM users WHERE user_id = $user_id LIMIT 1");

    return $r[0]['username'] ?? 'N/A';

}



/* -------------------- inputs -------------------- */

$type          = strtolower(trim($_GET['type']           ?? ''));

$voucher = trim($_GET['voucher'] ?? '');

$invoice = trim($_GET['invoice'] ?? '');

$url_description = trim($_GET['description'] ?? ''); 



// NEW: Capture the specific GRN Note and the Reference Number from the URL

$grn_note = trim($_GET['grn_note'] ?? '');

$ref_number_url = trim($_GET['ref_number'] ?? ''); // <<-- CAPTURE NEW REFERENCE



$specific_grn = '';

if (preg_match('/GRN: (.*)/', $grn_note, $matches)) {

    // Extract only the GRN number(s) from the note (e.g., '12345')

    $specific_grn = trim($matches[1]);

}



// CRITICAL FIX: Capture the amount paid for accurate database lookup

$amount_paid_filter = (float)($_GET['amount'] ?? 0);



// Capture Used Advance and Used Redemption correctly from $_GET (sent from list view)

$advance_used = (float)($_GET['adv_used'] ?? 0); 

$redemption_used = (float)($_GET['red_used'] ?? 0); 

// NEW: Capture total bill amount from list view (if available, fallback to manual calc)

$total_bill_amount_filter = (float)($_GET['total_bill_amount'] ?? ($amount_paid_filter + $advance_used + $redemption_used));



/* -------------------- table presence -------------------- */

$exists = [

    'vendor'   => t_exists('vendor_grn_payments'),

    'employee' => t_exists('employee_salary_payments'),

    'expense'  => t_exists('expenses'),

    'event'    => t_exists('event_items'),

];

if (!$type || empty($exists[$type])) {

    foreach (['vendor','employee','expense','event'] as $try) {

        if ($exists[$try]) { $type = $try; break; }

    }

}



/**

 * Returns:

 * party, voucher_no, invoice_no, dt, amount, note, payment_by, method,

 * branch_name, purpose, grn_list, account_number, ifsc_code, ref_number, bank_mode, requested_by_user, approved_by_user, payment_by_user

 */

function load_row(string $type, string $voucher, string $invoice, float $amount_paid, string $specific_grn_filter): array {

    $voucher = addslashes($voucher);

    $invoice = addslashes($invoice);

    $amount_str = number_format($amount_paid, 2, '.', ''); // Use formatted string for comparison



    /* helper to build WHERE: (voucher OR invoice) if provided, else latest row */

    $buildWhere = function(array $cols, string $alias) use ($voucher, $invoice, $amount_str): string {

        $conds = [];

        if ($voucher !== '' && isset($cols['voucher_no'])) $conds[] = "$alias.voucher_no='$voucher'";

        if ($invoice !== '' && isset($cols['invoice_no'])) $conds[] = "$alias.invoice_no='$invoice'";

        

        // CRITICAL FIX: Add amount to WHERE clause for unique identification (amount is the net cash/bank paid)

        if ($amount_str > 0) $conds[] = "ABS($alias.amount - $amount_str) < 0.005"; 



        if (!$conds) return '1=1'; 

        return '(' . implode(' AND ', $conds) . ')'; 

    };



    /* ---- vendor (Most complex case) ---- */

    if ($type==='vendor' && t_exists('vendor_grn_payments')) {

        $VG = t_cols('vendor_grn_payments');

        $V = t_cols('vendors');



        $sel_invoice = isset($VG['invoice_no']) ? 'p.invoice_no' : 'NULL';

        $sel_voucher = isset($VG['voucher_no']) ? 'p.voucher_no' : 'NULL';

        $sel_note    = isset($VG['remark']) ? 'p.remark' : (isset($VG['note']) ? 'p.note' : 'NULL');

        $sel_method  = isset($VG['method']) ? 'p.method' : 'NULL';

        $sel_payment = isset($VG['payment_by']) ? 'p.payment_by' : 'NULL'; // Raw payment_by (can be ID or name)

        $sel_branch  = (t_exists('branches') && isset($VG['branch_id'])) ? 'b.branch_name' : 'NULL';

        

        $joinV = t_exists('vendors') ? "LEFT JOIN vendors v ON v.vendor_id = p.vendor_id" : "";

        $name  = t_exists('vendors') ? "v.vendor_name" : "CONCAT('Vendor #',p.vendor_id)";

        $joinB = (t_exists('branches') && isset($VG['branch_id'])) ? "LEFT JOIN branches b ON b.branch_id = p.branch_id" : "";



        // NEW: Account details

        $sel_account = (isset($V['account_number']) && isset($VG['vendor_id'])) ? "v.account_number" : "NULL";

        $sel_ifsc    = (isset($V['ifsc']) && isset($VG['vendor_id'])) ? "v.ifsc" : "NULL";

        

        // NEW: Payment Reference/Mode - prioritize the dedicated column

        $sel_ref_num       = isset($VG['payment_reference']) ? "p.payment_reference" : (isset($VG['upi_id']) ? "p.upi_id" : (isset($VG['cheque_no']) ? "p.cheque_no" : "NULL"));

        $sel_method_type = isset($VG['bank_mode']) ? "p.bank_mode" : "NULL"; 

        

        // GRN LOGIC (fetching GRN numbers)

        $sel_grn_list = 'NULL'; $joinG = '';

        if (isset($VG['grn_id']) && t_exists('goods_receipts')) {

            $G = t_cols('goods_receipts');

            if (isset($G['grn_number'])) { $joinG = "LEFT JOIN goods_receipts g ON g.grn_id = p.grn_id"; $sel_grn_list = 'g.grn_number'; }

        }

        // END GRN LOGIC



        $where = $buildWhere($VG, 'p');



        // --- Step 1: Get the Payment Record and basic metadata ---

        // We fetch multiple matching records if voucher/amount matches but GRNs differ.

        $sql = "

            SELECT

                p.vendor_id,

                p.grn_id, /* Keep grn_id for multi-GRN grouping */

                $name AS party,

                $sel_voucher AS voucher_no,

                $sel_invoice AS invoice_no,

                p.paid_at AS dt,

                p.amount AS amount,

                p.advance_used,

                p.redemption_used,

                $sel_note AS note,

                $sel_payment AS payment_by,

                $sel_method AS method,

                $sel_branch AS branch_name,

                $sel_grn_list AS grn_list, 

                $sel_account AS account_number,    

                $sel_ifsc AS ifsc_code,            

                $sel_ref_num AS ref_number,        

                $sel_method_type AS bank_mode,     

                CASE

                    WHEN $sel_grn_list IS NOT NULL THEN CONCAT('GRN: ', $sel_grn_list)

                    WHEN ".(isset($VG['invoice_no']) ? "p.invoice_no IS NOT NULL" : "0")." THEN CONCAT('Invoice: ', $sel_invoice)

                    ELSE IFNULL($sel_note,'')

                END AS purpose

            FROM vendor_grn_payments p

            $joinV

            $joinB

            $joinG

            WHERE $where

            ORDER BY p.paid_at DESC

            LIMIT 100";

        $r = exeSql($sql);

        if (empty($r)) return [];

        $data_list = $r; // Store all matching results



        // --- Step 2: Filter results by specific GRN if provided (CRITICAL FIX) ---

        if ($specific_grn_filter !== '') {

            $data = [];

            foreach ($data_list as $row) {

                // Check if the specific GRN number is present in the individual row's GRN list field

                if (strpos($row['grn_list'] ?? '', $specific_grn_filter) !== false) {

                    $data = $row;

                    break;

                }

            }

        } else {

            $data = $data_list[0]; // Fallback to the first record if no specific GRN is requested

        }

        

        if (empty($data)) return [];



        // --- Step 3: OVERRIDE GRN LIST to show ONLY the specific GRN if requested ---

        if ($specific_grn_filter !== '') {

            $data['grn_list'] = $specific_grn_filter;

            $data['purpose'] = 'GRN Payment for GRN: ' . $specific_grn_filter;

        } else if (isset($data['grn_list'])) {

             // For cases where there is no specific GRN filter but a GRN is present

             $data['purpose'] = 'GRN Payment for GRN: ' . $data['grn_list'];

        }

        

        // --- Step 4: Fetch Requested/Approved By and Payment By Names ---

        $data['requested_by_user'] = 'N/A';

        $data['approved_by_user'] = 'N/A';

        $data['payment_by_user'] = $data['payment_by'] ?? 'N/A'; 



        if (is_numeric($data['payment_by']) && (int)$data['payment_by'] > 0) {

            $data['payment_by_user'] = get_user_name((int)$data['payment_by']);

        }





        if (t_exists('payment_requests')) {

            $sql_pr = "

                SELECT

                    pr.requested_by, pr.approved_by, pr.request_id, pr.request_type

                FROM payment_requests pr

                WHERE pr.status = 'PAID'

                AND pr.vendor_id = ".(int)$data['vendor_id']."

                ORDER BY pr.created_at DESC

                LIMIT 1

            ";

            

            $pr_result = exeSql($sql_pr);



            if (!empty($pr_result)) {

                $pr_data = $pr_result[0];

                $data['requested_by_user'] = get_user_name((int)$pr_data['requested_by']);

                

                $appr_id = (int)($pr_data['approved_by'] ?? 0);

                $data['approved_by_user'] = ($appr_id > 0) ? get_user_name($appr_id) : 'N/A';



                if ($data['approved_by_user'] === 'N/A' && t_exists('payment_actions')) {

                    $sql_appr = "SELECT actor_id FROM payment_actions WHERE request_id = ".(int)$pr_data['request_id']." AND action = 'APPROVE' ORDER BY acted_at DESC LIMIT 1";

                    $appr_res = exeSql($sql_appr);

                    if (!empty($appr_res)) {

                            $data['approved_by_user'] = get_user_name((int)$appr_res[0]['actor_id']);

                    }

                }

            }

        }

        

        return $data;

    }



    return [];

}





/* -------------------- fetch/normalize -------------------- */

// Fallback for ref_number if loading row fails, takes the value passed from the list view URL

$ref_number_fallback = $ref_number_url ?: 'N/A'; 

$data = load_row($type, $voucher, $invoice, $amount_paid_filter, $specific_grn);



if (empty($data)) {

    // Basic fallback if data loading fails completely

    $data = [

        'party' => 'N/A', 'voucher_no' => $voucher, 'invoice_no' => $invoice, 'dt' => date('Y-m-d H:i:s'), 

        'amount' => $amount_paid_filter, 'advance_used' => $advance_used, 'redemption_used' => $redemption_used, 

        'note' => 'Error fetching details.', 'payment_by' => 'N/A', 'method' => 'N/A', 'branch_name' => 'N/A',

        'account_number' => 'N/A', 'ifsc_code' => 'N/A', 'ref_number' => $ref_number_fallback, 'bank_mode' => 'N/A', // Use fallback reference

        'requested_by_user' => 'N/A', 'approved_by_user' => 'N/A', 'purpose' => 'Payment for: ' . h($invoice),

        'grn_list' => $specific_grn ?: 'N/A', 'payment_by_user' => 'N/A' // Use specific_grn in fallback

    ];

}



$party           = $data['party']            ?? 'N/A';

$voucher_no      = $data['voucher_no']         ?? '';

$invoice_no      = $data['invoice_no']         ?? '';

$dt              = $data['dt']               ?? date('Y-m-d');

$branch_name     = $data['branch_name']      ?? 'N/A';



// Amounts

$total_bill_amount = $total_bill_amount_filter; 



// MODIFIED: Round off the total amount to the nearest whole rupee

$total_rupees_whole = (int)round($total_bill_amount); 

$total_paise         = 0; // Paie is now always zero if we round off the whole amount

$amount_words = rupees_in_words((float)$total_rupees_whole);



// Payment details

$payment_by_user = $data['payment_by_user'] ?? 'N/A'; 

$method          = $data['method']           ?? 'N/A';

$bank_mode       = $data['bank_mode']        ?? 'N/A';

$ref_number      = $data['ref_number']       ?? $ref_number_fallback; // Use DB value, fallback to URL value

$account_number  = $data['account_number']   ?? 'N/A';

$ifsc_code       = $data['ifsc_code']        ?? 'N/A';

$purpose         = $data['purpose']          ?? 'N/A';

$grn_list_print  = $data['grn_list']         ?? 'N/A';



// User names

$requested_by_user = $data['requested_by_user'] ?? 'N/A';

$approved_by_user  = $data['approved_by_user'] ?? 'N/A';



$payment_mode_display = ucfirst($method);

if ($method === 'bank' && $bank_mode && $bank_mode !== $method) {

    $payment_mode_display = ucfirst($bank_mode);

}



$total_bill_rupees_str = number_format($total_rupees_whole, 0, '.', '');

$total_bill_paise_str = str_pad(number_format($total_paise, 0, '.', ''), 2, '0', STR_PAD_LEFT);

$logoUrl = "../assets/img/logo.jpg" 

?>

<!doctype html>

<html>

<head>

<meta charset="utf-8">

<title>Payment Voucher</title>

<meta name="viewport" content="width=device-width, initial-scale=1">

<style>

    body { font-family: Arial, Helvetica, sans-serif; background:#fff; }

    .container { width: 900px; margin: 14px auto; }

    .toolbar { margin-bottom: 12px; }

    .wrap { width: 820px; margin: 0 auto; border:1px solid #333; }

    .row { display:flex; }

    .cell { border-bottom:1px solid #333; border-left:1px solid #333; padding:8px; }

    .row > .cell:first-child { border-left:0; }

    .header { border-bottom:1px solid #333; padding:10px; text-align:center; position:relative; }

    .logo { 

        position:absolute; 

        left:12px; 

        top:50%; 

        transform: translateY(-50%); 

        max-width: 80px; 

        max-height: 80px; 

        display:flex;  

        align-items:center;  

        justify-content:center;  

    }

    .logo img { max-width:100%; max-height:100%; object-fit:contain; }

    .logo .fallback { font-weight:bold; font-size:28px; }

    .w50 { width:50%; }

    .tbl { width:100%; border-collapse:collapse; }

    .tbl th,.tbl td { border-top:1px solid #333; border-bottom:1px solid #333; border-left:1px solid #333; padding:6px; }

    .tbl th:first-child,.tbl td:first-child{ border-left:0; }

    .muted { color:#666; }

    .footer { padding:10px 12px; }

    /* MODIFIED: Adjusted width for 4 signatures in the same line */

    .sign { width:24%; display:inline-block; text-align:center; margin-top:24px; } 

    .sign .name { font-size:12px; color:#222; display:block; margin-bottom:2px; min-height:16px; }

    .line { border-top:1px solid #333; margin:18px 8px 4px; }

    @media print { .toolbar { display:none; } .container{ width:auto; margin:0; } .wrap{ margin:0 auto; } }

    .topline { border-top:1px solid #333; }

    .btn { padding:6px 10px; border:1px solid #aaa; border-radius:4px; text-decoration:none; background:#f3f3f3; color:#222; }

    .total-row { border-top: 2px solid #333 !important; }

    

    /* MODIFIED: Ensure Total Row and Table header line up perfectly using the 70%/30% ratio */

    .grand-total-row { border-top: 2px solid #333 !important; }

    .total-label-cell { width:70%; border-right:1px solid #333; }

    .total-amount-cell { width:30%; }



    /* New style for combined RS. and PS. column */

    .combined-rs-ps { text-align:right; font-weight:bold; padding-right: 15px; } 

    .combined-label { float:left; width: 70%; text-align: left;}

    .combined-amount { float:right; width: 30%; text-align: right;}

    

    /* ADDED: Style for increased GRN number size */

    .grn-list-size { font-size: 13px; font-weight: bold; color: #000; }

</style>

</head>

<body>

<div class="container">

    <div class="toolbar">

        <button onclick="window.print()" class="btn">🖨️ Print Voucher</button>

        <a href="payments_view" class="btn">↶ Back to Payments</a>

    </div>



    <div class="wrap">

        <div class="header">

            <div class="logo">

                <?php if ($logoUrl): ?>

                    <img src="<?= h($logoUrl) ?>" alt="Logo">

                <?php else: ?>

                    <div class="fallback">KMK</div>

                <?php endif; ?>

            </div>

            <h2 style="margin:4px 0;">KMK GLOBAL LIMITED</h2>

            <div class="muted" style="font-size:12px;">71-4-8A/1, Ground Floor, VIJAYAWADA - 520 007, www.kmklimited.com</div>

        </div>



        <div class="row topline">

            <div class="cell w50"><strong>PAID TO</strong><br><?= h($party) ?></div>

            <div class="cell w50"><strong>VOUCHER NO.</strong><br><?= h($voucher_no !== '' ? $voucher_no : 'N/A') ?></div>

        </div>

        

        <div class="row">

            <div class="cell w50" style="padding-top:4px; padding-bottom:4px; font-size:12px;">

                <strong>ACCOUNT DETAILS WITH IFSC CODE</strong><br>

                A/c No: <?= h($account_number) ?><br>

                IFSC: <?= h($ifsc_code) ?>

            </div>

            <div class="cell w50">

                <strong>DATE</strong><br><?= h(substr($dt,0,10)) ?>

            </div>

        </div>

        

        <div class="row">

            <div class="cell w50">

                <strong>TOWARDS / PURPOSE</strong><br>

                GRN Payment

                <?php if ($grn_list_print && $grn_list_print !== 'N/A'): ?>

: <?= h($grn_list_print) ?>

                <?php endif; ?>

            </div>

            <div class="cell w50">

                <strong>PAYMENT MODE</strong><br>

                <?= h($payment_mode_display) ?>

                <br>

                <strong>PAYMENT REF NUMBER/UTR</strong><br>

                <?= h($ref_number) ?>

            </div>

        </div>



        <table class="tbl" style="border-top:0;">

            <thead>

                <tr>

                    <th style="width:70%;">HEAD OF ACCOUNT</th>

                    <th style="width:30%; text-align:right;">₹</th> </tr>

            </thead>

            <tbody>

                

                <?php 

                $row_count = 0;

                

                // 1. Display the Total Bill Amount as the Head of Account.

                if ($total_bill_amount > 0) { 

                    $row_count++;

                ?>

                    <tr>

                        <td>Vendor GRN Payment 

                            <?php if ($grn_list_print && $grn_list_print !== 'N/A'): ?>

                                <span class="grn-list-size" style="display:block;">(GRN: <?= h($grn_list_print) ?>)</span>

                            <?php endif; ?>

                        </td>

                        <td class="combined-rs-ps">

                            <?= $total_bill_rupees_str ?>.<?= $total_bill_paise_str ?>

                        </td>

                    </tr>

                <?php 

                }

                

                // Fill remaining rows with blank lines up to 4 content rows (5 lines minimum total)

                for($i=$row_count; $i<2; $i++): ?>

                    <tr><td>&nbsp;</td><td>&nbsp;</td></tr>

                <?php endfor; ?>

                

            </tbody>

        </table>



        <div class="row grand-total-row" style="border-top:2px solid #333;">

            <div class="cell total-label-cell" style="width:75%; border-right:1px solid #333;">

                <strong style="float:right;">TOTAL</strong>

            </div>

            <div class="cell combined-rs-ps total-amount-cell" style="width:30%;">

                <strong style="float:right;"><?= $total_bill_rupees_str ?>.<?= $total_bill_paise_str ?></strong>

            </div>

        </div>



        <div class="footer">

            <div>**Rupees In Words:** <?= h($amount_words) ?> </div>

            <?php if ($invoice_no !== ''): ?>

                <div class="muted" style="margin-top:4px;">**Invoice No:** <?= h($invoice_no) ?></div>

            <?php endif; ?>

            <?php if ($branch_name && $branch_name !== 'N/A'): ?>

                <div class="muted" style="margin-top:4px;">**Branch:** <?= h($branch_name) ?></div>

            <?php endif; ?>



            <div style="margin-top:20px;">

                

                <div class="sign">

                    <span class="name"><?= h($requested_by_user) ?></span>

                    <div class="line"></div>

                    Requested by

                </div>

                <div class="sign">

                    <span class="name"><?= h($approved_by_user) ?></span>

                    <div class="line"></div>

                    Approved by

                </div>

                <div class="sign">

                    <span class="name"><?= h($payment_by_user) ?></span>

                    <div class="line"></div>

                    Payment by

                </div>

                <div class="sign">

                    <span class="name"></span>

                    <div class="line"></div>

                    Receiver's Signature

                </div>

                

            </div>

        </div>

    </div>

</div>

</body>

</html>