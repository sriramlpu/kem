<?php
header('Content-Type: application/json');
require_once('../../functions.php');

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

// Add stock (e.g., goods received via GRN)
if ($action === 'create') {
    $grn_id = intval($_POST['grn_id'] ?? 0);
    $item_id = intval($_POST['item_id'] ?? 0);
    $branch_id = intval($_POST['branch_id'] ?? 0);
    $quantity_in = floatval($_POST['quantity_in'] ?? 0);
    $quantity_out = 0;  // For GRN, it's incoming stock, so no quantity out.
    
    if (!$grn_id || !$item_id || !$branch_id || $quantity_in <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
        exit;
    }
    
    // Insert stock movement into stock ledger
    $stmt = excuteSql("INSERT INTO stock_ledger (grn_id, branch_id, item_id, ref_type, ref_id, qty_in, qty_out, moved_at) 
                       VALUES ($grn_id, $branch_id, $item_id, 'GRN', $grn_id, $quantity_in, $quantity_out, NOW())");

    if ($stmt) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Stock addition failed']);
    }
    exit;
}

// List stock movements
if ($action === 'list') {
    $draw = intval($_POST['draw'] ?? 1);
    $start = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 10);
    $where = "WHERE 1=1";

    $totalRecords = getCount("stock_ledger");
    $totalFilteredRow = exeSql("SELECT COUNT(*) as total FROM stock_ledger $where");
    $totalFiltered = $totalFilteredRow[0]['total'];

    $sqlData = exeSql("SELECT * FROM stock_ledger $where ORDER BY moved_at DESC LIMIT $start, $length");

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

// Adjust stock (for corrections or manual updates)
if ($action === 'adjust') {
    $ledger_id = intval($_POST['ledger_id'] ?? 0);
    $quantity_in = floatval($_POST['quantity_in'] ?? 0);
    $quantity_out = floatval($_POST['quantity_out'] ?? 0);

    if (!$ledger_id || ($quantity_in <= 0 && $quantity_out <= 0)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid adjustment data']);
        exit;
    }

    $stmt = excuteSql("UPDATE stock_ledger SET qty_in = qty_in + $quantity_in, qty_out = qty_out + $quantity_out, moved_at = NOW() WHERE ledger_id = $ledger_id");

    if ($stmt) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Stock adjustment failed']);
    }
    exit;
}

// Fetch specific stock movement
if ($action === 'get') {
    $ledger_id = intval($_POST['ledger_id']);
    
    if (!$ledger_id) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ledger ID']);
        exit;
    }

    $stock = getRowValues("stock_ledger", $ledger_id, "ledger_id");
    echo json_encode($stock ? ['status' => 'success', 'data' => $stock] : ['status' => 'error', 'message' => 'Stock not found']);
    exit;
}
// ------- SUMMARY: Branch-wise Stock (aggregation) -------
if ($action === 'branchWiseStock') {
    // Optional filters
    $item_id         = intval($_REQUEST['item_id'] ?? 0);
    $sub_category_id = intval($_REQUEST['sub_category_id'] ?? 0);
    $branch_id       = intval($_REQUEST['branch_id'] ?? 0);

    $whereParts = [];
    if ($item_id)         $whereParts[] = "i.item_id = $item_id";
    if ($sub_category_id) $whereParts[] = "i.sub_category_id = $sub_category_id";
    if ($branch_id)       $whereParts[] = "sl.branch_id = $branch_id";

    $where = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

    // NOTE: uses INNER JOIN to include only items that have ledger movements.
    // If you want even items with zero movement, change to LEFT JOIN from items -> stock_ledger
    $sql = "
        SELECT
            i.item_id,
            i.item_name,
            sc.sub_category_name,
            COALESCE(SUM(sl.qty_in - sl.qty_out), 0) AS total
        FROM stock_ledger sl
        INNER JOIN items i ON i.item_id = sl.item_id
        LEFT JOIN sub_categories sc ON sc.sub_category_id = i.sub_category_id
        $where
        GROUP BY i.item_id, i.item_name, sc.sub_category_name
        ORDER BY i.item_name ASC
    ";

    $rows = exeSql($sql);
    echo json_encode(['status' => 'success', 'data' => $rows ?: []]);
    exit;
}

?>
