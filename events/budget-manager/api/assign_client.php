<?php
session_start();

// Ensure the user is logged in and has the "Budget Manager" role
if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if ($_SESSION['roleName'] !== 'Budget Manager') {
    echo json_encode(['success' => false, 'error' => 'Access Denied']);
    exit();
}

// Database connection
$servername = "localhost";
$username = "kmkglobal_web";
$password = "tI]rfPhdOo9zHdKw";
$dbname = "kmkglobal_web";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

// Get POST data for client assignment
$client_id = $_POST['client_id'] ?? null;
$sales_manager_id = $_POST['sales_manager_id'] ?? null;

// Validate input
if (!$client_id || !$sales_manager_id) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit();
}

// Check if the Sales Manager exists (role_id = 11 for Salesperson)
$sql_manager = "SELECT user_id, username FROM users WHERE user_id = ? AND role_id = 11";
$stmt_manager = $conn->prepare($sql_manager);
$stmt_manager->bind_param("i", $sales_manager_id);
$stmt_manager->execute();
$result_manager = $stmt_manager->get_result();

if ($result_manager->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid salesperson']);
    $stmt_manager->close();
    $conn->close();
    exit();
}

$manager = $result_manager->fetch_assoc();
$manager_name = $manager['username'];
$stmt_manager->close();

// Update the client with the assigned salesperson
$sql = "UPDATE clients SET assigned_to = ? WHERE client_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $sales_manager_id, $client_id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => "Client successfully assigned to $manager_name"
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to assign client'
    ]);
}

$stmt->close();
$conn->close();
?>
