<?php
session_start();
require_once('../../functions.php');

set_time_limit(300);
ini_set('memory_limit', '512M');

try {
    $exportType = $_GET['type'] ?? 'excel';
    $dateFrom = trim($_GET['date_from'] ?? '');
    $dateTo = trim($_GET['date_to'] ?? '');
    $categoryId = intval($_GET['category_id'] ?? 0);
    $itemName = trim($_GET['item_name'] ?? '');
    $vendorId = intval($_GET['vendor_id'] ?? 0);
    $branchId = intval($_GET['branch_id'] ?? 0);
    $sortBy = trim($_GET['sort_by'] ?? 'total_spent');

    // Build WHERE clause
    $whereClauses = [];
    
    if ($dateFrom && $dateTo) {
        $whereClauses[] = "gr.grn_date BETWEEN '$dateFrom' AND '$dateTo'";
    } elseif ($dateFrom) {
        $whereClauses[] = "gr.grn_date >= '$dateFrom'";
    } elseif ($dateTo) {
        $whereClauses[] = "gr.grn_date <= '$dateTo'";
    }
    
    if ($categoryId > 0) {
        $whereClauses[] = "i.category_id = $categoryId";
    }
    
    if ($itemName !== '') {
        $whereClauses[] = "i.item_name LIKE '%" . addslashes($itemName) . "%'";
    }
    
    if ($vendorId > 0) {
        $whereClauses[] = "gr.vendor_id = $vendorId";
    }
    
    if ($branchId > 0) {
        $whereClauses[] = "gri.branch_id = $branchId";
    }

    $where = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    // Sorting
    $orderBy = match($sortBy) {
        'quantity' => 'net_quantity DESC',
        'item_name' => 'i.item_name ASC',
        default => 'total_spent DESC'
    };

    // Fetch data
    $sql = "
    SELECT 
        i.item_id,
        i.item_name,
        COALESCE(ic.category_name, 'Uncategorized') AS category_name,
        COALESCE(SUM(gri.qty_received), 0) AS total_qty_purchased,
        COALESCE(SUM(grti.return_qty), 0) AS total_qty_returned,
        (COALESCE(SUM(gri.qty_received), 0) - COALESCE(SUM(grti.return_qty), 0)) AS net_quantity,
        
        CASE 
            WHEN SUM(gri.qty_received) > 0 THEN
                SUM((gri.unit_price * (gri.qty_received - COALESCE(grti.return_qty, 0))) / gri.qty_received * gri.qty_received)
                / SUM(gri.qty_received - COALESCE(grti.return_qty, 0))
            ELSE 0
        END AS avg_unit_price,
        
        SUM(gri.unit_price * (gri.qty_received - COALESCE(grti.return_qty, 0))) AS total_before_discount,
        
        SUM((gri.discount_amount * (gri.qty_received - COALESCE(grti.return_qty, 0))) / gri.qty_received) AS total_discount,
        
        SUM((gri.tax_amount * (gri.qty_received - COALESCE(grti.return_qty, 0))) / gri.qty_received) AS total_gst,
        
        SUM(
            ((gri.unit_price * (gri.qty_received - COALESCE(grti.return_qty, 0))) - 
             ((gri.discount_amount * (gri.qty_received - COALESCE(grti.return_qty, 0))) / gri.qty_received)) +
            ((gri.tax_amount * (gri.qty_received - COALESCE(grti.return_qty, 0))) / gri.qty_received)
        ) AS total_spent,
        
        MAX(gr.grn_date) AS last_purchase_date
        
    FROM items i
    LEFT JOIN item_categories ic ON i.category_id = ic.category_id
    LEFT JOIN goods_receipt_items gri ON gri.item_id = i.item_id
    LEFT JOIN goods_receipts gr ON gr.grn_id = gri.grn_id
    LEFT JOIN (
        SELECT grn_item_id, SUM(return_qty) AS return_qty
        FROM goods_return_items
        GROUP BY grn_item_id
    ) grti ON grti.grn_item_id = gri.grn_item_id
    $where
    GROUP BY i.item_id
    HAVING net_quantity > 0
    ORDER BY $orderBy
    ";

    $data = exeSql($sql);

    if (!is_array($data) || count($data) === 0) {
        die('No data found for export');
    }

    // Calculate totals
    $totals = [
        'total_qty_purchased' => 0,
        'total_qty_returned' => 0,
        'net_quantity' => 0,
        'total_before_discount' => 0,
        'total_discount' => 0,
        'total_gst' => 0,
        'total_spent' => 0
    ];

    foreach ($data as $row) {
        $totals['total_qty_purchased'] += floatval($row['total_qty_purchased']);
        $totals['total_qty_returned'] += floatval($row['total_qty_returned']);
        $totals['net_quantity'] += floatval($row['net_quantity']);
        $totals['total_before_discount'] += floatval($row['total_before_discount']);
        $totals['total_discount'] += floatval($row['total_discount']);
        $totals['total_gst'] += floatval($row['total_gst']);
        $totals['total_spent'] += floatval($row['total_spent']);
    }

    // ==================== EXCEL EXPORT ====================
    if ($exportType === 'excel') {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="GRN_ItemWise_Report_' . date('Y-m-d_His') . '.xlsx"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo '<?xml version="1.0" encoding="UTF-8"?>
        <?mso-application progid="Excel.Sheet"?>
        <Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
         xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
         <Styles>
          <Style ss:ID="header">
           <Font ss:Bold="1" ss:Color="#FFFFFF"/>
           <Interior ss:Color="#17a2b8" ss:Pattern="Solid"/>
           <Alignment ss:Horizontal="Center"/>
           <Borders>
            <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
           </Borders>
          </Style>
          <Style ss:ID="number">
           <NumberFormat ss:Format="#,##0.00"/>
           <Alignment ss:Horizontal="Right"/>
           <Borders>
            <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
           </Borders>
          </Style>
          <Style ss:ID="text">
           <Alignment ss:Horizontal="Left"/>
           <Borders>
            <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
           </Borders>
          </Style>
          <Style ss:ID="total">
           <Font ss:Bold="1"/>
           <NumberFormat ss:Format="#,##0.00"/>
           <Interior ss:Color="#e9ecef" ss:Pattern="Solid"/>
           <Alignment ss:Horizontal="Right"/>
           <Borders>
            <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2"/>
            <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
            <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2"/>
           </Borders>
          </Style>
         </Styles>
         <Worksheet ss:Name="Item-wise Report">
          <Table>
           <Column ss:Width="40"/>
           <Column ss:Width="200"/>
           <Column ss:Width="120"/>
           <Column ss:Width="100"/>
           <Column ss:Width="100"/>
           <Column ss:Width="100"/>
           <Column ss:Width="100"/>
           <Column ss:Width="120"/>
           <Column ss:Width="100"/>
           <Column ss:Width="100"/>
           <Column ss:Width="120"/>
           <Column ss:Width="100"/>
           <Row>
            <Cell ss:StyleID="header"><Data ss:Type="String">S.No</Data></Cell>
            <Cell ss:StyleID="header"><Data ss:Type="String">Item Name</Data></Cell>
            <Cell ss:StyleID="header"><Data ss:Type="String">Category</Data></Cell>
            <Cell ss:StyleID="header"><Data ss:Type="String">Total Qty Purchased</Data></Cell>
            <Cell ss:StyleID="header"><Data ss:Type="String">Total Qty Returned</Data></Cell>
            <Cell ss:StyleID="header"><Data ss:Type="String">Net Quantity</Data></Cell>
            <Cell ss:StyleID="header"><Data ss:Type="String">Avg Unit Price</Data></Cell>
            <Cell ss:StyleID="header"><Data ss:Type="String">Total (Before Discount)</Data></Cell>
            <Cell ss:StyleID="header"><Data ss:Type="String">Total Discount</Data></Cell>
            <Cell ss:StyleID="header"><Data ss:Type="String">Total GST</Data></Cell>
            <Cell ss:StyleID="header"><Data ss:Type="String">Total Spent (Net)</Data></Cell>
            <Cell ss:StyleID="header"><Data ss:Type="String">Last Purchase Date</Data></Cell>
           </Row>';
        
        $sn = 1;
        foreach ($data as $row) {
            echo '<Row>
             <Cell ss:StyleID="text"><Data ss:Type="Number">' . $sn++ . '</Data></Cell>
             <Cell ss:StyleID="text"><Data ss:Type="String">' . htmlspecialchars($row['item_name']) . '</Data></Cell>
             <Cell ss:StyleID="text"><Data ss:Type="String">' . htmlspecialchars($row['category_name']) . '</Data></Cell>
             <Cell ss:StyleID="number"><Data ss:Type="Number">' . $row['total_qty_purchased'] . '</Data></Cell>
             <Cell ss:StyleID="number"><Data ss:Type="Number">' . $row['total_qty_returned'] . '</Data></Cell>
             <Cell ss:StyleID="number"><Data ss:Type="Number">' . $row['net_quantity'] . '</Data></Cell>
             <Cell ss:StyleID="number"><Data ss:Type="Number">' . $row['avg_unit_price'] . '</Data></Cell>
             <Cell ss:StyleID="number"><Data ss:Type="Number">' . $row['total_before_discount'] . '</Data></Cell>
             <Cell ss:StyleID="number"><Data ss:Type="Number">' . $row['total_discount'] . '</Data></Cell>
             <Cell ss:StyleID="number"><Data ss:Type="Number">' . $row['total_gst'] . '</Data></Cell>
             <Cell ss:StyleID="number"><Data ss:Type="Number">' . $row['total_spent'] . '</Data></Cell>
             <Cell ss:StyleID="text"><Data ss:Type="String">' . htmlspecialchars($row['last_purchase_date']) . '</Data></Cell>
            </Row>';
        }
        
        echo '<Row>
             <Cell ss:StyleID="total" ss:MergeAcross="2"><Data ss:Type="String">GRAND TOTALS</Data></Cell>
             <Cell ss:StyleID="total"><Data ss:Type="Number">' . $totals['total_qty_purchased'] . '</Data></Cell>
             <Cell ss:StyleID="total"><Data ss:Type="Number">' . $totals['total_qty_returned'] . '</Data></Cell>
             <Cell ss:StyleID="total"><Data ss:Type="Number">' . $totals['net_quantity'] . '</Data></Cell>
             <Cell ss:StyleID="total"><Data ss:Type="String"></Data></Cell>
             <Cell ss:StyleID="total"><Data ss:Type="Number">' . $totals['total_before_discount'] . '</Data></Cell>
             <Cell ss:StyleID="total"><Data ss:Type="Number">' . $totals['total_discount'] . '</Data></Cell>
             <Cell ss:StyleID="total"><Data ss:Type="Number">' . $totals['total_gst'] . '</Data></Cell>
             <Cell ss:StyleID="total"><Data ss:Type="Number">' . $totals['total_spent'] . '</Data></Cell>
             <Cell ss:StyleID="total"><Data ss:Type="String"></Data></Cell>
            </Row>
          </Table>
         </Worksheet>
        </Workbook>';
        exit;
    }

    // ==================== PDF/PRINT EXPORT ====================
    if ($exportType === 'pdf' || $exportType === 'print') {
        header('Content-Type: text/html; charset=UTF-8');
        
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Item-wise GRN Report</title>
            <style>
                @page { margin: 1cm; size: A4 landscape; }
                body { font-family: Arial, sans-serif; margin: 0; font-size: 9px; }
                h2 { color: #17a2b8; margin: 10px 0; font-size: 16px; }
                table { border-collapse: collapse; width: 100%; margin-top: 10px; }
                th { background-color: #17a2b8; color: white; font-weight: bold; padding: 6px 4px; border: 1px solid #000; font-size: 9px; }
                td { padding: 4px; border: 1px solid #000; font-size: 8px; }
                .number { text-align: right; }
                .total-row { background-color: #e9ecef; font-weight: bold; border-top: 2px solid #000; }
                .print-btn { position: fixed; top: 10px; right: 10px; background: #17a2b8; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; z-index: 1000; }
                @media print { .print-btn { display: none; } }
            </style>
            <script>
                function printPage() {
                    document.querySelector(".print-btn").style.display = "none";
                    setTimeout(() => window.print(), 100);
                }
            </script>
        </head>
        <body>
            <button class="print-btn" onclick="printPage()">🖨️ Print</button>
            <h2>Item-wise GRN Report</h2>
            <div style="margin-bottom: 10px; color: #666;">
                <strong>Generated:</strong> ' . date('Y-m-d H:i:s') . ' | 
                <strong>Records:</strong> ' . count($data) . '
            </div>
            <table>
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Purchased</th>
                        <th>Returned</th>
                        <th>Net Qty</th>
                        <th>Avg Price</th>
                        <th>Before Disc</th>
                        <th>Discount</th>
                        <th>GST</th>
                        <th>Total Spent</th>
                        <th>Last Date</th>
                    </tr>
                </thead>
                <tbody>';
        
        $sn = 1;
        foreach ($data as $row) {
            echo '<tr>
                <td>' . $sn++ . '</td>
                <td>' . htmlspecialchars($row['item_name']) . '</td>
                <td>' . htmlspecialchars($row['category_name']) . '</td>
                <td class="number">' . number_format($row['total_qty_purchased'], 2) . '</td>
                <td class="number">' . number_format($row['total_qty_returned'], 2) . '</td>
                <td class="number">' . number_format($row['net_quantity'], 2) . '</td>
                <td class="number">₹' . number_format($row['avg_unit_price'], 2) . '</td>
                <td class="number">₹' . number_format($row['total_before_discount'], 2) . '</td>
                <td class="number">₹' . number_format($row['total_discount'], 2) . '</td>
                <td class="number">₹' . number_format($row['total_gst'], 2) . '</td>
                <td class="number">₹' . number_format($row['total_spent'], 2) . '</td>
                <td>' . htmlspecialchars($row['last_purchase_date']) . '</td>
            </tr>';
        }
        
        echo '</tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;"><strong>TOTALS:</strong></td>
                        <td class="number"><strong>' . number_format($totals['total_qty_purchased'], 2) . '</strong></td>
                        <td class="number"><strong>' . number_format($totals['total_qty_returned'], 2) . '</strong></td>
                        <td class="number"><strong>' . number_format($totals['net_quantity'], 2) . '</strong></td>
                        <td></td>
                        <td class="number"><strong>₹' . number_format($totals['total_before_discount'], 2) . '</strong></td>
                        <td class="number"><strong>₹' . number_format($totals['total_discount'], 2) . '</strong></td>
                        <td class="number"><strong>₹' . number_format($totals['total_gst'], 2) . '</strong></td>
                        <td class="number"><strong>₹' . number_format($totals['total_spent'], 2) . '</strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </body>
        </html>';
        exit;
    }

} catch (Exception $e) {
    error_log('Item-wise Export Error: ' . $e->getMessage());
    die('Export failed: ' . $e->getMessage());
}
?>