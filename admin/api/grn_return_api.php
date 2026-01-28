<?php
session_start();
header('Content-Type: application/json');
require_once('../../functions.php');

try {
    // Parse JSON if present
    $raw_input = file_get_contents('php://input');
    $json_data = json_decode($raw_input, true);

    if (is_array($json_data) && isset($json_data['action'])) {
        $action = $json_data['action'];
        $_POST = $json_data; // Treat JSON as normal $_POST for convenience
    } else {
        $action = $_POST['action'] ?? ($_GET['action'] ?? '');
    }
    
    // error_log('GRN Return Action: ' . $action);

    // -----------------------------
    // GET GRN DETAILS FOR RETURN
    // -----------------------------
    if ($action === 'getGrnDetails') {
        $grn_id = intval($_POST['grn_id'] ?? ($_GET['grn_id'] ?? 0));
        
        if ($grn_id <= 0) {
            throw new Exception('Valid GRN ID is required');
        }

        // Get GRN header
        $header = exeSql("
            SELECT 
                gr.grn_id,
                gr.vendor_id,
                v.vendor_name,
                gr.grn_number,
                gr.grn_date
            FROM goods_receipts gr
            LEFT JOIN vendors v ON gr.vendor_id = v.vendor_id
            WHERE gr.grn_id = $grn_id
        ");

        if (!$header || empty($header)) {
            throw new Exception('GRN not found');
        }

        // Get GRN items with return quantities and per-unit calculations
        $items = exeSql("
            SELECT 
                gri.grn_item_id,
                gri.item_id,
                i.item_name,
                gri.qty_received AS received_qty,
                COALESCE(SUM(grti.return_qty), 0) AS already_returned,
                gri.unit_price,
                COALESCE(gri.discount_amount, 0) AS total_discount_amt,
                COALESCE(gri.tax_amount, 0) AS total_tax_amt,
                COALESCE(gri.tax_percentage, 0) AS tax_pct,
                -- Calculate per-unit discount amount
                CASE 
                    WHEN gri.qty_received > 0 
                    THEN COALESCE(gri.discount_amount, 0) / gri.qty_received 
                    ELSE 0 
                END AS per_unit_discount,
                -- Calculate per-unit tax amount
                CASE 
                    WHEN gri.qty_received > 0 
                    THEN COALESCE(gri.tax_amount, 0) / gri.qty_received 
                    ELSE 0 
                END AS per_unit_tax
            FROM goods_receipt_items gri
            LEFT JOIN items i ON i.item_id = gri.item_id
            LEFT JOIN goods_return_items grti ON grti.grn_item_id = gri.grn_item_id
            WHERE gri.grn_id = $grn_id
            GROUP BY gri.grn_item_id
            HAVING (gri.qty_received - COALESCE(SUM(grti.return_qty), 0)) > 0
        ");

        echo json_encode([
            'status' => 'success',
            'data' => [
                'header' => $header[0],
                'items' => $items ?: []
            ]
        ]);
        exit;
    }

    // -----------------------------
    // CREATE RETURN
    // -----------------------------
    if ($action === 'create') {
        
        // print_r($_REQUEST);
        $grn_id = intval($_POST['grn_id'] ?? 0);
        $vendor_id = intval($_POST['vendor_id'] ?? 0);
        $return_date = trim($_POST['return_date'] ?? '');
        $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : null;
        $totalAmount = floatval($_POST['total_amount'] ?? 0);
        $discountAmount = floatval($_POST['discount_amount'] ?? 0);
        $taxAmount = floatval($_POST['tax_amount'] ?? 0);

        $items = $_POST['items'] ?? [];
        if (!is_array($items)) {
            throw new Exception('Invalid items data');
        }

        // Validation
        $validationErrors = [];
        if ($grn_id <= 0) {
            $validationErrors[] = "Valid GRN is required";
        }
        if ($vendor_id <= 0) {
            $validationErrors[] = "Valid Vendor is required";
        }
        if (empty($return_date) || !DateTime::createFromFormat('Y-m-d', $return_date)) {
            $validationErrors[] = "Invalid or missing Return Date (YYYY-MM-DD)";
        }
        if (count($items) === 0) {
            $validationErrors[] = "At least one item is required for the return";
        }

        if (!empty($validationErrors)) {
            throw new Exception('Validation failed: ' . implode('; ', $validationErrors));
        }

        // Generate return number
        $datePart = date('Ymd', strtotime($return_date));
        $countResult = getCount("goods_return_notes", "return_number LIKE 'GRN-RET-$datePart%'");
        $sequence = str_pad($countResult + 1, 4, '0', STR_PAD_LEFT);
        $return_number = "GRN-RET-$datePart-$sequence";

        $created_by = isset($_SESSION['userId']) ? intval($_SESSION['userId']) : 0;

        excuteSql("START TRANSACTION");

        // Insert return header
        $sql = "
        INSERT INTO goods_return_notes
            (grn_id, vendor_id, return_number, return_date, total_amount, 
             discount_amount, tax_amount, created_by, remarks, created_at)
        VALUES
            ($grn_id, $vendor_id, '$return_number', '$return_date', $totalAmount + $taxAmount,
             $discountAmount, $taxAmount, $created_by, " . ($remarks !== null && $remarks !== '' ? "'" . addslashes($remarks) . "'" : "NULL") . ", NOW())
        ";
        if (!excuteSql($sql)) {
            throw new Exception('Failed to insert return header');
        }

        $row = exeSql("SELECT LAST_INSERT_ID() AS return_id");
        if (!$row || empty($row[0]['return_id'])) {
            throw new Exception('Failed to retrieve return ID');
        }
        $return_id = intval($row[0]['return_id']);

        // Insert return items
        foreach ($items as $it) {
            $grn_item_id = intval($it['grn_item_id'] ?? 0);
            $item_id = intval($it['item_id'] ?? 0);
            $return_qty = floatval($it['return_qty'] ?? 0);
            $unitPrice = floatval($it['unit_price'] ?? 0);
            $discountPct = 0; // Always 0 as per client requirement
            $discountAmt = floatval($it['discount_amt'] ?? 0);
            $taxPct = floatval($it['tax_pct'] ?? 0);
            $taxAmt = floatval($it['tax_amt'] ?? 0);
            $totalAmt = ($return_qty * $unitPrice) - $discountAmt + $taxAmt;

            if ($grn_item_id <= 0 || $item_id <= 0 || $return_qty <= 0) {
                throw new Exception('Invalid return item data');
            }

            $lineSql = "
            INSERT INTO goods_return_items
                (return_id, grn_item_id, item_id, return_qty, unit_price,
                 discount_percentage, discount_amount, tax_percentage, tax_amount, total_amount)
            VALUES
                ($return_id, $grn_item_id, $item_id, $return_qty, $unitPrice,
                 $discountPct, $discountAmt, $taxPct, $taxAmt, $totalAmt)
            ";
            if (!excuteSql($lineSql)) {
                throw new Exception("Failed to insert return line for item_id=$item_id");
            }
        }

        excuteSql("COMMIT");

        echo json_encode([
            'status' => 'success',
            'message' => 'Return created successfully!',
            'return_number' => $return_number,
            'return_id' => $return_id
        ]);
        exit;
    }

    throw new Exception('Invalid or missing action parameter: ' . $action);
    
} catch (Exception $e) {
    excuteSql("ROLLBACK");
    error_log('GRN Return API Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug_info' => [
            'received_post' => $_POST,
            'timestamp' => date('Y-m-d H:i:s'),
        ]
    ]);
}
?>