<?php
require_once("../functions.php");
print_r($_REQUEST);
// declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

/* ---------------- CACHE TABLE COLUMNS ---------------- */
function cols(string $table): array
{
    global $dbObj;
    static $cache = [];
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if (isset($cache[$table])) return $cache[$table];
    
    $res = $dbObj->getAllResults("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table'");
    $out = [];
    if ($res) {
        foreach ($res as $row) {
            $out[$row['COLUMN_NAME']] = true;
        }
    }
    $cache[$table] = $out;
    return $out;
}

/* ---------------- SAFE UPDATE ---------------- */
function safe_update(string $table, array $data, string $where)
{
    global $dbObj;
    $c = cols($table);
    if (!$c) return false;
    $sets = [];

    foreach ($data as $k => $v) {
        if (!isset($c[$k])) continue;
        $col = preg_replace('/[^a-zA-Z0-9_]/', '', $k);
        if (is_string($v) && (strpos($v, ' + ') !== false || strpos($v, ' - ') !== false)) {
            $sets[] = "`$col`=" . $v;
        } elseif ($v === null) {
            $sets[] = "`$col`=NULL";
        } elseif (is_numeric($v)) {
            $sets[] = "`$col`=" . $v;
        } else {
            $sets[] = "`$col`='" . DataBasePDO::escape($v) . "'";
        }
    }

    if (!$sets || trim($where) === '') {
        throw new RuntimeException("safe_update() missing WHERE or empty data for $table");
    }

    $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE $where";
    $affected = $dbObj->executeQuery($sql);
    if ($affected === false) {
        throw new RuntimeException("Update Error for $table");
    }
    return $affected;
}

