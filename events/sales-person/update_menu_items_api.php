<?php
// Set the content type to JSON
header('Content-Type: application/json');

// Start session
session_start();

// Database connection details
$servername = "localhost";
$username = "kmkglobal_web";
$password = "tI]rfPhdOo9zHdKw";
$dbname = "kmkglobal_web";

// Connect to the database
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => "Database connection failed: " . $conn->connect_error]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    $conn->close();
    exit;
}

// 1. Validate Input Data
$client_id = $_POST['client_id'] ?? null;
$proposal_items_json = $_POST['proposal_items'] ?? []; 

if (empty($client_id) || !is_numeric($client_id)) {
    echo json_encode(['success' => false, 'error' => 'Invalid client ID.']);
    $conn->close();
    exit;
}

// Start a transaction for data integrity
$conn->begin_transaction();

try {
    
    // --- STEP 1: Delete all existing menu items ONLY for this proposal ---
    // This is the CRITICAL step to preserve decor/misc items.
    $sql_delete = "DELETE FROM proposal_items WHERE client_id = ? AND item_type = 'menu'";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $client_id);
    
    if (!$stmt_delete->execute()) {
        throw new Exception("Error deleting existing menu items: " . $stmt_delete->error);
    }
    $stmt_delete->close();


    // --- STEP 2: Insert the newly selected menu items with custom quantity ---
    $new_menu_gross_budget = 0;
    
    if (!empty($proposal_items_json)) {
        
        $sql_insert = "INSERT INTO proposal_items (client_id, item_id, item_name, item_type, unit_price, quantity, total_price) VALUES (?, ?, ?, 'menu', ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);

        foreach ($proposal_items_json as $item_json) {
            $item_data = json_decode($item_json, true);
            
            $menu_id = $item_data['id'] ?? null;
            $unit_price = (float)($item_data['price'] ?? 0);
            $quantity = (int)($item_data['qty'] ?? 0); 
            
            if (!$menu_id || $quantity <= 0) continue;

            // Fetch the item_name
            $sql_menu_name = "SELECT item_name FROM menu_items WHERE menu_id = ?";
            $stmt_menu_name = $conn->prepare($sql_menu_name);
            $stmt_menu_name->bind_param("i", $menu_id);
            $stmt_menu_name->execute();
            $menu_name = $stmt_menu_name->get_result()->fetch_assoc()['item_name'] ?? 'Unknown Menu Item';
            $stmt_menu_name->close();

            // Calculate total price for this item
            $item_total_price = $unit_price * $quantity; 
            $new_menu_gross_budget += $item_total_price;

            // Bind explicitly: (i, i, s, d, i, d)
            if (!$stmt_insert->bind_param("iisdid", $client_id, $menu_id, $menu_name, $unit_price, $quantity, $item_total_price)) {
                 throw new Exception("Binding parameters failed for menu item.");
            }
            
            if (!$stmt_insert->execute()) {
                throw new Exception("Error inserting menu item: " . $stmt_insert->error);
            }
        }
        $stmt_insert->close();
    }
    
    // --- STEP 3: Recalculate Final Budget for ALL proposal items ---

    $sql_total_budget = "SELECT SUM(total_price) as total FROM proposal_items WHERE client_id = ?";
    $stmt_total = $conn->prepare($sql_total_budget);
    $stmt_total->bind_param("i", $client_id);
    $stmt_total->execute();
    $result_total = $stmt_total->get_result();
    $total_gross_budget = $result_total->fetch_assoc()['total'] ?? 0;
    $stmt_total->close();
    
    // Fetch current client data to retain discount/stage
    $sql_client_info = "SELECT workflow_stage, discount_amount FROM clients WHERE client_id = ?";
    $stmt_info = $conn->prepare($sql_client_info);
    $stmt_info->bind_param("i", $client_id);
    $stmt_info->execute();
    $client_info = $stmt_info->get_result()->fetch_assoc();
    $current_stage = $client_info['workflow_stage'] ?? 'LEAD_CREATED';
    $discount_amount = (float)($client_info['discount_amount'] ?? 0);
    $stmt_info->close();
    
    // Apply the existing discount to the new gross total
    $final_budget_client = $total_gross_budget - $discount_amount;
    
    $new_stage = $current_stage;

    // Logic to determine the new stage after edit
    if (in_array($current_stage, ['LEAD_CREATED', 'SALES_DRAFT', 'ADMIN_REVIEW'])) {
         if ($total_gross_budget > 0) { // If any items exist (menu, decor, misc)
             $new_stage = 'ADMIN_REVIEW'; 
         } else {
             $new_stage = 'SALES_DRAFT'; 
         }
    }
    
    // Update the client record
    $sql_update_client = "UPDATE clients SET final_budget = ?, workflow_stage = ? WHERE client_id = ?";
    $stmt_update_client = $conn->prepare($sql_update_client);
    $stmt_update_client->bind_param("dsi", $final_budget_client, $new_stage, $client_id);
    
    if (!$stmt_update_client->execute()) {
        throw new Exception("Error updating client budget/stage: " . $stmt_update_client->error);
    }
    $stmt_update_client->close();


    // --- STEP 4: Commit Transaction ---
    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Menu items updated successfully and gross budget recalculated! Proposal moved to **' . htmlspecialchars($new_stage) . '**.',
        'new_budget' => number_format($final_budget_client, 2),
        'new_stage' => $new_stage
    ]);

} catch (Exception $e) {
    // --- STEP 5: Rollback Transaction and Handle Error ---
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>