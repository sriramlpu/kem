<?php
/**
 * APPROVER: Review / Approval Form Page.
 * Fixed: Extremely robust ID extraction to prevent "Invalid Request" error.
 */
session_start();
require_once("../auth.php");
requireRole(['Approver','Admin']);
require_once("../functions.php");

$userName = ($_SESSION['userName'] ?? 'Approver');

/* ---------- Helpers ---------- */
if (!function_exists('h')) { function h($x) { return htmlspecialchars((string)$x, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
function i($x) { return is_numeric($x) ? (int)$x : 0; }
function s($x) { return trim((string)$x); }

/* ---------- ID EXTRACTION (ROBUST) ---------- */
$rid = i($_GET['rid'] ?? $_POST['request_id'] ?? $_REQUEST['rid'] ?? $_REQUEST['request_id'] ?? 0);

function fetch_paid_amount(array $req): float {
    $type = $req['request_type'] ?? '';
    $payload = json_decode($req['payload_json'] ?? '{}', true) ?: [];
    $paid = 0.00;
    
    if ($type === 'employee') {
        $r = exeSql("SELECT SUM(amount) AS s FROM employee_salary_payments WHERE employee_id = " . i($req['employee_id']));
        $paid = (float)($r[0]['s'] ?? 0);
    } elseif ($type === 'fixed') {
        $r = exeSql("SELECT balance_paid FROM fixed_expenses WHERE id = " . i($payload['fixed_id'] ?? 0));
        $paid = (float)($r[0]['balance_paid'] ?? 0);
    } elseif ($type === 'vendor') {
        $grn_ids = array_filter(array_map('i', (array)($payload['grn_ids'] ?? [])));
        if ($grn_ids) {
            $r = exeSql("SELECT SUM(amount + advance_used) AS s FROM vendor_grn_payments WHERE grn_id IN (" . implode(',',$grn_ids) . ")");
            $paid = (float)($r[0]['s'] ?? 0);
        }
    }
    return $paid;
}

function username_lookup(int $id): string {
    $r = exeSql("SELECT username FROM users WHERE user_id = $id LIMIT 1");
    return (string)($r[0]['username'] ?? 'User#' . $id);
}

/* ---------- Form Handler ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $row = exeSql("SELECT * FROM payment_requests WHERE request_id=$rid LIMIT 1");
    $req = $row ? $row[0] : null;
    if (!$req) exit("Critical Error: Request #$rid not found.");

    $payload = json_decode($req['payload_json'] ?? '{}', true) ?: [];
    $payload['pay_now'] = (float)s($_POST['pay_now'] ?? '0');
    $payload['notes'] = s($_POST['notes'] ?? '');
    
    $action = s($_POST['wf_action'] ?? 'save');
    $now = date('Y-m-d H:i:s');
    
    if ($action==='approve') {
        $up = [
            'status' => 'APPROVED',
            'approved_by' => (int)$_SESSION['userId'],
            'approved_at' => $now,
            'payload_json' => json_encode($payload)
        ];
    } elseif ($action==='reject') {
        $up = ['status' => 'RETURNED', 'payload_json' => json_encode($payload)];
    } else {
        $up = ['payload_json' => json_encode($payload)];
    }

    $up['updated_at'] = $now;
    upData('payment_requests', $up, ["request_id=$rid"]);

    header("Location: appdashboard?tab=".strtolower($req['request_type'])."&msg=$action");
    exit;
}

/* ---------- Page Load Fetch ---------- */
$row = exeSql("SELECT * FROM payment_requests WHERE request_id=$rid LIMIT 1");
$req = $row ? $row[0] : null;

if (!$req) {
    echo "<div style='font-family:sans-serif; text-align:center; padding:100px; background:#f4f7f6; height:100vh;'>";
    echo "<div style='background:white; padding:50px; display:inline-block; border-radius:20px; box-shadow:0 15px 35px rgba(0,0,0,0.05); text-align:left;'>";
    echo "<h3 style='color:#e74c3c; font-weight:800;'>Invalid Request</h3>";
    echo "<p style='color:#7f8c8d;'>Attempted to load ID: <strong>$rid</strong>. This record does not exist in the payment_requests table.</p>";
    echo "<a href='appdashboard' style='text-decoration:none; background:#0d6efd; color:white; padding:12px 30px; border-radius:30px; font-weight:bold; display:inline-block; margin-top:15px;'>Return to Dashboard</a>";
    echo "</div></div>";
    exit;
}

$payload = json_decode($req['payload_json'] ?? '{}', true) ?: [];
$initial_pay_now = (float)($payload['pay_now'] ?? $req['total_amount']);
$total_paid = fetch_paid_amount($req);
$requester_name = username_lookup(i($req['requested_by']));

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Verification: Request #<?= $rid ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7f6; font-family: 'Segoe UI', system-ui, sans-serif; }
        .navbar { background: #fff !important; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .card { border-radius: 20px; border: 0; box-shadow: 0 15px 35px rgba(0,0,0,0.05); overflow: hidden; }
        .info-label { font-size: 0.65rem; font-weight: 800; color: #95a5a6; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; display: block; }
        .info-value { font-size: 1.1rem; font-weight: 700; color: #2c3e50; }
        .status-pill { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; padding: 6px 16px; border-radius: 30px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light mb-5">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold text-primary" href="appdashboard">KMK FINANCE</a>
        <div class="d-flex align-items-center">
            <span class="me-3 text-muted small fw-bold">Verification: <?= h($userName) ?></span>
            <a href="appdashboard" class="btn btn-sm btn-outline-secondary rounded-pill px-3 shadow-sm">Dashboard</a>
        </div>
    </div>
</nav>

<div class="container py-2 pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0 text-dark fw-bold h4">Decision Desk: Request #<?= $rid ?></h2>
        <a href="appdashboard?tab=<?= strtolower($req['request_type']) ?>" class="btn btn-light rounded-pill border shadow-sm px-4 fw-bold">⬅ Back</a>
    </div>

    <form method="post">
        <input type="hidden" name="request_id" value="<?=$rid?>">

        <div class="card shadow-lg border-0">
            <div class="card-header bg-primary text-white py-4 px-4 d-flex justify-content-between align-items-center border-0">
                <div>
                    <h5 class="mb-0 fw-bold"><?=h(ucfirst($req['request_type'] ?? 'Payment'))?> Processing</h5>
                    <small class="opacity-75 italic">Verified by <?= h($userName) ?></small>
                </div>
                <span class="status-pill bg-white text-primary fw-bold"><?= h($req['status'] ?? 'Pending') ?></span>
            </div>
            <div class="card-body p-4 bg-white">
                
                <div class="row g-4 mb-4 border-bottom pb-4 text-center text-md-start">
                    <div class="col-md-4">
                        <label class="info-label">Submitted By</label>
                        <div class="info-value"><?=h($requester_name)?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="info-label">Net Requested</label>
                        <div class="info-value text-primary h4 mb-0">₹<?=number_format((float)($req['total_amount']??0),2)?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="info-label">Already Disbursed</label>
                        <div class="info-value text-success">₹<?=number_format($total_paid,2)?></div>
                    </div>
                </div>

                <!-- Breakdown for Employees -->
                <?php if($req['request_type'] === 'employee'): ?>
                    <div class="p-4 bg-light rounded-4 mb-4 border border-info-subtle">
                        <h6 class="fw-bold text-info text-uppercase small mb-3 letter-spacing-1 border-bottom pb-2">Salary Calculation Details</h6>
                        <div class="row g-3 text-center">
                            <div class="col-md-3">
                                <label class="small text-muted mb-1 d-block">Gross Salary</label>
                                <div class="fw-bold text-dark">₹<?= number_format((float)($payload['gross_salary'] ?? 0), 2) ?></div>
                            </div>
                            <div class="col-md-3 border-start">
                                <label class="small text-muted mb-1 d-block">LOP Deductions</label>
                                <div class="fw-bold text-danger">₹<?= number_format((float)($payload['lop_amount'] ?? 0), 2) ?></div>
                            </div>
                            <div class="col-md-3 border-start">
                                <label class="small text-muted mb-1 d-block">PF Contribution</label>
                                <div class="fw-bold text-dark">₹<?= number_format((float)($payload['pf_deduction'] ?? 0), 2) ?></div>
                            </div>
                            <div class="col-md-3 border-start">
                                <label class="small text-muted mb-1 d-block">ESI Contribution</label>
                                <div class="fw-bold text-dark">₹<?= number_format((float)($payload['esi_deduction'] ?? 0), 2) ?></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Decision Panel -->
                <div class="row g-4 pt-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark h6">Approved Disbursal Amount</label>
                        <div class="input-group input-group-lg shadow-sm">
                            <span class="input-group-text bg-light border-2 text-muted fw-bold">₹</span>
                            <input type="number" step="0.01" name="pay_now" class="form-control border-2 fw-bold text-primary" value="<?=h((string)$initial_pay_now)?>" required>
                        </div>
                        <p class="text-muted small mt-3 italic">** You can lower this amount for partial approvals.</p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-dark h6">Decision Comments / Notes</label>
                        <textarea name="notes" class="form-control border shadow-sm" rows="3" placeholder="Explain your decision or provide instructions for the cashier..."><?=h($payload['notes']??'')?></textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-light p-5 border-0">
                <div class="d-flex flex-column flex-md-row justify-content-center gap-4">
                    <button type="submit" name="wf_action" value="approve" class="btn btn-success btn-lg px-5 rounded-pill shadow-lg fw-bold border-0 hvr-grow">
                        Approve Payment
                    </button>
                    <button type="submit" name="wf_action" value="reject" class="btn btn-danger btn-lg px-5 rounded-pill shadow-lg fw-bold border-0 hvr-grow">
                        Return / Reject
                    </button>
                    <button type="submit" name="wf_action" value="save" class="btn btn-outline-secondary btn-lg px-5 rounded-pill fw-bold">
                        Save Modification
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>