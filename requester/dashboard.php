<?php
session_start();

require_once("../functions.php");

if (!isset($_SESSION["userId"] )) {
    echo '<script>window.location.href = "../login.php";</script>';
    exit;
}
if (
    !isset($_SESSION["roleName"]) ||
    ($_SESSION["roleName"] !== 'Requester' && $_SESSION["roleName"] !== 'Admin')
) {
    // Destroy session to prevent access
    session_destroy();
    echo '<script>alert("Access denied. Only Authorized can login."); window.location.href = "../login.php";</script>';
    exit;
}


$userId = $_SESSION["userId"];

require_once("header.php");
require_once("nav.php");
/**
 * Requester Dashboard
 * Path: kmk/finance/requester/dashboard.php
 *
 * - Tabs: Dashboard / Vendors / Employees / Expenses / FIXED
 * - Dashboard tab now uses internal pill navigation (Pending/Approved/etc.) to show ONE consolidated table
 * - Dashboard KPI cards show ALL totals. Pill navigation COUNT REMOVED for clarity (as requested).
 * - NEW: Vendors/Employees/Expenses/FIXED tabs now have their OWN KPI cards showing only their type's totals.
 * - Request ID column hidden; progress column shows plain-language status
 * - Logs SEND_TO_CASHIER action when forwarding an approved request
 * * NOTE: All internal pill counts (Dashboard, Vendors, Employees, Expenses, FIXED) have been removed
 * for "Pending" and "With Cashier" to align with the simplified design requirement.
 * * NEW: A visual indicator (•) is added next to "Pending" links/cards when the count is > 0.
 *
 * MODIFICATION: Added 'View Payment History' button to header.
 */
// declare(strict_types=1);
// if (session_status() === PHP_SESSION_NONE) { session_start(); }

// require __DIR__ . '/../functions.php';

