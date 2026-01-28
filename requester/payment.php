<?php 
/**
 * REQUESTER: Create/Update Payment Request (vendor/employee/expenses + FIXED EXPENSES)
 * Path: kmk/finance/requester/payment.php
 *
 * NEW in this version:
 * - Adds "Fixed Expenses" (pulls from fixed_expenses, shows amount/paid/remaining; pays pending).
 * - GRN amount now reduced by returns (goods_return_notes) before computing balance.
 * - Duplicate guards extended to fixed expenses (one open request per fixed_id).
 * - All previous features kept (duplicate GRN/employee/expenses checks, edit-while-submitted, etc.).
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

/* include project helpers */
require __DIR__ . '/../functions.php';

/* ---------- tiny utils ---------- */
if (!function_exists('v')) { function v($k,$d=null){ return $_POST[$k]??$_GET[$k]??$d; } }
if (!function_exists('i')) { function i($x){ return is_numeric($x)?(int)$x:0; } }
if (!function_exists('s')) { function s($x){ return trim((string)$x); } }
if (!function_exists('h')) { function h($x){ return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('json_out')) {
	function json_out($data,$code=200){ http_response_code($code); header('Content-Type: application/json; charset=UTF-8'); echo json_encode($data); exit; }
}
if (!function_exists('table_exists')) {
	function table_exists($table){
		$t = preg_replace('/[^a-zA-Z0-9_]/','',$table);
		$rows = exeSql("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' LIMIT 1");
		return !empty($rows);
	}
}
if (!function_exists('table_columns')) {
	function table_columns($table){
		$t = preg_replace('/[^a-zA-Z0-9_]/','',$table);
		$rows = exeSql("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t'");
		$cols = [];
		foreach ($rows as $r){ $cols[$r['COLUMN_NAME']] = true; }
		return $cols;
	}
}
if (!function_exists('safe_insert')) {
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
}

/* ---------- fetch lists ---------- */
function fetch_vendors(){
	if (table_exists('vendors')) return exeSql("SELECT vendor_id, vendor_name FROM vendors ORDER BY vendor_name");
	if (table_exists('vendor'))	 return exeSql("SELECT vendor_id, vendor_name FROM vendor ORDER BY vendor_name");
	return [];
}
function fetch_branches(){
	if (table_exists('branches')) return exeSql("SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
	if (table_exists('branch'))	 return exeSql("SELECT branch_id, branch_name FROM branch ORDER BY branch_name");
	return [];
}
function fetch_employees(){
	if (table_exists('employees')) return exeSql("SELECT id, employee_name FROM employees ORDER BY employee_name");
	if (table_exists('employee'))	 return exeSql("SELECT id, employee_name FROM employee ORDER BY employee_name");
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
function fetch_expense_purposes(){
	if (!table_exists('expenses')) return [];
	$rows = exeSql("SELECT DISTINCT purpose FROM expenses WHERE purpose IS NOT NULL AND TRIM(purpose)<>'' ORDER BY purpose");
	return array_map(fn($r)=>$r['purpose'], $rows);
}

/* ---------- FIXED EXPENSES helpers ---------- */
function fetch_fixed_expenses_pending(){
	if (!table_exists('fixed_expenses')) return [];
	// Use generated remaining_balance column when available
	$cols = table_columns('fixed_expenses');
	$rb   = isset($cols['remaining_balance']) ? "remaining_balance" : "(amount-IFNULL(balance_paid,0))";
	$rows = exeSql("
		SELECT id, expense_type, amount, IFNULL(balance_paid,0) AS balance_paid,
		       $rb AS remaining_balance, notes, frequency, due_day, DATE_FORMAT(start_date,'%Y-%m-%d') AS start_date
		FROM fixed_expenses
		HAVING remaining_balance > 0
		ORDER BY id DESC
	");
	return $rows ?: [];
}
function fetch_fixed_one($id){
	$id = (int)$id;
	if (!$id || !table_exists('fixed_expenses')) return null;
	$cols = table_columns('fixed_expenses');
	$rb   = isset($cols['remaining_balance']) ? "remaining_balance" : "(amount-IFNULL(balance_paid,0))";
	$rows = exeSql("
		SELECT id, expense_type, amount, IFNULL(balance_paid,0) AS balance_paid,
		       $rb AS remaining_balance, notes, frequency, due_day, DATE_FORMAT(start_date,'%Y-%m-%d') AS start_date
		FROM fixed_expenses
		WHERE id=$id
		LIMIT 1
	");
	return $rows ? $rows[0] : null;
}

/* ---------- GRNs & Returns & pending ---------- */
function returns_map_for_grns(array $grn_ids): array {
	$out = [];
	if (!$grn_ids) return $out;
	if (!table_exists('goods_return_notes')) return $out;
	$ids = implode(',', array_map('intval',$grn_ids));
	$rows = exeSql("SELECT grn_id, IFNULL(SUM(total_amount),0) AS rtn FROM goods_return_notes WHERE grn_id IN ($ids) GROUP BY grn_id");
	foreach ($rows ?: [] as $r){
		$out[(int)$r['grn_id']] = (float)$r['rtn'];
	}
	return $out;
}
function enrich_and_filter_pending(array $rows, bool $has_amount_received, array $returnMap){
	$out = [];
	foreach ($rows as $r){
		$grn_id = (int)$r['grn_id'];
		$gross  = (float)$r['total_amount'];
		$rt     = (float)($returnMap[$grn_id] ?? 0.0);
		$total  = max(0.0, $gross - $rt); // reduce by returns
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
				'grn_id'	 	 => $grn_id,
				'grn_number'	 => $r['grn_number'],
				// expose both gross and returns if you want to show them in UI (optional)
				'total_amount' => $total,
				'paid'			 => $paid,
				'balance'		 => $balance
			];
		}
	}
	return $out;
}
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
		$ids = array_map(fn($r)=>(int)$r['grn_id'], $rows ?: []);
		$rtMap = returns_map_for_grns($ids);
		$rows = enrich_and_filter_pending($rows, $has_amount_received, $rtMap);
		if ($rows) return $rows;
	}
	if ($grcols && isset($grcols['branch_id'])) {
		$rows = exeSql("SELECT grn_id, grn_number, total_amount
							FROM goods_receipts
							WHERE vendor_id = $vendor_id AND branch_id = $branch_id
							ORDER BY grn_id DESC");
		$ids = array_map(fn($r)=>(int)$r['grn_id'], $rows ?: []);
		$rtMap = returns_map_for_grns($ids);
		$rows = enrich_and_filter_pending($rows, $has_amount_received, $rtMap);
		if ($rows) return $rows;
	}
	return [];
}

/* ---------- Duplicate guards ---------- */

/** Vendor: find ANY open request that overlaps with any of the selected GRN IDs */
function find_vendor_grn_overlap(array $grn_ids, int $excludeRid=0): ?array {
	$grn_ids = array_values(array_unique(array_map('intval', $grn_ids)));
	if (!$grn_ids) return null;

	$statuses = "'SUBMITTED','APPROVED','READY_FOR_CASHIER'";
	$where = "request_type='vendor' AND status IN ($statuses)";
	if ($excludeRid>0) $where .= " AND request_id<>$excludeRid";

	$conds = [];
	foreach ($grn_ids as $gid) {
		$conds[] = "JSON_CONTAINS(JSON_EXTRACT(payload_json,'$.grn_ids'), JSON_QUOTE(CAST($gid AS CHAR)))";
	}
	if (!$conds) return null;

	$sql = "SELECT request_id, status
			FROM payment_requests
			WHERE $where AND (".implode(' OR ', $conds).")
			ORDER BY updated_at DESC, request_id DESC
			LIMIT 1";
	$row = exeSql($sql);
	if ($row) return ['request_id'=>(int)$row[0]['request_id'], 'status'=>(string)$row[0]['status']];
	return null;
}

/** Employee: block by (employee_id + pay_period) if open */
function find_employee_open(array $payload, int $excludeRid=0): ?array {
	$eid = (int)($payload['employee_id'] ?? 0);
	$per = preg_replace('/\D/','', (string)($payload['pay_period'] ?? ''));
	if (!$eid || !$per) return null;
	$statuses = "'SUBMITTED','APPROVED','READY_FOR_CASHIER'";
	$where = "request_type='employee' AND employee_id=$eid AND JSON_EXTRACT(payload_json,'$.pay_period')='$per' AND status IN ($statuses)";
	if ($excludeRid>0) $where .= " AND request_id<>$excludeRid";
	$row = exeSql("SELECT request_id, status FROM payment_requests WHERE $where ORDER BY updated_at DESC, request_id DESC LIMIT 1");
	if ($row) return ['request_id'=>(int)$row[0]['request_id'], 'status'=>(string)$row[0]['status']];
	return null;
}

/** Expenses: block by purpose (resolved) if open */
function find_expense_open(array $payload, int $excludeRid=0): ?array {
	$purpose = ($payload['purpose'] ?? '');
	$purpose = $purpose==='__other__' ? (string)($payload['custom_purpose'] ?? '') : $purpose;
	$purpose = addslashes($purpose);
	if ($purpose==='') return null;
	$statuses = "'SUBMITTED','APPROVED','READY_FOR_CASHIER'";
	$where = "request_type='expenses' AND status IN ($statuses) AND (
		JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.purpose'))='".addslashes($purpose)."'
		OR JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.custom_purpose'))='".addslashes($purpose)."'
		)";
	if ($excludeRid>0) $where .= " AND request_id<>$excludeRid";
	$row = exeSql("SELECT request_id, status FROM payment_requests WHERE $where ORDER BY updated_at DESC, request_id DESC LIMIT 1");
	if ($row) return ['request_id'=>(int)$row[0]['request_id'], 'status'=>(string)$row[0]['status']];
	return null;
}

/** Fixed expenses: block one open per fixed_id */
function find_fixed_open(int $fixed_id, int $excludeRid=0): ?array {
	if ($fixed_id<=0) return null;
	$statuses = "'SUBMITTED','APPROVED','READY_FOR_CASHIER'";
	$where = "request_type='fixed' AND JSON_EXTRACT(payload_json,'$.fixed_id')='$fixed_id' AND status IN ($statuses)";
	if ($excludeRid>0) $where .= " AND request_id<>$excludeRid";
	$row = exeSql("SELECT request_id, status FROM payment_requests WHERE $where ORDER BY updated_at DESC, request_id DESC LIMIT 1");
	if ($row) return ['request_id'=>(int)$row[0]['request_id'], 'status'=>(string)$row[0]['status']];
	return null;
}

/* ---------- AJAX ---------- */
if (isset($_GET['ajax'])) {
	$act = $_GET['ajax'];

	if ($act === 'vendor_bank') {
		$vendor_id = i(v('vendor_id',0));
		$row = getRowValues('vendors', $vendor_id, 'vendor_id');
		json_out([
			'account_number' => $row['account_number'] ?? '',
			'ifsc'			 => $row['ifsc'] ?? '',
		]);
	}

	if ($act === 'grns') {
		$vendor_id = i(v('vendor_id',0));
		$branch_id = i(v('branch_id',0));
		$rows = fetch_pending_grns($vendor_id, $branch_id);
		json_out($rows ?: []);
	}

	if ($act === 'employee') {
		$employee_id = i(v('employee_id',0));
		$period = preg_replace('/\D/','', v('period', date('Ym')));
		$emp = getRowValues('employees', $employee_id, 'id');
		if (!$emp && table_exists('employee')) $emp = getRowValues('employee', $employee_id, 'id');

		$role	= $emp['role']	 ?? '';
		$salary = (float)($emp['salary'] ?? 0);

		$paidRow = getValues("(SELECT IFNULL(SUM(amount),0) AS paid
							FROM employee_salary_payments
							WHERE employee_id=$employee_id AND pay_period='$period') t", "1");
		$paid = (float)($paidRow['paid'] ?? 0);

		json_out(['role'=>$role, 'salary'=>$salary, 'paid'=>$paid]);
	}

	if ($act === 'employees') {
		$branch_id = i(v('branch_id',0));
		$rows = fetch_employees_by_branch($branch_id);
		json_out($rows ?: []);
	}

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

		$row	 = $trow[0];
		$total	 = (float)($row['amount'] ?? 0);
		$paid	 = (float)($row['balance_paid'] ?? 0);
		$balance = isset($row['remaining_balance'])
			? (float)$row['remaining_balance']
			: max(0.0, $total - $paid);

		json_out(['total'=>$total, 'paid'=>$paid, 'balance'=>$balance]);
	}

	// Fixed Expenses AJAX
	if ($act === 'fixed_list') {
		$list = fetch_fixed_expenses_pending();
		json_out($list);
	}
	if ($act === 'fixed_one') {
		$id = i(v('id',0));
		$row = fetch_fixed_one($id);
		json_out($row ?: []);
	}

	// Duplicate check (client): vendor uses GRN list; fixed by fixed_id
	if ($act === 'dup_check') {
		$rid = (int)($_GET['rid'] ?? 0);
		$pf	= s(v('pay_for',''));
		if ($pf === 'vendor') {
			$grn_ids = [];
			if (isset($_GET['grn_ids'])) {
				if (is_array($_GET['grn_ids'])) $grn_ids = array_map('intval', $_GET['grn_ids']);
				else $grn_ids = array_map('intval', explode(',', (string)$_GET['grn_ids']));
			}
			$dup = find_vendor_grn_overlap($grn_ids, $rid);
			json_out(['duplicate'=> (bool)$dup, 'rid'=>$dup['request_id']??null, 'status'=>$dup['status']??null]);
		} elseif ($pf === 'employee') {
			$payload = [
				'employee_id' => i(v('employee_id',0)),
				'pay_period'	=> preg_replace('/\D/','', v('pay_period','')),
			];
			$dup = find_employee_open($payload, $rid);
			json_out(['duplicate'=> (bool)$dup, 'rid'=>$dup['request_id']??null, 'status'=>$dup['status']??null]);
		} elseif ($pf === 'expenses') {
			$payload = [
				'purpose'		 => s(v('purpose','')),
				'custom_purpose' => s(v('custom_purpose','')),
			];
			$dup = find_expense_open($payload, $rid);
			json_out(['duplicate'=> (bool)$dup, 'rid'=>$dup['request_id']??null, 'status'=>$dup['status']??null]);
		} elseif ($pf === 'fixed') {
			$fixed_id = i(v('fixed_id',0));
			$dup = find_fixed_open($fixed_id, $rid);
			json_out(['duplicate'=> (bool)$dup, 'rid'=>$dup['request_id']??null, 'status'=>$dup['status']??null]);
		} else {
			json_out(['duplicate'=>false]);
		}
	}

	json_out(['error'=>'Unknown action'], 400);
}

