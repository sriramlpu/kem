<?php
/**
 * Payments View — Vendor / Employee / Expense / Event (from event_items)
 * - Note column shows: Vendor→GRN, Employee→Role, Event→Venue, Expense→Purpose
 * - Defaults: Vendor + last 365 days
 * * **FIXED: Now includes and displays the dedicated 'payment_reference' column.**
 */

require_once("../auth.php");
requireRole(['Cashier','Admin']);

/* Capture any stray output before JSON/CSV */
if (!ob_get_level()) ob_start();

require_once(__DIR__ . '/../functions.php');

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

/* ---------- helpers ---------- */
function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function nf($n){ return number_format((float)$n, 2, '.', ''); }
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

/* ---------- picklists for page ---------- */
$vendors     = t_exists('vendors')     ? (exeSql("SELECT vendor_id, vendor_name FROM vendors ORDER BY vendor_name") ?: []) : [];
$employees = t_exists('employees') ? (exeSql("SELECT id, employee_name FROM employees ORDER BY employee_name") ?: []) : [];
$branches  = t_exists('branches')  ? (exeSql("SELECT branch_id, branch_name FROM branches ORDER BY branch_name") ?: []) : [];

/* ---------- query builder (FIXED for stability and new columns) ---------- */
function build_union_sql(array $f): string {
    $type              = $f['type'] ?? '';
    $vendor_id         = (int)($f['vendor_id'] ?? 0);
    $employee_id = (int)($f['employee_id'] ?? 0);
    $branch_id         = (int)($f['branch_id'] ?? 0);
    $method            = trim($f['method'] ?? '');
    $fromDt            = $f['fromDt'] ?? null;
    $toDt              = $f['toDt'] ?? null;

    $parts = [];

    $VG = t_cols('vendor_grn_payments');
    $ES = t_cols('employee_salary_payments');
    $EX = t_cols('expenses');
    $EI = t_cols('event_items');
    $V = t_cols('vendors');
    $E = t_cols('employees');
    $G = t_cols('goods_receipts');
    
    $vendor_has_redemption_used = isset($VG['redemption_used']);
    $employee_has_advance = isset($ES['advance']);

    // Check for the dedicated payment_reference column in target tables
    $vendor_has_payment_ref = isset($VG['payment_reference']);
    $employee_has_payment_ref = isset($ES['payment_reference']);
    $expense_has_payment_ref = isset($EX['payment_reference']);

    // Common safe select fields (for all UNION parts)
    $safe_selects = [
        'account_number' => 'NULL', 'ifsc_code' => 'NULL', 'ref_number' => 'NULL', 
        'payment_mode' => 'NULL', 'paid_at' => 'NULL', 'amount' => '0.00', 
        'advance_used' => '0.00', 'redemption_points_used' => '0.00', 'total_paid_amount' => '0.00',
        'voucher_no' => 'NULL', 'invoice_no' => 'NULL', 'note' => 'NULL', 
        'payment_by' => 'NULL', 'extra_branch' => 'NULL'
    ];

    /* ---- Vendor payments ---- */
    if (($type==='' || $type==='vendor') && t_exists('vendor_grn_payments')) {
        $w = ["1=1"];
        if ($vendor_id) $w[] = "p.vendor_id = $vendor_id";
        if ($method !== '' && isset($VG['method'])) $w[] = "p.method = '".addslashes($method)."'";
        if ($fromDt && isset($VG['paid_at'])) $w[] = "p.paid_at >= '$fromDt'";
        if ($toDt  && isset($VG['paid_at'])) $w[] = "p.paid_at <= '$toDt'";
        if ($branch_id && isset($VG['branch_id'])) $w[] = "p.branch_id = $branch_id";

        $sel_adv = isset($VG['advance_used']) ? "p.advance_used" : "0.00";
        $sel_red = $vendor_has_redemption_used ? "p.redemption_used" : "0.00";
        $sel_total = "p.amount + $sel_adv + $sel_red";
        
        $sel_acc = isset($V['account_number']) ? "v.account_number" : "NULL";
        $sel_ifsc = isset($V['ifsc']) ? "v.ifsc" : "NULL";
        
        // ** MODIFIED: Prioritize the dedicated payment_reference column **
        $sel_ref = $vendor_has_payment_ref 
                 ? "p.payment_reference" 
                 : (isset($VG['upi_id']) ? "p.upi_id" : (isset($VG['cheque_no']) ? "p.cheque_no" : "NULL"));

        // MODIFIED: Concatenate payment mode with reference for display only if payment is bank/online
        $sel_pmode = "CASE WHEN p.method IN ('bank', 'online') THEN CONCAT(COALESCE(UPPER(p.method), ''), '/', COALESCE(UPPER(p.bank_mode), ''), ' (', COALESCE($sel_ref, ''), ')') ELSE UPPER(p.method) END";
        
        $sel_note_vendor = "''";
        $joinGRN = "";
        if (t_exists('goods_receipts') && isset($G['grn_number']) && isset($VG['grn_id'])) {
            $joinGRN = "LEFT JOIN goods_receipts g ON g.grn_id = p.grn_id";
            $sel_grn_info = "CASE WHEN g.grn_number IS NOT NULL AND g.grn_number<>'' THEN CONCAT('GRN: ', g.grn_number) ELSE '' END";
            $sel_note_vendor = $sel_grn_info; // This provides the specific GRN number for the note column
        }
        
        $parts[] = "
            SELECT
                'vendor' AS pay_source,
                p.paid_at AS paid_at,
                ".(t_exists('vendors') ? "v.vendor_name" : "CONCAT('Vendor #', p.vendor_id)")." AS party,
                $sel_acc AS account_number,
                $sel_ifsc AS ifsc_code,
                $sel_pmode AS payment_mode,
                $sel_ref AS ref_number,
                p.amount AS amount,
                $sel_adv AS advance_used,
                $sel_red AS redemption_points_used,
                $sel_total AS total_paid_amount,
                p.voucher_no AS voucher_no,
                p.invoice_no AS invoice_no,
                $sel_note_vendor AS note,
                p.payment_by AS payment_by,
                b.branch_name AS extra_branch
            FROM vendor_grn_payments p
            LEFT JOIN vendors v ON v.vendor_id = p.vendor_id
            LEFT JOIN branches b ON b.branch_id = p.branch_id
            $joinGRN
            WHERE ".implode(' AND ', $w)."
        ";
    }

    /* ---- Employee salary payments ---- */
    if (($type==='' || $type==='employee') && t_exists('employee_salary_payments')) {
        $w = ["1=1"];
        if ($employee_id) $w[] = "p.employee_id = $employee_id";
        if ($fromDt && isset($ES['paid_at'])) $w[] = "p.paid_at >= '$fromDt'";
        if ($toDt  && isset($ES['paid_at'])) $w[] = "p.paid_at <= '$toDt'";

        $sel_adv = $employee_has_advance ? "p.advance" : "0.00";  
        $sel_total = "p.amount + $sel_adv"; 
        $roleExpr = "NULL";
        foreach (['role'] as $rc) { // Use minimal columns for safety
            if (!empty($E[$rc])) { $roleExpr = "e.$rc"; break; }
        }
        $note_employee = "CASE WHEN $roleExpr IS NOT NULL AND $roleExpr<>'' THEN CONCAT('Role: ', $roleExpr) ELSE '' END";
        
        $sel_acc = isset($E['account_number']) ? "e.account_number" : "NULL";
        $sel_ifsc = isset($E['ifsc']) ? "e.ifsc" : "NULL";
        $sel_pmode = "'SALARY'";

        $parts[] = "
            SELECT
                'employee' AS pay_source,
                p.paid_at AS paid_at,
                ".(t_exists('employees') ? "e.employee_name" : "CONCAT('Emp #', p.employee_id)")." AS party,
                $sel_acc AS account_number,
                $sel_ifsc AS ifsc_code,
                $sel_pmode AS payment_mode,
                ".($employee_has_payment_ref ? "p.payment_reference" : "NULL")." AS ref_number,
                p.amount AS amount,
                $sel_adv AS advance_used,
                0.00 AS redemption_points_used,
                $sel_total AS total_paid_amount,
                p.voucher_no AS voucher_no,
                p.invoice_no AS invoice_no,
                $note_employee AS note,
                p.payment_by AS payment_by,
                b.branch_name AS extra_branch
            FROM employee_salary_payments p
            LEFT JOIN employees e ON e.id = p.employee_id
            LEFT JOIN branches b ON b.branch_id = e.branch_id
            WHERE ".implode(' AND ', $w)."
        ";
    }

    /* ---- Expenses ---- */
    if (($type==='' || $type==='expense') && t_exists('expenses')) {
        $w = ["1=1"];
        $sel_date = isset($EX['paid_at']) ? "e.paid_at" : "NULL";
        if ($fromDt && $sel_date !== 'NULL') $w[] = "$sel_date >= '$fromDt'";
        if ($toDt  && $sel_date !== 'NULL') $w[] = "$sel_date <= '$toDt'";
        
        $sel_acc = isset($EX['account_no']) ? "e.account_no" : "NULL";
        $sel_ifsc = isset($EX['ifsc_code']) ? "e.ifsc_code" : "NULL";
        $sel_pmode = "COALESCE(UPPER(e.method), '')";

        $parts[] = "
            SELECT
                'expense' AS pay_source,
                e.paid_at AS paid_at,
                e.purpose AS party,
                $sel_acc AS account_number,
                $sel_ifsc AS ifsc_code,
                $sel_pmode AS payment_mode,
                ".($expense_has_payment_ref ? "e.payment_reference" : "NULL")." AS ref_number,
                e.amount AS amount,
                0.00 AS advance_used,
                0.00 AS redemption_points_used,
                e.amount AS total_paid_amount,
                e.voucher_no AS voucher_no,
                e.invoice_no AS invoice_no,
                e.purpose AS note,
                e.payment_by AS payment_by,
                b.branch_name AS extra_branch
            FROM expenses e
            LEFT JOIN branches b ON b.branch_id = e.branch_id
            WHERE ".implode(' AND ', $w)."
        ";
    }

    /* ---- Events (from event_items) ---- */
    if (($type==='' || $type==='event') && t_exists('event_items')) {
        $w = ["ei.amount_received > 0"]; // Filter only paid events
        if ($fromDt && isset($EI['created_at'])) $w[] = "ei.created_at >= '$fromDt'";
        if ($toDt  && isset($EI['created_at'])) $w[] = "ei.created_at <= '$toDt'";

        $sel_party = t_exists('events') ? "ev.event_name" : "CONCAT('Event #', ei.event_id)";

        $parts[] = "
            SELECT
                'event' AS pay_source,
                ei.created_at AS paid_at,
                $sel_party AS party,
                NULL AS account_number,
                NULL AS ifsc_code,
                'N/A' AS payment_mode,
                NULL AS ref_number,
                ei.amount_received AS amount,
                0.00 AS advance_used,
                0.00 AS redemption_points_used,
                ei.amount_received AS total_paid_amount,
                ei.voucher_no AS voucher_no,
                ei.invoice_no AS invoice_no,
                ei.note AS note,
                ei.payment_by AS payment_by,
                b.branch_name AS extra_branch
            FROM event_items ei
            LEFT JOIN events ev ON ev.event_id = ei.event_id
            LEFT JOIN branches b ON b.branch_id = ei.branch_id
            WHERE ".implode(' AND ', $w)."
        ";
    }

    if (!$parts) return '';
    return implode(" UNION ALL ", $parts) . " ORDER BY paid_at DESC LIMIT 1000";
}

