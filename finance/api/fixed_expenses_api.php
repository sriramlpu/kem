<?php
// kmk/finance/api/fixed_expenses_api.php
declare(strict_types=1);

ob_start();
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
date_default_timezone_set('Asia/Kolkata');

// --- Load project DB helper (adjust if your functions.php is elsewhere)
$fn = dirname(__DIR__, 2) . '/functions.php';
if (!is_file($fn)) $fn = dirname(__DIR__) . '/functions.php';
require_once $fn;

if (!function_exists('exeSql')) {
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'functions.php not loaded (exeSql missing)']);
  exit;
}

// ---------- helpers ----------
function json_out($data, int $code=200): void {
  if (ob_get_length()) { ob_clean(); }
  http_response_code($code);
  echo json_encode($data);
  exit;
}
function fail(int $code, string $msg): void { json_out(['status'=>'error','message'=>$msg], $code); }
function ok(array $data=[]): void { json_out(['status'=>'success'] + $data, 200); }

/** accept Y-m-d or d-m-Y and normalize to Y-m-d */
function normalize_date(string $in): string {
  $in = trim($in);
  if ($in === '') return '';
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $in)) return $in;      // Y-m-d
  if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $in)) {                // d-m-Y
    [$d,$m,$y] = explode('-', $in);
    return sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
  }
  $ts = strtotime($in);
  return $ts ? date('Y-m-d', $ts) : '';
}

/** Next due date calculator */
function calculate_next_due_date(string $start_date, string $frequency, int $due_day): string {
  if ($due_day < 1 || $due_day > 31) return 'Invalid Day';
  try {
    $now = new DateTime('today');
    $interval = match ($frequency) {
      'Monthly'     => '1 month',
      'Quarterly'   => '3 months',
      'Half-Yearly' => '6 months',
      'Annually'    => '1 year',
      default       => null,
    };
    if ($interval === null) return 'N/A';
    $current = new DateTime($start_date);
    $target_day = min($due_day, (int)$current->format('t'));
    $current->setDate((int)$current->format('Y'), (int)$current->format('n'), $target_day);
    while ($current < $now) {
      $current->modify('+'.$interval);
      $target_day = min($due_day, (int)$current->format('t'));
      $current->setDate((int)$current->format('Y'), (int)$current->format('n'), $target_day);
    }
    return $current->format('Y-m-d');
  } catch (Throwable $e) {
    error_log('calculate_next_due_date: '.$e->getMessage());
    return 'Error';
  }
}

