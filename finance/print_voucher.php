<?php
declare(strict_types=1);

require_once(__DIR__ . '/../functions.php');

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
    $crore   = intdiv($n, 10000000); $n %= 10000000;
    $lakh    = intdiv($n, 100000);   $n %= 100000;
    $thou    = intdiv($n, 1000);     $n %= 1000;
    $hundred = intdiv($n, 100);      $n %= 100;

    $twoDigits = function(int $x) use($ones,$tens){
        if ($x===0) return '';
        if ($x<20) return $ones[$x];
        $t = $tens[intdiv($x,10)];
        $o = $x%10 ? ' '.$ones[$x%10] : '';
        return $t.$o;
    };

    if ($crore)   $parts[] = inr_words_int($crore).' crore';
    if ($lakh)    $parts[] = inr_words_int($lakh).' lakh';
    if ($thou)    $parts[] = inr_words_int($thou).' thousand';
    if ($hundred) $parts[] = $ones[$hundred].' hundred';
    if ($n)       $parts[] = $twoDigits($n);

    return trim(preg_replace('/\s+/', ' ', implode(' ', $parts)));
}
function rupees_in_words(float $amount): string {
    $rupees = (int)floor($amount + 0.00001);
    if ($rupees === 0) return 'Zero rupees only';
    $w = ucfirst(inr_words_int($rupees));
    return $w.' rupees only';
}

/* -------------------- inputs -------------------- */
$type    = strtolower(trim($_GET['type']    ?? ''));
$voucher = trim($_GET['voucher'] ?? '');
$invoice = trim($_GET['invoice'] ?? '');
$url_description = trim($_GET['description'] ?? ''); // <--- Capture description from URL

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
 * branch_name, purpose, grn_list (NEW for vendor)
 */
