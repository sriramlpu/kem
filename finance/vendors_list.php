<?php
/**
 * vendors_list.php — Vendor Summary List (Main Page with Filters)
 * This version retains only Vendor Name, Status, and Vendor Payment Status filters.
 * MODIFICATION: The first column now displays the Vendor Name directly, removing the new Serial Number (S/N) column.
 */
$db_config = [
    'host' => 'localhost',
    'username' => 'kmkglobal_web',
    'password' => 'tI]rfPhdOo9zHdKw',
    'database' => 'kmkglobal_web'
];
$conn = new mysqli($db_config['host'], $db_config['username'], $db_config['password'], $db_config['database']);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }
function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function nf($n){ return number_format((float)$n, 2, '.', ''); }
function runSql($conn, $sql) { /* ... */ $result = $conn->query($sql); /* ... */ $rows = []; if (is_object($result)) { while ($row = $result->fetch_assoc()) { $rows[] = $row; } $result->free(); } return $rows; }
function safeEscape($conn, $value) { /* ... */ if (empty($value)) { return null; } return $conn->real_escape_string(trim($value)); }

// --- MODIFIED FUNCTION: No change needed here, we still select vendor_id for logic ---
function fetchFilteredVendors($conn, $filters) { 
    $filter_vendor = $filters['vendor_id'];
    $filter_status = $filters['status'];
    
    $where_parts = [];
    if ($filter_vendor > 0) { $where_parts[] = "v.vendor_id = " . $filter_vendor; }
    $escaped_status = safeEscape($conn, $filter_status);
    if ($escaped_status !== null) { $where_parts[] = "v.status = '" . $escaped_status . "'"; }
    
    $where_sql = !empty($where_parts) ? " WHERE " . implode(' AND ', $where_parts) : '';

    $sql = " SELECT v.vendor_id, v.vendor_name, v.status, v.account_number, v.ifsc, COALESCE(vt.total_bill, 0) AS total_bill, COALESCE(vt.total_paid, 0) AS total_paid, COALESCE(vt.balance, 0) AS balance, vt.updated_at FROM vendors v LEFT JOIN vendor_totals vt ON vt.vendor_id = v.vendor_id {$where_sql} GROUP BY v.vendor_id ORDER BY v.vendor_id DESC ";
    
    return runSql($conn, $sql);
}

// ... (UNCHANGED AUTO-UPDATE vendor_totals SQL, AND PHP-SIDE FILTERING)
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
            SELECT grn.vendor_id, gri.grn_item_id, COALESCE( NULLIF(gri.subjective_amount,0), (GREATEST(COALESCE(gri.unit_price,0),0) * COALESCE(gri.qty_received,0)) - COALESCE( NULLIF(gri.discount_amount,0), (GREATEST(COALESCE(gri.unit_price,0),0) * COALESCE(gri.qty_received,0)) * (COALESCE(gri.discount_percentage,0)/100.0) ) + COALESCE( NULLIF(gri.tax_amount,0), ( (GREATEST(COALESCE(gri.unit_price,0),0) * COALESCE(gri.qty_received,0)) - COALESCE( NULLIF(gri.discount_amount,0), (GREATEST(COALESCE(gri.unit_price,0),0) * COALESCE(gri.qty_received,0)) * (COALESCE(gri.discount_percentage,0)/100.0) ) ) * (COALESCE(gri.tax_percentage,0)/100.0) ) ) AS line_amount
            FROM goods_receipts grn
            JOIN goods_receipt_items gri ON gri.grn_id = grn.grn_id
        ) x
        LEFT JOIN (
            SELECT grn_item_id, SUM(total_amount) AS return_amt FROM goods_return_items GROUP BY grn_item_id
        ) rbi ON rbi.grn_item_id = x.grn_item_id
        GROUP BY x.vendor_id
    ) b ON b.vendor_id = v.vendor_id
    LEFT JOIN (
        SELECT vendor_id, SUM(COALESCE(transportation, 0)) AS total_transportation FROM goods_receipts GROUP BY vendor_id
    ) t ON t.vendor_id = v.vendor_id
    LEFT JOIN (
        SELECT vgp.vendor_id, SUM(vgp.amount + vgp.advance_used + vgp.redemption_used) AS total_payments FROM vendor_grn_payments vgp GROUP BY vgp.vendor_id
    ) p ON p.vendor_id = v.vendor_id
    ON DUPLICATE KEY UPDATE
        total_bill = VALUES(total_bill),
        total_paid = VALUES(total_paid),
        balance    = VALUES(balance),
        updated_at = VALUES(updated_at)
