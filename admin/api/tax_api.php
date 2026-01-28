<?php
require_once("../../functions.php");
header('Content-Type: application/json');

// Fetch tax types
try {
    $tax_types = exeSql("SELECT tax_type_id, tax_type_name FROM tax_types ORDER BY tax_type_name ASC");
} catch (\PDOException $e) {
    $tax_types = [];
}

// Fetch GST slabs
try {
    $gst_slabs = exeSql("SELECT gst_slab_id, rate, tax_type_id, cgst, sgst, igst FROM gst_slabs ORDER BY gst_slab_id ASC");
} catch (\PDOException $e) {
    $gst_slabs = [];
}

// Return JSON
echo json_encode([
    'status' => 'success',
    'data' => [
        'tax_types' => $tax_types,
        'gst_slabs' => $gst_slabs
    ]
]);
