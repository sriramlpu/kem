<?php
header('Content-Type: application/json');
require_once('../../functions.php'); // Ensure this defines $conn and exeSql/getCount functions
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

// Helper: sanitize regex input to allow only safe characters
function sanitize_regex($input) {
    return preg_replace('/[^a-zA-Z0-9\s\-]/', '', $input);
}

if ($action === 'list') {
    $draw = intval($_POST['draw'] ?? 1);
    $start = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 10);
    $where = "WHERE 1=1";
    
    // Regex-safe text search filter
    if (!empty($_POST['filter_search'])) {
        $search = sanitize_regex($_POST['filter_search']);
        if ($search !== '') {
            $where .= " AND role_name REGEXP '$search'";
        }
    }
    
    // Total records (before filtering)
    $totalRecords = getCount("roles");
    
    // Total records (after filtering)
    $totalFilteredRow = exeSql("SELECT COUNT(*) as total FROM roles $where");
    $totalFiltered = $totalFilteredRow[0]['total'];
    
    // Fetch filtered data with pagination
    $sqlData = exeSql("SELECT role_id, role_name, status FROM roles $where ORDER BY role_id DESC LIMIT $start, $length");
    
    $data = [];
    $sno = $start + 1;
    foreach ($sqlData as $row) {
        $row['sno'] = $sno++;
        $data[] = $row;
    }
    
    echo json_encode([
        "status" => "success",
        "draw" => $draw,
        "recordsTotal" => intval($totalRecords),
        "recordsFiltered" => intval($totalFiltered),
        "data" => $data
    ]);
    exit;
}

// CREATE role
if ($action === 'create') {
    $name = trim($_POST['name'] ?? '');
    
    if (!$name) {
        echo json_encode(['status'=>'error', 'message'=>'role name required']);
        exit;
    }

    $getCount = getCount("roles", "role_name = '$name'");
    if($getCount > 0){
        echo json_encode(['status'=>'error', 'message'=>'role already exists']);
        exit;
    }
    
    $stmt = excuteSql("INSERT INTO roles (role_name) VALUES ('$name')");
    
    if ($stmt) {
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error', 'message'=>'Insert failed']);
    }
    exit;
}

// EDIT role
if ($action === 'edit') {
    $id = intval($_POST['id']);
    $name = $_POST['name'];
    $status = $_POST['status'];
    
    $sql = excuteSql("UPDATE roles SET role_name='$name', status='$status' WHERE role_id='$id'");
    if ($sql) {
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error','message'=>'Update failed']);
    }
    exit;
}

// DEACTIVATE role (set status to 'Inactive')
if ($action === 'deactivate') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['status'=>'error', 'message'=>'ID required']);
        exit;
    }
    
    $sql = excuteSql("UPDATE roles SET status='Inactive' WHERE role_id='$id'");
    if ($sql) {
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error', 'message'=>'Deactivate failed']);
    }
    exit;
}

// FETCH single role
if ($action === 'getrole') {
    $id = intval($_POST['id']);
    $role = getValues('roles', "role_id = '$id'");
    if ($role) {
        echo json_encode(['status'=>'success','data'=>$role]);
    } else {
        echo json_encode(['status'=>'error','message'=>'role not found']);
    }
    exit;
}
?>