<?php
/**
 * CASHIER: Vendor Redemption Point Management
 * UPDATED: Integrated with global functions.php and standard portal UI.
 * LOGIC: Manage balance in vendor_totals and audit trail in redemption_point_logs.
 */

require_once("../auth.php");
requireRole(['Cashier', 'Admin']);
require_once(__DIR__ . '/../functions.php');

if (!function_exists('h')) {
    function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}

$userId = (int)($_SESSION['userId'] ?? 1);
$msg = null;

/* ---------------------------------------------------- */
/* ---------- POST LOGIC: Update Points -------------- */
/* ---------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_points') {
    $vendorId    = (int)($_POST['vendor_id'] ?? 0);
    $updateType  = $_POST['update_type'] ?? ''; // 'ADD', 'SET'
    $pointsValue = (int)($_POST['points_value'] ?? 0);
    $note        = trim($_POST['note'] ?? '');

    if ($vendorId > 0 && $pointsValue >= 0 && !empty($note)) {
        try {
            // 1. Get current balance
            $currentData = exeSql("SELECT redemption_points FROM vendor_totals WHERE vendor_id = $vendorId LIMIT 1");
            $oldBal = $currentData ? (int)$currentData[0]['redemption_points'] : 0;
            
            // 2. Calculate new balance
            if ($updateType === 'ADD') {
                $newBal = $oldBal + $pointsValue;
                $change = $pointsValue;
            } else {
                $newBal = $pointsValue;
                $change = $newBal - $oldBal;
            }

            $dbObj->beginTransaction();

            // 3. Update or Insert into vendor_totals
            if ($currentData) {
                upData('vendor_totals', ['redemption_points' => $newBal], ["vendor_id = $vendorId"]);
            } else {
                insData('vendor_totals', [
                    'vendor_id' => $vendorId,
                    'redemption_points' => $newBal,
                    'total_bill' => 0,
                    'total_paid' => 0,
                    'balance' => 0
                ]);
            }

            // 4. Log the transaction
            insData('redemption_point_logs', [
                'vendor_id'     => $vendorId,
                'action_type'   => $updateType,
                'points_change' => $change,
                'new_balance'   => $newBal,
                'acted_by'      => $userId,
                'note'          => $note,
                'acted_at'      => date('Y-m-d H:i:s')
            ]);

            $dbObj->commit();
            $msg = ['type' => 'success', 'text' => "Points updated successfully. New Balance: ₹" . number_format($newBal)];
        } catch (Exception $e) {
             $dbObj->rollBack();
            $msg = ['type' => 'danger', 'text' => "Error: " . $e->getMessage()];
        }
    } else {
        $msg = ['type' => 'warning', 'text' => "Please provide a valid amount and a reference note."];
    }
}

/* ---------------------------------------------------- */
/* ---------- VIEW DATA FETCHING ---------------------- */
/* ---------------------------------------------------- */
$vendors = exeSql("SELECT vendor_id, vendor_name FROM vendors ORDER BY vendor_name ASC") ?: [];
$selId   = (int)($_GET['vendor_id'] ?? 0);

$currentPoints = 0;
$selVendorName = '';
$logs = [];

