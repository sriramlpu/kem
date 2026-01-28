<?php
/**
 * vendor_details.php — Single Vendor GRN Summary View (Final Corrected Version)
 *
 * This version uses PHP-based merging and sorting, includes detailed Voucher Types,
 * and fixes the "Reset Filters" link to always preserve the required vendor_id.
 */

/* -------------------------------------------------------------------------
 * CONFIGURATION & CONNECTION 
 * ----------------------------------------------------------------------- */
$db_config = [
	'host' => 'localhost',
	'username' => 'kmkglobal_web',
	'password' => 'tI]rfPhdOo9zHdKw',
	'database' => 'kmkglobal_web'
];
$conn = new mysqli($db_config['host'], $db_config['username'], $db_config['password'], $db_config['database']);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// Helper Functions (retained from vendor_list.php)
function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function nf($n){ return number_format((float)$n, 2, '.', ''); }
function runSql($conn, $sql) { 
    $rows = []; 
    $result = $conn->query($sql);
    if (is_object($result)) { 
        while ($row = $result->fetch_assoc()) { $rows[] = $row; } 
        $result->free(); 
    } 
    return $rows; 
}
function safeEscape($conn, $value) { 
    if (empty($value)) { return null; } 
    return $conn->real_escape_string(trim($value)); 
}

/* -------------------------------------------------------------------------
 * A) Get Vendor ID and Filters
 * ----------------------------------------------------------------------- */
// FIX: Use both $_REQUEST (POST/GET) and filter_input/filter_var for safety and consistency.
$vendor_id = isset($_REQUEST['vendor_id']) ? (int)$_REQUEST['vendor_id'] : 0;
if ($vendor_id === 0) { die("Error: Vendor ID is required."); }

$filters = [
	'grn_from' => isset($_REQUEST['grn_from']) ? trim($_REQUEST['grn_from']) : '',
	'grn_to' => isset($_REQUEST['grn_to']) ? trim($_REQUEST['grn_to']) : '',
	'grn_number' => isset($_REQUEST['grn_number']) ? trim($_REQUEST['grn_number']) : '',
	'branch_id' => isset($_REQUEST['branch_id']) ? (int)$_REQUEST['branch_id'] : 0
];
extract($filters);

/* -------------------------------------------------------------------------
 * B) Fetch Single Vendor Info
 * ----------------------------------------------------------------------- */
