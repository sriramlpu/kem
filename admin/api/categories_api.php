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
            $where .= " AND (category_name REGEXP '$search' OR category_code REGEXP '$search')";
        }
    }
    
    // Total records (before filtering)
    $totalRecords = getCount("categories");
    
    // Total records (after filtering)
    $totalFilteredRow = exeSql("SELECT COUNT(*) as total FROM categories $where");
    $totalFiltered = $totalFilteredRow[0]['total'];
    
    // Fetch filtered data with pagination
    $sqlData = exeSql("SELECT category_id, category_name, category_code, status FROM categories $where ORDER BY category_id DESC LIMIT $start, $length");
    
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

// CREATE category
if ($action === 'create') {
    $name = trim($_POST['name'] ?? '');
    $category_code = trim($_POST['category_code'] ?? '');
    
    if (!$name) {
        echo json_encode(['status'=>'error', 'message'=>'Category name required']);
        exit;
    }
    
    if (!$category_code) {
        echo json_encode(['status'=>'error', 'message'=>'Category code required']);
        exit;
    }

    
    
    // Check if category code already exists
    $existing = exeSql("SELECT category_id FROM categories WHERE category_code = '$category_code'");
    if (!empty($existing)) {
        echo json_encode(['status'=>'error', 'message'=>'Category code already exists']);
        exit;
    }

    $catExisting = exeSql("SELECT category_name FROM categories WHERE category_name = '$name'");
    if (!empty($catExisting)) {
        echo json_encode(['status'=>'error', 'message'=>'Category already exists']);
        exit;
    }
    
    $stmt = excuteSql("INSERT INTO categories (category_name, category_code) VALUES ('$name', '$category_code')");
    
    if ($stmt) {
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error', 'message'=>'Insert failed']);
    }
    exit;
}

// EDIT category
if ($action === 'edit') {
    $id = intval($_POST['id']);
    $name = $_POST['name'];
    $category_code = $_POST['category_code'];
    $status = $_POST['status'];
    
    // Check if category code already exists (excluding current record)
    $existing = exeSql("SELECT category_id FROM categories WHERE category_code = '$category_code' AND category_id != $id");
    if (!empty($existing)) {
        echo json_encode(['status'=>'error', 'message'=>'Category code already exists']);
        exit;
    }
    
    $sql = excuteSql("UPDATE categories SET category_name='$name', category_code='$category_code', status='$status' WHERE category_id='$id'");
    if ($sql) {
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error','message'=>'Update failed']);
    }
    exit;
}

// DEACTIVATE category (set status to 'Inactive')
if ($action === 'deactivate') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['status'=>'error', 'message'=>'ID required']);
        exit;
    }
    
    $sql = excuteSql("UPDATE categories SET status='Inactive' WHERE category_id='$id'");
    if ($sql) {
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error', 'message'=>'Deactivate failed']);
    }
    exit;
}

// FETCH single category
if ($action === 'getCategory') {
    $id = intval($_POST['id']);
    $category = getValues('categories', "category_id = '$id'");
    if ($category) {
        echo json_encode(['status'=>'success','data'=>$category]);
    } else {
        echo json_encode(['status'=>'error','message'=>'Category not found']);
    }
    exit;
}
?>