/* ---------- Page data ---------- */
$vendors	 = fetch_vendors();
$branches	= fetch_branches();
$employees = fetch_employees();
$purposes	= fetch_expense_purposes();

/* ---------- EDIT MODE ---------- */
$edit_request = null;
$edit_payload = [];
$rid = isset($_GET['rid']) ? (int)$_GET['rid'] : 0;
if ($rid > 0) {
	$row = exeSql("SELECT * FROM payment_requests WHERE request_id=$rid LIMIT 1");
	if ($row) {
		$edit_request = $row[0];
		$edit_payload = json_decode($edit_request['payload_json'] ?? '{}', true) ?: [];
	}
}

/* ---------- SUBMIT / UPDATE ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$existing_rid = (int)($_POST['rid'] ?? 0);
	$pay_for		= s(v('pay_for',''));
	$pay_now		= (float)s(v('pay_now','0'));
	$notes			= s(v('notes',''));
	$total_amt	= (float)s(v('total_amount','0'));

	if (!in_array($pay_for, ['vendor','employee','expenses','fixed'], true)) {
		header("Location: ".$_SERVER['REQUEST_URI']."?err=type"); exit;
	}

	$payload = [
		'pay_for'			 => $pay_for,
		'vendor_id'		 => i(v('vendor_id',0)),
		'branch_id'		 => i(v('branch_id',0)),
		'grn_ids'			 => (array)(v('grn_ids',[])),
		'employee_id'	 => i(v('employee_id',0)),
		'pay_period'	 => preg_replace('/\D/','', v('pay_period', date('Ym'))),
		'purpose'			 => s(v('purpose','')),
		'custom_purpose' => s(v('custom_purpose','')),
		'custom_total'	 => (float)s(v('custom_total','0')),
		'fixed_id'       => i(v('fixed_id',0)), // NEW
		'pay_now'			 => $pay_now,
		'notes'			 => $notes,
		'__total_amount__' => $total_amt ?: null,
	];

	// Server duplicate guards (only for CREATE mode)
	if ($existing_rid === 0) {
		if ($pay_for==='vendor') {
			$dup = find_vendor_grn_overlap($payload['grn_ids'] ?? [], 0);
			if ($dup) {
				$qs = http_build_query(['err'=>'blocked_grn','blk_status'=>$dup['status'],'blk_rid'=>$dup['request_id']]);
				header("Location: ".$_SERVER['REQUEST_URI']."?".$qs);
				exit;
			}
		} elseif ($pay_for==='employee') {
			$dup = find_employee_open($payload, 0);
			if ($dup) {
				$qs = http_build_query(['err'=>'blocked_emp','blk_status'=>$dup['status'],'blk_rid'=>$dup['request_id']]);
				header("Location: ".$_SERVER['REQUEST_URI']."?".$qs);
				exit;
			}
		} elseif ($pay_for==='expenses') {
			$dup = find_expense_open($payload, 0);
			if ($dup) {
				$qs = http_build_query(['err'=>'blocked_exp','blk_status'=>$dup['status'],'blk_rid'=>$dup['request_id']]);
				header("Location: ".$_SERVER['REQUEST_URI']."?".$qs);
				exit;
			}
		} elseif ($pay_for==='fixed') {
			$dup = find_fixed_open((int)$payload['fixed_id'], 0);
			if ($dup) {
				$qs = http_build_query(['err'=>'blocked_fixed','blk_status'=>$dup['status'],'blk_rid'=>$dup['request_id']]);
				header("Location: ".$_SERVER['REQUEST_URI']."?".$qs);
				exit;
			}
		}
	}

	// EDIT: allowed only while SUBMITTED
	if ($existing_rid > 0) {
		$cur = exeSql("SELECT status FROM payment_requests WHERE request_id=$existing_rid LIMIT 1");
		$status = $cur && isset($cur[0]['status']) ? $cur[0]['status'] : '';
		if ($status === 'SUBMITTED') {
			$json = addslashes(json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
			$totalForList = (float)($payload['__total_amount__'] ?: $pay_now);
			$now = addslashes(date('Y-m-d H:i:s'));
			exeSql("UPDATE payment_requests
					SET payload_json='$json',
						total_amount=$totalForList,
						request_type='".addslashes($pay_for)."',
						vendor_id=".($payload['vendor_id']?:'NULL').",
						employee_id=".($payload['employee_id']?:'NULL').",
						branch_id=".($payload['branch_id']?:'NULL').",
						updated_at='$now'
					WHERE request_id=$existing_rid
					LIMIT 1");
			if (table_exists('payment_actions')) {
				safe_insert('payment_actions', [
					'request_id' => $existing_rid,
					'action'		 => 'EDIT',
					'actor_id'	 => (int)($_SESSION['user_id'] ?? 1),
					'comment'		 => 'Request edited by requester',
				]);
			}
			header("Location: dashboard.php?msg=updated&rid=".$existing_rid); exit;
		} else {
			header("Location: dashboard.php?msg=locked&rid=".$existing_rid); exit;
		}
	}

	// CREATE
	$ins = [
		'request_type' => $pay_for,
		'status'		 => 'SUBMITTED',
		'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
		'requested_by' => (int)($_SESSION['user_id'] ?? 1),
		'assigned_to'	 => null,
		'vendor_id'	 => $payload['vendor_id']	 ?: null,
		'employee_id'	 => $payload['employee_id'] ?: null,
		'branch_id'	 => $payload['branch_id']	 ?: null,
		'total_amount' => $payload['__total_amount__'] ?: $pay_now,
	];
	$newRid = safe_insert('payment_requests', $ins);

	if (table_exists('payment_actions')) {
		safe_insert('payment_actions', [
			'request_id' => (int)$newRid,
			'action'		 => 'SUBMIT',
			'actor_id'	 => (int)($_SESSION['user_id'] ?? 1),
			'comment'		 => 'Request submitted',
		]);
	}

	header("Location: dashboard.php?msg=submitted&rid=".$newRid); exit;
}

/* ---------- Defaults for edit mode ---------- */
$def_pay_for = $edit_payload['pay_for'] ?? '';
$def_vendor	 = (int)($edit_payload['vendor_id'] ?? 0);
$def_branch	 = (int)($edit_payload['branch_id'] ?? 0);
$def_emp_branch = (int)($edit_request['branch_id'] ?? $edit_payload['branch_id'] ?? 0);
$def_emp	 	= (int)($edit_payload['employee_id'] ?? 0);
$def_period	 = (string)($edit_payload['pay_period'] ?? date('Ym'));
$def_purpose = (string)($edit_payload['purpose'] ?? '');
$def_custom_purpose = (string)($edit_payload['custom_purpose'] ?? '');
$def_custom_total	= (float)($edit_payload['custom_total'] ?? 0);
$def_pay_now = (float)($edit_payload['pay_now'] ?? 0);
$def_total	 = (float)($edit_request['total_amount'] ?? ($edit_payload['__total_amount__'] ?? 0));
$def_notes	 = (string)($edit_payload['notes'] ?? '');
$def_grns	 = (array)($edit_payload['grn_ids'] ?? []);
$def_fixed  = (int)($edit_payload['fixed_id'] ?? 0);