";
$conn->query($update_sql);

/* -------------------------------------------------------------------------
 * B) Get Filter Values and Fetch Vendors
 * ----------------------------------------------------------------------- */
$filters = [
    'vendor_id' => isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : 0,
    'status' => isset($_GET['status']) ? trim($_GET['status']) : '',
    'email' => '', 
    'phone' => '', 
    'grn_from' => '', 
    'grn_to' => '', 
    'payment_status' => isset($_GET['payment_status']) ? trim($_GET['payment_status']) : '',
    'grn_number' => '', 
    'branch_id' => 0 
];
extract($filters);
$vendors = fetchFilteredVendors($conn, $filters);

/* -------------------------------------------------------------------------
 * FILTER VENDORS BY PAYMENT STATUS (PHP-side filtering - KEPT)
 * ----------------------------------------------------------------------- */
if (!empty($payment_status)) {
    $filteredVendors = [];
    foreach ($vendors as $v) {
        $balance = (float)$v['balance'];
        if ($payment_status === 'Paid' && abs($balance) < 0.01) {
            $filteredVendors[] = $v;
        } elseif ($payment_status === 'Pending' && $balance >= 0.01) {
            $filteredVendors[] = $v;
        }
    }
    $vendors = $filteredVendors;
}

/* -------------------------------------------------------------------------
 * F) Load dropdown data
 * ----------------------------------------------------------------------- */
