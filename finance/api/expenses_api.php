<?php
// kmk/finance/api/expenses_api.php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require_once dirname(__DIR__, 2) . '/functions.php';

if (!function_exists('exeSql')) {
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'functions.php not loaded (exeSql missing)']);
  exit;
}

date_default_timezone_set('Asia/Kolkata');

function json_out($data, int $code=200): void {
  http_response_code($code);
  echo json_encode($data);
  exit;
}
function fail(int $code, string $msg): void { json_out(['status'=>'error','message'=>$msg], $code); }
function ok(array $data=[]): void { json_out(['status'=>'success'] + $data, 200); }

// (Safe) bootstrap only if table missing — matches your dump
$has = exeSql("SHOW TABLES LIKE 'expenses'");
if (!$has) {
  exeSql("CREATE TABLE `expenses` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `purpose` varchar(255) NOT NULL,
    `amount` int(11) NOT NULL,
    `balance_paid` int(11) DEFAULT 0,
    `method` enum('online','cash','bank') DEFAULT NULL,
    `voucher_no` varchar(64) DEFAULT NULL,
    `invoice_no` varchar(64) DEFAULT NULL,
    `paid_at` datetime NOT NULL,
    `remark` varchar(500) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
    `payment_by` varchar(150) DEFAULT NULL,
    `account_no` varchar(30) NOT NULL,
    `ifsc_code` varchar(15) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_expenses_voucher_no` (`voucher_no`),
    UNIQUE KEY `uq_expenses_invoice_no` (`invoice_no`),
    KEY `idx_expenses_paid_at` (`paid_at`),
    KEY `idx_expenses_method` (`method`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

$act = $_GET['action'] ?? $_POST['action'] ?? 'list_all';

switch ($act) {

  // ---------- LIST with filters (keeps your behavior) ----------
  case 'list_all': {
    $purpose  = trim((string)($_GET['purpose']  ?? ''));
    $remark   = trim((string)($_GET['remark']   ?? ''));
    $fromdate = trim((string)($_GET['fromdate'] ?? '')); // yyyy-mm-dd
    $todate   = trim((string)($_GET['todate']   ?? '')); // yyyy-mm-dd

    $from_ok = ($fromdate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromdate));
    $to_ok   = ($todate   !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $todate));
    if ($from_ok && $to_ok && strcmp($fromdate, $todate) > 0) {
      [$fromdate, $todate] = [$todate, $fromdate];
    }

    $where = [];
    if ($purpose !== '') { $q = str_replace("'", "''", $purpose); $where[] = "purpose LIKE '%{$q}%'"; }
    if ($remark  !== '') { $r = str_replace("'", "''", $remark);  $where[] = "remark  LIKE '%{$r}%'"; }
    if ($from_ok && $to_ok) {
      $where[] = "DATE(created_at) BETWEEN '{$fromdate}' AND '{$todate}'";
    } elseif ($from_ok) {
      $where[] = "DATE(created_at) >= '{$fromdate}'";
    } elseif ($to_ok) {
      $where[] = "DATE(created_at) <= '{$todate}'";
    }
    $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $rows = exeSql("
      SELECT
        id,
        purpose,
        amount,
        IFNULL(balance_paid,0) AS balance_paid,
        GREATEST(amount - IFNULL(balance_paid,0), 0) AS remaining_balance,
        remark,
        DATE_FORMAT(created_at, '%Y-%m-%d') AS created_date
      FROM expenses
      {$where_sql}
      ORDER BY created_at DESC, id DESC
    ") ?: [];

    $totals = exeSql("
      SELECT
        IFNULL(SUM(amount),0)                                      AS total_amount,
        IFNULL(SUM(balance_paid),0)                                AS total_paid,
        IFNULL(SUM(GREATEST(amount - IFNULL(balance_paid,0),0)),0) AS total_remaining
      FROM expenses
      {$where_sql}
    ");
    $t = $totals ? $totals[0] : ['total_amount'=>0,'total_paid'=>0,'total_remaining'=>0];

    $out = [];
    $sn  = 1;
    foreach ($rows as $r) {
      $amount    = (int)$r['amount'];
      $paid      = (int)$r['balance_paid'];
      $remaining = (int)$r['remaining_balance'];
      $status    = ($remaining === 0 ? 'Complete' : 'Pending');

      $out[] = [
        'id'                => (int)$r['id'],
        'sno'               => $sn++,
        'created_date'      => (string)($r['created_date'] ?? ''),
        'purpose'           => (string)$r['purpose'],
        'remark'            => (string)($r['remark'] ?? ''),
        'amount'            => $amount,
        'total_paid'        => $paid,
        'remaining_balance' => $remaining,
        'status'            => $status,
      ];
    }

    json_out([
      'data'   => $out,
      'totals' => [
        'total_amount'    => (int)($t['total_amount'] ?? 0),
        'total_paid'      => (int)($t['total_paid'] ?? 0),
        'total_remaining' => (int)($t['total_remaining'] ?? 0),
      ]
    ]);
    break;
  }

  // ---------- GET one ----------
  case 'get': {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) fail(422, 'id is required');

    $row = exeSql("
      SELECT id, purpose, amount, IFNULL(balance_paid,0) AS balance_paid, remark,
             DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') AS created_at,
             DATE_FORMAT(updated_at, '%Y-%m-%d %H:%i:%s') AS updated_at
      FROM expenses
      WHERE id={$id}
      LIMIT 1
    ");
    if (!$row) fail(404, 'Expense not found');

    ok(['expense'=>$row[0]]);
    break;
  }

  // ---------- UPDATE ----------
  case 'update': {
    $id      = (int)($_POST['id'] ?? 0);
    $purpose = trim((string)($_POST['purpose'] ?? ''));
    $amount  = (int)($_POST['amount'] ?? 0);
    $paid    = (int)($_POST['balance_paid'] ?? 0);
    $remark  = trim((string)($_POST['remark'] ?? ''));

    if ($id <= 0)        fail(422, 'id is required');
    if ($purpose === '') fail(422, 'Purpose is required');
    if ($amount <= 0)    fail(422, 'Amount must be greater than zero');
    if ($paid < 0)       fail(422, 'Total paid cannot be negative');
    if ($paid > $amount) fail(422, 'Total paid cannot exceed amount');

    $pS = str_replace("'", "''", $purpose);
    $rS = ($remark === '') ? 'NULL' : ("'".str_replace("'", "''", $remark)."'");

    $ok = exeSql("
      UPDATE expenses
      SET purpose='{$pS}', amount={$amount}, balance_paid={$paid},
          remark={$rS}
      WHERE id={$id}
      LIMIT 1
    ");
    if ($ok === false) fail(500, 'Update failed');

    ok();
    break;
  }

  // ---------- DELETE ----------
  case 'delete': {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) fail(422, 'id is required');

    $ok = exeSql("DELETE FROM expenses WHERE id={$id} LIMIT 1");
    if ($ok === false) fail(500, 'Delete failed');

    ok();
    break;
  }

  default:
    fail(400, 'Unknown action');
}
