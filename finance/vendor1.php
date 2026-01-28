<?php
/**
 * vendor.php â€” Vendors with GRN details (WITH FILTERS, using native mysqli for query execution)
 *
 * PROFESSIONAL FIXES APPLIED:
 * 1. FIX: Corrected the SUM(total_paid) logic in AUTO-UPDATE and the GRN detail section 
 * to include **redemption_used** along with amount and advance_used from vendor_grn_payments.
 * 2. FIX: The GRN Status calculation now accurately compares the Net Bill Amount (Net GRN Item Cost + Transportation)
 * against the Total Paid Amount (Amount + Advance + Redemption).
 */

/* -------------------------------------------------------------------------
 * CONFIGURATION & CONNECTION (REPLACE THESE WITH YOUR DETAILS)
 * ----------------------------------------------------------------------- */
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
 * Includes error logging, but returns an empty array on failure instead of halting/alerting.
 */
function runSql($conn, $sql) {
    $result = $conn->query($sql);
    if (!$result) {
        // Log the SQL error for debugging, but continue execution gracefully
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
     * C) Build WHERE clause for vendors (Vendor Filters)
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
     * C.1) Build GRN-related filters as an INNER JOIN clause
     * ----------------------------------------------------------------------- */
    $grn_filter_vendor_sql = '';
    $grn_filter_where_parts = [];

    // GRN From Date filter FIX: Only apply if a date is set
    $escaped_grn_from = safeEscape($conn, $filter_grn_from);
    if ($escaped_grn_from !== null) {  
      $grn_filter_where_parts[] = "grn.grn_date >= '" . $escaped_grn_from . "'";
    }

    // GRN To Date filter FIX: Only apply if a date is set
    $escaped_grn_to = safeEscape($conn, $filter_grn_to);
    if ($escaped_grn_to !== null) {  
      $grn_filter_where_parts[] = "grn.grn_date <= '" . $escaped_grn_to . "'";
    }

    $escaped_grn_number = safeEscape($conn, $filter_grn_number);
    if ($escaped_grn_number !== null) {
      $grn_filter_where_parts[] = "grn.grn_number LIKE '%" . $escaped_grn_number . "%'";
    }

    // Branch filter is integer, applied to gri table
    $grn_item_filter_where_parts = [];
    if ($filter_branch_id > 0) {
        $grn_item_filter_where_parts[] = "gri.branch_id = " . $filter_branch_id;
    }

    // Build the INNER JOIN subquery if any GRN filter is active
    if (!empty($grn_filter_where_parts) || !empty($grn_item_filter_where_parts)) {
        $all_grn_filters = array_merge($grn_filter_where_parts, $grn_item_filter_where_parts);
        $grn_where_clause = " WHERE " . implode(' AND ', $all_grn_filters);
        
        $join_gri = "JOIN goods_receipt_items gri ON gri.grn_id = grn.grn_id";

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
     * D) Vendors + totals (Vendor and GRN filters combined)
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
    
    // echo $sql;

    return runSql($conn, $sql);
}

// -----------------------------------------------------------------------

/* -------------------------------------------------------------------------
 * A) AUTO-UPDATE vendor_totals on load (global across branches)
 * ----------------------------------------------------------------------- */
