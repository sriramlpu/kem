<?php
header('Content-Type: application/json');
require_once('../../functions.php');

// Helpers
function as_int($v){ return intval($v ?? 0); }
function as_date($v){
  $v = trim($v ?? '');
  return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
}

$action = $_POST['action'] ?? 'list';
$draw   = intval($_POST['draw'] ?? 1);
$start  = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);

// Filters from UI
$branch_id      = as_int($_POST['branch_id'] ?? 0);
$category_id    = as_int($_POST['category_id'] ?? 0);     // <— added
$subcategory_id = as_int($_POST['subcategory_id'] ?? 0);
$item_id        = as_int($_POST['item_id'] ?? 0);
$vendor_id      = as_int($_POST['vendor_id'] ?? 0);
$date_from      = as_date($_POST['date_from'] ?? '');
$date_to        = as_date($_POST['date_to'] ?? '');

if ($action !== 'list') {
  echo json_encode(['status'=>'error','message'=>'Unsupported action']);
  exit;
}

/**
 * Base FROM/JOIN
 */
$from = "
  FROM goods_receipt_items gri
  JOIN goods_receipts gr          ON gr.grn_id = gri.grn_id
  LEFT JOIN purchase_orders po    ON gr.po_id = po.po_id
  LEFT JOIN branches b            ON po.branch_id = b.branch_id
  LEFT JOIN items i               ON i.item_id = gri.item_id
  LEFT JOIN item_subcategories sc ON sc.subcategory_id = i.subcategory_id
  LEFT JOIN vendors v             ON v.vendor_id = gr.vendor_id
  WHERE 1=1
";

/**
 * Filters
 */
$filters = "";
if ($branch_id > 0)      $filters .= " AND po.branch_id = $branch_id";
if ($category_id > 0)    $filters .= " AND sc.category_id = $category_id";   // <— added
if ($subcategory_id > 0) $filters .= " AND i.subcategory_id = $subcategory_id";
if ($item_id > 0)        $filters .= " AND i.item_id = $item_id";
if ($vendor_id > 0)      $filters .= " AND gr.vendor_id = $vendor_id";

/* If grn_date is DATETIME, cast to DATE to include full day for date_to */
if ($date_from !== '')   $filters .= " AND DATE(gr.grn_date) >= '$date_from'";
if ($date_to   !== '')   $filters .= " AND DATE(gr.grn_date) <= '$date_to'";

/**
 * Ordering (matches DataTables columns)
 * 0 = S No, 1 = item_name, 2 = subcategory_name, 3 = total_amount
 */
$orderCol = 'i.item_name';
$orderDir = 'ASC';
if (isset($_POST['order'][0]['column'], $_POST['order'][0]['dir'])) {
  $colIdx = intval($_POST['order'][0]['column']);
  $dir    = strtoupper($_POST['order'][0]['dir']) === 'DESC' ? 'DESC' : 'ASC';
  switch ($colIdx) {
    case 1: $orderCol = 'i.item_name'; break;
    case 2: $orderCol = 'sc.subcategory_name'; break;
    case 3: $orderCol = 'total_amount'; break; // uses alias in outer
    default: $orderCol = 'i.item_name';
  }
  $orderDir = $dir;
}

/**
 * recordsFiltered: count groups after filters
 */
$countSql = "
  SELECT COUNT(*) AS cnt
  FROM (
    SELECT i.item_id, i.subcategory_id
    $from
    $filters
    GROUP BY i.item_id, i.subcategory_id
  ) t
";
$cntRow = exeSql($countSql);
$recordsFiltered = intval($cntRow[0]['cnt'] ?? 0);

/**
 * Data page
 */
$dataSql = "
  SELECT 
    i.item_id,
    COALESCE(i.item_name, '—') AS item_name,
    i.subcategory_id,
    COALESCE(sc.subcategory_name, '—') AS subcategory_name,
    SUM(COALESCE(gri.amount, gri.qty * gri.rate, 0)) AS total_amount
  $from
  $filters
  GROUP BY i.item_id, i.subcategory_id, i.item_name, sc.subcategory_name
  ORDER BY " . ($orderCol === 'total_amount' ? 'total_amount' : $orderCol) . " $orderDir
";
$limitSql = ($length > 0) ? " LIMIT $start, $length" : "";
$rows = exeSql($dataSql . $limitSql);

/**
 * recordsTotal: count all groups without filters
 */
$totalSql = "
  SELECT COUNT(*) AS cnt
  FROM (
    SELECT i.item_id, i.subcategory_id
    $from
    GROUP BY i.item_id, i.subcategory_id
  ) t0
";
$totalRow = exeSql($totalSql);
$recordsTotal = intval($totalRow[0]['cnt'] ?? 0);

/**
 * Output
 */
echo json_encode([
  "draw" => $draw,
  "recordsTotal" => $recordsTotal,
  "recordsFiltered" => $recordsFiltered,
  "data" => array_map(function($r){
    $r['total_amount'] = (float)($r['total_amount'] ?? 0);
    return $r;
  }, $rows)
]);
