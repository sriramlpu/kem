<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

/* functions.php is in kmk/functions/functions.php */
require __DIR__ . '/../../functions.php';

/* Optional composer autoload (kmk/vendor/autoload.php) */
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require __DIR__ . '/../../vendor/autoload.php';
}
use Dompdf\Dompdf;

/* ------------ user/role (replace with your auth) ------------ */
function current_user_id(): int { return (int)($_SESSION['user_id'] ?? 1); }
function current_user_name(): string { return (string)($_SESSION['name'] ?? 'User'); }

/* ------------ small helpers ------------ */
function v($k,$d=null){ return isset($_REQUEST[$k])?$_REQUEST[$k]:$d; }
function i($x){ return (int)$x; }
function s($x){ return trim((string)$x); }
function h($x){ return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }
function json_out($data,$code=200){ http_response_code($code); header('Content-Type: application/json; charset=UTF-8'); echo json_encode($data); exit; }
function voucher_no($prefix='VCH'){ return $prefix.'-'.date('Ymd-His').'-'.strtoupper(bin2hex(random_bytes(2))); }
function invoice_no($prefix='INV'){ return $prefix.'-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(2))); }

/* ------------ INFORMATION_SCHEMA caches ------------ */
function table_exists($table){
    static $exists = [];
    $t = preg_replace('/[^a-zA-Z0-9_]/','',$table);
    if (isset($exists[$t])) return $exists[$t];
    $rows = exeSql("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' LIMIT 1");
    return $exists[$t] = !empty($rows);
}
function table_columns($table){
    static $cache = [];
    $t = preg_replace('/[^a-zA-Z0-9_]/','',$table);
    if (isset($cache[$t])) return $cache[$t];
    $rows = exeSql("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t'");
    $cols = [];
    foreach ($rows as $r){ $cols[$r['COLUMN_NAME']] = true; }
    return $cache[$t] = $cols;
}
function safe_insert($table, array $data){
    $cols = table_columns($table);
    if (!$cols) return false;
    $clean = [];
    foreach ($data as $k=>$v){
        if (!isset($cols[$k])) continue;
        if ($v === null) continue;
        $clean[$k] = $v;
    }
    if (!$clean) return false;
    return insData($table, $clean);
}
function safe_update($table, array $data, array $where){
    $cols = table_columns($table);
    if (!$cols) return false;
    $clean = [];
    foreach ($data as $k=>$v){
        if (!isset($cols[$k])) continue;
        if ($v === null) continue;
        $clean[$k] = $v;
    }
    if (!$clean) return false;
    return upData($table, $clean, $where);
}
function pick_payment_by_field(string $table): ?string {
    $cols = table_columns($table);
    if (!$cols) return null;
    if (isset($cols['payment_by'])) return 'payment_by';
    if (isset($cols['payment_pu'])) return 'payment_pu';
    return null;
}

/* ------------ Rupees words ------------ */
function convert_number_to_rupees(float $number): string {
    $decimal = round($number - ($no = floor($number)), 2) * 100;
    $hundred = null;
    $digits_length = strlen((string)$no);
    $i = 0; $str = [];
    $words = [0=>'',1=>'one',2=>'two',3=>'three',4=>'four',5=>'five',6=>'six',7=>'seven',8=>'eight',9=>'nine',10=>'ten',11=>'eleven',12=>'twelve',13=>'thirteen',14=>'fourteen',15=>'fifteen',16=>'sixteen',17=>'seventeen',18=>'eighteen',19=>'nineteen',20=>'twenty',30=>'thirty',40=>'forty',50=>'fifty',60=>'sixty',70=>'seventy',80=>'eighty',90=>'ninety'];
    $list2=[0=>'',1=>'hundred',2=>'thousand',3=>'lakh',4=>'crore',5=>'arab'];
    $list1=[0=>'',1=>'hundred',2=>'thousand',3=>'lakh',4=>'crore'];
    $used_list = ($digits_length > 7) ? $list2 : $list1;

    while ($i < $digits_length) {
        $subword='';
        if ($i == 2) { $count = $no % 1000; if ($count > 0) { $hundred = $count / 100; $no = (int)($no / 1000); } }
        else { $count = $no % 100; $no = (int)($no / 100); }
        if ($count == 0) { $i += ($i == 2) ? 1 : 2; continue; }
        $key = ($i == 1 || $i == 3) ? 1 : $i;
        $separator = ($key > 0) ? ' ' . $used_list[$key] . ' ' : '';
        if ($count < 21) $subword = $words[$count];
        elseif ($count < 100) $subword = $words[(int)($count / 10) * 10] . (($count % 10 > 0) ? ' ' . $words[$count % 10] : '');
        if ($i == 2) { if ($hundred > 0) $subword = $words[$hundred] . ' hundred' . ($subword !== '' ? ' and ' . $subword : ''); $i = 3; }
        else { $i += 2; }
        $str[] = $subword . $separator;
    }
    $Rupees = implode('', array_reverse($str));
    $paise = '';
    if ($decimal > 0) {
        if ($decimal < 21) $paise = $words[(int)$decimal];
        elseif ($decimal < 100) $paise = $words[(int)(floor($decimal) / 10) * 10] . (($decimal % 10 > 0) ? ' ' . $words[(int)($decimal % 10)] : '');
        $paise = " and " . $paise . " paise";
    }
    $final_string = implode(' ', array_filter(array_map('trim', explode(' ', $Rupees))));
    if ($final_string === '') return 'Zero rupees only';
    return ucwords($final_string . " rupees" . $paise . " only");
}

/* ------------ Lists ------------ */
function fetch_vendors(){
    if (table_exists('vendors')) return exeSql("SELECT vendor_id, vendor_name FROM vendors ORDER BY vendor_name");
    if (table_exists('vendor'))  return exeSql("SELECT vendor_id, vendor_name FROM vendor ORDER BY vendor_name");
    return [];
}
function fetch_branches(){
    if (table_exists('branches')) return exeSql("SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
    if (table_exists('branch'))   return exeSql("SELECT branch_id, branch_name FROM branch ORDER BY branch_name");
    return [];
}
function fetch_employees(){
    if (table_exists('employees')) return exeSql("SELECT id, employee_name, COALESCE(branch_id,0) AS branch_id FROM employees ORDER BY employee_name");
    if (table_exists('employee'))  return exeSql("SELECT id, employee_name, COALESCE(branch_id,0) AS branch_id FROM employee ORDER BY employee_name");
    return [];
}
function fetch_employees_by_branch($branch_id){
    $branch_id = (int)$branch_id;
    if (table_exists('employees')) {
        $cols = table_columns('employees');
        if (isset($cols['branch_id'])) {
            return exeSql("SELECT id, employee_name FROM employees WHERE branch_id=$branch_id ORDER BY employee_name");
        } else {
            return exeSql("SELECT id, employee_name FROM employees ORDER BY employee_name");
        }
    }
    if (table_exists('employee')) {
        $cols = table_columns('employee');
        if (isset($cols['branch_id'])) {
            return exeSql("SELECT id, employee_name FROM employee WHERE branch_id=$branch_id ORDER BY employee_name");
        } else {
            return exeSql("SELECT id, employee_name FROM employee ORDER BY employee_name");
        }
    }
    return [];
}

/* ------------ GRNs (vendor) ------------ */
function fetch_pending_grns($vendor_id, $branch_id){
    $rows = [];
    $grcols = table_columns('goods_receipts');
    $has_amount_received = $grcols && isset($grcols['amount_received']);

    if (table_exists('goods_receipts') && table_exists('purchase_orders')) {
        $rows = exeSql("SELECT gr.grn_id, gr.grn_number, gr.total_amount, gr.vendor_id
                         FROM goods_receipts gr
                         JOIN purchase_orders po ON po.po_id = gr.po_id
                         WHERE gr.vendor_id = $vendor_id AND po.branch_id = $branch_id
                         ORDER BY gr.grn_id DESC");
        $rows = enrich_and_filter_pending($rows, $has_amount_received);
        if ($rows) return $rows;
    }
    if ($grcols && isset($grcols['branch_id'])) {
        $rows = exeSql("SELECT grn_id, grn_number, total_amount, vendor_id
                         FROM goods_receipts
                         WHERE vendor_id = $vendor_id AND branch_id = $branch_id
                         ORDER BY grn_id DESC");
        $rows = enrich_and_filter_pending($rows, $has_amount_received);
        if ($rows) return $rows;
    }
    return [];
}
function enrich_and_filter_pending(array $rows, bool $has_amount_received){
    $out = [];
    foreach ($rows as $r){
        $grn_id = (int)$r['grn_id'];
        $total  = (float)$r['total_amount'];
        if ($has_amount_received) {
            $grnRow = getRowValues('goods_receipts', $grn_id, 'grn_id');
            $paid = (float)($grnRow['amount_received'] ?? 0);
        } else {
            $p = getValues("(SELECT IFNULL(SUM(amount),0) AS paid FROM vendor_grn_payments WHERE grn_id=$grn_id) t","1");
            $paid = (float)($p['paid'] ?? 0);
        }
        $balance = $total - $paid;
        if ($balance > 0.0001) {
            $out[] = [
                'grn_id'       => $grn_id,
                'grn_number'   => $r['grn_number'],
                'total_amount' => $total,
                'paid'         => $paid,
                'balance'      => $balance
            ];
        }
    }
    return $out;
}
function update_vendor_totals(int $vendor_id): void {
    if (!table_exists('vendor_totals')) return;

    $rowT = getValues("(SELECT IFNULL(SUM(total_amount),0) AS total
                         FROM goods_receipts WHERE vendor_id=$vendor_id) t","1");
    $total_bill = (float)($rowT['total'] ?? 0);

    $grcols = table_columns('goods_receipts');
    if ($grcols && isset($grcols['amount_received'])) {
        $rowP = getValues("(SELECT IFNULL(SUM(amount_received),0) AS paid
                             FROM goods_receipts WHERE vendor_id=$vendor_id) t","1");
    } else {
        $rowP = getValues("(SELECT IFNULL(SUM(amount),0) AS paid
                             FROM vendor_grn_payments WHERE vendor_id=$vendor_id) t","1");
    }
    $total_paid = (float)($rowP['paid'] ?? 0);
    $balance    = max(0.0, $total_bill - $total_paid);

    exeSql("INSERT INTO vendor_totals (vendor_id, total_bill, total_paid, balance, updated_at)
            VALUES ($vendor_id, $total_bill, $total_paid, $balance, NOW())
            ON DUPLICATE KEY UPDATE
             total_bill=VALUES(total_bill),
             total_paid=VALUES(total_paid),
             balance=VALUES(balance),
             updated_at=NOW()");
}