// ---------- ensure/upgrade table ----------
// Creates the table if missing, with remaining_balance as a GENERATED column.
// If the table exists, adds any missing payment columns.
try { $has = exeSql("SHOW TABLES LIKE 'fixed_expenses'"); } catch (Throwable $t) { fail(500, 'DB error: '.$t->getMessage()); }
if (!$has) {
  exeSql("CREATE TABLE `fixed_expenses` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `expense_type` enum('current_bill','rent','water_bill','pf','wifi_bill','other') NOT NULL,
    `amount` decimal(12,2) NOT NULL,
    `balance_paid` decimal(12,2) DEFAULT 0.00,
    `method` enum('online','cash','bank') DEFAULT NULL,
    `voucher_no` varchar(64) DEFAULT NULL,
    `invoice_no` varchar(64) DEFAULT NULL,
    `payment_by` varchar(150) DEFAULT NULL,
    `account_no` varchar(30) DEFAULT NULL,
    `ifsc_code` varchar(15) DEFAULT NULL,
    `remaining_balance` decimal(12,2) GENERATED ALWAYS AS (`amount` - `balance_paid`) STORED,
    `start_date` date NOT NULL,
    `frequency` enum('Monthly','Quarterly','Half-Yearly','Annually') NOT NULL,
    `due_day` tinyint unsigned NOT NULL COMMENT 'The day of the month the expense is due (1-31)',
    `payment_proof_path` varchar(255) DEFAULT NULL,
    `notes` varchar(500) DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_fe_type` (`expense_type`),
    UNIQUE KEY `uq_fixed_voucher_no` (`voucher_no`),
    UNIQUE KEY `uq_fixed_invoice_no` (`invoice_no`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} else {
  // add missing columns if your DB is older
  $cols = exeSql("SHOW COLUMNS FROM fixed_expenses");
  $have = array_column($cols, 'Field');
  $alters = [];
  $maybe = [
    'method'     => "ADD COLUMN `method` enum('online','cash','bank') DEFAULT NULL",
    'voucher_no' => "ADD COLUMN `voucher_no` varchar(64) DEFAULT NULL",
    'invoice_no' => "ADD COLUMN `invoice_no` varchar(64) DEFAULT NULL",
    'payment_by' => "ADD COLUMN `payment_by` varchar(150) DEFAULT NULL",
    'account_no' => "ADD COLUMN `account_no` varchar(30) DEFAULT NULL",
    'ifsc_code'  => "ADD COLUMN `ifsc_code` varchar(15) DEFAULT NULL",
    'notes'      => "ADD COLUMN `notes` varchar(500) DEFAULT NULL",
    'created_at' => "ADD COLUMN `created_at` datetime NOT NULL DEFAULT current_timestamp()",
  ];
  foreach ($maybe as $col => $ddl) {
    if (!in_array($col, $have, true)) $alters[] = $ddl;
  }
  if ($alters) exeSql("ALTER TABLE fixed_expenses ".implode(', ', $alters));

  // ensure remaining_balance is GENERATED ALWAYS
  // (If it already is generated, MySQL will throw; wrap in try/catch and ignore)
  try {
    exeSql("ALTER TABLE fixed_expenses
      MODIFY `remaining_balance` decimal(12,2) GENERATED ALWAYS AS (`amount` - `balance_paid`) STORED");
  } catch (Throwable $e) {
    // ignore if it's already generated
  }
}

// ---------- routing ----------
$act = $_GET['action'] ?? $_POST['action'] ?? 'list_all';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($act, ['create','update','delete'], true) && $method !== 'POST') {
  fail(405, 'Use POST for this action');
}

switch ($act) {
  // LIST
  case 'list_all': {
    $type  = trim((string)($_GET['type']  ?? ''));
    $notes = trim((string)($_GET['notes'] ?? ''));

    $where = ["1=1"];
    if ($type !== '' && $type !== 'all') {
      $t = str_replace("'", "''", $type);
      $where[] = "expense_type = '{$t}'";
    }
    if ($notes !== '') {
      $n = str_replace("'", "''", $notes);
      $where[] = "notes LIKE '%{$n}%'";
    }
    $where_sql = 'WHERE '.implode(' AND ', $where);

    $rows = exeSql("
      SELECT id, expense_type, amount, start_date, frequency, due_day, notes,
             IFNULL(balance_paid,0) AS balance_paid,
             /* remaining_balance is generated by DB */
             remaining_balance
      FROM fixed_expenses
      {$where_sql}
      ORDER BY id ASC
    ") ?: [];

    $out = [];
    $sn  = 1;
    $t_amount = 0.0; $t_paid = 0.0; $t_rem = 0.0;

    foreach ($rows as $r) {
      $amount    = (float)$r['amount'];
      $paid      = (float)$r['balance_paid'];
      $remaining = (float)$r['remaining_balance'];
      $t_amount += $amount; $t_paid += $paid; $t_rem += $remaining;

      $start  = (string)$r['start_date'];
      $freq   = (string)$r['frequency'];
      $day    = (int)$r['due_day'];
      $next   = ($start && $freq && $day>0) ? calculate_next_due_date($start, $freq, $day) : 'N/A';
      $status = ($remaining <= 0.00001) ? 'Paid (Master)' : 'Pending (Master)';

      $out[] = [
        'id'                 => (int)$r['id'],
        'sno'                => $sn++,
        'expense_type'       => (string)$r['expense_type'],
        'amount'             => $amount,
        'start_date'         => $start,
        'frequency'          => $freq,
        'due_day'            => $day,
        'notes'              => (string)($r['notes'] ?? ''),
        'balance_paid'       => $paid,
        'remaining_balance'  => $remaining,
        'next_due_date'      => $next,
        'status'             => $status,
      ];
    }

    json_out([
      'data'   => $out,
      'totals' => [
        'total_amount'    => round($t_amount, 2),
        'total_paid'      => round($t_paid, 2),
        'total_remaining' => round($t_rem, 2),
      ]
    ]);
    break;
  }

  // GET one
  case 'get': {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) fail(422, 'id is required');
    $row = exeSql("SELECT * FROM fixed_expenses WHERE id={$id} LIMIT 1");
    if (!$row) fail(404, 'Record not found');
    ok(['expense' => $row[0]]);
    break;
  }

  // CREATE  (NOTE: no remaining_balance in INSERT)
  case 'create': {
    $expense_type = trim((string)($_POST['expense_type'] ?? ''));
    $amount       = (float)($_POST['amount'] ?? 0);
    $start_date   = normalize_date((string)($_POST['start_date'] ?? ''));
    $frequency    = trim((string)($_POST['frequency'] ?? ''));
    $due_day      = (int)($_POST['due_day'] ?? 0);
    $notes        = trim((string)($_POST['notes'] ?? ''));
    $paid         = (float)($_POST['balance_paid'] ?? 0);

    // optional payment fields
    $method      = $_POST['method']      ?? null;
    $voucher_no  = $_POST['voucher_no']  ?? null;
    $invoice_no  = $_POST['invoice_no']  ?? null;
    $payment_by  = $_POST['payment_by']  ?? null;
    $account_no  = $_POST['account_no']  ?? null;
    $ifsc_code   = $_POST['ifsc_code']   ?? null;

    if ($expense_type === '') fail(422, 'expense_type is required');
    if ($start_date === '')   fail(422, 'start_date must be a valid date');
    if (!in_array($frequency, ['Monthly','Quarterly','Half-Yearly','Annually'], true)) fail(422, 'Invalid frequency');
    if ($due_day < 1 || $due_day > 31) fail(422, 'due_day must be 1..31');
    if ($amount <= 0) fail(422, 'amount must be > 0');
    if ($paid < 0) fail(422, 'balance_paid cannot be negative');
    if ($paid > $amount) fail(422, 'balance_paid cannot exceed amount');

    $esc = fn($s) => "'".str_replace("'", "''", (string)$s)."'";
    $t  = str_replace("'", "''", $expense_type);
    $fq = str_replace("'", "''", $frequency);
    $nt = ($notes === '') ? 'NULL' : $esc($notes);
    $method_sql     = ($method     === null || $method     === '') ? 'NULL' : $esc($method);
    $voucher_sql    = ($voucher_no === null || $voucher_no === '') ? 'NULL' : $esc($voucher_no);
    $invoice_sql    = ($invoice_no === null || $invoice_no === '') ? 'NULL' : $esc($invoice_no);
    $payment_by_sql = ($payment_by === null || $payment_by === '') ? 'NULL' : $esc($payment_by);
    $account_sql    = ($account_no === null || $account_no === '') ? 'NULL' : $esc($account_no);
    $ifsc_sql       = ($ifsc_code === null || $ifsc_code === '') ? 'NULL' : $esc($ifsc_code);

    $ok = exeSql("
      INSERT INTO fixed_expenses
        (expense_type, amount, start_date, frequency, due_day, notes,
         balance_paid, method, voucher_no, invoice_no, payment_by, account_no, ifsc_code)
      VALUES
        ('{$t}', {$amount}, '{$start_date}', '{$fq}', {$due_day}, {$nt},
         {$paid}, {$method_sql}, {$voucher_sql}, {$invoice_sql}, {$payment_by_sql}, {$account_sql}, {$ifsc_sql})
    ");
    if ($ok === false) fail(500, 'Create failed');

    $newId = exeSql("SELECT LAST_INSERT_ID() AS lid");
    ok(['id' => (int)($newId[0]['lid'] ?? 0)]);
    break;
  }

  // UPDATE (NOTE: do NOT set remaining_balance)
  case 'update': {
    $id           = (int)($_POST['id'] ?? 0);
    $expense_type = trim((string)($_POST['expense_type'] ?? ''));
    $amount       = (float)($_POST['amount'] ?? 0);
    $start_date   = normalize_date((string)($_POST['start_date'] ?? ''));
    $frequency    = trim((string)($_POST['frequency'] ?? ''));
    $due_day      = (int)($_POST['due_day'] ?? 0);
    $notes        = trim((string)($_POST['notes'] ?? ''));
    $paid         = (float)($_POST['balance_paid'] ?? 0);

    $method      = $_POST['method']      ?? null;
    $voucher_no  = $_POST['voucher_no']  ?? null;
    $invoice_no  = $_POST['invoice_no']  ?? null;
    $payment_by  = $_POST['payment_by']  ?? null;
    $account_no  = $_POST['account_no']  ?? null;
    $ifsc_code   = $_POST['ifsc_code']   ?? null;

    if ($id <= 0) fail(422, 'id is required');
    if ($expense_type === '') fail(422, 'expense_type is required');
    if ($start_date === '')   fail(422, 'start_date must be a valid date');
    if (!in_array($frequency, ['Monthly','Quarterly','Half-Yearly','Annually'], true)) fail(422, 'Invalid frequency');
    if ($due_day < 1 || $due_day > 31) fail(422, 'due_day must be 1..31');
    if ($amount <= 0) fail(422, 'amount must be > 0');
    if ($paid < 0) fail(422, 'balance_paid cannot be negative');
    if ($paid > $amount) fail(422, 'balance_paid cannot exceed amount');

    $esc = fn($s) => "'".str_replace("'", "''", (string)$s)."'";
    $t  = str_replace("'", "''", $expense_type);
    $fq = str_replace("'", "''", $frequency);
    $nt = ($notes === '') ? 'NULL' : $esc($notes);
    $method_sql     = ($method     === null || $method     === '') ? 'NULL' : $esc($method);
    $voucher_sql    = ($voucher_no === null || $voucher_no === '') ? 'NULL' : $esc($voucher_no);
    $invoice_sql    = ($invoice_no === null || $invoice_no === '') ? 'NULL' : $esc($invoice_no);
    $payment_by_sql = ($payment_by === null || $payment_by === '') ? 'NULL' : $esc($payment_by);
    $account_sql    = ($account_no === null || $account_no === '') ? 'NULL' : $esc($account_no);
    $ifsc_sql       = ($ifsc_code === null || $ifsc_code === '') ? 'NULL' : $esc($ifsc_code);

    $ok = exeSql("
      UPDATE fixed_expenses
      SET expense_type='{$t}', amount={$amount}, start_date='{$start_date}', frequency='{$fq}',
          due_day={$due_day}, notes={$nt}, balance_paid={$paid},
          method={$method_sql}, voucher_no={$voucher_sql}, invoice_no={$invoice_sql},
          payment_by={$payment_by_sql}, account_no={$account_sql}, ifsc_code={$ifsc_sql}
      WHERE id={$id}
      LIMIT 1
    ");
    if ($ok === false) fail(500, 'Update failed');

    ok();
    break;
  }

  // DELETE
  case 'delete': {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) fail(422, 'id is required');
    $ok = exeSql("DELETE FROM fixed_expenses WHERE id={$id} LIMIT 1");
    if ($ok === false) fail(500, 'Delete failed');
    ok();
    break;
  }

  default:
    fail(400, 'Unknown action');
}
