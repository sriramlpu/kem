<?php
// Fixed purchase_order_api.php compatible with your functions (without insData)
ini_set('display_errors', 1);
ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/error_log.txt');

// Start output buffering to catch any unwanted output
// ob_start();
try {
    header('Content-Type: application/json');
    require_once('../../functions.php');

    // --- Helper used by the 'list' action ---
    if (!function_exists('sanitize_regex')) {
        function sanitize_regex($input)
        {
            // allow letters, numbers, spaces, hyphen and slash
            return preg_replace('/[^a-zA-Z0-9\s\-\/]/', '', (string)$input);
        }
    }

    $action = $_POST['action'] ?? ($_GET['action'] ?? '');
    // error_log('Action: ' . $action);

   if ($action === 'create') {
    // Get and validate form data
    $poType = trim($_POST['po_type'] ?? '');
    $branchId = intval($_POST['branch_id'] ?? 0);
    $poDate = trim($_POST['po_date'] ?? '');
    $expectedDeliveryDate = trim($_POST['expected_delivery_date'] ?? '');
    $itemLocation = trim($_POST['item_location'] ?? 'Store');

    $deliveryAddress = trim($_POST['delivery_address'] ?? '');
    $venueLocationAddress = trim($_POST['venue_location_address'] ?? '');
    $billingAddress = trim($_POST['billing_address'] ?? '');

    $discountAmount = floatval($_POST['discount_amount'] ?? 0);
    $transportation = floatval($_POST['transportation'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');
    $indentNumber = $_POST['indent_number'] ?? null;

    if(empty($indentNumber) || $indentNumber <= 0){
        $indentNumber = NULL;
    }

    // Enhanced validation
    $validationErrors = [];

    if (empty($poType)) {
        $validationErrors[] = "PO Type is required";
    } else if (!in_array($poType, ['WITH INDENT', 'WITHOUT INDENT'])) {
        $validationErrors[] = "Invalid PO Type: '$poType'";
    }

    if ($branchId <= 0) {
        $validationErrors[] = "Valid Branch is required";
    } else {
        try {
            $branchExists = getRowValues("branches", $branchId, "branch_id");
            if (!$branchExists) {
                $validationErrors[] = "Branch ID $branchId does not exist";
            }
        } catch (Exception $e) {
            $validationErrors[] = "Error validating branch: " . $e->getMessage();
        }
    }

    if (empty($poDate)) {
        $validationErrors[] = "PO Date is required";
    } else {
        $dateObj = DateTime::createFromFormat('Y-m-d', $poDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $poDate) {
            $validationErrors[] = "Invalid PO Date format: '$poDate'";
        }
    }

    // Validate items
    if (!isset($_POST['items']) || !is_array($_POST['items']) || count($_POST['items']) === 0) {
        $validationErrors[] = "At least one item is required";
    }

    // Validate that all items have vendors
    if (isset($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $index => $item) {
            $itemVendorId = intval($item['vendor_id'] ?? 0);
            if ($itemVendorId <= 0) {
                $validationErrors[] = "Item #" . ($index + 1) . " must have a vendor selected";
            }
        }
    }

    if (!empty($validationErrors)) {
        throw new Exception('Validation failed: ' . implode('; ', $validationErrors));
    }

    // ========== GROUP ITEMS BY VENDOR ==========
    $itemsByVendor = [];
    foreach ($_POST['items'] as $item) {
        $vendorId = intval($item['vendor_id'] ?? 0);
        if ($vendorId > 0) {
            if (!isset($itemsByVendor[$vendorId])) {
                $itemsByVendor[$vendorId] = [];
            }
            $itemsByVendor[$vendorId][] = $item;
        }
    }

    if (empty($itemsByVendor)) {
        throw new Exception('No valid items with vendors found');
    }

    // ========== BULLETPROOF PO CREATION ==========
    $createdPOs = [];
    $datePart = date('Ymd', strtotime($poDate));

    try {
        // Start transaction
        excuteSql("START TRANSACTION");

        foreach ($itemsByVendor as $vendorId => $vendorItems) {
            // Verify vendor exists
            $vendorExists = getRowValues("vendors", $vendorId, "vendor_id");
            if (!$vendorExists) {
                throw new Exception("Vendor ID $vendorId does not exist");
            }

            // **BULLETPROOF PO NUMBER GENERATION**
            $poNumber = null;
            $maxAttempts = 20;
            $attempt = 0;

            while ($attempt < $maxAttempts && !$poNumber) {
                try {
                    // Method 1: Use atomic sequence table [web:65][web:79]
                    $seqSql = "INSERT INTO purchase_order_sequences (date_part, last_sequence) 
                               VALUES ('$datePart', 1) 
                               ON DUPLICATE KEY UPDATE last_sequence = last_sequence + 1";
                    
                    if (excuteSql($seqSql)) {
                        // Get the sequence number
                        $getSeqSql = "SELECT last_sequence FROM purchase_order_sequences WHERE date_part = '$datePart'";
                        $seqResult = exeSql($getSeqSql);
                        
                        if ($seqResult && isset($seqResult[0]['last_sequence'])) {
                            $sequence = str_pad($seqResult[0]['last_sequence'], 4, '0', STR_PAD_LEFT);
                            $testPoNumber = "PO-$datePart-$sequence";
                            
                            // Verify this number doesn't exist (extra safety)
                            $checkSql = "SELECT COUNT(*) as count FROM purchase_orders WHERE order_number = '$testPoNumber'";
                            $checkResult = exeSql($checkSql);
                            
                            if ($checkResult[0]['count'] == 0) {
                                $poNumber = $testPoNumber;
                                break;
                            }
                        }
                    }
                    
                    // Method 2: Fallback method with direct counting [web:72]
                    if (!$poNumber) {
                        $countSql = "SELECT COALESCE(MAX(CAST(SUBSTRING(order_number, -4) AS UNSIGNED)), 0) + 1 as next_seq 
                                     FROM purchase_orders 
                                     WHERE order_number LIKE 'PO-$datePart%'";
                        $countResult = exeSql($countSql);
                        
                        if ($countResult && isset($countResult[0]['next_seq'])) {
                            $nextSeq = intval($countResult[0]['next_seq']) + $attempt;
                            $sequence = str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
                            $testPoNumber = "PO-$datePart-$sequence";
                            
                            // Check if this number is available
                            $checkSql = "SELECT COUNT(*) as count FROM purchase_orders WHERE order_number = '$testPoNumber'";
                            $checkResult = exeSql($checkSql);
                            
                            if ($checkResult[0]['count'] == 0) {
                                $poNumber = $testPoNumber;
                                break;
                            }
                        }
                    }

                } catch (Exception $e) {
                    // Log the error but continue trying
                    error_log("PO Number generation attempt $attempt failed: " . $e->getMessage());
                }

                $attempt++;
                
                // Small random delay to prevent synchronized attempts [web:85]
                if ($attempt < $maxAttempts) {
                    usleep(rand(10000, 100000)); // 0.01 to 0.1 seconds
                }
            }

            if (!$poNumber) {
                throw new Exception("Unable to generate unique PO number after $maxAttempts attempts for date $datePart");
            }

            // Calculate totals for this vendor's items
            $vendorTotalAmount = 0;
            $vendorDiscountAmount = 0;

            foreach ($vendorItems as $item) {
                $vendorTotalAmount += floatval($item['subjective_amount'] ?? 0);
                $vendorDiscountAmount += floatval($item['discount_amount'] ?? 0);
            }

            // Distribute transportation proportionally if multiple vendors
            $vendorTransportation = 0;
            if (count($itemsByVendor) > 1 && $transportation > 0) {
                $totalAmount = floatval($_POST['total_amount'] ?? 0);
                if ($totalAmount > 0) {
                    $vendorTransportation = ($vendorTotalAmount / $totalAmount) * $transportation;
                }
            } else {
                $vendorTransportation = $transportation;
            }

            // Build SQL for purchase order insertion
            $columns = [
                'po_type',
                'branch_id',
                'order_number',
                'vendor_id',
                'po_date',
                'item_location',
                'total_amount',
                'discount_amount',
                'transportation',
                'indent_id'
            ];

            $indentValue = is_null($indentNumber) ? "NULL" : intval($indentNumber);

            $values = [
                "'" . addslashes($poType) . "'",
                $branchId,
                "'" . addslashes($poNumber) . "'",
                $vendorId,
                "'" . addslashes($poDate) . "'",
                "'" . addslashes($itemLocation) . "'",
                $vendorTotalAmount,
                $vendorDiscountAmount,
                $vendorTransportation,
                $indentValue
            ];

            // Add optional fields
            if (!empty($expectedDeliveryDate)) {
                $columns[] = 'expected_delivery_date';
                $values[] = "'" . addslashes($expectedDeliveryDate) . "'";
            }
            if (!empty($deliveryAddress)) {
                $columns[] = 'delivery_address';
                $values[] = "'" . addslashes($deliveryAddress) . "'";
            }
            if (!empty($venueLocationAddress)) {
                $columns[] = 'venue_location_address';
                $values[] = "'" . addslashes($venueLocationAddress) . "'";
            }
            if (!empty($billingAddress)) {
                $columns[] = 'billing_address';
                $values[] = "'" . addslashes($billingAddress) . "'";
            }
            if (!empty($remarks)) {
                $columns[] = 'remarks';
                $values[] = "'" . addslashes($remarks) . "'";
            }

            $columnsStr = implode(', ', $columns);
            $valuesStr = implode(', ', $values);

            // **ATOMIC INSERT WITH RETRY ON DUPLICATE [web:84]**
            $insertSuccess = false;
            $insertAttempts = 0;
            $maxInsertAttempts = 5;

            while (!$insertSuccess && $insertAttempts < $maxInsertAttempts) {
                try {
                    $sql = "INSERT INTO purchase_orders ($columnsStr) VALUES ($valuesStr)";
                    $result = excuteSql($sql);
                    
                    if ($result) {
                        $insertSuccess = true;
                    } else {
                        throw new Exception('Insert operation returned false');
                    }
                    
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    
                    // Check if it's a duplicate entry error [web:23]
                    if (strpos($errorMsg, '1062') !== false || 
                        strpos(strtolower($errorMsg), 'duplicate entry') !== false) {
                        
                        $insertAttempts++;
                        
                        if ($insertAttempts >= $maxInsertAttempts) {
                            // Generate completely new PO number
                            $timestamp = microtime(true);
                            $uniqueSuffix = substr(str_replace('.', '', $timestamp), -6);
                            $poNumber = "PO-$datePart-$uniqueSuffix";
                            
                            // Update values array with new PO number
                            $values[2] = "'" . addslashes($poNumber) . "'";
                            $valuesStr = implode(', ', $values);
                        } else {
                            // Try incrementing the sequence
                            $currentSeq = intval(substr($poNumber, -4));
                            $newSeq = $currentSeq + $insertAttempts;
                            $sequence = str_pad($newSeq, 4, '0', STR_PAD_LEFT);
                            $poNumber = "PO-$datePart-$sequence";
                            
                            // Update values array
                            $values[2] = "'" . addslashes($poNumber) . "'";
                            $valuesStr = implode(', ', $values);
                        }
                        
                        // Small delay before retry
                        usleep(rand(5000, 25000)); // 0.005 to 0.025 seconds
                        
                    } else {
                        // Other error, rethrow immediately
                        throw new Exception("PO Insert failed: " . $errorMsg);
                    }
                }
            }

            if (!$insertSuccess) {
                throw new Exception("Failed to create purchase order after $maxInsertAttempts attempts");
            }

            // Update indent status if applicable
            if (!is_null($indentNumber)) {
                $upsql = "UPDATE indents SET status = 'Closed' WHERE indent_id = $indentValue";
                $upresult = excuteSql($upsql);

                if (!$upresult) {
                    throw new Exception('Failed to update indent status');
                }
            }

            // Get newly created PO ID
            $sql = "SELECT LAST_INSERT_ID() as po_id";
            $result = exeSql($sql);
            if (!$result || !isset($result[0]['po_id'])) {
                throw new Exception('Failed to retrieve purchase order ID');
            }
            $poId = $result[0]['po_id'];

            // Insert items for this PO
            foreach ($vendorItems as $item) {
                $itemId = intval($item['item_id'] ?? 0);
                $quantity = floatval($item['quantity'] ?? 0);
                $unitPrice = floatval($item['unit_price'] ?? 0);
                $discountPercentage = floatval($item['discount_percentage'] ?? 0);
                $discountAmount = floatval($item['discount_amount'] ?? 0);
                $taxPercentage = floatval($item['tax_percentage'] ?? 0);
                $taxType = 1;
                $gstSlab = 1;
                $taxAmount = floatval($item['tax_amount'] ?? 0);
                $subjectiveAmount = floatval($item['subjective_amount'] ?? 0);

                // Validate item
                if ($itemId <= 0) continue;

                $itemExists = getRowValues("items", $itemId, "item_id");
                if (!$itemExists) continue;

                // Build SQL for item insertion
                $itemColumns = [
                    'po_id', 'item_id', 'quantity', 'unit_price', 'discount_percentage',
                    'discount_amount', 'tax_type_id', 'gst_slab_id', 'tax_amount',
                    'subjective_amount', 'tax_percentage'
                ];

                $itemValues = [
                    $poId, $itemId, $quantity, $unitPrice, $discountPercentage,
                    $discountAmount, $taxType, $gstSlab, $taxAmount,
                    $subjectiveAmount, $taxPercentage
                ];

                foreach ($itemValues as &$value) {
                    if (is_string($value)) {
                        $value = "'" . addslashes($value) . "'";
                    } elseif (is_null($value)) {
                        $value = "NULL";
                    }
                }

                $itemColumnsStr = implode(', ', $itemColumns);
                $itemValuesStr = implode(', ', $itemValues);

                $itemSql = "INSERT INTO purchase_order_items ($itemColumnsStr) VALUES ($itemValuesStr)";
                $itemResult = excuteSql($itemSql);

                if (!$itemResult) {
                    throw new Exception('Failed to add item to purchase order');
                }
            }

            // Store created PO info
            $createdPOs[] = [
                'po_id' => $poId,
                'po_number' => $poNumber,
                'vendor_id' => $vendorId,
                'item_count' => count($vendorItems),
                'total_amount' => $vendorTotalAmount
            ];
        }

        // Commit transaction if all operations successful
        excuteSql("COMMIT");

    } catch (Exception $e) {
        // Rollback transaction on any error
        excuteSql("ROLLBACK");
        
        // Clean up any partial sequence entries
        try {
            excuteSql("DELETE FROM purchase_order_sequences WHERE date_part = '$datePart' AND last_sequence = 0");
        } catch (Exception $cleanupError) {
            // Ignore cleanup errors
        }
        
        throw new Exception('PO Creation failed: ' . $e->getMessage());
    }

    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => count($createdPOs) . ' Purchase Order(s) created successfully',
        'purchase_orders' => $createdPOs,
        'total_pos' => count($createdPOs)
    ]);
    exit;
}



    // Get purchase order details (supports GET or POST for po_id)
    if ($action === 'get') {
        $poId = intval($_POST['po_id'] ?? ($_GET['po_id'] ?? 0));
        if ($poId <= 0) {
            throw new Exception('Valid PO ID is required');
        }

        $po = exeSql("
            SELECT po.po_id, po.order_number, po.po_date, po.total_amount,
                   v.vendor_name, b.branch_name, po.po_type, po.expected_delivery_date
            FROM purchase_orders po
            JOIN vendors v  ON po.vendor_id  = v.vendor_id
            JOIN branches b ON po.branch_id = b.branch_id
            WHERE po.po_id = '$poId'
            ORDER BY po.po_id DESC
        ");

        if (!$po) {
            throw new Exception('Purchase Order not found');
        }

        // Get PO items
        $itemsSql = "
            SELECT poi.*, i.item_name
            FROM purchase_order_items poi
            JOIN items i ON poi.item_id = i.item_id
            WHERE poi.po_id = $poId
        ";
        $items = exeSql($itemsSql);

        // ob_end_clean();
        echo json_encode([
            'status' => 'success',
            'data' => [
                'po' => $po,
                'items' => $items ?: []
            ]
        ]);
        exit;
    }

    // Update purchase order
    if ($action === 'update') {
        // print_r($_REQUEST);
        $poId = intval($_POST['po_id'] ?? 0);
        if ($poId <= 0) throw new Exception("Invalid PO ID for update");

        // === STEP 1: Validate main PO fields ===
        $branchId = intval($_POST['branch_id'] ?? 0);

        $vendorId = intval($_POST['vendor_id_display'] ?? 0);
        $poDate = trim($_POST['po_date'] ?? '');
        $expectedDeliveryDate = trim($_POST['expected_delivery_date'] ?? '');
        $itemLocation = trim($_POST['item_location'] ?? 'Store');
        $deliveryAddress = trim($_POST['delivery_address'] ?? '');
        $venueLocationAddress = trim($_POST['venue_location_address'] ?? '');
        $billingAddress = trim($_POST['billing_address'] ?? '');
        $totalAmount = floatval($_POST['total_amount'] ?? 0);
        $discountAmount = floatval($_POST['discount_amount'] ?? 0);
        $transportation = floatval($_POST['transportation'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');
        $indentNumber = trim($_POST['indent_number'] ?? '');

        // === Validate items exist ===
        $submittedItems = $_POST['items'] ?? [];
        if (!is_array($submittedItems) || count($submittedItems) === 0) {
            throw new Exception("At least one item is required for the purchase order");
        }

        // === STEP 2: Update purchase_orders table ===
        $updateFields = [
            "branch_id=$branchId",
            "vendor_id=$vendorId",
            "po_date='" . addslashes($poDate) . "'",
            "item_location='" . addslashes($itemLocation) . "'",
            "total_amount=$totalAmount",
            "discount_amount=$discountAmount",
            "transportation=$transportation"
        ];
        if (!empty($expectedDeliveryDate)) $updateFields[] = "expected_delivery_date='" . addslashes($expectedDeliveryDate) . "'";
        if (!empty($deliveryAddress)) $updateFields[] = "delivery_address='" . addslashes($deliveryAddress) . "'";
        if (!empty($venueLocationAddress)) $updateFields[] = "venue_location_address='" . addslashes($venueLocationAddress) . "'";
        if (!empty($billingAddress)) $updateFields[] = "billing_address='" . addslashes($billingAddress) . "'";
        if (!empty($remarks)) $updateFields[] = "remarks='" . addslashes($remarks) . "'";

        $sql = "UPDATE purchase_orders SET " . implode(', ', $updateFields) . " WHERE po_id=$poId";
        // echo $sql;
        // die();
        $res = excuteSql($sql);
        if (!$res) throw new Exception("Failed to update purchase order");

        // === STEP 3: Handle items ===
        $currentItems = exeSql("SELECT item_id FROM purchase_order_items WHERE po_id=$poId");
        $currentItemIds = array_column($currentItems, 'item_id');
        $submittedItemIds = array_map(function ($i) {
            return intval($i['item_id']);
        }, $submittedItems);

        // 3a. Delete removed items
        $itemsToDelete = array_diff($currentItemIds, $submittedItemIds);
        if (!empty($itemsToDelete)) {
            $deleteIds = implode(',', $itemsToDelete);
            excuteSql("DELETE FROM purchase_order_items WHERE po_id=$poId AND item_id IN ($deleteIds)");
        }

        // 3b. Insert new items or update existing
        foreach ($submittedItems as $item) {
            $itemId = intval($item['item_id'] ?? 0);
            $quantity = floatval($item['quantity'] ?? 0);
            $unitPrice = floatval($item['unit_price'] ?? 0);
            $discountPercentage = floatval($item['discount_percentage'] ?? 0);
            $discountAmountItem = floatval($item['discount_amount'] ?? 0);
            $taxPercentage = floatval($item['tax_percentage'] ?? 0);
            $taxType = 1; // default
            $gstSlab = 1; // default
            $taxAmount = floatval($item['tax_amount'] ?? 0);
            $subjectiveAmount = floatval($item['subjective_amount'] ?? 0);

            if (in_array($itemId, $currentItemIds)) {
                // Update existing item
                $updateItemFields = [
                    "quantity=$quantity",
                    "unit_price=$unitPrice",
                    "discount_percentage=$discountPercentage",
                    "discount_amount=$discountAmountItem",
                    "tax_type_id=$taxType",
                    "gst_slab_id=$gstSlab",
                    "tax_amount=$taxAmount",
                    "subjective_amount=$subjectiveAmount",
                    "tax_percentage=$taxPercentage"
                ];
                $sql = "UPDATE purchase_order_items SET " . implode(', ', $updateItemFields) . " WHERE po_id=$poId AND item_id=$itemId";
                excuteSql($sql);
            } else {
                // Insert new item
                $columns = ['po_id', 'item_id', 'quantity', 'unit_price', 'discount_percentage', 'discount_amount', 'tax_type_id', 'gst_slab_id', 'tax_amount', 'subjective_amount', 'tax_percentage'];
                $values = [$poId, $itemId, $quantity, $unitPrice, $discountPercentage, $discountAmountItem, $taxType, $gstSlab, $taxAmount, $subjectiveAmount, $taxPercentage];
                $valuesSql = implode(',', array_map(function ($v) {
                    return is_numeric($v) ? $v : "'" . addslashes($v) . "'";
                }, $values));
                $sql = "INSERT INTO purchase_order_items (" . implode(',', $columns) . ") VALUES ($valuesSql)";
                excuteSql($sql);
            }
        }

        // ob_end_clean();
        echo json_encode([
            'status' => 'success',
            'message' => 'Purchase Order updated successfully',
            'po_id' => $poId
        ]);
        exit;
    }


    // Server-side DataTables list
if ($action === 'list') {
    $draw   = intval($_POST['draw'] ?? 1);
    $start  = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 10);
    $searchValue = trim($_POST['search']['value'] ?? '');

    $where = "WHERE 1=1";

    // --- Filters ---
    if (!empty($_POST['status'])) {
        $status = addslashes($_POST['status']);
        $where .= " AND po.status = '$status'";
    }

    if (!empty($searchValue)) {
        $sv = addslashes($searchValue);
        $where .= " AND (po.order_number LIKE '%$sv%'
                      OR v.vendor_name LIKE '%$sv%'
                      OR b.branch_name LIKE '%$sv%')";
    }

    if (!empty($_POST['po_number'])) {
        $poNum = addslashes($_POST['po_number']);
        $where .= " AND po.order_number LIKE '%$poNum%'";
    }

    if (!empty($_POST['po_type'])) {
        $poType = addslashes($_POST['po_type']);
        $where .= " AND po.po_type = '$poType'";
    }

    if (!empty($_POST['branch_id'])) {
        $where .= " AND po.branch_id = " . intval($_POST['branch_id']);
    }

    if (!empty($_POST['vendor_id'])) {
        $where .= " AND po.vendor_id = " . intval($_POST['vendor_id']);
    }

    if (!empty($_POST['po_id'])) {
        $where .= " AND po.po_id = " . intval($_POST['po_id']);
    }

    if (!empty($_POST['start_date']) && !empty($_POST['end_date'])) {
        $sd = addslashes($_POST['start_date']);
        $ed = addslashes($_POST['end_date']);
        $where .= " AND po.po_date BETWEEN '$sd' AND '$ed'";
    } elseif (!empty($_POST['start_date'])) {
        $sd = addslashes($_POST['start_date']);
        $where .= " AND DATE(po.po_date) = '$sd'";
    }

    // --- Optimized Counting ---
    // Count filtered rows (only one count query)
    $countSql = "SELECT COUNT(*) AS total
                 FROM purchase_orders po
                 JOIN vendors v  ON po.vendor_id = v.vendor_id
                 JOIN branches b ON po.branch_id = b.branch_id
                 $where";
    $countRes = exeSql($countSql);
    $filteredRecords = $countRes[0]['total'] ?? 0;

    // Total records (only if filters exist)
    if (!empty($_POST['status']) || !empty($searchValue) || !empty($_POST['branch_id']) || !empty($_POST['vendor_id'])) {
        $totalRes = exeSql("SELECT COUNT(*) AS total FROM purchase_orders");
        $totalRecords = $totalRes[0]['total'] ?? $filteredRecords;
    } else {
        $totalRecords = $filteredRecords;
    }

    // --- Fetch Paginated Data ---
    $sql = "SELECT po.po_id, po.order_number, po.po_date, po.total_amount, po.transportation, po.discount_amount,
       v.vendor_name, b.branch_name, po.po_type, po.expected_delivery_date,
       po.status, po.grn_raised_count, po.po_edit_approval
        FROM purchase_orders po
        JOIN vendors v  ON po.vendor_id = v.vendor_id
        JOIN branches b ON po.branch_id = b.branch_id
        $where
        ORDER BY 
            CASE 
                WHEN po.status = 'PENDING' THEN 0
                ELSE 1
            END,
            po.po_id DESC
        LIMIT $start, $length;
        ";
    $data = exeSql($sql);

    // --- Status Counts ---
    $countStatusSql = "SELECT po.status, COUNT(*) AS cnt
                       FROM purchase_orders po
                       JOIN vendors v  ON po.vendor_id = v.vendor_id
                       JOIN branches b ON po.branch_id = b.branch_id
                       $where
                       GROUP BY po.status";
    $countStatusRes = exeSql($countStatusSql);

    $counts = [
        'Pending' => 0,
        'Partially Fulfilled' => 0,
        'Completed' => 0,
        'Cancelled' => 0
    ];
    foreach ($countStatusRes as $r) {
        $counts[$r['status']] = intval($r['cnt']);
    }

    // --- Output JSON ---
    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => intval($totalRecords),
        'recordsFiltered' => intval($filteredRecords),
        'data'            => $data,
        'counts'          => $counts
    ]);
    exit;
}


    // Return items by PO (supports GET/POST)
    if ($action === 'getByPO') {
        $poId = intval($_POST['po_id'] ?? ($_POST['po_id'] ?? 0));
        if ($poId <= 0) {
            throw new Exception('Invalid PO ID');
        }
        $sql = "SELECT poi.*, i.item_name FROM purchase_order_items poi INNER JOIN items i ON i.item_id = poi.item_id WHERE poi.po_id = $poId";
        // echo $sql;
        $items = exeSql($sql);

        $getBranchIdOfPo = exeSql("SELECT po.branch_id branch_id FROM purchase_orders po WHERE po.po_id = $poId")[0]['branch_id'];
        // print_r($getBranchIdOfPo);
        echo json_encode(['status' => 'success', 'data' => $items, 'branch_id' => $getBranchIdOfPo]);
        exit;
    }
    
    if ($action === 'getByVendor') {
        $vendorId = intval($_POST['vendor_id'] ?? ($_POST['vendor_id'] ?? 0));
        if ($vendorId <= 0) {
            throw new Exception('Invalid Vendor ID');
        }
        $sql = "SELECT po.* FROM purchase_orders po WHERE po.vendor_id = $vendorId";
        // echo $sql;
        $items = exeSql($sql);

        echo json_encode(['status' => 'success', 'data' => $items]);
        exit;
    }

    // Get active POs (kept as-is)
    if ($action === 'getActivePOs') {
        $vendorId = intval($_POST['vendor_id'] ?? ($_POST['vendor_id'] ?? 0));
        
        // echo "SELECT po.po_id, po.order_number, v.vendor_name, b.branch_name, po.po_date, po.total_amount
        //         FROM purchase_orders po
        //         JOIN vendors v  ON po.vendor_id = v.vendor_id
        //         JOIN branches b ON po.branch_id = b.branch_id
        //         WHERE po.status NOT IN ('Cancelled', 'Completed') and po.vendor_id = '$vendorId'
        //         ORDER BY po.po_date DESC"; 
                
        $sql = "SELECT po.po_id, po.order_number, v.vendor_name, b.branch_name, po.po_date, po.total_amount
                FROM purchase_orders po
                JOIN vendors v  ON po.vendor_id = v.vendor_id
                JOIN branches b ON po.branch_id = b.branch_id
                WHERE po.status NOT IN ('Cancelled', 'Completed') and po.vendor_id = '$vendorId'
                ORDER BY po.po_date DESC";
        
        $result = exeSql($sql);

        // ob_end_clean();
        echo json_encode([
            'status' => 'success',
            'data' => $result ?: []
        ]);
        exit;
    }

    // Get PO details (supports GET or POST)
    if ($action === 'getPODetails') {
        $poId = intval($_POST['po_id'] ?? ($_GET['po_id'] ?? 0));
        if ($poId <= 0) {
            throw new Exception('Valid PO ID is required');
        }

        $sql = "SELECT po.*, v.vendor_name, b.branch_name
                FROM purchase_orders po
                LEFT JOIN vendors v  ON po.vendor_id = v.vendor_id
                LEFT JOIN branches b ON po.branch_id = b.branch_id
                WHERE po.po_id = $poId";
        $result = exeSql($sql);

        if (!$result || empty($result)) {
            throw new Exception('PO not found');
        }

        // ob_end_clean();
        echo json_encode(['status' => 'success', 'data' => $result[0]]);
        exit;
    }

    // Get PO items (supports GET or POST)
    if ($action === 'getPOItems') {
        $poId = intval($_POST['po_id'] ?? ($_GET['po_id'] ?? 0));
        if ($poId <= 0) {
            throw new Exception('Valid PO ID is required');
        }

        $sql = "SELECT poi.*, i.item_name
                FROM purchase_order_items poi
                LEFT JOIN items i ON poi.item_id = i.item_id
                WHERE poi.po_id = $poId";
        $result = exeSql($sql);

        // ob_end_clean();
        echo json_encode(['status' => 'success', 'data' => $result]);
        exit;
    }

    if ($action === 'request_edit_approval') {
        $po_id = $_POST['po_id'] ?? null;
        $requested_by = $_POST['requested_by'] ?? null;
        $requested_date = $_POST['requested_date'] ?? null;

        if ($po_id && $requested_by && $requested_date) {

            // Check if a pending request already exists
            $existing = exeSql("SELECT * FROM po_edit_requests WHERE po_id = '$po_id' AND status = 'Pending'");
            if ($existing) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'A pending edit request already exists for this PO.'
                ]);
                exit;
            }

            // Insert new request
            $req = excuteSql("INSERT INTO po_edit_requests (requested_by, request_date, po_id, status) 
            VALUES ('$requested_by', '$requested_date', '$po_id', 'Pending')");

            if ($req) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to create request']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
        }
        exit;
    }
    
    // Add this new action before the final throw Exception