// Pre-load employee list if in edit mode
$initial_employees = $employees;
if ($def_pay_for === 'employee' && $def_emp_branch > 0) {
	$initial_employees = fetch_employees_by_branch($def_emp_branch);
}

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?= $edit_request ? 'Edit Payment Request' : 'New Payment Request' ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
	:root {
		--primary-color: #007bff;
		--primary-hover: #0056b3;
		--text-color: #333;
		--muted-color: #6c757d;
		--border-color: #dee2e6;
		--bg-light: #f8f9fa;
		--radius-default: 8px;
		--radius-small: 4px;
		--shadow-light: 0 1px 3px rgba(0,0,0,0.05);
	}
	body { font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; margin:20px auto; max-width:800px; color:var(--text-color); background:#fff; }
	h1 { color: var(--primary-color); font-weight:700; margin-bottom:25px; border-bottom:2px solid var(--border-color); padding-bottom:10px; }
	fieldset { border:1px solid var(--border-color); border-radius:var(--radius-default); padding:20px; margin-bottom:25px; background:var(--bg-light); box-shadow:var(--shadow-light); }
	legend { font-weight:600; font-size:1.2em; color:var(--primary-color); padding:0 10px; margin-left:-10px; }
	label { display:block; margin:10px 0 5px; font-weight:500; color:var(--text-color); cursor:pointer; }
	input, select, textarea { width:100%; padding:10px 12px; border:1px solid var(--border-color); border-radius:var(--radius-small); box-sizing:border-box; transition:border-color .2s, box-shadow .2s; background:#fff; }
	input:focus, select:focus, textarea:focus { border-color:var(--primary-color); box-shadow:0 0 0 3px rgba(0,123,255,.25); outline:none; }
	.row { display:grid; grid-template-columns:repeat(auto-fit, minmax(280px,1fr)); gap:20px; }
	.row-3 { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:20px; }
	.totals { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px,1fr)); gap:15px; }
	.hide { display:none !important; }
	button { padding:12px 20px; border:0; border-radius:var(--radius-default); background:var(--primary-color); color:#fff; font-weight:600; cursor:pointer; transition:background-color .2s, opacity .2s; margin-top:15px; }
	button:hover { background:var(--primary-hover); }
	.btn-disabled { opacity:.5; cursor:not-allowed; background:var(--muted-color); }
	.muted { color:var(--muted-color); font-size:.85em; margin-top:5px; }
	.info { color:#0f5132; background:#d1e7dd; border:1px solid #badbcc; padding:10px; border-radius:var(--radius-small); font-size:.9em; margin-top:10px; }
	.warn, .danger { padding:12px; border-radius:var(--radius-default); margin-bottom:20px; font-weight:500; display:flex; align-items:center; gap:10px; }
	.warn { color:#856404; background:#fff3cd; border:1px solid #ffeeba; }
	.danger { color:#721c24; background:#f8d7da; border:1px solid #f5c6cb; }
	.grn-list { border:1px solid var(--border-color); border-radius:var(--radius-small); padding:10px; max-height:250px; overflow-y:auto; background:#fff; margin-top:5px; }
	.grn-item { display:flex; align-items:center; gap:12px; padding:8px 0; border-bottom:1px solid var(--bg-light); cursor:pointer; }
	.grn-item:last-child { border-bottom:none; }
	.grn-item input[type="checkbox"] { width:auto; margin:0; flex-shrink:0; }
	.grn-amounts { font-size:.8em; color:var(--muted-color); margin-top:2px; }
	.grn-item strong { color:var(--text-color); }
	input[readonly] { background:#e9ecef; cursor:default; }
</style>
</head>
<body>
	<h1><?= $edit_request ? 'Edit Payment Request' : 'New Payment Request' ?></h1>

	<?php
	if (isset($_GET['err'])):
		$brid = (int)($_GET['blk_rid'] ?? 0);
		$bs	 = (string)($_GET['blk_status'] ?? '');
		$msg	= '';
		if ($_GET['err']==='blocked_grn') {
			if ($bs==='SUBMITTED')			$msg = "There’s an open request using this GRN — waiting for approval (Request #$brid). Please wait or edit that request from the Dashboard.";
			elseif ($bs==='APPROVED')		$msg = "There’s an open request using this GRN — approved and ready to be forwarded to Cashier (Request #$brid).";
			elseif ($bs==='READY_FOR_CASHIER') $msg = "There’s an open request using this GRN — with Cashier (disbursal pending) (Request #$brid). You can raise a new request after payment is completed.";
			else							$msg = "There’s an open request using this GRN (Request #$brid).";
		} elseif ($_GET['err']==='blocked_emp') {
			if ($bs==='SUBMITTED')			$msg = "There’s a pending request for this employee & period — waiting for approval (Request #$brid).";
			elseif ($bs==='APPROVED')		$msg = "There’s a request for this employee & period — approved and ready to forward to Cashier (Request #$brid).";
			elseif ($bs==='READY_FOR_CASHIER') $msg = "There’s a request for this employee & period — with Cashier (disbursal pending) (Request #$brid).";
		} elseif ($_GET['err']==='blocked_exp') {
			if ($bs==='SUBMITTED')			$msg = "There’s a pending request for this expense purpose — waiting for approval (Request #$brid).";
			elseif ($bs==='APPROVED')		$msg = "There’s a request for this expense purpose — approved and ready to forward to Cashier (Request #$brid).";
			elseif ($bs==='READY_FOR_CASHIER') $msg = "There’s a request for this expense purpose — with Cashier (disbursal pending) (Request #$brid).";
		} elseif ($_GET['err']==='blocked_fixed') {
			if ($bs==='SUBMITTED')			$msg = "There’s an open request for this fixed expense — waiting for approval (Request #$brid).";
			elseif ($bs==='APPROVED')		$msg = "There’s a request for this fixed expense — approved and ready to forward to Cashier (Request #$brid).";
			elseif ($bs==='READY_FOR_CASHIER') $msg = "There’s a request for this fixed expense — with Cashier (disbursal pending) (Request #$brid).";
		}
		if ($msg): ?>
			<p class="danger"><?= h($msg) ?></p>
		<?php endif; endif; ?>

	<div id="dupBanner" class="warn hide"></div>

<form method="post" id="paymentForm">
	<?php if ($edit_request): ?>
		<input type="hidden" name="rid" value="<?= (int)$edit_request['request_id'] ?>">
	<?php endif; ?>

	<fieldset>
		<legend>Payment For</legend>
		<label>Type</label>
		<select name="pay_for" id="pay_for" required>
			<option value="">-- Select --</option>
			<option value="vendor"	 <?= ($def_pay_for==='vendor'?'selected':'') ?>>Vendor</option>
			<option value="employee" <?= ($def_pay_for==='employee'?'selected':'') ?>>Employees</option>
			<option value="expenses" <?= ($def_pay_for==='expenses'?'selected':'') ?>>Expenses</option>
			<option value="fixed"    <?= ($def_pay_for==='fixed'?'selected':'') ?>>Fixed Expenses</option>
		</select>
	</fieldset>

	<fieldset id="vendor_block" class="hide">
		<legend>Vendor</legend>
        <!-- (unchanged) -->
		<div class="row">
			<div>
				<label>Vendor</label>
				<select name="vendor_id" id="vendor_id">
					<option value="">-- Select vendor --</option>
					<?php foreach ($vendors as $v): ?>
						<option value="<?= (int)$v['vendor_id'] ?>" <?= ($def_vendor===(int)$v['vendor_id']?'selected':'') ?>><?= h($v['vendor_name']) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label>Branch</label>
				<select name="branch_id" id="branch_id">
					<option value="">-- Select branch --</option>
					<?php foreach ($branches as $b): ?>
						<option value="<?= (int)$b['branch_id'] ?>" <?= ($def_branch===(int)$b['branch_id']?'selected':'') ?>><?= h($b['branch_name']) ?></option>
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
			</div>
			<div>
				<label>Vendor Bank (readonly)</label>
				<input type="text" id="vendor_bank_view" readonly placeholder="Auto-fills on vendor select">
			</div>
		</div>
	</fieldset>

	<fieldset id="employee_block" class="hide">
		<legend>Employee</legend>
        <!-- (unchanged) -->
		<div class="row">
			<div>
				<label>Branch</label>
				<select id="emp_branch_id">
					<option value="">-- Select branch --</option>
					<?php foreach ($branches as $b): ?>
						<option value="<?= (int)$b['branch_id'] ?>" <?= ($def_emp_branch===(int)$b['branch_id']?'selected':'') ?>><?= h($b['branch_name']) ?></option>
					<?php endforeach; ?>
				</select>
				<div class="muted">Filters the employees list below.</div>
			</div>
			<div>
				<label>Employee</label>
				<select name="employee_id" id="employee_id">
					<option value="">-- Select employee --</option>
					<?php foreach ($initial_employees as $e): ?>
						<option value="<?= (int)$e['id'] ?>" <?= ($def_emp===(int)$e['id']?'selected':'') ?>><?= h($e['employee_name']) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<div class="row">
			<div><label>Role</label><input type="text" id="emp_role" readonly></div>
			<div><label>Salary</label><input type="text" id="emp_salary" readonly></div>
		</div>
		<div class="row">
			<div><label>Pay Period (YYYYMM)</label><input type="text" name="pay_period" id="pay_period" value="<?= h($def_period) ?>"></div>
			<div style="align-self:end"><div id="emp_nopending" class="info hide">No pending salary for the selected period.</div></div>
		</div>
	</fieldset>

	<fieldset id="expenses_block" class="hide">
		<legend>Expenses</legend>
        <!-- (unchanged) -->
		<div class="row">
			<div>
				<label>Purpose</label>
				<select name="purpose" id="purpose">
					<option value="">-- Select purpose --</option>
					<?php foreach ($purposes as $p): ?>
						<option value="<?= h($p) ?>" <?= ($def_purpose===$p?'selected':'') ?>><?= h($p) ?></option>
					<?php endforeach; ?>
					<option value="__other__" <?= ($def_purpose==='__other__'?'selected':'') ?>>Other…</option>
				</select>
				<div class="muted">From <code>expenses.purpose</code>. Choose <em>Other…</em> to add a new purpose.</div>
			</div>
			<div id="custom_purpose_wrap" class="<?= ($def_purpose==='__other__' || $def_custom_purpose ? '' : 'hide') ?>">
				<label>New Purpose</label>
				<input type="text" id="custom_purpose" name="custom_purpose" placeholder="Enter new purpose" value="<?= h($def_custom_purpose) ?>">
			</div>
		</div>
		<div class="row <?= ($def_purpose==='__other__' || $def_custom_purpose ? '' : 'hide') ?>" id="custom_total_wrap">
			<div><label>Total (new purpose)</label><input type="number" step="0.01" name="custom_total" id="custom_total" value="<?= $def_custom_total?number_format($def_custom_total,2,'.',''):'' ?>"></div>
			<div class="muted" style="align-self:end">If blank, we’ll treat the first payment as the total.</div>
		</div>
		<div id="exp_nopending" class="info hide">No pending amount for this purpose.</div>
	</fieldset>

	<!-- FIXED EXPENSES -->
	<fieldset id="fixed_block" class="hide">
		<legend>Fixed Expenses</legend>
		<div class="row">
			<div>
				<label>Fixed Expense</label>
				<select name="fixed_id" id="fixed_id">
					<option value="">-- Select fixed expense --</option>
				</select>
				<div class="muted">From <code>fixed_expenses</code> (only those with positive remaining balance).</div>
			</div>
			<div>
				<label>Meta (readonly)</label>
				<input type="text" id="fixed_meta" readonly placeholder="Type / Frequency / Due-day / Notes">
			</div>
		</div>
	</fieldset>

	<fieldset id="amounts_block" class="hide">
		<legend>Amounts</legend>
		<div class="totals">
			<div><label>Total Amount</label><input type="number" step="0.01" id="total_amount" name="total_amount" readonly value="<?= $def_total?number_format($def_total,2,'.',''):'' ?>"></div>
			<div><label>Paid (till now)</label><input type="number" step="0.01" id="paid_so_far" readonly></div>
			<div><label>Balance</label><input type="number" step="0.01" id="balance" readonly></div>
			<div><label>Pay Now</label><input type="number" step="0.01" name="pay_now" id="pay_now" min="0" value="<?= number_format($def_pay_now,2,'.','') ?>"></div>
		</div>
	</fieldset>

	<div id="grn_hidden_inputs"></div>

	<div class="row">
		<div><label>Notes</label><input type="text" name="notes" id="notes" placeholder="Optional" value="<?= h($def_notes) ?>"></div>
	</div>

	<button id="submitBtn" type="submit"><?= $edit_request ? 'Update Request' : 'Submit Request' ?></button>
</form>

<script>
const $ = s=>document.querySelector(s);

const payFor	 = $('#pay_for');
const vendorBl = $('#vendor_block');
const empBl		 = $('#employee_block');
const expBl		 = $('#expenses_block');
const fixedBl  = $('#fixed_block');

const vendorSel = $('#vendor_id');
const branchSel = $('#branch_id');
const grnList	 = $('#grn_list');

const amountsBl = $('#amounts_block');
const expNoPending = $('#exp_nopending');
const empNoPending = $('#emp_nopending');

const totalAmount = $('#total_amount');
const paidSoFar	 = $('#paid_so_far');
const balance	 	 = $('#balance');
const payNow	 	 = $('#pay_now');

const vendorBankV = $('#vendor_bank_view');

const empBranch	 = $('#emp_branch_id');
const empSel	 	 = $('#employee_id');
const empRole	 	 = $('#emp_role');
const empSalary	 = $('#emp_salary');
const payPeriod	 = $('#pay_period');

const purposeSel	= $('#purpose');
const customPurpose = document.getElementById('custom_purpose');
const customWrap	= document.getElementById('custom_purpose_wrap');
const customTotalWrap = document.getElementById('custom_total_wrap');
const customTotal = document.getElementById('custom_total');

const fixedSel   = $('#fixed_id');
const fixedMeta  = $('#fixed_meta');

const hiddenGrnInputs = $('#grn_hidden_inputs');
const dupBanner = $('#dupBanner');
const submitBtn = $('#submitBtn');

const editRid = <?= (int)($edit_request['request_id'] ?? 0) ?>;
const defGrns = <?= json_encode($def_grns) ?>;
const defFixed = <?= (int)$def_fixed ?>;

function show(el){ el.classList.remove('hide'); }
function hide(el){ el.classList.add('hide'); }
function setVal(el,v){ el.value = v ?? ''; }
function resetAmounts(){ setVal(totalAmount,''); setVal(paidSoFar,''); setVal(balance,''); setVal(payNow,'0'); }
function showAmounts(){ show(amountsBl); }
function hideAmounts(){ hide(amountsBl); }

function toggleSections() {
	[vendorBl, empBl, expBl, fixedBl].forEach(hide);
	[expNoPending, empNoPending].forEach(hide);
	resetAmounts();
	if (payFor.value==='vendor') { show(vendorBl); refreshVendor(); }
	if (payFor.value==='employee') { show(empBl); loadEmp(); }
	if (payFor.value==='expenses') { show(expBl); loadExpenseSummary(); }
	if (payFor.value==='fixed') { show(fixedBl); loadFixedList(); }
	liveDupCheck();
}
payFor.addEventListener('change', toggleSections);

function setSubmitEnabled(on){
	if (on){ submitBtn.disabled=false; submitBtn.classList.remove('btn-disabled'); }
	else{ submitBtn.disabled=true; submitBtn.classList.add('btn-disabled'); }
}
function banner(status, rid, kind){
	let base = '';
	if (kind==='grn') base = 'There’s an open request using this GRN — ';
	if (kind==='emp') base = 'There’s an open request for this employee & period — ';
	if (kind==='exp') base = 'There’s an open request for this expense purpose — ';
	if (kind==='fixed') base = 'There’s an open request for this fixed expense — ';
	let tail = ` (Request #${rid}).`;
	if (status==='SUBMITTED') return base + 'waiting for approval' + tail;
	if (status==='APPROVED') return base + 'approved and ready to forward to Cashier' + tail;
	if (status==='READY_FOR_CASHIER') return base + 'with Cashier (disbursal pending)' + tail;
	return base + 'please check the Dashboard' + tail;
}
function showDup(msg){ dupBanner.textContent = msg; show(dupBanner); setSubmitEnabled(false); }
function clearDup(){ dupBanner.textContent=''; hide(dupBanner); setSubmitEnabled(true); }

async function liveDupCheck(){
	const pf = payFor.value;
	const qs = new URLSearchParams();
	qs.set('ajax','dup_check');
	qs.set('rid', String(editRid||0));
	qs.set('pay_for', pf||'');

	if (pf==='vendor'){
		const checked = Array.from(document.querySelectorAll('.grn-cb:checked')).map(cb=>cb.value);
		if (checked.length) qs.set('grn_ids', checked.join(','));
	} else if (pf==='employee'){
		qs.set('employee_id', empSel.value||'0');
		qs.set('pay_period', (payPeriod.value||'').replace(/\D/g,''));
	} else if (pf==='expenses'){
		qs.set('purpose', purposeSel.value||'');
		if (purposeSel.value==='__other__') qs.set('custom_purpose', (document.querySelector('#custom_purpose')?.value||''));
		else qs.set('custom_purpose', '');
	} else if (pf==='fixed') {
		qs.set('fixed_id', fixedSel.value||'0');
	} else {
		clearDup(); return;
	}

	try{
		const res = await fetch('?'+qs.toString());
		if (!res.ok){ clearDup(); return; }
		const j = await res.json();
		if (j.duplicate){
			const kind = (pf==='vendor')?'grn':(pf==='employee')?'emp':(pf==='expenses')?'exp':'fixed';
			showDup(banner(j.status, j.rid, kind));
		} else {
			clearDup();
		}
	} catch(_){ clearDup(); }
}

/* ------- Vendor GRNs (with returns deducted) ------- */
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
		cb.dataset.total = g.total_amount; // already net of returns
		cb.dataset.paid	 = g.paid;
		cb.dataset.balance = g.balance;

		if (defGrns.includes(String(g.grn_id))) cb.checked = true;

		const info = document.createElement('div');
		const title = document.createElement('div');
		title.innerHTML = `<strong>${g.grn_number}</strong>`;
		const meta	= document.createElement('div');
		meta.className = 'grn-amounts';
		meta.textContent = `Net Total ₹${Number(g.total_amount).toFixed(2)} | Paid ₹${Number(g.paid).toFixed(2)} | Bal ₹${Number(g.balance).toFixed(2)}`;

		info.appendChild(title);
		info.appendChild(meta);

		row.appendChild(cb);
		row.appendChild(info);
		frag.appendChild(row);
	});
	grnList.appendChild(frag);

	document.querySelectorAll('.grn-cb').forEach(cb=>{
		cb.addEventListener('change', ()=>{ recalcFromChecked(); liveDupCheck(); });
	});

	recalcFromChecked();
	liveDupCheck();
}

function refreshVendor(){
	const vid = vendorSel.value, bid = branchSel.value;
	grnList.innerHTML = '<div class="muted">Loading…</div>';
	hiddenGrnInputs.innerHTML = '';
	resetAmounts(); hideAmounts();
	if (vid) {
		fetch('?ajax=vendor_bank&vendor_id='+vid).then(r=>r.json()).then(j=>{
			const view = (j.account_number?('A/C: '+j.account_number):'') + (j.ifsc?(' | IFSC: '+j.ifsc):'');
			if (vendorBankV) vendorBankV.value = view;
		});
	} else {
		vendorBankV.value = '';
	}

	if (!vid || !bid){
		grnList.innerHTML = '<div class="muted">Select a vendor and a branch to load GRNs…</div>';
		return;
	}
	fetch(`?ajax=grns&vendor_id=${vid}&branch_id=${bid}`).then(r=>r.json()).then(list=>{
		renderGRNCheckboxes(list);
	});
}

function recalcFromChecked(){
	const cbs = Array.from(document.querySelectorAll('.grn-cb:checked'));
	hiddenGrnInputs.innerHTML = '';
	if (cbs.length===0){ resetAmounts(); hideAmounts(); return; }
	let total=0, paid=0, bal=0;
	cbs.forEach(cb=>{
		total += parseFloat(cb.dataset.total||'0');
		paid	+= parseFloat(cb.dataset.paid||'0');
		bal	 += parseFloat(cb.dataset.balance||'0');

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

vendorSel && vendorSel.addEventListener('change', refreshVendor);
branchSel && branchSel.addEventListener('change', refreshVendor);

/* ------- Employees ------- */
async function populateEmployees(branchId, selectedId){
	empSel.innerHTML = '<option value="">Loading…</option>';
	try {
		const res = await fetch(`?ajax=employees&branch_id=${encodeURIComponent(branchId||0)}`);
		const list = await res.json();
		empSel.innerHTML = '<option value="">-- Select employee --</option>';
		if (!Array.isArray(list) || list.length===0) return;

		const frag = document.createDocumentFragment();
		list.forEach(e=>{
			const opt = document.createElement('option');
			opt.value = e.id;
			opt.textContent = e.employee_name;
			if (e.id == selectedId) opt.selected = true;
			frag.appendChild(opt);
		});
		empSel.appendChild(frag);
	} catch (e) {
		empSel.innerHTML = '<option value="">-- Error loading employees --</option>';
	}
}

empBranch && empBranch.addEventListener('change', ()=>{
	empSel.value = '';
	empRole.value = '';
	empSalary.value = '';
	resetAmounts(); hideAmounts(); hide(empNoPending);
	populateEmployees(empBranch.value, 0);
	loadEmp();
});

async function loadEmp(){
	hide(empNoPending); resetAmounts();
	const id = empSel.value; if (!id) { liveDupCheck(); return; }
	const period = (payPeriod.value||'').replace(/\D/g,'');

	try {
		const res = await fetch(`?ajax=employee&employee_id=${id}&period=${period}`);
		const j = await res.json();
		empRole.value = j.role||'';
		const salary = j.salary?Number(j.salary):0;
		const paid	 = j.paid?Number(j.paid):0;
		const bal = salary - paid;
		empSalary.value = salary?salary.toFixed(2):'';
		setVal(totalAmount, salary?salary.toFixed(2):'');
		setVal(paidSoFar, paid.toFixed(2));
		setVal(balance, bal.toFixed(2));
		showAmounts(); 
		if (bal>0){ 
			setVal(payNow, bal.toFixed(2));
		} else { 
			setVal(payNow, (0).toFixed(2));
			show(empNoPending);
		}
	} catch (e) {
		console.error("Error loading employee data:", e);
	}
	liveDupCheck();
}
empSel && empSel.addEventListener('change', loadEmp);
payPeriod && payPeriod.addEventListener('change', loadEmp);

/* ------- Expenses ------- */
async function loadExpenseSummary(){
	hide(expNoPending);
	resetAmounts();
	const purpose = purposeSel.value;

	if (purpose === '__other__') {
		show(customWrap);
		show(customTotalWrap);
		setVal(paidSoFar, (0).toFixed(2));
		setVal(payNow, (parseFloat(customTotal.value||'0')||0).toFixed(2));
		showAmounts();
		recomputeBalance();
		setTimeout(()=>customTotal.focus(), 0);
	} else if (purpose) {
		hide(customWrap); hide(customTotalWrap);
		try {
			const res = await fetch(`?ajax=expense_summary&purpose=${encodeURIComponent(purpose)}`);
			const j = await res.json();
			const total = Number(j.total||0), paid=Number(j.paid||0), bal=Number(j.balance||0);
			setVal(totalAmount, total.toFixed(2));
			setVal(paidSoFar, paid.toFixed(2));
			setVal(balance, bal.toFixed(2));
			if (bal>0){ setVal(payNow, bal.toFixed(2)); showAmounts(); }
			else { hideAmounts(); show(expNoPending); }
		} catch(e) {
			console.error("Error loading expense summary:", e);
		}
	} else {
		hide(customWrap); hide(customTotalWrap); hide(expNoPending); hideAmounts();
	}
	liveDupCheck();
}
purposeSel && purposeSel.addEventListener('change', loadExpenseSummary);
customPurpose && customPurpose.addEventListener('input', liveDupCheck);
customTotal && customTotal.addEventListener('input', ()=>{
	const t = parseFloat(customTotal.value||'0');
	setVal(totalAmount, t>0 ? t.toFixed(2) : '');
	setVal(payNow, t>0 ? t.toFixed(2) : '');
	recomputeBalance();
});

/* ------- Fixed Expenses ------- */
async function loadFixedList(){
	try{
		const res = await fetch(`?ajax=fixed_list`);
		const list = await res.json();
		fixedSel.innerHTML = '<option value="">-- Select fixed expense --</option>';
		if (!Array.isArray(list) || list.length===0){
			fixedMeta.value = '';
			hideAmounts(); resetAmounts(); 
			return;
		}
		const frag = document.createDocumentFragment();
		list.forEach(r=>{
			const opt = document.createElement('option');
			opt.value = r.id;
			const label = `${r.expense_type.toUpperCase()} — ₹${Number(r.amount).toFixed(2)} (paid ₹${Number(r.balance_paid).toFixed(2)}, bal ₹${Number(r.remaining_balance).toFixed(2)})`;
			opt.textContent = label + (r.notes ? ` — ${r.notes}` : '');
			if (String(r.id) === String(<?= json_encode($def_fixed) ?>)) opt.selected = true;
			frag.appendChild(opt);
		});
		fixedSel.appendChild(frag);

		// If edit mode has a default, load it, else clear amounts
		if (fixedSel.value) { await loadFixedOne(); } else { hideAmounts(); resetAmounts(); fixedMeta.value=''; }
		liveDupCheck();
	}catch(e){
		fixedSel.innerHTML = '<option value="">-- Error loading fixed expenses --</option>';
		hideAmounts(); resetAmounts();
	}
}
async function loadFixedOne(){
	const id = fixedSel.value;
	if (!id){ fixedMeta.value=''; hideAmounts(); resetAmounts(); liveDupCheck(); return; }
	try{
		const res = await fetch(`?ajax=fixed_one&id=${encodeURIComponent(id)}`);
		const r = await res.json();
		if (!r || !r.id){ fixedMeta.value=''; hideAmounts(); resetAmounts(); return; }
		fixedMeta.value = `${String(r.expense_type||'').toUpperCase()} | ${r.frequency||''} | Due day ${r.due_day||''}${r.notes?(' | '+r.notes):''}`;

		const total = Number(r.amount||0), paid=Number(r.balance_paid||0), bal=Number(r.remaining_balance||0);
		setVal(totalAmount, total.toFixed(2));
		setVal(paidSoFar, paid.toFixed(2));
		setVal(balance, bal.toFixed(2));
		setVal(payNow, bal>0? bal.toFixed(2) : (0).toFixed(2));
		showAmounts();
	} catch(e){
		console.error(e);
		hideAmounts(); resetAmounts(); fixedMeta.value='';
	}
	liveDupCheck();
}
fixedSel && fixedSel.addEventListener('change', loadFixedOne);

/* ------- Amount recompute ------- */
function recomputeBalance(){
	const t = parseFloat(totalAmount.value||'0');
	const p = parseFloat(paidSoFar.value||'0');
	const n = parseFloat(payNow.value||'0');
	const bal = t - p - n;
	setVal(balance, isFinite(bal)?bal.toFixed(2):'');
	payNow.max = t - p > 0 ? (t - p).toFixed(2) : 0;
}
payNow.addEventListener('input', recomputeBalance);
totalAmount.addEventListener('input', recomputeBalance);
paidSoFar.addEventListener('input', recomputeBalance);

/* ------- Init ------- */
document.addEventListener('DOMContentLoaded', async ()=>{
	const initialEmpBranchId = <?= (int)$def_emp_branch ?>;
	const initialEmpId = <?= (int)$def_emp ?>;

	if (initialEmpBranchId > 0) {
		await populateEmployees(initialEmpBranchId, initialEmpId);
	}
	toggleSections();

	if (payFor.value === 'vendor' && vendorSel.value && branchSel.value) {
		refreshVendor();
	}
	if (payFor.value === 'employee' && empSel.value) {
		loadEmp();
	}
	if (payFor.value === 'expenses' && (purposeSel.value || (customPurpose && customPurpose.value))) {
		loadExpenseSummary();
	}
	if (payFor.value === 'fixed') {
		await loadFixedList();
	}
	recomputeBalance();
});
</script>
</body>
</html>
