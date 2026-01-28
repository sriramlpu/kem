<?php
session_start();
header('Content-Type: application/json');
require_once('../../functions.php');

// Clear any output buffers
if (ob_get_level()) {
    ob_end_clean();
}

try {
    $action = $_POST['action'] ?? ($_GET['action'] ?? '');

    // ==================== LIST ====================
    if ($action === 'list') {
        $draw = intval($_POST['draw'] ?? 1);
        $start = intval($_POST['start'] ?? 0);
        $length = intval($_POST['length'] ?? 25);
        
        $dateFrom = trim($_POST['date_from'] ?? '');
        $dateTo = trim($_POST['date_to'] ?? '');
        $categoryId = intval($_POST['category_id'] ?? 0);
        $itemName = trim($_POST['item_name'] ?? '');
        $vendorId = intval($_POST['vendor_id'] ?? 0);
        $branchId = intval($_POST['branch_id'] ?? 0);
        $sortBy = trim($_POST['sort_by'] ?? 'total_spent');

        // Build WHERE clause
        $whereClauses = [];
        
        if ($dateFrom && $dateTo) {
            $whereClauses[] = "gr.grn_date BETWEEN '$dateFrom' AND '$dateTo'";
        } elseif ($dateFrom) {
            $whereClauses[] = "gr.grn_date >= '$dateFrom'";
        } elseif ($dateTo) {
            $whereClauses[] = "gr.grn_date <= '$dateTo'";
        }
        
        if ($categoryId > 0) {
            $whereClauses[] = "i.category_id = $categoryId";
        }
        
        if ($itemName !== '') {
            $whereClauses[] = "i.item_name LIKE '%" . addslashes($itemName) . "%'";
        }
        
        if ($vendorId > 0) {
            $whereClauses[] = "gr.vendor_id = $vendorId";
        }
        
        if ($branchId > 0) {
            $whereClauses[] = "gri.branch_id = $branchId";
        }

        $where = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        // Sorting
        $orderBy = 'total_spent DESC';
        if ($sortBy === 'quantity') {
            $orderBy = 'net_quantity DESC';
        } elseif ($sortBy === 'item_name') {
            $orderBy = 'i.item_name ASC';
        }

        // Get total records count
        $totalSql = exeSql("SELECT COUNT(DISTINCT item_id) AS total FROM items");
        $recordsTotal = intval($totalSql[0]['total'] ?? 0);

        // Main query with proper NULL handling
        $sql = "
        SELECT 
            i.item_id,
            i.item_name,
            COALESCE(ic.category_name, 'Uncategorized') AS category_name,
            COALESCE(SUM(gri.qty_received), 0) AS total_qty_purchased,
            COALESCE(SUM(CASE WHEN grti.grn_item_id IS NOT NULL THEN grti.return_qty ELSE 0 END), 0) AS total_qty_returned,
            (COALESCE(SUM(gri.qty_received), 0) - COALESCE(SUM(CASE WHEN grti.grn_item_id IS NOT NULL THEN grti.return_qty ELSE 0 END), 0)) AS net_quantity,
            
            CASE 
                WHEN SUM(gri.qty_received - COALESCE(grti.return_qty, 0)) > 0 THEN
                    AVG(gri.unit_price)
                ELSE 0
            END AS avg_unit_price,
            
            SUM(gri.unit_price * (gri.qty_received - COALESCE(grti.return_qty, 0))) AS total_before_discount,
            
            SUM((gri.discount_amount * (gri.qty_received - COALESCE(grti.return_qty, 0))) / NULLIF(gri.qty_received, 0)) AS total_discount,
            
            SUM((gri.tax_amount * (gri.qty_received - COALESCE(grti.return_qty, 0))) / NULLIF(gri.qty_received, 0)) AS total_gst,
            
            SUM(
                ((gri.unit_price * (gri.qty_received - COALESCE(grti.return_qty, 0))) - 
                 ((COALESCE(gri.discount_amount, 0) * (gri.qty_received - COALESCE(grti.return_qty, 0))) / NULLIF(gri.qty_received, 0))) +
                ((COALESCE(gri.tax_amount, 0) * (gri.qty_received - COALESCE(grti.return_qty, 0))) / NULLIF(gri.qty_received, 0))
            ) AS total_spent,
            
            MAX(gr.grn_date) AS last_purchase_date
            
        FROM items i
        LEFT JOIN categories ic ON i.category_id = ic.category_id
        INNER JOIN goods_receipt_items gri ON gri.item_id = i.item_id
        INNER JOIN goods_receipts gr ON gr.grn_id = gri.grn_id
        LEFT JOIN (
            SELECT grn_item_id, SUM(return_qty) AS return_qty
            FROM goods_return_items
            GROUP BY grn_item_id
        ) grti ON grti.grn_item_id = gri.grn_item_id
        $where
        GROUP BY i.item_id
        HAVING net_quantity > 0
        ORDER BY $orderBy
        LIMIT $start, $length
        ";

        $data = exeSql($sql);
        
        // Ensure data is array
        if (!is_array($data)) {
            $data = [];
        }

        // Get filtered count
        $filterCountSql = "
        SELECT COUNT(DISTINCT i.item_id) AS total
        FROM items i
        INNER JOIN goods_receipt_items gri ON gri.item_id = i.item_id
        INNER JOIN goods_receipts gr ON gr.grn_id = gri.grn_id
        $where
        ";
        $filterCount = exeSql($filterCountSql);
        $recordsFiltered = intval($filterCount[0]['total'] ?? 0);

        // Calculate summary - simplified query
        $summarySql = "
        SELECT 
            COUNT(DISTINCT i.item_id) AS total_items,
            COALESCE(SUM(gri.qty_received - COALESCE(grti.return_qty, 0)), 0) AS total_quantity,
            COALESCE(SUM(
                ((gri.unit_price * (gri.qty_received - COALESCE(grti.return_qty, 0))) - 
                 ((COALESCE(gri.discount_amount, 0) * (gri.qty_received - COALESCE(grti.return_qty, 0))) / NULLIF(gri.qty_received, 0))) +
                ((COALESCE(gri.tax_amount, 0) * (gri.qty_received - COALESCE(grti.return_qty, 0))) / NULLIF(gri.qty_received, 0))
            ), 0) AS total_spent
        FROM items i
        INNER JOIN goods_receipt_items gri ON gri.item_id = i.item_id
        INNER JOIN goods_receipts gr ON gr.grn_id = gri.grn_id
        LEFT JOIN (
            SELECT grn_item_id, SUM(return_qty) AS return_qty
            FROM goods_return_items
            GROUP BY grn_item_id
        ) grti ON grti.grn_item_id = gri.grn_item_id
        $where
        ";
        
        $summary = exeSql($summarySql);
        $summaryData = [
            'total_items' => intval($summary[0]['total_items'] ?? 0),
            'total_quantity' => floatval($summary[0]['total_quantity'] ?? 0),
            'total_spent' => floatval($summary[0]['total_spent'] ?? 0),
            'avg_price' => 0
        ];
        
        if ($summaryData['total_quantity'] > 0) {
            $summaryData['avg_price'] = $summaryData['total_spent'] / $summaryData['total_quantity'];
        }

        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'status' => 'success',
            'data' => $data,
            'summary' => $summaryData
        ]);
        exit;
    }

    // ==================== GET ITEM HISTORY ====================
    if ($action === 'getItemHistory') {
        $itemId = intval($_POST['item_id'] ?? 0);
        $dateFrom = trim($_POST['date_from'] ?? '');
        $dateTo = trim($_POST['date_to'] ?? '');

        if ($itemId <= 0) {
            throw new Exception('Valid Item ID is required');
        }

        $whereClauses = ["gri.item_id = $itemId"];
        
        if ($dateFrom && $dateTo) {
            $whereClauses[] = "gr.grn_date BETWEEN '$dateFrom' AND '$dateTo'";
        } elseif ($dateFrom) {
            $whereClauses[] = "gr.grn_date >= '$dateFrom'";
        } elseif ($dateTo) {
            $whereClauses[] = "gr.grn_date <= '$dateTo'";
        }

        $where = 'WHERE ' . implode(' AND ', $whereClauses);

        $sql = "
        SELECT 
            gr.grn_date,
            gr.grn_number,
            v.vendor_name,
            gr.invoice_number,
            gri.qty_received AS qty_purchased,
            COALESCE(SUM(grti.return_qty), 0) AS qty_returned,
            (gri.qty_received - COALESCE(SUM(grti.return_qty), 0)) AS net_qty,
            gri.unit_price,
            (COALESCE(gri.discount_amount, 0) * (gri.qty_received - COALESCE(SUM(grti.return_qty), 0))) / NULLIF(gri.qty_received, 0) AS discount,
            (COALESCE(gri.tax_amount, 0) * (gri.qty_received - COALESCE(SUM(grti.return_qty), 0))) / NULLIF(gri.qty_received, 0) AS gst,
            (
                ((gri.unit_price * (gri.qty_received - COALESCE(SUM(grti.return_qty), 0))) - 
                 ((COALESCE(gri.discount_amount, 0) * (gri.qty_received - COALESCE(SUM(grti.return_qty), 0))) / NULLIF(gri.qty_received, 0))) +
                ((COALESCE(gri.tax_amount, 0) * (gri.qty_received - COALESCE(SUM(grti.return_qty), 0))) / NULLIF(gri.qty_received, 0))
            ) AS total_amount
        FROM goods_receipt_items gri
        JOIN goods_receipts gr ON gr.grn_id = gri.grn_id
        JOIN vendors v ON v.vendor_id = gr.vendor_id
        LEFT JOIN goods_return_items grti ON grti.grn_item_id = gri.grn_item_id
        $where
        GROUP BY gri.grn_item_id
        ORDER BY gr.grn_date DESC
        ";

        $data = exeSql($sql);
        
        if (!is_array($data)) {
            $data = [];
        }

        echo json_encode([
            'status' => 'success',
            'data' => $data
        ]);
        exit;
    }

    throw new Exception('Invalid action');

} catch (Exception $e) {
    error_log('Item-wise API Error: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => []
    ]);
}
?>