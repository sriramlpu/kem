<?php
/** 
 * Payments (single file, uses kmk/functions.php helpers)
 * STRICT BRANCH FILTER for Vendors (vendor+branch only)
 * Adds Events support (pulls from events/event_items)
 * GRN PICKER: CHECKBOXES (multi-select) + FIFO split
 * Vendor invoice_no is taken from goods_receipts.invoice_number
 * Employees: Branch dropdown; filter employees by branch (if employees.branch_id exists)
 * Path: kmk/finance/payment.php
 */
declare(strict_types=1);

require __DIR__ . '/../functions.php';

// Optional PDF (composer require dompdf/dompdf)
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require __DIR__ . '/../../vendor/autoload.php';
}
use Dompdf\Dompdf;

/* ---------------- helpers ---------------- */
function v($k,$d=null){ return isset($_REQUEST[$k])?$_REQUEST[$k]:$d; }
function i($x){ return (int)$x; }
function s($x){ return trim((string)$x); }
function h($x){ return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }
function json_out($data,$code=200){ http_response_code($code); header('Content-Type: application/json; charset=UTF-8'); echo json_encode($data); exit; }
function voucher_no($prefix='VCH'){ return $prefix.'-'.date('Ymd-His').'-'.strtoupper(bin2hex(random_bytes(2))); }
function invoice_no($prefix='INV'){ return $prefix.'-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(2))); }

function table_exists($table){
    $t = preg_replace('/[^a-zA-Z0-9_]/','',$table);
    $rows = exeSql("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' LIMIT 1");
    return !empty($rows);
}
function table_columns($table){
    $t = preg_replace('/[^a-zA-Z0-9_]/','',$table);
    $rows = exeSql("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t'");
    $cols = [];
    foreach ($rows as $r){ $cols[$r['COLUMN_NAME']] = true; }
    return $cols;
}

/**
 * Return normalized list of allowed ENUM values (lowercased) for a column, or empty array if not enum.
 */
function enum_values(string $table, string $column): array {
    $t = preg_replace('/[^a-zA-Z0-9_]/','',$table);
    $c = preg_replace('/[^a-zA-Z0-9_]/','',$column);
    $row = exeSql("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1");
    if (!$row) return [];
    $ctype = $row[0]['COLUMN_TYPE'] ?? '';
    if (stripos($ctype, 'enum(') !== 0) return [];
    // parse: enum('A','B','C')
    $inside = substr($ctype, 5, -1); // remove enum( and trailing )
    // split on comma not inside quotes (simple approach since MySQL escapes '' as two single-quotes)
    $vals = [];
    $curr = '';
    $inq = false;
    for ($i=0; $i<strlen($inside); $i++){
        $ch = $inside[$i];
        if ($ch === "'") {
            if ($inq && $i+1 < strlen($inside) && $inside[$i+1] === "'") { // escaped '
                $curr .= "'";
                $i++;
            } else {
                $inq = !$inq;
            }
        } elseif ($ch === ',' && !$inq) {
            $vals[] = $curr;
            $curr = '';
        } else {
            $curr .= $ch;
        }
    }
    if ($curr !== '') $vals[] = $curr;
    $vals = array_map('trim',$vals);
    $vals = array_map(function($v){ return mb_strtolower($v, 'UTF-8'); }, $vals);
    return $vals;
}

/**
 * Try to coerce a free-text value to an allowed ENUM value for the given column.
 * - Checks the column's ENUM list.
 * - Tries candidates in order; matches case-insensitive.
 * - Returns the matching string (in the exact candidate's casing) or null if no match.
 */
function coerce_enum_or_null(string $table, string $column, array $candidates): ?string {
    $allowed = enum_values($table, $column);
    if (!$allowed) return $candidates[0] ?? null; // not an enum -> just return the first candidate
    foreach ($candidates as $cand) {
        if ($cand === null || $cand === '') continue;
        if (in_array(mb_strtolower($cand,'UTF-8'), $allowed, true)) {
            return $cand; // keep original casing as provided
        }
    }
    return null; // no match
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

/**
 * Converts a number (up to 99,99,99,999.99) into Indian Rupees words.
 */
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
        if ($i == 2) {
            $count = $no % 1000;
            if ($count > 0) {
                $hundred = $count / 100;
                $no = (int)($no / 1000);
            }
        } else {
            $count = $no % 100;
            $no = (int)($no / 100);
        }
        if ($count == 0) { $i += ($i == 2) ? 1 : 2; continue; }
        $key = ($i == 1 || $i == 3) ? 1 : $i;
        $separator = ($key > 0) ? ' ' . $used_list[$key] . ' ' : '';

        if ($count < 21)        $subword = $words[$count];
        elseif ($count < 100)   $subword = $words[(int)($count / 10) * 10] . (($count % 10 > 0) ? ' ' . $words[$count % 10] : '');

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

/** keep vendor_totals in sync after any vendor payment */
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

/** lists */
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
    if (table_exists('employees')) return exeSql("SELECT id, employee_name FROM employees ORDER BY employee_name");
    if (table_exists('employee'))  return exeSql("SELECT id, employee_name FROM employee ORDER BY employee_name");
    return [];
}
function fetch_employees_by_branch($branch_id){
    $branch_id = (int)$branch_id;
    // Prefer 'employees' table; fallback 'employee'
    if (table_exists('employees')) {
        $cols = table_columns('employees');
        if (isset($cols['branch_id'])) {
            return exeSql("SELECT id, employee_name FROM employees WHERE branch_id=$branch_id ORDER BY employee_name");
        } else {
            return exeSql("SELECT id, employee_name FROM employees ORDER BY employee_name"); // no branch column, return all
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
function fetch_expense_purposes(){
    if (!table_exists('expenses')) return [];
    $rows = exeSql("SELECT DISTINCT purpose FROM expenses WHERE purpose IS NOT NULL AND TRIM(purpose)<>'' ORDER BY purpose");
    return array_map(fn($r)=>$r['purpose'], $rows);
}
function fetch_events(){
    if (!table_exists('events')) return [];
    return exeSql("SELECT event_id, event_name FROM events ORDER BY event_name");
}

/** VENDORS: STRICT vendor+branch pending GRNs only */
function fetch_pending_grns($vendor_id, $branch_id){
    $rows = [];
    $grcols = table_columns('goods_receipts');
    $has_amount_received = $grcols && isset($grcols['amount_received']);

    if (table_exists('goods_receipts') && table_exists('purchase_orders')) {
        $rows = exeSql("SELECT gr.grn_id, gr.grn_number, gr.total_amount
                         FROM goods_receipts gr
                         JOIN purchase_orders po ON po.po_id = gr.po_id
                         WHERE gr.vendor_id = $vendor_id AND po.branch_id = $branch_id
                         ORDER BY gr.grn_id DESC");
        $rows = enrich_and_filter_pending($rows, $has_amount_received);
        if ($rows) return $rows;
    }
    if ($grcols && isset($grcols['branch_id'])) {
        $rows = exeSql("SELECT grn_id, grn_number, total_amount
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

/* ---------------- AJAX ---------------- */
if (isset($_GET['ajax'])) {
    $act = $_GET['ajax'];

    if ($act === 'vendor_bank') {
        $vendor_id = i(v('vendor_id',0));
        $row = getRowValues('vendors', $vendor_id, 'vendor_id');
        json_out([
            'account_number' => $row['account_number'] ?? '',
            'ifsc'           => $row['ifsc'] ?? '',
        ]);
    }

    if ($act === 'grns') {
        $vendor_id = i(v('vendor_id',0));
        $branch_id = i(v('branch_id',0));
        $rows = fetch_pending_grns($vendor_id, $branch_id);
        json_out($rows ?: []);
    }

    if ($act === 'grn_paid') {
        $grn_id = i(v('grn_id',0));
        $grcols = table_columns('goods_receipts');
        $has_amount_received = $grcols && isset($grcols['amount_received']);
        if ($has_amount_received) {
            $grnRow = getRowValues('goods_receipts', $grn_id, 'grn_id');
            $paid = (float)($grnRow['amount_received'] ?? 0);
        } else {
            $row = getValues("(SELECT IFNULL(SUM(amount),0) AS paid FROM vendor_grn_payments WHERE grn_id=$grn_id) t","1");
            $paid = (float)($row['paid'] ?? 0);
        }
        json_out(['paid' => $paid]);
    }

    if ($act === 'employee') {
        $employee_id = i(v('employee_id',0));
        $period = preg_replace('/\D/','', v('period', date('Ym')));
        $emp = getRowValues('employees', $employee_id, 'id');
        if (!$emp && table_exists('employee')) $emp = getRowValues('employee', $employee_id, 'id');

        $role   = $emp['role']   ?? '';
        $salary = (float)($emp['salary'] ?? 0);

        $paidRow = getValues("(SELECT IFNULL(SUM(amount),0) AS paid
                               FROM employee_salary_payments
                               WHERE employee_id=$employee_id AND pay_period='$period') t", "1");
        $paid = (float)($paidRow['paid'] ?? 0);

        json_out(['role'=>$role, 'salary'=>$salary, 'paid'=>$paid]);
    }

    // NEW: Employees filtered by branch
    if ($act === 'employees') {
        $branch_id = i(v('branch_id',0));
        $rows = fetch_employees_by_branch($branch_id);
        json_out($rows ?: []);
    }

    // Expense summary (Total = expenses.amount, Paid = expenses.balance_paid)
    if ($act === 'expense_summary') {
        $purpose = s(v('purpose',''));
        if ($purpose === '') json_out(['total'=>null,'paid'=>0,'balance'=>0]);

        $cols = table_columns('expenses');
        $selectCols = "amount, balance_paid";
        if (isset($cols['remaining_balance'])) $selectCols .= ", remaining_balance";

        $trow = exeSql("SELECT $selectCols
                         FROM expenses
                         WHERE purpose='".addslashes($purpose)."'
                         ORDER BY id DESC
                         LIMIT 1");

        if (!$trow) json_out(['total'=>0, 'paid'=>0, 'balance'=>0]);

        $row     = $trow[0];
        $total   = (float)($row['amount'] ?? 0);
        $paid    = (float)($row['balance_paid'] ?? 0);
        $balance = isset($row['remaining_balance'])
            ? (float)$row['remaining_balance']
            : max(0.0, $total - $paid);

        json_out(['total'=>$total, 'paid'=>$paid, 'balance'=>$balance]);
    }

    // Events list & summary
    if ($act === 'events') {
        $rows = fetch_events();
        json_out($rows ?: []);
    }
    if ($act === 'event_summary') {
        $event_id = i(v('event_id',0));
        if (!$event_id || !table_exists('event_items')) json_out(['total'=>0,'paid'=>0,'balance'=>0]);
        $row = getValues("(SELECT IFNULL(SUM(total_amount),0) AS total,
                                     IFNULL(SUM(amount_received),0) AS paid
                              FROM event_items WHERE event_id=$event_id) t","1");
        $total = (float)($row['total'] ?? 0);
        $paid  = (float)($row['paid'] ?? 0);
        $balance = max(0.0, $total - $paid);
        json_out(['total'=>$total, 'paid'=>$paid, 'balance'=>$balance]);
    }

    json_out(['error'=>'Unknown action'], 400);
}

/* ---------------- SUBMIT ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pay_for    = s(v('pay_for',''));
    $method     = s(v('method',''));
    $pay_now    = (float)s(v('pay_now','0'));
    $payment_by = s(v('payment_by',''));
    $payment_date = (string)v('payment_date', date('Y-m-d H:i:s'));
    $paid_at    = date('Y-m-d H:i:s', strtotime($payment_date));
    $notes      = s(v('notes',''));
    $voucher    = voucher_no('VCH');
    $invoice    = invoice_no('INV');
    $pdf_title  = '';

    $pdf_rows   = [
        ['Invoice #',  $invoice],
        ['Voucher #',  $voucher],
        ['Payment Date', $paid_at],
        ['Payment By', $payment_by !== '' ? $payment_by : '-'],
    ];
    if ($method!=='') $pdf_rows[] = ['Method', strtoupper($method)];

    $paid_to = '';
    $description = '';
    $head_of_account = 'General Payment';
    $total_amount = $pay_now;

    if ($pay_for === 'vendor') {
        $vendor_id = i(v('vendor_id',0));
        $branch_id = i(v('branch_id',0));

        $grn_ids = v('grn_ids', []);
        if (!is_array($grn_ids)) $grn_ids = [];
        $grn_ids = array_values(array_filter(array_map('intval', $grn_ids)));
        sort($grn_ids);

        $bank_mode    = '';
        $bank_account = '';
        $bank_ifsc    = '';
        $cheque_no    = '';
        $upi_id       = '';

        if ($method === 'online') {
            $upi_id = s(v('upi_id',''));
        } elseif ($method === 'bank') {
            $bank_mode    = s(v('bank_mode','transfer')); // UI default
            $bank_account = s(v('bank_account',''));
            $bank_ifsc    = s(v('bank_ifsc',''));
            if ($bank_mode === 'cheque') $cheque_no = s(v('cheque_no',''));
        }

        $vend = getRowValues('vendors', $vendor_id, 'vendor_id');

        $grcols = table_columns('goods_receipts');
        $has_amount_received = $grcols && isset($grcols['amount_received']);

        $selected = [];
        $combined_total=0.0; $combined_paid=0.0; $combined_balance=0.0;

        foreach ($grn_ids as $gid) {
            $grn = getRowValues('goods_receipts', $gid, 'grn_id');
            if (!$grn) continue;

            // Strict branch guard
            if (isset($grn['po_id']) && table_exists('purchase_orders')) {
                $po = getRowValues('purchase_orders', (int)$grn['po_id'], 'po_id');
                if ($po && isset($po['branch_id']) && (int)$po['branch_id'] !== $branch_id) continue;
            } elseif (isset($grn['branch_id']) && (int)$grn['branch_id'] !== $branch_id) {
                continue;
            }

            $total = (float)($grn['total_amount'] ?? 0);
            if ($has_amount_received) {
                $paid = (float)($grn['amount_received'] ?? 0);
            } else {
                $row = getValues("(SELECT IFNULL(SUM(amount),0) AS paid FROM vendor_grn_payments WHERE grn_id=$gid) t","1");
                $paid = (float)($row['paid'] ?? 0);
            }
            $bal = max(0.0, $total - $paid);
            if ($bal <= 0) continue;

            $combined_total   += $total;
            $combined_paid    += $paid;
            $combined_balance += $bal;

            $selected[] = [
                'id'      => $gid,
                'no'      => $grn['grn_number'] ?? ('#'.$gid),
                'total'   => $total,
                'paid'    => $paid,
                'bal'     => $bal,
                'invoice' => trim((string)($grn['invoice_number'] ?? ''))
            ];
        }

        if (empty($selected)) { header("Location: ".$_SERVER['REQUEST_URI']); exit; }
        if ($pay_now <= 0) $pay_now = $combined_balance;

        // FIFO
        $left = $pay_now;
        foreach ($selected as $row) {
            if ($left <= 0) break;
            $apply = min($left, $row['bal']);
            if ($apply <= 0) continue;

            $row_invoice = ($row['invoice'] !== '' ? $row['invoice'] : invoice_no('INV'));

            // Build insert payload
            $ins = [
                'vendor_id'    => $vendor_id,
                'grn_id'       => $row['id'],
                'branch_id'    => $branch_id,
                'amount'       => $apply,
                'method'       => $method,
                // 'bank_mode' will be added after enum-safe coercion
                'bank_account' => $bank_account,
                'bank_ifsc'    => $bank_ifsc,
                'cheque_no'    => $cheque_no,
                'upi_id'       => $upi_id,
                'remark'       => $notes,
                'voucher_no'   => $voucher,
                'invoice_no'   => $row_invoice,
                'paid_at'      => $paid_at,
            ];

            // Add payment_by with flexible column name support
            if ($pb = pick_payment_by_field('vendor_grn_payments')) $ins[$pb] = $payment_by;

            // *** ENUM-SAFE: bank_mode ***
            // Try to map UI values to whatever the server allows (e.g. 'Bank Transfer', 'NEFT', 'RTGS', 'Cheque').
            if ($method === 'bank' && table_exists('vendor_grn_payments')) {
                $candidateBankModes = [];
                // primary UI choice
                if ($bank_mode) $candidateBankModes[] = $bank_mode; // 'transfer' or 'cheque'
                // reasonable aliases to try
                if (mb_strtolower($bank_mode, 'UTF-8') === 'transfer') {
                    // Try common variations likely present on server schema
                    $candidateBankModes = array_merge($candidateBankModes, ['Bank Transfer','NEFT','RTGS','IMPS','Transfer']);
                } elseif (mb_strtolower($bank_mode, 'UTF-8') === 'cheque') {
                    $candidateBankModes = array_merge($candidateBankModes, ['Cheque','Check']);
                }
                $modeFinal = coerce_enum_or_null('vendor_grn_payments', 'bank_mode', $candidateBankModes);
                if ($modeFinal !== null && $modeFinal !== '') {
                    $ins['bank_mode'] = $modeFinal;
                }
                // if null, we omit it to avoid ENUM truncation in strict mode
            }

            safe_insert('vendor_grn_payments', $ins);

            if ($has_amount_received) {
                // fixed typo: exeSql (not excuteSql)
                exeSql("UPDATE goods_receipts SET amount_received = IFNULL(amount_received,0) + $apply WHERE grn_id = ".$row['id']);
            }

            $left -= $apply;
        }

        update_vendor_totals($vendor_id);

        // recompute for receipt
        $combined_paid_after = 0.0;
        foreach ($selected as $row) {
            if ($has_amount_received) {
                $grnR = getRowValues('goods_receipts', $row['id'], 'grn_id');
                $paidR = (float)($grnR['amount_received'] ?? 0);
            } else {
                $pR = getValues("(SELECT IFNULL(SUM(amount),0) AS paid FROM vendor_grn_payments WHERE grn_id=".$row['id'].") t","1");
                $paidR = (float)($pR['paid'] ?? 0);
            }
            $combined_paid_after += $paidR;
        }
        $paid_for_this_txn = $pay_now - max(0.0, $left);
        $combined_balance_after = max(0.0, $combined_total - $combined_paid_after);

        $head_of_account = 'Vendor GRN Payment';
        $paid_to = $vend['vendor_name'] ?? ('Vendor #'.$vendor_id);
        $grn_list = implode(', ', array_map(fn($r)=>$r['no'], $selected));
        $description = (count($selected) === 1) ? ("GRN: " . $selected[0]['no']) : ("GRNs: " . $grn_list);

        $pdf_title = 'Vendor GRN Payment';
        $pdf_rows[0] = ['Invoice #', (count($selected) === 1 ? ($selected[0]['invoice'] ?: $invoice) : 'MULTI')];
        $pdf_rows = array_merge($pdf_rows, [
            ['Vendor', $paid_to],
            ['Branch ID', $branch_id],
            ['GRNs Selected', $grn_list],
            ['Total Amount', number_format($combined_total,2)],
            ['Paid (till now)', number_format($combined_paid_after,2)],
            ['Balance', number_format($combined_balance_after,2)],
            ['Paid Now', number_format($paid_for_this_txn,2)],
        ]);
        if ($method==='online') $pdf_rows[] = ['UPI', ($upi_id !== '' ? $upi_id : '-')];
        if ($method==='bank') {
            // show the bank details we actually used
            if (isset($ins['bank_mode'])) $pdf_rows[] = ['Bank Mode', $ins['bank_mode']];
            $pdf_rows[] = ['Bank A/C', ($bank_account !== '' ? $bank_account : ($vend['account_number'] ?? '-'))];
            $pdf_rows[] = ['IFSC', ($bank_ifsc !== '' ? $bank_ifsc : ($vend['ifsc'] ?? '-'))];
            if ($cheque_no!=='') $pdf_rows[] = ['Cheque No', $cheque_no];
        }
        if ($notes!=='') $pdf_rows[] = ['Notes', $notes];

        $total_amount = $combined_total;

    } elseif ($pay_for === 'employee') {
        $employee_id = i(v('employee_id',0));
        $pay_period  = preg_replace('/\D/','', v('pay_period', date('Ym')));

        $ins = [
            'employee_id' => $employee_id,
            'pay_period'  => $pay_period,
            'amount'      => $pay_now,
            'method'      => $method,
            'voucher_no'  => $voucher,
            'invoice_no'  => $invoice,
            'paid_at'     => $paid_at,
            'remark'      => $notes,
        ];
        $colsESP = table_columns('employee_salary_payments');
        if (isset($colsESP['note'])) { $ins['note'] = $ins['remark']; unset($ins['remark']); }
        if ($pb = pick_payment_by_field('employee_salary_payments')) $ins[$pb] = $payment_by;
        safe_insert('employee_salary_payments', $ins);

        $emp = getRowValues('employees', $employee_id, 'id');
        if (!$emp && table_exists('employee')) $emp = getRowValues('employee', $employee_id, 'id');

        $salary  = (float)($emp['salary'] ?? 0);
        $paidRow = getValues("(SELECT IFNULL(SUM(amount),0) AS paid FROM employee_salary_payments WHERE employee_id=$employee_id AND pay_period='$pay_period') t","1");
        $paid    = (float)($paidRow['paid'] ?? 0);
        $balance = $salary - $paid;
        $total_amount = $salary;

        $head_of_account = 'Employee Salary';
        $paid_to = $emp['employee_name'] ?? ('Employee #'.$employee_id);
        $description = "Period: " . $pay_period;

        $pdf_title = 'Employee Salary Payment';
        $pdf_rows = array_merge($pdf_rows, [
            ['Employee', $paid_to],
            ['Role', $emp['role'] ?? '-'],
            ['Pay Period', $pay_period],
            ['Salary', number_format($salary,2)],
            ['Paid (till now)', number_format(max(0,$paid),2)],
            ['Balance', number_format(max(0,$balance),2)],
            ['Paid Now', number_format($pay_now,2)],
        ]);
        if ($notes!=='') $pdf_rows[] = ['Notes', $notes];

    } elseif ($pay_for === 'expenses') {
        $purpose = s(v('purpose',''));
        $custom  = s(v('custom_purpose',''));
        if ($purpose === '__other__') $purpose = $custom;

        $custom_total = (float)s(v('custom_total','0'));

        $row = exeSql("SELECT id, amount, balance_paid FROM expenses
                        WHERE purpose='".addslashes($purpose)."'
                        ORDER BY id DESC
                        LIMIT 1");

        if ($row) {
            $expId   = (int)$row[0]['id'];
            $newPaid = (float)$row[0]['balance_paid'] + $pay_now;

            $upd = [
                'balance_paid' => $newPaid,
                'method'       => $method,
                'voucher_no'   => $voucher,
                'invoice_no'   => $invoice,
                'paid_at'      => $paid_at,
                'remark'       => $notes,
            ];
            if ($pb = pick_payment_by_field('expenses')) $upd[$pb] = $payment_by;
            safe_update('expenses', $upd, ['id'=>$expId]);

            $total   = (float)$row[0]['amount'];
            $paid    = $newPaid;
            $balance = max(0.0, $total - $paid);
            $total_amount = $total;

        } else {
            $totalForNew = $custom_total > 0 ? $custom_total : $pay_now;
            $ins = [
                'purpose'      => $purpose !== '' ? $purpose : 'General Expense',
                'amount'       => $totalForNew,
                'balance_paid' => $pay_now,
                'method'       => $method,
                'voucher_no'   => $voucher,
                'invoice_no'   => $invoice,
                'paid_at'      => $paid_at,
                'remark'       => $notes,
                'payment_by'   => $payment_by ?: null,
            ];
            safe_insert('expenses', $ins);

            $total   = $totalForNew;
            $paid    = $pay_now;
            $balance = max(0.0, $totalForNew - $pay_now);
            $total_amount = $total;
        }

        $head_of_account = 'Expense';
        $paid_to = 'Various';
        $description = $purpose;

        $pdf_title = 'Expense Payment';
        $pdf_rows = array_merge($pdf_rows, [
            ['Purpose', $description],
            ['Total Amount', number_format($total,2)],
            ['Paid (till now)', number_format(max(0,$paid),2)],
            ['Balance', number_format(max(0,$balance),2)],
            ['Paid Now', number_format($pay_now,2)],
        ]);
        if ($notes!=='') $pdf_rows[] = ['Notes', $notes];

    } elseif ($pay_for === 'events') {
        $event_id = i(v('event_id',0));
        $event    = getRowValues('events', $event_id, 'event_id');
        $event_name = $event['event_name'] ?? ('#'.$event_id);

        if (table_exists('event_payments')) {
            $ins = [
                'event_id'   => $event_id,
                'amount'     => $pay_now,
                'method'     => $method,
                'voucher_no' => $voucher,
                'invoice_no' => $invoice,
                'paid_at'    => $paid_at,
                'remark'     => $notes,
            ];
            if ($pb = pick_payment_by_field('event_payments')) $ins[$pb] = $payment_by;
            safe_insert('event_payments', $ins);
        } else {
            $ins = [
                'purpose'    => 'Event Payment: '.$event_name,
                'amount'     => $pay_now,
                'method'     => $method,
                'voucher_no' => $voucher,
                'invoice_no' => $invoice,
                'paid_at'    => $paid_at,
                'remark'     => $notes,
            ];
            if ($pb = pick_payment_by_field('expenses')) $ins[$pb] = $payment_by;
            safe_insert('expenses', $ins);
        }

        if (table_exists('event_items') && $pay_now > 0) {
            $left = $pay_now;
            $itCols = table_columns('event_items');

            $items = exeSql("SELECT item_id, amount_received, total_amount
                              FROM event_items
                              WHERE event_id = $event_id
                              ORDER BY item_id ASC");
            foreach ($items as $it) {
                if ($left <= 0) break;
                $rec = (float)$it['amount_received'];
                $tot = (float)$it['total_amount'];
                $bal = max(0.0, $tot - $rec);
                if ($bal <= 0) continue;

                $apply  = min($left, $bal);
                $newRec = $rec + $apply;
                $newBal = max(0.0, $tot - $newRec);

                $upd = ['amount_received'=>$newRec, 'balance'=>$newBal];

                if (isset($itCols['paid_at']))     $upd['paid_at']    = $paid_at;
                if (isset($itCols['method']))      $upd['method']     = $method;
                if (isset($itCols['voucher_no']))  $upd['voucher_no'] = $voucher;
                if (isset($itCols['invoice_no']))  $upd['invoice_no'] = $invoice;
                if (isset($itCols['payment_by']))  $upd['payment_by'] = $payment_by;
                if ($notes !== '') {
                    if (isset($itCols['note']))       $upd['note']   = $notes;
                    elseif (isset($itCols['remark'])) $upd['remark'] = $notes;
                }

                safe_update('event_items', $upd, ['item_id'=>$it['item_id']]);
                $left -= $apply;
            }
        }

        $row = getValues("(SELECT IFNULL(SUM(total_amount),0) AS total,
                                     IFNULL(SUM(amount_received),0) AS paid
                              FROM event_items WHERE event_id=$event_id) t","1");
        $total = (float)($row['total'] ?? 0);
        $paid  = (float)($row['paid'] ?? 0);
        $balance = max(0.0, $total - $paid);
        $total_amount = $total;

        $head_of_account = 'Event Payment';
        $paid_to = $event_name;
        $description = 'Event Payment';

        $pdf_title = 'Event Payment';
        $pdf_rows = array_merge($pdf_rows, [
            ['Event', $event_name],
            ['Total Amount', number_format($total,2)],
            ['Paid (till now)', number_format(max(0,$paid),2)],
            ['Balance', number_format(max(0,$balance),2)],
            ['Paid Now', number_format($pay_now,2)],
        ]);
        if ($notes!=='') $pdf_rows[] = ['Notes', $notes];

    } else {
        // fallback to general expense
        $purpose = s(v('purpose','')) ?: 'General Expense';

        $row = exeSql("SELECT id, amount, balance_paid FROM expenses
                        WHERE purpose='".addslashes($purpose)."'
                        ORDER BY id DESC
                        LIMIT 1");

        if ($row) {
            $expId   = (int)$row[0]['id'];
            $newPaid = (float)$row[0]['balance_paid'] + $pay_now;
            $upd = [
                'balance_paid' => $newPaid,
                'method'       => $method,
                'voucher_no'   => $voucher,
                'invoice_no'   => $invoice,
                'paid_at'      => $paid_at,
                'remark'       => $notes,
            ];
            if ($pb = pick_payment_by_field('expenses')) $upd[$pb] = $payment_by;
            safe_update('expenses', $upd, ['id'=>$expId]);

            $total   = (float)$row[0]['amount'];
            $paid    = $newPaid;
            $balance = max(0.0, $total - $paid);
            $total_amount = $total;
        } else {
            $ins = [
                'purpose'      => $purpose,
                'amount'       => $pay_now,
                'balance_paid' => $pay_now,
                'method'       => $method,
                'voucher_no'   => $voucher,
                'invoice_no'   => $invoice,
                'paid_at'      => $paid_at,
                'remark'       => $notes,
            ];
            if ($pb = pick_payment_by_field('expenses')) $ins[$pb] = $payment_by;
            safe_insert('expenses', $ins);

            $total   = $pay_now;
            $paid    = $pay_now;
            $balance = 0.0;
            $total_amount = $pay_now;
        }

        $head_of_account = 'General Expense';
        $paid_to = 'General';
        $description = $purpose;

        $pdf_title = 'Expense Payment';
        $pdf_rows = array_merge($pdf_rows, [
            ['Purpose', $purpose !== '' ? $purpose : '-'],
            ['Total Amount', number_format($total,2)],
            ['Paid (till now)', number_format(max(0,$paid),2)],
            ['Balance', number_format(max(0,$balance),2)],
            ['Paid Now', number_format($pay_now,2)],
        ]);
    }

    // Redirect to voucher print
    $voucher_details = [
        'paid_to'        => $paid_to,
        'head_of_account'=> $head_of_account,
        'amount_figure'  => $pay_now,
        'amount_words'   => convert_number_to_rupees($pay_now),
        'voucher_no'     => $voucher,
        'date'           => date('d-m-Y', strtotime($payment_date)),
        'description'    => $description,
        'prepared_by'    => $payment_by ?: 'Accountant',
        'total_amount'   => $total_amount,
        'paid_so_far'    => $paid ?? 0,
        'notes'          => $notes,
    ];

    $queryString = http_build_query($voucher_details);
    header("Location: print_voucher.php?" . $queryString);
    exit;
}

/* ---------------- Page data ---------------- */
$vendors   = fetch_vendors();
$branches  = fetch_branches();
$employees = fetch_employees(); // initial (fallback/all)
$purposes  = fetch_expense_purposes();
$events    = fetch_events();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Payments</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{font-family:system-ui, Arial, sans-serif; margin:18px; max-width:980px}
  fieldset{border:1px solid #ddd; border-radius:10px; padding:16px; margin-bottom:18px}
  legend{font-weight:600}
  label{display:block; margin:8px 0 4px}
  input, select, textarea{width:100%; padding:8px; border:1px solid #ccc; border-radius:8px}
  .row{display:grid; grid-template-columns:1fr 1fr; gap:12px}
  .row-3{display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px}
  .hide{display:none !important;}
  .totals{display:grid; grid-template-columns:repeat(4,1fr); gap:12px}
  button{padding:10px 14px; border:0; border-radius:10px; background:#111; color:#fff; font-weight:600; cursor:pointer}
  .muted{color:#666; font-size:13px}
  .info{color:#0a6; font-size:13px}
  .grn-list{border:1px solid #ccc; border-radius:8px; padding:10px; max-height:220px; overflow:auto; background:#fafafa}
  .grn-item{display:flex; align-items:flex-start; gap:8px; padding:6px 4px; border-bottom:1px dashed #e5e5e5}
  .grn-item:last-child{border-bottom:none}
  .grn-amounts{font-size:12px; color:#475569}
</style>
</head>
<body>
  <?php if (file_exists(__DIR__.'/nav.php')) include __DIR__.'/nav.php'; ?>
<h1>Payments</h1>

<form method="post" id="paymentForm">
  <fieldset>
    <legend>Payment For</legend>
    <label>Type</label>
    <select name="pay_for" id="pay_for" required>
      <option value="">-- Select --</option>
      <option value="vendor">Vendor</option>
      <option value="employee">Employees</option>
      <option value="expenses">Expenses</option>
      <option value="events">Events</option>
    </select>
  </fieldset>

  <fieldset id="vendor_block" class="hide">
    <legend>Vendor</legend>
    <div class="row">
      <div>
        <label>Vendor</label>
        <select name="vendor_id" id="vendor_id">
          <option value="">-- Select vendor --</option>
          <?php foreach ($vendors as $v): ?>
            <option value="<?= (int)$v['vendor_id'] ?>"><?= h($v['vendor_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Branch</label>
        <select name="branch_id" id="branch_id">
          <option value="">-- Select branch --</option>
          <?php foreach ($branches as $b): ?>
            <option value="<?= (int)$b['branch_id'] ?>"><?= h($b['branch_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="muted">From <code>branches</code>.</div>
      </div>
    </div>

    <div class="row">
      <div>
        <label>GRN Number(s) (pending in this branch)</label>
        <div id="grn_list" class="grn-list">
          <div class="muted">Select a vendor and a branch to load GRNs…</div>
        </div>
        <div id="grn_help" class="muted"></div>
      </div>
      <div>
        <label>Vendor Bank (readonly)</label>
        <input type="text" id="vendor_bank_view" readonly placeholder="Auto-fills on vendor select">
      </div>
    </div>
  </fieldset>

  <fieldset id="employee_block" class="hide">
    <legend>Employee</legend>
    <div class="row">
      <div>
        <label>Branch</label>
        <select id="emp_branch_id">
          <option value="">-- Select branch --</option>
          <?php foreach ($branches as $b): ?>
            <option value="<?= (int)$b['branch_id'] ?>"><?= h($b['branch_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="muted">Filters the employees list below.</div>
      </div>
      <div>
        <label>Employee</label>
        <select name="employee_id" id="employee_id">
          <option value="">-- Select employee --</option>
          <?php foreach ($employees as $e): ?>
            <option value="<?= (int)$e['id'] ?>"><?= h($e['employee_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="row">
      <div>
        <label>Role</label>
        <input type="text" id="emp_role" readonly>
      </div>
      <div>
        <label>Salary</label>
        <input type="text" id="emp_salary" readonly>
      </div>
    </div>
    <div class="row">
      <div>
        <label>Pay Period (YYYYMM)</label>
        <input type="text" name="pay_period" id="pay_period" value="<?= date('Ym') ?>">
      </div>
      <div style="align-self:end">
        <div id="emp_nopending" class="info hide">No pending salary for the selected period.</div>
      </div>
    </div>
  </fieldset>

  <fieldset id="expenses_block" class="hide">
    <legend>Expenses</legend>
    <div class="row">
      <div>
        <label>Purpose</label>
        <select name="purpose" id="purpose">
          <option value="">-- Select purpose --</option>
          <?php foreach ($purposes as $p): ?>
            <option value="<?= h($p) ?>"><?= h($p) ?></option>
          <?php endforeach; ?>
          <option value="__other__">Other…</option>
        </select>
        <div class="muted">Values pulled from <code>expenses.purpose</code>. Choose <em>Other…</em> to add a new purpose.</div>
      </div>
      <div id="custom_purpose_wrap" class="hide">
        <label>New Purpose</label>
        <input type="text" id="custom_purpose" name="custom_purpose" placeholder="Enter new purpose">
      </div>
    </div>

    <div class="row hide" id="custom_total_wrap">
      <div>
        <label>Total (new purpose)</label>
        <input type="number" step="0.01" name="custom_total" id="custom_total" placeholder="e.g. 10000.00">
      </div>
      <div class="muted" style="align-self:end">If blank, we’ll treat the first payment as the total.</div>
    </div>

    <div id="exp_nopending" class="info hide">No pending amount for this purpose.</div>
  </fieldset>

  <fieldset id="events_block" class="hide">
    <legend>Events</legend>
    <div class="row">
      <div>
        <label>Event Name</label>
        <select name="event_id" id="event_id">
          <option value="">-- Select event --</option>
          <?php foreach ($events as $ev): ?>
            <option value="<?= (int)$ev['event_id'] ?>"><?= h($ev['event_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="muted">Pulled from <code>events</code>.</div>
      </div>
    </div>
    <div id="evt_nopending" class="info hide">No pending amount for this event.</div>
  </fieldset>

  <fieldset id="amounts_block" class="hide">
    <legend>Amounts</legend>
    <div class="totals">
      <div>
        <label>Total Amount</label>
        <input type="number" step="0.01" id="total_amount" readonly>
      </div>
      <div>
        <label>Paid (till now)</label>
        <input type="number" step="0.01" id="paid_so_far" readonly>
      </div>
      <div>
        <label>Balance</label>
        <input type="number" step="0.01" id="balance" readonly>
      </div>
      <div>
        <label>Pay Now</label>
        <input type="number" step="0.01" name="pay_now" id="pay_now" min="0" value="0">
      </div>
    </div>
  </fieldset>

  <fieldset>
    <legend>Payment Method</legend>
    <div class="row">
      <div>
        <label>Method</label>
        <select name="method" id="method" required>
          <option value="">-- Select --</option>
          <option value="online">Online</option>
          <option value="cash">Cash</option>
          <option value="bank">Bank</option>
        </select>
      </div>
      <div id="online_wrap" class="hide">
        <label>UPI ID</label>
        <input type="text" name="upi_id" id="upi_id" placeholder="e.g. name@upi">
      </div>
    </div>

    <div id="bank_wrap" class="hide">
      <div class="row">
        <div>
          <label>Bank Mode</label>
          <select name="bank_mode" id="bank_mode">
            <option value="transfer">Bank Transfer</option>
            <option value="cheque">Cheque</option>
          </select>
        </div>
      </div>
      <div class="row">
        <div>
          <label>Account Number</label>
          <input type="text" name="bank_account" id="bank_account" placeholder="Auto-fills for vendor">
        </div>
        <div>
          <label>IFSC</label>
          <input type="text" name="bank_ifsc" id="bank_ifsc" placeholder="Auto-fills for vendor">
        </div>
      </div>
      <div id="cheque_wrap" class="hide">
        <label>Cheque Number</label>
        <input type="text" name="cheque_no" id="cheque_no">
      </div>
    </div>
  </fieldset>

  <fieldset>
    <legend>Meta</legend>
    <div class="row-3">
      <div>
        <label>Payment Date</label>
        <input type="datetime-local" name="payment_date" id="payment_date" value="<?= date('Y-m-d\TH:i') ?>">
      </div>
      <div>
        <label>Payment By</label>
        <input type="text" name="payment_by" id="payment_by" placeholder="Your name">
      </div>
      <div>
        <label>Notes</label>
        <input type="text" name="notes" id="notes" placeholder="Optional">
      </div>
    </div>
  </fieldset>

  <div id="grn_hidden_inputs"></div>

  <button type="submit">Submit & Download Receipt</button>
</form>

<script>
const $ = s=>document.querySelector(s);

const payFor   = $('#pay_for');
const vendorBl = $('#vendor_block');
const empBl    = $('#employee_block');
const expBl    = $('#expenses_block');
const evtBl    = $('#events_block');

const vendorSel = $('#vendor_id');
const branchSel = $('#branch_id');
const grnList   = $('#grn_list');
const grnHelp   = $('#grn_help');

const amountsBl = $('#amounts_block');
const expNoPending = $('#exp_nopending');
const empNoPending = $('#emp_nopending');
const evtNoPending = $('#evt_nopending');

const totalAmount = $('#total_amount');
const paidSoFar   = $('#paid_so_far');
const balance     = $('#balance');
const payNow      = $('#pay_now');

const methodSel   = $('#method');
const onlineWrap  = $('#online_wrap');
const bankWrap    = $('#bank_wrap');
const chequeWrap  = $('#cheque_wrap');
const bankAcc     = $('#bank_account');
const bankIfsc    = $('#bank_ifsc');
const vendorBankV = $('#vendor_bank_view');
const bankModeSel = $('#bank_mode');

const empBranch   = $('#emp_branch_id');
const empSel      = $('#employee_id');
const empRole     = $('#emp_role');
const empSalary   = $('#emp_salary');
const payPeriod   = $('#pay_period');

const purposeSel  = $('#purpose');
const customWrap  = $('#custom_purpose_wrap');
const customTotalWrap = $('#custom_total_wrap');
const customTotal = $('#custom_total');

const eventSel    = $('#event_id');
const hiddenGrnInputs = $('#grn_hidden_inputs');

function show(el){ el.classList.remove('hide'); }
function hide(el){ el.classList.add('hide'); }
function setVal(el,v){ el.value = v ?? ''; }
function resetAmounts(){ setVal(totalAmount,''); setVal(paidSoFar,''); setVal(balance,''); setVal(payNow,'0'); }
function showAmounts(){ show(amountsBl); }
function hideAmounts(){ hide(amountsBl); }
function setSectionVisibility(section, on){
  if (!section) return;
  if (on) show(section); else hide(section);
  section.querySelectorAll('input,select,textarea,button').forEach(el=>{ el.disabled = !on; });
}

// Type toggles
payFor.addEventListener('change', ()=>{
  [vendorBl, empBl, expBl, evtBl].forEach(hide);
  [expNoPending, empNoPending, evtNoPending].forEach(hide);
  hide(customTotalWrap);
  resetAmounts();
  if (payFor.value==='vendor') show(vendorBl);
  if (payFor.value==='employee') show(empBl);
  if (payFor.value==='expenses') show(expBl);
  if (payFor.value==='events')   show(evtBl);
});

// ------- Vendor GRNs
function renderGRNCheckboxes(list){
  grnList.innerHTML = '';
  if (!Array.isArray(list) || list.length===0){
    grnList.innerHTML = '<div class="muted">No GRNs with positive balance in this branch.</div>';
    hideAmounts(); resetAmounts(); return;
  }
  const frag = document.createDocumentFragment();
  list.forEach(g=>{
    const row = document.createElement('label');
    row.className = 'grn-item';
    const cb = document.createElement('input');
    cb.type = 'checkbox';
    cb.className = 'grn-cb';
    cb.value = g.grn_id;
    cb.dataset.total = g.total_amount;
    cb.dataset.paid  = g.paid;
    cb.dataset.balance = g.balance;

    const info = document.createElement('div');
    const title = document.createElement('div');
    title.innerHTML = `<strong>${g.grn_number}</strong>`;
    const meta  = document.createElement('div');
    meta.className = 'grn-amounts';
    meta.textContent = `Total ₹${Number(g.total_amount).toFixed(2)} | Paid ₹${Number(g.paid).toFixed(2)} | Bal ₹${Number(g.balance).toFixed(2)}`;

    info.appendChild(title);
    info.appendChild(meta);

    row.appendChild(cb);
    row.appendChild(info);
    frag.appendChild(row);
  });
  grnList.appendChild(frag);

  document.querySelectorAll('.grn-cb').forEach(cb=>{
    cb.addEventListener('change', recalcFromChecked);
  });
}

function refreshGRNs(){
  const vid = vendorSel.value, bid = branchSel.value;
  grnList.innerHTML = '<div class="muted">Loading…</div>';
  hiddenGrnInputs.innerHTML = '';
  resetAmounts(); hideAmounts();
  if (!vid || !bid){
    grnList.innerHTML = '<div class="muted">Select a vendor and a branch to load GRNs…</div>';
    return;
  }
  fetch(`?ajax=grns&vendor_id=${vid}&branch_id=${bid}`).then(r=>r.json()).then(list=>{
    renderGRNCheckboxes(list);
  });
}

vendorSel && vendorSel.addEventListener('change', ()=>{
  const vid = vendorSel.value;
  if (!vid) return;
  fetch('?ajax=vendor_bank&vendor_id='+vid).then(r=>r.json()).then(j=>{
    const view = (j.account_number?('A/C: '+j.account_number):'') + (j.ifsc?(' | IFSC: '+j.ifsc):'');
    if (vendorBankV) vendorBankV.value = view;
    if (!bankAcc.value) bankAcc.value = j.account_number||'';
    if (!bankIfsc.value) bankIfsc.value = j.ifsc||'';
  });
  refreshGRNs();
});
branchSel && branchSel.addEventListener('change', refreshGRNs);

function recalcFromChecked(){
  const cbs = Array.from(document.querySelectorAll('.grn-cb:checked'));
  hiddenGrnInputs.innerHTML = '';
  if (cbs.length===0){ resetAmounts(); hideAmounts(); return; }
  let total=0, paid=0, bal=0;
  cbs.forEach(cb=>{
    total += parseFloat(cb.dataset.total||'0');
    paid  += parseFloat(cb.dataset.paid||'0');
    bal   += parseFloat(cb.dataset.balance||'0');

    const h = document.createElement('input');
    h.type = 'hidden';
    h.name = 'grn_ids[]';
    h.value = cb.value;
    hiddenGrnInputs.appendChild(h);
  });
  setVal(totalAmount, total.toFixed(2));
  setVal(paidSoFar, paid.toFixed(2));
  setVal(balance, bal.toFixed(2));
  if (bal>0){ setVal(payNow, bal.toFixed(2)); showAmounts(); } else { hideAmounts(); }
}

// ------- Employees (BRANCH FILTER)
function populateEmployees(list){
  empSel.innerHTML = '<option value="">-- Select employee --</option>';
  if (!Array.isArray(list) || list.length===0) return;
  const frag = document.createDocumentFragment();
  list.forEach(e=>{
    const opt = document.createElement('option');
    opt.value = e.id;
    opt.textContent = e.employee_name;
    frag.appendChild(opt);
  });
  empSel.appendChild(frag);
}

empBranch && empBranch.addEventListener('change', ()=>{
  const bid = empBranch.value;
  empSel.innerHTML = '<option value="">Loading…</option>';
  fetch(`?ajax=employees&branch_id=${encodeURIComponent(bid||0)}`).then(r=>r.json()).then(list=>{
    populateEmployees(list);
    // clear employee details
    empRole.value = '';
    empSalary.value = '';
    resetAmounts(); hideAmounts(); hide(empNoPending);
  });
});

function loadEmp(){
  hide(empNoPending); resetAmounts();
  const id = empSel.value; if (!id) return;
  const period = (payPeriod.value||'').replace(/\D/g,'');
  fetch(`?ajax=employee&employee_id=${id}&period=${period}`).then(r=>r.json()).then(j=>{
    empRole.value = j.role||'';
    const salary = j.salary?Number(j.salary):0;
    const paid   = j.paid?Number(j.paid):0;
    const bal = salary - paid;
    empSalary.value = salary?salary.toFixed(2):'';
    setVal(totalAmount, salary?salary.toFixed(2):'');
    setVal(paidSoFar, paid.toFixed(2));
    setVal(balance, bal.toFixed(2));
    if (bal>0){ setVal(payNow, bal.toFixed(2)); showAmounts(); }
    else { hideAmounts(); show(empNoPending); }
  });
}
empSel && empSel.addEventListener('change', loadEmp);
payPeriod && payPeriod.addEventListener('change', loadEmp);

// ------- Expenses
purposeSel.addEventListener('change', ()=>{
  hide(expNoPending);
  resetAmounts();

  if (purposeSel.value === '__other__') {
    show(customWrap);
    show(customTotalWrap);
    setVal(paidSoFar, (0).toFixed(2));
    setVal(balance, '');
    showAmounts();
    setTimeout(()=>customTotal.focus(), 0);
  } else if (purposeSel.value) {
    hide(customWrap); hide(customTotalWrap);
    fetch(`?ajax=expense_summary&purpose=${encodeURIComponent(purposeSel.value)}`).then(r=>r.json()).then(j=>{
      const total = Number(j.total||0), paid=Number(j.paid||0), bal=Number(j.balance||0);
      setVal(totalAmount, total.toFixed(2));
      setVal(paidSoFar, paid.toFixed(2));
      setVal(balance, bal.toFixed(2));
      if (bal>0){ setVal(payNow, bal.toFixed(2)); showAmounts(); }
      else { hideAmounts(); show(expNoPending); }
    });
  } else {
    hide(customWrap); hide(customTotalWrap); hide(expNoPending); hideAmounts();
  }
});
customTotal && customTotal.addEventListener('input', ()=>{
  const t = parseFloat(customTotal.value||'0');
  setVal(totalAmount, t>0 ? t.toFixed(2) : '');
  const p = parseFloat(paidSoFar.value||'0');
  const n = parseFloat(payNow.value||'0');
  const bal = t - p - n;
  setVal(balance, isFinite(bal)?bal.toFixed(2):'');
});

// ------- Events
eventSel && eventSel.addEventListener('change', ()=>{
  hide(evtNoPending); resetAmounts();
  const id = eventSel.value; if (!id) return;
  fetch(`?ajax=event_summary&event_id=${id}`).then(r=>r.json()).then(j=>{
    const total = Number(j.total||0), paid=Number(j.paid||0), bal=Number(j.balance||0);
    setVal(totalAmount, total.toFixed(2));
    setVal(paidSoFar, paid.toFixed(2));
    setVal(balance, bal.toFixed(2));
    if (bal>0){ setVal(payNow, bal.toFixed(2)); showAmounts(); }
    else { hideAmounts(); show(evtNoPending); }
  });
});

// ------- Payment Method toggles (robust)
function toggleMethodUI(){
  setSectionVisibility(onlineWrap,false);
  setSectionVisibility(bankWrap,false);
  setSectionVisibility(chequeWrap,false);

  if (methodSel.value==='online') {
    setSectionVisibility(onlineWrap,true);
  } else if (methodSel.value==='bank') {
    setSectionVisibility(bankWrap,true);
    if (bankModeSel && bankModeSel.value==='cheque') {
      setSectionVisibility(chequeWrap,true);
    }
  }
}
methodSel.addEventListener('change', toggleMethodUI);
bankModeSel && bankModeSel.addEventListener('change', ()=>{
  if (methodSel.value!=='bank') return;
  setSectionVisibility(chequeWrap, bankModeSel.value==='cheque');
});
document.addEventListener('DOMContentLoaded', toggleMethodUI);

// Live balance
function recomputeBalance(){
  const t = parseFloat(totalAmount.value||'0');
  const p = parseFloat(paidSoFar.value||'0');
  const n = parseFloat(payNow.value||'0');
  const bal = t - p - n;
  setVal(balance, isFinite(bal)?bal.toFixed(2):'');
}
payNow.addEventListener('input', recomputeBalance);
totalAmount.addEventListener('input', recomputeBalance);
paidSoFar.addEventListener('input', recomputeBalance);
</script>
</body>
</html>