function load_row(string $type, string $voucher, string $invoice): array {
    $voucher = addslashes($voucher);
    $invoice = addslashes($invoice);

    /* helper to build WHERE: (voucher OR invoice) if provided, else latest row */
    $buildWhere = function(array $cols, string $alias) use ($voucher, $invoice): string {
        $conds = [];
        if ($voucher !== '' && isset($cols['voucher_no'])) $conds[] = "$alias.voucher_no='$voucher'";
        if ($invoice !== '' && isset($cols['invoice_no'])) $conds[] = "$alias.invoice_no='$invoice'";
        return $conds ? '(' . implode(' OR ', $conds) . ')' : '1=1';
    };

    /* -------- vendor -------- */
    if ($type==='vendor' && t_exists('vendor_grn_payments')) {
        $VG = t_cols('vendor_grn_payments');

        $sel_invoice = isset($VG['invoice_no']) ? 'p.invoice_no' : 'NULL';
        $sel_voucher = isset($VG['voucher_no']) ? 'p.voucher_no' : 'NULL';
        $sel_note    = isset($VG['remark']) ? 'p.remark' : (isset($VG['note']) ? 'p.note' : 'NULL');
        $sel_method  = isset($VG['method']) ? 'p.method' : 'NULL';
        $sel_payment = isset($VG['payment_by']) ? 'p.payment_by' : (isset($VG['payment_pu']) ? 'p.payment_pu' : 'NULL');
        $sel_branch  = (t_exists('branches') && isset($VG['branch_id'])) ? 'b.branch_name' : 'NULL';

        $joinV = t_exists('vendors') ? "LEFT JOIN vendors v ON v.vendor_id = p.vendor_id" : "";
        $name  = t_exists('vendors') ? "v.vendor_name" : "CONCAT('Vendor #',p.vendor_id)";
        $joinB = (t_exists('branches') && isset($VG['branch_id'])) ? "LEFT JOIN branches b ON b.branch_id = p.branch_id" : "";

        // GRN LOGIC FOR MULTIPLE GRNS:
        $sel_grn_list = 'NULL';
        $joinG = '';
        
        // Case 1: GRN is stored directly as a number in the payment table
        if (isset($VG['grn_no'])) {
            $sel_grn_list = 'p.grn_no';
        } 
        
        // Case 2: Multi-GRN linked via a join table (Assumes 'vendor_grn_payment_links' and 'vendor_grn_payment_id' exists)
        elseif (t_exists('vendor_grn_payment_links') && isset($VG['vendor_grn_payment_id'])) {
            $link_table = 'vendor_grn_payment_links';
            $G_table = '';
            $G_col = '';
            $G_id_col = '';
            
            if (isset($VG['grn_id']) && t_exists('vendor_grns')) {
                $G = t_cols('vendor_grns');
                if (isset($G['grn_no'])) { $G_table = 'vendor_grns'; $G_col = 'grn_no'; $G_id_col = 'grn_id'; }
            } elseif (isset($VG['grn_id']) && t_exists('goods_receipts')) {
                $G = t_cols('goods_receipts');
                if (isset($G['grn_number'])) { $G_table = 'goods_receipts'; $G_col = 'grn_number'; $G_id_col = 'grn_id'; }
            }

            if ($G_table !== '') {
                // Use sub-query with GROUP_CONCAT to get all GRNs
                $subquery_grn_list = "
                    SELECT GROUP_CONCAT(DISTINCT g.$G_col SEPARATOR ', ')
                    FROM $link_table l
                    JOIN $G_table g ON g.$G_id_col = l.$G_id_col
                    WHERE l.vendor_grn_payment_id = p.vendor_grn_payment_id
                ";
                $sel_grn_list = "COALESCE(($subquery_grn_list), NULL)";
            }
        }

        // Case 3: Fallback to single GRN ID lookup if only p.grn_id is available
        if ($sel_grn_list === 'NULL' && isset($VG['grn_id'])) {
            if (t_exists('vendor_grns')) {
                $G = t_cols('vendor_grns');
                if (isset($G['grn_no'])) { $joinG = "LEFT JOIN vendor_grns g ON g.grn_id = p.grn_id"; $sel_grn_list = 'g.grn_no'; }
            }
            if (t_exists('goods_receipts')) {
                $G = t_cols('goods_receipts');
                if (isset($G['grn_number'])) { $joinG = "LEFT JOIN goods_receipts g ON g.grn_id = p.grn_id"; $sel_grn_list = 'g.grn_number'; }
            }
        }
        // END GRN LOGIC

        $where = $buildWhere($VG, 'p');

        $sql = "
            SELECT
                $name AS party,
                $sel_voucher AS voucher_no,
                $sel_invoice AS invoice_no,
                p.paid_at AS dt,
                p.amount AS amount,
                $sel_note AS note,
                $sel_payment AS payment_by,
                $sel_method AS method,
                $sel_branch AS branch_name,
                $sel_grn_list AS grn_list, /* --- NEW: Contains single or multiple GRNs (comma separated) --- */
                CASE
                    WHEN $sel_grn_list IS NOT NULL THEN CONCAT('GRN(s): ', $sel_grn_list)
                    WHEN ".(isset($VG['invoice_no']) ? "p.invoice_no IS NOT NULL" : "0")." THEN CONCAT('Invoice: ', $sel_invoice)
                    ELSE IFNULL($sel_note,'')
                END AS purpose
            FROM vendor_grn_payments p
            $joinV
            $joinB
            $joinG
            WHERE $where
            ORDER BY p.paid_at DESC
            LIMIT 1";
        $r = exeSql($sql);
        if ($r) return $r[0];
    }

    /* -------- employee -------- */
    if ($type==='employee' && t_exists('employee_salary_payments')) {
        $ES = t_cols('employee_salary_payments');

        $sel_invoice = isset($ES['invoice_no']) ? 'p.invoice_no' : 'NULL';
        $sel_voucher = isset($ES['voucher_no']) ? 'p.voucher_no' : 'NULL';
        $sel_note    = isset($ES['note']) ? 'p.note' : (isset($ES['remark']) ? 'p.remark' : 'NULL');
        $sel_payment = isset($ES['payment_by']) ? 'p.payment_by' : (isset($ES['payment_pu']) ? 'p.payment_pu' : 'NULL');

        $joinE = t_exists('employees') ? "LEFT JOIN employees e ON e.id=p.employee_id" : "";
        $Ecols = t_exists('employees') ? t_cols('employees') : [];
        $name  = t_exists('employees') ? "e.employee_name" : "CONCAT('Emp #',p.employee_id)";

        $roleExpr = 'NULL';
        foreach (['role','designation','position','job_title','title'] as $rc) {
            if (!empty($Ecols[$rc])) { $roleExpr = "e.$rc"; break; }
        }

        $sel_branch = 'NULL'; $joinB = '';
        if (t_exists('branches')) {
            if (isset($ES['branch_id'])) { $joinB="LEFT JOIN branches b ON b.branch_id=p.branch_id"; $sel_branch="b.branch_name"; }
            elseif (!empty($Ecols['branch_id'])) { $joinB="LEFT JOIN branches b ON b.branch_id=e.branch_id"; $sel_branch="b.branch_name"; }
        }

        $where = $buildWhere($ES, 'p');

        $sql = "
            SELECT
                $name AS party,
                $sel_voucher AS voucher_no,
                $sel_invoice AS invoice_no,
                p.paid_at AS dt,
                p.amount AS amount,
                $sel_note AS note,
                $sel_payment AS payment_by,
                NULL AS method,
                $sel_branch AS branch_name,
                NULL AS grn_list, /* Filler for structure */
                CASE WHEN $roleExpr IS NOT NULL THEN CONCAT('Role: ', $roleExpr)
                    ELSE IFNULL($sel_note,'')
                END AS purpose
            FROM employee_salary_payments p
            $joinE
            $joinB
            WHERE $where
            ORDER BY p.paid_at DESC
            LIMIT 1";
        $r = exeSql($sql);
        if ($r) return $r[0];
    }

    /* -------- expense -------- */
    if ($type==='expense' && t_exists('expenses')) {
        $EX = t_cols('expenses');

        $sel_dt    = isset($EX['paid_at']) ? 'e.paid_at' : (isset($EX['expense_date']) ? "CONCAT(e.expense_date,' 00:00:00')" : 'NULL');
        $sel_party = isset($EX['purpose']) ? 'e.purpose' : "IFNULL(e.description,'Expense')";
        $sel_method= isset($EX['method']) ? 'e.method' : (isset($EX['mode']) ? 'e.mode' : 'NULL');
        $sel_note  = isset($EX['remark']) ? 'e.remark' : (isset($EX['note']) ? 'e.note' : 'NULL');
        $sel_payment = isset($EX['payment_by']) ? 'e.payment_by' : (isset($EX['payment_pu']) ? 'e.payment_pu' : 'NULL');
        $sel_invoice = isset($EX['invoice_no']) ? 'e.invoice_no' : 'NULL';
        $sel_voucher = isset($EX['voucher_no']) ? 'e.voucher_no' : 'NULL';
        $sel_branch  = (t_exists('branches') && isset($EX['branch_id'])) ? 'b.branch_name' : 'NULL';
        $joinB       = (t_exists('branches') && isset($EX['branch_id'])) ? "LEFT JOIN branches b ON b.branch_id = e.branch_id" : "";

        $where = $buildWhere($EX, 'e');

        $sql = "
            SELECT
                $sel_party AS party,
                $sel_voucher AS voucher_no,
                $sel_invoice AS invoice_no,
                $sel_dt AS dt,
                e.amount AS amount,
                $sel_note AS note,
                $sel_payment AS payment_by,
                $sel_method AS method,
                $sel_branch AS branch_name,
                NULL AS grn_list, /* Filler for structure */
                $sel_party AS purpose
            FROM expenses e
            $joinB
            WHERE $where
            ORDER BY $sel_dt DESC
            LIMIT 1";
        $r = exeSql($sql);
        if ($r) return $r[0];
    }

    /* -------- event -------- */
    if ($type==='event' && t_exists('event_items')) {
        $EI = t_cols('event_items');
        $EV_cols = t_exists('events') ? t_cols('events') : [];

        $joinE = t_exists('events') ? "LEFT JOIN events ev ON ev.event_id = ei.event_id" : "";
        $party = t_exists('events') ? "ev.event_name" : "CONCAT('Event #', ei.event_id)";

        $sel_invoice = isset($EI['invoice_no']) ? 'ei.invoice_no' : 'NULL';
        $sel_voucher = isset($EI['voucher_no']) ? 'ei.voucher_no' : 'NULL';
        $sel_note    = isset($EI['note']) ? 'ei.note' : (isset($EI['remark']) ? 'ei.remark' : 'NULL');
        $sel_payment = isset($EI['payment_by']) ? 'ei.payment_by' : 'NULL';

        $sel_branch='NULL'; $joinB='';
        if (t_exists('branches')) {
            if (isset($EI['branch_id'])) { $joinB="LEFT JOIN branches b ON b.branch_id = ei.branch_id"; $sel_branch="b.branch_name"; }
            elseif (!empty($EV_cols['branch_id'])) { $joinB="LEFT JOIN branches b ON b.branch_id = ev.branch_id"; $sel_branch="b.branch_name"; }
        }

        $venueExpr = 'NULL';
        foreach (['venue','location','place','address'] as $vc) {
            if (!empty($EV_cols[$vc])) { $venueExpr = "ev.$vc"; break; }
        }

        $where = $buildWhere($EI, 'ei');

        $sql = "
            SELECT
                $party AS party,
                $sel_voucher AS voucher_no,
                $sel_invoice AS invoice_no,
                ".(isset($EI['created_at']) ? 'ei.created_at' : 'NOW()')." AS dt,
                ".(isset($EI['amount_received']) ? 'ei.amount_received' : '0')." AS amount,
                $sel_note AS note,
                $sel_payment AS payment_by,
                NULL AS method,
                $sel_branch AS branch_name,
                NULL AS grn_list, /* Filler for structure */
                CASE WHEN $venueExpr IS NOT NULL THEN CONCAT('Venue: ', $venueExpr)
                    ELSE IFNULL($sel_note,'')
                END AS purpose
            FROM event_items ei
            $joinE
            $joinB
            WHERE $where
            ORDER BY ".(isset($EI['created_at']) ? 'ei.created_at' : 'ei.event_id')." DESC
            LIMIT 1";
        $r = exeSql($sql);
        if ($r) return $r[0];
    }

    return [];
}

