<?php
session_start();
header('Content-Type: application/json');
require_once('../../functions.php');

ob_start();
try {
    $action = $_POST['action'] ?? ($_GET['action'] ?? '');
    // error_log('GRN Action: ' . $action);

    // -----------------------------
    // CREATE GRN
    // -----------------------------
    if ($action === 'create') {
        $po_id          = intval($_POST['po_id'] ?? 0);
        $vendor_id      = intval($_POST['vendor_id'] ?? 0);
        $grn_date       = trim($_POST['grn_date'] ?? '');
        $invoice_date   = trim($_POST['invoice_date'] ?? '');
        $invoice_number = trim($_POST['invoice_number'] ?? '');
        $remarks        = isset($_POST['remarks']) ? trim($_POST['remarks']) : null;
        $totalAmount    = floatval($_POST['total_amount'] ?? 0);
        $discountAmount = floatval($_POST['discount_amount'] ?? 0);
        $taxAmount = floatval($_POST['total_tax_amount'] ?? 0);
        $transportation = floatval($_POST['transportation'] ?? 0);
        $branchId = floatval($_POST['branch_id'] ?? 0);

        $items_raw = $_POST['items'] ?? '[]';
        $items = is_array($items_raw) ? $items_raw : json_decode($items_raw, true);
        if (!is_array($items)) {
            throw new Exception('Invalid items JSON.');
        }

        // Validation
        $validationErrors = [];
        if ($po_id <= 0) {
            $validationErrors[] = "Valid PO is required";
        }
        if ($vendor_id <= 0) {
            $validationErrors[] = "Valid Vendor is required";
        }
        if (empty($grn_date) || !DateTime::createFromFormat('Y-m-d', $grn_date)) {
            $validationErrors[] = "Invalid or missing GRN Date (YYYY-MM-DD)";
        }
        if (empty($invoice_date) || !DateTime::createFromFormat('Y-m-d', $invoice_date)) {
            $validationErrors[] = "Invalid or missing Invoice Date (YYYY-MM-DD)";
        }
        if (empty($invoice_number)) {
            $validationErrors[] = "Invoice Number is required";
        }
        if (!isset($items) || !is_array($items) || count($items) === 0) {
            $validationErrors[] = "At least one item is required for the GRN";
        }

        if (!empty($validationErrors)) {
            throw new Exception('Validation failed: ' . implode('; ', $validationErrors));
        }

        $received_by = isset($_SESSION['userId']) ? intval($_SESSION['userId']) : 0;
        $created_by  = $received_by ?: 0;

        // File upload
        $documentPath = null;
        if (isset($_FILES['document_upload']) && $_FILES['document_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['document_upload'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Upload error: " . $file['error']);
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExt = ['png', 'jpg', 'jpeg', 'pdf'];
            if (!in_array($ext, $allowedExt, true)) {
                throw new Exception('Invalid file type. Only PNG, JPEG, or PDF allowed.');
            }

            $dirAbs = __DIR__ . '/../uploads/grn';
            if (!is_dir($dirAbs) && !mkdir($dirAbs, 0775, true)) {
                throw new Exception('Failed to create upload directory.');
            }

            $safeBase = bin2hex(random_bytes(8));
            $filename = sprintf('%s_%s.%s', date('YmdHis'), $safeBase, $ext);
            $target   = $dirAbs . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $target)) {
                throw new Exception('Failed to move uploaded file.');
            }

            $documentPath = 'uploads/grn/' . $filename;
        }

        // GRN number
        $datePart = date('Ymd', strtotime($grn_date));
        $countResult = getCount("goods_receipts", "grn_number LIKE 'GRN-$datePart%'");
        $sequence = str_pad($countResult + 1, 4, '0', STR_PAD_LEFT);
        $grn_number = "GRN-$datePart-$sequence";

        excuteSql("START TRANSACTION");

        // Insert GRN header
        $sql = "
        INSERT INTO goods_receipts
            (po_id, vendor_id, grn_number, grn_date, invoice_date, invoice_number,
             total_amount, discount_amount, tax_amount, transportation, document_path,
             received_by, created_by, remarks, created_at, branch_id, status)
        VALUES
            ($po_id, $vendor_id, '$grn_number', '$grn_date', '$invoice_date', '$invoice_number',
             $totalAmount, $discountAmount, $taxAmount, $transportation, " . ($documentPath ? "'$documentPath'" : "NULL") . ",
             $received_by, $created_by, " . ($remarks !== null && $remarks !== '' ? "'" . addslashes($remarks) . "'" : "NULL") . ", NOW(), $branchId, 'Completed')
        ";
        if (!excuteSql($sql)) {
            throw new Exception('Failed to insert GRN header');
        }

        $row = exeSql("SELECT LAST_INSERT_ID() AS grn_id");
        if (!$row || empty($row[0]['grn_id'])) {
            throw new Exception('Failed to retrieve GRN ID');
        }
        $grn_id = intval($row[0]['grn_id']);

        // Insert GRN items
        foreach ($items as $it) {
            $item_id   = intval($it['item_id'] ?? 0);
            $po_item_id = intval($it['po_item_id'] ?? 0);
            $quantity  = floatval($it['quantity'] ?? 0);
            $unitPrice = floatval($it['unit_price'] ?? 0);
            $discountPercentage = 0;
            $discountAmount     = floatval($it['discount_amt'] ?? 0);
            $taxPercentage = floatval($it['tax_pct'] ?? 0);
            $taxAmount          = floatval($it['tax_amt'] ?? 0);
            $subjectiveAmount   = floatval($it['subjective_amt'] ?? 0);
            $amount = ($quantity * $unitPrice) - $discountAmount + $taxAmount;
            $taxType = 1;
            $gstSlab = 1;

            if ($item_id <= 0 || $po_item_id <= 0) {
                throw new Exception('Invalid item or PO item ID in items array');
            }

            $lineSql = "
            INSERT INTO goods_receipt_items
                (grn_id, po_item_id, item_id, branch_id, qty_received, unit_price,
                 discount_percentage, discount_amount, tax_amount, subjective_amount, tax_percentage,
                 tax_type_id, gst_slab_id, amount, created_at, status)
            VALUES
                ($grn_id, $po_item_id, $item_id, $branchId, $quantity, $unitPrice,
                 $discountPercentage, $discountAmount, $taxAmount, $subjectiveAmount, $taxPercentage,
                 $taxType, $gstSlab, $amount, NOW(), 'Completed')
            ";
            if (!excuteSql($lineSql)) {
                throw new Exception("Failed to insert GRN line for item_id=$item_id");
            }

            // Update received quantity in PO items
            $updatePO = "UPDATE purchase_order_items 
                         SET sent_quantity = COALESCE(sent_quantity,0) + $quantity
                         WHERE po_item_id = $po_item_id";
            if (excuteSql($updatePO) === false) {
                throw new Exception("Failed to update PO item quantity (po_item_id=$po_item_id)");
            }

            // Check status of PO item
            $row = exeSql("SELECT quantity, sent_quantity, status FROM purchase_order_items WHERE po_item_id = $po_item_id");
            if ($row) {
                $newStatus = ($row[0]['sent_quantity'] >= $row[0]['quantity']) ? 'Completed' : 'Pending';

                if ($row[0]['status'] !== $newStatus) {
                    $updatePOItems = "UPDATE purchase_order_items SET status='$newStatus' WHERE po_item_id=$po_item_id";
                    if (excuteSql($updatePOItems) === false) {
                        throw new Exception("Failed to update PO item status (po_item_id=$po_item_id)");
                    }
                }
            }
        }

        $grnRaised = "UPDATE purchase_orders 
                      SET grn_raised_count = grn_raised_count + 1
                      WHERE po_id = $po_id";
        if (excuteSql($grnRaised) === false) {
            throw new Exception("Failed to raise GRN");
        }
        
        // Update PO header status
        $remaining = exeSql("SELECT COUNT(*) AS pending_count 
                             FROM purchase_order_items 
                             WHERE po_id=$po_id AND status<>'Completed'");
        if ($remaining[0]['pending_count'] == 0) {
            exeSql("UPDATE purchase_orders SET status='Completed' WHERE po_id=$po_id");
        } else {
            exeSql("UPDATE purchase_orders SET status='Partially Fulfilled' WHERE po_id=$po_id");
        }

        excuteSql("COMMIT");

        ob_end_clean();
        echo json_encode([
            'status'        => 'success',
            'message'       => 'GRN created successfully!',
            'grn_number'    => $grn_number,
            'grn_id'        => $grn_id,
            'document_path' => $documentPath
        ]);
        exit;
    }


    // -----------------------------
    // UPDATE GRN
    // -----------------------------
    if ($action === 'update') {
        $grn_id         = intval($_POST['grn_id'] ?? 0);
        $po_id          = intval($_POST['po_id'] ?? 0);
        $vendor_id      = intval($_POST['vendor_id'] ?? 0);
        $grn_date       = trim($_POST['grn_date'] ?? '');
        $invoice_date   = trim($_POST['invoice_date'] ?? '');
        $invoice_number = trim($_POST['invoice_number'] ?? '');
        $remarks        = isset($_POST['remarks']) ? trim($_POST['remarks']) : null;
        $totalAmount    = floatval($_POST['total_amount'] ?? 0);
        $discountAmount = floatval($_POST['discount_amount'] ?? 0);
        $taxAmount = floatval($_POST['tax_amount'] ?? 0);
        $transportation = floatval($_POST['transportation'] ?? 0);
        $branchId       = floatval($_POST['branch_id'] ?? 0);

        $items_raw = $_POST['items'] ?? '[]';
        $items = is_array($items_raw) ? $items_raw : json_decode($items_raw, true);
        if (!is_array($items)) {
            throw new Exception('Invalid items JSON.');
        }

        // Validation
        $validationErrors = [];
        if ($grn_id <= 0) {
            $validationErrors[] = "Valid GRN ID is required";
        }
        if ($po_id <= 0) {
            $validationErrors[] = "Valid PO is required";
        }
        if ($vendor_id <= 0) {
            $validationErrors[] = "Valid Vendor is required";
        }
        if (empty($grn_date) || !DateTime::createFromFormat('Y-m-d', $grn_date)) {
            $validationErrors[] = "Invalid or missing GRN Date (YYYY-MM-DD)";
        }
        if (empty($invoice_date) || !DateTime::createFromFormat('Y-m-d', $invoice_date)) {
            $validationErrors[] = "Invalid or missing Invoice Date (YYYY-MM-DD)";
        }
        if (empty($invoice_number)) {
            $validationErrors[] = "Invoice Number is required";
        }
        if (!isset($items) || !is_array($items) || count($items) === 0) {
            $validationErrors[] = "At least one item is required for the GRN update";
        }

        if (!empty($validationErrors)) {
            throw new Exception('Validation failed: ' . implode('; ', $validationErrors));
        }

        // File upload
        $documentPath = null;
        if (isset($_FILES['document_upload']) && $_FILES['document_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['document_upload'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Upload error: " . $file['error']);
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExt = ['png', 'jpg', 'jpeg', 'pdf'];
            if (!in_array($ext, $allowedExt, true)) {
                throw new Exception('Invalid file type. Only PNG, JPEG, or PDF allowed.');
            }

            $dirAbs = __DIR__ . '/../uploads/grn';
            if (!is_dir($dirAbs) && !mkdir($dirAbs, 0775, true)) {
                throw new Exception('Failed to create upload directory.');
            }

            $safeBase = bin2hex(random_bytes(8));
            $filename = sprintf('%s_%s.%s', date('YmdHis'), $safeBase, $ext);
            $target   = $dirAbs . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $target)) {
                throw new Exception('Failed to move uploaded file.');
            }

            $documentPath = 'uploads/grn/' . $filename;
        }

        excuteSql("START TRANSACTION");

        // Update GRN header
        $sql = "
        UPDATE goods_receipts
        SET
            po_id = $po_id,
            vendor_id = $vendor_id,
            grn_date = '$grn_date',
            invoice_date = '$invoice_date',
            invoice_number = '$invoice_number',
            total_amount = $totalAmount,
            discount_amount = $discountAmount,
            tax_amount = $taxAmount,
            transportation = $transportation,
            remarks = " . ($remarks !== null && $remarks !== '' ? "'" . addslashes($remarks) . "'" : "NULL") . ",
            branch_id = $branchId,
            updated_at = NOW()
            " . ($documentPath ? ", document_path = '$documentPath'" : "") . "
        WHERE grn_id = $grn_id
        ";
        if (!excuteSql($sql)) {
            throw new Exception('Failed to update GRN header');
        }

        // Delete old items
        $deleteOld = "DELETE FROM goods_receipt_items WHERE grn_id = $grn_id";
        if (!excuteSql($deleteOld)) {
            throw new Exception('Failed to delete existing GRN items');
        }

        // Insert updated items
        foreach ($items as $it) {
            $item_id = intval($it['item_id'] ?? 0);
            $po_item_id = intval($it['po_item_id'] ?? 0);
            $quantity = floatval($it['quantity'] ?? 0);
            $unitPrice = floatval($it['unit_price'] ?? 0);
            $discountPct = floatval($it['discount_pct'] ?? 0);
            $discountAmt = floatval($it['discount_amt'] ?? 0);
            $taxPct = floatval($it['tax_pct'] ?? 0);
            $taxAmt = floatval($it['tax_amt'] ?? 0);
            $subjectiveAmt = floatval($it['subjective_amt'] ?? 0);
            $amount = ($quantity * $unitPrice) - $discountAmt + $taxAmt;
            $taxType = 1;
            $gstSlab = 1;

            if ($item_id <= 0) {
                throw new Exception('Invalid item ID in items array');
            }

            $lineSql = "
            INSERT INTO goods_receipt_items
                (grn_id, po_item_id, item_id, branch_id, qty_received, unit_price,
                 discount_percentage, discount_amount, tax_amount, subjective_amount,
                 tax_percentage, tax_type_id, gst_slab_id, amount, created_at, status)
            VALUES
                ($grn_id, $po_item_id, $item_id, $branchId, $quantity, $unitPrice,
                 $discountPct, $discountAmt, $taxAmt, $subjectiveAmt,
                 $taxPct, $taxType, $gstSlab, $amount, NOW(), 'Completed')
            ";
            if (!excuteSql($lineSql)) {
                throw new Exception("Failed to insert updated GRN line for item_id=$item_id");
            }
        }

        excuteSql("COMMIT");

        ob_end_clean();
        echo json_encode([
            'status' => 'success',
            'message' => 'GRN updated successfully!',
            'grn_id' => $grn_id,
            'document_path' => $documentPath
        ]);
        exit;
    }

    // -----------------------------
    // LIST - OPTIMIZED WITH RETURN AMOUNTS
    // -----------------------------
    if ($action === 'list') {
        $draw = intval($_POST['draw'] ?? 1);
        $start = intval($_POST['start'] ?? 0);
        $length = intval($_POST['length'] ?? 10);
        $searchValue = trim($_POST['search']['value'] ?? '');
        $includeItems = isset($_POST['include_items']) && $_POST['include_items'] === 'true';

        $grn_start = trim($_POST['start_date'] ?? '');
        $grn_end = trim($_POST['end_date'] ?? '');
        $inv_start = trim($_POST['invoice_from'] ?? '');
        $inv_end = trim($_POST['invoice_to'] ?? '');

        $po_id = intval($_POST['po_id'] ?? 0);
        $po_number = trim($_POST['po_number'] ?? '');
        $grn_number = trim($_POST['grn_number'] ?? '');
        $branch_id = intval($_POST['branch_id'] ?? 0);
        $vendor_id = intval($_POST['vendor_id'] ?? 0);
        $invoice_num = trim($_POST['invoice_number'] ?? '');

        $whereClauses = [];

        if ($searchValue !== '') {
            $sv = addslashes($searchValue);
            $whereClauses[] = "(gr.grn_number LIKE '%$sv%' OR gr.invoice_number LIKE '%$sv%' OR po.order_number LIKE '%$sv%' OR v.vendor_name LIKE '%$sv%' OR b.branch_name LIKE '%$sv%')";
        }

        if ($grn_number !== '') {
            $whereClauses[] = "gr.grn_number LIKE '%" . addslashes($grn_number) . "%'";
        }
        if ($invoice_num !== '') {
            $whereClauses[] = "gr.invoice_number LIKE '%" . addslashes($invoice_num) . "%'";
        }
        if ($po_id > 0) {
            $whereClauses[] = "gr.po_id = $po_id";
        } elseif ($po_number !== '') {
            $whereClauses[] = "po.order_number LIKE '%" . addslashes($po_number) . "%'";
        }
        if ($branch_id > 0) {
            $whereClauses[] = "po.branch_id = $branch_id";
        }
        if ($vendor_id > 0) {
            $whereClauses[] = "gr.vendor_id = $vendor_id";
        }

        if ($grn_start && $grn_end) {
            $whereClauses[] = "gr.grn_date BETWEEN '$grn_start' AND '$grn_end'";
        } elseif ($grn_start) {
            $whereClauses[] = "DATE(gr.grn_date) = '$grn_start'";
        } elseif ($grn_end) {
            $whereClauses[] = "DATE(gr.grn_date) <= '$grn_end'";
        }

        if ($inv_start && $inv_end) {
            $whereClauses[] = "gr.invoice_date BETWEEN '$inv_start' AND '$inv_end'";
        } elseif ($inv_start) {
            $whereClauses[] = "DATE(gr.invoice_date) = '$inv_start'";
        } elseif ($inv_end) {
            $whereClauses[] = "DATE(gr.invoice_date) <= '$inv_end'";
        }

        $where = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        $totalSql = exeSql("SELECT COUNT(*) AS total FROM goods_receipts");
        $recordsTotal = intval($totalSql[0]['total'] ?? 0);

        $filterSql = exeSql("
            SELECT COUNT(*) AS total
            FROM goods_receipts gr
            LEFT JOIN vendors v ON gr.vendor_id = v.vendor_id
            LEFT JOIN purchase_orders po ON gr.po_id = po.po_id
            LEFT JOIN branches b ON po.branch_id = b.branch_id
            $where
        ");
        $recordsFiltered = intval($filterSql[0]['total'] ?? 0);

        // OPTIMIZED: Single query with detailed breakdown
        // Note: total_amount already has discount deducted
        $dataSql = "
SELECT
    gr.grn_id,
    gr.grn_number,
    gr.grn_date,
    gr.invoice_number,
    gr.invoice_date,
    gr.remarks,
    (COALESCE(gr.total_amount, 0) - COALESCE(gr.tax_amount, 0)) AS total_amount,
    COALESCE(gr.tax_amount, 0) AS tax_amount,
    COALESCE(gr.discount_amount, 0) AS discount_amount,
    COALESCE(gr.transportation, 0) AS transportation,
    (COALESCE(gr.total_amount, 0) - COALESCE(gr.tax_amount, 0) + COALESCE(gr.transportation, 0) + COALESCE(gr.tax_amount, 0)) AS gross_total,
    (COALESCE(grn.total_amount, 0) - COALESCE(grn.discount_amount, 0)) AS return_amount,
    grn.return_number,
    (COALESCE(gr.total_amount, 0) + COALESCE(gr.transportation, 0) - COALESCE(grn.total_amount, 0)) AS net_amount,
    gr.status,
    v.vendor_name,
    b.branch_name,
    po.order_number AS po_number" . 
    ($includeItems ? ",
    (SELECT GROUP_CONCAT(CONCAT(i.item_name, ' (Qty: ', gri.qty_received, ')') SEPARATOR ', ')
     FROM goods_receipt_items gri
     LEFT JOIN items i ON i.item_id = gri.item_id
     WHERE gri.grn_id = gr.grn_id) AS items_list" : "") . "
FROM goods_receipts gr
LEFT JOIN vendors v ON gr.vendor_id = v.vendor_id
LEFT JOIN purchase_orders po ON gr.po_id = po.po_id
LEFT JOIN branches b ON po.branch_id = b.branch_id
LEFT JOIN (
    SELECT 
        grn.grn_id,
        GROUP_CONCAT(grn.return_number SEPARATOR ', ') AS return_number,
        SUM(COALESCE(grn.total_amount, 0)) AS total_amount,
        SUM(COALESCE(grn.discount_amount, 0)) AS discount_amount
    FROM goods_return_notes grn
    GROUP BY grn.grn_id
) grn ON grn.grn_id = gr.grn_id
$where
ORDER BY gr.grn_id DESC
LIMIT $start, $length
";


        $rows = exeSql($dataSql);

        ob_end_clean();
        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'status' => 'success',
            'data' => $rows ?: []
        ]);
        exit;
    }

    // -----------------------------
    // GET
    // -----------------------------
    if ($action === 'get') {
        $grn_id = intval($_POST['grn_id'] ?? ($_GET['grn_id'] ?? 0));
        if ($grn_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Valid GRN ID is required']);
            exit;
        }

        $res = exeSql("
            SELECT
                gr.*,
                v.vendor_name,
                b.branch_name,
                po.order_number AS po_number, 
                grnn.return_number,
                grnn.total_amount as return_amount
            FROM goods_receipts gr
            LEFT JOIN vendors v ON gr.vendor_id = v.vendor_id
            LEFT JOIN purchase_orders po ON gr.po_id = po.po_id
            LEFT JOIN branches b ON po.branch_id = b.branch_id
            LEFT JOIN goods_return_notes grnn ON grnn.grn_id = gr.grn_id
            WHERE gr.grn_id = $grn_id
        ");

        if (!$res || empty($res)) {
            echo json_encode(['status' => 'error', 'message' => 'GRN not found']);
            exit;
        }

        echo json_encode(['status' => 'success', 'data' => $res[0]]);
        exit;
    }

    // -----------------------------
    // GET ITEMS - OPTIMIZED WITH RETURNS
    // -----------------------------
    if ($action === 'getItems') {
        $grn_id = intval($_POST['grn_id'] ?? ($_GET['grn_id'] ?? 0));
        if ($grn_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Valid GRN ID is required']);
            exit;
        }

        $sql = "
        SELECT 
            gri.grn_item_id,
            gri.grn_id,
            gri.item_id,
            i.item_name,
            gri.qty_received,
            gri.unit_price,
            COALESCE(gri.discount_percentage, 0) AS discount_percentage,
            COALESCE(gri.discount_amount, 0) AS discount_amount,
            COALESCE(gri.tax_percentage, 0) AS tax_percentage,
            COALESCE(gri.tax_amount, 0) AS tax_amount,
            gri.amount,
            COALESCE(SUM(gri2.return_qty), 0) AS total_return_qty,
            (gri.qty_received - COALESCE(SUM(gri2.return_qty), 0)) AS balance_qty
        FROM goods_receipt_items gri
        LEFT JOIN items i 
            ON i.item_id = gri.item_id
        LEFT JOIN goods_return_items gri2
            ON gri2.grn_item_id = gri.grn_item_id
        WHERE gri.grn_id = $grn_id
        GROUP BY gri.grn_item_id
        ";

        $rows = exeSql($sql);
        if ($rows === false || $rows === null) {
            echo json_encode(['status' => 'error', 'message' => 'Database error while fetching GRN items']);
            exit;
        }

        ob_end_clean();
        echo json_encode(['status' => 'success', 'data' => $rows]);
        exit;
    }

    // -----------------------------
    // DELETE
    // -----------------------------
    if ($action === 'delete') {
        $grn_id = intval($_POST['grn_id'] ?? 0);
        if ($grn_id <= 0) throw new Exception('Valid GRN ID is required');

        // Check if GRN has returns
        // $hasReturns = exeSql("SELECT COUNT(*) as cnt FROM goods_return_notes WHERE grn_id = $grn_id");
        // if ($hasReturns && $hasReturns[0]['cnt'] > 0) {
        //     throw new Exception('Cannot delete GRN with existing returns. Please delete returns first.');
        // }

        try {
            excuteSql("START TRANSACTION");
            
            
            // Delete GRN header
            excuteSql("UPDATE goods_receipts set status = 'Cancelled' WHERE grn_id = $grn_id");
            
            excuteSql("COMMIT");
            
            ob_end_clean();
            echo json_encode(['status' => 'success', 'message' => 'GRN Cancelled successfully']);
            exit;
        } catch (Exception $e) {
            excuteSql("ROLLBACK");
            throw $e;
        }
    }

    throw new Exception('Invalid or missing action parameter: ' . $action);
    
} catch (Exception $e) {
    ob_end_clean();
    excuteSql("ROLLBACK");
    error_log('GRN API Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'code' => 'GRN-1001',
        'debug_info' => [
            'received_post' => $_POST,
            'received_get' => $_GET,
            'timestamp' => date('Y-m-d H:i:s'),
        ]
    ]);
}