/* ---------------- HELPERS ---------------- */
function v($k, $d = null)
{
    return $_POST[$k] ?? $_GET[$k] ?? $d;
}
function i($x)
{
    return is_numeric($x) ? (int)$x : 0;
}
function f($x)
{
    return is_numeric($x) ? (float)$x : 0.0;
}
function s($x)
{
    return trim((string)($x ?? ''));
}
function h($x)
{
    return htmlspecialchars((string)$x, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function now()
{
    return date('Y-m-d H:i:s');
}

function table_exists(string $t): bool
{
    global $dbObj;
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', $t);
    static $cache = [];
    if (isset($cache[$t])) return $cache[$t];

    $res = $dbObj->getAllResults("SHOW TABLES LIKE '$t'");
    $exists = $res && count($res) > 0;
    $cache[$t] = $exists;
    return $exists;
}

function has_col(string $table, string $col): bool
{
    $c = cols($table);
    return isset($c[$col]);
}

function safe_insert(string $table, array $data)
{
    global $dbObj;
    $c = cols($table);
    if (!$c) return false;
    $clean = [];
    foreach ($data as $k => $v) {
        if (isset($c[$k])) $clean[$k] = $v;
    }
    if (!$clean) return false;
    return $dbObj->insertData($table, $clean);
}

/* ---------------- UPLOAD PROOF ---------------- */
function upload_proof(array $file): ?string
{
    // Check if file exists and has no error (if file is uploaded)
    if (!$file || empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null; // Proof is optional, so return null if no file uploaded
    }

    // Otherwise, proceed with file upload validation
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    if (!in_array($ext, $allowed, true)) return null;
    
    $dir = __DIR__ . '/proofs/';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    
    $name = 'proof_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $path = $dir . $name;

    // Move uploaded file to the destination
    if (!move_uploaded_file($file['tmp_name'], $path)) return null;

    // Return the file path
    return 'proofs/' . $name;
}

/* ---------------- VOUCHER & INVOICE GENERATORS ---------------- */
function generate_voucher_no(): string
{
    global $dbObj;
    do {
        $code = 'VCH-' . date('Ymd') . '-' . date('His') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        $exist = false;
        foreach (['employee_salary_payments', 'expenses', 'vendor_grn_payments', 'event_items', 'expenses_payments', 'fixed_expenses'] as $t) {
            if (table_exists($t) && has_col($t, 'voucher_no')) {
                $r = $dbObj->getAllResults("SELECT 1 FROM `$t` WHERE voucher_no='" . DataBasePDO::escape($code) . "' LIMIT 1");
                if ($r && count($r) > 0) {
                    $exist = true;
                    break;
                }
            }
        }
    } while ($exist);
    return $code;
}

function generate_invoice_no(): string
{
    global $dbObj;
    do {
        $code = 'INV-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        $exist = false;
        foreach (['employee_salary_payments', 'expenses', 'vendor_grn_payments', 'event_items', 'expenses_payments', 'fixed_expenses'] as $t) {
            if (table_exists($t) && has_col($t, 'invoice_no')) {
                $r = $dbObj->getAllResults("SELECT 1 FROM `$t` WHERE invoice_no='" . DataBasePDO::escape($code) . "' LIMIT 1");
                if ($r && count($r) > 0) {
                    $exist = true;
                    break;
                }
            }
        }
    } while ($exist);
    return $code;
}

/* ---------------- ENTITY / BANK HELPERS ---------------- */
function get_entity_name(int $id, string $type, array $payload): string
{
    global $dbObj;
    if ($id <= 0) {
        if ($type === 'expenses') {
            $p = s($payload['purpose'] ?? '');
            $c = s($payload['custom_purpose'] ?? '');
            return h($p === '__other__' ? $c : ($p ?: 'General Expense'));
        }
        return "N/A";
    }

    $name = '';

    if ($type === 'vendor') {
        $r = $dbObj->selectData('vendors', ['vendor_id' => $id], ['vendor_name'], 1);
        $name = $r[0]['vendor_name'] ?? "Vendor N/A";
    } elseif ($type === 'employee') {
        $r = $dbObj->selectData('employees', ['id' => $id], ['employee_name'], 1);
        $name = $r[0]['employee_name'] ?? "Employee N/A";
    } elseif ($type === 'fixed') {
        $fixedId = i($payload['fixed_id'] ?? 0);
        $r = $dbObj->selectData('fixed_expenses', ['id' => $fixedId], ['expense_type'], 1);
        $name = ucfirst($r[0]['expense_type'] ?? "Fixed Expense N/A");
    } else {
        $p = s($payload['purpose'] ?? '');
        $c = s($payload['custom_purpose'] ?? '');
        $name = h($p === '__other__' ? $c : ($p ?: 'General Expense'));
    }

    return $name;
}

function get_entity_balances(int $id, string $type): array
{
    global $dbObj;
    $default = ['advance' => 0.0, 'redemption_points' => 0.0];

    if ($id <= 0) {
        return $default;
    }

    $has_redemption_points = has_col('vendor_totals', 'redemption_points');
    $redemption_cols = $has_redemption_points ? ['advance', 'redemption_points'] : ['advance'];

    if ($type === 'employee') {
        $r = $dbObj->selectData('employees', ['id' => $id], ['advance'], 1);
        return $r && isset($r[0]['advance']) ? ['advance' => (float)$r[0]['advance'], 'redemption_points' => 0.0] : $default;
    } elseif ($type === 'vendor' || $type === 'fixed') {
        $r = $dbObj->selectData('vendor_totals', ['vendor_id' => $id], $redemption_cols, 1);
        if ($r && isset($r[0]['advance'])) {
            return [
                'advance' => (float)$r[0]['advance'],
                'redemption_points' => $has_redemption_points ? (float)($r[0]['redemption_points'] ?? 0.0) : 0.0,
            ];
        }
    }
    return $default;
}

function get_entity_bank_details(int $entityId, string $entityType, int $fixedExpenseId): array
{
    global $dbObj;
    $default = ['account_number' => '', 'ifsc' => ''];
    if ($entityId <= 0 && $fixedExpenseId <= 0) return $default;

    if ($entityType === 'vendor') {
        $r = $dbObj->selectData('vendors', ['vendor_id' => $entityId], ['account_number', 'ifsc'], 1);
        if ($r && isset($r[0]['account_number'])) {
            return [
                'account_number' => s($r[0]['account_number']),
                'ifsc' => s($r[0]['ifsc']),
            ];
        }
    } elseif ($fixedExpenseId > 0) {
        $r = $dbObj->selectData('fixed_expenses', ['id' => $fixedExpenseId], ['account_no', 'ifsc_code'], 1);
        if ($r && isset($r[0]['account_no'])) {
            return [
                'account_number' => s($r[0]['account_no']),
                'ifsc' => s($r[0]['ifsc_code']),
            ];
        }
    }
    return $default;
}

function get_cashier_bank_accounts(): array
{
    global $dbObj;
    $bankNames = [];
    $accountsByBank = [];

    if (!table_exists('bank_accounts')) {
        return ['names' => $bankNames, 'json' => json_encode($accountsByBank)];
    }

    $accounts = $dbObj->getAllResults("SELECT id, bank_name, account_number FROM bank_accounts ORDER BY bank_name, account_number ASC");

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
    $rid = i(v('request_id', 0));
    $userAmountToClear = f(v('amount_to_clear', 0));

    $ref = s(v('payment_reference', ''));
    $mode = s(v('payment_mode', ''));

    $disbursedBankAccountId = i(v('disbursed_bank_account_id', 0));
    $userMode = strtolower($mode);
    $isBankPayment = in_array($userMode, ['bank transfer', 'cheque', 'upi']);

    $idToStore = ($isBankPayment && $disbursedBankAccountId > 0) ? $disbursedBankAccountId : null;

    if (($userMode === 'bank transfer' || $userMode === 'cheque') && $idToStore === null) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'A disbursing bank account number must be selected for Bank Transfer or Cheque payments.'];
        header("Location: payment?rid=" . $rid);
        exit;
    }

    if (empty($ref)) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Payment reference is compulsory for all payments.'];
        header("Location: payment?rid=" . $rid);
        exit;
    }
    if ($rid <= 0 || $userAmountToClear <= 0) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Invalid Request ID or Amount to Clear.'];
        header("Location: payment?rid=" . $rid);
        exit;
    }

    $row = $dbObj->selectData('payment_requests', ['request_id' => $rid], null, 1);
    if (!$row) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Request not found.'];
        header("Location: dashboard");
        exit;
    }
    $req = $row[0];
    if (($req['status'] ?? '') !== 'READY_FOR_CASHIER') {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'This request is not pending with Cashier.'];
        header("Location: dashboard");
        exit;
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

    $advanceUsed = min($currentAdvance, $userAmountToClear);
    $debtAfterAdvance = $userAmountToClear - $advanceUsed;
    $redemptionUsed = min($redemptionUsedInput, $currentPoints);
    $redemptionUsed = min($redemptionUsed, $debtAfterAdvance);
    $cashBankAmount = max(0, $debtAfterAdvance - $redemptionUsed);
    $paid = $redemptionUsed + $advanceUsed + $cashBankAmount;
    $paid = min($approvedAmount, $paid);

    if ($paid < 0.01) {
        if ($redemptionUsed > 0 && $advanceUsed > 0) {
            $dbMethod = 'redemption_advance';
        } elseif ($redemptionUsed > 0) {
            $dbMethod = 'redemption';
        } elseif ($advanceUsed > 0) {
            $dbMethod = 'advance';
        } else {
            $dbMethod = 'cash';
        }
    } elseif ($userMode === 'bank transfer' || $userMode === 'cheque') {
        $dbMethod = 'bank';
    } elseif ($userMode === 'upi') {
        $dbMethod = 'online';
    } else {
        $dbMethod = 'cash';
    }

    // Attempt to upload if file exists, otherwise $proof remains null. 
    // No error is thrown if missing.
    $proof = upload_proof($_FILES['proof'] ?? []);


    $paidAt = s(v('paid_at', '')) ?: now();
    $note = s(v('note', ''));
    $paidBy = s(v('payment_by', '')) ?: (string)($_SESSION['user_name'] ?? 'cashier');
    $voucherNo = generate_voucher_no();
    $invoiceNo = s(v('preview_invoice_number_hidden', ''));
    if (empty($invoiceNo) || $req['request_type'] !== 'vendor' || strpos($invoiceNo, 'Generated') !== false) {
        $invoiceNo = generate_invoice_no();
    }

    $bankAccount = s(v('bank_account'));
    $bankIfsc = s(v('bank_ifsc'));
    $chequeNo = s(v('cheque_no'));
    $upiId = s(v('upi_id'));
    $bankMode = null;

    if ($dbMethod === 'bank') {
        $bankMode = $userMode === 'cheque' ? 'cheque' : 'transfer';
        if (empty($chequeNo)) {
            $chequeNo = $userMode === 'cheque' ? $ref : null;
        }
    } else {
        $bankAccount = null;
        $bankIfsc = null;
        $chequeNo = null;
    }

    if ($dbMethod === 'online') {
        if (empty($upiId)) {
            $upiId = $userMode === 'upi' ? $ref : null;
        }
    } else {
        $upiId = null;
    }

    if (in_array($dbMethod, ['advance', 'redemption', 'redemption_advance', 'cash'])) {
        $bankAccount = null;
        $bankIfsc = null;
        $chequeNo = null;
        $upiId = null;
        $bankMode = null;
        $idToStore = null;
    }

    if ($userMode === 'upi') {
        $idToStore = null;
    }

    $vendor_has_redemption_used = has_col('vendor_grn_payments', 'redemption_used');
    $fixed_has_redemption_used = has_col('fixed_expenses', 'redemption_used');
    $expenses_has_disbursed_bank_id = has_col('expenses', 'disbursed_bank_account_id');
    $employee_has_disbursed_bank_id = has_col('employee_salary_payments', 'disbursed_bank_account_id');
    $fixed_has_disbursed_bank_id = has_col('fixed_expenses', 'disbursed_bank_account_id');
    $vendor_has_disbursed_bank_id = has_col('vendor_grn_payments', 'disbursed_bank_account_id');
    $has_redemption_points = has_col('vendor_totals', 'redemption_points');

    if ($req['request_type'] === 'vendor' && !has_col('goods_receipts', 'paid_amount')) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => "CRITICAL ERROR: The 'goods_receipts' table is missing the required 'paid_amount' column."];
        header("Location: payment?rid=" . $rid);
        exit;
    }

    $dbObj->beginTransaction();

    try {
        /* -------- MODIFIED vendor flow using grn_splits from payload -------- */
        if ($req['request_type'] === 'vendor' && $entityId > 0) {
            $grnSplits = $payload['grn_splits'] ?? [];
            
            if (empty($grnSplits)) {
                throw new RuntimeException("Vendor payment requested without valid GRN splits in payload.");
            }

            if (!table_exists('goods_receipts') || !table_exists('vendor_grn_payments')) {
                throw new RuntimeException("Missing essential tables ('goods_receipts' or 'vendor_grn_payments') for vendor flow.");
            }

            $targetGrnIds = array_map(fn($split) => (int)$split['grn_id'], $grnSplits);
            $targetGrnIds = array_filter($targetGrnIds);
            
            if (empty($targetGrnIds)) {
                throw new RuntimeException("No valid GRN IDs found in grn_splits.");
            }

            $grn_ids_string = implode(',', $targetGrnIds);
            $grnDetails = $dbObj->getAllResults("SELECT grn_id, total_amount, transportation, paid_amount FROM goods_receipts WHERE grn_id IN ($grn_ids_string)");

            $grnTotalBill = [];
            $grnPaidTotal = [];
            $grnOutstanding = [];

            foreach ($grnDetails as $detail) {
                $grnId = (int)$detail['grn_id'];
                $totalBill = (float)$detail['total_amount'] + (float)$detail['transportation'];
                $totalPaid = (float)($detail['paid_amount'] ?? 0.0);
                $outstanding = $totalBill - $totalPaid;
                

                $grnTotalBill[$grnId] = $totalBill;
                $grnPaidTotal[$grnId] = $totalPaid;
                $grnOutstanding[$grnId] = $outstanding;
            }

            $grnPaymentMap = [];
            $totalAllocatedFromMap = 0.0;

            foreach ($grnSplits as $split) {
                $grnId = (int)$split['grn_id'];
                $splitAmount = (float)$split['amount'];
                // echo $grnId;
                // echo $splitAmount;
                if (!isset($grnOutstanding[$grnId])) {
                    throw new RuntimeException("GRN ID $grnId from splits not found in database.");
                }
                
                if ($splitAmount > $grnOutstanding[$grnId] + 0.01) {
                    throw new RuntimeException("Split amount for GRN $grnId (₹" . number_format($splitAmount, 2) . ") exceeds outstanding amount (₹" . number_format($grnOutstanding[$grnId], 2) . ").");
                }
                
                $grnPaymentMap[$grnId] = $splitAmount;
                $totalAllocatedFromMap += $splitAmount;
            }

            if (abs($totalAllocatedFromMap - $paid) > 0.02) {
                throw new RuntimeException("Total GRN splits amount (₹" . number_format($totalAllocatedFromMap, 2) . ") doesn't match paid amount (₹" . number_format($paid, 2) . ").");
            }

            $paid = $totalAllocatedFromMap;

            if ($totalAllocatedFromMap > 0) {
                $advanceRemainingDist = $advanceUsed;
                $redemptionRemainingDist = $redemptionUsed;

                $paymentInsertRows = [];
                $grnPaidUpdates = [];

                foreach ($grnSplits as $split) {
                    $grnId = (int)$split['grn_id'];
                    $paymentForGrn = $grnPaymentMap[$grnId] ?? 0.0;
                    
                    if ($paymentForGrn <= 0) continue;

                    $ratio = $paymentForGrn / $totalAllocatedFromMap;
                    $advanceForGrn = round($advanceUsed * $ratio, 2);
                    $redemptionForGrn = round($redemptionUsed * $ratio, 2);

                    $advanceForGrn = min($advanceForGrn, $advanceRemainingDist);
                    $redemptionForGrn = min($redemptionForGrn, $redemptionRemainingDist);

                    $advanceRemainingDist -= $advanceForGrn;
                    $redemptionRemainingDist -= $redemptionForGrn;

                    $cashBankForGrn = max(0, $paymentForGrn - $advanceForGrn - $redemptionForGrn);
                    $paymentTableAmount = $cashBankForGrn;

                    if (!$vendor_has_redemption_used) {
                        $paymentTableAmount += $redemptionForGrn;
                    }

                    $row = [
                        'vendor_id' => $entityId,
                        'grn_id' => $grnId,
                        'branch_id' => $req['branch_id'] ?? null,
                        'amount' => $paymentTableAmount,
                        'advance_used' => $advanceForGrn,
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
                    ];

                    if ($vendor_has_redemption_used) {
                        $row['redemption_used'] = $redemptionForGrn;
                    }

                    if ($vendor_has_disbursed_bank_id) {
                        $row['disbursed_bank_account_id'] = $idToStore;
                    }

                    $paymentInsertRows[] = $row;
                    $fullAmountPaidToGrn = $paymentForGrn;
                    $grnPaidUpdates[$grnId] = ($grnPaidUpdates[$grnId] ?? 0) + $fullAmountPaidToGrn;
                }

                if (!empty($paymentInsertRows)) {
                    foreach ($paymentInsertRows as $payRow) {
                        // Remove null values to avoid deprecated warnings
                        $cleanPayRow = array_filter($payRow, function($value) {
                            return $value !== null;
                        });
                        
                        // Debug: Log what we're trying to insert
                        error_log("Inserting payment row: " . json_encode($cleanPayRow));
                        
                        $insertResult = $dbObj->insertData('vendor_grn_payments', $cleanPayRow);
                        
                        if (!$insertResult) {
                            throw new RuntimeException("Failed to insert vendor_grn_payments record for GRN ID: " . $cleanPayRow['grn_id']);
                        }
                    }
                }

                if (!empty($grnPaidUpdates)) {
                    foreach ($grnPaidUpdates as $gid => $amt) {
                        $sql = "UPDATE goods_receipts SET paid_amount = paid_amount + " . (float)$amt . ", updated_at = '" . now() . "' WHERE grn_id = $gid";
                        $dbObj->executeQuery($sql);
                    }
                }
            } else {
                throw new RuntimeException("Payment allocation failed, the calculated payment to GRNs is zero or negative.");
            }

            if (table_exists('vendor_totals')) {
                $vendor_totals_update = [
                    'total_paid' => 'total_paid + ' . (float)$paid,
                    'advance' => 'advance - ' . (float)$advanceUsed,
                    'balance' => 'total_bill - (total_paid + ' . (float)$paid . ')'
                ];
                
                if ($has_redemption_points) {
                    $vendor_totals_update['redemption_points'] = 'redemption_points - ' . (float)$redemptionUsed;
                }
                
                safe_update('vendor_totals', $vendor_totals_update, "vendor_id=" . $entityId . " LIMIT 1");
            }

        } elseif ($req['request_type'] === 'employee' && $entityId > 0) {
            $empPaidAmount = $cashBankAmount + $redemptionUsed;
            if (table_exists('employee_salary_payments')) {
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
                ];
                
               if (empty($data['disbursed_bank_account_id'])) {
    $data['disbursed_bank_account_id'] = null;
}


                safe_insert('employee_salary_payments', $insertData);
            }
            if (table_exists('employees')) {
                safe_update('employees', [
                    'last_paid_period' => s($payload['pay_period'] ?? ''),
                    'advance' => 'advance - ' . (float)$advanceUsed,
                ], "id=" . $entityId . " LIMIT 1");
            }

        } elseif ($req['request_type'] === 'fixed' && $fixedExpenseId > 0) {
            $fixed_expense_update = [
                'balance_paid' => 'balance_paid + ' . (float)$paid,
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
            ];

            if ($fixed_has_redemption_used) {
                $fixed_expense_update['redemption_used'] = (float)$redemptionUsed;
            }

            if ($fixed_has_disbursed_bank_id) {
                $fixed_expense_update['disbursed_bank_account_id'] = $idToStore;
            }

            $updateSuccess = safe_update('fixed_expenses', $fixed_expense_update, "id=" . $fixedExpenseId . " LIMIT 1");
            if (!$updateSuccess) throw new RuntimeException("Failed to update fixed_expenses table (ID: $fixedExpenseId).");

            if ($advanceUsed > 0 && $entityId > 0) {
                $linkedEntityType = $payload['linked_entity_type'] ?? 'vendor';
                $advanceTable = $linkedEntityType === 'employee' ? 'employees' : 'vendor_totals';
                $advanceIdCol = $linkedEntityType === 'employee' ? 'id' : 'vendor_id';
                safe_update($advanceTable, ['advance' => 'advance - ' . (float)$advanceUsed], "$advanceIdCol=" . $entityId . " LIMIT 1");
            }

            if ($redemptionUsed > 0 && $entityId > 0) {
                $linkedEntityType = $payload['linked_entity_type'] ?? 'vendor';
                if ($linkedEntityType === 'vendor' && table_exists('vendor_totals') && $has_redemption_points) {
                    safe_update('vendor_totals', ['redemption_points' => 'redemption_points - ' . (float)$redemptionUsed], "vendor_id=" . $entityId . " LIMIT 1");
                }
            }

        } else {
            $purpose = s($payload['purpose'] ?? $payload['custom_purpose'] ?? 'General Expense');
            $purposeEscaped = DataBasePDO::escape($purpose);

            $expenseRow = $dbObj->getAllResults("SELECT id FROM expenses WHERE purpose='" . $purposeEscaped . "' ORDER BY created_at DESC LIMIT 1");
            $expenseId = i($expenseRow[0]['id'] ?? 0);

            if ($expenseId) {
                $updateData = [
                    'balance_paid' => 'balance_paid + ' . (float)$paid,
                    'method' => strtolower($mode),
                    'voucher_no' => $voucherNo,
                    'invoice_no' => $invoiceNo,
                    'paid_at' => $paidAt,
                    'remark' => $note,
                    'payment_by' => $paidBy,
                    'advance' => (float)$advanceUsed,
                    'payment_reference' => $ref,
                ];
                
                if ($expenses_has_disbursed_bank_id) {
                    $updateData['disbursed_bank_account_id'] = $idToStore;
                }

                safe_update('expenses', $updateData, "id=" . $expenseId . " LIMIT 1");
            }
        }

        safe_update('payment_requests', [
            'status' => 'PAID',
            'approved_at' => $req['approved_at'] ?? null,
            'total_amount' => $req['total_amount'] ?? null,
            'updated_at' => now(),
        ], "request_id=" . $rid . " LIMIT 1");

        if (table_exists('payment_actions')) {
            safe_insert('payment_actions', [
                'request_id' => $rid,
                'action' => 'PAYMENT_POSTED',
                'actor_id' => (int)($_SESSION['user_id'] ?? 1),
                'comment' => 'Payment disbursed by Cashier',
                'acted_at' => now(),
            ]);
        }

        $dbObj->commit();

        $_SESSION['flash_message'] = [
            'type' => 'success',
            'text' => 'Payment for Request ID ' . $rid . ' successfully updated and marked as PAID. Invoice No: ' . h($invoiceNo) . '.',
        ];
        header("Location: dashboard");
        exit;
    } catch (Throwable $e) {
        $dbObj->rollBack();
        error_log("CASHIER PAYMENT FATAL ERROR: " . $e->getMessage() . " on line " . $e->getLine());
        $_SESSION['flash_message'] = [
            'text' => 'Payment failed: ' . h($e->getMessage()),
            'type' => 'danger'
        ];
        header("Location: payment?rid=" . $rid);
        exit;
    }
}