/* -------------------- fetch/normalize -------------------- */
$data = load_row($type, $voucher, $invoice);

$party       = $data['party']        ?? 'N/A';
$voucher_no  = $data['voucher_no']   ?? '';
$invoice_no  = $data['invoice_no']   ?? '';
$dt          = $data['dt']           ?? date('Y-m-d');
$amount      = (float)($data['amount'] ?? 0);
$note        = $data['note']         ?? '---';
$payment_by  = trim((string)($data['payment_by'] ?? ''));
$method      = $data['method']       ?? '';
$branch_name = $data['branch_name']  ?? '';

// --- Logic for GRN/PURPOSE ---
$grn_list_from_db = trim((string)($data['grn_list'] ?? ''));
$purpose = trim((string)($data['purpose'] ?? ''));

if ($type === 'vendor') {
    if ($url_description !== '') {
        // Option 1: URL description (often used when selecting payment for specific GRNs)
        $purpose = $url_description;
    } elseif ($grn_list_from_db !== '') {
        // Option 2: Multiple GRNs from DB (formatted neatly)
        // Replaces comma-space with a comma and an HTML line break for neat display
        $grn_display = 'GRN(s):<br>' . str_replace(', ', ',<br>', $grn_list_from_db);
        $purpose = $grn_display;
    }
}

