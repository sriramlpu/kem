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

// Fetch all clients
$sql = "SELECT 
    client_id, client_name, contact_no, lead_status, 
    date_time_of_event, event_type, assigned_to
FROM clients 
ORDER BY client_id DESC";

$result = $conn->query($sql);

$clients = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $clients[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'clients' => $clients,
        'count' => count($clients)
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to fetch clients: ' . $conn->error]);
}

$conn->close();
?>