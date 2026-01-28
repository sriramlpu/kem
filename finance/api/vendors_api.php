<?php
// /kem/finance/api/vendors_api.php
// API endpoint for fetching Vendor data with GRN-based calculations
// FIXED: Now includes transportation in all calculations

ob_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// *** CORRECT PATH: From api/ → up to kem/ → then functions.php ***
require_once __DIR__ . '/../../functions.php';

$response = ['status' => 'error', 'message' => 'Unknown error', 'data' => []];

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    if ($action === 'list') {
        $fromDate = $_REQUEST['from_date'] ?? '';
        $toDate = $_REQUEST['to_date'] ?? '';

        $fromDate = !empty($fromDate) ? trim($fromDate) : null;
        $toDate = !empty($toDate) ? trim($toDate) : null;

        // Fetch all vendors
        $vendors = exeSql("
            SELECT vendor_id, vendor_name, email, phone, status, account_number, ifsc 
            FROM vendors 
            ORDER BY vendor_id DESC
        ");

        if (empty($vendors)) {
            $response = ['status' => 'success', 'data' => []];
            ob_end_clean();
            echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Build date condition
        $dateCondition = "AND NULLIF(grn.grn_date,'0000-00-00') IS NOT NULL";
        
        if ($fromDate) {
            $dateCondition .= " AND DATE(grn.grn_date) >= '{$fromDate}'";
        }
        if ($toDate) {
            $dateCondition .= " AND DATE(grn.grn_date) <= '{$toDate}'";
        }

        $vendorIds = implode(',', array_map('intval', array_column($vendors, 'vendor_id')));

        // FIXED: GRN-based Total Bill (includes transportation, net of returns)
        $grnTotals = exeSql("
            SELECT 
                grn.vendor_id,
                COALESCE(SUM(
                    GREATEST(
                        COALESCE(
                            NULLIF(gri.subjective_amount, 0),
                            (GREATEST(COALESCE(gri.unit_price, 0), 0) * COALESCE(gri.qty_received, 0))
                            - COALESCE(
                                NULLIF(gri.discount_amount, 0),
                                (GREATEST(COALESCE(gri.unit_price, 0), 0) * COALESCE(gri.qty_received, 0))
                                * (COALESCE(gri.discount_percentage, 0) / 100.0)
                            )
                            + COALESCE(
                                NULLIF(gri.tax_amount, 0),
                                (
                                    (GREATEST(COALESCE(gri.unit_price, 0), 0) * COALESCE(gri.qty_received, 0))
                                    - COALESCE(
                                        NULLIF(gri.discount_amount, 0),
                                        (GREATEST(COALESCE(gri.unit_price, 0), 0) * COALESCE(gri.qty_received, 0))
                                        * (COALESCE(gri.discount_percentage, 0) / 100.0)
                                    )
                                ) * (COALESCE(gri.tax_percentage, 0) / 100.0)
                            )
                        ) - COALESCE(rbi.return_amt, 0),
                        0
                    )
                ), 0) 
                + COALESCE(SUM(DISTINCT grn.transportation), 0) AS total_bill
            FROM goods_receipts grn
            JOIN goods_receipt_items gri ON gri.grn_id = grn.grn_id
            LEFT JOIN (
                SELECT grn_item_id, SUM(total_amount) AS return_amt
                FROM goods_return_items
                GROUP BY grn_item_id
            ) rbi ON rbi.grn_item_id = gri.grn_item_id
            WHERE grn.vendor_id IN ({$vendorIds}) {$dateCondition}
            GROUP BY grn.vendor_id
        ") ?: [];

        $grnTotalBillMap = [];
        foreach ($grnTotals as $row) {
            $grnTotalBillMap[(int)$row['vendor_id']] = (float)$row['total_bill'];
        }

        // Total Paid
        $paidTotals = exeSql("
            SELECT
                vgp.vendor_id,
                COALESCE(SUM(vgp.amount), 0) AS total_paid
            FROM vendor_grn_payments vgp
            JOIN goods_receipts grn ON grn.grn_id = vgp.grn_id 
            WHERE vgp.vendor_id IN ({$vendorIds}) {$dateCondition}
            GROUP BY vgp.vendor_id
        ") ?: [];

        $paymentsMap = [];
        foreach ($paidTotals as $row) {
            $paymentsMap[(int)$row['vendor_id']] = (float)$row['total_paid'];
        }

        // Latest GRN Date
        $latestGrns = exeSql("
            SELECT 
                grn.vendor_id,
                MAX(NULLIF(grn.grn_date,'0000-00-00')) AS latest_grn_date
            FROM goods_receipts grn
            WHERE grn.vendor_id IN ({$vendorIds})
            GROUP BY grn.vendor_id
        ") ?: [];

        $vendorLastGrnDateMap = [];
        foreach ($latestGrns as $row) {
            $vendorLastGrnDateMap[(int)$row['vendor_id']] = $row['latest_grn_date'] ?? '';
        }

        // Format data
        $data = [];
        $sno = 1;

        foreach ($vendors as $v) {
            $vid = (int)$v['vendor_id'];
            
            $total_bill = $grnTotalBillMap[$vid] ?? 0.0;
            $total_paid = $paymentsMap[$vid] ?? 0.0;
            $balance = max($total_bill - $total_paid, 0);
            $payment_status = ($balance <= 0.009) ? 'Paid' : 'Pending';
            $latest_grn_date = $vendorLastGrnDateMap[$vid] ?? '';
            
            // Skip vendors with no activity in date range
            if (($fromDate || $toDate) && $total_bill == 0 && $total_paid == 0) {
                continue;
            }

            $data[] = [
                'sno' => $sno++,
                'vendor_name' => htmlspecialchars($v['vendor_name'] ?? ''),
                'email' => !empty($v['email']) ? '<a href="mailto:' . htmlspecialchars($v['email']) . '">' . htmlspecialchars($v['email']) . '</a>' : '',
                'phone' => htmlspecialchars($v['phone'] ?? ''),
                'status' => htmlspecialchars($v['status'] ?? ''),
                'account_number' => htmlspecialchars($v['account_number'] ?? ''),
                'ifsc' => htmlspecialchars($v['ifsc'] ?? ''),
                'total_bill' => number_format($total_bill, 2, '.', ''),
                'total_paid' => number_format($total_paid, 2, '.', ''),
                'balance' => number_format($balance, 2, '.', ''),
                'payment_status_html' => '<span data-payment-status="' . htmlspecialchars($payment_status) . '" class="badge badge-' . strtolower($payment_status) . '">' . htmlspecialchars($payment_status) . '</span>',
                'latest_grn_date' => htmlspecialchars($latest_grn_date)
            ];
        }
        
        $response = ['status' => 'success', 'data' => $data];

    } else {
        $response = ['status' => 'error', 'message' => 'Invalid action'];
    }

} catch (Exception $e) {
    $response = ['status' => 'error', 'message' => $e->getMessage(), 'data' => []];
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit;
?>