if ($action === 'getLastPurchasePrice') {
    $itemId = intval($_POST['item_id'] ?? 0);
    $vendorId = intval($_POST['vendor_id'] ?? 0);
    
    if ($itemId <= 0 || $vendorId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid item or vendor ID']);
        exit;
    }
    
    // Get the last purchase price for this item from this vendor
    $sql = "SELECT poi.unit_price, poi.tax_percentage, po.po_date
            FROM purchase_order_items poi
            INNER JOIN purchase_orders po ON poi.po_id = po.po_id
            WHERE poi.item_id = $itemId 
            AND po.vendor_id = $vendorId
            AND poi.unit_price > 0
            ORDER BY po.po_date DESC, po.po_id DESC
            LIMIT 1";
    
    $result = exeSql($sql);
    
    if ($result && count($result) > 0) {
        // ob_end_clean();
        echo json_encode([
            'status' => 'success',
            'data' => [
                'unit_price' => $result[0]['unit_price'],
                'tax_percentage' => $result[0]['tax_percentage'],
                'po_date' => $result[0]['po_date']
            ]
        ]);
    } else {
        // ob_end_clean();
        echo json_encode([
            'status' => 'success',
            'data' => null,
            'message' => 'No previous purchase found'
        ]);
    }
    exit;
}

if ($action === 'delete') {
    $poId = intval($_POST['po_id'] ?? 0);
    if ($poId <= 0) {
        throw new Exception("Invalid PO ID");
    }

    // Soft delete: Update status instead of removing record
    $sql = "UPDATE purchase_orders SET status='Cancelled' WHERE po_id=$poId";
    $res = excuteSql($sql);
    
    $sql1 = "UPDATE goods_receipts SET status='Cancelled' WHERE po_id=$poId";
    $res1 = excuteSql($sql1);

    if (!$res || $res1) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to cancel the purchase order'
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Purchase Order cancelled successfully'
    ]);
    exit;
}


    throw new Exception('Invalid or missing action parameter: ' . $action);
} catch (Exception $e) {
    // ob_end_clean();

    $errorMessage = $e->getMessage();
    // error_log('API Error: ' . $errorMessage);
    // error_log('Stack trace: ' . $e->getTraceAsString());

    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $errorMessage,
        'code' => 'PO-1001',
        'debug_info' => [
            'received_post' => $_POST,
            'received_get' => $_GET,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}
