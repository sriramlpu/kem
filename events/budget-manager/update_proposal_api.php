<?php
session_start();
header('Content-Type: application/json');

// -------------------- DATABASE CONNECTION --------------------
$servername = "localhost";
$username   = "kmkglobal_web";
$password   = "tI]rfPhdOo9zHdKw";
$dbname     = "kmkglobal_web";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// -------------------- GET ACTION --------------------
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'update_budget_discount':
        updateBudgetAndDiscount($conn);
        break;
    
    case 'update_item':
        updateItem($conn);
        break;
    
    case 'add_item':
        addItem($conn);
        break;
    
    case 'delete_item':
        deleteItem($conn);
        break;
    
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

$conn->close();

// -------------------- UPDATE BUDGET AND DISCOUNT --------------------
function updateBudgetAndDiscount($conn) {
    $client_id = $_POST['client_id'] ?? 0;
    $budget = $_POST['budget'] ?? 0;
    $discount = $_POST['discount'] ?? 0;

    if (!$client_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid client ID']);
        return;
    }

    // Calculate the correct final budget (subtotal - discount)
    // The budget input from admin should already be the final budget after discount
    $final_budget = floatval($budget);
    
    // Calculate budget_draft_sales from proposal items
    $sql_items = "SELECT COALESCE(SUM(total_price), 0) as subtotal FROM proposal_items WHERE client_id = ?";
    $stmt_items = $conn->prepare($sql_items);
    $stmt_items->bind_param("i", $client_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    $row = $result_items->fetch_assoc();
    $subtotal = floatval($row['subtotal']);
    $stmt_items->close();

    // Update budget_draft_sales (subtotal from items), discount_amount, and final_budget
    $sql = "UPDATE clients 
            SET budget_draft_sales = ?, 
                discount_amount = ?,
                final_budget = ? 
            WHERE client_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("dddi", $subtotal, $discount, $final_budget, $client_id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Budget and discount updated successfully',
            'new_budget' => $final_budget,
            'new_discount' => $discount,
            'subtotal' => $subtotal
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update budget and discount']);
    }

    $stmt->close();
}

// -------------------- UPDATE ITEM --------------------
function updateItem($conn) {
    $item_id = $_POST['item_id'] ?? 0;
    $item_name = $_POST['item_name'] ?? '';
    $item_description = $_POST['item_description'] ?? '';
    $quantity = $_POST['quantity'] ?? 1;
    $unit_price = $_POST['unit_price'] ?? 0;
    $amount = $_POST['amount'] ?? 0;

    if (!$item_id || !$item_name) {
        echo json_encode(['success' => false, 'error' => 'Invalid input data']);
        return;
    }

    // Calculate total_price based on quantity and unit_price
    $total_price = $quantity * $unit_price;

    $sql = "UPDATE proposal_items 
            SET item_name = ?, 
                item_type = ?, 
                quantity = ?, 
                unit_price = ?, 
                total_price = ? 
            WHERE proposal_item_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssiddi", $item_name, $item_description, $quantity, $unit_price, $total_price, $item_id);

    if ($stmt->execute()) {
        // After updating item, recalculate budget_draft_sales for the client
        $sql_client = "SELECT client_id FROM proposal_items WHERE proposal_item_id = ?";
        $stmt_client = $conn->prepare($sql_client);
        $stmt_client->bind_param("i", $item_id);
        $stmt_client->execute();
        $result = $stmt_client->get_result();
        $client = $result->fetch_assoc();
        $client_id = $client['client_id'];
        $stmt_client->close();

        // Update budget_draft_sales
        $sql_update = "UPDATE clients 
                       SET budget_draft_sales = (
                           SELECT COALESCE(SUM(total_price), 0) 
                           FROM proposal_items 
                           WHERE client_id = ?
                       )
                       WHERE client_id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ii", $client_id, $client_id);
        $stmt_update->execute();
        $stmt_update->close();

        echo json_encode([
            'success' => true, 
            'message' => 'Item updated successfully',
            'item_id' => $item_id,
            'total_price' => $total_price
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update item: ' . $stmt->error]);
    }

    $stmt->close();
}

// -------------------- ADD ITEM --------------------
function addItem($conn) {
    $client_id = $_POST['client_id'] ?? 0;
    $item_name = $_POST['item_name'] ?? '';
    $item_description = $_POST['item_description'] ?? '';
    $quantity = $_POST['quantity'] ?? 1;
    $unit_price = $_POST['unit_price'] ?? 0;
    $amount = $_POST['amount'] ?? 0;

    if (!$client_id || !$item_name) {
        echo json_encode(['success' => false, 'error' => 'Invalid input data']);
        return;
    }

    // Calculate total_price based on quantity and unit_price
    $total_price = $quantity * $unit_price;

    $sql = "INSERT INTO proposal_items (client_id, item_type, item_name, quantity, unit_price, total_price) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issids", $client_id, $item_description, $item_name, $quantity, $unit_price, $total_price);

    if ($stmt->execute()) {
        $new_item_id = $conn->insert_id;
        
        // Update budget_draft_sales after adding new item
        $sql_update = "UPDATE clients 
                       SET budget_draft_sales = (
                           SELECT COALESCE(SUM(total_price), 0) 
                           FROM proposal_items 
                           WHERE client_id = ?
                       )
                       WHERE client_id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ii", $client_id, $client_id);
        $stmt_update->execute();
        $stmt_update->close();

        echo json_encode([
            'success' => true, 
            'message' => 'Item added successfully',
            'item_id' => $new_item_id,
            'total_price' => $total_price
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to add item: ' . $stmt->error]);
    }

    $stmt->close();
}

// -------------------- DELETE ITEM --------------------
function deleteItem($conn) {
    $item_id = $_POST['item_id'] ?? 0;

    if (!$item_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid item ID']);
        return;
    }

    // Get client_id before deleting
    $sql_client = "SELECT client_id FROM proposal_items WHERE proposal_item_id = ?";
    $stmt_client = $conn->prepare($sql_client);
    $stmt_client->bind_param("i", $item_id);
    $stmt_client->execute();
    $result = $stmt_client->get_result();
    $client = $result->fetch_assoc();
    $client_id = $client['client_id'];
    $stmt_client->close();

    $sql = "DELETE FROM proposal_items WHERE proposal_item_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $item_id);

    if ($stmt->execute()) {
        // Update budget_draft_sales after deletion
        $sql_update = "UPDATE clients 
                       SET budget_draft_sales = (
                           SELECT COALESCE(SUM(total_price), 0) 
                           FROM proposal_items 
                           WHERE client_id = ?
                       )
                       WHERE client_id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ii", $client_id, $client_id);
        $stmt_update->execute();
        $stmt_update->close();

        echo json_encode([
            'success' => true, 
            'message' => 'Item deleted successfully',
            'item_id' => $item_id
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete item']);
    }

    $stmt->close();
}
?>