/* ---------------- VIEW (HTML UI) ---------------- */
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
        body {
            font-family: system-ui, Arial, sans-serif;
            padding: 20px;
        }

        .card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
        }

        .muted {
            color: #64748b;
            font-size: 13px
        }

        .badge-status {
            font-weight: 700
        }

        .readonly {
            background: #f8fafc
        }

        .field-required::before {
            content: '* ';
            color: red;
        }
    </style>
</head>

<body class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Cashier – Payments</h3>
        <a class="btn btn-outline-secondary" href="dashboard">Cashier Dashboard</a>
    </div>

    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= h($flashMessage['type']) ?> alert-dismissible fade show" role="alert">
            <?= h($flashMessage['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php
    if ($rid > 0) {
        $row = $dbObj->selectData('payment_requests', ['request_id' => $rid], null, 1);
        if (!$row) {
            echo '<div class="alert alert-warning">Request not found.</div>';
            exit;
        }
        $r = $row[0];
        $payload = json_decode($r['payload_json'] ?? '{}', true) ?: [];

        $title = ucfirst($r['request_type']) . ' Payment';
        $meta = [];
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
            $meta[] = 'Vendor: ' . h($entityName);
            $nums = [];
            
            $grnSplits = $payload['grn_splits'] ?? [];
            if (!empty($grnSplits)) {
                $ids = implode(',', array_map(fn($s) => (int)$s['grn_id'], $grnSplits));
            } else {
                $ids = implode(',', array_map('intval', (array)($payload['grn_ids'] ?? [])));
            }
            
            if ($ids) {
                $rs = $dbObj->getAllResults("SELECT grn_number, invoice_number FROM goods_receipts WHERE grn_id IN ($ids)");
                if ($rs) foreach ($rs as $x) {
                    $nums[] = $x['grn_number'];
                    if ($x['invoice_number'] && strpos($previewInvoice, $x['invoice_number']) === false) {
                        $previewInvoice .= ($previewInvoice ? '/' : '') . $x['invoice_number'];
                    }
                }
            }
            if ($nums) $meta[] = 'GRN(s): ' . implode(',', $nums);
            if (empty($previewInvoice)) {
                $previewInvoice = 'INV-GRN-MISSING (Generated)';
            }
            $entityBankDetails = get_entity_bank_details($entityId, $entityType, 0);
        } elseif ($r['request_type'] === 'employee') {
            $entityId = i($r['employee_id'] ?? 0);
            $entityName = get_entity_name($entityId, $entityType, $payload);
            $meta[] = 'Employee: ' . h($entityName);
            $meta[] = 'Period: ' . h((string)($payload['pay_period'] ?? ''));
            $previewInvoice = generate_invoice_no();
        } elseif ($r['request_type'] === 'fixed') {
            $fixedExpenseId = i($payload['fixed_id'] ?? 0);
            $entityId = i($r['entity_id'] ?? 0);
            $entityName = get_entity_name($entityId, $entityType, $payload);
            $meta[] = 'Fixed Expense: ' . h($entityName);

            $feRow = $dbObj->selectData('fixed_expenses', ['id' => $fixedExpenseId], ['expense_type', 'amount', 'balance_paid'], 1);
            if ($feRow) {
                $expenseTotal = f($feRow[0]['amount'] ?? 0);
                $expensePaid = f($feRow[0]['balance_paid'] ?? 0);
                $expenseBalance = $expenseTotal - $expensePaid;
                $meta[] = 'Balance: ' . number_format($expenseBalance, 2);
            }
            $previewInvoice = generate_invoice_no();
            $entityType = 'fixed_expense';
            $entityBankDetails = get_entity_bank_details($entityId, 'fixed_expense', $fixedExpenseId);
        } else {
            $entityName = get_entity_name(0, $entityType, $payload);
            $meta[] = 'Purpose: ' . h($entityName);
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

        $netPayableInitial = $approvedAmount;
        $initialAmountToClear = $approvedAmount;

        $redemptionAvailable = number_format($redemptionPoints, 2, '.', '');
        $isRedemptionAvailable = $redemptionPoints > 0.005;
    ?>
        <div class="card p-3 mb-4">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="h5 mb-1"><?= h($title) ?> for <?= h($entityName) ?></div>
                    <?php foreach ($meta as $m): ?>
                        <div class="muted"><?= $m ?></div>
                    <?php endforeach; ?>
                </div>
                <div>
                    <span class="badge bg-<?= $r['status'] === 'READY_FOR_CASHIER' ? 'info' : ($r['status'] === 'PAID' ? 'secondary' : 'primary') ?> badge-status">
                        <?= h($r['status']) ?>
                    </span>
                </div>
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
                <input type="hidden" id="approved_amount_value" value="<?= htmlspecialchars(number_format($approvedAmount ?: 0, 2, '.', '')) ?>">

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Total Approved (Readonly)</label>
                        <input type="number" step="0.01" id="approved_amount" class="form-control readonly"
                            value="<?= htmlspecialchars(number_format($approvedAmount ?: 0, 2, '.', '')) ?>" readonly>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Cash Advance Available (R/O)</label>
                        <input type="number" step="0.01" id="advance_available" name="advance_available_readonly" class="form-control readonly"
                            value="<?= htmlspecialchars(number_format($advanceAmount, 2, '.', '')) ?>" readonly>
                    </div>

                    <div class="col-md-3" id="redemption_points_block" style="<?= ($r['request_type'] === 'vendor' || ($r['request_type'] === 'fixed' && $entityId > 0)) ? '' : 'display:none;' ?>">
                        <label class="form-label">Redemption Points Available (R/O)</label>
                        <input type="number" step="0.01" id="redemption_points_available" class="form-control readonly"
                            value="<?= $redemptionAvailable ?>" readonly>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Net Payable (Cash/Bank) - Readonly</label>
                        <input type="number" step="0.01" id="net_payable_display" class="form-control readonly"
                            value="<?= htmlspecialchars(number_format($netPayableInitial, 2, '.', '')) ?>" readonly>
                        <input type="hidden" name="net_payable_cash_bank" id="net_payable_hidden" value="<?= htmlspecialchars(number_format($netPayableInitial, 2, '.', '')) ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label field-required">Amount to Clear </label>
                        <input type="number" step="0.01" min="0.01" id="amount_to_clear" name="amount_to_clear" class="form-control readonly"
                            value="<?= htmlspecialchars(number_format($approvedAmount ?: 0, 2, '.', '')) ?>" readonly>
                    </div>

                    <div class="col-md-3" id="redemption_use_block_input" style="<?= ($r['request_type'] === 'vendor' || ($r['request_type'] === 'fixed' && $entityId > 0)) ? '' : 'display:none;' ?>">
                        <label class="form-label">Redemption Points to Use (1 Point = ₹1)</label>
                        <input type="number" min="0.00" id="redemption_points_use" name="redemption_points_use" class="form-control"
                            value="0.00" <?= $isRedemptionAvailable ? '' : 'readonly' ?>>
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
                        <label class="form-label disbursed-bank-label">Disbursing Bank Name </label>
                        <select class="form-select" id="bank_name_select">
                            <option value="">-- Select Bank Name --</option>
                            <?php foreach ($cashierBankNames as $bankName): ?>
                                <option value="<?= h($bankName) ?>">
                                    <?= h($bankName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3 bank-select-field" id="account_number_field" style="display:none;">
                        <label class="form-label disbursed-account-label">Account Number </label>
                        <select name="disbursed_bank_account_id" class="form-select" id="account_number_select">
                            <option value="">-- Select Account Number --</option>
                        </select>
                    </div>
                    <div class="col-md-3 bank-fields" style="display:none;">
                        <label class="form-label bank-account-label">Recipient Bank Account #</label>
                        <input type="text" name="bank_account" class="form-control" placeholder="Account Number"
                            value="<?= ($entityType === 'vendor' || $fixedExpenseId > 0) ? h($entityBankDetails['account_number']) : '' ?>">
                    </div>
                    <div class="col-md-3 bank-fields" style="display:none;">
                        <label class="form-label bank-ifsc-label">Recipient Bank IFSC</label>
                        <input type="text" name="bank_ifsc" class="form-control" placeholder="IFSC Code"
                            value="<?= ($entityType === 'vendor' || $fixedExpenseId > 0) ? h($entityBankDetails['ifsc']) : '' ?>">
                    </div>
                    <div class="col-md-3 cheque-field" style="display:none;">
                        <label class="form-label cheque-no-label">Cheque Number</label>
                        <input type="text" name="cheque_no" class="form-control" placeholder="Cheque #">
                    </div>
                    <div class="col-md-3 upi-field" style="display:none;">
                        <label class="form-label upi-id-label">UPI ID</label>
                        <input type="text" name="upi_id" class="form-control" placeholder="yourname@bank">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" id="reference_label">Reference </label>
                        <input type="text" name="payment_reference" class="form-control" id="payment_reference_field">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label field-required">Voucher No (auto)</label>
                        <input type="text" class="form-control readonly" value="<?= h($previewVoucher) ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Invoice No (<?= $r['request_type'] === 'vendor' ? 'GRN' : 'auto' ?>)</label>
                        <input type="text" class="form-control readonly" value="<?= h($previewInvoice) ?>" readonly>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Payment By</label>
                        <input type="text" name="payment_by" class="form-control" placeholder="Cashier name" value="<?= h((string)($_SESSION['userName'] ?? 'cashier')) ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Note</label>
                        <input type="text" name="note" class="form-control" placeholder="Optional">
                    </div>
                    <div class="col-md-6 proof-field">
                        <label class="form-label proof-label">Upload Proof (jpg/png/pdf)</label>
                        <input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" class="form-control" id="proof-input" placeholder="Optional">
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-primary" <?= $r['status'] === 'READY_FOR_CASHIER' ? '' : 'disabled' ?>>Mark as Paid &amp; Go to Dashboard</button>
                    <a class="btn btn-outline-secondary" href="payment">Back to Cashier List</a>
                </div>
            </form>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const approved = document.getElementById('approved_amount');
                const advance = document.getElementById('advance_available');
                const amountToClear = document.getElementById('amount_to_clear');
                const netPayableDisplay = document.getElementById('net_payable_display');
                const netPayableHidden = document.getElementById('net_payable_hidden');
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
                const bankNameLabel = bankNameField ? bankNameField.querySelector('.form-label') : null;

                const accountNumberField = document.getElementById('account_number_field');
                const accountNumberSelect = document.getElementById('account_number_select');
                const accountNumberLabel = accountNumberField ? accountNumberField.querySelector('.form-label') : null;

                const cashierAccountsData = JSON.parse(document.getElementById('cashier_accounts_json').value);

                const initialBankAccount = bankAccountField ? bankAccountField.value : '';
                const initialBankIfsc = bankIfscField ? bankIfscField.value : '';
                const entityTypeInput = document.querySelector('input[name="entity_type"]');
                const entityType = entityTypeInput ? entityTypeInput.value.toLowerCase() : '';
                const fixedExpenseIdInput = document.querySelector('input[name="fixed_expense_id"]');
                const fixedExpenseId = fixedExpenseIdInput ? parseInt(fixedExpenseIdInput.value) || 0 : 0;

                function toggleRequired(field, label, isRequired) {
                    if (field) {
                        if (isRequired) {
                            field.setAttribute('required', 'required');
                            const targetLabel = label || field.closest('.col-md-3')?.querySelector('.form-label') || field.parentElement.querySelector('.form-label');
                            if (targetLabel) targetLabel.classList.add('field-required');
                        } else {
                            field.removeAttribute('required');
                            const targetLabel = label || field.closest('.col-md-3')?.querySelector('.form-label') || field.parentElement.querySelector('.form-label');
                            if (targetLabel) targetLabel.classList.remove('field-required');
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
                            option.value = account.id; // This is the ID we need to send
                            option.textContent = account.number_display;
                            accountNumberSelect.appendChild(option);
                        });
                    }

                    // Re-evaluate visibility and requirements
                    const mode = paymentModeSelect.value.toLowerCase();
                    
                    // Show account number field only if bank name is selected
                    if ((mode === 'bank transfer' || mode === 'cheque') && selectedBankName) {
                        if (accountNumberField) {
                            accountNumberField.style.display = 'block';
                            toggleRequired(accountNumberSelect, accountNumberLabel, true);
                        }
                    } else {
                        if (accountNumberField) {
                            accountNumberField.style.display = 'none';
                            toggleRequired(accountNumberSelect, accountNumberLabel, false);
                        }
                    }
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
                        redemptionToUseInput = redemptionUsed;
                    }

                    const netPayable = debtAfterAdvance - redemptionUsed;
                    const netPayableCashBank = Math.max(0, netPayable);

                    if (netPayableDisplay) netPayableDisplay.value = netPayableCashBank.toFixed(2);
                    if (netPayableHidden) netPayableHidden.value = netPayableCashBank.toFixed(2);

                    const totalClearedByInternal = advanceUsed + redemptionUsed;
                    
                    // Force false so no red asterisk appears and browser allows empty submit
                    toggleRequired(proofInput, proofLabel, false);
                }

                function togglePaymentFields() {
                    const mode = paymentModeSelect.value.toLowerCase();

                    bankFields.forEach(f => f.style.display = 'none');
                    chequeField.style.display = 'none';
                    upiField.style.display = 'none';

                    if (bankNameField) bankNameField.style.display = 'none';
                    if (accountNumberField) accountNumberField.style.display = 'none';

                    toggleRequired(bankNameSelect, bankNameLabel, false);
                    toggleRequired(accountNumberSelect, accountNumberLabel, false);

                    if (mode === 'cash' || mode === 'advance') {
                        bankNameSelect.value = '';
                        accountNumberSelect.value = '';
                    }

                    [bankAccountField, bankIfscField, chequeNoField, upiIdField, paymentReferenceField].forEach(f => {
                        if (f) f.removeAttribute('required');
                        const label = f?.closest('.col-md-3')?.querySelector('.form-label') || document.getElementById('reference_label');
                        if (label) label.classList.remove('field-required');
                        if (f === bankAccountField) f.value = initialBankAccount;
                        if (f === bankIfscField) f.value = initialBankIfsc;
                        if (f !== paymentReferenceField && f !== bankAccountField && f !== bankIfscField) f.value = '';
                    });

                    let refLabel = 'Reference ';
                    toggleRequired(paymentReferenceField, document.getElementById('reference_label'), true);

                    if (mode === 'bank transfer' || mode === 'cheque') {
                        if (bankNameField) {
                            bankNameField.style.display = 'block';
                            toggleRequired(bankNameSelect, bankNameLabel, true);
                        }

                        // Only show account number field if bank name is already selected
                        if (accountNumberField && bankNameSelect.value) {
                            accountNumberField.style.display = 'block';
                            toggleRequired(accountNumberSelect, accountNumberLabel, true);
                        }

                        bankFields.forEach(f => f.style.display = 'block');
                        toggleRequired(bankAccountField, document.querySelector('.bank-account-label'), true);
                        toggleRequired(bankIfscField, document.querySelector('.bank-ifsc-label'), true);

                        if (mode === 'cheque') {
                            chequeField.style.display = 'block';
                            toggleRequired(chequeNoField, document.querySelector('.cheque-no-label'), true);
                            refLabel = 'Reference (UTR/Transaction ID/Cheque Ref)';
                        } else if (mode === 'bank transfer') {
                            refLabel = 'Reference (UTR/Transaction ID)';
                        }

                    } else if (mode === 'upi') {
                        upiField.style.display = 'block';
                        toggleRequired(upiIdField, document.querySelector('.upi-id-label'), true);
                        refLabel = 'Reference (Transaction ID)';

                    } else if (mode === 'cash') {
                        refLabel = 'Reference (e.g., Cashier/Cash Count Batch #)';

                    } else if (mode === 'advance') {
                        refLabel = 'Reference (Internal Note/Ref)';
                    } else {
                        refLabel = 'Reference ';
                    }

                    const refLabelElement = document.getElementById('reference_label');
                    if (refLabelElement) {
                        refLabelElement.textContent = refLabel;
                        if (paymentReferenceField.hasAttribute('required')) {
                            refLabelElement.classList.add('field-required');
                        } else {
                            refLabelElement.classList.remove('field-required');
                        }
                    }

                    document.querySelectorAll('.form-label').forEach(label => {
                        const input = label.parentElement.querySelector('.form-control, .form-select');
                        if (input && !input.hasAttribute('required')) {
                            label.classList.remove('field-required');
                        }
                    });

                    if (paymentReferenceField.hasAttribute('required')) document.getElementById('reference_label').classList.add('field-required');
                    if (paymentModeSelect.hasAttribute('required')) paymentModeSelect.parentElement.querySelector('.form-label').classList.add('field-required');

                    if (mode === 'bank transfer' || mode === 'cheque') {
                        if (bankNameSelect.hasAttribute('required') && bankNameLabel) bankNameLabel.classList.add('field-required');
                        if (accountNumberSelect.hasAttribute('required') && accountNumberLabel) accountNumberLabel.classList.add('field-required');
                        if (bankAccountField.hasAttribute('required')) document.querySelector('.bank-account-label').classList.add('field-required');
                        if (bankIfscField.hasAttribute('required')) document.querySelector('.bank-ifsc-label').classList.add('field-required');
                    }
                    if (mode === 'cheque') {
                        if (chequeNoField.hasAttribute('required')) document.querySelector('.cheque-no-label').classList.add('field-required');
                    }
                    if (mode === 'upi') {
                        if (upiIdField.hasAttribute('required')) document.querySelector('.upi-id-label').classList.add('field-required');
                    }

                    calculateNetPayable();
                }

                if (amountToClear) amountToClear.addEventListener('input', calculateNetPayable);
                if (redemptionUse) redemptionUse.addEventListener('input', calculateNetPayable);
                if (paymentModeSelect) paymentModeSelect.addEventListener('change', togglePaymentFields);
                if (bankNameSelect) bankNameSelect.addEventListener('change', updateAccountDropdowns);

                // Add validation before form submit
                const form = document.querySelector('form');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        const mode = paymentModeSelect.value.toLowerCase();
                        
                        if (mode === 'bank transfer' || mode === 'cheque') {
                            const selectedBankName = bankNameSelect.value;
                            const selectedAccountId = accountNumberSelect.value;
                            
                            if (!selectedBankName) {
                                e.preventDefault();
                                alert('Please select a disbursing bank name for ' + mode + ' payments.');
                                return false;
                            }
                            
                            if (!selectedAccountId) {
                                e.preventDefault();
                                alert('Please select a disbursing account number for ' + mode + ' payments.');
                                return false;
                            }
                            
                            console.log('Submitting with disbursed_bank_account_id:', selectedAccountId);
                        }
                    });
                }

                calculateNetPayable();
                togglePaymentFields();
            });
        </script>

    <?php
    } else {
        $rows = $dbObj->getAllResults("SELECT * FROM payment_requests WHERE status='READY_FOR_CASHIER' ORDER BY created_at ASC");
    ?>
        <?php if (!$rows): ?>
            <div class="alert alert-info">No requests pending at cashier.</div>
        <?php else: ?>
            <?php foreach ($rows as $r):
                $payload = json_decode($r['payload_json'] ?? '{}', true) ?: [];
                $entityId = i($r['vendor_id'] ?? $r['employee_id'] ?? 0);
                $entityType = $r['request_type'];
                $entityName = get_entity_name($entityId, $entityType, $payload);
            ?>
                <div class="card p-3 mb-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div><strong><?= h(ucfirst($r['request_type'])) ?> Payment for <?= h($entityName) ?></strong></div>
                            <div class="muted"><?= $r['created_at'] ?></div>
                        </div>
                        <div>
                            <span class="badge bg-info badge-status">With Cashier – disbursal pending</span>
                        </div>
                    </div>
                    <div class="mt-2 d-flex gap-2">
                        <a class="btn btn-sm btn-primary" href="payment?rid=<?= $r['request_id'] ?>">Open & Pay</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php } ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>