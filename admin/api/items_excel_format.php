<?php
require '../../functions.php';
require '../../vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


// Fetch all categories and subcategories
$categories = $dbObj->selectData('categories', 1, ['category_id', 'category_name']);

$sheetData = [];
foreach ($categories as $cat) {
    $subcats = $dbObj->selectData('subcategories', ['category_id' => $cat['category_id']], ['subcategory_name']);
    if (!empty($subcats)) {
        foreach ($subcats as $sub) {
            $sheetData[] = [
                $cat['category_name'],
                $sub['subcategory_name'],
                '', // Item Name (user fills)
                '', // Item Code (user fills)
                '', // UOM (user fills)
                ''  // Tax Percentage (user fills)
            ];
        }
    } else {
        // Category has no subcategory
        $sheetData[] = [
            $cat['category_name'],
            '', '', '', '', ''
        ];
    }
}

// Create new spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set headers
$headers = ['Category Name', 'Subcategory Name', 'Item Name', 'Item Code', 'UOM', 'Tax Percentage'];
$sheet->fromArray($headers, NULL, 'A1');

// Add all categories/subcategories
$sheet->fromArray($sheetData, NULL, 'A2');

// Auto-size columns
foreach(range('A','F') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// File name
$filename = "Inventory_Upload_Template.xlsx";

// Send to browser
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