$update_sql = "
  INSERT INTO vendor_totals (vendor_id, total_bill, total_paid, balance, updated_at)
  SELECT
    v.vendor_id,
    COALESCE(b.total_bill_net, 0.00) + COALESCE(t.total_transportation, 0.00) AS total_bill,
    -- ðŸ’¡ FIX APPLIED: total_paid now correctly includes amount, advance_used, AND redemption_used
    COALESCE(p.total_payments, 0.00)  AS total_paid,
    GREATEST(
      -- Balance now correctly subtracts the newly calculated full paid amount
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
  
  /* ---- TRANSPORTATION TOTAL (used to calculate full bill amount) ---- */
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
$conn->query($update_sql); // Execute the mass update query


/* -------------------------------------------------------------------------
 * B) Get Filter Values and Fetch Vendors
 * ----------------------------------------------------------------------- */
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

// CALL THE NEW ENCAPSULATED FUNCTION
$vendors = fetchFilteredVendors($conn, $filters);


/* -------------------------------------------------------------------------
 * E) Load GRN lines â€” NET of returns (with filters)
 * ----------------------------------------------------------------------- */
$vendorItemsMap = [];
$grnHeaderMap   = [];
if (!empty($vendors)) {
  $ids = [];
  foreach ($vendors as $vr) { $id = (int)$vr['vendor_id']; if ($id > 0) $ids[] = $id; }
  if (!empty($ids)) {
    $idList = implode(',', $ids);

    // Build GRN WHERE clause for GRN Lines
    $grn_where_parts = ["grn.vendor_id IN ($idList)"]; // Only fetch GRNs for the filtered vendors
    
    // Use safeEscape for date/number fields.
    $escaped_grn_from = safeEscape($conn, $grn_from);
    if ($escaped_grn_from !== null) {
      $grn_where_parts[] = "grn.grn_date >= '" . $escaped_grn_from . "'";
    }

    $escaped_grn_to = safeEscape($conn, $grn_to);
    if ($escaped_grn_to !== null) {
      $grn_where_parts[] = "grn.grn_date <= '" . $escaped_grn_to . "'";
    }

    $escaped_grn_number = safeEscape($conn, $grn_number);
    if ($escaped_grn_number !== null) {
      $grn_where_parts[] = "grn.grn_number LIKE '%" . $escaped_grn_number . "%'";
    }
    
    // Branch filter applied to gri table
    if ($branch_id > 0) {
      $grn_where_parts[] = "gri.branch_id = " . $branch_id;
    }
    
    $grn_where_sql = " WHERE " . implode(' AND ', $grn_where_parts);

    // Run GRN query using the new native function
    $rows = runSql($conn, "
      SELECT
        t.vendor_id,
        t.order_number,
        t.item_name,
        t.item_code,
        t.grn_id,
        t.grn_number,
        t.grn_date,
        t.branch_name,
        t.branch_id,
        t.grn_qty,
        t.unit_price,
        t.discount_pct,
        t.eff_discount AS discount_amt,
        t.eff_tax        AS tax_amt,
        t.eff_amount   AS amount_gross,
        COALESCE(rbi.return_amt_item,0) AS return_amt_item,
        GREATEST(t.eff_amount - COALESCE(rbi.return_amt_item,0),0) AS amount_net,

        COALESCE(grn.total_amount,0) AS grn_total_gross,
        COALESCE(grn.transportation,0) AS grn_transportation,
        COALESCE(rgrn.rtn_total_grn,0) AS grn_total_returns,
        GREATEST(
          COALESCE(grn.total_amount,0) - COALESCE(rgrn.rtn_total_grn,0),
          0
        ) AS grn_total_bill_net,

        (
          -- âœ… FIX: Include redemption_used in grn_total_paid for accurate status check
          SELECT COALESCE(SUM(amount + advance_used + redemption_used), 0)
          FROM vendor_grn_payments vgp
          WHERE vgp.grn_id = t.grn_id
        ) AS grn_total_paid

      FROM (
        SELECT
          grn.vendor_id,
          po.order_number,
          i.item_name,
          i.item_code,
          grn.grn_id,
          grn.grn_number,
          NULLIF(grn.grn_date,'0000-00-00') AS grn_date,
          COALESCE(b.branch_name,'') AS branch_name,
          COALESCE(gri.branch_id, 0) AS branch_id,
          COALESCE(gri.qty_received,0) AS grn_qty,
          COALESCE(gri.unit_price,0) AS unit_price,
          COALESCE(gri.discount_percentage,0) AS discount_pct,
          gri.grn_item_id,

          COALESCE(
            NULLIF(gri.discount_amount,0),
            (GREATEST(COALESCE(gri.unit_price,0),0) * COALESCE(gri.qty_received,0)) * (COALESCE(gri.discount_percentage,0)/100.0)
          ) AS eff_discount,

          COALESCE(
            NULLIF(gri.tax_amount,0),
            (
              (GREATEST(COALESCE(gri.unit_price,0),0) * COALESCE(gri.qty_received,0))
              - COALESCE(
                  NULLIF(gri.discount_amount,0),
                  (GREATEST(COALESCE(gri.unit_price,0),0) * COALESCE(gri.qty_received,0)) * (COALESCE(gri.discount_percentage,0)/100.0)
                )
            ) * (COALESCE(gri.tax_percentage,0)/100.0)
          ) AS eff_tax,

          COALESCE(
            NULLIF(gri.subjective_amount,0),
            (GREATEST(COALESCE(gri.unit_price,0),0) * COALESCE(gri.qty_received,0))
            - COALESCE(
                NULLIF(gri.discount_amount,0),
                (GREATEST(COALESCE(gri.unit_price,0),0) * COALESCE(gri.qty_received,0)) * (COALESCE(gri.discount_percentage,0)/100.0)
              )
            + COALESCE(
                NULLIF(gri.tax_amount,0),
                (
                  (GREATEST(COALESCE(gri.unit_price,0),0) * COALESCE(gri.qty_received,0))
                  - COALESCE(
                      NULLIF(gri.discount_amount,0),
                      (GREATEST(COALESCE(gri.unit_price,0),0) * COALESCE(gri.qty_received,0)) * (COALESCE(gri.discount_percentage,0)/100.0)
                    )
                ) * (COALESCE(gri.tax_percentage,0)/100.0)
              )
          ) AS eff_amount

        FROM goods_receipts grn
        JOIN goods_receipt_items gri ON gri.grn_id = grn.grn_id
        LEFT JOIN purchase_orders po ON po.po_id = grn.po_id
        LEFT JOIN items i            ON i.item_id = gri.item_id
        LEFT JOIN branches b         ON b.branch_id = gri.branch_id
        $grn_where_sql
      ) t

      LEFT JOIN (
        SELECT grn_item_id, SUM(total_amount) AS return_amt_item
        FROM goods_return_items
        GROUP BY grn_item_id
      ) rbi ON rbi.grn_item_id = t.grn_item_id

      JOIN goods_receipts grn ON grn.grn_id = t.grn_id
      LEFT JOIN (
        SELECT grnrt.grn_id, SUM(gri.total_amount) AS rtn_total_grn
        FROM goods_return_items gri
        JOIN goods_return_notes grnrt ON grnrt.return_id = gri.return_id
        GROUP BY grnrt.grn_id
      ) rgrn ON rgrn.grn_id = t.grn_id

      ORDER BY t.vendor_id DESC, t.grn_date DESC, t.grn_id DESC
    ");

    foreach ($rows as $r) {
      $gid = (int)$r['grn_id'];
      
      // Calculate GRN totals and status accurately
      $grnNetBillAmount = (float)$r['grn_total_bill_net'] + (float)$r['grn_transportation'];
      $grnPaidAmount    = (float)$r['grn_total_paid'];
      $grnDate          = $r['grn_date'];
      // The amount is considered 'paid' if the paid amount is almost equal to or greater than the bill.
      $grnBalance       = $grnNetBillAmount - $grnPaidAmount;

      // Determine GRN status: Paid if negligible balance AND some payment has been recorded
      if (abs($grnBalance) < 0.01 && $grnPaidAmount > 0) {
        $grnStatus = 'Paid';
      } else {
        $grnStatus = 'Pending';
      }
      
      $grnTransport = (float)$r['grn_transportation'];

      if (!isset($grnHeaderMap[$gid])) {
        // Store GRN's total bill (Net + Transportation) and paid amount
        $grnHeaderMap[$gid] = [
          'net'          => $grnNetBillAmount, 
          'paid'         => $grnPaidAmount,
          'date'         => $grnDate,
          'status'       => $grnStatus,
          'transportation' => $grnTransport
        ];
      }

      $vendorItemsMap[(int)$r['vendor_id']][] = $r;
    }
  }
}

// Filter vendors by payment status if needed
if (!empty($payment_status)) {  
  $filteredVendors = [];
  foreach ($vendors as $v) {
    $balance = (float)$v['balance'];
    
    if ($payment_status === 'Paid') {
        // Includes fully paid and those with a slight credit/overpayment
        if (abs($balance) < 0.01) {
            $filteredVendors[] = $v;
        }
    } elseif ($payment_status === 'Pending') {
        // Includes vendors who still owe money (positive balance)
        // Check for any outstanding amount greater than a negligible float error (0.01)
        if ($balance >= 0.01) {
            $filteredVendors[] = $v;
        }
    }
  }
  $vendors = $filteredVendors;
}

/* -------------------------------------------------------------------------
 * F) Load dropdown data (using native function)
 * ----------------------------------------------------------------------- */
$all_vendors = runSql($conn, "SELECT vendor_id, vendor_name FROM vendors ORDER BY vendor_name");
$all_branches = runSql($conn, "SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Vendors</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.10/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-buttons-bs5@2.4.2/css/buttons.bootstrap5.min.css">
<style>
  .items-table { width:100%; font-size:.9rem; border-collapse:collapse; }
  .items-table th, .items-table td { padding:6px; border:1px solid #ddd; text-align:center; white-space:nowrap; }
  .items-table tfoot td { font-weight:700; background:#f8f9fa; }
  .table-responsive { overflow-x:auto; }
  .dataTables_wrapper .top-bar { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
  /* Narrower max-width for Details column */
  #vendorsTable tbody td:nth-child(7){ max-width:800px; overflow-x:auto; padding:0!important; } 
  .badge-soft { background:#eef2ff; color:#334155; border:1px solid #c7d2fe; font-weight:500; }
  .badge-paid { background:#e8f7e9; color:#155724; border:1px solid #b7e2bb; font-weight:600; }
  .badge-pending { background:#fff7e6; color:#8a6100; border:1px solid #ffe0a3; font-weight:600; }
  .row-grn-total { font-weight:700; }
  .table-success { background:#d4edda!important; }
  .table-warning { background:#fff3cd!important; }
  .filter-section { background:#f8f9fa; padding:20px; border-radius:8px; margin-bottom:20px; }
  /* CSS FIX: Use 5 columns for larger screens to reduce vertical space */
  .filter-row { display:grid; grid-template-columns: repeat(5, 1fr); gap:15px; margin-bottom:15px; }
  .filter-group { display:flex; flex-direction:column; }
  .filter-group label { font-weight:600; font-size:0.875rem; margin-bottom:5px; color:#495057; }
  .filter-buttons { display:flex; gap:10px; justify-content:flex-end; }
  
  .filter-group .select2-container { width: 100% !important; }

  @media (max-width: 1500px) { /* Adjust to 4 columns on mid-sized screens */
    .filter-row { grid-template-columns: repeat(4, 1fr); }
  }
  @media (max-width: 1000px) { /* Adjust to 3 columns on tablets */
    .filter-row { grid-template-columns: repeat(3, 1fr); }
  }
  @media (max-width: 768px) {
    .filter-row { grid-template-columns: 1fr; }
  }
</style>
</head>
<body class="bg-light">


<?php // include __DIR__ . '/nav.php'; ?>
<?php include 'nav.php'; ?>


<div class="container-fluid p-4">
  <h2 class="mb-3">Vendors</h2>

    <div class="filter-section">
    <h5 class="mb-3">Filters</h5>
    <form method="GET" action="" id="filterForm">
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
            <button type="submit" class="btn btn-primary">Apply Filters</button> <a href="?" class="btn btn-secondary">Reset Filters</a>
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
          <th>Details (GRN Lines)</th>
          <th>Total Bill</th>
          <th>Total Paid</th>
          <th>Balance</th>
          <th>Payment Status</th>
          <th>Totals Updated</th>
        </tr>
      </thead>
      <tbody>
      <?php
      if (!empty($vendors)) {
        foreach ($vendors as $v) {
          $vid  = (int)$v['vendor_id'];
          $rows = $vendorItemsMap[$vid] ?? [];

          $vendor_total_discount = 0.0;
          $vendor_total_tax      = 0.0;
          $vendor_total_transport = 0.0;

          $grnTotals = [];
          $grnTransports = [];
          
          // START OF FIX: Initialize variables for filtered totals
          $filtered_vendor_total_bill = 0.0;
          $filtered_vendor_total_paid = 0.0;
          $unique_grn_ids_processed = []; // To ensure each GRN's header total is added only once
          // END OF FIX

          foreach ($rows as $r) {
            $gid = (int)$r['grn_id'];
            
            // START OF FIX: Accumulate filtered totals
            if (!in_array($gid, $unique_grn_ids_processed)) {
                if (isset($grnHeaderMap[$gid])) {
                    $header = $grnHeaderMap[$gid];
                    // The GRN Bill includes net amount + transportation
                    $filtered_vendor_total_bill += (float)($header['net'] ?? 0);
                    $filtered_vendor_total_paid += (float)($header['paid'] ?? 0);
                }
                $unique_grn_ids_processed[] = $gid;
            }
            // END OF FIX

            if (!isset($grnTotals[$gid])) {
              $grnTotals[$gid] = ['discount'=>0.0,'tax'=>0.0,'amount'=>0.0];
              $grnTransports[$gid] = (float)($grnHeaderMap[$gid]['transportation'] ?? 0);
              $vendor_total_transport += $grnTransports[$gid];
            }

            $disc    = (float)$r['discount_amt'];
            $tax     = (float)$r['tax_amt'];
            $amt_net = (float)$r['amount_net'];

            $grnTotals[$gid]['discount'] += $disc;
            $grnTotals[$gid]['tax']      += $tax;
            $grnTotals[$gid]['amount']   += $amt_net;

            $vendor_total_discount += $disc;
            $vendor_total_tax      += $tax;
          }
            
          $vendor_total_amount_from_db = (float)$v['total_bill'];
          $vendor_total_paid_from_db   = (float)$v['total_paid'];
          $vendor_balance_from_db      = (float)$v['balance'];

          // START OF FIX: Override totals if GRN NUMBER filter is active
          if (!empty($grn_number)) {
              $vendor_total_amount_from_db = $filtered_vendor_total_bill;
              $vendor_total_paid_from_db   = $filtered_vendor_total_paid;
              // Recalculate balance based on the filtered totals
              $vendor_balance_from_db      = max($filtered_vendor_total_bill - $filtered_vendor_total_paid, 0.0);
          }
          // END OF FIX

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

          echo '<td class="grn-details">';
          echo '<div class="table-responsive">';
          echo '<table class="items-table table table-sm mb-0">';
          echo '<thead class="table-secondary">';
          echo '<tr>
                      <th>GRN No</th>
                      <th>GRN Date</th>
                      <th>Item Name</th>
                      <th>Item Code</th>
                      <th>Branch</th>
                      <th>Qty (GRN)</th>
                      <th>Unit Price</th>
                      <th>Disc Amt</th>
                      <th>Tax Amt</th>
                      <th>Transport</th>
                      <th>Total Amount (Net)</th>
                    </tr>';
          echo '</thead><tbody>';

          if (!empty($rows)) {
            $currentGrn = null;
            
            foreach ($rows as $it) {
              $gid = (int)$it['grn_id'];
              
              if ($currentGrn !== null && $currentGrn !== $gid) {
                $gt = $grnTotals[$currentGrn];
                $transport = $grnTransports[$currentGrn] ?? 0;
                
                $header = $grnHeaderMap[$currentGrn] ?? ['net'=>0,'paid'=>0,'date'=>'N/A','status'=>'Pending','transportation'=>0];
                $statusClass = $header['status'] === 'Paid' ? 'table-success' : 'table-warning';

                echo '<tr class="row-grn-total '.$statusClass.'">';
                echo '<td colspan="7" class="text-end">GRN Total ('.h($header['status']).'):</td>';
                echo '<td>'.h(nf($gt['discount'])).'</td>';
                echo '<td>'.h(nf($gt['tax'])).'</td>';
                echo '<td>'.h(nf($transport)).'</td>';
                echo '<td><strong>'.h(nf($gt['amount'] + $transport)).'</strong></td>';  
                echo '</tr>';
              }
              $currentGrn = $gid;

              echo '<tr>';
              
              echo '<td>'.h($it['grn_number']).'</td>';
              echo '<td>'.h($it['grn_date']).'</td>';
              echo '<td>'.h($it['item_name']).'</td>';
              echo '<td>'.h($it['item_code']).'</td>';
              echo '<td>'.h($it['branch_name']).'</td>';
              echo '<td>'.h(nf($it['grn_qty'])).'</td>';
              echo '<td>'.h(nf($it['unit_price'])).'</td>';
              echo '<td>'.h(nf($it['discount_amt'])).'</td>';
              echo '<td>'.h(nf($it['tax_amt'])).'</td>';
              echo '<td>-</td>';
              echo '<td>'.h(nf($it['amount_net'])).'</td>';
              
              echo '</tr>';
            }
            
            // Output final GRN total
            if ($currentGrn !== null) {
              $gt = $grnTotals[$currentGrn];
              $transport = $grnTransports[$currentGrn] ?? 0;
              $header = $grnHeaderMap[$currentGrn] ?? ['net'=>0,'paid'=>0,'date'=>'N/A','status'=>'Pending','transportation'=>0];
              $statusClass = $header['status'] === 'Paid' ? 'table-success' : 'table-warning';

              echo '<tr class="row-grn-total '.$statusClass.'">';
              echo '<td colspan="7" class="text-end">GRN Total ('.h($header['status']).'):</td>';
              echo '<td>'.h(nf($gt['discount'])).'</td>';
              echo '<td>'.h(nf($gt['tax'])).'</td>';
              echo '<td>'.h(nf($transport)).'</td>';
              echo '<td><strong>'.h(nf($gt['amount'] + $transport)).'</strong></td>';  
              echo '</tr>';
            }

          } else {
            echo '<tr><td colspan="11" class="text-center text-muted py-3">No GRN Lines found for this vendor.</td></tr>';
          }

          echo '</tbody>';
          echo '<tfoot>';
          echo '<tr>';
          echo '<td colspan="7" class="text-end">Details Total:</td>';
          echo '<td>'.h(nf($vendor_total_discount)).'</td>';
          echo '<td>'.h(nf($vendor_total_tax)).'</td>';
          echo '<td>'.h(nf($vendor_total_transport)).'</td>';
          // NOTE: For the details footer, we display the calculated net amount from the displayed items/transports
          echo '<td>'.h(nf( $vendor_total_transport + array_sum(array_column($rows, 'amount_net')))).'</td>';
          echo '</tr>';
          echo '</tfoot>';
          echo '</table>';
          echo '</div>';
          echo '</td>';

          // Display the (potentially filtered) totals
          echo '<td>'.h(nf($vendor_total_amount_from_db)).'</td>'; 
          echo '<td>'.h(nf($vendor_total_paid_from_db)).'</td>';
          echo '<td>'.h(nf($vendor_balance_from_db)).'</td>';

          echo '<td>';
          if ($paymentStatus === 'Paid') echo '<span class="badge badge-paid">Paid</span>';
          else echo '<span class="badge badge-pending">Pending</span>';
          echo '</td>';

          echo '<td class="text-nowrap">'.h($updatedAt).'</td>';

          echo '</tr>';
        }
      } else {
        // This row spans the correct 12 columns for DataTables compatibility
        echo '<tr><td colspan="12" class="text-center text-muted py-4">No vendors found matching the filters.</td></tr>';
      }
      ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="6"></td> 
          <td colspan="1"></td> 
          <td class="dt-total-bill"></td>
          <td class="dt-total-paid"></td>
          <td class="dt-total-balance"></td>
          <td colspan="2"></td> 
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.10/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.10/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.2/js/buttons.print.min.js"></script>

<script>
function formatMoney(n){ var x = Number(n || 0); return x.toFixed(2); }
function parseMoney(s){
  if (s == null || s === '') return 0;
  var n = (typeof s === 'string') ? s.replace(/,/g,'') : String(s);
  var v = parseFloat(n);
  return isNaN(v) ? 0 : v;
}

jQuery(function($){

  // 1. Initialize Select2 for searchable dropdowns
  $('#vendor_id').select2({
    theme: 'bootstrap4',
    placeholder: "Search or Select Vendor Name",
    allowClear: true 
  });

  $('#branch_id').select2({
    theme: 'bootstrap4',
    placeholder: "Search or Select Branch",
    allowClear: true
  });
  
  // 2. AUTO-SUBMIT / MANUAL SUBMIT FEATURE (FIXED LOGIC)
  const form = $('#filterForm');
  
  // These fields are simple dropdowns and will still auto-submit immediately on change.
  const autoSubmitFields = [
    '#vendor_id', 
    '#status', 
    '#payment_status', 
    '#branch_id'
  ];
  
  // Attach change listener ONLY to auto-submit fields
  $(autoSubmitFields.join(', ')).on('change', function() {
    form.submit();
  });
  
  // 3. DataTable Initialization
  const $vendorsTable = $('#vendorsTable');
  const hasVendorData = $vendorsTable.find('tbody tr').length > 0
                       && $vendorsTable.find('tbody tr').eq(0).find('td').length > 1; // Check if the first row is a data row (more than 1 td)
                      
  // CRITICAL FIX: Skip DataTables initialization if there's no vendor data.
  if (!hasVendorData) {
    console.log("Skipping DataTables init: No vendors found or data row is missing columns.");
    return; // Exit the jQuery ready function
  }
  
  var dt = $vendorsTable.DataTable({
    pageLength: 10,
    lengthMenu: [10,25,50,100],
    order: [[0, 'asc']],
    scrollX: true,
    dom: '<"top-bar"lBf>rtip',
    language: { 
        emptyTable: 'No vendors found', // Only used if JS filtering removes all rows
        zeroRecords: 'No matching records found' 
    },
    buttons: [
      {
        extend: 'excelHtml5',
        title: 'Vendors-Report-' + new Date().toISOString().slice(0, 10),
        exportOptions: {
          // Exclude the 'Details' column (index 6) for clean export
          columns: [0, 1, 2, 3, 4, 5, 7, 8, 9, 10, 11], 
          format: {
            body: function (data, row, col, node) {
              return $(node).text().trim() || (typeof data === 'string' ? data.replace(/<[^>]*>/g,'').trim() : data);
            }
          }
        }
      },
      {
        extend: 'print',
        title: 'Vendors Report (Summary)',
        text: 'Print (Summary)',
        autoPrint: true,
        exportOptions: {
          // Exclude the 'Details' column (index 6) for summary print
          columns: [0, 2, 4, 5, 7, 8, 9, 10], 
          format: {
            body: function (data, row, col, node) {
              return $(node).text().trim() || (typeof data === 'string' ? data.replace(/<[^>]*>/g,'').trim() : data);
            }
          }
        },
        customize: function (win) {
          var $body = $(win.document.body);
          $body.find('table').addClass('compact');
          // Hide the Details column for print summary
          $body.find('.grn-details').hide(); 

          $body.prepend(
            '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">' +
              '<div style="font-weight:700;font-size:18px;line-height:1.2;">' +
                'Vendors Report' +
              '</div>' +
              '<div style="text-align:right;font-size:12px;">' +
                'Printed on: ' + new Date().toLocaleString() + '<br>' +
                'Report Date: ' + new Date().toISOString().slice(0,10) +
              '</div>' +
            '</div>'
          );

          var totalBill = 0, totalPaid = 0, totalBal = 0;
          $body.find('table tbody tr').each(function(){
            var $tds = $(this).find('td');
            if ($tds.length === 12) { // Check for a valid data row (12 columns total)
              var bill = parseMoney($tds.eq(7).text()); 
              var paid = parseMoney($tds.eq(8).text()); 
              var bal  = parseMoney($tds.eq(9).text()); 
              
              if (!isNaN(bill)) totalBill += bill;
              if (!isNaN(paid)) totalPaid += paid;
              if (!isNaN(bal))  totalBal  += bal;
            }
          });
          $body.prepend(
            '<div style="margin-bottom:8px;font-size:12px;">' +
              '<strong>Summary:</strong> ' +
              'Total Bill: ' + formatMoney(totalBill) + ' &nbsp;|&nbsp; ' +
              'Total Paid: ' + formatMoney(totalPaid) + ' &nbsp;|&nbsp; ' +
              'Balance: '    + formatMoney(totalBal) +
            '</div>'
          );
        }
      }
    ],
    footerCallback: function(row, data, start, end, display) {
      var api = this.api();
      // Total Bill is Column 7 (index 7).
      // Total Paid is Column 8 (index 8).
      // Balance is Column 9 (index 9).
      
      var totalBill = api.column(7, {page: 'current' }).data().reduce(function (a, b) {
          return parseMoney(a) + parseMoney(b); 
        }, 0);
      var totalPaid = api.column(8, { page: 'current' }).data().reduce(function (a, b) {
          return parseMoney(a) + parseMoney(b);
        }, 0);
      var totalBalance = api.column(9, { page: 'current' }).data().reduce(function (a, b) {
          return parseMoney(a) + parseMoney(b);
        }, 0);
      
      $(api.column(7).footer()).html(formatMoney(totalBill));
      $(api.column(8).footer()).html(formatMoney(totalPaid));
      $(api.column(9).footer()).html(formatMoney(totalBalance));
    }
  });
});
</script>
</body>
</html>