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

// Get statistics
$sql_stats = "SELECT 
    COUNT(CASE WHEN admin_status = 'PENDING' AND workflow_stage = 'ADMIN_REVIEW' THEN 1 END) as pending_count,
    COUNT(CASE WHEN admin_status = 'APPROVED' AND workflow_stage IN ('ADMIN_APPROVED', 'EXECUTIVE_REVIEW', 'EXECUTIVE_APPROVED') THEN 1 END) as approved_count,
    COUNT(CASE WHEN admin_status = 'REJECTED' AND workflow_stage = 'REJECTED' THEN 1 END) as rejected_count,
    COUNT(CASE WHEN workflow_stage NOT IN ('LEAD_CREATED', 'SALES_DRAFT') THEN 1 END) as total_count
FROM clients";

$result = $conn->query($sql_stats);

if ($result) {
    $stats = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'stats' => [
            'pending_count' => (int)$stats['pending_count'],
            'approved_count' => (int)$stats['approved_count'],
            'rejected_count' => (int)$stats['rejected_count'],
            'total_count' => (int)$stats['total_count']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to fetch statistics']);
}

$conn->close();
?>