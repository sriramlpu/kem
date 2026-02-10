<?php
/**
 * APPROVER: Review / Approval / Modification Form.
 * Path: apppayment.php
 * UPDATED: Mirroring Simplified Payroll (No HRA/DA/Basic).
 * UPDATED: Added overrides for OT, Incentives, PT, and TDS.
 */
session_start();
require_once("../auth.php");
requireRole(['Approver', 'Admin']);
require_once("../functions.php");

$userName = ($_SESSION['userName'] ?? 'Approver');

/* ---------- Helpers ---------- */
if (!function_exists('h')) { function h($x) { return htmlspecialchars((string)$x, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
function i($x) { return is_numeric($x) ? (int)$x : 0; }
function s($x) { return trim((string)$x); }

/* ---------- ID EXTRACTION ---------- */
$rid = i($_GET['rid'] ?? $_POST['request_id'] ?? $_REQUEST['rid'] ?? 0);

/**
 * Fetch Outstanding Advance for an Employee
 */
function employee_advance_balance(?int $id): float {
    if (!$id) return 0.0;
    $r = exeSql("SELECT SUM(amount - IFNULL(recovered_amount, 0)) as bal FROM advances WHERE entity_id=$id AND entity_type='employee' AND status='Active'");
    return (float)($r[0]['bal'] ?? 0);
}

/**
 * Lookup Username
 */
function username_lookup(int $id): string {
    $r = exeSql("SELECT username FROM users WHERE user_id = $id LIMIT 1");
    return (string)($r[0]['username'] ?? 'User#' . $id);
}

/* ---------- Form Actions ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $row = exeSql("SELECT * FROM payment_requests WHERE request_id=$rid LIMIT 1");
    $req = $row ? $row[0] : null;
    if (!$req) exit("Critical Error: Request #$rid not found.");

    $payload = json_decode($req['payload_json'] ?? '{}', true) ?: [];
    
    // Capture potential edits (Approver Overrides)
    if ($req['request_type'] === 'employee') {
        $payload['gross_salary'] = (float)($_POST['gross_salary'] ?? 0);
        $payload['incentives']   = (float)($_POST['incentives'] ?? 0);
        $payload['ot_amount']    = (float)($_POST['ot_amount'] ?? 0);
        $payload['lop_amount']   = (float)($_POST['lop_amount'] ?? 0);
        $payload['pf_deduction'] = (float)($_POST['pf_deduction'] ?? 0);
        $payload['esi_deduction']= (float)($_POST['esi_deduction'] ?? 0);
        $payload['tax_deduction']= (float)($_POST['tax_deduction'] ?? 0); // Used for PT
        $payload['tds_deduction']= (float)($_POST['tds_deduction'] ?? 0);
    }

    $payload['pay_now'] = (float)s($_POST['pay_now'] ?? '0');
    $payload['notes'] = s($_POST['notes'] ?? '');
    
    $action = s($_POST['wf_action'] ?? 'save');
    $now = date('Y-m-d H:i:s');
    
    if ($action==='approve') {
        $up = [
            'status' => 'APPROVED',
            'approved_by' => (int)$_SESSION['userId'],
            'approved_at' => $now,
            'total_amount' => $payload['pay_now'],
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE)
        ];
    } elseif ($action==='reject') {
        $up = ['status' => 'RETURNED', 'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE)];
    } else {
        $up = ['payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE), 'total_amount' => $payload['pay_now']];
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
    echo "<h3 style='color:#e74c3c; font-weight:800;'>Request Not Found</h3>";
    echo "<p style='color:#7f8c8d;'>Attempted to load ID: <strong>$rid</strong>. This record does not exist.</p>";
    echo "<a href='appdashboard' style='text-decoration:none; background:#0d6efd; color:white; padding:12px 30px; border-radius:30px; font-weight:bold; display:inline-block; margin-top:20px;'>Go Back</a>";
    echo "</div></div>";
    exit;
}

$payload = json_decode($req['payload_json'] ?? '{}', true) ?: [];
$initial_pay_now = (float)($payload['pay_now'] ?? $req['total_amount']);
$requester_name = username_lookup(i($req['requested_by']));

// ESI Compliance Check
$hasError = false;
if ($req['request_type'] === 'employee') {
    $gross = (float)($payload['gross_salary'] ?? 0);
    $esi = (float)($payload['esi_deduction'] ?? 0);
    if ($gross > 21000 && $esi > 0) $hasError = true;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Review Request #<?= $rid ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f4f7f6; font-family: 'Inter', system-ui, sans-serif; }
        .navbar { background: #fff !important; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .card { border-radius: 20px; border: 0; box-shadow: 0 15px 35px rgba(0,0,0,0.05); overflow: hidden; }
        .info-label { font-size: 0.65rem; font-weight: 800; color: #95a5a6; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; display: block; }
        .info-value { font-size: 1.1rem; font-weight: 700; color: #2c3e50; }
        .text-danger-bold { color: #dc3545; font-weight: 800; }
        .text-success-bold { color: #198754; font-weight: 800; }
        .bg-error { background-color: #fff5f5 !important; border: 1px solid #feb2b2 !important; }
        .form-control:focus { box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1); border-color: #0d6efd; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light mb-5">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold text-primary" href="appdashboard">KMK FINANCE</a>
        <div class="d-flex align-items-center">
            <span class="me-3 text-muted small fw-bold">Reviewer: <?= h($userName) ?></span>
            <a href="appdashboard" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold">Back to List</a>
        </div>
    </div>
</nav>

<div class="container py-2 pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4 text-center">
        <h2 class="mx-auto text-dark fw-bold h4">Authorization Desk: Request #<?= $rid ?></h2>
    </div>

    <?php if ($hasError): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center p-3">
            <i class="bi bi-exclamation-octagon-fill fs-3 me-3"></i>
            <div>
                <h6 class="mb-1 fw-bold">ESI Compliance Error</h6>
                <p class="mb-0 small">Gross Salary > ₹21,000. ESI must be ₹0.00. <strong>Please adjust the values below.</strong></p>
            </div>
        </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="request_id" value="<?=$rid?>">

        <div class="card shadow-lg border-0">
            <div class="card-header bg-primary text-white py-4 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0 fw-bold"><?=h(ucfirst($req['request_type'] ?? 'Payment'))?> Verification</h5>
                    <small class="opacity-75">Raised on <?= date('d M Y, h:i A', strtotime($req['created_at'])) ?></small>
                </div>
                <span class="badge bg-white text-primary rounded-pill px-3 py-2 fw-bold text-uppercase"><?= h($req['status']) ?></span>
            </div>
            
            <div class="card-body p-4 bg-white">
                <div class="row g-4 mb-4 border-bottom pb-4">
                    <div class="col-md-4 border-end">
                        <label class="info-label">Requested By</label>
                        <div class="info-value"><?=h($requester_name)?></div>
                    </div>
                    <div class="col-md-4 border-end text-center">
                        <label class="info-label">Current Net Amount</label>
                        <div class="info-value text-primary h4 mb-0">₹<?=number_format((float)$req['total_amount'],2)?></div>
                    </div>
                    <div class="col-md-4 text-end">
                        <label class="info-label text-danger">O/S ADVANCE BALANCE</label>
                        <?php $adv = employee_advance_balance((int)$req['employee_id']); ?>
                        <div class="info-value <?= $adv > 0 ? 'text-danger' : 'text-success' ?>">₹<?=number_format($adv, 2)?></div>
                    </div>
                </div>

                <!-- Simplified Breakdown Overrides -->
                <?php if($req['request_type'] === 'employee'): ?>
                    <div class="p-4 bg-light rounded-4 mb-4 border <?= $hasError ? 'bg-error' : '' ?>">
                        <h6 class="fw-bold text-muted text-uppercase small mb-3 border-bottom pb-2">Earning & Deduction Overrides</h6>
                        
                        <!-- Row 1: Earnings -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted">GROSS SALARY</label>
                                <input type="number" step="0.01" name="gross_salary" class="form-control fw-bold" value="<?= (float)($payload['gross_salary'] ?? 0) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted">INCENTIVES</label>
                                <input type="number" step="0.01" name="incentives" class="form-control text-success fw-bold" value="<?= (float)($payload['incentives'] ?? 0) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted">OT AMOUNT</label>
                                <input type="number" step="0.01" name="ot_amount" class="form-control text-success fw-bold" value="<?= (float)($payload['ot_amount'] ?? 0) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-danger">LOP AMOUNT</label>
                                <input type="number" step="0.01" name="lop_amount" class="form-control text-danger fw-bold border-danger-subtle" value="<?= (float)($payload['lop_amount'] ?? 0) ?>">
                            </div>
                        </div>

                        <!-- Row 2: Statutory Deductions -->
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted">PF DEDUCTION</label>
                                <input type="number" step="0.01" name="pf_deduction" class="form-control" value="<?= (float)($payload['pf_deduction'] ?? 0) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted">ESI DEDUCTION</label>
                                <input type="number" step="0.01" name="esi_deduction" class="form-control <?= $hasError ? 'is-invalid' : '' ?>" value="<?= (float)($payload['esi_deduction'] ?? 0) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted">PROF. TAX (PT)</label>
                                <input type="number" step="0.01" name="tax_deduction" class="form-control" value="<?= (float)($payload['tax_deduction'] ?? 0) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted">TDS DEDUCTION</label>
                                <input type="number" step="0.01" name="tds_deduction" class="form-control" value="<?= (float)($payload['tds_deduction'] ?? 0) ?>">
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row g-4 pt-2">
                    <div class="col-md-6 border-end">
                        <label class="form-label fw-bold h6 text-dark">FINAL AUTHORIZED NET PAYOUT *</label>
                        <div class="input-group input-group-lg border rounded-3">
                            <span class="input-group-text bg-white border-0 text-muted">₹</span>
                            <input type="number" step="0.01" name="pay_now" class="form-control border-0 fw-bold text-success-bold" value="<?= (float)$initial_pay_now ?>" required>
                        </div>
                        <small class="text-muted d-block mt-2">The Cashier will disburse exactly this amount.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">DECISION REMARKS / INSTRUCTIONS</label>
                        <textarea name="notes" class="form-control border-2" rows="3" placeholder="Explain rejection reason or specify special payment instructions..."><?=h($payload['notes']??'')?></textarea>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-light p-4 border-0">
                <div class="d-flex justify-content-center gap-3">
                    <button type="submit" name="wf_action" value="approve" class="btn btn-success btn-lg px-5 rounded-pill shadow-sm fw-bold" <?= $hasError ? 'disabled' : '' ?>>
                        <i class="bi bi-check-lg me-1"></i> Authorize & Send to Cashier
                    </button>
                    <button type="submit" name="wf_action" value="reject" class="btn btn-danger btn-lg px-5 rounded-pill shadow-sm fw-bold">
                        <i class="bi bi-arrow-left-short me-1"></i> Return to Requester
                    </button>
                    <button type="submit" name="wf_action" value="save" class="btn btn-dark btn-lg px-5 rounded-pill shadow-sm fw-bold">
                        <i class="bi bi-save me-1"></i> Save Changes Only
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>