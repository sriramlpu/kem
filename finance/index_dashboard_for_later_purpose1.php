<?php
// /kem/finance/index.php
// Master Operations Dashboard

// Assuming functions.php defines exeSql() or similar database execution helper
require_once __DIR__ . '/../functions.php';

// --- VENDOR CODE INTEGRATION START ---

/* -------------------------------------------------------------------------
 * CONFIGURATION & CONNECTION (REPLACE THESE WITH YOUR DETAILS)
 * ----------------------------------------------------------------------- */
// NOTE: These credentials must be correct for the Vendors tab to function.
$db_config = [
    'host' => 'localhost', // Typically 'localhost'
    'username' => 'kmkglobal_web', // e.g., 'root'
    'password' => 'tI]rfPhdOo9zHdKw', // Your actual database password
    'database' => 'kmkglobal_web' // Based on your SQL dump
];

// Establish mysqli connection
$conn = new mysqli(
    $db_config['host'],
    $db_config['username'],
    $db_config['password'],
    $db_config['database']
);

if ($conn->connect_error) {
    // You can customize this error message for a production environment
    die("Connection failed: " . $conn->connect_error);
}

/* Helpers */
function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function nf($n){ return number_format((float)$n, 2, '.', ''); }

/**
 * Executes an SQL query using mysqli and returns the results as a PHP array.
 */
function runSql($conn, $sql) {
    $result = $conn->query($sql);
    if (!$result) {
        error_log("SQL Error: " . $conn->error . "\nQuery: " . $sql);  
        return [];
    }
    
    $rows = [];
    if (is_object($result)) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    return $rows;
}

/**
 * Safely escapes a string for use in SQL queries.
 */
function safeEscape($conn, $value) {
    if (empty($value)) {
        return null;
    }
    return $conn->real_escape_string(trim($value));
}

/**
 * Fetches the vendor list and applies all filters.
 * Filters that rely on GRN/Branch data require an INNER JOIN to the vendor list.
 */
function fetchFilteredVendors($conn, $filters) {
    // Extract filters
    $filter_vendor = $filters['vendor_id'];
    $filter_status = $filters['status'];
    $filter_email = $filters['email'];
    $filter_phone = $filters['phone'];
    $filter_grn_from = $filters['grn_from'];
    $filter_grn_to = $filters['grn_to'];
    $filter_grn_number = $filters['grn_number'];
    $filter_branch_id = $filters['branch_id'];

    /* -------------------------------------------------------------------------
     * C) Build WHERE clause for vendors (Vendor Filters on 'v' table)
     * ----------------------------------------------------------------------- */
    $where_parts = [];

    if ($filter_vendor > 0) {
        $where_parts[] = "v.vendor_id = " . $filter_vendor;
    }

    $escaped_status = safeEscape($conn, $filter_status);
    if ($escaped_status !== null) {
        $where_parts[] = "v.status = '" . $escaped_status . "'";
    }

    $escaped_email = safeEscape($conn, $filter_email);
    if ($escaped_email !== null) {
        $where_parts[] = "v.email LIKE '%" . $escaped_email . "%'";
    }

    $escaped_phone = safeEscape($conn, $filter_phone);
    if ($escaped_phone !== null) {
        $where_parts[] = "v.phone LIKE '%" . $escaped_phone . "%'";
    }

    $where_sql = !empty($where_parts) ? " WHERE " . implode(' AND ', $where_parts) : '';


    /* -------------------------------------------------------------------------
     * C.1) Build GRN-related filters as an INNER JOIN clause to filter vendors
     * ----------------------------------------------------------------------- */
    $grn_filter_vendor_sql = '';
    $grn_filter_where_parts = [];
    $grn_item_filter_where_parts = [];

    // GRN Date Range Filter
    $escaped_grn_from = safeEscape($conn, $filter_grn_from);
    if ($escaped_grn_from !== null) {  
      $grn_filter_where_parts[] = "grn.grn_date >= '" . $escaped_grn_from . "'";
    }

    $escaped_grn_to = safeEscape($conn, $filter_grn_to);
    if ($escaped_grn_to !== null) {  
      $grn_filter_where_parts[] = "grn.grn_date <= '" . $escaped_grn_to . "'";
    }

    // GRN Number Filter
    $escaped_grn_number = safeEscape($conn, $filter_grn_number);
    if ($escaped_grn_number !== null) {
      $grn_filter_where_parts[] = "grn.grn_number LIKE '%" . $escaped_grn_number . "%'";
    }

    // Branch ID Filter (Requires joining goods_receipt_items)
    if ($filter_branch_id > 0) {
        // This filter must be applied to the gri table
        $grn_item_filter_where_parts[] = "gri.branch_id = " . $filter_branch_id;
    }

    // Combine all GRN-related filters into an INNER JOIN subquery to filter vendors
    if (!empty($grn_filter_where_parts) || !empty($grn_item_filter_where_parts)) {
        
        // This check is important: if any item-based filter is active, we must join gri
        $join_gri = !empty($grn_item_filter_where_parts) || $filter_branch_id > 0 ? "INNER JOIN goods_receipt_items gri ON gri.grn_id = grn.grn_id" : "";

        // Merge all GRN-related WHERE conditions
        $all_grn_filters = array_merge($grn_filter_where_parts, $grn_item_filter_where_parts);
        $grn_where_clause = " WHERE " . implode(' AND ', $all_grn_filters);
        
        // Use a subquery to select DISTINCT vendor_ids that match the GRN criteria
        $grn_filter_vendor_sql = "
            INNER JOIN (
                SELECT DISTINCT grn.vendor_id
                FROM goods_receipts grn
                {$join_gri}
                {$grn_where_clause}
            ) filtered_grn_vendors ON filtered_grn_vendors.vendor_id = v.vendor_id
        ";
    }

    /* -------------------------------------------------------------------------
     * D) Final Vendor Query
     * ----------------------------------------------------------------------- */
    $sql = "
        SELECT 
          v.vendor_id, v.vendor_name, v.email, v.phone, v.status, v.account_number, v.ifsc,
          COALESCE(vt.total_bill, 0) AS total_bill,
          COALESCE(vt.total_paid, 0) AS total_paid,
          COALESCE(vt.balance, 0)    AS balance,
          vt.updated_at
        FROM vendors v
        {$grn_filter_vendor_sql}
        LEFT JOIN vendor_totals vt ON vt.vendor_id = v.vendor_id
        {$where_sql}
        ORDER BY v.vendor_id DESC
    ";

    return runSql($conn, $sql);
}

