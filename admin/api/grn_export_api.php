<?php
session_start();
require_once('../../functions.php');

set_time_limit(300);
ini_set('memory_limit', '512M');

try {
    $exportType = $_GET['type'] ?? 'excel';
    $poNumber = trim($_GET['po_number'] ?? '');
    $grnNumber = trim($_GET['grn_number'] ?? '');
    $branchId = intval($_GET['branch_id'] ?? 0);
    $vendorId = intval($_GET['vendor_id'] ?? 0);
    $invoiceNumber = trim($_GET['invoice_number'] ?? '');
    $startDate = trim($_GET['start_date'] ?? '');
    $endDate = trim($_GET['end_date'] ?? '');
    $invoiceFrom = trim($_GET['invoice_from'] ?? '');
    $invoiceTo = trim($_GET['invoice_to'] ?? '');
    $printLimit = intval($_GET['limit'] ?? 0);

    // Build WHERE clause
    $whereClauses = [];
    if ($grnNumber !== '') $whereClauses[] = "gr.grn_number LIKE '%" . addslashes($grnNumber) . "%'";
    if ($invoiceNumber !== '') $whereClauses[] = "gr.invoice_number LIKE '%" . addslashes($invoiceNumber) . "%'";
    if ($poNumber !== '') $whereClauses[] = "po.order_number LIKE '%" . addslashes($poNumber) . "%'";
    if ($branchId > 0) $whereClauses[] = "po.branch_id = $branchId";
    if ($vendorId > 0) $whereClauses[] = "gr.vendor_id = $vendorId";
    if ($startDate && $endDate) $whereClauses[] = "gr.grn_date BETWEEN '$startDate' AND '$endDate'";
    elseif ($startDate) $whereClauses[] = "DATE(gr.grn_date) = '$startDate'";
    elseif ($endDate) $whereClauses[] = "DATE(gr.grn_date) <= '$endDate'";
    if ($invoiceFrom && $invoiceTo) $whereClauses[] = "gr.invoice_date BETWEEN '$invoiceFrom' AND '$invoiceTo'";
    elseif ($invoiceFrom) $whereClauses[] = "DATE(gr.invoice_date) = '$invoiceFrom'";
    elseif ($invoiceTo) $whereClauses[] = "DATE(gr.invoice_date) <= '$invoiceTo'";

    $where = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    // Fetch GRN data with items (excluding returned items)
    $sql = "
    SELECT
        gr.grn_id,
        gr.grn_number,
        gr.grn_date,
        v.vendor_name,
        b.branch_name,
        po.order_number AS po_number,
        gr.invoice_number,
        gr.invoice_date,
        gri.item_id,
        i.item_name,
        gri.qty_received,
        COALESCE(SUM(grti.return_qty), 0) AS qty_returned,
        (gri.qty_received - COALESCE(SUM(grti.return_qty), 0)) AS balance_qty,
        gri.unit_price AS basic_value,
        COALESCE(gri.tax_percentage, 0) AS gst_percentage,
        (COALESCE(gri.tax_amount, 0)-COALESCE(grti.tax_amount, 0)) AS gst_amount,
        ((gri.unit_price * (gri.qty_received - COALESCE(SUM(grti.return_qty), 0)))+(COALESCE(gri.tax_amount, 0)-COALESCE(grti.tax_amount, 0))) AS total_invoice_value,
        0 AS less_tds,
         ((gri.unit_price * (gri.qty_received - COALESCE(SUM(grti.return_qty), 0)))+(COALESCE(gri.tax_amount, 0)-COALESCE(grti.tax_amount, 0))) AS total_invoice_value_after_tds
    FROM goods_receipts gr
    LEFT JOIN vendors v ON gr.vendor_id = v.vendor_id
    LEFT JOIN purchase_orders po ON gr.po_id = po.po_id
    LEFT JOIN branches b ON po.branch_id = b.branch_id
    LEFT JOIN goods_receipt_items gri ON gri.grn_id = gr.grn_id
    LEFT JOIN items i ON i.item_id = gri.item_id
    LEFT JOIN goods_return_items grti ON grti.grn_item_id = gri.grn_item_id
    $where
    GROUP BY gr.grn_id, gri.grn_item_id
    HAVING balance_qty > 0
    ORDER BY gr.grn_date, gr.grn_id, i.item_name
    ";
    
    if ($exportType === 'print_json' && $printLimit > 0) {
        $sql .= " LIMIT $printLimit";
    }

    $data = exeSql($sql);
    
    if (!is_array($data)) $data = [];

    if (count($data) === 0) {
        if ($exportType === 'print_json') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No data found']);
            exit;
        }
        die('No data found for export with current filters.');
    }

    // ==================== PRINT JSON ====================
    if ($exportType === 'print_json') {
        header('Content-Type: application/json');
        
        $totals = [
            'basic_value' => 0,
            'gst_amount' => 0,
            'total_invoice_value' => 0,
            'less_tds' => 0,
            'total_invoice_value_after_tds' => 0
        ];
        
        foreach ($data as $row) {
            $totals['basic_value'] += floatval($row['basic_value'] * $row['balance_qty']);
            $totals['gst_amount'] += floatval($row['gst_amount']);
            $totals['total_invoice_value'] += floatval($row['total_invoice_value']);
            $totals['less_tds'] += floatval($row['less_tds']);
            $totals['total_invoice_value_after_tds'] += floatval($row['total_invoice_value_after_tds']);
        }
        
        echo json_encode([
            'success' => true,
            'records' => $data,
            'totals' => $totals,
            'count' => count($data)
        ]);
        exit;
    }

    // ==================== EXCEL EXPORT ====================
    if ($exportType === 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="GRN_Report_' . date('Y-m-d_His') . '.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo '<?xml version="1.0" encoding="UTF-8"?>
        <?mso-application progid="Excel.Sheet"?>
        <Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
         xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
         <Styles>
          <Style ss:ID="header">
           <Font ss:Bold="1" ss:Color="#FFFFFF"/>
           <Interior ss:Color="#28a745" ss:Pattern="Solid"/>
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
         <Worksheet ss:Name="GRN Report">
          <Table>
           <Column ss:Width="40"/>
           <Column ss:Width="150"/>
           <Column ss:Width="120"/>
           <Column ss:Width="80"/>
           <Column ss:Width="100"/>
           <Column ss:Width="90"/>
           <Column ss:Width="70"/>
           <Column ss:Width="70"/>
           <Column ss:Width="70"/>
           <Column ss:Width="90"/>
           <Column ss:Width="90"/>
           <Column ss:Width="90"/>
           <Column ss:Width="80"/>
           <Row>
            <Cell ss:StyleID="header"><Data ss:Type="String">S.N</Data></Cell>
            <Cell ss:StyleID="header"><Data ss:Type="String">Name of the Vendors</Data></Cell>
            <Cell ss:StyleID="header"><Data ss:Type="String">Item Description</Data></Cell>
            <Cell ss:StyleID="header"><Data ss:Type="String">Invoice Date</Data></Cell>
            <Cell ss:StyleID="header"><Data ss:Type="String">Invoice No</Data></Cell>
            <Cell ss:StyleID="header"><Data ss:Type="String">Qty (After Returns)</Data></Cell>
            <Cell ss:StyleID="header"><Data ss:Type="String">Basic Value</Data></Cell>
            <Cell ss:StyleID="header"><Data ss:Type="String">GST@ %</Data></Cell>
            <Cell ss:StyleID="header"><Data ss:Type="String">IGST@ %</Data></Cell>
            <Cell ss:StyleID="header"><Data ss:Type="String">GST Amount</Data></Cell>
            <Cell ss:StyleID="header"><Data ss:Type="String">Total Invoice Value</Data></Cell>
            <Cell ss:StyleID="header"><Data ss:Type="String">Less TDS</Data></Cell>
            <Cell ss:StyleID="header"><Data ss:Type="String">Total Invoice Value</Data></Cell>
           </Row>';
        
        $sn = 1;
        $totals = [
            'basic_value' => 0,
            'gst_amount' => 0,
            'total_invoice_value' => 0,
            'less_tds' => 0,
            'total_invoice_value_after_tds' => 0
        ];
        
        foreach ($data as $row) {
            $basicValue = floatval($row['basic_value']) * floatval($row['balance_qty']);
            $gstAmount = floatval($row['gst_amount']);
            $totalInvoice = floatval($row['total_invoice_value']);
            $lessTds = floatval($row['less_tds']);
            $totalAfterTds = floatval($row['total_invoice_value_after_tds']);
            
            $totals['basic_value'] += $basicValue;
            $totals['gst_amount'] += $gstAmount;
            $totals['total_invoice_value'] += $totalInvoice;
            $totals['less_tds'] += $lessTds;
            $totals['total_invoice_value_after_tds'] += $totalAfterTds;
            
            $gstPct = floatval($row['gst_percentage']);
            $igstPct = 0; // Set based on your logic
            
            echo '<Row>
             <Cell ss:StyleID="text"><Data ss:Type="Number">' . $sn++ . '</Data></Cell>
             <Cell ss:StyleID="text"><Data ss:Type="String">' . htmlspecialchars($row['vendor_name']) . '</Data></Cell>
             <Cell ss:StyleID="text"><Data ss:Type="String">' . htmlspecialchars($row['item_name']) . '</Data></Cell>
             <Cell ss:StyleID="text"><Data ss:Type="String">' . htmlspecialchars($row['invoice_date']) . '</Data></Cell>
             <Cell ss:StyleID="text"><Data ss:Type="String">' . htmlspecialchars($row['invoice_number']) . '</Data></Cell>
             <Cell ss:StyleID="number"><Data ss:Type="Number">' . $row['balance_qty'] . '</Data></Cell>
             <Cell ss:StyleID="number"><Data ss:Type="Number">' . $basicValue . '</Data></Cell>
             <Cell ss:StyleID="number"><Data ss:Type="Number">' . $gstPct . '</Data></Cell>
             <Cell ss:StyleID="number"><Data ss:Type="Number">' . $igstPct . '</Data></Cell>
             <Cell ss:StyleID="number"><Data ss:Type="Number">' . $gstAmount . '</Data></Cell>
             <Cell ss:StyleID="number"><Data ss:Type="Number">' . $totalInvoice . '</Data></Cell>
             <Cell ss:StyleID="number"><Data ss:Type="Number">' . $lessTds . '</Data></Cell>
             <Cell ss:StyleID="number"><Data ss:Type="Number">' . $totalAfterTds . '</Data></Cell>
            </Row>';
        }
        
        echo '<Row>
             <Cell ss:StyleID="total" ss:MergeAcross="5"><Data ss:Type="String">GRAND TOTALS</Data></Cell>
             <Cell ss:StyleID="total"><Data ss:Type="Number">' . $totals['basic_value'] . '</Data></Cell>
             <Cell ss:StyleID="total"><Data ss:Type="String"></Data></Cell>
             <Cell ss:StyleID="total"><Data ss:Type="String"></Data></Cell>
             <Cell ss:StyleID="total"><Data ss:Type="Number">' . $totals['gst_amount'] . '</Data></Cell>
             <Cell ss:StyleID="total"><Data ss:Type="Number">' . $totals['total_invoice_value'] . '</Data></Cell>
             <Cell ss:StyleID="total"><Data ss:Type="Number">' . $totals['less_tds'] . '</Data></Cell>
             <Cell ss:StyleID="total"><Data ss:Type="Number">' . $totals['total_invoice_value_after_tds'] . '</Data></Cell>
            </Row>
          </Table>
         </Worksheet>
        </Workbook>';
        exit;
    }

    // ==================== PDF EXPORT ====================
    if ($exportType === 'pdf') {
        header('Content-Type: text/html; charset=UTF-8');
        
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>GRN Report PDF</title>
            <style>
                @page { margin: 1cm; size: A4 landscape; }
                body { font-family: Arial, sans-serif; margin: 0; font-size: 9px; }
                h2 { color: #28a745; margin: 10px 0; font-size: 16px; }
                table { border-collapse: collapse; width: 100%; margin-top: 10px; }
                th { background-color: #28a745; color: white; font-weight: bold; padding: 6px 4px; border: 1px solid #000; font-size: 9px; }
                td { padding: 4px; border: 1px solid #000; font-size: 8px; }
                .number { text-align: right; }
                .total-row { background-color: #e9ecef; font-weight: bold; border-top: 2px solid #000; }
                .pdf-btn { position: fixed; top: 10px; right: 10px; background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; z-index: 1000; }
                @media print { .pdf-btn { display: none; } }
            </style>
            <script>
                function printPDF() {
                    document.querySelector(".pdf-btn").style.display = "none";
                    setTimeout(() => window.print(), 100);
                }
            </script>
        </head>
        <body>
            <button class="pdf-btn" onclick="printPDF()">🖨️ Print to PDF</button>
            <h2>Goods Received Notes (GRN) Report</h2>
            <div style="margin-bottom: 10px; color: #666;">
                <strong>Generated:</strong> ' . date('Y-m-d H:i:s') . ' | <strong>Records:</strong> ' . count($data) . '
            </div>
            <table>
                <thead>
                    <tr>
                        <th>S.N</th>
                        <th>Vendor</th>
                        <th>Item</th>
                        <th>Invoice Date</th>
                        <th>Invoice No</th>
                        <th>Qty</th>
                        <th>Basic Value</th>
                        <th>GST %</th>
                        <th>GST Amt</th>
                        <th>Invoice Value</th>
                        <th>Less TDS</th>
                        <th>Total Value</th>
                    </tr>
                </thead>
                <tbody>';
        
        $sn = 1;
        $totals = [
            'basic_value' => 0,
            'gst_amount' => 0,
            'total_invoice_value' => 0,
            'less_tds' => 0,
            'total_invoice_value_after_tds' => 0
        ];
        
        foreach ($data as $row) {
            $basicValue = floatval($row['basic_value']) * floatval($row['balance_qty']);
            $gstAmount = floatval($row['gst_amount']);
            $totalInvoice = floatval($row['total_invoice_value']);
            $lessTds = floatval($row['less_tds']);
            $totalAfterTds = floatval($row['total_invoice_value_after_tds']);
            
            $totals['basic_value'] += $basicValue;
            $totals['gst_amount'] += $gstAmount;
            $totals['total_invoice_value'] += $totalInvoice;
            $totals['less_tds'] += $lessTds;
            $totals['total_invoice_value_after_tds'] += $totalAfterTds;
            
            echo '<tr>
                <td>' . $sn++ . '</td>
                <td>' . htmlspecialchars(substr($row['vendor_name'], 0, 25)) . '</td>
                <td>' . htmlspecialchars(substr($row['item_name'], 0, 20)) . '</td>
                <td>' . htmlspecialchars($row['invoice_date']) . '</td>
                <td>' . htmlspecialchars($row['invoice_number']) . '</td>
                <td class="number">' . $row['balance_qty'] . '</td>
                <td class="number">₹' . number_format($basicValue, 2) . '</td>
                <td class="number">' . $row['gst_percentage'] . '%</td>
                <td class="number">₹' . number_format($gstAmount, 2) . '</td>
                <td class="number">₹' . number_format($totalInvoice, 2) . '</td>
                <td class="number">₹' . number_format($lessTds, 2) . '</td>
                <td class="number">₹' . number_format($totalAfterTds, 2) . '</td>
            </tr>';
        }
        
        echo '</tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="6" style="text-align: right;"><strong>TOTALS:</strong></td>
                        <td class="number"><strong>₹' . number_format($totals['basic_value'], 2) . '</strong></td>
                        <td></td>
                        <td class="number"><strong>₹' . number_format($totals['gst_amount'], 2) . '</strong></td>
                        <td class="number"><strong>₹' . number_format($totals['total_invoice_value'], 2) . '</strong></td>
                        <td class="number"><strong>₹' . number_format($totals['less_tds'], 2) . '</strong></td>
                        <td class="number"><strong>₹' . number_format($totals['total_invoice_value_after_tds'], 2) . '</strong></td>
                    </tr>
                </tfoot>
            </table>
        </body>
        </html>';
        exit;
    }

} catch (Exception $e) {
    error_log('Export Error: ' . $e->getMessage());
    if (isset($_GET['type']) && $_GET['type'] === 'print_json') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        die('Export failed: ' . $e->getMessage());
    }
}
?>