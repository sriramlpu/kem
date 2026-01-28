<?php
header('Content-Type: application/json');
require_once('../../functions.php');

$action = $_GET['action'] ?? '';

if ($action == 'list') {
    $sql = "SELECT * FROM services WHERE status = 'Active' ORDER BY service_name";
    $result = exeSql($sql);
    
    echo json_encode([
        'status' => 'success',
        'data' => $result
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
?>