// --- AUTO-UPDATE vendor_totals on load (mass update logic retained) ---
// (No change needed here, as it's the standard total update)
$update_sql = "
  INSERT INTO vendor_totals (vendor_id, total_bill, total_paid, balance, updated_at)
  SELECT
    v.vendor_id,
    COALESCE(b.total_bill_net, 0.00) + COALESCE(t.total_transportation, 0.00) AS total_bill,
    COALESCE(p.total_payments, 0.00)  AS total_paid,
    GREATEST(
      COALESCE(b.total_bill_net, 0.00) + COALESCE(t.total_transportation, 0.00) - (COALESCE(p.total_payments, 0.00)),
      0.00
    ) AS balance,
    NOW()
  FROM vendors v
  LEFT JOIN (
    SELECT x.vendor_id, SUM(GREATEST(x.line_amount - COALESCE(rbi.return_amt,0),0)) AS total_bill_net
    FROM (
      SELECT
        grn.vendor_id,
        gri.grn_item_id,
        COALESCE(
          NULLIF(gri.subjective_amount,0),
          (GREATEST(COALESCE(gri.unit_price,0),0) * COALESCE(gri.qty_received,0))
          - COALESCE(
              NULLIF(gri.discount_amount,0),
              (GREATEST(COALESCE(gri.unit_price,0),0) * COALESCE(gri.qty_received,0))
              * (COALESCE(gri.discount_percentage,0)/100.0)
            )
          + COALESCE(
              NULLIF(gri.tax_amount,0),
              (
                (GREATEST(COALESCE(gri.unit_price,0),0) * COALESCE(gri.qty_received,0))
                - COALESCE(
                    NULLIF(gri.discount_amount,0),
                    (GREATEST(COALESCE(gri.unit_price,0),0) * COALESCE(gri.qty_received,0))
                    * (COALESCE(gri.discount_percentage,0)/100.0)
                  )
              ) * (COALESCE(gri.tax_percentage,0)/100.0)
            )
        ) AS line_amount
      FROM goods_receipts grn
      JOIN goods_receipt_items gri ON gri.grn_id = grn.grn_id
    ) x
    LEFT JOIN (
      SELECT grn_item_id, SUM(total_amount) AS return_amt
      FROM goods_return_items
      GROUP BY grn_item_id
    ) rbi ON rbi.grn_item_id = x.grn_item_id
    GROUP BY x.vendor_id
  ) b ON b.vendor_id = v.vendor_id
  
  /* ---- TRANSPORTATION TOTAL ---- */
  LEFT JOIN (
    SELECT vendor_id, SUM(COALESCE(transportation, 0)) AS total_transportation
    FROM goods_receipts
    GROUP BY vendor_id
  ) t ON t.vendor_id = v.vendor_id
  
  -- MODIFIED: Total payments now include amount, advance_used, AND redemption_used
  LEFT JOIN (
    SELECT 
      vgp.vendor_id, 
      SUM(vgp.amount + vgp.advance_used + vgp.redemption_used) AS total_payments
    FROM vendor_grn_payments vgp
    GROUP BY vgp.vendor_id
  ) p ON p.vendor_id = v.vendor_id
  
  ON DUPLICATE KEY UPDATE
    total_bill = VALUES(total_bill),
    total_paid = VALUES(total_paid),
    balance    = VALUES(balance),
    updated_at = VALUES(updated_at)
";
$conn->query($update_sql);

/* --- Get Filter Values and Fetch Vendors --- */
// IMPORTANT: Use ternary operator to prevent Undefined index notice
$filters = [
    'vendor_id' => isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : 0,
    'status' => isset($_GET['status']) ? trim($_GET['status']) : '',
    'email' => isset($_GET['email']) ? trim($_GET['email']) : '',
    'phone' => isset($_GET['phone']) ? trim($_GET['phone']) : '',
    'grn_from' => isset($_GET['grn_from']) ? trim($_GET['grn_from']) : '',
    'grn_to' => isset($_GET['grn_to']) ? trim($_GET['grn_to']) : '',
    'payment_status' => isset($_GET['payment_status']) ? trim($_GET['payment_status']) : '',
    'grn_number' => isset($_GET['grn_number']) ? trim($_GET['grn_number']) : '',
    'branch_id' => isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0
];

