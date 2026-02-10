<?php
/**
 * FINANCE: Global Header & Utilities
 * UPDATED: Added FontAwesome and advanced table utilities.
 */
if (!function_exists('h')) { 
    function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } 
}
if (!function_exists('i')) { function i($x) { return is_numeric($x) ? (int)$x : 0; } }
if (!function_exists('s')) { function s($x) { return trim((string)($x ?? '')); } }

function nf($n) { return number_format((float)$n, 2, '.', ''); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance | KMK Admin Portal</title>
    
    <!-- CSS Frameworks -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.10/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root { --kmk-primary: #0d6efd; --kmk-bg: #f6f8fb; }
        body { background-color: var(--kmk-bg); font-family: 'Inter', sans-serif; padding-top: 80px; }
        .card { border-radius: 16px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
        .btn { border-radius: 10px; font-weight: 600; }
        .form-control, .form-select { border-radius: 10px; border: 1px solid #e5e7eb; padding: 10px 15px; }
        
        .table thead th { 
            background: #fdfdfd; text-transform: uppercase; font-size: 0.7rem; 
            letter-spacing: 0.05em; color: #64748b; font-weight: 700; border-bottom: 2px solid #f1f3f5;
        }
        .badge-soft-success { background: #dcfce7; color: #15803d; }
        .badge-soft-danger { background: #fee2e2; color: #b91c1c; }
        .badge-soft-warning { background: #fef3c7; color: #92400e; }
        
        /* Select2 Overrides */
        .select2-container--bootstrap4 .select2-selection--single {
            height: 46px !important; border-radius: 10px !important;
            border: 1px solid #e5e7eb !important; display: flex; align-items: center;
        }
        .bg-light-blue { background-color: #f0f7ff !important; }
    </style>
</head>