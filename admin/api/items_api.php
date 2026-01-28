<?php
header('Content-Type: application/json');
require_once('../../functions.php'); 
require '../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

function sanitize_regex($input) {
    return preg_replace('/[^a-zA-Z0-9\s\-]/', '', $input);
}

if ($action === 'list') {
    $draw = intval($_POST['draw'] ?? 1);
    $start = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 10);
    $where = "WHERE 1=1";

    if (!empty($_POST['filter_search'])) {
        $search = sanitize_regex($_POST['filter_search']);
        if ($search !== '') {
            $where .= " AND (i.item_name REGEXP '$search' OR i.item_code REGEXP '$search' OR c.category_name REGEXP '$search' OR sc.subcategory_name REGEXP '$search' OR i.uom REGEXP '$search')";
        }
    }

    $totalRecords = getCount("items");
    $totalFilteredRow = exeSql("
        SELECT COUNT(*) as total 
        FROM items i 
        LEFT JOIN categories c ON i.category_id = c.category_id 
        LEFT JOIN subcategories sc ON i.subcategory_id = sc.subcategory_id 
        $where
    ");
    $totalFiltered = $totalFilteredRow[0]['total'];

    $sqlData = exeSql("
        SELECT i.item_id, i.item_name, i.item_code, i.UOM, i.Tax_percentage, i.status, 
               c.category_name, sc.subcategory_name, i.unit_price 
        FROM items i 
        LEFT JOIN categories c ON i.category_id = c.category_id 
        LEFT JOIN subcategories sc ON i.subcategory_id = sc.subcategory_id 
        $where 
        ORDER BY i.item_id DESC 
        LIMIT $start, $length
    ");

    $data=[]; $sno=$start+1;
    foreach($sqlData as $r){ $r['sno']=$sno++; $data[]=$r; }

    echo json_encode([
        "draw"=>$draw,"recordsTotal"=>intval($totalRecords),
        "recordsFiltered"=>intval($totalFiltered),"data"=>$data
    ]);
    exit;
}

if ($action === 'create') {
    $category_id = intval($_POST['category_id'] ?? 0);
    $sub_category_id = intval($_POST['sub_category_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $item_code = trim($_POST['item_code'] ?? '');
    $UOM = trim($_POST['UOM'] ?? '');
    $Tax_percentage = floatval($_POST['Tax_percentage'] ?? 0);
    $unit_price = floatval($_POST['unit_price'] ?? 0);

    if(!$category_id){echo json_encode(['status'=>'error','message'=>'Category required']);exit;}
    if(!$sub_category_id){echo json_encode(['status'=>'error','message'=>'Sub-category required']);exit;}
    if(!$name){echo json_encode(['status'=>'error','message'=>'Item name required']);exit;}
    if(!$item_code){echo json_encode(['status'=>'error','message'=>'Item code required']);exit;}
    if(!$UOM){echo json_encode(['status'=>'error','message'=>'UOM required']);exit;}
    if(!$unit_price){echo json_encode(['status'=>'error','message'=>'Unit Price required']);exit;}
    // if(!$Tax_percentage){echo json_encode(['status'=>'error','message'=>'Tax percentage required']);exit;}

    $existing=exeSql("SELECT item_id FROM items WHERE item_code='$item_code'");
    if(!empty($existing)){echo json_encode(['status'=>'error','message'=>'Item code already exists']);exit;}

    $stmt=excuteSql("INSERT INTO items (category_id,subcategory_id,item_name,item_code,uom,tax_percentage, unit_price) 
                     VALUES ('$category_id','$sub_category_id','$name','$item_code','$UOM','$Tax_percentage', $unit_price)");
    echo json_encode($stmt?['status'=>'success']:['status'=>'error','message'=>'Insert failed']); exit;
}

if ($action === 'edit') {
    $id=intval($_POST['id']);
    $category_id=intval($_POST['category_id']);
    $sub_category_id=intval($_POST['sub_category_id']);
    $name=$_POST['name'];
    $item_code=$_POST['item_code'];
    $UOM=$_POST['UOM'];
    $Tax_percentage=floatval($_POST['Tax_percentage']);
    $status=$_POST['status'];
    $unit_price = floatval($_POST['unit_price'] ?? 0);

    $existing=exeSql("SELECT item_id FROM items WHERE item_code='$item_code' AND item_id!=$id");
    if(!empty($existing)){echo json_encode(['status'=>'error','message'=>'Item code already exists']);exit;}

    $sql=excuteSql("UPDATE items SET category_id='$category_id',subcategory_id='$sub_category_id',
        item_name='$name',item_code='$item_code',uom='$UOM',tax_percentage='$Tax_percentage',status='$status',unit_price='$unit_price' 
        WHERE item_id='$id'");
    echo json_encode($sql?['status'=>'success']:['status'=>'error','message'=>'Update failed']); exit;
}

if ($action === 'deactivate') {
    $id=intval($_POST['id'] ?? 0);
    if(!$id){echo json_encode(['status'=>'error','message'=>'ID required']);exit;}
    $sql=excuteSql("UPDATE items SET status='Inactive' WHERE item_id='$id'");
    echo json_encode($sql?['status'=>'success']:['status'=>'error','message'=>'Deactivate failed']); exit;
}

if ($action === 'getItem') {
    $id=intval($_POST['id']);
    $item=getValues('items',"item_id='$id'");
    echo json_encode($item?['status'=>'success','data'=>$item]:['status'=>'error','message'=>'Item not found']); exit;
}

if ($action === 'getCategories') {
    $cats=exeSql("SELECT category_id,category_name FROM categories WHERE status='Active' ORDER BY category_name ASC");
    echo json_encode(['status'=>'success','data'=>$cats]); exit;
}

if ($action === 'getSubCategories') {
    $cid=intval($_POST['category_id'] ?? 0);
    if(!$cid){echo json_encode(['status'=>'error','message'=>'Category ID required']);exit;}
    $subs=exeSql("SELECT subcategory_id,subcategory_name FROM subcategories WHERE category_id=$cid AND status='Active' ORDER BY subcategory_name ASC");
    echo json_encode(['status'=>'success','data'=>$subs]); exit;
}

/* ✅ ADDED/UPDATED: Items by subcategory now also returns Tax_percentage */
if ($action === 'getItemsBySubCategory') {
    $sid = intval($_POST['subcategory_id'] ?? 0);
    if(!$sid){echo json_encode(['status'=>'error','message'=>'Sub-category ID required']);exit;}
    $items = exeSql("
        SELECT item_id, item_name, item_code, tax_percentage
        FROM items 
        WHERE subcategory_id = $sid AND status='Active'
        ORDER BY item_name ASC
    ");
    echo json_encode(['status'=>'success','data'=>$items]); 
    exit;
}

/* ✅ UPDATED: Include Tax_percentage so we can seed Tax % on row add */
if ($action === 'getActiveItems') {
    $items=exeSql("
        SELECT item_id, item_name, tax_percentage
        FROM items 
        WHERE status='Active' 
        ORDER BY item_name ASC
    ");
    echo json_encode(['status'=>'success','data'=>$items]); exit;
}

if ($action === 'getItemsByService') {
    $sid=intval($_POST['service_id'] ?? 0);
    if(!$sid){echo json_encode(['status'=>'error','message'=>'Service ID required']);exit;}
    $items=exeSql("SELECT i.item_id,i.item_name FROM items i JOIN service_items si ON i.item_id=si.item_id WHERE si.service_id=$sid AND i.status='Active' ORDER BY i.item_name ASC");
    echo json_encode(['status'=>'success','data'=>$items]); exit;
}

/* Optional helper: Fetch branch address */
if ($action === 'getBranchAddress') {
    $branch_id = intval($_POST['branch_id'] ?? 0);
    if(!$branch_id){echo json_encode(['status'=>'error','message'=>'Branch ID required']);exit;}
    $branch = exeSql("SELECT CONCAT(address, ', ', city, ', ', state) AS full_address 
                      FROM branches 
                      WHERE branch_id = $branch_id");
    echo json_encode(!empty($branch)?['status'=>'success','data'=>$branch[0]]:['status'=>'error','message'=>'Branch not found']); 
    exit;
}

if ($_POST['action'] == 'uploadExcel') {

    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == 0) {
        $tmp = $_FILES['excel_file']['tmp_name'];
        $spreadsheet = IOFactory::load($tmp);
        $sheetData = $spreadsheet->getActiveSheet()->toArray();

        $inserted = 0;

        try {
            $dbObj->beginTransaction(); // Start transaction

            for ($i = 1; $i < count($sheetData); $i++) {
                $category_name    = trim($sheetData[$i][0]);
                $subcategory_name = trim($sheetData[$i][1]);
                $item_name        = trim($sheetData[$i][2]);
                $item_code        = trim($sheetData[$i][3]);
                $uom              = trim($sheetData[$i][4]);
                $tax_percentage   = trim($sheetData[$i][5]);

                if ($item_name == '') continue;

                // Lookup category_id
                $cat = $dbObj->getOneRow("SELECT category_id FROM inventory_category WHERE category_name = '{$category_name}'");
                if (!$cat) {
                    throw new Exception("Invalid category name '{$category_name}' found in row " . ($i + 1));
                }
                $category_id = $cat['category_id'];

                // Lookup subcategory_id
                $sub = $dbObj->getOneRow("SELECT subcategory_id FROM inventory_subcategory WHERE subcategory_name = '{$subcategory_name}' AND category_id = '{$category_id}'");
                if (!$sub) {
                    throw new Exception("Invalid subcategory name '{$subcategory_name}' for category '{$category_name}' in row " . ($i + 1));
                }
                $subcategory_id = $sub['subcategory_id'];

                // Insert item
                $data = [
                    'category_id'    => $category_id,
                    'subcategory_id' => $subcategory_id,
                    'item_name'      => $item_name,
                    'item_code'      => $item_code,
                    'UOM'            => $uom,
                    'tax_percentage' => $tax_percentage,
                    'status'         => 'Active'
                ];

                $dbObj->insertData('inventory_items', $data);
                $inserted++;
            }

            $dbObj->commit(); 
            echo json_encode(['status' => 'success', 'message' => "$inserted items uploaded."]);

        } catch (Exception $e) {
            $dbObj->rollBack(); // Rollback everything
            echo json_encode(['status' => 'error', 'message' => 'Upload stopped: ' . $e->getMessage()]);
        }

    } else {
        echo json_encode(['status' => 'error', 'message' => 'No file selected']);
    }

    exit;
}

if ($_POST['action'] == 'exportAll') {
    $sql = "SELECT i.item_id, c.category_name, s.subcategory_name,
                   i.item_name, i.item_code, i.unit_price, 
                   i.tax_percentage, i.uom, i.status
            FROM items i
            LEFT JOIN categories c ON c.category_id = i.category_id
            LEFT JOIN subcategories s ON s.subcategory_id = i.subcategory_id
            ORDER BY i.item_id DESC";

    $data = exeSql($sql); // your helper to fetch all rows

    echo json_encode([
        'status' => 'success',
        'data' => $data
    ]);
    exit;
}



?>
