<?php
// Turn off error display to prevent HTML output in JSON response
// ini_set('display_errors', 0);
// Enable error logging
// ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/error_log.txt');

try {
    // Set header for JSON response
    header('Content-Type: application/json');
    
    // Include functions
    require_once('../../functions.php');
    
    // Get action
    $action = $_POST['action'] ?? ($_GET['action'] ?? '');
    
    if (!$action) {
        throw new Exception('Invalid action');
    }
    
   if ($action == 'list') {

    $clean = function($s) {
        return preg_replace('/[^a-zA-Z0-9_\-\s]/', '', (string)$s);
    };
    $is_valid_date = function($d) {
        // expects YYYY-MM-DD
        return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
    };
    // $status_to_internal = function($friendlyStatus) {
    //     // map friendly filter to internal column values
    //     $friendlyStatus = strtoupper(trim((string)$friendlyStatus));
    //     if ($friendlyStatus === 'OPEN') {
    //         return ['Draft','Submitted','Approved'];
    //     } elseif ($friendlyStatus === 'CLOSED') {
    //         return ['Partially Ordered','Ordered'];
    //     } elseif ($friendlyStatus === 'CANCELLED') {
    //         return ['Cancelled'];
    //     }
    //     return []; // unknown or empty -> ignore
    // };

    // ----------------------------
    // datatables base params
    // ----------------------------
    $draw   = intval($_POST['draw'] ?? 1);
    $start  = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 10);

    // ----------------------------
    // build WHERE
    // ----------------------------
    $where = "WHERE 1=1";

    // existing quick search on indent number
    if (!empty($_POST['filter_search'])) {
        $search = $clean($_POST['filter_search']);
        $where .= " AND i.indent_number LIKE '%$search%'";
    }

    // NEW: branch filter (optional)
    if (!empty($_POST['branch_id'])) {
        $branch_id = intval($_POST['branch_id']);
        $where .= " AND i.branch_id = $branch_id";
    }

    // NEW: requested_by filter (optional) — your list page shows users.username
    if (!empty($_POST['requested_by'])) {
        $requested_by = intval($_POST['requested_by']);
        // your list join uses i.raised_by = u.user_id
        $where .= " AND i.raised_by = $requested_by";
    }

    // NEW: status filter (OPEN/CLOSED/CANCELLED -> internal values)
    if (!empty($_POST['status'])) {
        $internals = $status_to_internal($_POST['status']);
        if (!empty($internals)) {
            // create a safe IN clause
            $in = implode("','", array_map('addslashes', $internals));
            $where .= " AND i.status IN ('$in')";
        }
    }

    // NEW: date range by indent_date (optional)
    $start_date = $_POST['start_date'] ?? '';
    $end_date   = $_POST['end_date'] ?? '';
    if ($start_date && $end_date && $is_valid_date($start_date) && $is_valid_date($end_date)) {
        $where .= " AND i.indent_date BETWEEN '$start_date' AND '$end_date'";
    } elseif ($start_date && $is_valid_date($start_date)) {
        $where .= " AND DATE(i.indent_date) = '$start_date'";
    } elseif ($end_date && $is_valid_date($end_date)) {
        $where .= " AND DATE(i.indent_date) <= '$end_date'";
    }

    // ----------------------------
    // status counts (kept as-is: global)
    // ----------------------------


     // Get status counts with mapping to OPEN, CLOSED, CANCELLED
        $countQuery = "SELECT 
            SUM(CASE WHEN status = 'Opened' THEN 1 ELSE 0 END) as Opened,
            SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) as Closed,
            SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_count
        FROM indents";
        
        $countResult = exeSql($countQuery);
        $statusCounts = [
            'Opened' => (int)($countResult[0]['Opened'] ?? 0),
            'Closed' => (int)($countResult[0]['Closed'] ?? 0),
            'cancelled' => (int)($countResult[0]['cancelled_count'] ?? 0)
        ];
    // ----------------------------
    // totals
    // ----------------------------
    $totalRecords = getCount("indents");

    $totalFilteredRow = exeSql("SELECT COUNT(*) as total 
                                FROM indents i 
                                $where");
    $totalFiltered = (int)($totalFilteredRow[0]['total'] ?? 0);

    $sqlData = exeSql("
        SELECT 
            i.*,
            u.username AS requested_name,
            b.branch_name
        FROM indents i
        LEFT JOIN users u   ON i.raised_by = u.user_id
        LEFT JOIN branches b ON i.branch_id = b.branch_id
        $where
        ORDER BY i.indent_id DESC
        LIMIT $start, $length
    ");

   $sno = $start + 1; // DataTables pagination base index
$data = [];

foreach ($sqlData as $row) {
    $row['sno'] = $sno++; // add serial number
    $data[] = $row;
}

    echo json_encode([
        'status'           => 'success',
        'draw'             => $draw,
        'recordsTotal'     => (int)$totalRecords,
        'recordsFiltered'  => (int)$totalFiltered,
        'data'             => $data,
        'status_counts'    => $statusCounts
    ]);
    exit;
}
if ($action == 'getStatusCounts') {
        // Get status counts with mapping to OPEN, CLOSED, CANCELLED
        $countQuery = "SELECT 
            SUM(CASE WHEN status = 'Opened' THEN 1 ELSE 0 END) as open_count,
            SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) as closed_count,
            SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_count
        FROM indents";
        
        $countResult = exeSql($countQuery);
        $statusCounts = [
            'Opened' => (int)($countResult[0]['open_count'] ?? 0),
            'Closed' => (int)($countResult[0]['closed_count'] ?? 0),
            'Cancelled' => (int)($countResult[0]['cancelled_count'] ?? 0)
        ];
        
        
        echo json_encode([
            'status' => "success",
            "counts" => $statusCounts
        ]);
        exit;
    }
    
    if ($action == 'create') {
        // Get form data
        $raised_by = intval($_POST['raised_by'] ?? 0);
        $indent_date = $_POST['indent_date'] ?? '';
        $branch = intval($_POST['branch'] ?? 0);
        $indent_against = trim($_POST['indent_against'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        
        // Validate required fields (only required ones) - use empty() for better validation
        if (empty($raised_by) || empty($indent_date)) {
            throw new Exception('Required fields missing: raised_by and indent_date are required');
        }
        
        // Get items data
        $items_json = $_POST['items'] ?? '[]';
        $items = json_decode($items_json, true);
        
        // Check if JSON decoding failed
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON in items data: ' . json_last_error_msg());
        }
        
        // Start transaction
        $dbObj->beginTransaction();
        
        try {
            // Generate indent number
            $indent_number = 'IND-' . date('YmdHis');
            $sql = "INSERT INTO indents (indent_number, raised_by, indent_date, branch_id, indent_against, remarks) VALUES ('$indent_number', '$raised_by', '$indent_date', '$branch', '$indent_against', '$remarks')";
            $stmt = excuteSql($sql);
            // Get last insert ID
            $indent_id = $dbObj->getLastInsertId();
            
            // Insert items if any (items are optional)
            if (!empty($items)) {
                foreach ($items as $item) {
                    $item_id = intval($item['item_id'] ?? 0);
                    $quantity = floatval($item['quantity'] ?? 0);
                    $description = trim($item['description'] ?? '');
                    
                    if ($item_id && $quantity > 0) {
                        $itemSql = "INSERT INTO indent_items (indent_id, item_id, qty_requested, description) VALUES ('$indent_id', '$item_id', '$quantity', " . 
                                  (empty($description) ? "NULL" : "'$description'") . ")";
                        excuteSql($itemSql);
                    }
                }
            }
            
            // Commit transaction
            $dbObj->commit();
            
            // Return success response
            echo json_encode([
                'status' => 'success',
                'message' => 'Indent created successfully',
                'indent_id' => $indent_id,
                'indent_number' => $indent_number
            ]);
        } catch (Exception $e) {
            // Rollback on error
            $dbObj -> rollBack();
            // error_log('Transaction rolled back: ' . $e->getMessage());
            throw $e;
        }
        exit;
    }
    
    if ($action == 'getIndent') {
        $id = intval($_POST['indent_id']);
        $indent = getValues('indents', "indent_id=$id");
        
        if ($indent) {
            // Get items for this indent
            $itemsSql = "SELECT ii.item_id, ii.qty_requested, ii.description, it.item_name 
                         FROM indent_items ii 
                         JOIN items it ON ii.item_id = it.item_id 
                         WHERE ii.indent_id = $id";
            $items = exeSql($itemsSql);
            
            // Add items to indent data
            $indent['items'] = $items;
            
            echo json_encode(['status'=>'success','data'=>$indent]);
        } else {
            throw new Exception('Indent not found');
        }
        exit;
    }
if ($action === 'updateStatus') {
    $indent_id = $_POST['indent_id'];
    $status = $_POST['status'];

    $ok = excuteSql("UPDATE indents SET status = '$status' WHERE indent_id = '$indent_id'");
   

    if ($ok) {
        // Fetch updated status from DB
        $updated = exeSql("SELECT status FROM indents WHERE indent_id = '$indent_id'")[0];

        echo json_encode([
            "status" => "success",
            "message" => "Status updated",
            "updated_status" => $updated
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Update failed"]);
    }
    exit;
}


    if ($action == 'getIndentsByBranch') {
        $branchId = $_GET['branch_id'];

        $indent = getSubject('indents', "branch_id = '$branchId' AND status != 'Closed'");
        
        if ($indent) {
            echo json_encode(['status'=>'success','data'=>$indent]);
        } else {
            throw new Exception('Indent not found');
        }
        exit;
    }
    
    if ($action == 'edit') {
        $id = intval($_POST['indent_id']);
        $raised_by = intval($_POST['raised_by'] ?? 0);
        $indent_date = $_POST['indent_date'] ?? '';
        $branch = intval($_POST['branch'] ?? 0);
        $indent_against = trim($_POST['indent_against'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        
        // Get items data
        $items_json = $_POST['items'] ?? '[]';
        $items = json_decode($items_json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON in items data: ' . json_last_error_msg());
        }
        
        $dbObj->beginTransaction();
        
        try {
            // Update indent record
            $update_fields = [
                "raised_by = '$raised_by'",
                "indent_date = '$indent_date'",
                "branch_id = '$branch'",
                "indent_against = '$indent_against'",
                "remarks = " . (empty($remarks) ? "NULL" : "'$remarks'")
            ];
            
            $update_sql = "UPDATE indents SET " . implode(", ", $update_fields) . " WHERE indent_id = '$id'";
            excuteSql($update_sql);
            
            // Delete existing items
            excuteSql("DELETE FROM indent_items WHERE indent_id = $id");
            
            // Insert new items (even if empty, we'll handle validation)
            if (!empty($items)) {
                foreach ($items as $item) {
                    $item_id = intval($item['item_id'] ?? 0);
                    $quantity = floatval($item['quantity'] ?? 0);
                    $description = trim($item['description'] ?? '');
                    
                    // Only insert if we have valid item and quantity
                    if ($item_id > 0 && $quantity > 0) {
                        $itemSql = "INSERT INTO indent_items (indent_id, item_id, qty_requested, description) 
                                    VALUES ('$id', '$item_id', '$quantity', " . 
                                    (empty($description) ? "NULL" : "'$description'") . ")";
                        excuteSql($itemSql);
                    }
                }
            }
            
            $dbObj->commit();
            echo json_encode(['status'=>'success', 'message'=>'Indent updated successfully']);
        } catch (Exception $e) {
            $dbObj->rollBack();
            // error_log('Edit transaction rolled back: ' . $e->getMessage());
            throw $e;
        }
        exit;
    }
    
    if ($action == 'cancel') {
        $id = intval($_POST['indent_id']);
        if (!$id) {
            throw new Exception('ID required');
        }
        $stmt = excuteSql("UPDATE indents SET status='Cancelled' WHERE indent_id=$id");
        if ($stmt) {
            
            echo json_encode(['status'=>'success']);
        } else {
            throw new Exception('Cancel failed');
        }
        exit;
    }
    
    throw new Exception('Invalid action');
    
} catch (Exception $e) {
    
    // Log the error
    // error_log('Indent API Error: ' . $e->getMessage());
    
    // Return the error as JSON
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>