// Extract variables into the local scope for convenience (e.g., $vendor_id, $payment_status)
extract($filters);  

// 1. Fetch vendors filtered by all criteria (vendor details, status, email, phone, GRN/Branch related fields)
$vendors = fetchFilteredVendors($conn, $filters);

// 2. Handle GRN Number/Date/Branch Filtering for Totals Override
// This recalculates totals based ONLY on the filtered GRNs/Dates/Branches
$vendorTotalMap = [];
// Check if any GRN-related filter is active
if (!empty($grn_number) || !empty($grn_from) || !empty($grn_to) || $branch_id > 0) {
    $ids = array_filter(array_column($vendors, 'vendor_id'));
    if (!empty($ids)) {
        $idList = implode(',', $ids);
        
        $grn_where_clause = [];
        // CRITICAL FIX: The outer query (vendors list) has already filtered the vendor IDs
        // The inner query for totals must ONLY apply the GRN/Date/Branch filters to those IDs.
        $grn_where_clause[] = "grn.vendor_id IN ($idList)";

        $escaped_grn_num = safeEscape($conn, $grn_number);
        if ($escaped_grn_num !== null) {
            $grn_where_clause[] = "grn.grn_number LIKE '%".$escaped_grn_num."%'";
        }

        $escaped_grn_from_date = safeEscape($conn, $grn_from);
        if ($escaped_grn_from_date !== null) {
            $grn_where_clause[] = "grn.grn_date >= '".$escaped_grn_from_date."'";
        }

        $escaped_grn_to_date = safeEscape($conn, $grn_to);
        if ($escaped_grn_to_date !== null) {
            $grn_where_clause[] = "grn.grn_date <= '".$escaped_grn_to_date."'";
        }
        
        // **FIX**: The Branch ID filter should use a subquery/JOIN on goods_receipt_items for accuracy, 
        // especially since the main vendor filter relies on it. To simplify the "Totals Override" query, 
        // we'll run a sub-select for GRN IDs that match all criteria (including branch).
        
        $grn_id_subquery = "
             SELECT DISTINCT grn.grn_id FROM goods_receipts grn
        ";
        $grn_id_sub_where = ["grn.vendor_id IN ($idList)"]; // Start with filtered vendors
        
        if ($escaped_grn_num !== null) $grn_id_sub_where[] = "grn.grn_number LIKE '%".$escaped_grn_num."%'";
        if ($escaped_grn_from_date !== null) $grn_id_sub_where[] = "grn.grn_date >= '".$escaped_grn_from_date."'";
        if ($escaped_grn_to_date !== null) $grn_id_sub_where[] = "grn.grn_date <= '".$escaped_grn_to_date."'";

        // Add Branch filter join if active
        if ($branch_id > 0) {
            $grn_id_subquery .= " INNER JOIN goods_receipt_items gri ON gri.grn_id = grn.grn_id";
            $grn_id_sub_where[] = "gri.branch_id = " . $branch_id;
        }

        $grn_id_subquery .= " WHERE " . implode(' AND ', $grn_id_sub_where);
        
        // This query fetches the filtered bill and paid totals relevant to the active GRN filters
        $vendorGrnTotals = runSql($conn, "
            SELECT
              grn.vendor_id,
              SUM(COALESCE(grn.transportation,0)) AS filtered_transportation,
              SUM(
                GREATEST(
                  COALESCE(grn.total_amount,0) - COALESCE(rgrn.rtn_total_grn,0),
                  0
                )
              ) AS filtered_bill_net,
              (
                -- Calculate total paid amount specifically for the GRNs matching the filter criteria
                SELECT COALESCE(SUM(amount + advance_used + redemption_used), 0)
                FROM vendor_grn_payments vgp
                WHERE vgp.grn_id IN (
                  {$grn_id_subquery} -- Use the accurately filtered GRN IDs
                )
              ) AS filtered_paid
            FROM goods_receipts grn
            LEFT JOIN (
              SELECT grnrt.grn_id, SUM(gri.total_amount) AS rtn_total_grn
              FROM goods_return_items gri
              JOIN goods_return_notes grnrt ON grnrt.return_id = gri.return_id
              GROUP BY grnrt.grn_id
            ) rgrn ON rgrn.grn_id = grn.grn_id
            WHERE grn.grn_id IN (
                  {$grn_id_subquery} -- Use the accurately filtered GRN IDs
                )
            GROUP BY grn.vendor_id
        ");

        foreach ($vendorGrnTotals as $t) {
            $vendorTotalMap[(int)$t['vendor_id']] = [
                'bill' => (float)$t['filtered_bill_net'] + (float)$t['filtered_transportation'],
                'paid' => (float)$t['filtered_paid']
            ];
        }
    }
}


// 3. Filter vendors by payment status (must run AFTER all other filtering/total recalculations)
// (No change needed here as the logic correctly uses the overridden totals)
if (!empty($payment_status)) {  
  $filteredVendors = [];
  foreach ($vendors as $v) {
    $vid  = (int)$v['vendor_id'];
    $balance = (float)$v['balance'];

    // If GRN-related filters were applied, use the recalculated balance
    if (isset($vendorTotalMap[$vid])) {
        $filteredTotals = $vendorTotalMap[$vid];
        $balance = max($filteredTotals['bill'] - $filteredTotals['paid'], 0.0);
    }

    if ($payment_status === 'Paid') {
        if (abs($balance) < 0.01) {
            $filteredVendors[] = $v;
        }
    } elseif ($payment_status === 'Pending') {
        if ($balance >= 0.01) {
            $filteredVendors[] = $v;
        }
    }
  }
  $vendors = $filteredVendors;
}

// Load dropdown data (from vendor.php)
$all_vendors = runSql($conn, "SELECT vendor_id, vendor_name FROM vendors ORDER BY vendor_name");
$all_branches = runSql($conn, "SELECT branch_id, branch_name FROM branches ORDER BY branch_name");


// --- VENDOR CODE INTEGRATION END ---


// --- EVENTS Data Fetching (Uses global exeSql from functions.php) ---
$events = exeSql("
    SELECT event_id, event_name, venue_location, mobile_number, email, created_at
    FROM events
    ORDER BY event_id DESC
") ?: [];

$event_names = array_unique(array_column($events, 'event_name'));
sort($event_names);

// --- EMPLOYEES Filter Options ---
$employee_roles = ['Manager', 'Developer', 'Sales', 'Support', 'Admin', 'HR'];
sort($employee_roles);
$employee_statuses = ['Active', 'Inactive', 'On Leave'];
sort($employee_statuses);

$EMPLOYEE_API = 'api/employee_api.php';
$EVENT_API = 'api/events_api.php';

// Check which tab should be active (for CSS/JS state preservation)
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'vendors-pane';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Master Operations Dashboard</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.10/css/dataTables.bootstrap5.min.css">
<link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">

<style>
/* Basic styles */
.items-table { width: 100%; font-size: 0.85rem; border-collapse: collapse; }
.items-table th, .items-table td { padding: 4px; border: 1px solid #ccc; text-align: center; white-space: nowrap; }
.items-table tfoot td { font-weight: 700; background: #f8f9fa; }
.table-responsive { overflow-x: auto; }
.dataTables_wrapper .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.tab-content { background: #fff; }
.badge-paid { background:#e8f7e9; color:#155724; border:1px solid #b7e2bb; font-weight:600; padding: .35em .65em; }
.badge-pending { background:#fff7e6; color:#8a6100; border:1px solid #ffe0a3; font-weight:600; padding: .35em .65em; }
.badge-soft { background:#eef2ff; color:#334155; border:1px solid #c7d2fe; font-weight:500; }

/* Filter styles (from vendor.php) */
.filter-section { background:#f8f9fa; padding:20px; border-radius:8px; margin-bottom:20px; }
.filter-row { display:grid; grid-template-columns: repeat(5, 1fr); gap:15px; margin-bottom:15px; }
.filter-group { display:flex; flex-direction:column; }
.filter-group label { font-weight:600; font-size:0.875rem; margin-bottom:5px; color:#495057; }
.filter-buttons { display:flex; gap:10px; justify-content:flex-end; }
.filter-group .select2-container { width: 100% !important; }
@media (max-width: 1500px) { .filter-row { grid-template-columns: repeat(4, 1fr); } }
@media (max-width: 1000px) { .filter-row { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 768px) { .filter-row { grid-template-columns: 1fr; } }
</style>
</head>
<body class="bg-light">

<?php 
// Assuming nav.php includes the navigation/header
include __DIR__ . '/nav.php'; 
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0 text-start">Master Operations Dashboard</h2>
        <div>
            <a href="payments_view.php" class="btn btn-md btn-outline-primary shadow-sm ms-2" id="btnPaymentHistory">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-receipt me-2" viewBox="0 0 16 16">
                    <path d="M1.92.506a.5.5 0 0 1 .58-.093L4 1.383l1.5-.97 1.5.97 1.5-.97 1.5.97 1.5-.97 1.5.97.5-.314a.5.5 0 0 1 .5.866l-.5.314v12.5a.5.5 0 0 1-.76.43L12 14.617l-1.5.97-1.5-.97-1.5.97-1.5-.97-1.5.97-1.5-.97-1.5.97a.5.5 0 0 1-.76-.43V1.383l-.5-.314a.5.5 0 0 1-.08-.563zM3 4.5a.5.5 0 0 0 0 1h10a.5.5 0 0 0 0-1H3zm0 3a.5.5 0 0 0 0 1h10a.5.5 0 0 0 0-1H3zm0 3a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1H3z"/>
                </svg>
                Payment History
            </a>
        </div>
    </div>

    <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $active_tab == 'vendors-pane' ? 'active' : '' ?>" id="vendors-tab" data-bs-toggle="tab" data-bs-target="#vendors-pane" type="button" role="tab" aria-selected="<?= $active_tab == 'vendors-pane' ? 'true' : 'false' ?>">Vendors</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $active_tab == 'events-pane' ? 'active' : '' ?>" id="events-tab" data-bs-toggle="tab" data-bs-target="#events-pane" type="button" role="tab" aria-selected="<?= $active_tab == 'events-pane' ? 'true' : 'false' ?>">Events</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $active_tab == 'employees-pane' ? 'active' : '' ?>" id="employees-tab" data-bs-toggle="tab" data-bs-target="#employees-pane" type="button" role="tab" aria-selected="<?= $active_tab == 'employees-pane' ? 'true' : 'false' ?>">Employees</button>
        </li>
    </ul>

    <div class="tab-content border border-top-0 p-3 bg-white">
        
        <div class="tab-pane fade <?= $active_tab == 'vendors-pane' ? 'show active' : '' ?>" id="vendors-pane" role="tabpanel" aria-labelledby="vendors-tab">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Vendor Management List</h5>
            </div>
            
            <div class="filter-section">
                <h5 class="mb-3">Filters</h5>
                <form method="GET" action="" id="filterForm">  
                    <input type="hidden" name="tab" value="vendors-pane">

                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="vendor_id">Vendor Name</label>
                            <select name="vendor_id" id="vendor_id" class="form-select">
                                <option value="">All Vendor Names</option>
                                <?php foreach ($all_vendors as $av): ?>
                                    <option value="<?= h($av['vendor_id']) ?>" <?= $vendor_id == $av['vendor_id'] ? 'selected' : '' ?>>
                                        <?= h($av['vendor_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="email">Email</label>
                            <input type="text" name="email" id="email" class="form-control" placeholder="Search Email" value="<?= h($email) ?>">
                        </div>

                        <div class="filter-group">
                            <label for="phone">Phone</label>
                            <input type="text" name="phone" id="phone" class="form-control" placeholder="Search Phone" value="<?= h($phone) ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="grn_number">GRN Number</label>
                            <input type="text" name="grn_number" id="grn_number" class="form-control" placeholder="Search GRN No." value="<?= h($grn_number) ?>">
                        </div>
                    </div>

                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="grn_from">GRN From Date</label>
                            <input type="date" name="grn_from" id="grn_from" class="form-control" value="<?= h($grn_from) ?>">
                            <small class="text-muted">YYYY-MM-DD</small>
                        </div>

                        <div class="filter-group">
                            <label for="grn_to">GRN To Date</label>
                            <input type="date" name="grn_to" id="grn_to" class="form-control" value="<?= h($grn_to) ?>">
                            <small class="text-muted">YYYY-MM-DD</small>
                        </div>

                        <div class="filter-group">
                            <label for="payment_status">GRN Payment Status</label>
                            <select name="payment_status" id="payment_status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="Pending" <?= $payment_status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="Paid" <?= $payment_status === 'Paid' ? 'selected' : '' ?>>Paid</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="branch_id">GRN Branch Filter</label>
                            <select name="branch_id" id="branch_id" class="form-select">
                                <option value="">All Branches (GRNs)</option>
                                <?php foreach ($all_branches as $ab): ?>
                                    <option value="<?= h($ab['branch_id']) ?>" <?= $branch_id == $ab['branch_id'] ? 'selected' : '' ?>>
                                        <?= h($ab['branch_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group" style="align-self:flex-end;">
                            <div class="filter-buttons">
                                <button type="submit" class="btn btn-primary">Apply Filters</button>  
                                <a href="?tab=vendors-pane" class="btn btn-secondary" id="resetVendorsFilters">Reset Filters</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>


            <div class="table-responsive">
                <table id="vendorsTable" class="table table-bordered table-striped table-hover align-middle" style="width:100%">
                    <thead class="table-dark">
                        <tr>
                            <th>Vendor</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Account #</th>
                            <th>IFSC</th>
                            <th class="text-end">Total Bill (₹)</th> 
                            <th class="text-end">Total Paid (₹)</th>
                            <th class="text-end">Balance (₹)</th>
                            <th>Payment Status</th>
                            <th>Totals Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    if (!empty($vendors)) {
                        foreach ($vendors as $v) {
                            $vid  = (int)$v['vendor_id'];

                            $vendor_total_amount_from_db = (float)$v['total_bill'];
                            $vendor_total_paid_from_db  = (float)$v['total_paid'];
                            $vendor_balance_from_db     = (float)$v['balance'];

                            // OVERRIDE: If GRN-related filter (GRN No, From/To Date, Branch) is active, use the calculated filtered totals
                            if (isset($vendorTotalMap[$vid])) {
                                $filteredTotals = $vendorTotalMap[$vid];
                                $vendor_total_amount_from_db = $filteredTotals['bill'];
                                $vendor_total_paid_from_db  = $filteredTotals['paid'];
                                // Recalculate balance based on the filtered totals
                                $vendor_balance_from_db      = max($filteredTotals['bill'] - $filteredTotals['paid'], 0.0);
                            }
                            // END OVERRIDE

                            $paymentStatus = (abs($vendor_balance_from_db) < 0.01) ? 'Paid' : 'Pending';
                            $updatedAt = !empty($v['updated_at']) ? date('Y-m-d H:i', strtotime($v['updated_at'])) : '';

                            echo '<tr id="vendor-'.$vid.'">';

                            echo '<td class="text-nowrap">'.h($v['vendor_name']);
                            if ($vendor_balance_from_db > 0.01) echo ' <span class="badge badge-soft ms-1">Due</span>';
                            echo '</td>';

                            echo '<td>';
                            if (!empty($v['email'])) echo '<a href="mailto:'.h($v['email']).'">'.h($v['email']).'</a>';
                            echo '</td>';

                            echo '<td>'.h($v['phone']).'</td>';
                            echo '<td>'.h($v['status']).'</td>';
                            echo '<td>'.h($v['account_number']).'</td>';
                            echo '<td>'.h($v['ifsc']).'</td>';

                            // Display the (potentially filtered) totals
                            echo '<td class="text-end" data-total-bill>'.h(nf($vendor_total_amount_from_db)).'</td>'; 
                            echo '<td class="text-end" data-total-paid>'.h(nf($vendor_total_paid_from_db)).'</td>';
                            echo '<td class="text-end" data-total-balance>'.h(nf($vendor_balance_from_db)).'</td>';

                            echo '<td>';
                            if ($paymentStatus === 'Paid') echo '<span class="badge badge-paid">Paid</span>';
                            else echo '<span class="badge badge-pending">Pending</span>';
                            echo '</td>';

                            echo '<td class="text-nowrap">'.h($updatedAt).'</td>';

                            echo '</tr>';
                        }
                    } else {
                        // This row spans the correct 11 columns
                        echo '<tr><td colspan="11" class="text-center text-muted py-4">No vendors found matching the filters.</td></tr>';
                    }
                    ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" class="text-end"><strong>Total:</strong></td> 
                            <td class="dt-total-bill text-end"></td>
                            <td class="dt-total-paid text-end"></td>
                            <td class="dt-total-balance text-end"></td>
                            <td colspan="2"></td> 
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        
        <div class="tab-pane fade <?= $active_tab == 'events-pane' ? 'show active' : '' ?>" id="events-pane" role="tabpanel" aria-labelledby="events-tab">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Event Tracking List</h5>
                <a href="events_manage.php" class="btn btn-primary btn-sm">Add Event</a>
            </div>
            
            <div class="row g-2 mb-3">
                <div class="col-md-3">
                    <select id="filterEventsName" class="form-select form-select-sm">
                        <option value="">All Event Names</option>
                        <?php foreach ($event_names as $name): ?>
                        <option value="<?= h($name) ?>"><?= h($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" id="filterEventsVenue" class="form-control form-control-sm" placeholder="Search Venue">
                </div>
                <div class="col-md-2">
                    <input type="text" id="filterEventsMobile" class="form-control form-control-sm" placeholder="Search Mobile">
                </div>
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">Date Range:</span>
                        <input type="date" id="filterEventsStartDate" class="form-control">
                        <input type="date" id="filterEventsEndDate" class="form-control">
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table id="eventsTable" class="table table-bordered table-striped align-middle w-100">
                    <thead class="table-dark">
                        <tr>
                            <th>S.No</th>
                            <th>Event Name</th>
                            <th>Venue</th>
                            <th>Mobile</th>
                            <th>Email</th>
                            <th>Created At</th>
                            <th>Total (₹)</th>
                            <th>Received (₹)</th>
                            <th>Balance (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($events)): $s=1; ?>
                        <?php foreach ($events as $e): ?>
                            <?php
                                $eid = (int)$e['event_id'];
                                $items = $eid ? exeSql("
                                        SELECT
                                            COALESCE(item_name, remark) AS item_for,
                                            COALESCE(quantity, 0) AS quantity,
                                            COALESCE(price, 0) AS price,
                                            COALESCE(total_amount, COALESCE(quantity,0) * COALESCE(price,0)) AS total_amount,
                                            COALESCE(amount_received, 0) AS amount_received,
                                            COALESCE(balance,
                                                COALESCE(total_amount, COALESCE(quantity,0)*COALESCE(price,0))
                                                - COALESCE(amount_received,0)) AS balance
                                            FROM event_items
                                            WHERE event_id = {$eid}
                                            ORDER BY item_id DESC
                                        ") : [];

                                $sum_total = 0.0; $sum_recv = 0.0; $sum_bal = 0.0;
                                foreach ($items as &$it) {
                                    $ta = (float)($it['total_amount'] ?? 0);
                                    $rcv = (float)($it['amount_received'] ?? 0);
                                    $bal = (float)($it['balance'] ?? ($ta - $rcv));
                                    $sum_total += $ta; $sum_recv += $rcv; $sum_bal += $bal;
                                }
                                unset($it);
                            ?>
                            <tr>
                                <td><?= $s++; ?></td>
                                <td><?= h($e['event_name']) ?></td>
                                <td><?= h($e['venue_location']) ?></td>
                                <td><?= h($e['mobile_number']) ?></td>
                                <td>
                                    <?php if (!empty($e['email'])): ?>
                                        <a href="mailto:<?= h($e['email']) ?>"><?= h($e['email']) ?></a>
                                    <?php endif; ?>
                                </td>
                                <td><?= h(date("d M Y, h:i A", strtotime($e['created_at']))) ?></td>
                                <td class="text-end"><?= h(nf($sum_total)) ?></td>
                                <td class="text-end"><?= h(nf($sum_recv)) ?></td>
                                <td class="text-end"><?= h(nf($sum_bal)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="text-center">No events found</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane fade <?= $active_tab == 'employees-pane' ? 'show active' : '' ?>" id="employees-pane" role="tabpanel" aria-labelledby="employees-tab">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Employee List</h5>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-3">
                    <input type="text" id="filterEmployeesName" class="form-control form-control-sm" placeholder="Search Name">
                </div>
                <div class="col-md-3">
                    <input type="text" id="filterEmployeesID" class="form-control form-control-sm" placeholder="Search ID">
                </div>
                <div class="col-md-3">
                    <select id="filterEmployeesRole" class="form-select form-select-sm">
                        <option value="">All Roles</option>
                        <?php foreach ($employee_roles as $role): ?>
                        <option value="<?= h($role) ?>"><?= h($role) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="filterEmployeesStatus" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        <?php foreach ($employee_statuses as $status): ?>
                        <option value="<?= h($status) ?>"><?= h($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="employeeErrBox" class="alert alert-danger d-none mb-3"></div>

            <div class="table-responsive">
                <table id="employeesTable" class="table table-bordered table-striped table-hover align-middle" style="width:100%">
                    <thead class="table-dark">
                        <tr>
                            <th>S.No</th>
                            <th>Employee Name</th>
                            <th>Employee ID</th>
                            <th>Mobile</th>
                            <th>Email</th>
                            <th>Address</th>
                            <th>Role</th>
                            <th class="text-end">Salary (₹)</th>
                            <th class="text-end">Balance (₹)</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                    <tfoot>
                        <tr>
                            <td colspan="7" class="text-end"><strong>Total:</strong></td>
                            <td id="ft-salary" class="text-end"><strong>₹ 0.00</strong></td>
                            <td id="ft-balance" class="text-end"><strong>₹ 0.00</strong></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.10/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.10/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<script>
function formatMoney(n){ var x = Number(n || 0); return x.toFixed(2); }
function parseMoney(s){
    if (s == null || s === '') return 0;
    var n = (typeof s === 'string') ? s.replace(/,/g,'') : String(s);
    var v = parseFloat(n);
    return isNaN(v) ? 0 : v;
}

jQuery(function($){
    let dtVendors = null;
    let dtEvents = null;
    let dtEmployees = null;
    const EMPLOYEE_API = 'api/employee_api.php';
    const EVENT_API = 'api/events_api.php';

    // Preserve active tab state on filter/form submission (PHP handles initial active class)
    const activeTab = new URLSearchParams(window.location.search).get('tab') || 'vendors-pane';
    if(activeTab !== 'vendors-pane') {
        $(`#dashboardTabs button[data-bs-target="#${activeTab}"]`).tab('show');
    }

    // Initialize Select2 for Vendor Filters
    $('#vendor_id').select2({ theme: 'bootstrap4', placeholder: "Search or Select Vendor Name", allowClear: true });
    $('#branch_id').select2({ theme: 'bootstrap4', placeholder: "Search or Select Branch", allowClear: true });
    
    // **FIXED FILTER SUBMISSION LOGIC**
    const form = $('#filterForm');
    const autoSubmitFields = [
        '#vendor_id', 
        '#status', 
        '#payment_status', 
        '#branch_id'
    ];
    
    // 1. Auto-submit on change for SELECT fields
    $(autoSubmitFields.join(', ')).on('change', function() {
        form.submit();
    });

    // 2. Submit on ENTER key press for text/date fields
    $('#filterForm input[type="text"], #filterForm input[type="date"]').on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            form.submit();
        }
    });

    // Reset button now targets the specific vendor tab
    $('#resetVendorsFilters').on('click', function(e) {
        e.preventDefault();
        window.location.href = '?tab=vendors-pane';
    });
    // **END FIXED FILTER SUBMISSION LOGIC**


    // === VENDORS TABLE (Static PHP Output + Datatables) ===
    function initVendorsTable() {
        if (dtVendors) {
            dtVendors.columns.adjust().draw();
            return;
        }

        const $vendorsTable = $('#vendorsTable');
        // Check for actual data rows (more than the single "No vendors found" row)
        const hasVendorData = $vendorsTable.find('tbody tr').length > 0 && 
                                     $vendorsTable.find('tbody tr').eq(0).find('td').length > 1;

        if (!hasVendorData) {
            return;
        }

        dtVendors = $('#vendorsTable').DataTable({
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [[0, 'asc']], 
            scrollX: true,
            dom: '<"top-bar"lBf>rtip', 
            buttons: [ // Columns are 0 to 10 (11 total)
                { 
                    extend: 'excelHtml5', 
                    text: 'Export Excel', 
                    className: 'btn btn-sm btn-success',
                    // Export all columns
                    exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10] }
                },
                { 
                    extend: 'print', 
                    text: 'Print', 
                    className: 'btn btn-sm btn-info',
                    // Selective print columns
                    exportOptions: { columns: [0, 2, 4, 5, 6, 7, 8, 9] } 
                }
            ],
            language: { emptyTable: 'No vendors found' },
            columns: [
                { orderable: true }, // 0: Vendor
                { orderable: true }, // 1: Email
                { orderable: true }, // 2: Phone
                { orderable: true }, // 3: Status
                { orderable: true }, // 4: Account #
                { orderable: true }, // 5: IFSC
                { orderable: true }, // 6: Total Bill
                { orderable: true }, // 7: Total Paid
                { orderable: true }, // 8: Balance
                { orderable: true }, // 9: Payment Status
                { orderable: true }  // 10: Totals Updated
            ],
            footerCallback: function(row, data, start, end, display) {
                var api = this.api();
                // Indices: Bill is 6, Paid is 7, Balance is 8
                var totalBill = api.column(6, {page: 'current'}).data().reduce(function (a, b) {
                    return parseMoney(a) + parseMoney(b); 
                }, 0);
                var totalPaid = api.column(7, { page: 'current' }).data().reduce(function (a, b) {
                    return parseMoney(a) + parseMoney(b);
                }, 0);
                var totalBalance = api.column(8, { page: 'current' }).data().reduce(function (a, b) {
                    return parseMoney(a) + parseMoney(b);
                }, 0);
                
                $(api.column(6).footer()).html(formatMoney(totalBill));
                $(api.column(7).footer()).html(formatMoney(totalPaid));
                $(api.column(8).footer()).html(formatMoney(totalBalance));
            }
        });
    }

    // === EVENTS TABLE (Unchanged logic, just cleanup) ===
    $.fn.dataTable.ext.search.push(
        function(settings, data, dataIndex) {
            if (settings.nTable.id !== 'eventsTable') return true;
            const startDateStr = $('#filterEventsStartDate').val();
            const endDateStr = $('#filterEventsEndDate').val();
            const dateCell = data[5]; 
            if (!startDateStr && !endDateStr) return true;
            let minDate = startDateStr ? new Date(startDateStr) : null;
            let maxDate = endDateStr ? new Date(endDateStr) : null;
            const dateParts = dateCell.split(',')[0].trim(); 
            const cellDate = new Date(dateParts); 
            if (minDate && isNaN(minDate.getTime())) minDate = null;
            if (maxDate && isNaN(maxDate.getTime())) maxDate = null;
            if (isNaN(cellDate.getTime())) return false;
            if (minDate && cellDate < minDate) return false;
            if (maxDate) {
                let adjustedMaxDate = new Date(maxDate);
                adjustedMaxDate.setDate(adjustedMaxDate.getDate() + 1);
                if (cellDate >= adjustedMaxDate) return false;
            }
            return true;
        }
    );

    function initEventsTable() {
        if (dtEvents) {
            dtEvents.columns.adjust().draw();
            return;
        } 
        dtEvents = $('#eventsTable').DataTable({
            pageLength: 10,
            order: [[1,'asc']],
            scrollX: true 
        });
        
        $('#filterEventsName').on('change', function () { dtEvents.column(1).search(this.value).draw(); });
        $('#filterEventsVenue').on('keyup change', function () { dtEvents.column(2).search(this.value).draw(); });
        $('#filterEventsMobile').on('keyup change', function () { dtEvents.column(3).search(this.value).draw(); });
        $('#filterEventsStartDate, #filterEventsEndDate').on('change', function () { dtEvents.draw(); });
    }

    // === EMPLOYEES TABLE (Unchanged logic, uses AJAX) ===
    function initEmployeesTable() {
        if (dtEmployees) {
            dtEmployees.columns.adjust().draw();
            return;
        }
        dtEmployees = $('#employeesTable').DataTable({
            ajax: {
                url: EMPLOYEE_API + '?action=list',
                dataSrc: function(json){
                    return json && json.data ? json.data : [];
                },
                error: function(xhr){
                    const box = document.getElementById('employeeErrBox');
                    if(box) {
                        box.textContent = 'Error loading employees: ' + xhr.status;
                        box.classList.remove('d-none');
                    }
                }
            },
            columns: [
                { data: 'sno' },
                { data: 'employee_name' },
                { data: 'employee_uid' },
                { data: 'mobile_number' },
                { data: 'email' },
                { data: 'address' },
                { data: 'role' },
                { data: 'salary' },
                { data: 'balance' },
                { data: 'status' },
                { data: 'actions', orderable:false }
            ],
            pageLength: 10,
            lengthMenu: [10,25,50,100],
            order: [[1,'asc']],
            scrollX: true,
            language: { emptyTable: 'No employees found' }
        });

        $('#filterEmployeesName').on('keyup change', function () { dtEmployees.column(1).search(this.value).draw(); });
        $('#filterEmployeesID').on('keyup change', function () { dtEmployees.column(2).search(this.value).draw(); });
        $('#filterEmployeesRole').on('change', function () { dtEmployees.column(6).search(this.value).draw(); });
        $('#filterEmployeesStatus').on('change', function () { dtEmployees.column(9).search(this.value).draw(); });
    }

    // Initialize the current tab table on load
    switch (activeTab) {
        case 'events-pane': initEventsTable(); break;
        case 'employees-pane': initEmployeesTable(); break;
        default: initVendorsTable(); break;
    }

    // Tab switching
    const tabEl = document.getElementById('dashboardTabs');
    if (tabEl) {
        tabEl.addEventListener('shown.bs.tab', event => {
            const targetPaneId = event.target.getAttribute('data-bs-target');
            const paneName = targetPaneId.substring(1);

            // Update URL to retain state
            const url = new URL(window.location);
            url.searchParams.set('tab', paneName);
            window.history.pushState({}, '', url);

            switch (paneName) {
                case 'vendors-pane':
                    initVendorsTable();
                    break;
                case 'events-pane':
                    initEventsTable();
                    break;
                case 'employees-pane':
                    initEmployeesTable();
                    break;
            }
        });
    }
});
</script>
</body>
</html>