<?php
/**
 * Payments View — Vendor / Employee / Expense / Event (from event_items)
 * - Note column shows: Vendor→GRN (from goods_receipts.grn_number), Employee→Role, Event→Venue, Expense→Purpose
 * - If that field is missing/empty, show empty (no fallback)
 * - Defaults: Vendor + last 365 days
 * - Auto-derive Type (employee selection overrides)
 */

declare(strict_types=1);

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
$vendors   = t_exists('vendors')   ? (exeSql("SELECT vendor_id, vendor_name FROM vendors ORDER BY vendor_name") ?: []) : [];
$employees = t_exists('employees') ? (exeSql("SELECT id, employee_name FROM employees ORDER BY employee_name") ?: []) : [];
$branches  = t_exists('branches')  ? (exeSql("SELECT branch_id, branch_name FROM branches ORDER BY branch_name") ?: []) : [];

/* ---------- query builder ---------- */
function build_union_sql(array $f): string {
  $type        = $f['type'] ?? '';
  $vendor_id   = (int)($f['vendor_id'] ?? 0);
  $employee_id = (int)($f['employee_id'] ?? 0);
  $branch_id   = (int)($f['branch_id'] ?? 0);
  $method      = trim($f['method'] ?? '');
  $fromDt      = $f['fromDt'] ?? null;
  $toDt        = $f['toDt'] ?? null;

  $parts = [];

  /* ---- Vendor payments ---- */
  if (($type==='' || $type==='vendor') && t_exists('vendor_grn_payments')) {
    $VG = t_cols('vendor_grn_payments');
    $w = ["1=1"];
    if ($vendor_id) $w[] = "p.vendor_id = $vendor_id";
    if ($method !== '' && isset($VG['method'])) $w[] = "p.method = '".addslashes($method)."'";
    if ($fromDt && isset($VG['paid_at'])) $w[] = "p.paid_at >= '$fromDt'";
    if ($toDt   && isset($VG['paid_at'])) $w[] = "p.paid_at <= '$toDt'";
    if ($branch_id && isset($VG['branch_id'])) $w[] = "p.branch_id = $branch_id";

    $sel_invoice     = isset($VG['invoice_no']) ? "p.invoice_no" : "NULL";
    $sel_voucher     = isset($VG['voucher_no']) ? "p.voucher_no" : "NULL";
    $sel_method      = isset($VG['method']) ? "p.method" : "NULL";
    $sel_payment_by  = isset($VG['payment_by']) ? "p.payment_by" : (isset($VG['payment_pu']) ? "p.payment_pu" : "NULL");

    $joinV     = t_exists('vendors') ? "LEFT JOIN vendors v ON v.vendor_id = p.vendor_id" : "";
    $joinB     = (t_exists('branches') && isset($VG['branch_id'])) ? "LEFT JOIN branches b ON b.branch_id = p.branch_id" : "";
    $sel_branch= (t_exists('branches') && isset($VG['branch_id'])) ? "b.branch_name" : "NULL";

    // ✅ GRN number comes from goods_receipts.grn_number via p.grn_id
    $joinGRN = "";
    $sel_note_vendor = "''";
    if (isset($VG['grn_id']) && t_exists('goods_receipts')) {
      $GRN = t_cols('goods_receipts');
      if (!empty($GRN['grn_number'])) {
        $joinGRN = "LEFT JOIN goods_receipts g ON g.grn_id = p.grn_id";
        $sel_note_vendor = "CASE WHEN g.grn_number IS NOT NULL AND g.grn_number<>'' THEN CONCAT('GRN: ', g.grn_number) ELSE '' END";
      }
    }

    $parts[] = "
      SELECT
        'vendor' AS pay_source,
        p.paid_at AS paid_at,
        ".(t_exists('vendors') ? "v.vendor_name" : "CONCAT('Vendor #', p.vendor_id)")." AS party,
        $sel_method AS method,
        p.amount AS amount,
        $sel_voucher AS voucher_no,
        $sel_invoice AS invoice_no,
        $sel_note_vendor AS note,
        $sel_payment_by AS payment_by,
        $sel_branch AS extra_branch
      FROM vendor_grn_payments p
      $joinV
      $joinB
      $joinGRN
      WHERE ".implode(' AND ', $w)."
    ";
  }

  /* ---- Employee salary payments ---- */
  if (($type==='' || $type==='employee') && t_exists('employee_salary_payments')) {
    $ES = t_cols('employee_salary_payments');
    $w = ["1=1"];
    if ($employee_id) $w[] = "p.employee_id = $employee_id";
    if ($fromDt && isset($ES['paid_at'])) $w[] = "p.paid_at >= '$fromDt'";
    if ($toDt   && isset($ES['paid_at'])) $w[] = "p.paid_at <= '$toDt'";

    $joinE = t_exists('employees') ? "LEFT JOIN employees e ON e.id = p.employee_id" : "";

    // Role/designation for note (no fallback)
    $Ecols = t_exists('employees') ? t_cols('employees') : [];
    $roleExpr = "NULL";
    foreach (['role','designation','position','job_title','title'] as $rc) {
      if (!empty($Ecols[$rc])) { $roleExpr = "e.$rc"; break; }
    }
    $note_employee = "CASE WHEN $roleExpr IS NOT NULL AND $roleExpr<>'' THEN $roleExpr ELSE '' END";

    // Branch handling
    $joinB = "";
    $sel_branch = "NULL";
    if (t_exists('branches')) {
      if (isset($ES['branch_id'])) {
        $joinB = "LEFT JOIN branches b ON b.branch_id = p.branch_id";
        $sel_branch = "b.branch_name";
        if ($branch_id) $w[] = "p.branch_id = $branch_id";
      } else {
        if (!empty($Ecols['branch_id'])) {
          $joinB = "LEFT JOIN branches b ON b.branch_id = e.branch_id";
          $sel_branch = "b.branch_name";
          if ($branch_id) $w[] = "e.branch_id = $branch_id";
        }
      }
    }

    $sel_payment_by = isset($ES['payment_by']) ? "p.payment_by" : (isset($ES['payment_pu']) ? "p.payment_pu" : "NULL");
    $sel_invoice    = isset($ES['invoice_no']) ? "p.invoice_no" : "NULL";
    $sel_voucher    = isset($ES['voucher_no']) ? "p.voucher_no" : "NULL";

    $parts[] = "
      SELECT
        'employee' AS pay_source,
        p.paid_at AS paid_at,
        ".(t_exists('employees') ? "e.employee_name" : "CONCAT('Emp #', p.employee_id)")." AS party,
        NULL AS method,
        p.amount AS amount,
        $sel_voucher AS voucher_no,
        $sel_invoice AS invoice_no,
        $note_employee AS note,
        $sel_payment_by AS payment_by,
        $sel_branch AS extra_branch
      FROM employee_salary_payments p
      $joinE
      $joinB
      WHERE ".implode(' AND ', $w)."
    ";
  }

  /* ---- Expenses ---- */
  if (($type==='' || $type==='expense') && t_exists('expenses')) {
    $EX = t_cols('expenses');
    $w = ["1=1"];

    $sel_date = isset($EX['paid_at']) ? "e.paid_at"
              : (isset($EX['expense_date']) ? "CONCAT(e.expense_date,' 00:00:00')" : "NULL");
    if ($fromDt && $sel_date !== 'NULL') $w[] = "$sel_date >= '$fromDt'";
    if ($toDt   && $sel_date !== 'NULL') $w[] = "$sel_date <= '$toDt'";

    $sel_method    = isset($EX['method']) ? "e.method" : (isset($EX['mode']) ? "e.mode" : "NULL");
    if ($method !== '' && $sel_method!=='NULL') $w[] = "$sel_method = '".addslashes($method)."'";

    if ($branch_id && isset($EX['branch_id'])) $w[] = "e.branch_id = $branch_id";

    // Note = purpose only if exists, else empty
    $sel_note       = isset($EX['purpose']) ? "e.purpose" : "''";
    $sel_payment_by = isset($EX['payment_by']) ? "e.payment_by" : (isset($EX['payment_pu']) ? "e.payment_pu" : "NULL");
    $sel_invoice    = isset($EX['invoice_no']) ? "e.invoice_no" : "NULL";
    $sel_voucher    = isset($EX['voucher_no']) ? "e.voucher_no" : "NULL";
    $sel_party      = isset($EX['purpose']) ? "e.purpose" : "IFNULL(e.description,'Expense')";
    $joinB          = (t_exists('branches') && isset($EX['branch_id'])) ? "LEFT JOIN branches b ON b.branch_id = e.branch_id" : "";
    $sel_branch     = (t_exists('branches') && isset($EX['branch_id'])) ? "b.branch_name" : "NULL";

    $parts[] = "
      SELECT
        'expense' AS pay_source,
        $sel_date AS paid_at,
        $sel_party AS party,
        $sel_method AS method,
        e.amount AS amount,
        $sel_voucher AS voucher_no,
        $sel_invoice AS invoice_no,
        $sel_note AS note,
        $sel_payment_by AS payment_by,
        $sel_branch AS extra_branch
      FROM expenses e
      $joinB
      WHERE ".implode(' AND ', $w)."
    ";
  }

  /* ---- Events (from event_items) ---- */
  if (($type==='' || $type==='event') && t_exists('event_items')) {
    $EI = t_cols('event_items');
    $EV_exists = t_exists('events');
    $EV_cols   = $EV_exists ? t_cols('events') : [];

    $w = ["1=1"];
    if ($fromDt && isset($EI['created_at'])) $w[] = "ei.created_at >= '$fromDt'";
    if ($toDt   && isset($EI['created_at'])) $w[] = "ei.created_at <= '$toDt'";

    $sel_amount = isset($EI['amount_received']) ? "ei.amount_received" : "0";
    $w[] = "$sel_amount > 0";

    $joinE = $EV_exists ? "LEFT JOIN events ev ON ev.event_id = ei.event_id" : "";
    $joinB = "";
    $sel_branch = "NULL";
    if (t_exists('branches')) {
      if (isset($EI['branch_id'])) {
        $joinB = "LEFT JOIN branches b ON b.branch_id = ei.branch_id";
        $sel_branch = "b.branch_name";
        if ($branch_id) $w[] = "ei.branch_id = $branch_id";
      } elseif ($EV_exists && isset($EV_cols['branch_id'])) {
        $joinB = "LEFT JOIN branches b ON b.branch_id = ev.branch_id";
        $sel_branch = "b.branch_name";
        if ($branch_id) $w[] = "ev.branch_id = $branch_id";
      }
    }

    $sel_paid_at    = isset($EI['created_at']) ? "ei.created_at" : "NULL";
    $sel_voucher    = isset($EI['voucher_no']) ? "ei.voucher_no" : "NULL";
    $sel_invoice    = isset($EI['invoice_no']) ? "ei.invoice_no" : "NULL";
    $sel_payment_by = isset($EI['payment_by']) ? "ei.payment_by" : "NULL";

    // Venue/location for note (no fallback)
    $venueExpr = "NULL";
    foreach (['venue','location','place','address'] as $vc) {
      if ($EV_exists && !empty($EV_cols[$vc])) { $venueExpr = "ev.$vc"; break; }
    }
    $sel_note_event = "CASE WHEN $venueExpr IS NOT NULL AND $venueExpr<>'' THEN $venueExpr ELSE '' END";

    $party = $EV_exists ? "ev.event_name" : "CONCAT('Event #', ei.event_id)";

    $parts[] = "
      SELECT
        'event' AS pay_source,
        $sel_paid_at AS paid_at,
        $party AS party,
        NULL AS method,
        $sel_amount AS amount,
        $sel_voucher AS voucher_no,
        $sel_invoice AS invoice_no,
        $sel_note_event AS note,
        $sel_payment_by AS payment_by,
        $sel_branch AS extra_branch
      FROM event_items ei
      $joinE
      $joinB
      WHERE ".implode(' AND ', $w)."
    ";
  }

  if (!$parts) return '';
  return implode(" UNION ALL ", $parts) . " ORDER BY paid_at DESC LIMIT 1000";
}

