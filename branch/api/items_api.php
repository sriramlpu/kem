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
            $where .= " AND (i.item_name REGEXP '$search' OR i.item_code REGEXP '$search' OR c.category_name REGEXP '$search' OR sc.subcategory_name REGEXP '$search')";
        }
    }
    
    // Total records (before filtering)
    $totalRecords = getCount("items");
    
    // Total records (after filtering)
    $totalFilteredRow = exeSql("
        SELECT COUNT(*) as total 
        FROM items i 
        LEFT JOIN categories c ON i.category_id = c.category_id 
        LEFT JOIN subcategories sc ON i.subcategory_id = sc.subcategory_id 
        $where
    ");
    $totalFiltered = $totalFilteredRow[0]['total'];
    
    // Fetch filtered data with pagination
    $sqlData = exeSql("
        SELECT i.item_id, i.item_name, i.item_code, i.status, c.category_name as category_name, sc.subcategory_name as subcategory_name 
        FROM items i 
        LEFT JOIN categories c ON i.category_id = c.category_id 
        LEFT JOIN subcategories sc ON i.subcategory_id = sc.subcategory_id 
        $where 
        ORDER BY i.item_id DESC 
        LIMIT $start, $length
    ");
    
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
// CREATE item
if ($action === 'create') {
    $category_id = intval($_POST['category_id'] ?? 0);
    $sub_category_id = intval($_POST['sub_category_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $item_code = trim($_POST['item_code'] ?? '');
    
    if (!$category_id) {
        echo json_encode(['status'=>'error', 'message'=>'Category required']);
        exit;
    }
    
    if (!$sub_category_id) {
        echo json_encode(['status'=>'error', 'message'=>'Sub-category required']);
        exit;
    }
    
    if (!$name) {
        echo json_encode(['status'=>'error', 'message'=>'Item name required']);
        exit;
    }
    
    if (!$item_code) {
        echo json_encode(['status'=>'error', 'message'=>'Item code required']);
        exit;
    }
    
    // Check if item code already exists
    $existing = exeSql("SELECT item_id FROM items WHERE item_code = '$item_code'");
    if (!empty($existing)) {
        echo json_encode(['status'=>'error', 'message'=>'Item code already exists']);
        exit;
    }
    
    $catExisting = exeSql("SELECT category_id FROM items WHERE category_id = '$category_id' AND subcategory_id = '$sub_category_id' AND item_name = '$name' AND item_code = '$item_code'");
    if (!empty($catExisting)) {
        echo json_encode(['status'=>'error', 'message'=>'Item already exists']);
        exit;
    }
    
    $stmt = excuteSql("INSERT INTO items (category_id, subcategory_id, item_name, item_code) VALUES ('$category_id', '$sub_category_id', '$name', '$item_code')");
    
    if ($stmt) {
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error', 'message'=>'Insert failed']);
    }
    exit;
}
// EDIT item
if ($action === 'edit') {
    $id = intval($_POST['id']);
    $category_id = intval($_POST['category_id']);
    $sub_category_id = intval($_POST['sub_category_id']);
    $name = $_POST['name'];
    $item_code = $_POST['item_code'];
    $status = $_POST['status'];
    
    // Check if item code already exists (excluding current record)
    $existing = exeSql("SELECT item_id FROM items WHERE item_code = '$item_code' AND item_id != $id");
    if (!empty($existing)) {
        echo json_encode(['status'=>'error', 'message'=>'Item code already exists']);
        exit;
    }
    
    $sql = excuteSql("UPDATE items SET category_id='$category_id', subcategory_id='$sub_category_id', item_name='$name', item_code='$item_code', status='$status' WHERE item_id='$id'");
    if ($sql) {
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error','message'=>'Update failed']);
    }
    exit;
}
// DEACTIVATE item (set status to 'Inactive')
if ($action === 'deactivate') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['status'=>'error', 'message'=>'ID required']);
        exit;
    }
    
    $sql = excuteSql("UPDATE items SET status='Inactive' WHERE item_id='$id'");
    if ($sql) {
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error', 'message'=>'Deactivate failed']);
    }
    exit;
}
// FETCH single item
if ($action === 'getItem') {
    $id = intval($_POST['id']);
    $item = getValues('items', "item_id = '$id'");
    if ($item) {
        echo json_encode(['status'=>'success','data'=>$item]);
    } else {
        echo json_encode(['status'=>'error','message'=>'Item not found']);
    }
    exit;
}
// FETCH all categories for dropdown
if ($action === 'getCategories') {
    $categories = exeSql("SELECT category_id, category_name FROM categories WHERE status = 'Active' ORDER BY category_name ASC");
    echo json_encode(['status'=>'success','data'=>$categories]);
    exit;
}
// FETCH sub-categories based on category_id
if ($action === 'getSubCategories') {
    $category_id = intval($_POST['category_id'] ?? 0);
    if (!$category_id) {
        echo json_encode(['status'=>'error', 'message'=>'Category ID required']);
        exit;
    }
    
    $sub_categories = exeSql("SELECT subcategory_id, subcategory_name FROM subcategories WHERE category_id = $category_id AND status = 'Active' ORDER BY subcategory_name ASC");
    echo json_encode(['status'=>'success','data'=>$sub_categories]);
    exit;
}

if ($action === 'getItemsBySubCategory') {
    $subcategory_id = intval($_POST['subcategory_id'] ?? 0);
    if (!$subcategory_id) {
        echo json_encode(['status'=>'error', 'message'=>'Subcategory ID required']);
        exit;
    }
    
    $items = getSubject("items", "subcategory_id = '$subcategory_id'");
    echo json_encode(['status'=>'success','data'=>$items]);
    exit;
}
// FETCH all active items for dropdown
if ($action === 'getActiveItems') {
    $items = exeSql("SELECT item_id, item_name FROM items WHERE status = 'Active' ORDER BY item_name ASC");
    echo json_encode(['status'=>'success','data'=>$items]);
    exit;
}
// FETCH items by service
if ($action === 'getItemsByService') {
    $service_id = intval($_POST['service_id'] ?? 0);
    if (!$service_id) {
        echo json_encode(['status'=>'error', 'message'=>'Service ID required']);
        exit;
    }
    
    $items = exeSql("SELECT i.item_id, i.item_name 
                     FROM items i 
                     JOIN service_items si ON i.item_id = si.item_id 
                     WHERE si.service_id = $service_id AND i.status = 'Active' 
                     ORDER BY i.item_name ASC");
    echo json_encode(['status'=>'success','data'=>$items]);
    exit;
}
?>