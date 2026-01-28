<?php
header('Content-Type: application/json');

// Database Connection
$servername = "localhost";
$username   = "kmkglobal_web";
$password   = "tI]rfPhdOo9zHdKw";
$dbname     = "kmkglobal_web";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Connection failed: ' . $conn->connect_error]);
    exit();
}

// Get client_id from GET parameters
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

if ($client_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid client ID provided.']);
    exit();
}

// Fetch client details
$sql_client = "SELECT * FROM clients WHERE client_id = ?";
$stmt_client = $conn->prepare($sql_client);
$stmt_client->bind_param("i", $client_id);
$stmt_client->execute();
$result_client = $stmt_client->get_result();
$client = $result_client->fetch_assoc();
$stmt_client->close();

if (!$client) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Client not found.']);
    exit();
}

// Fetch proposal items
$sql_items = "SELECT * FROM proposal_items WHERE client_id = ?";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $client_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();
$menu_items = $result_items->fetch_all(MYSQLI_ASSOC);
$stmt_items->close();

$conn->close();

// Helper function to format the budget
function format_budget($budget) {
    $numeric_budget = is_numeric($budget) ? floatval($budget) : 0;
    return number_format($numeric_budget, 2);
}

// Process menu items
$processed_items = array_map(function($item) {
    $unit_price = is_numeric($item['unit_price'] ?? 0) ? floatval($item['unit_price']) : 0;
    $quantity = is_numeric($item['quantity'] ?? 0) ? intval($item['quantity']) : 0;
    $subtotal = $unit_price * $quantity;
    
    return [
        'proposal_item_id' => $item['proposal_item_id'],
        'item_name' => $item['item_name'],
        'item_type' => $item['item_type'],
        'quantity' => $quantity,
        'unit_price' => $unit_price,
        'subtotal' => $subtotal,
        'formatted_unit_price' => '₹' . format_budget($unit_price),
        'formatted_subtotal' => '₹' . format_budget($subtotal)
    ];
}, $menu_items);

// Calculate total
$total_cost = array_sum(array_column($processed_items, 'subtotal'));

// Response structure
$response = [
    'status' => 'success',
    'client' => [
        'client_id' => $client['client_id'],
        'client_name' => $client['client_name'],
        'contact_email' => $client['contact_email'],
        'contact_no' => $client['contact_no'],
        'address' => $client['address'],
        'lead_status' => $client['lead_status'],
        'event_type' => $client['event_type'],
        'date_time_of_event' => $client['date_time_of_event'],
        'expected_budget' => $client['expected_budget'],
        'budget_draft_sales' => floatval($client['budget_draft_sales'] ?? 0),
        'final_budget' => floatval($client['final_budget'] ?? 0),
        'services_required' => $client['services_required'],
        'food_category' => $client['food_category'],
        'decor_type' => $client['decor_type'],
        'sales_notes' => $client['sales_notes'],
        'admin_status' => $client['admin_status'],
        'admin_notes' => $client['admin_notes'],
        'admin_approved_by' => $client['admin_approved_by'],
        'admin_approved_at' => $client['admin_approved_at'],
        'workflow_stage' => $client['workflow_stage'],
        'formatted_budget_draft' => '₹' . format_budget($client['budget_draft_sales'] ?? 0),
        'formatted_final_budget' => '₹' . format_budget($client['final_budget'] ?? 0),
        'formatted_event_date' => date('F d, Y \a\t h:i A', strtotime($client['date_time_of_event']))
    ],
    'menu_items' => $processed_items,
    'summary' => [
        'total_items' => count($processed_items),
        'total_cost' => $total_cost,
        'formatted_total_cost' => '₹' . format_budget($total_cost)
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>