<?php
/**
 * FINANCE API: Employee Management (Clean Implementation)
 * Path: finance/api/employee_api.php
 * FIXED: DataTables "Invalid JSON" by preventing SQL errors in Strict Mode.
 * FIXED: Added robust GROUP BY and error suppression.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

// Suppress errors from being printed to output stream to prevent JSON corruption
error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['userId'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Session expired.']);
    exit;
}

require_once("../../functions.php");

function json_out($data, int $code = 200) {
    if (ob_get_length()) ob_clean(); // Clear any existing output/warnings
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

try {
    switch ($action) {
        case 'list':
            $branch_id = (int)($_GET['branch_id'] ?? 0);
            $yyyymm    = preg_replace('/\D/', '', (string)($_GET['yyyymm'] ?? date('Ym')));

            /**
             * FIXED SQL: Only include processed employees.
             * Added all non-aggregated columns to GROUP BY to satisfy ONLY_FULL_GROUP_BY.
             */
            $sql = "SELECT 
                        e.id, 
                        e.employee_name, 
                        e.role, 
                        b.branch_name,
                        e.salary as master_gross,
                        e.advance as current_master_advance,
                        SUM(p.amount) as total_cash_paid,
                        SUM(p.advance) as total_used_advance,
                        SUM(p.lop_amount) as month_lop,
                        SUM(p.ot_amount) as month_ot,
                        SUM(p.incentives) as month_inc,
                        SUM(p.pf_deduction) as month_pf,
                        SUM(p.esi_deduction) as month_esi,
                        SUM(p.tax_deduction) as month_pt,
                        SUM(p.tds_deduction) as month_tds
                    FROM employees e
                    JOIN employee_salary_payments p ON p.employee_id = e.id
                    LEFT JOIN branches b ON b.branch_id = e.branch_id
                    WHERE p.pay_period = '$yyyymm'
                    AND ($branch_id = 0 OR e.branch_id = $branch_id)
                    GROUP BY e.id, e.employee_name, e.role, b.branch_name, e.salary, e.advance
                    ORDER BY e.employee_name ASC";
            
            $rows = exeSql($sql) ?: [];
            $data = [];
            foreach($rows as $r) {
                $data[] = [
                    'id'            => (int)$r['id'],
                    'employee_name' => (string)$r['employee_name'],
                    'role'          => (string)($r['role'] ?: 'Staff'),
                    'branch_name'   => (string)($r['branch_name'] ?: 'N/A'),
                    'salary'        => (float)$r['master_gross'],
                    'lop'           => (float)$r['month_lop'],
                    'ot'            => (float)$r['month_ot'],
                    'inc'           => (float)$r['month_inc'],
                    'pf'            => (float)$r['month_pf'],
                    'esi'           => (float)$r['month_esi'],
                    'pt'            => (float)$r['month_pt'],
                    'tds'           => (float)$r['month_tds'],
                    'advance_bal'   => (float)$r['current_master_advance'],
                    'processed_net' => (float)$r['total_cash_paid'] + (float)$r['total_used_advance']
                ];
            }
            json_out(['data' => $data]);
            break;

        case 'master_list':
            $branch_id = (int)($_GET['branch_id'] ?? 0);
            $sql = "SELECT e.*, b.branch_name FROM employees e
                    LEFT JOIN branches b ON b.branch_id = e.branch_id
                    WHERE ($branch_id = 0 OR e.branch_id = $branch_id)
                    ORDER BY e.status ASC, e.employee_name ASC";
            $rows = exeSql($sql) ?: [];
            json_out(['data' => $rows]);
            break;

        case 'getEmployee':
            $id = (int)($_GET['id'] ?? 0);
            $res = exeSql("SELECT * FROM employees WHERE id = $id LIMIT 1");
            if (!$res) throw new Exception("Employee not found.");
            json_out(['status' => 'success', 'employee' => $res[0]]);
            break;

        case 'create':
            $name = trim((string)$_POST['employee_name']);
            if (!$name) throw new Exception("Employee name is required.");
            
            // Unique ID generation logic
            $uid = 'EMP-' . date('ym') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            
            $data = [
                'employee_name'    => $name,
                'employee_uid'     => $uid,
                'mobile_number'    => trim((string)$_POST['mobile_number']),
                'email'            => trim((string)$_POST['email']),
                'role'             => trim((string)$_POST['role']),
                'branch_id'        => (int)$_POST['branch_id'] ?: null,
                'salary'           => (float)$_POST['salary'],
                'pf_percent'       => (float)($_POST['pf_percent'] ?? 12.00),
                'esi_percent'      => (float)($_POST['esi_percent'] ?? 0.75),
                'professional_tax' => (float)$_POST['professional_tax'],
                'bank_name'        => trim((string)$_POST['bank_name']),
                'ifsc_code'        => trim((string)$_POST['ifsc_code']),
                'status'           => 'Active'
            ];
            $newId = insData('employees', $data);
            json_out(['status' => 'success', 'id' => $newId, 'employee_uid' => $uid]);
            break;
        
         case 'deleteEmployee':
            $id = (int)$_POST['id'];
            if (!$id) throw new Exception("ID missing for deactivation.");
            
            // Toggle status to Inactive (Soft delete)
            upData('employees', ['status' => 'Inactive'], ["id = $id"]);
            json_out(['status' => 'success']);
            break;

        case 'updateEmployee':
            $id = (int)$_POST['id'];
            $data = [
                'employee_name'    => trim((string)$_POST['employee_name']),
                'role'             => trim((string)$_POST['role']),
                'branch_id'        => (int)$_POST['branch_id'] ?: null,
                'salary'           => (float)$_POST['salary'],
                'pf_percent'       => (float)($_POST['pf_percent'] ?? 12.00),
                'esi_percent'      => (float)($_POST['esi_percent'] ?? 0.75),
                'professional_tax' => (float)$_POST['professional_tax'],
                'bank_name'        => trim((string)$_POST['bank_name']),
                'ifsc_code'        => trim((string)$_POST['ifsc_code'])
            ];
            upData('employees', $data, ["id = $id"]);
            json_out(['status' => 'success']);
            break;

         case 'getBranches':
            $res = exeSql("SELECT branch_id, branch_name FROM branches ORDER BY branch_name ASC");
            json_out(['status' => 'success', 'branches' => $res ?: []]);
            break;

        default:
            json_out(['status' => 'error', 'message' => 'Action not supported.'], 400);
    }
} catch (Exception $e) {
    json_out(['data' => [], 'status' => 'error', 'message' => $e->getMessage()], 200);
}