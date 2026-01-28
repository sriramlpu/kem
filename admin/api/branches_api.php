<?php
header('Content-Type: application/json');
require_once('../../functions.php');
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

// Helper: sanitize regex input
function sanitize_regex($input) {
    return preg_replace('/[^a-zA-Z0-9\s\-]/', '', $input);
}

if ($action === 'list') {
    $draw   = intval($_POST['draw'] ?? 1);
    $start  = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 10);
    $where  = "WHERE 1=1";

    if (!empty($_POST['filter_search'])) {
        $search = sanitize_regex($_POST['filter_search']);
        if ($search !== '') {
            $where .= " AND (branch_code REGEXP '$search' 
                        OR branch_name REGEXP '$search' 
                        OR address REGEXP '$search' 
                        OR city REGEXP '$search' 
                        OR state REGEXP '$search' 
                        OR country REGEXP '$search' 
                        OR pincode REGEXP '$search')";
        }
    }

    $totalRecords = getCount("branches");
    $totalFilteredRow = exeSql("SELECT COUNT(*) as total FROM branches $where");
    $totalFiltered = $totalFilteredRow[0]['total'];

    $sqlData = exeSql("SELECT branch_id AS id, branch_code, branch_name, address, city, state, country, pincode 
                       FROM branches $where ORDER BY branch_id DESC LIMIT $start, $length");

    $data = [];
    $sno = $start + 1;
    foreach ($sqlData as $row) {
        $row['sno'] = $sno++;
        $data[] = $row;
    }

    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => intval($totalRecords),
        "recordsFiltered" => intval($totalFiltered),
        "data" => $data
    ]);
    exit;
}

// CREATE
if ($action === 'create') {
    $branch_code = trim($_POST['branch_code'] ?? '');
    $branch_name = trim($_POST['branch_name'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $city        = trim($_POST['city'] ?? '');
    $state       = trim($_POST['state'] ?? '');
    $country     = trim($_POST['country'] ?? '');
    $pincode     = trim($_POST['pincode'] ?? '');

    if (!$branch_code || !$branch_name) {
        echo json_encode(['status'=>'error','message'=>'Branch code and name are required']);
        exit;
    }

    $getCountCode = getCount("branches", "branch_code = '$branch_code'");
    if($getCountCode > 0){
        echo json_encode(['status'=>'error','message'=>'Branch code already exists']);
        exit;
    }

    $getCountName = getCount("branches", "branch_name = '$branch_name'");
    if($getCountName > 0){
        echo json_encode(['status'=>'error','message'=>'Branch name already exists']);
        exit;
    }

    $stmt = excuteSql("INSERT INTO branches (branch_code, branch_name, address, city, state, country, pincode) 
                       VALUES ('$branch_code', '$branch_name', '$address', '$city', '$state', '$country', '$pincode')");

    echo json_encode($stmt ? ['status'=>'success'] : ['status'=>'error','message'=>'Insert failed']);
    exit;
}

// EDIT
if ($action === 'edit') {
    $id          = intval($_POST['id']);
    $branch_code = $_POST['branch_code'];
    $branch_name = $_POST['branch_name'];
    $address     = $_POST['address'];
    $city        = $_POST['city'];
    $state       = $_POST['state'];
    $country     = $_POST['country'];
    $pincode     = $_POST['pincode'];

    $getCountCode = getCount("branches", "branch_code = '$branch_code' AND branch_id != $id");
    if($getCountCode > 0){
        echo json_encode(['status'=>'error','message'=>'Branch code already exists']);
        exit;
    }

    $getCountName = getCount("branches", "branch_name = '$branch_name' AND branch_id != $id");
    if($getCountName > 0){
        echo json_encode(['status'=>'error','message'=>'Branch name already exists']);
        exit;
    }

    $sql = excuteSql("UPDATE branches SET 
                        branch_code='$branch_code',
                        branch_name='$branch_name',
                        address='$address',
                        city='$city',
                        state='$state',
                        country='$country',
                        pincode='$pincode'
                      WHERE branch_id='$id'");

    echo json_encode($sql ? ['status'=>'success'] : ['status'=>'error','message'=>'Update failed']);
    exit;
}

// DELETE
if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['status'=>'error', 'message'=>'ID required']);
        exit;
    }

    $sql = excuteSql("DELETE FROM branches WHERE branch_id='$id'");
    echo json_encode($sql ? ['status'=>'success'] : ['status'=>'error','message'=>'Delete failed']);
    exit;
}

// GET single branch
if ($action === 'getBranch') {
    $id = intval($_POST['id']);
    $branch = getRowValues('branches', $id, 'branch_id');
    if ($branch) {
        $branch['id'] = $branch['branch_id'];
        echo json_encode(['status'=>'success','data'=>$branch]);
    } else {
        echo json_encode(['status'=>'error','message'=>'Branch not found']);
    }
    exit;
}

// ✅ Existing: FETCH all branches (kept for your current UI usage)
// Note: You wanted branch dropdown to show only branch_name. Keep as-is.
if ($action === 'getActiveBranches') {
    $branches = exeSql("SELECT branch_id, branch_name, address, city, state, pincode 
                        FROM branches ORDER BY branch_name");
    echo json_encode(['status'=>'success','data'=>$branches]);
    exit;
}

// ✅ NEW: alias for your PO page to load ALL addresses for Delivery/Billing selects
// Returns address fields (address, city, state, pincode) + branch_name for display
if ($action === 'getAllAddresses') {
    $rows = exeSql("SELECT branch_id, branch_name, address, city, state, pincode
                    FROM branches
                    ORDER BY branch_name");
    echo json_encode(['status' => 'success', 'data' => ($rows ?: [])]);
    exit;
}

echo json_encode(['status'=>'error','message'=>'Invalid action']);