$all_vendors = runSql($conn, "SELECT vendor_id, vendor_name FROM vendors ORDER BY vendor_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Vendors - Summary List</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.10/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-buttons-bs5@2.4.2/css/buttons.bootstrap5.min.css">
<style>
     /* Retain core styles */
     .badge-paid { background:#e8f7e9; color:#155724; border:1px solid #b7e2bb; font-weight:600; }
     .badge-pending { background:#fff7e6; color:#8a6100; border:1px solid #ffe0a3; font-weight:600; }
     .filter-section { background:#f8f9fa; padding:20px; border-radius:8px; margin-bottom:20px; }
     
     /* Filter Grid Layout: 4 columns for 3 filters + 1 button group */
     .filter-row { display:grid; grid-template-columns: repeat(4, 1fr); gap:15px; margin-bottom:15px; }
     .filter-group { 
         display:flex; 
         flex-direction:column; 
         justify-content: space-between;
     }
     .filter-group label { font-weight:600; font-size:0.875rem; margin-bottom:5px; color:#495057; }
     .filter-group .select2-container { width: 100% !important; }

     /* Specific fix for the button group to align its content to the bottom */
     .filter-group .filter-buttons {
         margin-top: auto; 
     }

     @media (max-width: 1500px) { .filter-row { grid-template-columns: repeat(3, 1fr); } }
     @media (max-width: 1000px) { .filter-row { grid-template-columns: repeat(2, 1fr); } }
     @media (max-width: 768px) { .filter-row { grid-template-columns: 1fr; } }
     
     /* Style for DataTables top bar elements to be inline (UNCHANGED) */
     #vendorsTable_wrapper .top-bar {
         display: flex;
         justify-content: space-between;
         align-items: center;
         margin-bottom: 8px;
         flex-wrap: wrap; 
     }
     #vendorsTable_wrapper .top-bar .dt-buttons {
         order: 2; 
     }
     #vendorsTable_wrapper .top-bar div[id$="_length"] {
         order: 1; 
     }
     #vendorsTable_wrapper .top-bar div[id$="_filter"] {
         order: 3; 
     }
</style>
</head>
<body class="bg-light">
     <?php  include 'nav.php'; ?>

<div class="container-fluid p-4">
    <h2 class="mb-3">Vendors List</h2>

    <div class="filter-section">
        <h5 class="mb-3">Filters</h5>
        <form method="GET" action="" id="filterForm">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="vendor_id">Vendor Name</label>
                    <select name="vendor_id" id="vendor_id" class="form-select">
                        <option value="">All Vendor Names</option>
                        <?php foreach ($all_vendors as $av): ?>
                            <option value="<?= h($av['vendor_id']) ?>" <?= $vendor_id == $av['vendor_id'] ? 'selected' : '' ?>><?= h($av['vendor_name']) ?></option>
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
                    <label for="payment_status">Vendor Payment Status</label>
                    <select name="payment_status" id="payment_status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="Pending" <?= $payment_status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Paid" <?= $payment_status === 'Paid' ? 'selected' : '' ?>>Paid</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <div class="filter-buttons">
                        <input type="hidden" name="branch_id" value="0">
                        <input type="hidden" name="grn_number" value="">
                        <input type="hidden" name="grn_from" value="">
                        <input type="hidden" name="grn_to" value="">
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
                    <th>Status</th>
                    <th>Account #</th>
                    <th>IFSC</th>
                    <th>Total Bill</th>
                    <th>Total Paid</th>
                    <th>Balance</th>
                    <th>Payment Status</th>
                    <th>Action</th>
                    <th>Totals Updated</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // REMOVED: $serial_number = 1;
            if (!empty($vendors)) {
                foreach ($vendors as $v) {
                    $vid = (int)$v['vendor_id'];

                    $vendor_total_amount_from_db = (float)$v['total_bill'];
                    $vendor_total_paid_from_db    = (float)$v['total_paid'];
                    $vendor_balance_from_db      = (float)$v['balance'];
                    $paymentStatus = (abs($vendor_balance_from_db) < 0.01) ? 'Paid' : 'Pending';
                    $updatedAt = !empty($v['updated_at']) ? date('Y-m-d H:i', strtotime($v['updated_at'])) : '';
                    
                    // Vendor ID is now only stored in the data attribute, not displayed
                    $grn_filters_data = "data-vendor-id='{$vid}' data-grn-from='' data-grn-to='' data-grn-number='' data-branch-id='0' ";

                    echo '<tr id="vendor-'.$vid.'">';

                    // STARTING WITH VENDOR NAME COLUMN
                    echo '<td class="text-nowrap">'.h($v['vendor_name']);
                    if ($vendor_balance_from_db > 0.01) echo ' <span class="badge badge-soft ms-1">Due</span>';
                    echo '</td>';
                    
                    echo '<td>'.h($v['status']).'</td>';
                    
                    echo '<td>'.h($v['account_number']).'</td>';
                    echo '<td>'.h($v['ifsc']).'</td>';

                    echo '<td>'.h(nf($vendor_total_amount_from_db)).'</td>';
                    echo '<td>'.h(nf($vendor_total_paid_from_db)).'</td>';
                    echo '<td>'.h(nf($vendor_balance_from_db)).'</td>';

                    echo '<td>';
                    if ($paymentStatus === 'Paid') echo '<span class="badge badge-paid">Paid</span>';
                    else echo '<span class="badge badge-pending">Pending</span>';
                    echo '</td>';

                    // Action Column
                    echo '<td class="text-center">';
                    echo '<button type="button" class="btn btn-sm btn-info text-white view-details-btn" ' . $grn_filters_data . '>';
                    echo 'View Details';
                    echo '</button>';
                    echo '</td>';

                    echo '<td class="text-nowrap">'.h($updatedAt).'</td>';

                    echo '</tr>';
                }
            } else {
                // Colspan adjusted from 10 back to 9
                echo '<tr><td colspan="9" class="text-center text-muted py-4">No vendors found matching the filters.</td></tr>';
            }
            ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3"></td> 
                    <td class="dt-total-bill"></td>
                    <td class="dt-total-paid"></td>
                    <td class="dt-total-balance"></td>
                    <td colspan="3"></td> 
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<form id="detailsForm"  action="vendor_details" method="POST" style="display:none;">
    <input type="hidden" name="vendor_id" id="post_vendor_id">
    <input type="hidden" name="grn_from" id="post_grn_from">
    <input type="hidden" name="grn_to" id="post_grn_to">
    <input type="hidden" name="grn_number" id="post_grn_number">
    <input type="hidden" name="branch_id" id="post_branch_id">
</form>

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
    $('#vendor_id').select2({ theme: 'bootstrap4', placeholder: "Search or Select Vendor Name", allowClear: true });
    
    const form = $('#filterForm');
    const autoSubmitFields = ['#vendor_id', '#status', '#payment_status']; 
    $(autoSubmitFields.join(', ')).on('change', function() { form.submit(); });

    $('.view-details-btn').on('click', function() {
        const btn = $(this);
        const detailsForm = $('#detailsForm');

        $('#post_vendor_id').val(btn.data('vendor-id'));
        $('#post_grn_from').val(btn.data('grn-from') || '');
        $('#post_grn_to').val(btn.data('grn-to') || '');
        $('#post_grn_number').val(btn.data('grn-number') || '');
        $('#post_branch_id').val(btn.data('branch-id') || '');

        detailsForm.submit();
    });

    const $vendorsTable = $('#vendorsTable');
    // Now 10 columns total (Vendor: 0, Status: 1, Account #: 2, IFSC: 3, Total Bill: 4, Total Paid: 5, Balance: 6, Payment Status: 7, Action: 8, Totals Updated: 9)
    const hasVendorData = $vendorsTable.find('tbody tr').length > 0 && $vendorsTable.find('tbody tr').eq(0).find('td').length > 1;

    if (!hasVendorData) {
        console.log("Skipping DataTables init: No vendors found or data row is missing columns.");
        return;
    }
    
    // New Indices (10 columns): Vendor: 0, Status: 1, Account #: 2, IFSC: 3, Total Bill: 4, Total Paid: 5, Balance: 6, Payment Status: 7, Action: 8, Totals Updated: 9
    
    var dt = $vendorsTable.DataTable({
        pageLength: 10,
        lengthMenu: [10,25,50,100],
        order: [[0, 'asc']], // Order by Vendor Name (now index 0)
        scrollX: true,
        dom: '<"top-bar"lBf>rtip',
        language: { emptyTable: 'No vendors found', zeroRecords: 'No matching records found' },
        columnDefs: [
            // Action (8) is the only non-orderable column now
            { "orderable": false, "targets": [8] } 
        ],
        buttons: [
            {
                extend: 'excelHtml5',
                title: 'Vendors-Summary-Report-' + new Date().toISOString().slice(0, 10),
                // EXCEL Export columns: Exclude Action (index 8)
                exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 9] } 
            },
            {
                extend: 'print',
                title: 'Vendors Report (Summary)',
                text: 'Print',
                autoPrint: true,
                // PRINT Export columns: Exclude Action (index 8) and Totals Updated (9)
                exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7] }, 
                customize: function (win) {
                    var $body = $(win.document.body);
                    $body.find('table').addClass('compact');
                    $body.prepend('<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">' +
                        '<div style="font-weight:700;font-size:18px;line-height:1.2;">Vendors Report</div>' +
                        '<div style="text-align:right;font-size:12px;">Printed on: ' + new Date().toLocaleString() + '</div></div>');
                    var totalBill = 0, totalPaid = 0, totalBal = 0;
                    $body.find('table tbody tr').each(function(){
                        var $tds = $(this).find('td');
                        // Check for 10 columns
                        if ($tds.length === 10) { 
                            // Indices for amounts are now: Total Bill: 4, Total Paid: 5, Balance: 6
                            var bill = parseMoney($tds.eq(4).text());
                            var paid = parseMoney($tds.eq(5).text());
                            var bal  = parseMoney($tds.eq(6).text());
                            if (!isNaN(bill)) totalBill += bill;
                            if (!isNaN(paid)) totalPaid += paid;
                            if (!isNaN(bal))  totalBal  += bal;
                        }
                    });
                    $body.prepend('<div style="margin-bottom:8px;font-size:12px;"><strong>Summary:</strong> Total Bill: ' + formatMoney(totalBill) + ' &nbsp;|&nbsp; Total Paid: ' + formatMoney(totalPaid) + ' &nbsp;|&nbsp; Balance: ' + formatMoney(totalBal) + '</div>');
                }
            }
        ],
        footerCallback: function(row, data, start, end, display) {
            var api = this.api();
            // Column indices for totals are now: Total Bill: 4, Total Paid: 5, Balance: 6
            var totalBill = api.column(4, {page: 'current' }).data().reduce(function (a, b) { return parseMoney(a) + parseMoney(b); }, 0);
            var totalPaid = api.column(5, { page: 'current' }).data().reduce(function (a, b) { return parseMoney(a) + parseMoney(b); }, 0);
            var totalBalance = api.column(6, { page: 'current' }).data().reduce(function (a, b) { return parseMoney(a) + parseMoney(b); }, 0);
            $(api.column(4).footer()).html(formatMoney(totalBill));
            $(api.column(5).footer()).html(formatMoney(totalPaid));
            $(api.column(6).footer()).html(formatMoney(totalBalance));
        }
    });
});
</script>
</body>
</html>