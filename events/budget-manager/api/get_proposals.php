<?php
session_start();
header('Content-Type: application/json');

// Database connection
$servername = "localhost";
$username   = "kmkglobal_web";
$password   = "tI]rfPhdOo9zHdKw";
$dbname     = "kmkglobal_web";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

// Get filter parameter
$filter = $_GET['filter'] ?? 'PENDING';
$allowed_filters = ['PENDING', 'APPROVED', 'REJECTED', 'ALL'];

if (!in_array($filter, $allowed_filters)) {
    $filter = 'PENDING';
}

// Build SQL query - Simplified to get all proposals that have admin_status
$sql = "SELECT 
    client_id, client_name, contact_no, lead_status, 
    date_time_of_event, event_type, expected_budget, 
    budget_draft_sales, sales_notes, admin_status,
    services_required, food_category, decor_type,
    workflow_stage, admin_approved_by, admin_approved_at,
    admin_notes, assigned_to
FROM clients 
WHERE 1=1 ";

$params = [];
$types = "";

if ($filter !== 'ALL') {
    $sql .= "AND (admin_status = ? OR (admin_status IS NULL AND ? = 'PENDING')) ";
    $params[] = $filter;
    $params[] = $filter;
    $types .= "ss";
}

$sql .= "ORDER BY 
    CASE 
        WHEN admin_status = 'PENDING' OR admin_status IS NULL THEN 1
        WHEN admin_status = 'APPROVED' THEN 2
        WHEN admin_status = 'REJECTED' THEN 3
        ELSE 4
    END,
    date_time_of_event DESC";

$stmt = $conn->prepare($sql);

if ($filter !== 'ALL') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$proposals = [];
while ($row = $result->fetch_assoc()) {
    // Set default admin_status if null
    if ($row['admin_status'] === null) {
        $row['admin_status'] = 'PENDING';
    }
    $proposals[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'proposals' => $proposals,
    'filter' => $filter,
    'count' => count($proposals)
]);
?>