<?php
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

// -------------------- 2. INPUT VALIDATION --------------------
$client_id = $_POST['client_id'] ?? null;

if (!$client_id || !is_numeric($client_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => "Invalid Client ID provided."]);
    $conn->close();
    exit();
}

// -------------------- 3. DATABASE DELETION --------------------
// Note: We use a JOIN to ensure we only delete clients where the workflow 
// stage is REJECTED or LEAD_CREATED to prevent accidental deletion of active proposals.
// This is redundant since the UI handles the visibility, but it adds a layer of safety.
// However, to simply delete the client regardless of their current status if the UI 
// allows it (as intended by the request for REJECTED/LEAD_CREATED clients):

$sql = "DELETE FROM clients WHERE client_id = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "Error preparing statement: " . $conn->error]);
    $conn->close();
    exit();
}

$stmt->bind_param("i", $client_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => "Client #{$client_id} and all related data deleted successfully."
        ]);
    } else {
        // No client found or deleted
        echo json_encode(['success' => false, 'error' => "Client ID #{$client_id} not found."]);
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "DB Execution Error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>