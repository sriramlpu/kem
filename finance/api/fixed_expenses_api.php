<?php
/**
 * FINANCE API: Fixed Expenses / Recurring Obligations
 * Path: finance/api/fixed_expenses_api.php
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
require_once dirname(__DIR__, 2) . '/functions.php';

function json_out($data, int $code = 200): void {
    if (ob_get_length()) ob_clean();
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(int $code, string $msg): void { json_out(['status' => 'error', 'message' => $msg], $code); }
function ok(array $data = []): void { json_out(['status' => 'success'] + $data); }

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

try {
    switch ($action) {
        case 'list':
            $sql = "SELECT *, (amount - IFNULL(balance_paid, 0)) as remaining_balance 
                    FROM fixed_expenses ORDER BY id DESC";
            $rows = exeSql($sql) ?: [];
            
            $data = [];
            foreach ($rows as $r) {
                $data[] = [
                    'id'           => $r['id'],
                    'type'         => strtoupper(str_replace('_', ' ', $r['expense_type'])),
                    'amount'       => (float)$r['amount'],
                    'paid'         => (float)$r['balance_paid'],
                    'balance'      => (float)$r['remaining_balance'],
                    'frequency'    => $r['frequency'],
                    'due_day'      => $r['due_day'],
                    'start_date'   => $r['start_date'] ? date('d-M-Y', strtotime($r['start_date'])) : 'N/A',
                    'notes'        => $r['notes'] ?: ''
                ];
            }
            json_out(['data' => $data]);
            break;

        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            $row = exeSql("SELECT * FROM fixed_expenses WHERE id = $id LIMIT 1");
            if (!$row) fail(404, 'Obligation not found');
            ok(['record' => $row[0]]);
            break;

        case 'create':
            $data = [
                'expense_type' => trim($_POST['expense_type'] ?? ''),
                'amount'       => (float)($_POST['amount'] ?? 0),
                'start_date'   => trim($_POST['start_date'] ?? date('Y-m-d')),
                'frequency'    => trim($_POST['frequency'] ?? 'Monthly'),
                'due_day'      => (int)($_POST['due_day'] ?? 1),
                'notes'        => trim($_POST['notes'] ?? ''),
                'balance_paid' => (float)($_POST['balance_paid'] ?? 0),
                'account_no'   => trim($_POST['account_no'] ?? ''),
                'ifsc_code'    => trim($_POST['ifsc_code'] ?? '')
            ];
            
            if ($data['expense_type'] === '') fail(422, 'Type is required');
            if ($data['amount'] <= 0) fail(422, 'Amount must be positive');
            
            $newId = insData('fixed_expenses', $data);
            ok(['id' => $newId]);
            break;

        case 'update':
            $id = (int)$_POST['id'];
            $data = [
                'expense_type' => trim($_POST['expense_type'] ?? ''),
                'amount'       => (float)($_POST['amount'] ?? 0),
                'start_date'   => trim($_POST['start_date'] ?? ''),
                'frequency'    => trim($_POST['frequency'] ?? ''),
                'due_day'      => (int)($_POST['due_day'] ?? 1),
                'notes'        => trim($_POST['notes'] ?? ''),
                'balance_paid' => (float)($_POST['balance_paid'] ?? 0),
                'account_no'   => trim($_POST['account_no'] ?? ''),
                'ifsc_code'    => trim($_POST['ifsc_code'] ?? '')
            ];

            if ($id <= 0) fail(422, 'Valid ID required');
            upData('fixed_expenses', $data, ["id = $id"]);
            ok();
            break;

        case 'delete':
            $id = (int)$_POST['id'];
            if ($id <= 0) fail(422, 'Valid ID required');
            exeSql("DELETE FROM fixed_expenses WHERE id = $id LIMIT 1");
            ok();
            break;

        default:
            fail(405, 'Action not supported');
    }
} catch (Exception $e) {
    fail(500, $e->getMessage());
}