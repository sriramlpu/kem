<?php
// kmk/finance/api/employee_api.php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// functions.php is two levels up from /finance/api/
require_once dirname(__DIR__, 2) . '/functions.php';

if (!function_exists('exeSql')) {
 http_response_code(500);
 echo json_encode(['data'=>[], 'status'=>'error', 'message'=>'functions.php not loaded (exeSql missing)']);
 exit;
}

date_default_timezone_set('Asia/Kolkata');

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

function jexit($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }
function fail($code,$msg){ http_response_code($code); jexit(['data'=>[], 'status'=>'error','message'=>$msg]); }
function rupee($n){ return '₹ ' . number_format((float)$n, 2, '.', ','); }
function clamp0(float $x): float { return $x > 0 ? $x : 0.0; }

/* -------------------------------------------------------
 BOOTSTRAP / MIGRATIONS (idempotent + based on your DDL)
 ------------------------------------------------------- */

/** Ensure employees has PK(id) + AUTO_INCREMENT, and fix id=0 rows. */
function ensureEmployeesPKAI(): void {
 // Add table if missing minimal cols (won't run if table already exists)
 exeSql("CREATE TABLE IF NOT EXISTS employees (
  id INT(10) UNSIGNED NOT NULL,
  employee_uid VARCHAR(64) NOT NULL,
  employee_name VARCHAR(150) NOT NULL,
  mobile_number VARCHAR(20) DEFAULT NULL,
  email VARCHAR(150) DEFAULT NULL,
  address TEXT DEFAULT NULL,
  branch_id INT(10) UNSIGNED DEFAULT NULL,
  branch VARCHAR(100) DEFAULT NULL,
  role VARCHAR(100) NOT NULL,
  salary DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  last_paid_period CHAR(6) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

 // Primary key?
 $pk = exeSql("SHOW KEYS FROM employees WHERE Key_name = 'PRIMARY'");
 if (!$pk) {
  // If duplicates on id exist, we need to fix only the special case we saw: id=0
  $hasZero = exeSql("SELECT COUNT(*) AS c FROM employees WHERE id=0");
  $c0 = (int)($hasZero[0]['c'] ?? 0);
  if ($c0 > 0) {
   // sequentially assign new ids for any id=0 rows
   $rows0 = exeSql("SELECT row_number() OVER () AS rn FROM (SELECT 1) t"); // fallback no-op if window not supported
   // Simple loop: for each row with id=0, set to max(id)+1
   for ($i=0; $i<$c0; $i++) {
    $max = exeSql("SELECT COALESCE(MAX(id),0) AS mx FROM employees");
    $next = (int)$max[0]['mx'] + 1;
    exeSql("UPDATE employees SET id={$next} WHERE id=0 LIMIT 1");
   }
  }
  exeSql("ALTER TABLE employees ADD PRIMARY KEY(id)");
 }

 // Ensure AUTO_INCREMENT on id
 // Find max id to seed AUTO_INCREMENT properly
 $mx = exeSql("SELECT COALESCE(MAX(id),0) AS mx FROM employees");
 $next = (int)$mx[0]['mx'] + 1;
 exeSql("ALTER TABLE employees MODIFY id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");
 exeSql("ALTER TABLE employees AUTO_INCREMENT = {$next}");
}

/** Ensure employee_salary_payments has PK(id) + AUTO_INCREMENT. */
function ensurePaymentsPKAI(): void {
 exeSql("CREATE TABLE IF NOT EXISTS employee_salary_payments (
  id BIGINT(20) UNSIGNED NOT NULL,
  employee_id INT(10) UNSIGNED NOT NULL,
  pay_period CHAR(6) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  paid_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  note VARCHAR(255) DEFAULT NULL,
  voucher_no VARCHAR(64) DEFAULT NULL,
  invoice_no VARCHAR(64) DEFAULT NULL,
  payment_by VARCHAR(150) DEFAULT NULL
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

 // Primary key?
 $pk = exeSql("SHOW KEYS FROM employee_salary_payments WHERE Key_name = 'PRIMARY'");
 if (!$pk) {
  // Fix any id=0 the same way
  $hasZero = exeSql("SELECT COUNT(*) AS c FROM employee_salary_payments WHERE id=0");
  $c0 = (int)($hasZero[0]['c'] ?? 0);
  if ($c0 > 0) {
   for ($i=0; $i<$c0; $i++) {
    $max = exeSql("SELECT COALESCE(MAX(id),0) AS mx FROM employee_salary_payments");
    $next = (int)$max[0]['mx'] + 1;
    exeSql("UPDATE employee_salary_payments SET id={$next} WHERE id=0 LIMIT 1");
   }
  }
  exeSql("ALTER TABLE employee_salary_payments ADD PRIMARY KEY(id)");
 }

 $mx = exeSql("SELECT COALESCE(MAX(id),0) AS mx FROM employee_salary_payments");
 $next = (int)$mx[0]['mx'] + 1;
 exeSql("ALTER TABLE employee_salary_payments MODIFY id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT");
 exeSql("ALTER TABLE employee_salary_payments AUTO_INCREMENT = {$next}");
}

/** Create branches table only if missing (for dropdowns). */
function ensureBranchesTable(): void {
 exeSql("CREATE TABLE IF NOT EXISTS branches (
  branch_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_name VARCHAR(150) NOT NULL,
  PRIMARY KEY(branch_id),
  UNIQUE KEY uq_branch_name (branch_name)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

ensureEmployeesPKAI();     // Fix employees: PK + AI + id=0 rows
ensurePaymentsPKAI();     // Fix payments: PK + AI
ensureBranchesTable();    // Ensure branches exists for UI

/* ----------------- helpers for list math ----------------- */
function monthStart(?string $yyyymm = null): string {
 if ($yyyymm && preg_match('/^\d{6}$/',$yyyymm)) {
  $y = (int)substr($yyyymm,0,4);
  $m = (int)substr($yyyymm,4,2);
  return sprintf('%04d-%02d-01 00:00:00', $y, $m);
 }
 return date('Y-m-01 00:00:00');
}
function nextMonthStart(string $fromStart): string {
 $ts = strtotime($fromStart . ' +1 month');
 return date('Y-m-01 00:00:00', $ts);
}
function currentYYYYMM(): string { return date('Ym'); }

/* -------------------------- actions -------------------------- */

/** Health check */
if ($action === 'ping') { jexit(['status'=>'ok']); }

/** Branch list */
if ($action === 'branches') {
 $rows = exeSql("SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
 $out = [];
 foreach ($rows ?: [] as $r) {
  $id = (int)($r['branch_id'] ?? 0);
  $nm = trim($r['branch_name'] ?? '');
  if ($id && $nm !== '') $out[] = ['branch_id'=>$id, 'branch_name'=>$nm];
 }
 jexit($out);
}

/** Get one employee */
if ($action === 'getEmployee') {
 $id = (int)($_GET['id'] ?? -1);
 if ($id < 1) fail(422, 'id is required');

 // Prefer branches.branch_name when available; otherwise use employees.branch text column
 $row = exeSql("
  SELECT e.id, e.employee_uid, e.employee_name, e.mobile_number, e.email, e.address,
     e.branch_id,
     COALESCE(b.branch_name, e.branch) AS branch_name,
     e.role, CAST(e.salary AS DECIMAL(12,2)) AS salary
  FROM employees e
  LEFT JOIN branches b ON b.branch_id = e.branch_id
  WHERE e.id = {$id}
  LIMIT 1
 ");
 if (!$row) fail(404, 'Employee not found');

 jexit(['status'=>'success', 'employee'=>$row[0]]);
}

/** Update employee */
if ($action === 'updateEmployee') {
 $id   = (int)($_POST['id'] ?? -1);
 $name  = trim($_POST['employee_name'] ?? '');
 $mobile = trim($_POST['mobile_number'] ?? '');
 $email  = trim($_POST['email'] ?? '');
 $addr  = trim($_POST['address'] ?? '');
 $branchId = (int)($_POST['branch_id'] ?? 0);
 $role  = trim($_POST['role'] ?? '');
 $salary = (float)($_POST['salary'] ?? 0);

 if ($id < 1) fail(422,'id is required');
 if ($name==='') fail(422,'Employee name is required');
 if ($role==='') fail(422,'Role is required');
 if ($salary<=0) fail(422,'Salary must be greater than 0');
 if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) fail(422,'Invalid email');

 $exists = exeSql("SELECT 1 FROM employees WHERE id={$id} LIMIT 1");
 if (!$exists) fail(404,'Employee not found');

 if ($branchId > 0) {
  $bexists = exeSql("SELECT 1 FROM branches WHERE branch_id={$branchId} LIMIT 1");
  if (!$bexists) fail(422,'Invalid branch_id');
 }

 $nS = str_replace("'", "''", $name);
 $mS = str_replace("'", "''", $mobile);
 $eS = str_replace("'", "''", $email);
 $aS = str_replace("'", "''", $addr);
 $rS = str_replace("'", "''", $role);
 $sal = number_format($salary,2,'.','');
 $branchSqlVal = ($branchId>0) ? (string)$branchId : 'NULL';

 $ok = exeSql("
  UPDATE employees
   SET employee_name='{$nS}',
     mobile_number='{$mS}',
     email='{$eS}',
     address='{$aS}',
     branch_id={$branchSqlVal},
     role='{$rS}',
     salary={$sal}
  WHERE id={$id}
  LIMIT 1
 ");
 if ($ok === false) fail(500, 'Update failed');

 jexit(['status'=>'success', 'message'=>'Employee updated']);
}

/** Create employee */
if ($action === 'create') {
 $name  = trim($_POST['employee_name'] ?? '');
 $mobile = trim($_POST['mobile_number'] ?? '');
 $email  = trim($_POST['email'] ?? '');
 $addr  = trim($_POST['address'] ?? '');
 $branchId = (int)($_POST['branch_id'] ?? 0);
 $role  = trim($_POST['role'] ?? '');
 $salary = (float)($_POST['salary'] ?? 0);

 if ($name==='') fail(422,'Employee name is required');
 if ($role==='') fail(422,'Role is required');
 if ($salary<=0) fail(422,'Salary must be greater than 0');
 if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) fail(422,'Invalid email');
 if ($branchId>0) {
  $exists = exeSql("SELECT 1 FROM branches WHERE branch_id={$branchId} LIMIT 1");
  if (!$exists) fail(422,'Invalid branch_id');
 }

 // Generate UID (not unique-constrained in your DDL, but we still avoid collisions)
 $uid = null;
 for ($i=0;$i<5;$i++){
  $tmp = 'EMP' . strtoupper(bin2hex(random_bytes(4)));
  $esc = str_replace("'", "''", $tmp);
  $hit = exeSql("SELECT 1 FROM employees WHERE employee_uid='{$esc}' LIMIT 1");
  if(!$hit){ $uid = $tmp; break; }
 }
 if (!$uid) $uid = 'EMP' . time();

 $uidS = str_replace("'", "''", $uid);
 $nS = str_replace("'", "''", $name);
 $mS = str_replace("'", "''", $mobile);
 $eS = str_replace("'", "''", $email);
 $aS = str_replace("'", "''", $addr);
 $rS = str_replace("'", "''", $role);
 $sal = number_format($salary,2,'.','');
 $branchSqlVal = ($branchId>0) ? (string)$branchId : 'NULL';

 // INSERT without id now works because id is AUTO_INCREMENT (we fixed it above)
 $ok = exeSql("INSERT INTO employees
        (employee_uid, employee_name, mobile_number, email, address, branch_id, role, salary)
        VALUES
        ('{$uidS}','{$nS}','{$mS}','{$eS}','{$aS}',{$branchSqlVal},'{$rS}',{$sal})");
 if ($ok === false) fail(500,'Insert failed');

 jexit(['status'=>'success', 'employee_uid'=>$uid]);
}

/** Delete employee (and their payments) */
if ($action === 'deleteEmployee') {
 $id = (int)($_POST['id'] ?? -1);
 if ($id < 1) fail(422, 'id is required');

 $exists = exeSql("SELECT 1 FROM employees WHERE id={$id} LIMIT 1");
 if (!$exists) fail(404,'Employee not found');

 exeSql("DELETE FROM employee_salary_payments WHERE employee_id={$id}");
 $ok = exeSql("DELETE FROM employees WHERE id={$id} LIMIT 1");
 if ($ok === false) fail(500, 'Delete failed');

 jexit(['status'=>'success', 'message'=>'Employee deleted']);
}

/** List (for your table) */
if ($action === 'list') {
 $yyyymm = preg_replace('/[^0-9]/','', $_GET['yyyymm'] ?? '') ?: currentYYYYMM();
 $branchId = (int)($_GET['branch_id'] ?? 0);
 $from  = monthStart($yyyymm);
 $to   = nextMonthStart($from);
 $prevTo = $from;

 $whereEmp = [];
 if ($branchId > 0) $whereEmp[] = "e.branch_id = {$branchId}";
 $whereEmpSql = $whereEmp ? ('WHERE ' . implode(' AND ', $whereEmp)) : '';

 // Use branches.branch_name if available; else fall back to employees.branch
 $employees = exeSql("
  SELECT e.id, e.employee_uid, e.employee_name, e.mobile_number, e.email, e.address,
     e.branch_id, COALESCE(b.branch_name, e.branch) AS branch_name,
     e.role, CAST(e.salary AS DECIMAL(12,2)) AS salary, e.created_at
  FROM employees e
  LEFT JOIN branches b ON b.branch_id = e.branch_id
  {$whereEmpSql}
  ORDER BY e.id DESC
 ") ?: [];

 if (!$employees) {
  jexit(['data'=>[], 'totals'=>['total_salary_all'=>rupee(0), 'total_unpaid_balance'=>rupee(0)]]);
 }

 $ids = array_map(fn($r) => (int)$r['id'], $employees);
 $idList = implode(',', array_map('intval', $ids));

 $paidThisMonth = [];
 if ($idList !== '') {
  $rows = exeSql("
   SELECT employee_id, COALESCE(SUM(amount),0) AS s
   FROM employee_salary_payments
   WHERE employee_id IN ({$idList})
    AND paid_at >= '{$from}' AND paid_at < '{$to}'
   GROUP BY employee_id
  ") ?: [];
  foreach ($rows as $r) { $paidThisMonth[(int)$r['employee_id']] = (float)$r['s']; }
 }

 $paidBefore = [];
 if ($idList !== '') {
  $rows = exeSql("
   SELECT employee_id, COALESCE(SUM(amount),0) AS s
   FROM employee_salary_payments
   WHERE employee_id IN ({$idList})
    AND paid_at < '{$prevTo}'
   GROUP BY employee_id
  ") ?: [];
  foreach ($rows as $r) { $paidBefore[(int)$r['employee_id']] = (float)$r['s']; }
 }

 $monthsBetween = function (string $startYmd, string $endYmdExclusive): int {
  $sy = (int)substr($startYmd,0,4);
  $sm = (int)substr($startYmd,5,2);
  $ey = (int)substr($endYmdExclusive,0,4);
  $em = (int)substr($endYmdExclusive,5,2);
  return max(0, ($ey - $sy) * 12 + ($em - $sm));
 };

 $out = [];
 $i = 1;
 $totalSalary = 0.0;
 $totalUnpaidBalance = 0.0;

 foreach ($employees as $r) {
  $id   = (int)$r['id'];
  $salary = (float)$r['salary'];
  $created = $r['created_at'] ?? date('Y-m-01 00:00:00');
  $createdMonthStart = date('Y-m-01 00:00:00', strtotime($created));

  $months_active_before = 0;
  if (strtotime($createdMonthStart) < strtotime($from)) {
   $months_active_before = $monthsBetween($createdMonthStart, $from);
  }

  $paid_before  = $paidBefore[$id] ?? 0.0;
  $expected_before = $months_active_before * $salary;
  $carry_forward = clamp0($expected_before - $paid_before);

  $paid_this_month = $paidThisMonth[$id] ?? 0.0;
  $balance_val  = clamp0($salary + $carry_forward - $paid_this_month);

  $totalSalary    += $salary;
  $totalUnpaidBalance += $balance_val;

  $statusHtml =
   ($paid_this_month >= ($salary + $carry_forward) && ($salary + $carry_forward) > 0)
    ? '<span class="badge bg-success">Paid</span>'
    : (($paid_this_month > 0)
      ? '<span class="badge bg-warning text-dark">Partially Paid</span>'
      : '<span class="badge bg-danger">Unpaid</span>');

  /** * CORRECTION: Updated to include Font Awesome icons and use 
     * the required btn-delete class and data-id attribute correctly.
     */
  $actions = '
<div class="btn-group btn-group-sm" role="group">
  <a href="employee_edit?id='.(int)$id.'" class="btn btn-sm btn-primary" title="Edit">
    <i class="fas fa-edit"></i>
  </a>
  <button type="button" class="btn btn-sm btn-danger btn-delete" data-id="'.(int)$id.'" title="Delete">
    <i class="fas fa-trash-alt"></i>
  </button>
</div>';

  $out[] = [
   'sno'     => $i++,
   'employee_name' => htmlspecialchars($r['employee_name'] ?? ''),
   'employee_uid' => htmlspecialchars($r['employee_uid'] ?? ''),
   'mobile_number' => htmlspecialchars($r['mobile_number'] ?? ''),
   'email'    => ($r['email'] ?? '') !== '' ? '<a href="mailto='.htmlspecialchars($r['email']).'">'.htmlspecialchars($r['email']).'</a>' : '',
   'address'   => htmlspecialchars($r['address'] ?? ''),
   'branch'    => htmlspecialchars($r['branch_name'] ?? ''),
   'role'     => htmlspecialchars($r['role'] ?? ''),
   'salary'    => rupee($salary),
   'balance'   => rupee($balance_val),
   'status'    => $statusHtml,
   'actions'   => $actions
  ];
 }

 jexit([
  'data' => $out,
  'totals' => [
   'total_salary_all'  => rupee($totalSalary),
   'total_unpaid_balance' => rupee($totalUnpaidBalance)
  ]
 ]);
}

/** Pay salary (unchanged logic) */
if ($action === 'paySalary') {
 $empId = (int)($_POST['employee_id'] ?? 0);
 $yyyymm = preg_replace('/[^0-9]/','', $_POST['yyyymm'] ?? '') ?: currentYYYYMM();
 if (!$empId) fail(422,'employee_id required');

 $from = monthStart($yyyymm);
 $to = nextMonthStart($from);

 $row = exeSql("SELECT id, salary, created_at FROM employees WHERE id={$empId} LIMIT 1");
 if (!$row) fail(404,'Employee not found');
 $salary = (float)$row[0]['salary'];
 $created = $row[0]['created_at'];
 $createdMonthStart = date('Y-m-01 00:00:00', strtotime($created));

 $monthsBetween = function (string $startYmd, string $endYmdExclusive): int {
  $sy = (int)substr($startYmd,0,4);
  $sm = (int)substr($startYmd,5,2);
  $ey = (int)substr($endYmdExclusive,0,4);
  $em = (int)substr($endYmdExclusive,5,2);
  return max(0, ($ey - $sy) * 12 + ($em - $sm));
 };
 $months_active_before = 0;
 if (strtotime($createdMonthStart) < strtotime($from)) {
  $months_active_before = $monthsBetween($createdMonthStart, $from);
 }

 $paidBeforeRow = exeSql("SELECT COALESCE(SUM(amount),0) AS s
             FROM employee_salary_payments
             WHERE employee_id={$empId} AND paid_at < '{$from}'");
 $paid_before = (float)($paidBeforeRow[0]['s'] ?? 0.0);
 $paidThisRow = exeSql("SELECT COALESCE(SUM(amount),0) AS s
            FROM employee_salary_payments
            WHERE employee_id={$empId} AND paid_at >= '{$from}' AND paid_at < '{$to}'");
 $paid_this = (float)($paidThisRow[0]['s'] ?? 0.0);

 $expected_before = $months_active_before * $salary;
 $carry_forward = clamp0($expected_before - $paid_before);

 $default_due = clamp0($salary + $carry_forward - $paid_this);
 $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : $default_due;
 $amount = round($amount, 2);
 if ($amount <= 0) jexit(['status'=>'success', 'message'=>'Nothing due for this month']);

 $amtS = number_format($amount, 2, '.', '');
 $note = trim($_POST['note'] ?? '');
 $noteS = $note === '' ? 'NULL' : ("'".str_replace("'", "''", $note)."'");

 $pp = $yyyymm;
 $ins = exeSql("INSERT INTO employee_salary_payments(employee_id, pay_period, amount, note)
        VALUES({$empId}, '{$pp}', {$amtS}, {$noteS})");
 if ($ins === false) fail(500,'Insert payment failed');

 jexit(['status'=>'success']);
}

/** Mark unpaid for a month (unchanged logic) */
if ($action === 'markUnpaid') {
 $empId = (int)($_POST['employee_id'] ?? 0);
 $yyyymm = preg_replace('/[^0-9]/','', $_POST['yyyymm'] ?? '') ?: currentYYYYMM();
 if (!$empId) fail(422,'employee_id required');

 $from = monthStart($yyyymm);
 $to = nextMonthStart($from);

 exeSql("DELETE FROM employee_salary_payments
     WHERE employee_id={$empId} AND paid_at >= '{$from}' AND paid_at < '{$to}'");

 exeSql("UPDATE employees SET last_paid_period=NULL WHERE id={$empId} LIMIT 1");

 jexit(['status'=>'success']);
}

fail(400,'Invalid action');