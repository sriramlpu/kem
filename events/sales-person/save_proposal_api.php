<?php
// Set headers for JSON response
header('Content-Type: application/json');

// -------------------- 1. DATABASE CONNECTION --------------------
$servername = "localhost";
$username   = "kmkglobal_web";
$password   = "tI]rfPhdOo9zHdKw";
$dbname     = "kmkglobal_web";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "Connection failed: " . $conn->connect_error]);
    exit();
}

// Enable transactional integrity for multi-table update
$conn->autocommit(FALSE);

// -------------------- 2. INPUT VALIDATION & COLLECTION --------------------
$client_id          = $_POST['client_id'] ?? null;
$budget_gross       = floatval($_POST['budget_draft_sales'] ?? 0); // Menu + Decor Subtotal
$discount_amount    = floatval($_POST['discount_amount'] ?? 0);
$sales_notes_raw    = $_POST['sales_notes'] ?? '';
$logistics_cost     = floatval($_POST['logistics_cost'] ?? 0);
$vas_cost           = floatval($_POST['vas_cost'] ?? 0);
$proposal_items     = $_POST['proposal_items'] ?? []; 
$decor_id           = intval($_POST['decor_id'] ?? 0); 
$guest_count        = intval($_POST['expected_guest_count'] ?? 0); // Grabbed for notes

if (!$client_id || !is_numeric($client_id) || $budget_gross <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => "Invalid Client ID or Gross Budget provided."]);
    $conn->close();
    exit();
}

// Calculate the FINAL price (Menu+Decor Subtotal - Discount + Flat Costs)
$final_budget_price = max(0, $budget_gross - $discount_amount + $logistics_cost + $vas_cost);

// Prepare comprehensive notes for the 'sales_notes' field in 'clients'
$combined_notes = "GUEST COUNT: {$guest_count}\n" .
                  "LOGISTICS COST: ₹" . number_format($logistics_cost, 2) . "\n" .
                  "VAS COST: ₹" . number_format($vas_cost, 2) . "\n" .
                  "DISCOUNT: ₹" . number_format($discount_amount, 2) . "\n\n" .
                  "SALES NOTES:\n" . $sales_notes_raw;


// -------------------- 3. UPDATE CLIENTS TABLE (SUMMARY & WORKFLOW) --------------------
// MODIFICATION: Set to SALES_DRAFT and DRAFT status
$new_workflow_stage = 'SALES_DRAFT'; 
$admin_status = 'PENDING'; // Keep as PENDING, but DRAFT stage implies no action needed yet.

// Get current stage for confirmation message
$sql_check_stage = "SELECT workflow_stage FROM clients WHERE client_id = ?";
$stmt_check_stage = $conn->prepare($sql_check_stage);
$stmt_check_stage->bind_param("i", $client_id);
$stmt_check_stage->execute();
$current_stage_result = $stmt_check_stage->get_result();
$current_stage_row = $current_stage_result->fetch_assoc();
$stmt_check_stage->close();

if ($current_stage_row['workflow_stage'] === 'EXECUTIVE_APPROVED') {
    // If it was already approved, keep the highest status, don't revert to draft
    $new_workflow_stage = 'EXECUTIVE_APPROVED';
    $admin_status = 'APPROVED';
} elseif ($current_stage_row['workflow_stage'] === 'ADMIN_APPROVED' || $current_stage_row['workflow_stage'] === 'EXECUTIVE_REVIEW') {
    // If it was already approved/in review, save the draft but mark it for review again later
    $new_workflow_stage = 'SALES_DRAFT';
    $admin_status = 'PENDING';
} else {
    $new_workflow_stage = 'SALES_DRAFT'; // LEAD_CREATED or REJECTED go to DRAFT
    $admin_status = 'PENDING';
}

$sql_update_client = "UPDATE clients SET 
    budget_draft_sales = ?, 
    sales_notes = ?, 
    workflow_stage = ?,
    admin_status = ?
WHERE client_id = ?";

$stmt_client = $conn->prepare($sql_update_client);
if ($stmt_client === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "Error preparing client update statement: " . $conn->error]);
    $conn->close();
    exit();
}

// Bind parameters: (d)double for final price, (s)string for notes/stage/status, (i)integer for client_id
$stmt_client->bind_param(
    "dsssi",
    $final_budget_price,
    $combined_notes,
    $new_workflow_stage,
    $admin_status,
    $client_id
);

