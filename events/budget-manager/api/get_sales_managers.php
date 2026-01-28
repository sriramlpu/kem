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

// Fetch sales managers - role_id = 11 for sales persons
$sql = "SELECT user_id, username, email 
        FROM users 
        WHERE role_id = 11 AND status = 'Active'
        ORDER BY username";

$result = $conn->query($sql);

$managers = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $managers[] = [
            'user_id' => $row['user_id'],
            'username' => $row['username'],
            'full_name' => $row['username'],
            'email' => $row['email']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'managers' => $managers,
        'count' => count($managers)
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to fetch sales managers: ' . $conn->error]);
}

$conn->close();
?>