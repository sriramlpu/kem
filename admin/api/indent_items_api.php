<?php
// Turn off error display to prevent HTML output in JSON response
ini_set('display_errors', 0);
// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');
// Start output buffering to catch any unwanted output
ob_start();
try {
    header('Content-Type: application/json');
    require_once('../../functions.php');
    
    $action = $_POST['action'] ?? ($_GET['action'] ?? '');
    
    // Add an item to indent
    if ($action === 'add') {
        $indent_id = intval($_POST['indent_id'] ?? 0);
        $item_id = intval($_POST['item_id'] ?? 0);
        $qty_requested = floatval($_POST['qty_requested'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        
        if ($indent_id <= 0 || $item_id <= 0 || $qty_requested <= 0) {
            throw new Exception('All fields are required and must be valid values');
        }
        
        // Build the INSERT query
        $sql = "INSERT INTO indent_items (indent_id, item_id, qty_requested, description) 
                VALUES ('$indent_id', '$item_id', '$qty_requested', " . 
                (empty($description) ? "NULL" : "'$description'") . ")";
        
        $result = exeSql($sql);
        
        if ($result) {
            ob_end_clean();
            echo json_encode(['status' => 'success', 'message' => 'Item added successfully']);
        } else {
            throw new Exception('Insert failed');
        }
        exit;
    }
    
    // List items for a specific indent
    if ($action === 'list') {
        $indent_id = intval($_POST['indent_id'] ?? 0);
        if ($indent_id <= 0) {
            throw new Exception('Valid indent ID is required');
        }
        
        $sql = "SELECT ii.indent_item_id, ii.item_id, ii.qty_requested, ii.description, ii.status, it.item_name
                FROM indent_items ii
                JOIN items it ON ii.item_id = it.item_id
                WHERE ii.indent_id = '$indent_id'";
        
        $items = exeSql($sql);
        
        // Map statuses to the new system
        foreach ($items as &$item) {
            switch ($item['line_status']) {
                case 'Open':
                    $item['line_status'] = 'OPEN';
                    break;
                case 'Partially Ordered':
                case 'Fully Ordered':
                    $item['line_status'] = 'CLOSED';
                    break;
                default:
                    $item['line_status'] = 'OPEN';
            }
        }
        
        ob_end_clean();
        echo json_encode(['status' => 'success', 'data' => $items]);
        exit;
    }
    
    // Update an indent item
    if ($action === 'update') {
        $id = intval($_POST['indent_item_id'] ?? 0);
        $qty_requested = floatval($_POST['qty_requested'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        
        if ($id <= 0 || $qty_requested <= 0) {
            throw new Exception('All fields are required and must be valid values');
        }
        
        $sql = "UPDATE indent_items 
                SET qty_requested = '$qty_requested', 
                    description = " . (empty($description) ? "NULL" : "'$description'") . "
                WHERE indent_item_id = '$id'";
        
        $result = exeSql($sql);
        
        if ($result) {
            ob_end_clean();
            echo json_encode(['status' => 'success', 'message' => 'Item updated successfully']);
        } else {
            throw new Exception('Update failed');
        }
        exit;
    }
    
    // Delete an indent item
    if ($action === 'delete') {
        $id = intval($_POST['indent_item_id'] ?? 0);
        if ($id <= 0) {
            throw new Exception('Valid item ID is required');
        }
        
        $sql = "DELETE FROM indent_items WHERE indent_item_id = '$id'";
        $result = exeSql($sql);
        
        if ($result) {
            ob_end_clean();
            echo json_encode(['status' => 'success', 'message' => 'Item deleted successfully']);
        } else {
            throw new Exception('Delete failed');
        }
        exit;
    }

    // Get items by indent ID
    if ($action === 'getByIndent') {
        $indentId = intval($_POST['indent_id'] ?? 0);
        
        if ($indentId <= 0) {
            throw new Exception('Valid indent ID is required');
        }
        
        $result = getSubject("indent_items", "indent_id = '$indentId'");
        
        if ($result) {
            ob_end_clean();
            echo json_encode(['status' => 'success', 'data' => json_encode($result)]);
        } else {
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'No items found for this indent']);
        }
        exit;
    }
    
    throw new Exception('Invalid action');
    
} catch (Exception $e) {
    // Clear any output buffer
    ob_end_clean();
    
    // Log the error
    error_log('Indent Items API Error: ' . $e->getMessage());
    
    // Return the error as JSON
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'code' => 'GEDM-1003' // Match the error code from your error message
    ]);
}
?>