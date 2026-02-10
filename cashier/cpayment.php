<?php

/**
 * CASHIER: Payment Disbursal Page.
 * Path: cpayment.php
 * FIXED: Implemented Multi-GRN Split Logic to prevent paying only one GRN when multiple are linked.
 * UPDATED: Shared adjustment distribution (Advance/Redemption) across split invoices.
 */
require_once("../auth.php");
requireRole(['Cashier', 'Admin']);
require_once("../functions.php");

/**
 * Helper: Generate unique Voucher No
 */
function generate_voucher_no(): string
{
    return 'VCH-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
}

$v_generated = generate_voucher_no();

/**
 * 1. ACTION HANDLER
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'process_payment') {
    $rid = (int)$_POST['request_id'];
    $mode = (string)($_POST['payment_mode'] ?? 'cash');

    $paid_at_input = (string)($_POST['paid_at'] ?? '');
    $now = ($paid_at_input) ? date('Y-m-d H:i:s', strtotime($paid_at_input)) : date('Y-m-d H:i:s');
    if (strtotime($now) > time()) {
        $now = date('Y-m-d H:i:s');
    }

    $curUser = (string)($_SESSION['userName'] ?? 'Cashier');

    $row = exeSql("SELECT * FROM payment_requests WHERE request_id=$rid LIMIT 1");
    if (!$row || $row[0]['status'] === 'PAID') {
        header("Location: cdashboard.php?err=invalid_request");
        exit;
    }

    $req = $row[0];
    $payload = json_decode($req['payload_json'] ?? '{}', true) ?: [];
    $type = (string)$req['request_type'];

    $v_no   = (string)trim($_POST['voucher_no'] ?? '');
    $i_no   = (string)trim($_POST['invoice_no'] ?? '');
    $ref    = (string)trim($_POST['payment_reference'] ?? '');
    $remark = (string)trim($_POST['remark'] ?? '');

    $bank_acc_id_raw = (int)($_POST['disbursed_bank_account_id'] ?? 0);
    $bank_acc_id = ($bank_acc_id_raw > 0) ? $bank_acc_id_raw : null;

    $cash_paid = (float)$_POST['amount_paid'];
    $adv_used = (float)($_POST['advance_used'] ?? 0);
    $red_used = (float)($_POST['redemption_used'] ?? 0);
    $total_disbursed = $cash_paid + $adv_used + $red_used;

    $proof_path = '';
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === 0) {
        $uploadDir = '../uploads/payments/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $newName = 'proof_' . $rid . '_' . time() . '.' . pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
        if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $uploadDir . $newName)) {
            $proof_path = 'uploads/payments/' . $newName;
        }
    }

    $dbObj->beginTransaction();
    try {
        if ($type === 'vendor') {
            /** * MULTI-GRN SPLIT LOGIC 
             * Distributes the total disbursement (Cash + Adv + Red) across the selected GRNs
             */
            $splits = $payload['grn_splits'] ?? [];
            if (empty($splits) && !empty($payload['grn_id'])) {
                // Fallback for single GRN requests
                $splits = [$payload['grn_id'] => $payload['pay_now']];
            }

            // Distribution trackers
            $rem_cash = $cash_paid;
            $rem_adv  = $adv_used;
            $rem_red  = $red_used;

            foreach ($splits as $gid => $req_amt) {
                $gid = (int)$gid;
                $req_amt = (float)$req_amt;

                // Calculate how much of this specific GRN split is being covered in this payment
                // We use the ratio of the total disbursed vs total requested if partial payment is made
                $ratio = ($total_disbursed > 0) ? ($req_amt / (float)$payload['pay_now']) : 0;

                $row_cash = round($rem_cash * $ratio, 2);
                $row_adv  = round($rem_adv * $ratio, 2);
                $row_red  = round($rem_red * $ratio, 2);

                $vendorData = [
                    'vendor_id' => (int)$req['vendor_id'],
                    'grn_id' => $gid,
                    'branch_id' => (int)$req['branch_id'],
                    'amount' => $row_cash,
                    'advance_used' => $row_adv,
                    'redemption_used' => $row_red,
                    'payment_reference' => $ref,
                    'method' => (string)strtolower($mode === 'Bank Transfer' ? 'bank' : $mode),
                    'bank_mode' => ($mode === 'Bank Transfer' ? 'transfer' : ($mode === 'Cheque' ? 'cheque' : ($mode === 'UPI' ? 'upi' : 'cash'))),
                    'bank_account' => (string)trim($_POST['bank_account'] ?? ''),
                    'bank_ifsc' => (string)trim($_POST['bank_ifsc'] ?? ''),
                    'cheque_no' => (string)trim($_POST['cheque_no'] ?? ''),
                    'upi_id' => (string)trim($_POST['upi_id'] ?? ''),
                    'voucher_no' => $v_no,
                    'invoice_no' => $i_no,
                    'remark' => $remark,
                    'voucher_path' => $proof_path,
                    'paid_at' => $now,
                    'payment_by' => $curUser
                ];
                if ($bank_acc_id) $vendorData['disbursed_bank_account_id'] = $bank_acc_id;
                insData('vendor_grn_payments', $vendorData);
            }

            // Finalize total vendor balance update
            exeSql("UPDATE vendor_totals SET total_paid = total_paid + $total_disbursed, balance = balance - $total_disbursed, advance = advance - $adv_used, redemption_points = redemption_points - $red_used WHERE vendor_id = " . (int)$req['vendor_id']);
        } elseif ($type === 'employee') {
            $employeeData = [
                'employee_id' => (int)$req['employee_id'],
                'pay_period' => (string)($payload['pay_period'] ?? date('Ym')),
                'gross_salary' => (float)($payload['gross_salary'] ?? 0),
                'amount' => $cash_paid,
                'advance' => $adv_used,
                'ot_amount' => (float)($payload['ot_amount'] ?? 0),
                'incentives' => (float)($payload['incentives'] ?? 0),
                'lop_days' => (float)($payload['lop_days'] ?? 0),
                'lop_amount' => (float)($payload['lop_amount'] ?? 0),
                'pf_deduction' => (float)($payload['pf_deduction'] ?? 0),
                'esi_deduction' => (float)($payload['esi_deduction'] ?? 0),
                'tax_deduction' => (float)($payload['tax_deduction'] ?? 0),
                'tds_deduction' => (float)($payload['tds_deduction'] ?? 0),
                'net_paid' => $cash_paid,
                'voucher_no' => $v_no,
                'invoice_no' => $i_no,
                'payment_by' => $curUser,
                'paid_at' => $now,
                'note' => $remark ?: (string)($payload['notes'] ?? 'Salary Disbursal')
            ];
            if ($bank_acc_id) $employeeData['disbursed_bank_account_id'] = $bank_acc_id;
            insData('employee_salary_payments', $employeeData);
            if ($adv_used > 0) exeSql("UPDATE employees SET advance = advance - $adv_used WHERE id = " . (int)$req['employee_id']);
        } elseif ($type === 'advance') {
            $entity_type = (!empty($req['vendor_id'])) ? 'vendor' : 'employee';
            $entity_id = (int)($req['vendor_id'] ?: $req['employee_id']);
            insData('advances', [
                'entity_type' => (string)$entity_type,
                'entity_id' => $entity_id,
                'branch_id' => (int)$req['branch_id'],
                'amount' => $cash_paid,
                'payment_method' => (string)$mode,
                'ref_number' => $ref,
                'voucher_no' => $v_no,
                'description' => $remark ?: (string)('Advance Disbursal | Req #' . $rid),
                'notes' => (string)($payload['notes'] ?? ''),
                'payment_date' => $now,
                'advance_date' => date('Y-m-d', strtotime($now)),
                'status' => 'Active',
                'created_by' => (int)$_SESSION['userId']
            ]);
            if ($entity_type === 'employee') {
                exeSql("UPDATE employees SET advance = advance + $cash_paid WHERE id = $entity_id");
            } else {
                exeSql("UPDATE vendor_totals SET advance = advance + $cash_paid WHERE vendor_id = $entity_id");
            }
        } elseif ($type === 'fixed') {
            $fixed_id = (int)($payload['fixed_id'] ?? 0);
            $db_bank_sql_val = $bank_acc_id ? (int)$bank_acc_id : "NULL";
            exeSql("UPDATE fixed_expenses SET balance_paid = balance_paid + $cash_paid, method = '$mode', voucher_no = '$v_no', invoice_no = '$i_no', payment_by = '$curUser', disbursed_bank_account_id = $db_bank_sql_val WHERE id = $fixed_id");
        } else {
            $expenseData = [
                'purpose' => (string)($payload['purpose'] ?? ($payload['custom_purpose'] ?? 'General')),
                'amount' => (int)($cash_paid + $adv_used),
                'balance_paid' => (int)($cash_paid + $adv_used),
                'method' => (string)strtolower($mode === 'Bank Transfer' ? 'bank' : $mode),
                'voucher_no' => $v_no,
                'invoice_no' => $i_no,
                'paid_at' => $now,
                'remark' => $remark ?: (string)($payload['notes'] ?? 'General Disbursal'),
                'payment_by' => $curUser,
                'account_no' => (string)trim($_POST['bank_account'] ?? ''),
                'ifsc_code' => (string)trim($_POST['bank_ifsc'] ?? ''),
                'payment_reference' => $ref,
                'advance' => $adv_used
            ];
            if ($bank_acc_id) $expenseData['disbursed_bank_account_id'] = $bank_acc_id;
            insData('expenses', $expenseData);
        }

        upData('payment_requests', ['status' => 'PAID', 'approved_amount' => $total_disbursed, 'updated_at' => $now], ["request_id=$rid"]);
        $dbObj->commit();
        header("Location: cdashboard.php?msg=paid&rid=$rid");
        exit;
    } catch (Throwable $e) {
        if ($dbObj->inTransaction()) $dbObj->rollBack();
        die("<div style='padding:50px; font-family:sans-serif;'><h2>Processing Error</h2><p>{$e->getMessage()}</p><a href='javascript:history.back()'>← Go Back</a></div>");
    }
}

