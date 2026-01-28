<?php
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

// Get POST data
$client_id = $_POST['client_id'] ?? null;
$admin_status = $_POST['admin_status'] ?? null;
$admin_notes = $_POST['admin_notes'] ?? '';

// Validate input
if (!$client_id || !$admin_status) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    $conn->close();
    exit();
}

$allowed_statuses = ['PENDING', 'APPROVED', 'REJECTED'];
if (!in_array($admin_status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    $conn->close();
    exit();
}

// Determine workflow stage based on admin status
$workflow_stage = '';
switch ($admin_status) {
    case 'APPROVED':
        $workflow_stage = 'ADMIN_APPROVED';
        break;
    case 'REJECTED':
        $workflow_stage = 'REJECTED';
        break;
    case 'PENDING':
        $workflow_stage = 'ADMIN_REVIEW';
        break;
}

// Use default value for approved by
$approved_by = 'Budget Manager';
$approved_at = date('Y-m-d H:i:s');

// Update the client record
$sql = "UPDATE clients SET 
    admin_status = ?,
    workflow_stage = ?,
    admin_notes = ?,
    admin_approved_by = ?,
    admin_approved_at = ?
WHERE client_id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database prepare error: ' . $conn->error]);
    $conn->close();
    exit();
}

$stmt->bind_param("sssssi", 
    $admin_status, 
    $workflow_stage, 
    $admin_notes, 
    $approved_by, 
    $approved_at, 
    $client_id
);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => "Proposal status updated to $admin_status successfully"
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No changes made or client not found'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update status: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>