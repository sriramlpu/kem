<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['sales_person'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Please login.'
    ]);
    exit();
}

// Database connection
$servername = "localhost";
$username = "kmkglobal_web";
$password = "tI]rfPhdOo9zHdKw";
$dbname = "kmkglobal_web";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit();
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit();
}

// Get client_id from POST data
$client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;

if ($client_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid client ID'
    ]);
    exit();
}

// Verify that the client exists and is admin approved
$sql_check = "SELECT client_id, client_name, workflow_stage 
              FROM clients 
              WHERE client_id = ? AND workflow_stage = 'ADMIN_APPROVED'";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("i", $client_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Client not found or not approved by admin'
    ]);
    $stmt_check->close();
    $conn->close();
    exit();
}

$client_data = $result_check->fetch_assoc();
$stmt_check->close();

// Begin transaction
$conn->begin_transaction();

try {
    // Update client workflow stage
    $sql_update = "UPDATE clients 
                   SET workflow_stage = 'EXECUTIVE_REVIEW',
                       executive_status = 'PENDING'
                   WHERE client_id = ?";
    
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("i", $client_id);
    
    if (!$stmt_update->execute()) {
        throw new Exception('Failed to update client workflow stage');
    }
    $stmt_update->close();
    
    // Log activity
    $sales_person = $_SESSION['sales_person'];
    $sql_log = "INSERT INTO activity_log 
                (client_id, action_by, action_type, action_description, new_status) 
                VALUES (?, ?, 'SENT_TO_EXECUTIVE', 'Proposal sent to Executive Manager for final approval', 'EXECUTIVE_REVIEW')";
    
    $stmt_log = $conn->prepare($sql_log);
    $stmt_log->bind_param("is", $client_id, $sales_person);
    
    if (!$stmt_log->execute()) {
        throw new Exception('Failed to log activity');
    }
    $stmt_log->close();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Proposal successfully sent to Executive Manager for approval',
        'client_name' => $client_data['client_name']
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>