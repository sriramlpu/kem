<?php
/**
 * FINANCE API: Unified Expenses & Obligations
 * Path: finance/api/expenses_api.php
 * Handles unified listing of both general and fixed expenses.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=UTF-8');
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

if (!isset($_SESSION['userId'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Session expired.']);
    exit;
}

require_once("../../functions.php");

function json_out($data, int $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

try {
    switch ($action) {
        case 'list':
            $from = $_GET['from'] ?? date('Y-01-01');
            $to   = $_GET['to'] ?? date('Y-m-d');
            $purpose = $_GET['purpose'] ?? '';

            // 1. Fetch General Expenses (Filters applied to paid_at)
            $w1 = ["paid_at BETWEEN '$from 00:00:00' AND '$to 23:59:59'"];
            if ($purpose !== '') $w1[] = "purpose = '" . addslashes($purpose) . "'";
            
            $q1 = "SELECT id, 'General' as type, paid_at as dt, purpose, amount, balance_paid, remark, account_no, ifsc_code 
                   FROM expenses WHERE " . implode(' AND ', $w1);

            // 2. Fetch Fixed Obligations (Filters applied to start_date)
            // UPDATED: Added date range filter to Fixed Obligations to fix the "Filter not working" issue
            $w2 = ["start_date BETWEEN '$from' AND '$to'"];
            if ($purpose !== '') $w2[] = "expense_type = '" . addslashes($purpose) . "'";
            
            $q2 = "SELECT id, 'Fixed' as type, start_date as dt, expense_type as purpose, amount, balance_paid, notes as remark, account_no, ifsc_code 
                   FROM fixed_expenses WHERE " . implode(' AND ', $w2);

            $sql = "SELECT * FROM (($q1) UNION ALL ($q2)) as combined ORDER BY dt DESC";
            $rows = exeSql($sql) ?: [];
            
            $data = [];
            foreach ($rows as $r) {
                $amt = (float)$r['amount'];
                $paid = (float)$r['balance_paid'];
                $data[] = [
                    'id'         => $r['id'],
                    'type'       => $r['type'],
                    'date'       => date('d-M-Y', strtotime($r['dt'])),
                    'purpose'    => str_replace('_', ' ', strtoupper($r['purpose'])),
                    'amount'     => $amt,
                    'paid'       => $paid,
                    'balance'    => $amt - $paid,
                    'account_no' => $r['account_no'] ?: 'N/A',
                    'ifsc_code'  => $r['ifsc_code'] ?: '',
                    'remark'     => $r['remark'] ?: '-'
                ];
            }
            json_out(['data' => $data]);
            break;

        case 'expense_summary':
            $p = trim((string)($_GET['purpose'] ?? ''));
            // Checks both tables for historical spent context
            $r1 = exeSql("SELECT SUM(amount) as t, SUM(balance_paid) as p FROM expenses WHERE purpose = '".addslashes($p)."'");
            $r2 = exeSql("SELECT SUM(amount) as t, SUM(balance_paid) as p FROM fixed_expenses WHERE expense_type = '".addslashes($p)."'");
            
            $t = (float)($r1[0]['t'] ?? 0) + (float)($r2[0]['t'] ?? 0);
            $p_amt = (float)($r1[0]['p'] ?? 0) + (float)($r2[0]['p'] ?? 0);
            
            json_out(['total' => $t, 'paid' => $p_amt, 'balance' => $t - $p_amt]);
            break;

        case 'delete':
            $id = (int)$_POST['id'];
            $type = $_POST['type'] ?? 'General';
            $table = ($type === 'Fixed') ? 'fixed_expenses' : 'expenses';
            exeSql("DELETE FROM $table WHERE id = $id LIMIT 1");
            json_out(['status' => 'success']);
            break;

        case 'getExpense':
            $id = (int)$_GET['id'];
            $type = $_GET['type'] ?? 'General';
            $table = ($type === 'Fixed') ? 'fixed_expenses' : 'expenses';
            $res = exeSql("SELECT * FROM $table WHERE id = $id LIMIT 1");
            json_out(['status' => 'success', 'expense' => $res[0]]);
            break;

        default:
            json_out(['status' => 'error', 'message' => 'Action not supported'], 400);
    }
} catch (Exception $e) {
    json_out(['status' => 'error', 'message' => $e->getMessage()], 400);
}