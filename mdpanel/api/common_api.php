<?php
header('Content-Type: application/json');
require_once('../../functions.php');
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

// GET branches for dropdown
if ($action === 'getBranches') {
    $branches = exeSql("SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
    echo json_encode(['status'=>'success','data'=>$branches]);
    exit;
}

echo json_encode(['status'=>'error', 'message'=>'Invalid action']);
?>