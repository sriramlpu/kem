<?php
// Fixed grn_items_api.php compatible with your functions
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// Start output buffering to catch any unwanted output
ob_start();
try {
    header('Content-Type: application/json');
    require_once('../../functions.php');
    
    $action = $_POST['action'] ?? ($_GET['action'] ?? '');
    error_log('Action: ' . $action);
    
    // Get items by GRN ID
    if ($action === 'getByGRN') {
        $grnId = intval($_POST['grn_id'] ?? ($_GET['grn_id'] ?? 0));
        
        if ($grnId <= 0) {
            throw new Exception('Valid GRN ID is required');
        }
        
        // Get GRN items using your exeSql function
        $sql = "SELECT gri.*, i.item_name, i.item_code, b.branch_name 
                FROM goods_receipt_items gri 
                LEFT JOIN items i ON gri.item_id = i.item_id 
                LEFT JOIN branches b ON gri.branch_id = b.branch_id 
                WHERE gri.grn_id = $grnId";
        
        $items = exeSql($sql);
        
        ob_end_clean();
        echo json_encode([
            'status' => 'success',
            'data' => $items ?: []
        ]);
        exit;
    }
    
    // Add items to GRN
    if ($action === 'add') {
        $grnId = intval($_POST['grn_id'] ?? 0);
        
        if ($grnId <= 0) {
            throw new Exception('Valid GRN ID is required');
        }
        
        // Check if GRN exists
        $grn = getRowValues("goods_receipts", $grnId, "grn_id");
        if (!$grn) {
            throw new Exception('GRN not found');
        }
        
        // Validate items
        if (!isset($_POST['items']) || !is_array($_POST['items']) || count($_POST['items']) === 0) {
            throw new Exception('At least one item is required');
        }
        
        $totalAmount = 0;
        $items = $_POST['items'];
        
        foreach ($items as $item) {
            $itemId = intval($item['item_id'] ?? 0);
            $quantity = floatval($item['quantity'] ?? 0);
            $unitPrice = floatval($item['unit_price'] ?? 0);
            
            // Validate item
            if ($itemId <= 0) {
                error_log("Invalid item ID: $itemId");
                continue;
            }
            
            // Check if item exists
            $itemExists = getRowValues("items", $itemId, "item_id");
            if (!$itemExists) {
                error_log("Item ID $itemId does not exist in database");
                continue;
            }
            
            // Calculate amount
            $amount = $quantity * $unitPrice;
            $totalAmount += $amount;
            
            // Build SQL for GRN item insertion
            $itemColumns = [
                'grn_id', 'item_id', 'branch_id', 'qty_received', 
                'unit_price', 'amount'
            ];
            
            $itemValues = [
                $grnId,
                $itemId,
                1, // Default branch ID
                $quantity,
                $unitPrice,
                $amount
            ];
            
            // Convert values to SQL format
            foreach ($itemValues as &$value) {
                if (is_string($value)) {
                    $value = "'" . addslashes($value) . "'";
                } elseif (is_null($value)) {
                    $value = "NULL";
                }
                // Numbers remain as is
            }
            
            $itemColumnsStr = implode(', ', $itemColumns);
            $itemValuesStr = implode(', ', $itemValues);
            
            $itemSql = "INSERT INTO goods_receipt_items ($itemColumnsStr) VALUES ($itemValuesStr)";
            error_log("Item Insert SQL: $itemSql");
            
            // Insert using your excuteSql function
            $itemResult = excuteSql($itemSql);
            
            if (!$itemResult) {
                error_log("Failed to insert item: $itemId");
                throw new Exception('Failed to add item to GRN');
            }
            
            error_log("Successfully added item: $itemId");
            
            // Update inventory stock
            updateInventoryStock($grnId, $itemId, 1, $quantity);
        }
        
        // Update GRN total amount
        $updateSql = "UPDATE goods_receipts SET total_amount = $totalAmount WHERE grn_id = $grnId";
        excuteSql($updateSql);
        
        ob_end_clean();
        echo json_encode([
            'status' => 'success',
            'message' => 'Items added successfully',
            'data' => [
                'total_amount' => $totalAmount
            ]
        ]);
        exit;
    }
    
    // Update GRN item
    if ($action === 'update') {
        $grnItemId = intval($_POST['grn_item_id'] ?? 0);
        
        if ($grnItemId <= 0) {
            throw new Exception('Valid GRN Item ID is required');
        }
        
        // Get existing item
        $existingItem = getRowValues("goods_receipt_items", $grnItemId, "grn_item_id");
        if (!$existingItem) {
            throw new Exception('GRN item not found');
        }
        
        // Get and validate form data
        $quantity = floatval($_POST['quantity'] ?? 0);
        $unitPrice = floatval($_POST['unit_price'] ?? 0);
        
        if ($quantity <= 0) {
            throw new Exception('Valid quantity is required');
        }
        
        if ($unitPrice < 0) {
            throw new Exception('Unit price cannot be negative');
        }
        
        // Calculate new amount
        $newAmount = $quantity * $unitPrice;
        $oldAmount = $existingItem['amount'];
        
        // Calculate difference in quantity
        $qtyDiff = $quantity - $existingItem['qty_received'];
        
        // Build SQL for GRN item update
        $updateFields = [
            "qty_received = $quantity",
            "unit_price = $unitPrice",
            "amount = $newAmount"
        ];
        
        $updateFieldsStr = implode(', ', $updateFields);
        
        $sql = "UPDATE goods_receipt_items SET $updateFieldsStr WHERE grn_item_id = $grnItemId";
        error_log('GRN Item Update SQL: ' . $sql);
        
        // Update GRN item using your excuteSql function
        $result = excuteSql($sql);
        
        if (!$result) {
            error_log('Failed to update GRN item - excuteSql returned false');
            throw new Exception('Failed to update GRN item');
        }
        
        error_log('GRN item updated successfully');
        
        // Update inventory stock if quantity changed
        if ($qtyDiff != 0) {
            updateInventoryStock($existingItem['grn_id'], $existingItem['item_id'], $existingItem['branch_id'], $qtyDiff);
        }
        
        // Update GRN total amount
        $grnId = $existingItem['grn_id'];
        $grn = getRowValues("goods_receipts", $grnId, "grn_id");
        $newTotalAmount = $grn['total_amount'] - $oldAmount + $newAmount;
        $updateGrnSql = "UPDATE goods_receipts SET total_amount = $newTotalAmount WHERE grn_id = $grnId";
        excuteSql($updateGrnSql);
        
        ob_end_clean();
        echo json_encode([
            'status' => 'success',
            'message' => 'GRN item updated successfully'
        ]);
        exit;
    }
    
    // Delete item from GRN
    if ($action === 'delete') {
        $grnItemId = intval($_POST['grn_item_id'] ?? 0);
        
        if ($grnItemId <= 0) {
            throw new Exception('Valid GRN Item ID is required');
        }
        
        // Get existing item
        $existingItem = getRowValues("goods_receipt_items", $grnItemId, "grn_item_id");
        if (!$existingItem) {
            throw new Exception('GRN item not found');
        }
        
        $grnId = $existingItem['grn_id'];
        $itemAmount = $existingItem['amount'];
        
        // Delete item
        $deleteSql = "DELETE FROM goods_receipt_items WHERE grn_item_id = $grnItemId";
        $result = excuteSql($deleteSql);
        
        if (!$result) {
            throw new Exception('Failed to delete GRN item');
        }
        
        // Update inventory stock (reduce quantity)
        updateInventoryStock($grnId, $existingItem['item_id'], $existingItem['branch_id'], -$existingItem['qty_received']);
        
        // Update GRN total amount
        $grn = getRowValues("goods_receipts", $grnId, "grn_id");
        $newTotalAmount = $grn['total_amount'] - $itemAmount;
        $updateGrnSql = "UPDATE goods_receipts SET total_amount = $newTotalAmount WHERE grn_id = $grnId";
        excuteSql($updateGrnSql);
        
        ob_end_clean();
        echo json_encode([
            'status' => 'success',
            'message' => 'GRN item deleted successfully'
        ]);
        exit;
    }
    
    throw new Exception('Invalid or missing action parameter: ' . $action);
    
} catch (Exception $e) {
    ob_end_clean();
    
    $errorMessage = $e->getMessage();
    error_log('API Error: ' . $errorMessage);
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $errorMessage,
        'code' => 'GRN-ITEMS-1001',
        'debug_info' => [
            'received_post' => $_POST,
            'received_get' => $_GET,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}

/**
 * Update inventory stock
 */
function updateInventoryStock($grnId, $itemId, $branchId, $quantity) {
    // Check if stock record exists
    $stock = getRowValues('inventory_stock', $itemId, 'item_id');
    
    if ($stock) {
        // Update existing stock
        $newQuantity = $stock['quantity'] + $quantity;
        $updateSql = "UPDATE inventory_stock SET quantity = $newQuantity, updated_at = NOW() WHERE branch_id = $branchId AND item_id = $itemId";
        excuteSql($updateSql);
    } else {
        // Create new stock record
        $insertSql = "INSERT INTO inventory_stock (branch_id, item_id, quantity, updated_at) VALUES ($branchId, $itemId, $quantity, NOW())";
        excuteSql($insertSql);
    }
    
    // Add to stock ledger
    $ledgerSql = "INSERT INTO stock_ledger (branch_id, item_id, ref_type, ref_id, qty_in, qty_out, moved_at) VALUES ($branchId, $itemId, 'GRN', $grnId, " . ($quantity > 0 ? $quantity : 0) . ", " . ($quantity < 0 ? abs($quantity) : 0) . ", NOW())";
    excuteSql($ledgerSql);
}
?>