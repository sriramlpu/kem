<?php
header('Content-Type: application/json');
require_once('../../functions.php'); // Ensure this defines $conn and exeSql/getCount functions
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

// Helper: sanitize regex input to allow only safe characters
function sanitize_regex($input) {
    return preg_replace('/[^a-zA-Z0-9\s\-]/', '', $input);
}

if ($action === 'list') {
    $draw   = intval($_POST['draw'] ?? 1);
    $start  = max(0, intval($_POST['start'] ?? 0));
    $length = intval($_POST['length'] ?? 10);
    $where  = "WHERE 1=1";

    // NEW: read filters from the UI
    $filter_category_id = intval($_POST['filter_category_id'] ?? 0);
    $filter_status      = trim($_POST['filter_status'] ?? '');

    // Apply filters
    if ($filter_category_id > 0) {
        $where .= " AND sc.category_id = $filter_category_id";
    }
    if ($filter_status !== '') {
        $where .= " AND sc.status = '" . addslashes($filter_status) . "'";
    }

    // Regex-safe text search filter
    if (!empty($_POST['filter_search'])) {
        $search = sanitize_regex($_POST['filter_search']);
        if ($search !== '') {
            $where .= " AND (sc.subcategory_name REGEXP '$search'
                          OR sc.subcategory_code REGEXP '$search'
                          OR c.category_name     REGEXP '$search')";
        }
    }

    // Total records (before filtering)
    $totalRecords = getCount("subcategories");

    // Total records (after filtering) — FIXED JOIN
    $totalFilteredRow = exeSql("
        SELECT COUNT(*) AS total
        FROM subcategories sc
        LEFT JOIN categories c ON c.category_id = sc.category_id
        $where
    ");
    $totalFiltered = intval($totalFilteredRow[0]['total'] ?? 0);

    // Fetch filtered data with pagination (same JOIN as above)
    $limitSql = ($length > 0) ? " LIMIT $start, $length" : "";
    $sqlData = exeSql("
        SELECT
            sc.subcategory_id,
            sc.subcategory_name,
            sc.subcategory_code,
            sc.status,
            c.category_name
        FROM subcategories sc
        LEFT JOIN categories c ON c.category_id = sc.category_id
        $where
        ORDER BY sc.subcategory_id DESC
        $limitSql
    ");

    // Add S.No
    $data = [];
    $sno = $start + 1;
    foreach ($sqlData as $row) {
        $row['sno'] = $sno++;
        $data[] = $row;
    }

    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => intval($totalRecords),
        "recordsFiltered" => $totalFiltered,
        "data" => $data
    ]);
    exit;
}

// CREATE sub-category
if ($action === 'create') {
    $category_id = intval($_POST['category_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $sub_category_code = trim($_POST['sub_category_code'] ?? '');
    
    if (!$category_id) {
        echo json_encode(['status'=>'error', 'message'=>'Category required']);
        exit;
    }
    
    if (!$name) {
        echo json_encode(['status'=>'error', 'message'=>'Sub-category name required']);
        exit;
    }
    
    if (!$sub_category_code) {
        echo json_encode(['status'=>'error', 'message'=>'Sub-category code required']);
        exit;
    }
    
    // Check if sub-category code already exists
    $existing = exeSql("SELECT subcategory_id FROM subcategories WHERE subcategory_code = '$sub_category_code'");
    if (!empty($existing)) {
        echo json_encode(['status'=>'error', 'message'=>'Sub-category code already exists']);
        exit;
    }

     $catExisting = exeSql("SELECT subcategory_id FROM subcategories WHERE subcategory_code = '$sub_category_code' AND category_id = '$category_id' AND subcategory_name = '$name'");
    if (!empty($catExisting)) {
        echo json_encode(['status'=>'error', 'message'=>'Sub-category already exists']);
        exit;
    }
    
    $stmt = excuteSql("INSERT INTO subcategories (category_id, subcategory_name, subcategory_code) VALUES ('$category_id', '$name', '$sub_category_code')");
    
    if ($stmt) {
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error', 'message'=>'Insert failed']);
    }
    exit;
}

// EDIT sub-category
if ($action === 'edit') {
    $id = intval($_POST['id']);
    $category_id = intval($_POST['category_id']);
    $name = $_POST['name'];
    $sub_category_code = $_POST['sub_category_code'];
    $status = $_POST['status'];
    
    $sql = excuteSql("UPDATE subcategories SET category_id='$category_id', subcategory_name='$name', subcategory_code='$sub_category_code', status='$status' WHERE subcategory_id='$id'");
    if ($sql) {
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error','message'=>'Update failed']);
    }
    exit;
}

// DEACTIVATE sub-category (set status to 'Inactive')
if ($action === 'deactivate') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['status'=>'error', 'message'=>'ID required']);
        exit;
    }
    // echo "UPDATE sub_categories SET status='Inactive' WHERE id='$id'";
    $sql = excuteSql("UPDATE subcategories SET status='Inactive' WHERE subcategory_id='$id'");
    // echo $sql;
    if ($sql) {
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error', 'message'=>'Deactivate failed']);
    }
    exit;
}

// FETCH single sub-category
if ($action === 'getSubCategory') {
    $id = intval($_POST['id']);
    $sub_category = getValues('subcategories', "subcategory_id = '$id'");
    if ($sub_category) {
        echo json_encode(['status'=>'success','data'=>$sub_category]);
    } else {
        echo json_encode(['status'=>'error','message'=>'Sub-category not found']);
    }
    exit;
}

// FETCH all categories for dropdown
if ($action === 'getCategories') {
    $categories = exeSql("SELECT category_id, category_name FROM categories WHERE status = 'Active' ORDER BY category_name ASC");
    echo json_encode(['status'=>'success','data'=>$categories]);
    exit;
}

?>