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
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "Connection failed: " . $conn->connect_error]);
    exit();
}

// -------------------- INPUT VALIDATION --------------------
$client_id = $_POST['client_id'] ?? null;
$admin_status = $_POST['admin_status'] ?? null;
$admin_notes = $_POST['admin_notes'] ?? '';

if (!$client_id || !is_numeric($client_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => "Invalid Client ID provided."]);
    $conn->close();
    exit();
}

$allowed_statuses = ['PENDING', 'APPROVED', 'REJECTED'];
if (!$admin_status || !in_array($admin_status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => "Invalid status. Must be PENDING, APPROVED, or REJECTED."]);
    $conn->close();
    exit();
}

// -------------------- UPDATE STATUS WITH WORKFLOW --------------------
// Determine workflow_stage based on admin_status
$workflow_stage = 'ADMIN_REVIEW'; // Default
if ($admin_status === 'APPROVED') {
    $workflow_stage = 'ADMIN_APPROVED';
} elseif ($admin_status === 'REJECTED') {
    $workflow_stage = 'REJECTED';
}

// Get admin user (from session or default)
$admin_user = $_SESSION['username'] ?? $_SESSION['admin_user'] ?? 'Admin';

$sql = "UPDATE clients SET 
    admin_status = ?, 
    workflow_stage = ?,
    admin_notes = ?,
    admin_approved_by = ?,
    admin_approved_at = NOW()
    WHERE client_id = ?";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "Error preparing statement: " . $conn->error]);
    $conn->close();
    exit();
}

$stmt->bind_param("ssssi", $admin_status, $workflow_stage, $admin_notes, $admin_user, $client_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        // Log activity
        $action_type = ($admin_status === 'APPROVED') ? 'ADMIN_APPROVED' : (($admin_status === 'REJECTED') ? 'ADMIN_REJECTED' : 'ADMIN_PENDING');
        $action_desc = ($admin_status === 'APPROVED') ? 'Proposal approved by admin' : (($admin_status === 'REJECTED') ? 'Proposal rejected by admin' : 'Proposal moved to pending');
        
        $sql_log = "INSERT INTO activity_log (client_id, action_by, action_type, action_description, new_status) 
                    VALUES (?, ?, ?, ?, ?)";
        $stmt_log = $conn->prepare($sql_log);
        $stmt_log->bind_param("issss", $client_id, $admin_user, $action_type, $action_desc, $workflow_stage);
        $stmt_log->execute();
        $stmt_log->close();
        
        $action_text = ($admin_status === 'APPROVED') ? 'approved' : (($admin_status === 'REJECTED') ? 'rejected' : 'moved to pending');
        echo json_encode([
            'success' => true, 
            'message' => "Proposal for Client #{$client_id} has been {$action_text} successfully!",
            'workflow_stage' => $workflow_stage
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => "No changes were made. Client ID might not exist or status is already set."
        ]);
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "DB Execution Error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>