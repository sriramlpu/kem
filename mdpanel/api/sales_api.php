<?php
header('Content-Type: application/json');
require_once('../../functions.php');
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

// Helper: sanitize regex input to allow only safe characters
function sanitize_regex($input) {
    return preg_replace('/[^a-zA-Z0-9\s\-]/', '', $input);
}

// LIST sales
if ($action === 'getSales') {
    $draw = intval($_POST['draw'] ?? 1);
    $start = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 10);
    $where = "WHERE 1=1";
    
    // Apply branch filter
    if (!empty($_POST['branch_id'])) {
        $branch_id = intval($_POST['branch_id']);
        $where .= " AND s.branch_id = $branch_id";
    }
    
    // Apply date filters
    if (!empty($_POST['start_date'])) {
        $start_date = $_POST['start_date'];
        $where .= " AND s.sale_date >= '$start_date'";
    }
    
    if (!empty($_POST['end_date'])) {
        $end_date = $_POST['end_date'];
        $where .= " AND s.sale_date <= '$end_date'";
    }
    
    // Apply status filter
    if (!empty($_POST['status'])) {
        $status = sanitize_regex($_POST['status']);
        $where .= " AND s.status = '$status'";
    }
    
    // Regex-safe text search filter
    if (!empty($_POST['filter_search'])) {
        $search = sanitize_regex($_POST['filter_search']);
        if ($search !== '') {
            $where .= " AND (s.invoice_no REGEXP '$search' OR c.customer_name REGEXP '$search' OR b.branch_name REGEXP '$search')";
        }
    }
    
    // Total records (before filtering)
    $totalRecords = getCount("sales s");
    
    // Total records (after filtering)
    $totalFilteredRow = exeSql("SELECT COUNT(*) as total FROM sales s 
                               LEFT JOIN customers c ON s.customer_id = c.customer_id 
                               LEFT JOIN branches b ON s.branch_id = b.branch_id $where");
    $totalFiltered = $totalFilteredRow[0]['total'];
    
    // Fetch filtered data with pagination
    $sqlData = exeSql("SELECT s.sale_id, s.sale_date AS date, s.invoice_no, c.customer_name, 
                              b.branch_name, s.total_amount AS amount, s.status
                       FROM sales s
                       LEFT JOIN customers c ON s.customer_id = c.customer_id
                       LEFT JOIN branches b ON s.branch_id = b.branch_id
                       $where 
                       ORDER BY s.sale_id DESC 
                       LIMIT $start, $length");
    
    $data = [];
    $sno = $start + 1;
    foreach ($sqlData as $row) {
        $row['sno'] = $sno++;
        $row['sale_id'] = $row['sale_id'];
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

// GET sale details
if ($action === 'getSaleDetails') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['status'=>'error', 'message'=>'ID required']);
        exit;
    }
    
    // Get sale details
    $sale = exeSql("SELECT s.sale_id, s.sale_date AS date, s.invoice_no, c.customer_name, 
                           b.branch_name, s.total_amount AS amount, s.status
                    FROM sales s
                    LEFT JOIN customers c ON s.customer_id = c.customer_id
                    LEFT JOIN branches b ON s.branch_id = b.branch_id
                    WHERE s.sale_id = $id");
    
    if (!$sale) {
        echo json_encode(['status'=>'error','message'=>'Sale not found']);
        exit;
    }
    
    // Get sale items
    $items = exeSql("SELECT si.item_id, i.item_name, si.quantity, si.price, si.total_amount AS total
                    FROM sale_items si
                    LEFT JOIN items i ON si.item_id = i.item_id
                    WHERE si.sale_id = $id");
    
    echo json_encode([
        'status'=>'success',
        'data' => [
            'sale_id' => $sale[0]['sale_id'],
            'date' => $sale[0]['date'],
            'invoice_no' => $sale[0]['invoice_no'],
            'customer_name' => $sale[0]['customer_name'],
            'branch_name' => $sale[0]['branch_name'],
            'amount' => $sale[0]['amount'],
            'status' => $sale[0]['status'],
            'items' => $items
        ]
    ]);
    exit;
}

// GET branches for dropdown
if ($action === 'getBranches') {
    $branches = exeSql("SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
    echo json_encode(['status'=>'success','data'=>$branches]);
    exit;
}

echo json_encode(['status'=>'error', 'message'=>'Invalid action']);
?>