// 2. Fetch View Data
$rid = (int)($_GET['rid'] ?? 0);
$row = exeSql("SELECT * FROM payment_requests WHERE request_id=$rid LIMIT 1");
$req = $row ? $row[0] : null;
if (!$req) {
    header("Location: cdashboard.php");
    exit;
}

$payload = json_decode($req['payload_json'] ?? '{}', true) ?: [];
$entityName = "N/A";
$availAdvance = 0;
$availRedemption = 0;
$beneficiary_ac = '';
$beneficiary_ifsc = '';
$metaInfo = '';
$breakdown = [];

$branch = exeSql("SELECT branch_name FROM branches WHERE branch_id=" . (int)$req['branch_id'])[0]['branch_name'] ?? 'General Office';

if ($req['vendor_id']) {
    $v_info = exeSql("SELECT v.vendor_name, v.account_number, v.ifsc, t.advance, t.redemption_points FROM vendors v LEFT JOIN vendor_totals t ON t.vendor_id = v.vendor_id WHERE v.vendor_id=" . (int)$req['vendor_id']);
    $entityName = $v_info[0]['vendor_name'] ?? 'Vendor #' . $req['vendor_id'];
    $availAdvance = (float)($v_info[0]['advance'] ?? 0);
    $availRedemption = (float)($v_info[0]['redemption_points'] ?? 0);
    $beneficiary_ac = $v_info[0]['account_number'] ?? '';
    $beneficiary_ifsc = $v_info[0]['ifsc'] ?? '';

    $grn_ids_preview = array_keys($payload['grn_splits'] ?? []);
    if (!empty($grn_ids_preview)) {
        $idList = implode(',', array_map('intval', $grn_ids_preview));
        $g_nums = exeSql("SELECT grn_number FROM goods_receipts WHERE grn_id IN ($idList)");
        $nums = array_column($g_nums, 'grn_number');
        $metaInfo = "Settling GRNs: " . implode(', ', $nums);
    }
} elseif ($req['employee_id']) {
    $e_info = exeSql("SELECT employee_name, bank_name, ifsc_code, advance FROM employees WHERE id=" . (int)$req['employee_id']);
    $entityName = $e_info[0]['employee_name'] ?? 'Employee #' . $req['employee_id'];
    $availAdvance = (float)($e_info[0]['advance'] ?? 0);
    $beneficiary_ac = $e_info[0]['bank_name'] ?? '';
    $beneficiary_ifsc = $e_info[0]['ifsc_code'] ?? '';
    $metaInfo = "Pay Period: " . ($payload['pay_period'] ?? 'N/A');

    $breakdown = [
        ['label' => 'Gross Salary', 'val' => (float)($payload['gross_salary'] ?? 0), 'type' => 'text-dark fw-bold'],
        ['label' => 'Incentives', 'val' => (float)($payload['incentives'] ?? 0), 'type' => 'text-success fw-bold'],
        ['label' => 'OT Amount', 'val' => (float)($payload['ot_amount'] ?? 0), 'type' => 'text-success fw-bold'],
        ['label' => 'LOP Amount', 'val' => (float)($payload['lop_amount'] ?? 0), 'type' => 'text-danger fw-bold'],
        ['label' => 'PF Deduction', 'val' => (float)($payload['pf_deduction'] ?? 0), 'type' => 'text-warning fw-bold'],
        ['label' => 'ESI Deduction', 'val' => (float)($payload['esi_deduction'] ?? 0), 'type' => 'text-warning fw-bold'],
        ['label' => 'Prof. Tax (PT)', 'val' => (float)($payload['tax_deduction'] ?? 0), 'type' => 'text-warning fw-bold'],
        ['label' => 'TDS Deduction', 'val' => (float)($payload['tds_deduction'] ?? 0), 'type' => 'text-warning fw-bold']
    ];
} else {
    $entityName = $payload['purpose'] ?? ($payload['custom_purpose'] ?? 'General Expense');
}

