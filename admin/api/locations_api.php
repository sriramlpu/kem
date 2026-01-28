<?php
header('Content-Type: application/json');
require_once('../../functions.php');
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
            $where .= " AND (zone_name REGEXP '$search' OR zone_code REGEXP '$search' OR location_name REGEXP '$search' OR location_code REGEXP '$search' OR location_address REGEXP '$search')";
        }
    }
    
    // Total records (before filtering)
    $totalRecords = getCount("locations");
    
    // Total records (after filtering)
    $totalFilteredRow = exeSql("SELECT COUNT(*) as total FROM locations $where");
    $totalFiltered = $totalFilteredRow[0]['total'];
    
    // Fetch filtered data with pagination
    $sqlData = exeSql("SELECT id, zone_name, zone_code, location_name, location_code, location_address FROM locations $where ORDER BY id DESC LIMIT $start, $length");
    
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
// CREATE location
if ($action === 'create') {
    $zone_name = trim($_POST['zone_name'] ?? '');
    $zone_code = trim($_POST['zone_code'] ?? '');
    $location_name = trim($_POST['location_name'] ?? '');
    $location_code = trim($_POST['location_code'] ?? '');
    $location_address = trim($_POST['location_address'] ?? '');
    
    if (!$zone_name || !$zone_code || !$location_name || !$location_code) {
        echo json_encode(['status'=>'error', 'message'=>'Zone name, zone code, location name and location code are required']);
        exit;
    }
    
    // Check for duplicate zone code
    $getCountZoneCode = getCount("locations", "zone_code = '$zone_code'");
    if($getCountZoneCode > 0){
        echo json_encode(['status'=>'error', 'message'=>'Zone code already exists']);
        exit;
    }
    
    // Check for duplicate location code
    $getCountLocationCode = getCount("locations", "location_code = '$location_code'");
    if($getCountLocationCode > 0){
        echo json_encode(['status'=>'error', 'message'=>'Location code already exists']);
        exit;
    }
    
    $stmt = excuteSql("INSERT INTO locations (zone_name, zone_code, location_name, location_code, location_address) VALUES ('$zone_name', '$zone_code', '$location_name', '$location_code', '$location_address')");
    
    if ($stmt) {
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error', 'message'=>'Insert failed']);
    }
    exit;
}
// EDIT location
if ($action === 'edit') {
    $id = intval($_POST['id']);
    $zone_name = $_POST['zone_name'];
    $zone_code = $_POST['zone_code'];
    $location_name = $_POST['location_name'];
    $location_code = $_POST['location_code'];
    $location_address = $_POST['location_address'];
    
    // Check for duplicate zone code (excluding current record)
    $getCountZoneCode = getCount("locations", "zone_code = '$zone_code' AND id != $id");
    if($getCountZoneCode > 0){
        echo json_encode(['status'=>'error', 'message'=>'Zone code already exists']);
        exit;
    }
    
    // Check for duplicate location code (excluding current record)
    $getCountLocationCode = getCount("locations", "location_code = '$location_code' AND id != $id");
    if($getCountLocationCode > 0){
        echo json_encode(['status'=>'error', 'message'=>'Location code already exists']);
        exit;
    }
    
    $sql = excuteSql("UPDATE locations SET zone_name='$zone_name', zone_code='$zone_code', location_name='$location_name', location_code='$location_code', location_address='$location_address' WHERE id='$id'");
    if ($sql) {
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error','message'=>'Update failed']);
    }
    exit;
}
// DELETE location
if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['status'=>'error', 'message'=>'ID required']);
        exit;
    }
    
    $sql = excuteSql("DELETE FROM locations WHERE id='$id'");
    if ($sql) {
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error', 'message'=>'Delete failed']);
    }
    exit;
}
// FETCH single location
if ($action === 'getLocation') {
    $id = intval($_POST['id']);
    $location = getRowValues('locations', $id, 'id');
    if ($location) {
        echo json_encode(['status'=>'success','data'=>$location]);
    } else {
        echo json_encode(['status'=>'error','message'=>'Location not found']);
    }
    exit;
}
// FETCH all active locations for dropdown
if ($action === 'getActiveLocations') {
    $locations = exeSql("SELECT id, location_name FROM locations ORDER BY location_name");
    echo json_encode(['status'=>'success','data'=>$locations]);
    exit;
}
?>