/* ============================= AJAX ============================= */
if (isset($_GET['ajax'])) {
    $act       = $_GET['ajax'];
    $type      = trim($_GET['type'] ?? '');
    $vendor_id = (int)($_GET['vendor_id'] ?? 0);
    $employee_id = (int)($_GET['employee_id'] ?? 0);
    $branch_id = (int)($_GET['branch_id'] ?? 0);
    $method    = trim($_GET['method'] ?? '');
    $from      = trim($_GET['from'] ?? '');
    $to        = trim($_GET['to'] ?? '');

    // Derive type server-side too
    if ($employee_id) { $type = 'employee'; } 
    // else if ($type === '' && ($vendor_id || $method)) { $type = 'vendor'; } // Keep user selection if set
    
    $fromDt = ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)) ? "$from 00:00:00" : null;
    $toDt   = ($to    && preg_match('/^\d{4}-\d{2}-\d{2}$/',$to))    ? "$to 23:59:59"    : null;

    $filters = compact('type','vendor_id','employee_id','branch_id','method','fromDt','toDt');

    try {
        $sql  = build_union_sql($filters);
        $rows = $sql ? (exeSql($sql) ?: []) : [];

        if ($act === 'list') {
            while (ob_get_level()) { @ob_end_clean(); }
            header('Content-Type: application/json; charset=UTF-8');
            
            $sum_net_paid = 0.0;
            $sum_adv_used = 0.0;
            $sum_red_used = 0.0;
            $sum_total_paid = 0.0;
            foreach ($rows as $r){ 
                $sum_net_paid += (float)($r['amount'] ?? 0); 
                $sum_adv_used += (float)($r['advance_used'] ?? 0); 
                $sum_red_used += (float)($r['redemption_points_used'] ?? 0);
                $sum_total_paid += (float)($r['total_paid_amount'] ?? 0);
            }
            
            echo json_encode([
                'data'   => $rows,
                'totals' => [
                    'count'=>count($rows), 
                    'amount_fmt'=>'₹ '.nf($sum_net_paid),
                    'advance_used_fmt'=>'₹ '.nf($sum_adv_used),
                    'redemption_used_fmt'=>'₹ '.nf($sum_red_used),
                    'total_paid_amount_fmt'=>'₹ '.nf($sum_total_paid)
                ]
            ]);
            exit;
        }

        if ($act === 'export_csv') {
            while (ob_get_level()) { @ob_end_clean(); }
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=payments_view_'.date('Ymd_His').'.csv');
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Type','Paid At','Party/Purpose','Account No','IFSC Code','Payment Mode','Ref No','Total Amount','Net Paid','Used Advance','Used Redemption', 
                'Voucher No','Invoice No','Notes','Payment By','Branch'
            ]);
            foreach ($rows as $r){
                fputcsv($out, [
                    $r['pay_source'] ?? '',
                    $r['paid_at'] ?? '',
                    $r['party'] ?? '',
                    $r['account_number'] ?? '',
                    $r['ifsc_code'] ?? '',
                    $r['payment_mode'] ?? '',
                    $r['ref_number'] ?? '',
                    $r['total_paid_amount'] ?? 0,
                    $r['amount'] ?? 0,
                    $r['advance_used'] ?? 0, 
                    $r['redemption_points_used'] ?? 0, 
                    $r['voucher_no'] ?? '',
                    $r['invoice_no'] ?? '',
                    $r['note'] ?? '',
                    $r['payment_by'] ?? '',
                    $r['extra_branch'] ?? ''
                ]);
            }
            fclose($out);
            exit;
        }

        while (ob_get_level()) { @ob_end_clean(); }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['data'=>[], 'totals'=>['count'=>0,'amount_fmt'=>'₹ 0.00','advance_used_fmt'=>'₹ 0.00','redemption_used_fmt'=>'₹ 0.00', 'total_paid_amount_fmt'=>'₹ 0.00']]);
        exit;

    } catch (Throwable $e) {
        // Output error details for debugging (only in development)
        error_log("Payments View Fatal Error: " . $e->getMessage() . " on line " . $e->getLine());
        while (ob_get_level()) { @ob_end_clean(); }
        header('Content-Type: application/json; charset=UTF-8');
        // Return a clean error state for the client to prevent a blank page
        echo json_encode(['data'=>[], 'totals'=>['count'=>0,'amount_fmt'=>'₹ 0.00','advance_used_fmt'=>'₹ 0.00','redemption_used_fmt'=>'₹ 0.00', 'total_paid_amount_fmt'=>'₹ 0.00']]);
        exit;
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Payments View</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.10/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<style>
    body{background:#f7f7f7}
    .container-max{max-width:1400px}
    .card{border-radius:12px}
    .dt-buttons .btn{margin-right:8px}
</style>
</head>
<body>
    
<div class="container container-max py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Payments View</h3>
        <div>
            
            <a href="dashboard" class="btn btn-sm btn-outline-secondary">Back to Dashboard</a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label mb-1">Type</label>
                    <select id="f_type" class="form-select form-select-sm">
                        <option value="vendor" selected>Vendor</option>
                        <option value="employee">Employee</option>
                        <option value="expense">Expense</option>
                        <option value="event">Event</option>
                        <option value="">All</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Vendor</label>
                    <select id="f_vendor" class="form-select form-select-sm">
                        <option value="">All</option>
                        <?php foreach($vendors as $v): ?>
                            <option value="<?= (int)$v['vendor_id'] ?>"><?= h($v['vendor_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Employee</label>
                    <select id="f_employee" class="form-select form-select-sm">
                        <option value="">All</option>
                        <?php foreach($employees as $e): ?>
                            <option value="<?= (int)$e['id'] ?>"><?= h($e['employee_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label mb-1">Method (Vendor/Expense)</label>
                    <select id="f_method" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="cash">cash</option>
                        <option value="bank">bank</option>
                        <option value="online">online</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label mb-1">Branch</label>
                    <select id="f_branch" class="form-select form-select-sm">
                        <option value="">All</option>
                        <?php foreach($branches as $b): ?>
                            <option value="<?= (int)$b['branch_id'] ?>"><?= h($b['branch_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label mb-1">From</label>
                    <input type="date" id="f_from" class="form-control form-select-sm form-control-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">To</label>
                    <input type="date" id="f_to" class="form-control form-select-sm form-control-sm">
                </div>

                <div class="col-12 d-flex gap-2">
                    <button id="btn_search" class="btn btn-sm btn-primary">Search</button>
                    <button id="btn_reset" class="btn btn-sm btn-outline-secondary">Reset</button>
                    <button id="btn_csv" class="btn btn-sm btn-success">Export CSV</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table id="tbl" class="table table-striped table-bordered w-100">
                <thead class="table-dark">
                    <tr>
                        <th>Type</th>
                        <th>Paid At</th>
                        <th>Party / Purpose</th>
                        <th>Account No.</th>
                        <th>IFSC Code</th>
                        <th>Payment Mode</th>
                        <th>Ref. No.</th>
                        <th class="text-end">Total Amount</th>
                        <th class="text-end">Net Paid</th>
                        <th class="text-end">Used Advance</th>
                        <th class="text-end">Used Redemption</th>
                        <th>Voucher No</th>
                        <th>Invoice No</th>
                        <th>Notes</th>
                        <th>Payment By</th>
                        <th>Branch</th>
                        <th>Print</th>
                    </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                    <tr>
                        <th colspan="7" class="text-end">Total:</th>
                        <th class="text-end" id="ft_total_amount">₹ 0.00</th>
                        <th class="text-end" id="ft_amount">₹ 0.00</th>
                        <th class="text-end" id="ft_advance_used">₹ 0.00</th>
                        <th class="text-end" id="ft_redemption_used">₹ 0.00</th>
                        <th colspan="5"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.10/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.10/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<script>
jQuery(function($){
    let dt = null, reloadTimer = null;

    function qs(params){
        return Object.entries(params)
            .filter(([k,v]) => v !== '' && v != null)
            .map(([k,v]) => encodeURIComponent(k)+'='+encodeURIComponent(v))
            .join('&');
    }

    function currentFilters(){
        const typeSel   = $('#f_type').val();
        const vendorId  = $('#f_vendor').val();
        const empId     = $('#f_employee').val();
        const method    = $('#f_method').val();
        const branchId  = $('#f_branch').val();
        const from      = $('#f_from').val();
        const to        = $('#f_to').val();

        let type = typeSel;
        if (empId) {
            type = 'employee';
        } else if (!type || type === '') {
            if (vendorId || method) type = 'vendor';
        } else {
            if (type === 'employee' && !empId && (vendorId || method)) type = 'vendor';
        }

        return { type, vendor_id: vendorId, employee_id: empId, branch_id: branchId, method, from, to };
    }

    function loadTable(fallbackTried=false){
        const url = '?ajax=list&'+qs(currentFilters());
        if (dt) { dt.destroy(); $('#tbl tbody').empty(); }

        dt = $('#tbl').DataTable({
            ajax: {
                url,
                dataSrc: function(json){
                    try {
                        if (json && json.data) {
                            const sum_net_paid = json.data.reduce((a,r) => a + parseFloat(r.amount || 0), 0); 
                            const sum_adv_used = json.data.reduce((a,r) => a + parseFloat(r.advance_used || 0), 0); 
                            const sum_red_used = json.data.reduce((a,r) => a + parseFloat(r.redemption_points_used || 0), 0);
                            const sum_total_paid = json.data.reduce((a,r) => a + parseFloat(r.total_paid_amount || 0), 0);
                            
                            $('#ft_amount').text('₹ ' + sum_net_paid.toFixed(2));
                            $('#ft_advance_used').text('₹ ' + sum_adv_used.toFixed(2));
                            $('#ft_redemption_used').text('₹ ' + sum_red_used.toFixed(2));
                            $('#ft_total_amount').text('₹ ' + sum_total_paid.toFixed(2));
                            
                            json.data.forEach(row => {
                                // Calculate the final total amount for the print link (Total Bill Amount)
                                const finalTotal = parseFloat(row.amount || 0) + parseFloat(row.advance_used || 0) + parseFloat(row.redemption_points_used || 0);

                                // CRITICAL MODIFICATION: Added row.note (the specific GRN number) to the URL
                                row.print_url = 'print_voucher'
                                    + '?type='  + encodeURIComponent(row.pay_source || '')
                                    + '&voucher='+ encodeURIComponent(row.voucher_no || '')
                                    + '&invoice='+ encodeURIComponent(row.invoice_no || '')
                                    + '&adv_used=' + parseFloat(row.advance_used || 0).toFixed(2)  
                                    + '&red_used=' + parseFloat(row.redemption_points_used || 0).toFixed(2)
                                    + '&amount=' + parseFloat(row.amount || 0).toFixed(2) // Net Paid Amount
                                    + '&total_bill_amount=' + finalTotal.toFixed(2) // Send the calculated Total Bill Amount
                                    + '&grn_note=' + encodeURIComponent(row.note || '')
                                    + '&ref_number=' + encodeURIComponent(row.ref_number || ''); // <<-- KEY CHANGE: Pass the Reference Number
                            });
                            
                            return json.data;
                        }
                    } catch(e){ console.error("Data processing error: ", e); }
                    $('#ft_amount').text('₹ 0.00');
                    $('#ft_advance_used').text('₹ 0.00');
                    $('#ft_redemption_used').text('₹ 0.00');
                    $('#ft_total_amount').text('₹ 0.00');
                    if (!fallbackTried) {
                        $('#f_type').val('vendor');
                        loadTable(true);
                    }
                    return [];
                },
                error: function(xhr, textStatus, errorThrown){
                    console.error("AJAX Error: ", textStatus, errorThrown);
                    // Minimal fallback on error
                    $('#ft_amount').text('₹ 0.00');
                    $('#ft_advance_used').text('₹ 0.00');
                    $('#ft_redemption_used').text('₹ 0.00');
                    $('#ft_total_amount').text('₹ 0.00');
                    if (!fallbackTried) {
                        $('#f_type').val('vendor');
                        loadTable(true);
                    }
                }
            },
            columns: [
                { data: 'pay_source', render:d => d? d.charAt(0).toUpperCase()+d.slice(1) : '-' },
                { data: 'paid_at' },
                { data: 'party' },
                { data: 'account_number', defaultContent:'-' },
                { data: 'ifsc_code', defaultContent:'-' },
                { data: 'payment_mode', defaultContent:'-' },
                { data: 'ref_number', defaultContent:'-' },
                { data: 'total_paid_amount', className:'text-end', defaultContent:'0.00', render:d => parseFloat(d||0).toFixed(2) },
                { data: 'amount', className:'text-end', render:d => parseFloat(d||0).toFixed(2) }, 
                { data: 'advance_used', className:'text-end', defaultContent:'0.00', render:d => parseFloat(d||0).toFixed(2) },
                { data: 'redemption_points_used', className:'text-end', defaultContent:'0.00', render:d => parseFloat(d||0).toFixed(2) },
                { data: 'voucher_no', defaultContent:'-' },
                { data: 'invoice_no', defaultContent:'-' },
                { data: 'note', defaultContent:'' },
                { data: 'payment_by', defaultContent:'-' },
                { data: 'extra_branch', defaultContent:'-' },
                { data: 'print_url', orderable:false, searchable:false,
                    render: function(data, type, row){
                        if (!row?.voucher_no && !row?.invoice_no) return '<span class="text-muted">-</span>';
                        return '<a class="btn btn-sm btn-outline-primary" target="_blank" href="'+data+'">Print</a>';
                    }
                }
            ],
            order: [[1,'desc']],
            pageLength: 25,
            lengthMenu: [25,50,100,200],
            dom: '<"d-flex justify-content-between align-items-center mb-2"Bf>rtip',
            buttons: [
                { extend:'excel', text:'Export Excel', className:'btn btn-sm btn-success' },
                { extend:'print', text:'Print', className:'btn btn-sm btn-info' }
            ],
            scrollX: true,
            language: { emptyTable: 'No payments found' }
        });
    }

    function scheduleReload(){ clearTimeout(reloadTimer); reloadTimer = setTimeout(()=>loadTable(false), 120); }

    $('#f_employee').on('change', function(){
        if (this.value) $('#f_type').val('employee');
        scheduleReload();
    });
    $('#f_vendor, #f_method').on('change', function(){
        if (!$('#f_employee').val()) $('#f_type').val('vendor');
        scheduleReload();
    });
    $('#f_branch').on('change', scheduleReload);
    $('#f_type').on('change', scheduleReload);
    $('#f_from, #f_to').on('change', scheduleReload);

    $('#btn_search').on('click', function(){ loadTable(false); });
    $('#btn_reset').on('click', function(){
        $('#f_type').val('vendor');
        $('#f_vendor, #f_employee, #f_method, #f_branch').val('');
        const d = new Date(); const to = d.toISOString().slice(0,10); d.setDate(d.getDate()-365);
        const from = d.toISOString().slice(0,10);
        $('#f_from').val(from); $('#f_to').val(to);
        loadTable(false);
    });
    $('#btn_csv').on('click', function(){
        const url = '?ajax=export_csv&'+qs(currentFilters());
        window.location = url;
    });

    // Default range: last 365 days, default Type Vendor
    const d = new Date();
    const to = d.toISOString().slice(0,10);
    d.setDate(d.getDate()-365);
    const from = d.toISOString().slice(0,10);
    $('#f_from').val(from); $('#f_to').val(to);
    $('#f_type').val('vendor');

    loadTable(false);
});
</script>
</body>
</html>