$companyBanks = exeSql("SELECT id, bank_name, account_number FROM bank_accounts ORDER BY bank_name");

require_once("header.php");
require_once("nav.php");
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <h4 class="fw-bold m-0 text-dark">Payment Disbursal Panel: Request #<?= $rid ?></h4>
        <a href="cdashboard.php" class="btn btn-outline-secondary rounded-pill px-4 btn-sm fw-bold">Cancel</a>
    </div>

    <form method="post" id="payForm" enctype="multipart/form-data">
        <input type="hidden" name="action" value="process_payment">
        <input type="hidden" name="request_id" value="<?= $rid ?>">

        <div class="row g-4">
            <!-- Left Panel: Breakdown -->
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 rounded-4 h-100 overflow-hidden">
                    <div class="card-header bg-dark text-white py-3">
                        <h6 class="mb-0 fw-bold text-white"><i class="bi bi-shield-lock me-2"></i>VERIFICATION AUDIT</h6>
                    </div>
                    <div class="card-body p-4 bg-light">
                        <div class="mb-4">
                            <label class="text-muted small text-uppercase fw-bold d-block">Payee / Recipient</label>
                            <div class="h4 fw-bold text-dark mb-0"><?= htmlspecialchars($entityName) ?></div>
                            <span class="badge bg-secondary mt-1 text-uppercase small"><?= strtoupper($req['request_type']) ?></span>
                            <?php if ($metaInfo): ?><div class="fw-bold mt-2 text-primary small"><i class="bi bi-link-45deg"></i> <?= $metaInfo ?></div><?php endif; ?>
                        </div>

                        <?php if (!empty($breakdown)): ?>
                            <div class="mb-4 bg-white p-3 rounded-4 border shadow-sm">
                                <label class="info-label text-muted small fw-bold text-uppercase d-block border-bottom pb-2 mb-2">Payout Components</label>
                                <?php foreach ($breakdown as $b): if ($b['val'] == 0 && !in_array($b['label'], ['PF Deduction', 'ESI Deduction'])) continue; ?>
                                    <div class="d-flex justify-content-between mb-1 small">
                                        <span class="text-muted"><?= $b['label'] ?>:</span>
                                        <span class="<?= $b['type'] ?>">₹<?= number_format($b['val'], 2) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="p-3 bg-white border rounded-3 mb-4 shadow-sm text-center">
                            <label class="text-muted small text-uppercase fw-bold d-block">Approved Net Amount</label>
                            <div class="h2 fw-bold text-success mb-0">₹<?= number_format((float)$req['total_amount'], 2) ?></div>
                        </div>

                        <div class="row g-2 mb-4">
                            <div class="col-6">
                                <div class="p-3 bg-danger-subtle rounded-3 border border-danger-subtle text-center">
                                    <label class="small text-danger fw-bold d-block text-uppercase">Avail. Advance</label>
                                    <div class="fw-bold h5 mb-0 text-danger">₹<?= number_format($availAdvance, 2) ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 bg-info-subtle rounded-3 border border-info-subtle text-center">
                                    <label class="small text-info fw-bold d-block text-uppercase">Avail. redemption</label>
                                    <div class="fw-bold h5 mb-0 text-info">₹<?= number_format($availRedemption, 2) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="text-muted small text-uppercase fw-bold d-block mb-2">Requester Notes</label>
                            <div class="p-3 bg-white border rounded-3 small text-muted italic"><?= htmlspecialchars($payload['notes'] ?? 'No special notes.') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Form -->
            <div class="col-lg-7">
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-primary text-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-cash-coin me-2"></i>LOG TRANSACTION DETAILS</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted text-uppercase">Voucher Number</label>
                                <input type="text" name="voucher_no" class="form-control border-primary bg-primary-subtle fw-bold" value="<?= $v_generated ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted text-uppercase">Invoice / Bill Ref</label>
                                <input type="text" name="invoice_no" class="form-control" placeholder="Original Ref" value="<?= htmlspecialchars($payload['invoice_no'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted text-uppercase">Source Account</label>
                                <select name="disbursed_bank_account_id" class="form-select border-2">
                                    <option value="0">-- Petty Cash (Manual) --</option>
                                    <?php foreach ($companyBanks as $cb): ?>
                                        <option value="<?= $cb['id'] ?>"><?= htmlspecialchars($cb['bank_name']) ?> (***<?= htmlspecialchars(substr($cb['account_number'], -4)) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small text-muted text-uppercase">Payment Mode</label>
                                <select name="payment_mode" id="mode_select" class="form-select border-2" required>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Cash">Cash</option>
                                    <option value="UPI">UPI</option>
                                    <option value="Cheque">Cheque</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small text-muted text-uppercase">Paid At</label>
                                <input type="datetime-local" name="paid_at" class="form-control border-2" value="<?= date('Y-m-d\TH:i') ?>" max="<?= date('Y-m-d\TH:i') ?>">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-muted text-uppercase">Transaction Reference</label>
                                <input type="text" name="payment_reference" class="form-control border-2 shadow-sm" placeholder="UTR / Ref Number" required>
                            </div>
                        </div>

                        <div id="bank_fields" class="p-3 bg-light rounded-4 mb-4 border border-primary-subtle">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label fw-bold small text-muted">A/C NO</label><input type="text" name="bank_account" class="form-control" value="<?= htmlspecialchars($beneficiary_ac) ?>"></div>
                                <div class="col-md-6"><label class="form-label fw-bold small text-muted">IFSC</label><input type="text" name="bank_ifsc" class="form-control" value="<?= htmlspecialchars($beneficiary_ifsc) ?>"></div>
                            </div>
                        </div>
                        <div id="upi_fields" class="p-3 bg-light rounded-4 mb-4 border border-primary-subtle d-none">
                            <div class="col-md-12"><label class="form-label fw-bold small text-muted">UPI ID / MOBILE</label><input type="text" name="upi_id" class="form-control" placeholder="example@upi"></div>
                        </div>
                        <div id="cheque_fields" class="p-3 bg-light rounded-4 mb-4 border border-primary-subtle d-none">
                            <div class="col-md-12"><label class="form-label fw-bold small text-muted">CHEQUE NUMBER</label><input type="text" name="cheque_no" class="form-control" placeholder="6-digit unique number"></div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-12"><label class="form-label fw-bold small text-muted">LEDGER REMARK</label><textarea name="remark" class="form-control" rows="2" placeholder="Audit notes..."></textarea></div>
                            <div class="col-md-12"><label class="form-label fw-bold small text-muted text-uppercase">Voucher Proof (File)</label><input type="file" name="payment_proof" class="form-control shadow-sm"></div>
                        </div>

                        <div class="row g-3 border-top pt-4 text-center">
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-success small">Amount</label>
                                <input type="number" step="0.01" name="amount_paid" class="form-control form-control-lg fw-bold border-2 text-center" value="<?= (float)$req['total_amount'] ?>" required readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-danger small">ADV. ADJUSTED</label>
                                <input type="number" step="0.01" name="advance_used" class="form-control form-control-lg text-center" value="<?= $availAdvance ?>" max="<?= $availAdvance ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-info small">REDEMPTION USED</label>
                                <input type="number" step="0.01" name="redemption_used" class="form-control form-control-lg text-center" value="<?= $availRedemption ?>" max="<?= $availRedemption ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-success small">Amount to be Paid</label>
                                <input type="number" step="0.01" name="amount_paid" class="form-control form-control-lg fw-bold border-2 text-center" value="<?= (float)$req['total_amount'] - $availAdvance - $availRedemption ?>" required readonly>
                            </div>
                            
                        </div>
                    </div>

                    <div class="card-footer bg-white border-0 p-4 pt-0">
                        <button type="submit" class="btn btn-success btn-lg w-100 py-3 rounded-pill fw-bold shadow-lg">Confirm Disbursal</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    $(document).ready(function() {
        $('#mode_select').change(function() {
            const mode = $(this).val();
            $('#bank_fields, #upi_fields, #cheque_fields').addClass('d-none');
            if (mode === 'Bank Transfer') $('#bank_fields').removeClass('d-none');
            if (mode === 'UPI') $('#upi_fields').removeClass('d-none');
            if (mode === 'Cheque') $('#cheque_fields').removeClass('d-none');
        });

        $('#payForm').submit(function(e) {
            const cash = parseFloat($('[name="amount_paid"]').val() || 0);
            const adv = parseFloat($('[name="advance_used"]').val() || 0);
            const red = parseFloat($('[name="redemption_used"]').val() || 0);
            if (adv > <?= $availAdvance ?>) {
                alert("Insufficient advance balance!");
                e.preventDefault();
                return;
            }
            if (red > <?= $availRedemption ?>) {
                alert("Insufficient points balance!");
                e.preventDefault();
                return;
            }
            if ((cash + adv + red) > (<?= (float)$req['total_amount'] ?> + 1)) {
                if (!confirm("Payout exceeds approved limit. Continue?")) e.preventDefault();
            }
        });
    });
</script>

<?php require_once("footer.php"); ?>