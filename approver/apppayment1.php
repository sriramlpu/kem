<?php
/**
 * APPROVER: Approval / Rejection / Save Form Page
 * This page handles the form submission for a single request ID (rid).
 * Path: kmk/finance/approver/payment.php
 */
require_once("../auth.php");
requireRole(['Approver','Admin']);

require __DIR__ . '/../functions.php';

/* ---------- mini helpers (Required for form processing) ---------- */
function v($k,$d=null){return $_POST[$k]??$_GET[$k]??$d;}
function i($x){return is_numeric($x)?(int)$x:0;}
function s($x){return trim((string)($x??''));}
function h($x){return htmlspecialchars((string)$x,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function uid(): int { return isset($_SESSION['userId'])?(int)$_SESSION['userId']:1; }

function table_exists(string $t): bool {
    $t = preg_replace('/[^a-zA-Z0-9_]/','',$t);
    $rows = exeSql("SHOW TABLES LIKE '$t'");
    return is_array($rows) && $rows;
}
function has_column(string $table, string $col): bool {
    $t = preg_replace('/[^a-zA-Z0-9_]/','',$table);
    $c = preg_replace('/[^a-zA-Z0-9_]/','',$col);
    $rows = exeSql("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1");
    return is_array($rows) && count($rows) > 0;
}
function sql_update_row(string $table, array $fields, string $where, int $limit = 1): void {
    $table = preg_replace('/[^a-zA-Z0-9_]/','',$table);
    $sets = [];
    foreach ($fields as $k => $v) {
        $col = preg_replace('/[^a-zA-Z0-9_]/','',$k);
        if ($v === null) {
            $sets[] = "$col=NULL";
        } else {
            $val = addslashes((string)$v);
            $sets[] = "$col='$val'";
        }
    }
    $w = trim($where);
    if ($w === '') { throw new RuntimeException('Refusing to UPDATE without WHERE'); }
    $sql = "UPDATE $table SET ".implode(', ',$sets)." WHERE $w".($limit ? " LIMIT $limit" : "");
    exeSql($sql);
}
function log_action(int $rid, string $action): void {
    if (!table_exists('payment_actions')) return;
    $payload = json_encode(['by'=>uid()], JSON_UNESCAPED_SLASHES);
    $sql = "INSERT INTO payment_actions (request_id, action, actor_id, comment, diff_json, acted_at) 
            VALUES ($rid, '".addslashes($action)."', ".uid().", NULL, '$payload', '".date('Y-m-d H:i:s')."')";
    exeSql($sql);
}
function update_request(int $rid, array $fields, string $actionNote): void {
    $fields['updated_at'] = date('Y-m-d H:i:s');
    sql_update_row('payment_requests', $fields, "request_id=$rid", 1);
    log_action($rid, $actionNote);
}
function get_request(int $rid){
    $rows = exeSql("SELECT * FROM payment_requests WHERE request_id=$rid LIMIT 1");
    return (is_array($rows) && $rows) ? $rows[0] : null;
}
function fetch_expense_purposes(): array {
    $rows = exeSql("SELECT DISTINCT purpose FROM expenses WHERE purpose IS NOT NULL AND TRIM(purpose)<>'' ORDER BY purpose");
    $out = [];
    if (is_array($rows)) {
        foreach ($rows as $r) if (isset($r['purpose'])) $out[] = (string)$r['purpose'];
    }
    return $out ?: ['Office Supplies','Travel','Meals','Utilities','General Expense'];
}
function badge(string $s): string {
    $map=['SUBMITTED'=>'primary','APPROVED'=>'success','RETURNED'=>'danger','PAID'=>'secondary'];
    $cls = $map[$s] ?? 'light';
    return '<span class="badge bg-'.$cls.'">'.h($s).'</span>';
}

/**
 * Fetches the total amount already paid for the entity in the request.
 */
function fetch_paid_amount(array $req): float {
    $type = $req['request_type'] ?? '';
    $payload = json_decode($req['payload_json'] ?? '{}', true) ?: [];
    
    $paid_amount = 0.00;
    $sql = '';

    switch ($type) {
        case 'expenses':
            $paid_amount = 0.00; 
            break;

        case 'employee':
            $employee_id = i($req['employee_id'] ?? 0);
            if ($employee_id > 0) {
                $sql = "SELECT SUM(amount) AS total FROM employee_salary_payments WHERE employee_id = $employee_id";
            }
            break;

        case 'fixed':
            $fixed_id = i($payload['fixed_id'] ?? 0);
            if ($fixed_id > 0) {
                $sql = "SELECT balance_paid AS total FROM fixed_expenses WHERE id = $fixed_id LIMIT 1";
            }
            break;

        case 'vendor':
            $grn_ids = array_map('i', (array)($payload['grn_ids'] ?? []));
            $valid_grn_ids = array_filter($grn_ids);
            
            if (!empty($valid_grn_ids)) {
                $grn_list = implode(',', $valid_grn_ids);
                $sql = "SELECT SUM(amount + advance_used) AS total FROM vendor_grn_payments WHERE grn_id IN ($grn_list)";
            } else {
                $vendor_id = i($req['vendor_id'] ?? 0);
                if ($vendor_id > 0) {
                    $sql = "SELECT total_paid AS total FROM vendor_totals WHERE vendor_id = $vendor_id LIMIT 1";
                }
            }
            break;
    }

    if ($sql) {
        $rows = exeSql($sql);
        if (is_array($rows) && $rows && isset($rows[0]['total'])) {
            $paid_amount = (float)($rows[0]['total'] ?? 0.00);
        }
    }

    return $paid_amount;
}

/**
 * Helper function to fetch requester's username
 */
function get_requester_name(int $user_id): string {
    if ($user_id <= 0) return 'Unknown User';
    $rows = exeSql("SELECT username FROM users WHERE user_id = $user_id LIMIT 1");
    return (is_array($rows) && $rows) ? $rows[0]['username'] : "User #$user_id";
}

/**
 * HELPER FUNCTIONS TO FETCH NAMES
 */
function get_vendor_name(int $vendor_id): string {
    if ($vendor_id <= 0) return 'N/A';
    $rows = exeSql("SELECT vendor_name FROM vendors WHERE vendor_id = $vendor_id LIMIT 1");
    return (is_array($rows) && $rows) ? $rows[0]['vendor_name'] : "Vendor #$vendor_id";
}

function get_branch_name(int $branch_id): string {
    if ($branch_id <= 0) return 'N/A';
    $rows = exeSql("SELECT branch_name FROM branches WHERE branch_id = $branch_id LIMIT 1");
    return (is_array($rows) && $rows) ? $rows[0]['branch_name'] : "Branch #$branch_id";
}

function get_grn_numbers(array $grn_ids): string {
    if (empty($grn_ids)) return 'N/A';
    $valid_ids = array_filter(array_map('i', $grn_ids));
    if (empty($valid_ids)) return 'N/A';
    
    $grn_list = implode(',', $valid_ids);
    $rows = exeSql("SELECT grn_number FROM goods_receipts WHERE grn_id IN ($grn_list) ORDER BY grn_id");
    
    if (is_array($rows) && $rows) {
        $numbers = array_column($rows, 'grn_number');
        return implode(', ', $numbers);
    }
    return implode(', ', $valid_ids); // Fallback to IDs
}

function get_employee_details(int $employee_id): array {
    if ($employee_id <= 0) return ['name' => 'N/A', 'salary' => 'N/A'];
    
    $rows = exeSql("SELECT employee_name, salary FROM employees WHERE id = $employee_id LIMIT 1");
    if (is_array($rows) && $rows) {
        return [
            'name' => $rows[0]['employee_name'],
            'salary' => number_format((float)$rows[0]['salary'], 2)
        ];
    }
    return ['name' => "Employee #$employee_id", 'salary' => 'N/A'];
}

function get_fixed_expense_details(int $fixed_id): array {
    if ($fixed_id <= 0) return ['type' => 'N/A', 'amount' => 'N/A'];
    
    $rows = exeSql("SELECT expense_type, amount FROM fixed_expenses WHERE id = $fixed_id LIMIT 1");
    if (is_array($rows) && $rows) {
        return [
            'type' => ucfirst(str_replace('_', ' ', $rows[0]['expense_type'])),
            'amount' => number_format((float)$rows[0]['amount'], 2)
        ];
    }
    return ['type' => "Fixed Expense #$fixed_id", 'amount' => 'N/A'];
}
/* ---------- End helper functions ---------- */


/* ---------- Form Actions ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $rid = i(v('request_id',0));
    $req = get_request($rid);
    
    if (!$req) { 
        header("Location: dashboard?msg=invalid_request_post");
        exit;
    }

    $payload = json_decode($req['payload_json'] ?? '{}', true) ?: [];
    
    // Update payload fields from form submission
    $payload['pay_now'] = (float)s(v('pay_now','0'));
    $payload['notes'] = s(v('notes',''));

    if ($req['request_type']==='expenses'){
        $payload['purpose'] = s(v('purpose', $payload['purpose'] ?? ''));
        $payload['custom_purpose'] = s(v('custom_purpose',''));
        $payload['custom_total'] = (float)s(v('custom_total','0'));
    }

    // Determine Action
    $action = s(v('wf_action','save'));
    
    if ($action==='approve') {
        $fields = [
            'status' => 'APPROVED',
            'approved_by' => uid(),
            'approved_at' => date('Y-m-d H:i:s'),
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
        ];
        if (has_column('payment_requests','approved_amount')) {
            $fields['approved_amount'] = (float)($payload['pay_now'] ?? 0);
        }
        update_request($rid, $fields, 'APPROVE');

    } elseif ($action==='reject') {
        update_request($rid, [
            'status' => 'RETURNED',
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
        ], 'RETURN');

    } else { // 'save'
        update_request($rid, [
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
        ], 'EDIT');
    }

    header("Location: dashboard?tab=".strtolower($req['request_type'])."&msg=$action");
    exit;
}

/* ---------- Page Data ---------- */
$rid = i(v('rid',0));
$req = get_request($rid);

if (!$req) {
    header("Location: dashboard?msg=request_not_found"); 
    exit;
}

$payload = json_decode($req['payload_json'] ?? '{}', true) ?: [];
$purposes = fetch_expense_purposes();
$initial_pay_now = (float)($payload['pay_now'] ?? $req['total_amount']);
$total_paid = fetch_paid_amount($req);

// Fetch requester name - THIS IS THE KEY FIX
$requester_name = get_requester_name(i($req['requested_by'] ?? 0));

// Fetch names based on request type
$vendor_name = '';
$branch_name = '';
$grn_numbers = '';
$employee_details = ['name' => 'N/A', 'salary' => 'N/A'];
$fixed_details = ['type' => 'N/A', 'amount' => 'N/A'];

if ($req['request_type'] === 'vendor') {
    $vendor_name = get_vendor_name(i($payload['vendor_id'] ?? 0));
    $branch_name = get_branch_name(i($payload['branch_id'] ?? 0));
    $grn_numbers = get_grn_numbers((array)($payload['grn_ids'] ?? []));
}

if ($req['request_type'] === 'employee') {
    $employee_details = get_employee_details(i($req['employee_id'] ?? 0));
}

if ($req['request_type'] === 'fixed') {
    $fixed_details = get_fixed_expense_details(i($payload['fixed_id'] ?? 0));
}

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Approver — Review Request #<?= $rid ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body{background:#f8f9fa}
  .page-title{display:flex;justify-content:space-between;align-items:center;margin:14px 0 18px}
  .info-label{font-weight:600;color:#495057;font-size:0.875rem;text-transform:uppercase;letter-spacing:0.5px}
  .info-value{font-size:1rem;color:#212529;padding:0.5rem;background:#f8f9fa;border-radius:0.25rem}
</style>
</head>
<body class="container-md py-4">

<header class="page-title">
  <h2 class="mb-0">Review Request #<?= $rid ?></h2>
  <a href="dashboard?tab=<?= strtolower($req['request_type']) ?>" class="btn btn-outline-secondary">Back to Dashboard</a>
</header>

<form method="post">
  <input type="hidden" name="request_id" value="<?=$req['request_id']?>">

  <div class="card shadow-sm mb-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><?=h(ucfirst($req['request_type']))?> Payment Request</h5>
      <span class="fs-5"><?= badge($req['status']) ?></span>
    </div>
    <div class="card-body">
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <label class="info-label">Requested By</label>
          <div class="info-value"><?=h($requester_name)?></div>
        </div>
        <div class="col-md-4">
          <label class="info-label">Total Amount (Original)</label>
          <div class="info-value">₹<?=number_format((float)($req['total_amount']??0),2)?></div>
        </div>
        <div class="col-md-4">
          <label class="info-label">Total Paid (Historical)</label>
          <div class="info-value text-success fw-bold">₹<?=number_format($total_paid,2)?></div>
        </div>
      </div>

      <?php if($req['request_type']==='vendor'): ?>
        <h6 class="border-bottom pb-2 mb-3 text-primary">Vendor Payment Details</h6>
        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <label class="info-label">Vendor Name</label>
            <div class="info-value"><?=h($vendor_name)?></div>
          </div>
          <div class="col-md-4">
            <label class="info-label">Branch</label>
            <div class="info-value"><?=h($branch_name)?></div>
          </div>
          <div class="col-md-4">
            <label class="info-label">GRN Numbers</label>
            <div class="info-value" style="font-size:0.875rem"><?=h($grn_numbers)?></div>
          </div>
        </div>
      <?php endif; ?>

      <?php if($req['request_type']==='employee'): ?>
        <h6 class="border-bottom pb-2 mb-3 text-primary">Employee Salary Details</h6>
        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <label class="info-label">Employee Name</label>
            <div class="info-value"><?=h($employee_details['name'])?></div>
          </div>
          <div class="col-md-4">
            <label class="info-label">Monthly Salary</label>
            <div class="info-value">₹<?=h($employee_details['salary'])?></div>
          </div>
          <div class="col-md-4">
            <label class="info-label">Total Salary Paid</label>
            <div class="info-value text-success fw-bold">₹<?=number_format($total_paid,2)?></div>
          </div>
        </div>
      <?php endif; ?>

      <?php if($req['request_type']==='fixed'): ?>
        <h6 class="border-bottom pb-2 mb-3 text-primary">Fixed Expense Details</h6>
        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <label class="info-label">Expense Type</label>
            <div class="info-value"><?=h($fixed_details['type'])?></div>
          </div>
          <div class="col-md-4">
            <label class="info-label">Total Amount</label>
            <div class="info-value">₹<?=h($fixed_details['amount'])?></div>
          </div>
          <div class="col-md-4">
            <label class="info-label">Total Paid</label>
            <div class="info-value text-success fw-bold">₹<?=number_format($total_paid,2)?></div>
          </div>
        </div>
      <?php endif; ?>

      <?php if($req['request_type']==='expenses'): ?>
        <h6 class="border-bottom pb-2 mb-3 text-primary">Expense Details (Editable)</h6>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Purpose</label>
            <select name="purpose" class="form-select">
              <option value="">-- Select purpose --</option>
              <?php foreach ($purposes as $p): ?>
                <option value="<?=h($p)?>" <?=(($payload['purpose']??'')===$p)?'selected':''?>><?=h($p)?></option>
              <?php endforeach; ?>
              <option value="_other" <?=(($payload['purpose']??'')==='other_')?'selected':''?>>Other…</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">New Purpose (if 'Other')</label>
            <input type="text" name="custom_purpose" class="form-control" value="<?=h($payload['custom_purpose']??'')?>">
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Total Amount (New Purpose)</label>
            <input type="number" step="0.01" name="custom_total" class="form-control" value="<?=h((string)($payload['custom_total']??''))?>">
          </div>
          <div class="col-md-6">
            <label class="info-label">Total Paid</label>
            <div class="info-value text-muted">Not Tracked per Request</div>
          </div>
        </div>
      <?php endif; ?>

      <h6 class="border-bottom pb-2 mb-3 mt-4 text-success">Approval Action</h6>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-bold">Amount to Pay Now (Editable)</label>
          <input type="number" step="0.01" name="pay_now" class="form-control form-control-lg" value="<?=h((string)$initial_pay_now)?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Approver Notes</label>
          <input type="text" name="notes" class="form-control form-control-lg" value="<?=h($payload['notes']??'')?>" placeholder="Add any notes or comments">
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-center gap-3 mt-4 mb-5">
    <button type="submit" name="wf_action" value="approve" class="btn btn-success btn-lg px-5">
      <i class="bi bi-check-circle"></i> Approve
    </button>
    <button type="submit" name="wf_action" value="reject" class="btn btn-danger btn-lg px-5">
      <i class="bi bi-x-circle"></i> Return / Reject
    </button>
    <button type="submit" name="wf_action" value="save" class="btn btn-secondary btn-lg px-5">
      <i class="bi bi-save"></i> Save Edits
    </button>
  </div>
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>