/* helpers */
function v($k,$d=null){return $_POST[$k]??$_GET[$k]??$d;}
function i($x){return is_numeric($x)?(int)$x:0;}
function s($x){return trim((string)($x??''));}
function h($x){return htmlspecialchars((string)$x,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}

function table_exists(string $t): bool {
	$t = preg_replace('/[^a-zA-Z0-9_]/','',$t);
	$rows = exeSql("SHOW TABLES LIKE '$t'");
	return is_array($rows) && $rows;
}
function has_col(string $table,string $col): bool {
	$table = preg_replace('/[^a-zA-Z0-9_]/','',$table);
	$col = preg_replace('/[^a-zA-Z0-9_]/','',$col);
	$rows = exeSql("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$table' AND COLUMN_NAME='$col' LIMIT 1");
	return is_array($rows) && $rows;
}

/* status update helper */
function update_request_status(int $rid, string $status): void {
	$status = addslashes($status);
	$now = addslashes(date('Y-m-d H:i:s'));
	exeSql("UPDATE payment_requests SET status='$status', updated_at='$now' WHERE request_id=$rid LIMIT 1");
}

/* send to cashier */
if ($_SERVER['REQUEST_METHOD']==='POST' && s(v('action',''))==='send_cashier') {
	$rid = i(v('request_id',0));
	$row = exeSql("SELECT status FROM payment_requests WHERE request_id=$rid LIMIT 1");
	$cur = (is_array($row)&&$row)?(string)($row[0]['status']??''):'';
	if ($cur==='APPROVED') {
		exeSql("START TRANSACTION");
		try {
			update_request_status($rid,'READY_FOR_CASHIER');
			if (table_exists('payment_actions')) {
				$actor = (int)($_SESSION['user_id'] ?? 1);
				$now = date('Y-m-d H:i:s');
				insData('payment_actions', [
					'request_id' => $rid,
					'action' => 'SEND_TO_CASHIER', // valid enum
					'actor_id' => $actor,
					'comment' => 'Forwarded to Cashier from Requester dashboard',
					'acted_at' => $now,
				]);
			}
			exeSql("COMMIT");
		} catch (\Throwable $e) {
			exeSql("ROLLBACK");
		}
	}
	// Redirect back to the correct tab/sub-status
	header("Location: dashboard?tab=".($_POST['tab'] ?? 'dashboard')."&sub=".($_POST['sub'] ?? 'approved')); exit;
}

/* lookups */
$_B=[]; $_V=[]; $_E=[];
function branch_name(?int $id): string {
	global $_B; if (!$id) return '';
	if (isset($_B[$id])) return $_B[$id];
	$name='';
	if (table_exists('branches')){
		$r = exeSql("SELECT branch_name FROM branches WHERE branch_id=$id LIMIT 1");
		if (is_array($r)&&$r) $name = (string)$r[0]['branch_name'];
	} elseif (table_exists('branch')) {
		$r = exeSql("SELECT branch_name FROM branch WHERE branch_id=$id LIMIT 1");
		if (is_array($r)&&$r) $name = (string)$r[0]['branch_name'];
	}
	$_B[$id]=$name; return $name;
}
function vendor_name(?int $id): string {
	global $_V; if (!$id) return '';
	if (isset($_V[$id])) return $_V[$id];
	$name='';
	if (table_exists('vendors')) {
		$r=exeSql("SELECT vendor_name FROM vendors WHERE vendor_id=$id LIMIT 1");
		if (is_array($r)&&$r) $name=(string)$r[0]['vendor_name'];
	} elseif (table_exists('vendor')) {
		$r=exeSql("SELECT vendor_name FROM vendor WHERE vendor_id=$id LIMIT 1");
		if (is_array($r)&&$r) $name=(string)$r[0]['vendor_name'];
	}
	$_V[$id]=$name; return $name;
}
function employee_info(?int $id): array {
	global $_E; if (!$id) return ['name'=>'','salary'=>0.0];
	if (isset($_E[$id])) return $_E[$id];
	$row=[];
	if (table_exists('employees')) {
		$r=exeSql("SELECT employee_name AS name, salary FROM employees WHERE id=$id LIMIT 1");
		if (is_array($r)&&$r) $row=$r[0];
	} elseif (table_exists('employee')) {
		$r=exeSql("SELECT employee_name AS name, salary FROM employee WHERE id=$id LIMIT 1");
		if (is_array($r)&&$r) $row=$r[0];
	}
	$_E[$id]=['name'=>$row['name']??'','salary'=>(float)($row['salary']??0)];
	return $_E[$id];
}

/* amounts */
function paynow_from_payload(array $r): float {
	$payload = json_decode($r['payload_json'] ?? '{}', true) ?: [];
	return (float)($payload['pay_now'] ?? 0);
}
function approved_amount(array $r): float {
	if (isset($r['approved_amount']) && $r['approved_amount']!=='') return (float)$r['approved_amount'];
	return paynow_from_payload($r) ?: (float)($r['total_amount'] ?? 0);
}
function vendor_grn_summary(array $grn_ids): array {
	$grn_ids = array_values(array_unique(array_map('intval',$grn_ids)));
	if (!$grn_ids) return ['grn_numbers'=>[], 'total'=>0.0, 'paid'=>0.0];
	$idCsv = implode(',', $grn_ids);
	$rows = exeSql("SELECT grn_id, grn_number, total_amount FROM goods_receipts WHERE grn_id IN ($idCsv)");
	if (!is_array($rows)) $rows = [];
	$numbers = []; $total = 0.0;
	foreach ($rows as $r){ $numbers[] = (string)($r['grn_number']??('#'.$r['grn_id'])); $total += (float)($r['total_amount']??0); }
	$paid = 0.0;
	if (has_col('goods_receipts','amount_received')) {
		$rows = exeSql("SELECT SUM(amount_received) AS s FROM goods_receipts WHERE grn_id IN ($idCsv)");
		if (is_array($rows)&&$rows) $paid = (float)($rows[0]['s']??0);
	} elseif (table_exists('vendor_grn_payments')) {
		$rows = exeSql("SELECT SUM(amount) AS s FROM vendor_grn_payments WHERE grn_id IN ($idCsv)");
		if (is_array($rows)&&$rows) $paid = (float)($rows[0]['s']??0);
	}
	return ['grn_numbers'=>$numbers, 'total'=>$total, 'paid'=>$paid];
}
function employee_period_paid(int $empId, string $period): float {
	$period = preg_replace('/[^0-9]/','',$period);
	if (!$empId || $period==='') return 0.0;
	if (!table_exists('employee_salary_payments')) return 0.0;
	$rows = exeSql("SELECT SUM(amount) AS s FROM employee_salary_payments WHERE employee_id=$empId AND pay_period='$period'");
	return (is_array($rows)&&$rows) ? (float)($rows[0]['s']??0) : 0.0;
}
function expense_summary(string $purpose): array {
	$purpose = addslashes($purpose);
	if (!table_exists('expenses')) return ['total'=>0.0,'paid'=>0.0];
	$cols = has_col('expenses','remaining_balance');
	$res = exeSql("SELECT amount, balance_paid".($cols?", remaining_balance":"")." FROM expenses WHERE purpose='$purpose' ORDER BY id DESC LIMIT 1");
	if (!is_array($res) || !$res) return ['total'=>0.0,'paid'=>0.0];
	$row = $res[0];
	$total=(float)($row['amount']??0); $paid=(float)($row['balance_paid']??0);
	return ['total'=>$total,'paid'=>$paid];
}

/**
 * NEW: Summary logic for fixed expenses.
 */
function fixed_expense_summary(string $expenseType): array {
    $expenseType = addslashes($expenseType);
    if (!table_exists('fixed_expenses')) return ['total'=>0.0,'paid'=>0.0];
    // NOTE: The fixed_expenses table uses 'amount' and 'balance_paid'.
    $res = exeSql("SELECT amount, balance_paid FROM fixed_expenses WHERE expense_type='$expenseType' ORDER BY id DESC LIMIT 1");
    if (!is_array($res) || !$res) return ['total'=>0.0,'paid'=>0.0];
    $row = $res[0];
    $total=(float)($row['amount']??0); 
    $paid=(float)($row['balance_paid']??0);
    return ['total'=>$total,'paid'=>$paid];
}

/* fetches */
function fetch_all(?string $type=null, ?string $status=null): array {
	$w=[]; if($type) $w[]="request_type='".addslashes($type)."'";
	if($status) $w[]="status='".addslashes($status)."'";
	$sql = "SELECT * FROM payment_requests ".($w?('WHERE '.implode(' AND ',$w)):'')." ORDER BY updated_at DESC, request_id DESC";
	$rows = exeSql($sql);
	return is_array($rows)?$rows:[];
}

function fetch_all_for_dashboard(?string $status=null): array {
	$w=[];
	if($status) $w[]="status='".addslashes($status)."'";
	$sql = "SELECT * FROM payment_requests ".($w?('WHERE '.implode(' AND ',$w)):'')." ORDER BY updated_at DESC, request_id DESC";
	$rows = exeSql($sql);
	return is_array($rows)?$rows:[];
}

function kpi_counts(array $rows): array {
	$k = ['SUBMITTED'=>0,'APPROVED'=>0,'READY_FOR_CASHIER'=>0,'PAID'=>0];
	foreach($rows as $r){ if(isset($k[$r['status']])) $k[$r['status']]++; }
	return $k;
}

/* progress messages */
function progress_msg(string $status): string {
	return match($status){
		'SUBMITTED' => 'Waiting for approval',
		'APPROVED' => 'Approved — ready to forward to Cashier',
		'READY_FOR_CASHIER' => 'With Cashier — disbursal pending',
		'PAID' => 'Paid — transaction completed',
		default => 'Status unavailable',
	};
}

/* datasets */
$active = $_GET['tab'] ?? 'dashboard';
$sub = strtolower((string)($_GET['sub'] ?? 'pending'));
$mapStatus = ['pending'=>'SUBMITTED','approved'=>'APPROVED','cashier'=>'READY_FOR_CASHIER','paid'=>'PAID'];
if (!isset($mapStatus[$sub])) $sub = 'pending';
$statusFilter = $mapStatus[$sub]; // The status filter for both dashboard and specific tabs

// *** MODIFIED LINE: Added 'fixed' ***
$types = ['vendor','employee','expenses','fixed'];
// *** END MODIFIED LINE ***

$allRows = fetch_all();
$allKPI = kpi_counts($allRows);

$data=[]; // Data filtered by type AND status for the dedicated tabs
foreach($types as $t){
	$typeRows = fetch_all($t);
	$data[$t] = [
		'SUBMITTED' => array_filter($typeRows, fn($r) => $r['status'] === 'SUBMITTED'),
		'APPROVED' => array_filter($typeRows, fn($r) => $r['status'] === 'APPROVED'),
		'READY_FOR_CASHIER' => array_filter($typeRows, fn($r) => $r['status'] === 'READY_FOR_CASHIER'),
		'PAID' => array_filter($typeRows, fn($r) => $r['status'] === 'PAID'),
	];
}

/* render helpers */
function badge(string $s): string {
	$map=['SUBMITTED'=>'primary','APPROVED'=>'success','READY_FOR_CASHIER'=>'info','PAID'=>'secondary'];
	$cls = $map[$s] ?? 'light';
	return '<span class="badge bg-'.$cls.'">'.h($s).'</span>';
}
function subActive(string $name, string $current): string {
	// This logic is used for all internal pill tabs
	return $name===strtolower($current) ? 'active' : '';
}
function getBadgeColor(string $status): string {
	return match($status){
		'SUBMITTED' => 'primary',
		'APPROVED' => 'success',
		'READY_FOR_CASHIER' => 'info',
		'PAID' => 'secondary',
		default => 'light',
	};
}
function getConsolidatedTitle(string $sub): string {
	return match($sub){
		'pending' => 'All Pending Requests (Awaiting Initial Approval)',
		'approved' => 'All Approved Requests (Ready to Forward to Cashier)',
		'cashier' => 'All Requests With Cashier (Disbursal Pending)',
		'paid' => 'All Completed Payments',
		default => 'All Requests',
	};
}

// Function to render the KPI cards for dedicated tabs (Vendor, Employee, Expense, Fixed)
function render_type_kpi_cards(array $kpis, string $type){
	$t = ucfirst($type);
	$pending_alert = (int)$kpis['SUBMITTED'] > 0 ? '<span class="text-danger ps-1" style="font-size:1.5em;line-height:0.5;vertical-align:middle;">&bullet;</span>' : '';
	?>
	<div class="row g-3 mb-4">
		<div class="col-md-3">
			<a class="text-decoration-none" href="dashboard?tab=<?=$type?>&sub=pending">
				<div class="p-3 kpi-card"><div>Pending <?=$t?><?=$pending_alert?></div><div class="value text-primary"><?= (int)$kpis['SUBMITTED'] ?></div></div>
			</a>
		</div>
		<div class="col-md-3">
			<a class="text-decoration-none" href="dashboard?tab=<?=$type?>&sub=approved">
				<div class="p-3 kpi-card"><div>Approved <?=$t?></div><div class="value text-success"><?= (int)$kpis['APPROVED'] ?></div></div>
			</a>
		</div>
		<div class="col-md-3">
			<a class="text-decoration-none" href="dashboard?tab=<?=$type?>&sub=cashier">
				<div class="p-3 kpi-card"><div><?=$t?> With Cashier</div><div class="value text-info"><?= (int)$kpis['READY_FOR_CASHIER'] ?></div></div>
			</a>
		</div>
		<div class="col-md-3">
			<a class="text-decoration-none" href="dashboard?tab=<?=$type?>&sub=paid">
				<div class="p-3 kpi-card"><div><?=$t?> Completed</div><div class="value text-secondary"><?= (int)$kpis['PAID'] ?></div></div>
			</a>
		</div>
	</div>
	<?php
}


function render_consolidated_table(array $rows, string $emptyMsg='No data.', string $currentTab='dashboard'){ ?>
	<div class="card shadow-sm border-0 mb-3"><div class="card-body p-2">
		<div class="table-responsive">
			<table class="table table-striped table-hover align-middle small">
				<thead class="table-light">
					<tr>
						<th style="display:none">#</th>
						<th>Type</th>
						<th>Details/Purpose</th>
						<th>Amount</th>
						<th>Status</th>
						<th>Progress</th>
						<th>Updated</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
				<?php if(!$rows): ?><tr><td colspan="8" class="text-muted"><?=h($emptyMsg)?></td></tr>
				<?php else: foreach($rows as $r):
					$type = (string)($r['request_type'] ?? 'unknown');
					$paidNow = approved_amount($r);
					$details = '';

					// Get specific details based on type
					$payload = json_decode($r['payload_json']??'{}',true) ?: [];
					if ($type === 'vendor') {
						$grn_ids = (array)($payload['grn_ids']??[]);
						$sum = vendor_grn_summary($grn_ids);
						$vend = vendor_name((int)($r['vendor_id'] ?? ($payload['vendor_id']??0)));
						$details = 'Vendor: '.h($vend).' (GRN: '.h(implode(', ', $sum['grn_numbers'])).')';
					} elseif ($type === 'employee') {
						$empId = (int)($r['employee_id'] ?? ($payload['employee_id']??0));
						$info = employee_info($empId);
						$period = (string)($payload['pay_period']??'');
						$details = 'Employee: '.h($info['name']).' (ID: '.$empId.($period ? ', Period: '.$period : '').')';
					} elseif ($type === 'expenses') {
						$purpose = (string)($payload['purpose']??'');
						if ($purpose==='_other_') $purpose = (string)($payload['custom_purpose']??'');
						$details = 'Purpose: '.h($purpose);
					}
                    // *** NEW CONSOLIDATED TABLE LOGIC FOR FIXED ***
                    elseif ($type === 'fixed') {
                        $purpose = (string)($payload['purpose']??'');
						if ($purpose==='_other_') $purpose = (string)($payload['custom_purpose']??'');
                        $details = 'Fixed Expense: ' . h(ucfirst($purpose));
                        $fixedId = (int)($payload['fixed_expense_id'] ?? 0);
                        if ($fixedId > 0) {
                            $details .= ' (FE ID: ' . $fixedId . ')';
                        }
                    }
                    // *** END NEW CONSOLIDATED TABLE LOGIC ***

				?>
					<tr title="<?= h(progress_msg($r['status'])) ?>">
						<td style="display:none">#<?= (int)$r['request_id'] ?></td>
						<td><span class="badge bg-secondary"><?= ucfirst($type) ?></span></td>
						<td><?= h($details) ?></td>
						<td>₹<?= number_format($paidNow,2) ?></td>
						<td><?= badge($r['status']) ?></td>
						<td><span class="text-muted"><?= h(progress_msg($r['status'])) ?></span></td>
						<td><?= h($r['updated_at']) ?></td>
						<td>
							<?php if ($r['status']==='SUBMITTED'): ?>
								<a class="btn btn-sm btn-outline-primary" href="payment?rid=<?=$r['request_id']?>">Edit</a>
								<small class="text-muted d-block">Waiting for approval</small>
							<?php elseif ($r['status']==='APPROVED'): ?>
								<form method="post" class="d-inline">
									<input type="hidden" name="action" value="send_cashier">
									<input type="hidden" name="request_id" value="<?=$r['request_id']?>">
									<input type="hidden" name="tab" value="<?=h($currentTab)?>">
									<input type="hidden" name="sub" value="<?=h($_GET['sub']??'approved')?>">
									<button class="btn btn-sm btn-primary">Send to Cashier</button>
								</form>
								<small class="text-muted d-block">Approved — forward to Cashier</small>
							<?php elseif ($r['status']==='READY_FOR_CASHIER'): ?>
								<span class="text-info">With Cashier</span>
								<small class="text-muted d-block">Disbursal pending</small>
							<?php else: ?>
								<span class="text-secondary">—</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
	</div></div>
<?php }

// Placeholder functions for existing render functions (unchanged)
function render_vendor_table_single(array $rows, string $emptyMsg='No data.'){
	ob_start(); 
?>
<div class="card shadow-sm border-0 mb-3"><div class="card-body p-2">
	<div class="table-responsive">
		<table class="table table-striped table-hover align-middle small">
			<thead class="table-light">
				<tr>
					<th style="display:none">#</th>
					<th>GRN No(s)</th>
					<th>Vendor</th>
					<th>Branch</th>
					<th>Total Amount</th>
					<th>Total Paid</th>
					<th>Paid Now</th>
					<th>Status</th>
					<th>Progress</th>
					<th>Updated</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
			<?php if(!$rows): ?><tr><td colspan="11" class="text-muted"><?=h($emptyMsg)?></td></tr>
			<?php else: foreach($rows as $r):
				$payload = json_decode($r['payload_json']??'{}',true) ?: [];
				$grn_ids = (array)($payload['grn_ids']??[]);
				$sum = vendor_grn_summary($grn_ids);
				$vend = vendor_name((int)($r['vendor_id'] ?? ($payload['vendor_id']??0)));
				$branch = branch_name((int)($r['branch_id'] ?? ($payload['branch_id']??0)));
				$paidNow = approved_amount($r);
				// Determine the correct 'tab' and 'sub' for the redirect in the action form
				$currentTab = $_GET['tab'] ?? 'vendor';
				$currentSub = $_GET['sub'] ?? 'approved';
			?>
				<tr title="<?= h(progress_msg($r['status'])) ?>">
					<td style="display:none">#<?= (int)$r['request_id'] ?></td>
					<td><?= h(implode(', ', $sum['grn_numbers'])) ?></td>
					<td><?= h($vend) ?></td>
					<td><?= h($branch) ?></td>
					<td>₹<?= number_format($sum['total'],2) ?></td>
					<td>₹<?= number_format($sum['paid'],2) ?></td>
					<td>₹<?= number_format($paidNow,2) ?></td>
					<td><?= badge($r['status']) ?></td>
					<td><span class="text-muted"><?= h(progress_msg($r['status'])) ?></span></td>
					<td><?= h($r['updated_at']) ?></td>
					<td>
						<?php if ($r['status']==='SUBMITTED'): ?>
							<a class="btn btn-sm btn-outline-primary" href="payment?rid=<?=$r['request_id']?>">Edit</a>
							<small class="text-muted d-block">Waiting for approval</small>
						<?php elseif ($r['status']==='APPROVED'): ?>
							<form method="post" class="d-inline">
								<input type="hidden" name="action" value="send_cashier">
								<input type="hidden" name="request_id" value="<?=$r['request_id']?>">
								<input type="hidden" name="tab" value="<?=h($currentTab)?>">
								<input type="hidden" name="sub" value="<?=h($currentSub)?>">
								<button class="btn btn-sm btn-primary">Send to Cashier</button>
							</form>
							<small class="text-muted d-block">Approved — forward to Cashier</small>
						<?php elseif ($r['status']==='READY_FOR_CASHIER'): ?>
							<span class="text-info">With Cashier</span>
							<small class="text-muted d-block">Disbursal pending</small>
						<?php else: ?>
							<span class="text-secondary">—</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
	</div>
</div></div>
<?php
	echo ob_get_clean();
}

function render_employee_table_single(array $rows, string $emptyMsg='No data.'){
	ob_start();
?>
<div class="card shadow-sm border-0 mb-3"><div class="card-body p-2">
	<div class="table-responsive">
		<table class="table table-striped table-hover align-middle small">
			<thead class="table-light">
				<tr>
					<th style="display:none">#</th>
					<th>Employee</th>
					<th>Employee ID</th>
					<th>Salary</th>
					<th>Total Paid (Period)</th>
					<th>Paid Now</th>
					<th>Status</th>
					<th>Progress</th>
					<th>Updated</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
			<?php if(!$rows): ?><tr><td colspan="10" class="text-muted"><?=h($emptyMsg)?></td></tr>
			<?php else: foreach($rows as $r):
				$p = json_decode($r['payload_json']??'{}',true)?:[];
				$empId = (int)($r['employee_id'] ?? ($p['employee_id']??0));
				$info = employee_info($empId);
				$period = (string)($p['pay_period']??'');
				$periodPaid = employee_period_paid($empId,$period);
				$paidNow = approved_amount($r);
				$currentTab = $_GET['tab'] ?? 'employee';
				$currentSub = $_GET['sub'] ?? 'approved';
			?>
				<tr title="<?= h(progress_msg($r['status'])) ?>">
					<td style="display:none">#<?= (int)$r['request_id'] ?></td>
					<td><?= h($info['name']) ?></td>
					<td><?= $empId ?></td>
					<td>₹<?= number_format((float)$info['salary'],2) ?></td>
					<td>₹<?= number_format($periodPaid,2) ?></td>
					<td>₹<?= number_format($paidNow,2) ?></td>
					<td><?= badge($r['status']) ?></td>
					<td><span class="text-muted"><?= h(progress_msg($r['status'])) ?></span></td>
					<td><?= h($r['updated_at']) ?></td>
					<td>
						<?php if ($r['status']==='SUBMITTED'): ?>
							<a class="btn btn-sm btn-outline-primary" href="payment?rid=<?=$r['request_id']?>">Edit</a>
							<small class="text-muted d-block">Waiting for approval</small>
						<?php elseif ($r['status']==='APPROVED'): ?>
							<form method="post" class="d-inline">
								<input type="hidden" name="action" value="send_cashier">
								<input type="hidden" name="request_id" value="<?=$r['request_id']?>">
								<input type="hidden" name="tab" value="<?=h($currentTab)?>">
								<input type="hidden" name="sub" value="<?=h($currentSub)?>">
								<button class="btn btn-sm btn-primary">Send to Cashier</button>
							</form>
							<small class="text-muted d-block">Approved — forward to Cashier</small>
						<?php elseif ($r['status']==='READY_FOR_CASHIER'): ?>
							<span class="text-info">With Cashier</span>
							<small class="text-muted d-block">Disbursal pending</small>
						<?php else: ?>
							<span class="text-secondary">—</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
	</div>
</div></div>
<?php
	echo ob_get_clean();
}

function render_expense_table_single(array $rows, string $emptyMsg='No data.'){
	ob_start();
?>
<div class="card shadow-sm border-0 mb-3"><div class="card-body p-2">
	<div class="table-responsive">
		<table class="table table-striped table-hover align-middle small">
			<thead class="table-light">
				<tr>
					<th style="display:none">#</th>
					<th>Purpose</th>
					<th>Total Amount</th>
					<th>Total Paid</th>
					<th>Paid Now</th>
					<th>Status</th>
					<th>Progress</th>
					<th>Updated</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
			<?php if(!$rows): ?><tr><td colspan="9" class="text-muted"><?=h($emptyMsg)?></td></tr>
			<?php else: foreach($rows as $r):
				$p = json_decode($r['payload_json']??'{}',true)?:[];
				$purpose = (string)($p['purpose']??'');
				if ($purpose==='_other_') $purpose = (string)($p['custom_purpose']??'');
				$sum = $purpose!=='' ? expense_summary($purpose) : ['total'=>0.0,'paid'=>0.0];
				$paidNow = approved_amount($r);
				$currentTab = $_GET['tab'] ?? 'expenses';
				$currentSub = $_GET['sub'] ?? 'approved';
			?>
				<tr title="<?= h(progress_msg($r['status'])) ?>">
					<td style="display:none">#<?= (int)$r['request_id'] ?></td>
					<td><?= h($purpose) ?></td>
					<td>₹<?= number_format($sum['total'],2) ?></td>
					<td>₹<?= number_format($sum['paid'],2) ?></td>
					<td>₹<?= number_format($paidNow,2) ?></td>
					<td><?= badge($r['status']) ?></td>
					<td><span class="text-muted"><?= h(progress_msg($r['status'])) ?></span></td>
					<td><?= h($r['updated_at']) ?></td>
					<td>
						<?php if ($r['status']==='SUBMITTED'): ?>
							<a class="btn btn-sm btn-outline-primary" href="payment?rid=<?=$r['request_id']?>">Edit</a>
							<small class="text-muted d-block">Waiting for approval</small>
						<?php elseif ($r['status']==='APPROVED'): ?>
							<form method="post" class="d-inline">
								<input type="hidden" name="action" value="send_cashier">
								<input type="hidden" name="request_id" value="<?=$r['request_id']?>">
								<input type="hidden" name="tab" value="<?=h($currentTab)?>">
								<input type="hidden" name="sub" value="<?=h($currentSub)?>">
								<button class="btn btn-sm btn-primary">Send to Cashier</button>
							</form>
							<small class="text-muted d-block">Approved — forward to Cashier</small>
						<?php elseif ($r['status']==='READY_FOR_CASHIER'): ?>
							<span class="text-info">With Cashier</span>
							<small class="text-muted d-block">Disbursal pending</small>
						<?php else: ?>
							<span class="text-secondary">—</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
	</div>
</div></div>
<?php
	echo ob_get_clean();
}

/**
 * NEW: Rendering function for Fixed Expenses.
 */
function render_fixed_table_single(array $rows, string $emptyMsg='No data.'){
	ob_start();
?>
<div class="card shadow-sm border-0 mb-3"><div class="card-body p-2">
	<div class="table-responsive">
		<table class="table table-striped table-hover align-middle small">
			<thead class="table-light">
				<tr>
					<th style="display:none">#</th>
					<th>Expense Type</th>
                    <th>FE ID</th>
					<th>Total Amount</th>
					<th>Total Paid</th>
					<th>Paid Now</th>
					<th>Status</th>
					<th>Progress</th>
					<th>Updated</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
			<?php if(!$rows): ?><tr><td colspan="10" class="text-muted"><?=h($emptyMsg)?></td></tr>
			<?php else: foreach($rows as $r):
				$p = json_decode($r['payload_json']??'{}',true)?:[];
				$purpose = (string)($p['purpose']??''); // Corresponds to expense_type in fixed_expenses table
				if ($purpose==='_other_') $purpose = (string)($p['custom_purpose']??'');
                $fixedId = (int)($p['fixed_expense_id'] ?? 0);
                
				$sum = $purpose!=='' ? fixed_expense_summary($purpose) : ['total'=>0.0,'paid'=>0.0];
				$paidNow = approved_amount($r);
				$currentTab = $_GET['tab'] ?? 'fixed';
				$currentSub = $_GET['sub'] ?? 'approved';
			?>
				<tr title="<?= h(progress_msg($r['status'])) ?>">
					<td style="display:none">#<?= (int)$r['request_id'] ?></td>
					<td><?= h(ucfirst($purpose)) ?></td>
                    <td><?= $fixedId ?: 'N/A' ?></td>
					<td>₹<?= number_format($sum['total'],2) ?></td>
					<td>₹<?= number_format($sum['paid'],2) ?></td>
					<td>₹<?= number_format($paidNow,2) ?></td>
					<td><?= badge($r['status']) ?></td>
					<td><span class="text-muted"><?= h(progress_msg($r['status'])) ?></span></td>
					<td><?= h($r['updated_at']) ?></td>
					<td>
						<?php if ($r['status']==='SUBMITTED'): ?>
							<a class="btn btn-sm btn-outline-primary" href="payment?rid=<?=$r['request_id']?>">Edit</a>
							<small class="text-muted d-block">Waiting for approval</small>
						<?php elseif ($r['status']==='APPROVED'): ?>
							<form method="post" class="d-inline">
								<input type="hidden" name="action" value="send_cashier">
								<input type="hidden" name="request_id" value="<?=$r['request_id']?>">
								<input type="hidden" name="tab" value="<?=h($currentTab)?>">
								<input type="hidden" name="sub" value="<?=h($currentSub)?>">
								<button class="btn btn-sm btn-primary">Send to Cashier</button>
							</form>
							<small class="text-muted d-block">Approved — forward to Cashier</small>
						<?php elseif ($r['status']==='READY_FOR_CASHIER'): ?>
							<span class="text-info">With Cashier</span>
							<small class="text-muted d-block">Disbursal pending</small>
						<?php else: ?>
							<span class="text-secondary">—</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
	</div>
</div></div>
<?php
	echo ob_get_clean();
}


/* -------------- HTML -------------- */
?>

<style>
	body{background:#fff}
	.page-title{display:flex;justify-content:space-between;align-items:center;margin:14px 0 18px}
	.kpi-card{border:1px solid #e5e7eb;border-radius:12px;background:#fafafa}
	.kpi-card .value{font-size:26px;font-weight:800}
	.flash{border-radius:10px;padding:10px 12px;margin-bottom:14px}
	.flash-success{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}
	.flash-info{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af}
	.flash-warn{background:#fff7ed;border:1px solid #fde68a;color:#92400e}
</style>


<header class="page-title">
	<h2 class="mb-0">Requester Dashboard</h2>
	<div class="d-flex gap-2">
		<a href="payments_view.php" class="btn btn-secondary">View Payment History</a>
		<a href="payment" class="btn btn-success">Go to Payment Entry</a>
	</div>
</header>

<?php if (isset($_GET['msg']) && $_GET['msg']==='submitted'): ?>
	<div class="flash flash-success">Request submitted successfully (Request ID: <strong><?= (int)($_GET['rid']??0) ?></strong>).</div>
<?php elseif (isset($_GET['msg']) && $_GET['msg']==='updated'): ?>
	<div class="flash flash-info">Request updated successfully (Request ID: <strong><?= (int)($_GET['rid']??0) ?></strong>).</div>
<?php elseif (isset($_GET['msg']) && $_GET['msg']==='locked'): ?>
	<div class="flash flash-warn">This request can no longer be edited because it is no longer pending (Request ID: <strong><?= (int)($_GET['rid']??0) ?></strong>).</div>
<?php elseif (isset($_GET['msg']) && $_GET['msg']==='paid_ok'): ?>
	<div class="flash flash-success">Payment recorded successfully.</div>
<?php endif; ?>

<?php
// Determine if there are any pending requests for the main navigation tab
$dashboard_pending_alert = (int)$allKPI['SUBMITTED'] > 0 ? '<span class="text-danger ps-1" style="font-size:1.5em;line-height:0.5;vertical-align:middle;">&bullet;</span>' : '';
$vendor_pending_count = count($data['vendor']['SUBMITTED']??[]);
$employee_pending_count = count($data['employee']['SUBMITTED']??[]);
$expenses_pending_count = count($data['expenses']['SUBMITTED']??[]);
// *** NEW: Fixed Expense Pending Count ***
$fixed_pending_count = count($data['fixed']['SUBMITTED']??[]);
// *** END NEW ***
$vendor_nav_alert = $vendor_pending_count > 0 ? '<span class="text-danger ps-1" style="font-size:1.5em;line-height:0.5;vertical-align:middle;">&bullet;</span>' : '';
$employee_nav_alert = $employee_pending_count > 0 ? '<span class="text-danger ps-1" style="font-size:1.5em;line-height:0.5;vertical-align:middle;">&bullet;</span>' : '';
$expenses_nav_alert = $expenses_pending_count > 0 ? '<span class="text-danger ps-1" style="font-size:1.5em;line-height:0.5;vertical-align:middle;">&bullet;</span>' : '';
// *** NEW: Fixed Expense Nav Alert ***
$fixed_nav_alert = $fixed_pending_count > 0 ? '<span class="text-danger ps-1" style="font-size:1.5em;line-height:0.5;vertical-align:middle;">&bullet;</span>' : '';
// *** END NEW ***
?>

<ul class="nav nav-tabs mb-3" role="tablist">
	<li class="nav-item"><a class="nav-link <?= $active==='dashboard'?'active':'' ?>" data-bs-toggle="tab" href="#tab-dashboard" role="tab">Dashboard<?= $dashboard_pending_alert ?></a></li>
	<li class="nav-item"><a class="nav-link <?= $active==='vendor'?'active':'' ?>" href="dashboard?tab=vendor&sub=pending" role="tab">Vendors<?= $vendor_nav_alert ?></a></li>
	<li class="nav-item"><a class="nav-link <?= $active==='employee'?'active':'' ?>" href="dashboard?tab=employee&sub=pending" role="tab">Employees<?= $employee_nav_alert ?></a></li>
	<li class="nav-item"><a class="nav-link <?= $active==='expenses'?'active':'' ?>" href="dashboard?tab=expenses&sub=pending" role="tab">Expenses<?= $expenses_nav_alert ?></a></li>
	<li class="nav-item"><a class="nav-link <?= $active==='fixed'?'active':'' ?>" href="dashboard?tab=fixed&sub=pending" role="tab">Fixed Expenses<?= $fixed_nav_alert ?></a></li>
</ul>

<div class="tab-content">
	<div class="tab-pane fade <?= $active==='dashboard'?'show active':'' ?>" id="tab-dashboard" role="tabpanel">
		<h4 class="mb-3">All Requests Overview (Total Counts)</h4>
		
		<div class="row g-3 mb-4">
			<div class="col-md-3">
				<?php $alert = (int)$allKPI['SUBMITTED'] > 0 ? '<span class="text-danger ps-1" style="font-size:1.5em;line-height:0.5;vertical-align:middle;">&bullet;</span>' : ''; ?>
				<a class="text-decoration-none" href="dashboard?tab=dashboard&sub=pending">
					<div class="p-3 kpi-card"><div>Pending (All)<?= $alert ?></div><div class="value text-primary"><?= (int)$allKPI['SUBMITTED'] ?></div></div>
				</a>
			</div>
			<div class="col-md-3">
				<a class="text-decoration-none" href="dashboard?tab=dashboard&sub=approved">
					<div class="p-3 kpi-card"><div>Approved (All)</div><div class="value text-success"><?= (int)$allKPI['APPROVED'] ?></div></div>
				</a>
			</div>
			<div class="col-md-3">
				<a class="text-decoration-none" href="dashboard?tab=dashboard&sub=cashier">
					<div class="p-3 kpi-card"><div>With Cashier (All)</div><div class="value text-info"><?= (int)$allKPI['READY_FOR_CASHIER'] ?></div></div>
				</a>
			</div>
			<div class="col-md-3">
				<a class="text-decoration-none" href="dashboard?tab=dashboard&sub=paid">
					<div class="p-3 kpi-card"><div>Payment Completed (All)</div><div class="value text-secondary"><?= (int)$allKPI['PAID'] ?></div></div>
				</a>
			</div>
		</div>
		
		<h4 class="mb-3 border-bottom pb-2">All Status Details</h4>

		<?php $pending_pill_alert = (int)$allKPI['SUBMITTED'] > 0 ? '<span class="text-danger ps-1" style="font-size:1.5em;line-height:0.5;vertical-align:middle;">&bullet;</span>' : ''; ?>

		<ul class="nav nav-pills mb-3">
			<li class="nav-item">
				<a class="nav-link <?= subActive('pending',$sub) ?> btn-outline-primary me-2" href="dashboard?tab=dashboard&sub=pending">
					Pending<?= $pending_pill_alert ?>
				</a>
			</li>
			<li class="nav-item">
				<a class="nav-link <?= subActive('approved',$sub) ?> btn-outline-success me-2" href="dashboard?tab=dashboard&sub=approved">
					Approved (Action Required)
				</a>
			</li>
			<li class="nav-item">
				<a class="nav-link <?= subActive('cashier',$sub) ?> btn-outline-info me-2" href="dashboard?tab=dashboard&sub=cashier">
					With Cashier
				</a>
			</li>
			<li class="nav-item">
				<a class="nav-link <?= subActive('paid',$sub) ?> btn-outline-secondary" href="dashboard?tab=dashboard&sub=paid">
					Payment Completed
				</a>
			</li>
		</ul>

		<h5 class="mb-3 text-<?= getBadgeColor($statusFilter) ?>"><?= getConsolidatedTitle($sub) ?></h5>
		<?php
			$rows = fetch_all_for_dashboard($statusFilter);
			$emptyMsg = 'No requests found for this status.';
			render_consolidated_table($rows, $emptyMsg, 'dashboard');
		?>
	</div>
	
	<div class="tab-pane fade <?= $active==='vendor'?'show active':'' ?>" id="tab-vendor" role="tabpanel">
		<h4 class="mb-3">Vendor Requests Dashboard</h4>
		
		<?php
			// Render KPI cards specific to 'vendor' requests
			$vendorKPIs = kpi_counts(fetch_all('vendor'));
			render_type_kpi_cards($vendorKPIs, 'vendor');
		?>

		<h4 class="mb-3 border-bottom pb-2">Vendor Status Details</h4>
		<?php $vendor_pending_pill_alert = $vendor_pending_count > 0 ? '<span class="text-danger ps-1" style="font-size:1.5em;line-height:0.5;vertical-align:middle;">&bullet;</span>' : ''; ?>
		<ul class="nav nav-pills mb-3">
			<li class="nav-item"><a class="nav-link <?= subActive('pending',$sub) ?>" href="dashboard?tab=vendor&sub=pending">Pending<?= $vendor_pending_pill_alert ?></a></li>
			<li class="nav-item"><a class="nav-link <?= subActive('approved',$sub) ?>" href="dashboard?tab=vendor&sub=approved">Approved (Action Required) </a></li>
			<li class="nav-item"><a class="nav-link <?= subActive('cashier',$sub) ?>" href="dashboard?tab=vendor&sub=cashier">With Cashier </a></li>
			<li class="nav-item"><a class="nav-link <?= subActive('paid',$sub) ?>" href="dashboard?tab=vendor&sub=paid">Payment Completed </a></li>
		</ul>
		<?php
			$status = $mapStatus[$sub];
			$rows = $data['vendor'][$status] ?? [];
			$empty = match($status){
				'SUBMITTED' => 'No pending vendor requests.',
				'APPROVED' => 'No approved vendor requests. Approved requests must be forwarded to the Cashier.',
				'READY_FOR_CASHIER' => 'No vendor requests with cashier.',
				'PAID' => 'No completed vendor payments.',
				default => 'No vendor data.'
			};
			render_vendor_table_single($rows, $empty);
		?>
	</div>

	<div class="tab-pane fade <?= $active==='employee'?'show active':'' ?>" id="tab-employee" role="tabpanel">
		<h4 class="mb-3">Employee Requests Dashboard</h4>

		<?php
			// Render KPI cards specific to 'employee' requests
			$employeeKPIs = kpi_counts(fetch_all('employee'));
			render_type_kpi_cards($employeeKPIs, 'employee');
		?>

		<h4 class="mb-3 border-bottom pb-2">Employee Status Details</h4>
		<?php $employee_pending_pill_alert = $employee_pending_count > 0 ? '<span class="text-danger ps-1" style="font-size:1.5em;line-height:0.5;vertical-align:middle;">&bullet;</span>' : ''; ?>
		<ul class="nav nav-pills mb-3">
			<li class="nav-item"><a class="nav-link <?= subActive('pending',$sub) ?>" href="dashboard?tab=employee&sub=pending">Pending<?= $employee_pending_pill_alert ?></a></li>
			<li class="nav-item"><a class="nav-link <?= subActive('approved',$sub) ?>" href="dashboard?tab=employee&sub=approved">Approved (Action Required) </a></li>
			<li class="nav-item"><a class="nav-link <?= subActive('cashier',$sub) ?>" href="dashboard?tab=employee&sub=cashier">With Cashier </a></li>
			<li class="nav-item"><a class="nav-link <?= subActive('paid',$sub) ?>" href="dashboard?tab=employee&sub=paid">Payment Completed </a></li>
		</ul>
		<?php
			$status = $mapStatus[$sub];
			$rows = $data['employee'][$status] ?? [];
			$empty = match($status){
				'SUBMITTED' => 'No pending employee requests.',
				'APPROVED' => 'No approved employee requests. Approved requests must be forwarded to the Cashier.',
				'READY_FOR_CASHIER' => 'No employee requests with cashier.',
				'PAID' => 'No completed employee payments.',
				default => 'No employee data.'
			};
			render_employee_table_single($rows, $empty);
		?>
	</div>

	<div class="tab-pane fade <?= $active==='expenses'?'show active':'' ?>" id="tab-expenses" role="tabpanel">
		<h4 class="mb-3">Expense Requests Dashboard</h4>

		<?php
			// Render KPI cards specific to 'expenses' requests
			$expenseKPIs = kpi_counts(fetch_all('expenses'));
			render_type_kpi_cards($expenseKPIs, 'expenses');
		?>

		<h4 class="mb-3 border-bottom pb-2">Expense Status Details</h4>
		<?php $expenses_pending_pill_alert = $expenses_pending_count > 0 ? '<span class="text-danger ps-1" style="font-size:1.5em;line-height:0.5;vertical-align:middle;">&bullet;</span>' : ''; ?>
		<ul class="nav nav-pills mb-3">
			<li class="nav-item"><a class="nav-link <?= subActive('pending',$sub) ?>" href="dashboard?tab=expenses&sub=pending">Pending<?= $expenses_pending_pill_alert ?></a></li>
			<li class="nav-item"><a class="nav-link <?= subActive('approved',$sub) ?>" href="dashboard?tab=expenses&sub=approved">Approved (Action Required) </a></li>
			<li class="nav-item"><a class="nav-link <?= subActive('cashier',$sub) ?>" href="dashboard?tab=expenses&sub=cashier">With Cashier </a></li>
			<li class="nav-item"><a class="nav-link <?= subActive('paid',$sub) ?>" href="dashboard?tab=expenses&sub=paid">Payment Completed </a></li>
		</ul>
		<?php
			$status = $mapStatus[$sub];
			$rows = $data['expenses'][$status] ?? [];
			$empty = match($status){
				'SUBMITTED' => 'No pending expense requests.',
				'APPROVED' => 'No approved expense requests. Approved requests must be forwarded to the Cashier.',
				'READY_FOR_CASHIER' => 'No expense requests with cashier.',
				'PAID' => 'No completed expense payments.',
				default => 'No expense data.'
			};
			render_expense_table_single($rows, $empty);
		?>
	</div>
    
    <div class="tab-pane fade <?= $active==='fixed'?'show active':'' ?>" id="tab-fixed" role="tabpanel">
		<h4 class="mb-3">Fixed Expense Requests Dashboard</h4>

		<?php
			// Render KPI cards specific to 'fixed' requests
			$fixedKPIs = kpi_counts(fetch_all('fixed'));
			render_type_kpi_cards($fixedKPIs, 'fixed');
		?>

		<h4 class="mb-3 border-bottom pb-2">Fixed Expense Status Details</h4>
		<?php $fixed_pending_pill_alert = $fixed_pending_count > 0 ? '<span class="text-danger ps-1" style="font-size:1.5em;line-height:0.5;vertical-align:middle;">&bullet;</span>' : ''; ?>
		<ul class="nav nav-pills mb-3">
			<li class="nav-item"><a class="nav-link <?= subActive('pending',$sub) ?>" href="dashboard?tab=fixed&sub=pending">Pending<?= $fixed_pending_pill_alert ?></a></li>
			<li class="nav-item"><a class="nav-link <?= subActive('approved',$sub) ?>" href="dashboard?tab=fixed&sub=approved">Approved (Action Required) </a></li>
			<li class="nav-item"><a class="nav-link <?= subActive('cashier',$sub) ?>" href="dashboard?tab=fixed&sub=cashier">With Cashier </a></li>
			<li class="nav-item"><a class="nav-link <?= subActive('paid',$sub) ?>" href="dashboard?tab=fixed&sub=paid">Payment Completed </a></li>
		</ul>
		<?php
			$status = $mapStatus[$sub];
			$rows = $data['fixed'][$status] ?? [];
			$empty = match($status){
				'SUBMITTED' => 'No pending fixed expense requests.',
				'APPROVED' => 'No approved fixed expense requests. Approved requests must be forwarded to the Cashier.',
				'READY_FOR_CASHIER' => 'No fixed expense requests with cashier.',
				'PAID' => 'No completed fixed expense payments.',
				default => 'No fixed expense data.'
			};
			render_fixed_table_single($rows, $empty);
		?>
	</div>
    </div>

<?php
require_once("footer.php");
?>