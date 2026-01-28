<?php
/**
 * payment2.php - Refactored to use existing class.mysql.php and functions.php
 */

declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Optimized limits - faster execution
set_time_limit(120); // 2 minutes is enough
ini_set('memory_limit', '128M');

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once('../functions.php');

/* ---------------- HELPER FUNCTIONS ---------------- */
function v($k, $d = null) { return $_POST[$k] ?? $_GET[$k] ?? $d; }
function i($x) { return is_numeric($x) ? (int)$x : 0; }
function f($x) { return is_numeric($x) ? (float)$x : 0.0; }
function s($x) { return trim((string)($x ?? '')); }
function h($x) { return htmlspecialchars((string)$x, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function now() { return date('Y-m-d H:i:s'); }

/* ---------------- UPLOAD PROOF ---------------- */
function upload_proof(array $file): ?string {
    if (!$file || empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    if (!in_array($ext, $allowed, true)) return null;
    $dir = __DIR__ . '/proofs/';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $name = 'proof_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $path = $dir . $name;
    if (!move_uploaded_file($file['tmp_name'], $path)) return null;
    return 'proofs/' . $name;
}

/* ---------------- VOUCHER & INVOICE GENERATORS ---------------- */
function generate_voucher_no(): string {
    global $dbObj;
    // $tables = ['employee_salary_payments', 'expenses', 'vendor_grn_payments', 'event_items', 'expenses_payments', 'fixed_expenses'];
    $tables = ['employee_salary_payments', 'expenses', 'vendor_grn_payments', 'event_items', 'fixed_expenses'];
    
    do {
        $code = 'VCH-' . date('Ymd') . '-' . date('His') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        $exist = false;
        
        foreach ($tables as $t) {
            $result = exeSql("SELECT 1 FROM `$t` WHERE voucher_no='" . DataBasePDO::escape($code) . "' LIMIT 1");
            if ($result) {
                $exist = true;
                break;
            }
        }
    } while ($exist);
    
    return $code;
}

function generate_invoice_no(): string {
    global $dbObj;
    // $tables = ['employee_salary_payments', 'expenses', 'vendor_grn_payments', 'event_items', 'expenses_payments', 'fixed_expenses'];

    $tables = ['employee_salary_payments', 'expenses', 'vendor_grn_payments', 'event_items', 'fixed_expenses'];
    
    do {
        $code = 'INV-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        $exist = false;
        
        foreach ($tables as $t) {
            $result = exeSql("SELECT 1 FROM `$t` WHERE invoice_no='" . DataBasePDO::escape($code) . "' LIMIT 1");
            if ($result) {
                $exist = true;
                break;
            }
        }
    } while ($exist);
    
    return $code;
}

/* ---------------- ENTITY HELPERS ---------------- */
function get_entity_name(int $id, string $type, array $payload): string {
    if ($id <= 0) {
        if ($type === 'expenses') {
            $p = s($payload['purpose'] ?? '');
            $c = s($payload['custom_purpose'] ?? '');
            return h($p === '__other__' ? $c : ($p ?: 'General Expense'));
        }
        return "N/A";
    }

    if ($type === 'vendor') {
        $r = exeSql("SELECT vendor_name FROM vendors WHERE vendor_id = $id LIMIT 1");
        return $r[0]['vendor_name'] ?? "Vendor N/A";
    } elseif ($type === 'employee') {
        $r = exeSql("SELECT employee_name FROM employees WHERE id = $id LIMIT 1");
        return $r[0]['employee_name'] ?? "Employee N/A";
    } elseif ($type === 'fixed') {
        $fixedId = i($payload['fixed_id'] ?? 0);
        $r = exeSql("SELECT expense_type FROM fixed_expenses WHERE id = $fixedId LIMIT 1");
        return ucfirst($r[0]['expense_type'] ?? "Fixed Expense N/A");
    }
    
    $p = s($payload['purpose'] ?? '');
    $c = s($payload['custom_purpose'] ?? '');
    return h($p === '__other__' ? $c : ($p ?: 'General Expense'));
}

function get_entity_balances(int $id, string $type): array {
    $default = ['advance' => 0.0, 'redemption_points' => 0.0];
    if ($id <= 0) return $default;

    if ($type === 'employee') {
        $r = exeSql("SELECT advance FROM employees WHERE id = $id LIMIT 1");
        return $r && isset($r[0]['advance']) ? ['advance' => (float)$r[0]['advance'], 'redemption_points' => 0.0] : $default;
    } elseif ($type === 'vendor' || $type === 'fixed') {
        $r = exeSql("SELECT advance, redemption_points FROM vendor_totals WHERE vendor_id = $id LIMIT 1");
        if ($r && isset($r[0]['advance'])) {
            return [
                'advance' => (float)$r[0]['advance'],
                'redemption_points' => (float)($r[0]['redemption_points'] ?? 0.0),
            ];
        }
    }
    return $default;
}

function get_entity_bank_details(int $entityId, string $entityType, int $fixedExpenseId): array {
    $default = ['account_number' => '', 'ifsc' => ''];
    if ($entityId <= 0 && $fixedExpenseId <= 0) return $default;

    if ($entityType === 'vendor') {
        $r = exeSql("SELECT account_number, ifsc FROM vendors WHERE vendor_id = $entityId LIMIT 1");
        if ($r && isset($r[0]['account_number'])) {
            return [
                'account_number' => s($r[0]['account_number']),
                'ifsc' => s($r[0]['ifsc']),
            ];
        }
    } elseif ($fixedExpenseId > 0) {
        $r = exeSql("SELECT account_no, ifsc_code FROM fixed_expenses WHERE id = $fixedExpenseId LIMIT 1");
        if ($r && isset($r[0]['account_no'])) {
            return [
                'account_number' => s($r[0]['account_no']),
                'ifsc' => s($r[0]['ifsc_code']),
            ];
        }
    }
    return $default;
}

function get_cashier_bank_accounts(): array {
    $bankNames = [];
    $accountsByBank = [];
    
    $accounts = exeSql("SELECT id, bank_name, account_number FROM bank_accounts ORDER BY bank_name, account_number ASC");

    foreach ($accounts as $account) {
        $name = s($account['bank_name']);
        
        if (!in_array($name, $bankNames)) {
            $bankNames[] = $name;
        }

        if (!isset($accountsByBank[$name])) {
            $accountsByBank[$name] = [];
        }

        $accountsByBank[$name][] = [
            'id' => i($account['id']),
            'number_display' => 'A/C ***' . substr(s($account['account_number']), -4),
        ];
    }
    
    return [
        'names' => $bankNames, 
        'json' => json_encode($accountsByBank)
    ];
}

/* ---------------- MAIN PAYMENT HANDLER (POST) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && s(v('action', '')) === 'pay') {
    global $dbObj;
    
    $rid = i(v('request_id', 0));
    $userAmountToClear = f(v('amount_to_clear', 0));
    $ref = s(v('payment_reference', ''));
    $mode = s(v('payment_mode', ''));
    $disbursedBankAccountId = i(v('disbursed_bank_account_id', 0));
    
    $userMode = strtolower($mode);
    $isBankPayment = in_array($userMode, ['bank transfer', 'cheque', 'upi']);
    $idToStore = ($isBankPayment && $disbursedBankAccountId > 0) ? $disbursedBankAccountId : null;
    
    // Validation
    if (($userMode === 'bank transfer' || $userMode === 'cheque') && $idToStore === null) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'A disbursing bank account number must be selected.'];
        header("Location: payment1.php?rid=" . $rid); exit;
    }
    
    if (empty($ref)) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Payment reference is compulsory.'];
        header("Location: payment1.php?rid=" . $rid); exit;
    }
    
    if ($rid <= 0 || $userAmountToClear <= 0) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Invalid Request ID or Amount.'];
        header("Location: payment1.php?rid=" . $rid); exit;
    }

    // Fetch request
    $row = exeSql("SELECT * FROM payment_requests WHERE request_id=$rid LIMIT 1");
    if (!$row) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Request not found.'];
        header("Location: dashboard.php"); exit;
    }
    
    $req = $row[0];
    if (($req['status'] ?? '') !== 'READY_FOR_CASHIER') {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'This request is not pending with Cashier.'];
        header("Location: dashboard.php"); exit;
    }

    $payload = json_decode($req['payload_json'] ?? '{}', true) ?: [];
    $approvedAmount = isset($req['approved_amount']) && $req['approved_amount'] !== null
        ? (float)$req['approved_amount']
        : (float)($payload['pay_now'] ?? ($req['total_amount'] ?? 0));

    $entityId = i(v('entity_id', 0));
    $entityType = s(v('entity_type', ''));
    $redemptionUsedInput = ($entityType === 'vendor' || $req['request_type'] === 'fixed') ? f(v('redemption_points_use', 0)) : 0.0;
    $fixedExpenseId = $req['request_type'] === 'fixed' ? i($payload['fixed_id'] ?? 0) : 0;

    $entityBalances = get_entity_balances($entityId, $req['request_type'] === 'fixed' ? 'vendor' : $req['request_type']);
    $currentAdvance = $entityBalances['advance'];
    $currentPoints = $entityBalances['redemption_points'];

    // Allocation logic
    $advanceUsed = min($currentAdvance, $userAmountToClear);
    $debtAfterAdvance = $userAmountToClear - $advanceUsed;
    $redemptionUsed = min($redemptionUsedInput, $currentPoints);
    $redemptionUsed = min($redemptionUsed, $debtAfterAdvance);
    $cashBankAmount = max(0, $debtAfterAdvance - $redemptionUsed);
    $paid = $redemptionUsed + $advanceUsed + $cashBankAmount;
    $paid = min($approvedAmount, $paid);

    // Determine payment method
    if ($paid < 0.01) {
        if ($redemptionUsed > 0 && $advanceUsed > 0) { $dbMethod = 'redemption_advance'; }
        elseif ($redemptionUsed > 0) { $dbMethod = 'redemption'; }
        elseif ($advanceUsed > 0) { $dbMethod = 'advance'; }
        else { $dbMethod = 'cash'; }
    } elseif ($userMode === 'bank transfer' || $userMode === 'cheque') { $dbMethod = 'bank'; }
    elseif ($userMode === 'upi') { $dbMethod = 'online'; }
    else { $dbMethod = 'cash'; }

    // Proof upload
    $isProofRequired = $cashBankAmount > 0.005 || $advanceUsed > 0 || $redemptionUsed > 0;
    $proof = null;
    if ($isProofRequired) {
        $proof = upload_proof($_FILES['proof'] ?? []);
        if (!$proof) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Payment proof is required but was not uploaded.'];
            header("Location: payment1.php?rid=" . $rid); exit;
        }
    }

    $paidAt = s(v('paid_at', '')) ?: now();
    $note = s(v('note', ''));
    $paidBy = s(v('payment_by', '')) ?: (string)($_SESSION['user_name'] ?? 'cashier');
    $voucherNo = generate_voucher_no();
    $invoiceNo = s(v('preview_invoice_number_hidden', ''));
    if (empty($invoiceNo)) {
        $invoiceNo = generate_invoice_no();
    }

    $bankAccount = s(v('bank_account'));
    $bankIfsc = s(v('bank_ifsc'));
    $chequeNo = s(v('cheque_no'));
    $upiId = s(v('upi_id'));
    $bankMode = null;
    
    if ($dbMethod === 'bank') {
        $bankMode = $userMode === 'cheque' ? 'cheque' : 'transfer';
        if (empty($chequeNo)) { $chequeNo = $userMode === 'cheque' ? $ref : null; }
    }
    
    if ($dbMethod === 'online') {
        if (empty($upiId)) { $upiId = $userMode === 'upi' ? $ref : null; }
    }

    // Begin Transaction
    $dbObj->beginTransaction();
    
    try {
        /* -------- VENDOR FLOW -------- */
        if ($req['request_type'] === 'vendor' && $entityId > 0) {
            $targetGrnIds = array_map('intval', (array)($payload['grn_ids'] ?? []));
            $targetGrnIds = array_filter($targetGrnIds);

            if (empty($targetGrnIds)) {
                throw new RuntimeException("Vendor payment requested without valid GRN IDs.");
            }

            $grn_ids_string = implode(',', $targetGrnIds);
            $grnDetails = exeSql("SELECT grn_id, total_amount, transportation, paid_amount FROM goods_receipts WHERE grn_id IN ($grn_ids_string)");

            $grnOutstanding = [];
            $totalGrnOutstanding = 0.0;

            foreach ($grnDetails as $detail) {
                $grnId = (int)$detail['grn_id'];
                $totalBill = (float)$detail['total_amount'] + (float)$detail['transportation'];
                $totalPaid = (float)($detail['paid_amount'] ?? 0.0);
                $outstanding = max(0.0, $totalBill - $totalPaid);
                $grnOutstanding[$grnId] = $outstanding;
                $totalGrnOutstanding += $outstanding;
            }

            $paidToDistribute = $paid;
            $grnPaymentMap = [];

            if ($totalGrnOutstanding > 0) {
                // OPTIMIZED: Simple proportional distribution - no loops
                $totalDistributed = 0;
                $lastGrnId = null;
                
                foreach ($targetGrnIds as $grnId) {
                    $lastGrnId = $grnId; // Track last GRN for rounding adjustment
                    if (!isset($grnOutstanding[$grnId]) || $grnOutstanding[$grnId] <= 0) continue;
                    
                    $ratio = $grnOutstanding[$grnId] / $totalGrnOutstanding;
                    $paymentForGrn = round($paidToDistribute * $ratio, 2);
                    $paymentForGrn = min($paymentForGrn, $grnOutstanding[$grnId]);
                    $grnPaymentMap[$grnId] = $paymentForGrn;
                    $totalDistributed += $paymentForGrn;
                }
                
                // Handle rounding differences - add remainder to last GRN
                $remainder = round($paidToDistribute - $totalDistributed, 2);
                if ($remainder != 0 && $lastGrnId !== null && isset($grnPaymentMap[$lastGrnId])) {
                    $grnPaymentMap[$lastGrnId] += $remainder;
                }

                // BATCH INSERT: Prepare all payment records
                $paymentBatch = [];
                $grnUpdateCases = [];
                $grnIdsToUpdate = [];
                
                foreach ($targetGrnIds as $grnId) {
                    $paymentForGrn = $grnPaymentMap[$grnId] ?? 0.0;
                    if ($paymentForGrn <= 0) continue;

                    $ratio = $paymentForGrn / $paid;
                    $advanceForGrn = round($advanceUsed * $ratio, 2);
                    $redemptionForGrn = round($redemptionUsed * $ratio, 2);
                    $cashBankForGrn = max(0, $paymentForGrn - $advanceForGrn - $redemptionForGrn);

                    $paymentBatch[] = [
                        'vendor_id' => $entityId,
                        'grn_id' => $grnId,
                        'branch_id' => $req['branch_id'] ?? null,
                        'amount' => $cashBankForGrn + $redemptionForGrn,
                        'advance_used' => $advanceForGrn,
                        'redemption_used' => $redemptionForGrn,
                        'method' => $dbMethod,
                        'payment_reference' => $ref,
                        'bank_mode' => $bankMode,
                        'bank_account' => $bankAccount,
                        'bank_ifsc' => $bankIfsc,
                        'cheque_no' => $chequeNo,
                        'upi_id' => $upiId,
                        'remark' => $note,
                        'proof_path' => $proof,
                        'voucher_no' => $voucherNo,
                        'invoice_no' => $invoiceNo,
                        'paid_at' => $paidAt,
                        'payment_by' => $paidBy,
                        'disbursed_bank_account_id' => $idToStore,
                    ];
                    
                    // Prepare batch update for goods_receipts
                    $grnUpdateCases[] = "WHEN $grnId THEN paid_amount + $paymentForGrn";
                    $grnIdsToUpdate[] = $grnId;
                }

                // BATCH INSERT: Insert all payments in chunks of 100 (increased from 50)
                if (!empty($paymentBatch)) {
                    $chunkSize = 100; // Larger chunks = fewer queries
                    $chunks = array_chunk($paymentBatch, $chunkSize);
                    
                    foreach ($chunks as $chunk) {
                        // Build multi-row INSERT
                        $columns = array_keys($chunk[0]);
                        $columnsSql = '`' . implode('`, `', $columns) . '`';
                        $valuesParts = [];
                        
                        foreach ($chunk as $row) {
                            $values = [];
                            foreach ($columns as $col) {
                                $val = $row[$col];
                                if ($val === null) {
                                    $values[] = 'NULL';
                                } elseif (is_numeric($val)) {
                                    $values[] = $val;
                                } else {
                                    $values[] = "'" . DataBasePDO::escape($val) . "'";
                                }
                            }
                            $valuesParts[] = '(' . implode(', ', $values) . ')';
                        }
                        
                        $insertSql = "INSERT INTO vendor_grn_payments ($columnsSql) VALUES " . implode(', ', $valuesParts);
                        excuteSql($insertSql);
                    }
                }

                // BATCH UPDATE: Update all GRN paid_amounts in one query
                if (!empty($grnIdsToUpdate)) {
                    $grnIdsList = implode(',', $grnIdsToUpdate);
                    $caseSql = implode(' ', $grnUpdateCases);
                    $updateSql = "UPDATE goods_receipts SET 
                        paid_amount = CASE grn_id $caseSql END,
                        updated_at = '" . now() . "'
                        WHERE grn_id IN ($grnIdsList)";
                    excuteSql($updateSql);
                }

                // Update vendor_totals
                $updateSql = "UPDATE vendor_totals SET 
                    total_paid = total_paid + $paid,
                    advance = advance - $advanceUsed,
                    redemption_points = redemption_points - $redemptionUsed,
                    balance = total_bill - (total_paid + $paid)
                    WHERE vendor_id = $entityId";
                excuteSql($updateSql);
            }

        /* -------- EMPLOYEE FLOW -------- */
        } elseif ($req['request_type'] === 'employee' && $entityId > 0) {
            $empPaidAmount = $cashBankAmount + $redemptionUsed;
            
            $insertData = [
                'employee_id' => $entityId,
                'pay_period' => s($payload['pay_period'] ?? ''),
                'amount' => $empPaidAmount,
                'paid_at' => $paidAt,
                'note' => $note,
                'voucher_no' => $voucherNo,
                'invoice_no' => $invoiceNo,
                'payment_by' => $paidBy,
                'advance' => $advanceUsed,
                'payment_reference' => $ref,
                'disbursed_bank_account_id' => $idToStore,
            ];
            
            insData('employee_salary_payments', $insertData);

            excuteSql("UPDATE employees SET 
                last_paid_period = '" . DataBasePDO::escape(s($payload['pay_period'] ?? '')) . "',
                advance = advance - $advanceUsed 
                WHERE id = $entityId");

        /* -------- FIXED EXPENSES -------- */
        } elseif ($req['request_type'] === 'fixed' && $fixedExpenseId > 0) {
            $updateData = [
                'balance_paid' => 'balance_paid + ' . $paid,
                'method' => $dbMethod,
                'voucher_no' => $voucherNo,
                'invoice_no' => $invoiceNo,
                'payment_by' => $paidBy,
                'payment_proof_path' => $proof,
                'notes' => $note,
                'account_no' => $bankAccount ?? $upiId ?? null,
                'ifsc_code' => $bankIfsc ?? null,
                'updated_at' => now(),
                'payment_reference' => $ref,
                'redemption_used' => $redemptionUsed,
                'disbursed_bank_account_id' => $idToStore,
            ];

            upData('fixed_expenses', $updateData, ['id' => $fixedExpenseId]);

            if ($advanceUsed > 0 && $entityId > 0) {
                excuteSql("UPDATE vendor_totals SET advance = advance - $advanceUsed WHERE vendor_id = $entityId");
            }

            if ($redemptionUsed > 0 && $entityId > 0) {
                excuteSql("UPDATE vendor_totals SET redemption_points = redemption_points - $redemptionUsed WHERE vendor_id = $entityId");
            }

        /* -------- EXPENSES FLOW -------- */
        } else {
            $purpose = s($payload['purpose'] ?? $payload['custom_purpose'] ?? 'General Expense');
            $expenseRow = exeSql("SELECT id FROM expenses WHERE purpose='" . DataBasePDO::escape($purpose) . "' ORDER BY created_at DESC LIMIT 1");
            $expenseId = i($expenseRow[0]['id'] ?? 0);

            if ($expenseId > 0) {
                $updateData = [
                    'balance_paid' => 'balance_paid + ' . $paid,
                    'method' => strtolower($mode),
                    'voucher_no' => $voucherNo,
                    'invoice_no' => $invoiceNo,
                    'paid_at' => $paidAt,
                    'remark' => $note,
                    'payment_by' => $paidBy,
                    'advance' => $advanceUsed,
                    'payment_reference' => $ref,
                    'disbursed_bank_account_id' => $idToStore,
                ];
                
                upData('expenses', $updateData, ['id' => $expenseId]);
            }
        }

        // Mark request PAID
        upData('payment_requests', [
            'status' => 'PAID',
            'updated_at' => now(),
        ], ['request_id' => $rid]);

        // Audit trail
        insData('payment_actions', [
            'request_id' => $rid,
            'action' => 'PAYMENT_POSTED',
            'actor_id' => (int)($_SESSION['user_id'] ?? 1),
            'comment' => 'Payment disbursed by Cashier',
            'acted_at' => now(),
        ]);

        $dbObj->commit();

        $_SESSION['flash_message'] = [
            'type' => 'success',
            'text' => 'Payment for Request ID ' . $rid . ' successfully updated. Invoice: ' . h($invoiceNo),
        ];
        header("Location: dashboard.php");
        exit;

    } catch (Throwable $e) {
        $dbObj->rollBack();
        error_log("PAYMENT ERROR: " . $e->getMessage());
        $_SESSION['flash_message'] = [
            'text' => 'Payment failed: ' . h($e->getMessage()),
            'type' => 'danger'
        ];
        header("Location: payment1.php?rid=" . $rid);
        exit;
    }
}

/* ---------------- VIEW (HTML UI) - Same as before ---------------- */
$rid = isset($_GET['rid']) ? (int)$_GET['rid'] : 0;
$flashMessage = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cashier – Payments</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{font-family:system-ui,Arial,sans-serif; padding:20px;}
        .card{border:1px solid #e5e7eb; border-radius:12px;}
        .muted{color:#64748b; font-size:13px}
        .badge-status{font-weight:700}
        .readonly{background:#f8fafc}
        .field-required::before { content: '* '; color: red; }
        
        /* Loading Overlay - only shows for 10+ GRNs */
        #loadingOverlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        #loadingOverlay.show {
            display: flex;
        }
        .loading-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #0d6efd;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 0.8s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loading-text {
            color: #333;
            margin-top: 15px;
            font-size: 14px;
            font-weight: 500;
        }
    </style>
</head>
<body class="container">
    <!-- Loading Overlay - Conditional -->
    <div id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <div class="loading-text">Processing payment...</div>
        </div>
    </div>
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Cashier – Payments</h3>
        <a class="btn btn-outline-secondary" href="dashboard.php">Dashboard</a>
    </div>

    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= h($flashMessage['type']) ?> alert-dismissible fade show" role="alert">
            <?= h($flashMessage['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

<?php
if ($rid > 0) {
    $row = exeSql("SELECT * FROM payment_requests WHERE request_id=$rid LIMIT 1");
    if (!$row) { echo '<div class="alert alert-warning">Request not found.</div>'; exit; }
    $r = $row[0];
    $payload = json_decode($r['payload_json'] ?? '{}', true) ?: [];

    $entityId = 0;
    $entityName = '';
    $entityType = $r['request_type'];
    $previewInvoice = '';
    $fixedExpenseId = 0;
    
    $cashierBankData = get_cashier_bank_accounts();
    $cashierBankNames = $cashierBankData['names'];
    $cashierAccountsJson = $cashierBankData['json'];

    if ($r['request_type'] === 'vendor') {
        $entityId = i($r['vendor_id'] ?? 0);
        $entityName = get_entity_name($entityId, $entityType, $payload);
        
        $ids = implode(',', array_map('intval', (array)($payload['grn_ids'] ?? [])));
        if ($ids) {
            $rs = exeSql("SELECT invoice_number FROM goods_receipts WHERE grn_id IN ($ids)");
            foreach ($rs as $x) {
                if ($x['invoice_number'] && strpos($previewInvoice, $x['invoice_number']) === false) {
                    $previewInvoice .= ($previewInvoice ? '/' : '') . $x['invoice_number'];
                }
            }
        }
        if (empty($previewInvoice)) $previewInvoice = 'Generated';
        $entityBankDetails = get_entity_bank_details($entityId, $entityType, 0);

    } elseif ($r['request_type'] === 'employee') {
        $entityId = i($r['employee_id'] ?? 0);
        $entityName = get_entity_name($entityId, $entityType, $payload);
        $previewInvoice = generate_invoice_no();

    } elseif ($r['request_type'] === 'fixed') {
        $fixedExpenseId = i($payload['fixed_id'] ?? 0);
        $entityId = i($r['entity_id'] ?? 0);
        $entityName = get_entity_name($entityId, $entityType, $payload);
        $previewInvoice = generate_invoice_no();
        $entityType = 'fixed_expense';
        $entityBankDetails = get_entity_bank_details($entityId, 'fixed_expense', $fixedExpenseId);

    } else {
        $entityName = get_entity_name(0, $entityType, $payload);
        $previewInvoice = generate_invoice_no();
        $entityType = 'expenses';
        $entityBankDetails = get_entity_bank_details(0, 'expenses', 0);
    }

    $previewVoucher = generate_voucher_no();
    $approvedAmount = isset($r['approved_amount']) && $r['approved_amount'] !== null
        ? (float)$r['approved_amount']
        : (float)($payload['pay_now'] ?? ($r['total_amount'] ?? 0));

    $entityBalances = get_entity_balances($entityId, $r['request_type'] === 'fixed' ? 'vendor' : $r['request_type']);
    $advanceAmount = $entityBalances['advance'];
    $redemptionPoints = $entityBalances['redemption_points'];
?>
    <div class="card p-3 mb-4">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="h5 mb-1"><?= h(ucfirst($r['request_type'])) ?> Payment for <?= h($entityName) ?></div>
            </div>
            <span class="badge bg-<?= $r['status'] === 'READY_FOR_CASHIER' ? 'info' : 'secondary' ?>">
                <?= h($r['status']) ?>
            </span>
        </div>

        <?php if ($r['status'] !== 'READY_FOR_CASHIER'): ?>
            <div class="alert alert-warning mt-3">This request is <strong><?= h($r['status']) ?></strong>. Only requests <strong>With Cashier</strong> can be paid.</div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="mt-3">
            <input type="hidden" name="action" value="pay">
            <input type="hidden" name="request_id" value="<?= $rid ?>">
            <input type="hidden" name="entity_id" value="<?= $entityId ?>">
            <input type="hidden" name="entity_type" value="<?= h($entityType) ?>">
            <input type="hidden" id="cashier_accounts_json" value='<?= h($cashierAccountsJson) ?>'>

            <?php if ($fixedExpenseId > 0): ?>
                <input type="hidden" name="fixed_expense_id" value="<?= $fixedExpenseId ?>">
            <?php endif; ?>

            <input type="hidden" name="preview_invoice_number_hidden" value="<?= h($previewInvoice) ?>">

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Total Approved (Readonly)</label>
                    <input type="number" step="0.01" id="approved_amount" class="form-control readonly"
                        value="<?= number_format($approvedAmount, 2, '.', '') ?>" readonly>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Cash Advance Available</label>
                    <input type="number" step="0.01" id="advance_available" class="form-control readonly"
                        value="<?= number_format($advanceAmount, 2, '.', '') ?>" readonly>
                </div>

                <div class="col-md-3" id="redemption_points_block" style="<?= ($r['request_type'] === 'vendor' || ($r['request_type'] === 'fixed' && $entityId > 0)) ? '' : 'display:none;' ?>">
                    <label class="form-label">Redemption Points Available</label>
                    <input type="number" step="0.01" id="redemption_points_available" class="form-control readonly"
                        value="<?= number_format($redemptionPoints, 2, '.', '') ?>" readonly>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Net Payable (Cash/Bank)</label>
                    <input type="number" step="0.01" id="net_payable_display" class="form-control readonly"
                        value="<?= number_format($approvedAmount, 2, '.', '') ?>" readonly>
                </div>

                <div class="col-md-3">
                    <label class="form-label field-required">Amount to Clear</label>
                    <input type="number" step="0.01" min="0.01" id="amount_to_clear" name="amount_to_clear" class="form-control readonly"
                        value="<?= number_format($approvedAmount, 2, '.', '') ?>" readonly>
                </div>

                <div class="col-md-3" id="redemption_use_block_input" style="<?= ($r['request_type'] === 'vendor' || ($r['request_type'] === 'fixed' && $entityId > 0)) ? '' : 'display:none;' ?>">
                    <label class="form-label">Redemption Points to Use</label>
                    <input type="number" min="0.00" id="redemption_points_use" name="redemption_points_use" class="form-control"
                        value="0.00" <?= $redemptionPoints > 0 ? '' : 'readonly' ?>>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Paid At</label>
                    <input type="datetime-local" name="paid_at" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label field-required">Payment Mode</label>
                    <select name="payment_mode" id="payment_mode_select" class="form-select" required>
                        <option value="">-- Select --</option>
                        <option value="Cash">Cash</option>
                        <option value="UPI">UPI</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Advance">Advance</option>
                    </select>
                </div>
                
                <div class="col-md-3 bank-select-field" id="bank_name_field" style="display:none;">
                    <label class="form-label">Disbursing Bank Name</label>
                    <select class="form-select" id="bank_name_select">
                        <option value="">-- Select Bank Name --</option>
                        <?php foreach ($cashierBankNames as $bankName): ?>
                            <option value="<?= h($bankName) ?>"><?= h($bankName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3 bank-select-field" id="account_number_field" style="display:none;">
                    <label class="form-label">Account Number</label>
                    <select name="disbursed_bank_account_id" class="form-select" id="account_number_select">
                        <option value="">-- Select Account Number --</option>
                    </select>
                </div>

                <div class="col-md-3 bank-fields" style="display:none;">
                    <label class="form-label">Recipient Bank Account #</label>
                    <input type="text" name="bank_account" class="form-control" 
                        value="<?= h($entityBankDetails['account_number'] ?? '') ?>">
                </div>

                <div class="col-md-3 bank-fields" style="display:none;">
                    <label class="form-label">Recipient Bank IFSC</label>
                    <input type="text" name="bank_ifsc" class="form-control" 
                        value="<?= h($entityBankDetails['ifsc'] ?? '') ?>">
                </div>

                <div class="col-md-3 cheque-field" style="display:none;">
                    <label class="form-label">Cheque Number</label>
                    <input type="text" name="cheque_no" class="form-control">
                </div>

                <div class="col-md-3 upi-field" style="display:none;">
                    <label class="form-label">UPI ID</label>
                    <input type="text" name="upi_id" class="form-control">
                </div>

                <div class="col-md-3">
                    <label class="form-label field-required" id="reference_label">Reference</label>
                    <input type="text" name="payment_reference" class="form-control" id="payment_reference_field" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Voucher No (auto)</label>
                    <input type="text" class="form-control readonly" value="<?= h($previewVoucher) ?>" readonly>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Invoice No</label>
                    <input type="text" class="form-control readonly" value="<?= h($previewInvoice) ?>" readonly>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Payment By</label>
                    <input type="text" name="payment_by" class="form-control" value="<?= h($_SESSION['user_name'] ?? 'cashier') ?>">
                </div>

                <div class="col-md-12">
                    <label class="form-label">Note</label>
                    <input type="text" name="note" class="form-control">
                </div>

                <div class="col-md-6">
                    <label class="form-label proof-label">Upload Proof (jpg/png/pdf)</label>
                    <input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" class="form-control" id="proof-input">
                </div>
            </div>

            <div class="mt-3">
                <button class="btn btn-primary" id="submitPaymentBtn" <?= $r['status'] === 'READY_FOR_CASHIER' ? '' : 'disabled' ?>>
                    Mark as Paid &amp; Go to Dashboard
                </button>
                <a class="btn btn-outline-secondary" href="payment1.php">Back</a>
            </div>
        </form>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const submitBtn = document.getElementById('submitPaymentBtn');
    const loadingOverlay = document.getElementById('loadingOverlay');
    
    // Get GRN count from payload
    const grnCount = <?= isset($payload['grn_ids']) ? count((array)$payload['grn_ids']) : 0 ?>;
    const showLoading = grnCount >= 10; // Only show for 10+ GRNs
    
    // Show loading overlay on form submit (only for large batches)
    if (form && submitBtn) {
        form.addEventListener('submit', function(e) {
            if (submitBtn.disabled) {
                e.preventDefault();
                return false;
            }
            
            // Show loading only for large GRN batches
            if (showLoading) {
                loadingOverlay.classList.add('show');
            }
            
            submitBtn.disabled = true;
            
            // Show inline spinner for small batches
            if (!showLoading) {
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
            }
        });
    }
    
    const approved = document.getElementById('approved_amount');
    const advance = document.getElementById('advance_available');
    const amountToClear = document.getElementById('amount_to_clear');
    const netPayableDisplay = document.getElementById('net_payable_display');
    const paymentModeSelect = document.getElementById('payment_mode_select');
    const redemptionAvailable = document.getElementById('redemption_points_available');
    const redemptionUse = document.getElementById('redemption_points_use');
    const proofInput = document.getElementById('proof-input');
    const proofLabel = document.querySelector('.proof-label');
    const bankAccountField = document.querySelector('input[name="bank_account"]');
    const bankIfscField = document.querySelector('input[name="bank_ifsc"]');
    const chequeNoField = document.querySelector('input[name="cheque_no"]');
    const upiIdField = document.querySelector('input[name="upi_id"]');
    const paymentReferenceField = document.getElementById('payment_reference_field');
    const bankFields = document.querySelectorAll('.bank-fields');
    const chequeField = document.querySelector('.cheque-field');
    const upiField = document.querySelector('.upi-field');
    const bankNameField = document.getElementById('bank_name_field');
    const bankNameSelect = document.getElementById('bank_name_select');
    const accountNumberField = document.getElementById('account_number_field');
    const accountNumberSelect = document.getElementById('account_number_select');
    const cashierAccountsData = JSON.parse(document.getElementById('cashier_accounts_json').value);

    function toggleRequired(field, isRequired) {
        if (field) {
            if (isRequired) {
                field.setAttribute('required', 'required');
                const label = field.closest('.col-md-3, .col-md-6, .col-md-12')?.querySelector('.form-label');
                if (label) label.classList.add('field-required');
            } else {
                field.removeAttribute('required');
                const label = field.closest('.col-md-3, .col-md-6, .col-md-12')?.querySelector('.form-label');
                if (label) label.classList.remove('field-required');
            }
        }
    }
    
    function updateAccountDropdowns() {
        const selectedBankName = bankNameSelect.value;
        accountNumberSelect.innerHTML = '<option value="">-- Select Account Number --</option>';
        
        if (selectedBankName && cashierAccountsData[selectedBankName]) {
            const accounts = cashierAccountsData[selectedBankName];
            accounts.forEach(account => {
                const option = document.createElement('option');
                option.value = account.id;
                option.textContent = account.number_display;
                accountNumberSelect.appendChild(option);
            });
        }
        togglePaymentFields();
    }

    function calculateNetPayable() {
        const approvedVal = parseFloat(approved?.value) || 0;
        const clearedVal = parseFloat(amountToClear?.value) || 0;
        const advanceVal = parseFloat(advance?.value) || 0;
        const redemptionVal = parseFloat(redemptionAvailable?.value) || 0;
        let redemptionToUseInput = parseFloat(redemptionUse?.value) || 0;

        const advanceUsed = Math.min(advanceVal, clearedVal);
        const debtAfterAdvance = clearedVal - advanceUsed;
        let redemptionUsed = Math.min(redemptionToUseInput, redemptionVal);
        redemptionUsed = Math.min(redemptionUsed, debtAfterAdvance);

        if (redemptionUse && redemptionToUseInput !== redemptionUsed) {
            redemptionUse.value = redemptionUsed.toFixed(2);
        }

        const netPayable = Math.max(0, debtAfterAdvance - redemptionUsed);
        if (netPayableDisplay) netPayableDisplay.value = netPayable.toFixed(2);

        const isProofRequired = netPayable > 0.005 || advanceUsed > 0 || redemptionUsed > 0;
        toggleRequired(proofInput, isProofRequired);
    }

    function togglePaymentFields() {
        const mode = paymentModeSelect.value.toLowerCase();

        bankFields.forEach(f => f.style.display = 'none');
        chequeField.style.display = 'none';
        upiField.style.display = 'none';
        
        if (bankNameField) bankNameField.style.display = 'none';
        if (accountNumberField) accountNumberField.style.display = 'none';
        
        toggleRequired(bankNameSelect, false);
        toggleRequired(accountNumberSelect, false);
        toggleRequired(bankAccountField, false);
        toggleRequired(bankIfscField, false);
        toggleRequired(chequeNoField, false);
        toggleRequired(upiIdField, false);

        let refLabel = 'Reference';

        if (mode === 'bank transfer' || mode === 'cheque') {
            if (bankNameField) {
                bankNameField.style.display = 'block';
                toggleRequired(bankNameSelect, true);
            }
            
            if (accountNumberField && bankNameSelect.value) {
                accountNumberField.style.display = 'block';
                toggleRequired(accountNumberSelect, true);
            }
            
            bankFields.forEach(f => f.style.display = 'block');
            toggleRequired(bankAccountField, true);
            toggleRequired(bankIfscField, true);

            if (mode === 'cheque') {
                chequeField.style.display = 'block';
                toggleRequired(chequeNoField, true);
                refLabel = 'Reference (Cheque Ref)';
            } else {
                refLabel = 'Reference (UTR/Transaction ID)';
            }
        } else if (mode === 'upi') {
            upiField.style.display = 'block';
            toggleRequired(upiIdField, true);
            refLabel = 'Reference (Transaction ID)';
        } else if (mode === 'cash') {
            refLabel = 'Reference (Cash Batch #)';
        } else if (mode === 'advance') {
            refLabel = 'Reference (Internal Note)';
        }

        const refLabelElement = document.getElementById('reference_label');
        if (refLabelElement) {
            refLabelElement.textContent = refLabel;
        }
        
        calculateNetPayable();
    }

    if (amountToClear) amountToClear.addEventListener('input', calculateNetPayable);
    if (redemptionUse) redemptionUse.addEventListener('input', calculateNetPayable);
    if (paymentModeSelect) paymentModeSelect.addEventListener('change', togglePaymentFields);
    if (bankNameSelect) bankNameSelect.addEventListener('change', updateAccountDropdowns);

    calculateNetPayable();
    togglePaymentFields();
});
</script>

<?php
} else {
    // List view
    $rows = exeSql("SELECT * FROM payment_requests WHERE status='READY_FOR_CASHIER' ORDER BY created_at ASC");
?>
    <?php if(!$rows): ?>
        <div class="alert alert-info">No requests pending at cashier.</div>
    <?php else: ?>
        <?php foreach($rows as $r):
            $payload = json_decode($r['payload_json'] ?? '{}', true) ?: [];
            $entityId = i($r['vendor_id'] ?? $r['employee_id'] ?? 0);
            $entityType = $r['request_type'];
            $entityName = get_entity_name($entityId, $entityType, $payload);
        ?>
            <div class="card p-3 mb-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <div><strong><?= h(ucfirst($r['request_type'])) ?> Payment for <?= h($entityName) ?></strong></div>
                        <div class="muted"><?= h($r['created_at']) ?></div>
                    </div>
                    <div>
                        <span class="badge bg-info">With Cashier</span>
                    </div>
                </div>
                <div class="mt-2">
                    <a class="btn btn-sm btn-primary" href="payment1.php?rid=<?= $r['request_id'] ?>">Open & Pay</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php } ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>