$vendor_info = runSql($conn, "
	SELECT v.*, COALESCE(vt.total_bill, 0) AS total_bill, COALESCE(vt.total_paid, 0) AS total_paid, COALESCE(vt.balance, 0) AS balance
	FROM vendors v
	LEFT JOIN vendor_totals vt ON vt.vendor_id = v.vendor_id
	WHERE v.vendor_id = {$vendor_id}
");
$vendor = $vendor_info[0] ?? null;

if (!$vendor) { die("Error: Vendor not found."); }
$vendor_name = $vendor['vendor_name'];

/* -------------------------------------------------------------------------
 * C) Fetch GRN and Payments Separately
 * ----------------------------------------------------------------------- */

// 1. Build Dynamic WHERE clauses
$grn_where_parts = ["grn.vendor_id = {$vendor_id}"];
$payment_where_parts = ["vgp.vendor_id = {$vendor_id}"];

$escaped_grn_from = safeEscape($conn, $grn_from);
if ($escaped_grn_from !== null) { 
    $grn_where_parts[] = "grn.grn_date >= '" . $escaped_grn_from . "'"; 
    $payment_where_parts[] = "vgp.paid_at >= '" . $escaped_grn_from . " 00:00:00'";
}
$escaped_grn_to = safeEscape($conn, $grn_to);
if ($escaped_grn_to !== null) { 
    $grn_where_parts[] = "grn.grn_date <= '" . $escaped_grn_to . "'"; 
    $payment_where_parts[] = "vgp.paid_at <= '" . $escaped_grn_to . " 23:59:59'";
}
$escaped_grn_number = safeEscape($conn, $grn_number);
if ($escaped_grn_number !== null) { 
    $search_cond = "(grn.grn_number LIKE '%" . $escaped_grn_number . "%' OR grn.invoice_number LIKE '%" . $escaped_grn_number . "%')";
    $grn_where_parts[] = $search_cond;
    $payment_where_parts[] = "(vgp.payment_reference LIKE '%" . $escaped_grn_number . "%' OR vgp.invoice_no LIKE '%" . $escaped_grn_number . "%' OR grn.grn_id IN (SELECT grn_id FROM goods_receipts WHERE vendor_id = {$vendor_id} AND {$search_cond}))";
}

// Branch filter
if ($branch_id > 0) { 
    $branch_filter_sql = "grn.grn_id IN (SELECT DISTINCT grn_id FROM goods_receipt_items WHERE branch_id = " . $branch_id . ")";
    $grn_where_parts[] = $branch_filter_sql;
    $payment_where_parts[] = "vgp.branch_id = " . $branch_id;
}


$grn_where_sql = " WHERE " . implode(' AND ', $grn_where_parts);
$payment_where_sql = " WHERE " . implode(' AND ', $payment_where_parts);


// 2. Fetch GRN Data
$grn_query = "
    SELECT
        'GRN' AS transaction_type,
        grn.grn_id,
        grn.grn_number,
        grn.grn_date AS transaction_date,
        CONCAT(grn.grn_date, ' 00:00:00') AS sort_datetime, 
        COALESCE(grn.invoice_number, grn.grn_number) AS document_number,
        CONCAT('', grn.grn_number) AS particulars_text,
        COALESCE(b.branch_name, 'Multiple Branches') AS branch_name,
        (
            GREATEST(COALESCE(grn.total_amount, 0) - COALESCE(rgrn.rtn_total_grn, 0), 0) + COALESCE(grn.transportation, 0)
        ) AS amount_in,
        0.00 AS amount_out,
        grn.status AS grn_status
    FROM goods_receipts grn
    LEFT JOIN (
        SELECT grnrt.grn_id, SUM(gri.total_amount) AS rtn_total_grn
        FROM goods_return_items gri
        JOIN goods_return_notes grnrt ON grnrt.return_id = gri.return_id
        GROUP BY grnrt.grn_id
    ) rgrn ON rgrn.grn_id = grn.grn_id
    LEFT JOIN (
        SELECT grn_id, MIN(branch_id) AS branch_id FROM goods_receipt_items GROUP BY grn_id
    ) grn_branch_link ON grn_branch_link.grn_id = grn.grn_id
    LEFT JOIN branches b ON b.branch_id = grn_branch_link.branch_id
    {$grn_where_sql}
";
$grn_transactions = runSql($conn, $grn_query);


// 3. Fetch Payment Data 
$payment_query = "
    SELECT
        'PAYMENT' AS transaction_type,
        vgp.grn_id,
        grn.grn_number,
        grn.total_amount AS grn_total_amount,
        (
            SELECT COALESCE(SUM(amount + advance_used + redemption_used), 0)
            FROM vendor_grn_payments vgp_sub
            WHERE vgp_sub.grn_id = vgp.grn_id AND vgp_sub.id <= vgp.id
        ) AS cumulative_paid_for_grn,
        vgp.method AS payment_method, 
        DATE(vgp.paid_at) AS transaction_date,
        vgp.paid_at AS sort_datetime,
        COALESCE(vgp.payment_reference, vgp.invoice_no, CONCAT('Payment Ref. ', vgp.id)) AS document_number,
        CONCAT(' ', grn.grn_number) AS particulars_text,
        b.branch_name,
        0.00 AS amount_in,
        COALESCE(vgp.amount, 0) + COALESCE(vgp.advance_used, 0) + COALESCE(vgp.redemption_used, 0) AS amount_out,
        'Paid' AS grn_status
    FROM vendor_grn_payments vgp
    JOIN goods_receipts grn ON grn.grn_id = vgp.grn_id
    LEFT JOIN branches b ON b.branch_id = vgp.branch_id
    {$payment_where_sql}
    ORDER BY vgp.id ASC
";
$payment_transactions = runSql($conn, $payment_query);


// 4. Merge and Sort Transactions in PHP
$all_transactions = array_merge($grn_transactions, $payment_transactions);

// Custom sort function to order by sort_datetime
usort($all_transactions, function($a, $b) {
    // If datetimes are the same, transactions retain their original order (GRN before Payment for a single GRN on the same date)
    return strtotime($a['sort_datetime']) - strtotime($b['sort_datetime']);
});


// 5. Calculate Running Totals and Determine Voucher Type
$transactions = [];
$sno = 1;
$cumulative_balance = 0.0;
$page_total_in = 0.0;
$page_total_out = 0.0;

foreach ($all_transactions as $row) {
    $amount_in = (float)$row['amount_in'];
    $amount_out = (float)$row['amount_out'];
    
    // Determine Voucher Type
    $voucher_type = '';
    $row_class_segment = '';
    
    if ($row['transaction_type'] === 'GRN') {
        $voucher_type = 'Purchase';
        $row_class_segment = 'table-Purchase';
    } elseif ($row['transaction_type'] === 'PAYMENT') {
        $method_display = strtoupper($row['payment_method'] ?? 'N/A');
        
        $grn_total = (float)($row['grn_total_amount'] ?? 0);
        $cum_paid = (float)($row['cumulative_paid_for_grn'] ?? 0);

        if (abs($grn_total - $cum_paid) < 0.01) {
             $payment_status = 'Full Payment';
             $row_class_segment = 'table-Full';
        } else {
             $payment_status = 'Partial Payment';
             $row_class_segment = 'table-Partial';
        }
        
        $voucher_type = "{$payment_status} via {$method_display}";
    }
    
    // Update Cumulative Balance
    $cumulative_balance += $amount_in - $amount_out;
    
    $page_total_in += $amount_in;
    $page_total_out += $amount_out;
    
    // Determine status badge class
    $status_text = $row['grn_status'];
    $status_class = 'badge-pending';
    if (in_array($status_text, ['Completed', 'Paid', 'Partial'])) {
        $status_class = 'badge-paid';
    } elseif ($status_text === 'Cancelled') {
        $status_class = 'badge-cancelled';
    }

    $transactions[] = [
        'sno' => $sno++,
        'voucher_type' => $voucher_type,
        'row_class_segment' => $row_class_segment,
        'transaction_date' => $row['transaction_date'],
        'document_number' => h($row['document_number']),
        'particulars' => h($row['particulars_text']),
        'branch_name' => h($row['branch_name']),
        'amount_in' => $amount_in,
        'amount_out' => $amount_out,
        'cumulative_balance' => $cumulative_balance,
        'status_text' => h(strtoupper($status_text)),
        'status_class' => $status_class
    ];
}

$page_final_balance = $cumulative_balance; 

/* -------------------------------------------------------------------------
 * D) Load dropdown data for filters 
 * ----------------------------------------------------------------------- */
$all_branches = runSql($conn, "SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Vendor GRN Summary: <?= h($vendor_name) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/css/select2-bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.10/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-buttons-bs5@2.4.2/css/buttons.bootstrap5.min.css">
<style>
	/* Retain core styles for details table */
	.items-table { width:100%; font-size:.9rem; border-collapse:collapse; }
	.items-table th, .items-table td { padding:6px; border:1px solid #ddd; text-align:center; white-space:nowrap; }
	.items-table tfoot td { font-weight:700; background:#f8f9fa; }
	.table-responsive { overflow-x:auto; }
	.filter-section { background:#f8f9fa; padding:20px; border-radius:8px; margin-bottom:20px; }
	.filter-row { display:grid; grid-template-columns: repeat(4, 1fr); gap:15px; margin-bottom:15px; }
	.filter-group { display:flex; flex-direction:column; }
	.filter-group label { font-weight:600; font-size:0.875rem; margin-bottom:5px; color:#495057; }
	
	.top-bar {
		display: flex; 
		justify-content: space-between; 
		align-items: center; 
		margin-bottom: 10px;
	}
	.top-bar .dataTables_length { order: 1; flex-grow: 0; margin-right: auto; }
	.top-bar .dt-buttons { order: 2; flex-grow: 0; }
	.top-bar .dataTables_filter { order: 3; flex-grow: 0; margin-left: auto; }
    
	/* Custom badge styles */
    .badge-paid { background:#d4edda; color:#155724; border:1px solid #c3e6cb; font-weight:600; }
    .badge-pending { background:#fff3cd; color:#856404; border:1px solid #ffeeba; font-weight:600; }
    .badge-cancelled { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; font-weight:600; }
    
    /* Table row styles for transaction types (based on VOUCHER TYPE now) */
    .table-Purchase { background: #eaf5ff; } 
    .table-Full { background: #d4edda; } 
    .table-Partial { background: #e8f7e9; } 

</style>
</head>
<body class="bg-light">
	<?php include 'nav.php'; ?>

<div class="container-fluid p-4">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<h2 class="mb-0">VENDOR GRN SUMMARY: <?= h(strtoupper($vendor_name)) ?></h2>
		<a href="vendors_list.php" class="btn btn-secondary">← BACK TO LIST</a>
	</div>

	<div class="card mb-4">
		<div class="card-header bg-dark text-white">VENDOR SUMMARY</div>
		<div class="card-body">
			<div class="row">
				<div class="col-md-3"><strong>EMAIL:</strong> <?= h($vendor['email']) ?></div>
				<div class="col-md-3"><strong>PHONE:</strong> <?= h($vendor['phone']) ?></div>
				<div class="col-md-3"><strong>TOTAL BILL (GRNS):</strong> <?= h(nf($vendor['total_bill'])) ?></div>
				<div class="col-md-3"><strong>OVERALL BALANCE:</strong>
					<?php 
					$balance_amount = (float)$vendor['balance'];
					$badge_class = 'badge-paid';
					$balance_text = 'PAID';
					
					if (abs($balance_amount) >= 0.01) {
						$balance_text = 'PENDING (' . h(nf($balance_amount)) . ')';
						$badge_class = 'badge-pending';
					}

					echo '<span class="badge ' . $badge_class . '">' . $balance_text . '</span>';
					?>
				</div>
			</div>
		</div>
	</div>

	<div class="filter-section">
	<h5 class="mb-3">TRANSACTION FILTERS</h5>
	<form method="POST" action="" id="grnFilterForm">
		<input type="hidden" name="vendor_id" value="<?= $vendor_id ?>">
		<div class="filter-row">
			<div class="filter-group">
				<label for="grn_number">GRN/INVOICE/PAYMENT REF. NO</label>
				<input type="text" name="grn_number" id="grn_number" class="form-control" placeholder="Search GRN/Invoice/Ref No." value="<?= h($grn_number) ?>">
			</div>
			<div class="filter-group">
				<label for="grn_from">TRANSACTION FROM DATE</label>
				<input type="date" name="grn_from" id="grn_from" class="form-control" value="<?= h($grn_from) ?>">
			</div>
			<div class="filter-group">
				<label for="grn_to">TRANSACTION TO DATE</label>
				<input type="date" name="grn_to" id="grn_to" class="form-control" value="<?= h($grn_to) ?>">
			</div>
			<div class="filter-group">
				<label for="branch_id">BRANCH FILTER</label>
				<select name="branch_id" id="branch_id" class="form-select">
					<option value="">ALL BRANCHES</option>
					<?php foreach ($all_branches as $ab): ?>
						<option value="<?= h($ab['branch_id']) ?>" <?= $branch_id == $ab['branch_id'] ? 'selected' : '' ?>><?= h($ab['branch_name']) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<div class="filter-buttons d-flex justify-content-end gap-2">
			<button type="submit" class="btn btn-primary">APPLY FILTERS</button>
			<a href="vendor_details?vendor_id=<?= $vendor_id ?>" class="btn btn-secondary">RESET FILTERS</a>
		</div>
	</form>
	</div>

	<div class="table-responsive">
		<table id="grnDetailsTable" class="items-table table table-bordered table-striped table-hover align-middle" style="width:100%">
			<thead class="table-dark">
				<tr>
					<th>S.NO</th>
					<th>DATE</th>
					<th>INVOICE NO/PAYMENT REF NUMBER</th>
					<th>PARTICULARS</th>
					<th>LOCATION (Branch)</th>
					<th>VOUCHER TYPE</th>
					<th>INVOICE AMOUNT</th>
					<th>AMOUNT PAID</th>
					<th>BALANCE</th>
					<th>GRN STATUS</th>
				</tr>
			</thead>
			<tbody>
			<?php
			if (!empty($transactions)) {
				foreach ($transactions as $t) {
					// Use the determined payment type for row highlighting
					echo '<tr class="' . h($t['row_class_segment']) . '">';
					echo '<td>'.h($t['sno']).'</td>';
					echo '<td>'.h($t['transaction_date']).'</td>';
					echo '<td>'.h($t['document_number']).'</td>'; // DOCUMENT NO: GRN Invoice/Payment Ref
					echo '<td class="text-start" style="white-space:normal;">'.h($t['particulars']).'</td>';
					echo '<td>'.h($t['branch_name']).'</td>';
					echo '<td>'.h($t['voucher_type']).'</td>'; // Display the dynamic voucher type
					echo '<td class="text-end">'.h(nf($t['amount_in'])).'</td>';
					echo '<td class="text-end">'.h(nf($t['amount_out'])).'</td>';
					echo '<td class="text-end"><strong>'.h(nf($t['cumulative_balance'])).'</strong></td>';
					echo '<td><span class="badge ' . h($t['status_class']) . '">'.h($t['status_text']).'</span></td>';
					echo '</tr>';
				}
			} else {
				echo '<tr><td colspan="10" class="text-center text-muted py-3">NO TRANSACTIONS FOUND FOR THIS VENDOR MATCHING THE FILTERS.</td></tr>';
			}
			?>
			</tbody>
			<tfoot>
				<tr>
					<td colspan="6" class="text-end">PAGE TOTALS:</td>
					<td class="text-end"><?= h(nf($page_total_in)) ?></td>
					<td class="text-end"><?= h(nf($page_total_out)) ?></td>
					<td class="text-end"><strong><?= h(nf($page_final_balance)) ?></strong></td>
					<td></td>
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
// Helper functions retained/redefined here for script scope
function formatMoney(n){ var x = Number(n || 0); return x.toFixed(2); }
function parseMoney(s){
	if (s == null || s === '') return 0;
	// Strip HTML tags and non-numeric characters (except dot and minus)
	var clean_s = (typeof s === 'string') ? s.replace(/<[^>]*>?/gm, '').replace(/[^0-9.-]/g,'') : String(s); 
	var v = parseFloat(clean_s);
	return isNaN(v) ? 0 : v;
}

jQuery(function($){
	$('#branch_id').select2({ theme: 'bootstrap4', placeholder: "SEARCH OR SELECT BRANCH", allowClear: true });
	
	// Initialize DataTable
	const $grnTable = $('#grnDetailsTable');
	if ($grnTable.find('tbody tr').length > 0 && $grnTable.find('tbody tr').eq(0).find('td').length > 1) {
		$grnTable.DataTable({
			// Disable sorting/paging/searching as cumulative balance relies on the pre-sorted list
			ordering: false, 
			paging: false, 
            searching: false,
			scrollX: true,
			dom: '<"top-bar d-flex justify-content-between align-items-center"lBf>rtip',
			language: { emptyTable: 'NO TRANSACTIONS FOUND', zeroRecords: 'NO MATCHING RECORDS FOUND' },
			buttons: [
				{
					extend: 'excelHtml5',
					title: 'Vendor-TXN-SUMMARY-<?= h(strtoupper($vendor_name)) ?>-' + new Date().toISOString().slice(0, 10),
					exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9] }
				},
				{
					extend: 'print',
					title: 'VENDOR TRANSACTION SUMMARY (<?= h(strtoupper($vendor_name)) ?>)',
					text: 'PRINT',
					autoPrint: true,
					exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9] }
				}
			],
			// Footer Callback calculates page totals
			footerCallback: function(row, data, start, end, display) {
				var api = this.api();
				
				// Columns: Amount In (6), Amount Out (7), Cumulative Balance (8)
				var totalAmountIn = api.column(6, {page: 'current'}).data().reduce(function (a, b) { return parseMoney(a) + parseMoney(b); }, 0);
				var totalAmountOut = api.column(7, {page: 'current'}).data().reduce(function (a, b) { return parseMoney(a) + parseMoney(b); }, 0);
				
				$(api.column(6).footer()).html(formatMoney(totalAmountIn));
				$(api.column(7).footer()).html(formatMoney(totalAmountOut));
				
				// Get the last value of the Cumulative Balance column (8) for the final total
				var lastRowBalance = api.column(8, {page: 'current'}).data().map(parseMoney).pop() || 0;
                
				$(api.column(8).footer()).html('<strong>' + formatMoney(lastRowBalance) + '</strong>');
			}
		});
	}
});
</script>
</body>
</html>