if ($selId > 0) {
    // Current Balance
    $res = exeSql("SELECT redemption_points FROM vendor_totals WHERE vendor_id = $selId LIMIT 1");
    if ($res) $currentPoints = (int)$res[0]['redemption_points'];

    // Vendor Name Lookup
    foreach($vendors as $v) { if((int)$v['vendor_id'] === $selId) { $selVendorName = $v['vendor_name']; break; } }

    // History Logs
    $logs = exeSql("SELECT l.*, u.username 
                    FROM redemption_point_logs l 
                    LEFT JOIN users u ON u.user_id = l.acted_by 
                    WHERE l.vendor_id = $selId 
                    ORDER BY l.acted_at DESC LIMIT 10") ?: [];
}

require_once("header.php");
require_once("nav.php");
?>

<style>
    .card { border-radius: 16px; border: 1px solid #eef2f3; }
    .balance-box { background: #f8fafb; border-radius: 12px; padding: 20px; border-left: 5px solid #0d6efd; }
    .log-table thead th { background: #f8fafb; font-size: 11px; text-transform: uppercase; color: #6c757d; }
    .log-table td { font-size: 13px; }
    .select2-container--default .select2-selection--single { height: 42px !important; border: 1px solid #dee2e6 !important; border-radius: 10px !important; padding-top: 6px !important; }
</style>

<div class="container-fluid px-4 mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <div>
            <h2 class="fw-bold h4 mb-0 text-dark">Vendor Redemption Management</h2>
            <p class="text-muted small mb-0">Adjust and track loyalty points / redemption balances.</p>
        </div>
        <a href="cdashboard.php" class="btn btn-sm btn-outline-secondary rounded-pill px-4 fw-bold">Back to Desk</a>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">
            <i class="bi bi-info-circle-fill me-2"></i> <?= h($msg['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- SELECTOR & ADJUSTMENT FORM -->
        <div class="col-lg-5">
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 text-uppercase small text-muted">Step 1: Choose Vendor</h6>
                    <form method="GET" id="vendorForm">
                        <select name="vendor_id" id="vendor_id" class="form-select select2" onchange="document.getElementById('vendorForm').submit()">
                            <option value="0">-- Search/Select Vendor --</option>
                            <?php foreach ($vendors as $v): ?>
                                <option value="<?= $v['vendor_id'] ?>" <?= $v['vendor_id'] == $selId ? 'selected' : '' ?>>
                                    <?= h($v['vendor_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <?php if ($selId > 0): ?>
                        <div class="balance-box mt-4 mb-4">
                            <span class="small fw-bold text-muted text-uppercase d-block mb-1">Current Balance for <?= h($selVendorName) ?></span>
                            <h2 class="fw-bold text-primary mb-0">₹<?= number_format($currentPoints) ?></h2>
                            <small class="text-muted">Equivalent to points available for redemption.</small>
                        </div>

                        <h6 class="fw-bold mb-3 text-uppercase small text-muted">Step 2: Adjust Balance</h6>
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="action" value="update_points">
                            <input type="hidden" name="vendor_id" value="<?= $selId ?>">

                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Adjustment Type</label>
                                <select name="update_type" class="form-select" required>
                                    <option value="ADD">Add Points (+)</option>
                                    <option value="SET">Set Balance (Manual Overwrite)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Point Value</label>
                                <input type="number" name="points_value" class="form-control" placeholder="0" required min="0">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Reference / Note</label>
                                <textarea name="note" class="form-control" rows="2" placeholder="e.g. Incentive for Jan GRNs / Correction for Voucher #123" required></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary w-100 py-2 rounded-pill fw-bold">Update Redemption Balance</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-person-badge fs-1 text-light"></i>
                            <p class="text-muted mt-2">Select a vendor from the list to view or edit their point balance.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- HISTORY LOGS -->
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold text-dark">Recent Transactions <?= $selId > 0 ? " - " . h($selVendorName) : "" ?></h6>
                </div>
                <div class="card-body p-0">
                    <?php if ($selId > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 log-table">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Date</th>
                                        <th>Action</th>
                                        <th class="text-end">Change</th>
                                        <th class="text-end">New Balance</th>
                                        <th>By</th>
                                        <th class="pe-4">Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($logs)): ?>
                                        <tr><td colspan="6" class="text-center py-5 text-muted italic">No prior redemption logs found for this vendor.</td></tr>
                                    <?php else: foreach ($logs as $l): ?>
                                        <tr>
                                            <td class="ps-4 text-muted small"><?= date('d M Y, h:i A', strtotime($l['acted_at'])) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $l['action_type'] === 'ADD' ? 'success' : 'info' ?>-subtle text-<?= $l['action_type'] === 'ADD' ? 'success' : 'info' ?> border small">
                                                    <?= $l['action_type'] ?>
                                                </span>
                                            </td>
                                            <td class="text-end fw-bold <?= $l['points_change'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= $l['points_change'] >= 0 ? '+' : '' ?><?= number_format($l['points_change']) ?>
                                            </td>
                                            <td class="text-end fw-bold">₹<?= number_format($l['new_balance']) ?></td>
                                            <td class="small fw-medium"><?= h($l['username'] ?? 'System') ?></td>
                                            <td class="pe-4 small text-muted"><?= h($l['note']) ?></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <p class="text-muted">Audit trail will appear here once a vendor is selected.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.select2').select2({ width: '100%' });
});
</script>

<?php require_once("footer.php"); ?>