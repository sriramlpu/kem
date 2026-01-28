<?php
header('Content-Type: application/json');
require_once('../../functions.php');
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

function sanitize_regex($input) {
    return preg_replace('/[^a-zA-Z0-9\s\-]/', '', $input);
}
if ($action === 'get') {
    $grn_id = intval($_POST['grn_id']);
    
    if (!$grn_id) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid GRN ID']);
        exit;
    }
    
    $sql = "SELECT gr.*, v.vendor_name, po.order_number as po_number 
            FROM goods_receipts gr 
            LEFT JOIN vendors v ON gr.vendor_id = v.vendor_id 
            LEFT JOIN purchase_orders po ON gr.po_id = po.po_id 
            WHERE gr.grn_id = $grn_id";
    
    $result = exeSql($sql);
    
    if (!$result) {
        echo json_encode(['status' => 'error', 'message' => 'GRN not found']);
        exit;
    }
    
    echo json_encode(['status' => 'success', 'data' => $result[0]]);
    exit;
}


// Simple list for dropdowns (non-DataTables)
if ($action === 'simpleList') {
    $where = "WHERE 1=1";
    if (!empty($_REQUEST['search'])) {
        $search = sanitize_regex($_REQUEST['search']);
        $where .= " AND (vendor_name REGEXP '$search' OR email REGEXP '$search' OR phone REGEXP '$search')";
    }
    $sqlData = exeSql("SELECT vendor_id, vendor_name FROM vendors $where ORDER BY vendor_name ASC");
    $data = [];
    foreach ($sqlData as $row) {
        $data[] = $row;
    }
    echo json_encode([
        "status" => "success",
        "data" => $data
    ]);
    exit;
}

// LIST vendors for DataTables
if ($action === 'list') {
    $draw = intval($_POST['draw'] ?? 1);
    $start = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 10);
    $where = "WHERE 1=1";
    if (!empty($_POST['filter_search'])) {
        $search = sanitize_regex($_POST['filter_search']);
        $where .= " AND (vendor_name REGEXP '$search' OR email REGEXP '$search' OR phone REGEXP '$search')";
    }
    $totalRecords = getCount("vendors");
    $totalFilteredRow = exeSql("SELECT COUNT(*) as total FROM vendors $where");
    $totalFiltered = $totalFilteredRow[0]['total'];
    $sqlData = exeSql("SELECT * FROM vendors $where ORDER BY vendor_id DESC LIMIT $start, $length");
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

// CREATE vendor
if ($action === 'create') {
    $vendor_name = trim($_POST['vendor_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gstin = trim($_POST['gstin'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $branch = trim($_POST['branch'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $ifsc_code = trim($_POST['ifsc'] ?? '');
    
    if (!$vendor_name) { 
        echo json_encode(['status'=>'error','message'=>'Vendor name required']); 
        exit; 
    }
    
    // Duplicate check
    $existing = exeSql("SELECT vendor_id FROM vendors WHERE vendor_name='$vendor_name'");
    if (!empty($existing)) {
        echo json_encode(['status'=>'error','message'=>'Vendor name already exists']); 
        exit;
    }

    $stmt = excuteSql("INSERT INTO vendors (vendor_name,email,phone,gstin,address,city,state,country,pincode,branch,account_number,ifsc) VALUES ('$vendor_name','$email','$phone','$gstin','$address','$city','$state','$country','$pincode','$branch','$account_number','$ifsc_code')");
    echo json_encode($stmt ? ['status'=>'success'] : ['status'=>'error','message'=>'Insert failed']);
    exit;
}

// EDIT vendor
if ($action === 'edit') {
    $id = intval($_POST['vendor_id']);
    $vendor_name = trim($_POST['vendor_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gstin = trim($_POST['gstin'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $status = $_POST['status'] ?? 'Active';
    $branch = trim($_POST['branch'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $ifsc = trim($_POST['ifsc'] ?? '');

    // Duplicate check
    $existing = exeSql("SELECT vendor_id FROM vendors WHERE vendor_name='$vendor_name' AND vendor_id != $id");
    if (!empty($existing)) { 
        
        echo json_encode(['status'=>'error','message'=>'Vendor name already exists']); 
        exit; 
    }

    $sql = excuteSql("UPDATE vendors SET vendor_name='$vendor_name',email='$email',phone='$phone',gstin='$gstin',address='$address',city='$city',state='$state',country='$country',pincode='$pincode',status='$status',branch='$branch',account_number='$account_number',ifsc='$ifsc' WHERE vendor_id='$id'");
    echo json_encode($sql ? ['status'=>'success'] : ['status'=>'error','message'=>'Update failed']);
    exit;
}

// DEACTIVATE vendor
if ($action === 'deactivate') {
    $id = intval($_POST['vendor_id'] ?? 0);
    if (!$id) { 
        echo json_encode(['status'=>'error','message'=>'ID required']); 
        exit; 
    }
    
    $sql = excuteSql("UPDATE vendors SET status='Inactive' WHERE vendor_id='$id'");
    echo json_encode($sql ? ['status'=>'success'] : ['status'=>'error','message'=>'Deactivate failed']);
    exit;
}

// FETCH single vendor
if ($action === 'getVendor') {
    $id = intval($_POST['vendor_id']);
    $vendor = getValues('vendors', "vendor_id='$id'");
    echo json_encode($vendor ? ['status'=>'success','data'=>$vendor] : ['status'=>'error','message'=>'Vendor not found']);
    exit;
}
?>