if (!$stmt_client->execute()) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "DB Client Update Error: " . $stmt_client->error]);
    $stmt_client->close();
    $conn->close();
    exit();
}
$stmt_client->close();

// -------------------- 4. UPDATE PROPOSAL_ITEMS TABLE (DETAILS) --------------------

// A. Delete existing proposal items for this single client
$sql_delete = "DELETE FROM proposal_items WHERE client_id = ?";
$stmt_delete = $conn->prepare($sql_delete);
$stmt_delete->bind_param("i", $client_id);

// Check if deletion was successful before continuing
if (!$stmt_delete->execute()) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "Failed to clear previous proposal items: " . $stmt_delete->error]);
    $stmt_delete->close();
    $conn->close();
    exit();
}
$stmt_delete->close();

// B. Insert new line items
$sql_insert_item = "INSERT INTO proposal_items (client_id, item_type, item_id, item_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt_item = $conn->prepare($sql_insert_item);

if ($stmt_item === false) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "Error preparing item insert statement: " . $conn->error]);
    $conn->close();
    exit();
}

$errors = [];

// Helper function to fetch item details (using the correct table names and IDs from your schema)
function fetch_item_details_safe($conn, $id, $table, $name_field, $price_field, $id_field) {
    $sql = "SELECT {$name_field} AS item_name, {$price_field} AS item_price FROM {$table} WHERE {$id_field} = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $details = $result->fetch_assoc();
    $stmt->close();
    return $details;
}

// 1. Insert Decor Item
if ($decor_id > 0) {
    $decor_details = fetch_item_details_safe($conn, $decor_id, 'decor_packages', 'package_name', 'base_price', 'decor_id');
    
    if ($decor_details) {
        $item_type = 'decor';
        $item_name = $decor_details['item_name'];
        $unit_price = floatval($decor_details['item_price']);
        $quantity = 1; 
        $total_cost = $unit_price; 
        $item_id_insert = $decor_id;

        // Bind parameters: (i)client_id, (s)item_type, (i)item_id, (s)item_name, (i)quantity, (d)unit_price, (d)total_price
        $stmt_item->bind_param("isisidd", $client_id, $item_type, $item_id_insert, $item_name, $quantity, $unit_price, $total_cost);
        
        if (!$stmt_item->execute()) {
            $errors[] = "Decor insert error: " . $stmt_item->error;
        }
    } else {
         $errors[] = "Decor ID {$decor_id} not found.";
    }
}


// 2. Insert Menu Items
foreach ($proposal_items as $json_item) {
    $item = json_decode($json_item, true);
    
    if (isset($item['id']) && isset($item['qty']) && isset($item['price'])) {
        $item_id = intval($item['id']);
        $unit_price = floatval($item['price']);
        $quantity = intval($item['qty']);
        $total_cost = $unit_price * $quantity;
        $item_type = 'menu';

        // Fetch the item_name from the database using menu_id
        $menu_details = fetch_item_details_safe($conn, $item_id, 'menu_items', 'item_name', 'price_per_plate', 'menu_id');

        if ($menu_details) {
             $item_name = $menu_details['item_name'];
             // Bind parameters: (i)client_id, (s)item_type, (i)item_id, (s)item_name, (i)quantity, (d)unit_price, (d)total_price
             $stmt_item->bind_param("isisidd", $client_id, $item_type, $item_id, $item_name, $quantity, $unit_price, $total_cost);
             if (!$stmt_item->execute()) {
                 $errors[] = "Menu item #{$item_id} insert error: " . $stmt_item->error;
             }
        } else {
             $errors[] = "Menu item ID {$item_id} not found.";
        }
    } else {
        $errors[] = "Invalid menu item data received.";
    }
}
$stmt_item->close();


// -------------------- 5. COMMIT OR ROLLBACK --------------------
if (count($errors) > 0) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "Proposal submission failed due to item errors. Transaction rolled back. Details: " . implode('; ', $errors)]);
} else {
    $conn->commit();
    echo json_encode([
        'success' => true,
        // CONFIRMATION MESSAGE: Reflects the save-draft operation.
        'message' => "Proposal for Client #{$client_id} successfully saved as **{$new_workflow_stage}**." 
    ]);
}

$conn->close();
?>