// Final fallback and cleanup
if ($purpose === '' || $purpose === 'GRN(s): ' || $purpose === 'Invoice: ') {
    $purpose = $note;
}
if ($purpose === '') $purpose = 'N/A';
// -----------------------------

$rupeesWhole = (int)floor($amount + 0.00001);
$paise       = (int)round(($amount - $rupeesWhole) * 100);
$amount_words = rupees_in_words((float)$rupeesWhole);

/* -------------------- logo -------------------- */
/* primary path requested */
$logoUrl = '';
if (file_exists(__DIR__.'/../assets/img/logo.jpg'))       $logoUrl = '../assets/img/logo.jpg';
elseif (file_exists(__DIR__.'/../assets/logo.png'))       $logoUrl = '../assets/logo.png';
elseif (file_exists(__DIR__.'/logo.png'))                 $logoUrl = 'logo.png';
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
    /* --- MODIFIED LOGO STYLES FOR ALIGNMENT --- */
    .logo { 
        position:absolute; 
        left:12px; 
        top:50%; 
        transform: translateY(-50%); /* Centers vertically */
        max-width: 80px; /* Increased size limit */
        max-height: 80px; /* Increased size limit */
        display:flex; 
        align-items:center; 
        justify-content:center; 
    }
    .logo img { max-width:100%; max-height:100%; object-fit:contain; }
    .logo .fallback { font-weight:bold; font-size:28px; }
    /* --------------------------------------------- */
    .w50 { width:50%; }
    .tbl { width:100%; border-collapse:collapse; }
    .tbl th,.tbl td { border-top:1px solid #333; border-bottom:1px solid #333; border-left:1px solid #333; padding:6px; }
    .tbl th:first-child,.tbl td:first-child{ border-left:0; }
    .muted { color:#666; }
    .footer { padding:10px 12px; }
    .sign { width:24%; display:inline-block; text-align:center; margin-top:24px; }
    .sign .name { font-size:12px; color:#222; display:block; margin-bottom:2px; min-height:16px; }
    .line { border-top:1px solid #333; margin:18px 8px 4px; }
    @media print { .toolbar { display:none; } .container{ width:auto; margin:0; } .wrap{ margin:0 auto; } }
    .topline { border-top:1px solid #333; }
    .btn { padding:6px 10px; border:1px solid #aaa; border-radius:4px; text-decoration:none; background:#f3f3f3; color:#222; }
</style>
</head>
<body>
<div class="container">
    <div class="toolbar">
        <button onclick="window.print()" class="btn">🖨️ Print Voucher</button>
        <a href="payment.php" class="btn">↶ New Payment</a>
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
            <div class="cell w50">
                <strong>TOWARDS / PURPOSE</strong><br>
                <?= $purpose // Display purpose, allowing for <br> tags used for neat formatting ?>
            </div>
            <div class="cell w50"><strong>DATE</strong><br><?= h(substr($dt,0,10)) ?></div>
        </div>

        <table class="tbl" style="border-top:0;">
            <thead>
                <tr>
                    <th>HEAD OF ACCOUNT</th>
                    <th style="width:120px;">RS.</th>
                    <th style="width:80px;">PS.</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= h($method ? (($type==='vendor') ? 'Vendor GRN Payment' : ucfirst($type)).($purpose? ' ('.h(strip_tags($purpose)).')':'') : (h(strip_tags($purpose)) ?: 'N/A')) ?></td>
                    <td style="text-align:right;"><?= number_format($rupeesWhole, 0, '.', '') ?></td>
                    <td style="text-align:right;"><?= number_format($paise, 0, '.', '') ?></td>
                </tr>
                <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                <tr>
                    <th style="text-align:right;">TOTAL</th>
                    <th style="text-align:right;"><?= number_format($rupeesWhole, 0, '.', '') ?></th>
                    <th style="text-align:right;"><?= number_format($paise, 0, '.', '') ?></th>
                </tr>
            </tbody>
        </table>

        <div class="footer">
            <div><strong>Rupees :</strong> <?= h($amount_words) ?></div>
            <?php if ($invoice_no !== ''): ?>
                <div class="muted" style="margin-top:4px;"><strong>Invoice No :</strong> <?= h($invoice_no) ?></div>
            <?php endif; ?>
            <?php if ($branch_name): ?>
                <div class="muted" style="margin-top:4px;"><strong>Branch :</strong> <?= h($branch_name) ?></div>
            <?php endif; ?>

            <div style="margin-top:20px;">
                <div class="sign">
                    <span class="name"></span>
                    <div class="line"></div>
                    Prepared by
                </div>
                <div class="sign">
                    <span class="name"><?= h($payment_by) ?></span>
                    <div class="line"></div>
                    Payment By
                </div>
                <div class="sign">
                    <span class="name"></span>
                    <div class="line"></div>
                    Approved by
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