/* ============================= AJAX ============================= */
if (isset($_GET['ajax'])) {
  $act         = $_GET['ajax'];
  $type        = trim($_GET['type'] ?? '');
  $vendor_id   = (int)($_GET['vendor_id'] ?? 0);
  $employee_id = (int)($_GET['employee_id'] ?? 0);
  $branch_id   = (int)($_GET['branch_id'] ?? 0);
  $method      = trim($_GET['method'] ?? '');
  $from        = trim($_GET['from'] ?? '');
  $to          = trim($_GET['to'] ?? '');

  // Derive type server-side too
  if ($employee_id) {
    $type = 'employee';
  } else if ($type === '' && ($vendor_id || $method)) {
    $type = 'vendor';
  }

  $fromDt = ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)) ? "$from 00:00:00" : null;
  $toDt   = ($to   && preg_match('/^\d{4}-\d{2}-\d{2}$/',$to))   ? "$to 23:59:59"   : null;

  $filters = compact('type','vendor_id','employee_id','branch_id','method','fromDt','toDt');

  try {
    $sql  = build_union_sql($filters);
    $rows = $sql ? (exeSql($sql) ?: []) : [];

    if ($act === 'list') {
      while (ob_get_level()) { @ob_end_clean(); }
      header('Content-Type: application/json; charset=UTF-8');
      $sum = 0.0; foreach ($rows as $r){ $sum += (float)($r['amount'] ?? 0); }
      echo json_encode([
        'data'   => $rows,
        'totals' => ['count'=>count($rows), 'amount'=>$sum, 'amount_fmt'=>'₹ '.nf($sum)]
      ]);
      exit;
    }

    if ($act === 'export_csv') {
      while (ob_get_level()) { @ob_end_clean(); }
      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename=payments_view_'.date('Ymd_His').'.csv');
      $out = fopen('php://output', 'w');
      fputcsv($out, [
        'Type','Paid At','Party/Purpose','Method','Amount',
        'Voucher No','Invoice No','Notes','Payment By','Branch'
      ]);
      foreach ($rows as $r){
        fputcsv($out, [
          $r['pay_source'] ?? '',
          $r['paid_at'] ?? '',
          $r['party'] ?? '',
          $r['method'] ?? '',
          $r['amount'] ?? 0,
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
    echo json_encode(['data'=>[], 'totals'=>['count'=>0,'amount'=>0,'amount_fmt'=>'₹ 0.00']]);
    exit;

  } catch (Throwable $e) {
    while (ob_get_level()) { @ob_end_clean(); }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['data'=>[], 'totals'=>['count'=>0,'amount'=>0,'amount_fmt'=>'₹ 0.00']]);
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
  .container-max{max-width:1200px}
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
          <button id="btn_csv" class="btn btn-sm btn成功">Export CSV</button>
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
            <th>Method</th>
            <th class="text-end">Amount</th>
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
            <th colspan="4" class="text-end">Total:</th>
            <th class="text-end" id="ft_amount">₹ 0.00</th>
            <th colspan="6"></th>
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
              $('#ft_amount').text(json?.totals?.amount_fmt || '₹ 0.00');
              return json.data;
            }
          } catch(e){}
          $('#ft_amount').text('₹ 0.00');
          return [];
        },
        error: function(){
          if (!fallbackTried) {
            $('#f_type').val('vendor');
            loadTable(true);
          } else {
            $('#ft_amount').text('₹ 0.00');
          }
        }
      },
      columns: [
        { data: 'pay_source', render:d => d? d.charAt(0).toUpperCase()+d.slice(1) : '-' },
        { data: 'paid_at' },
        { data: 'party' },
        { data: 'method', defaultContent:'-' },
        { data: 'amount', className:'text-end', render:d => parseFloat(d||0).toFixed(2) },
        { data: 'voucher_no', defaultContent:'-' },
        { data: 'invoice_no', defaultContent:'-' },
        { data: 'note', defaultContent:'' }, // empty when missing
        { data: 'payment_by', defaultContent:'-' },
        { data: 'extra_branch', defaultContent:'-' },
        { data: null, orderable:false, searchable:false,
          render: function(data, type, row){
            const src     = row?.pay_source || '';
            const voucher = row?.voucher_no || '';
            const invoice = row?.invoice_no || '';
            if (!voucher && !invoice) return '<span class="text-muted">-</span>';
            const href = 'print_voucher'
              + '?type='   + encodeURIComponent(src)
              + '&voucher='+ encodeURIComponent(voucher || '')
              + '&invoice='+ encodeURIComponent(invoice || '');
            return '<a class="btn btn-sm btn-outline-primary" target="_blank" href="'+href+'">Print</a>';
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
