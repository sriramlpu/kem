<?php
/**
 * vendor.php - Vendor Summary & Ledger (FIXED: Pending Items First)
 * FIXES:
 * 1. Sorting: Pending items (Balance > 0) now appear at the TOP of the Ledger.
 * 2. Collation: Kept CAST fix for fatal errors.
 * 3. Totals: Dynamic calculation based on date filters.
 * 4. Branch ID: Fixed LEFT JOIN syntax and filtering logic.
 */

// -------------------------------------------------------------------------
// 1. CONNECTION & HELPERS
// -------------------------------------------------------------------------
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

// -------------------------------------------------------------------------
// 2. GET FILTERS
// -------------------------------------------------------------------------
$filters = [
    'vendor_id'      => isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : 0,
    'status'         => isset($_GET['status']) ? trim($_GET['status']) : '',
    'email'          => isset($_GET['email']) ? trim($_GET['email']) : '',
    'phone'          => isset($_GET['phone']) ? trim($_GET['phone']) : '',
    'grn_from'       => isset($_GET['grn_from']) ? trim($_GET['grn_from']) : '',
    'grn_to'         => isset($_GET['grn_to']) ? trim($_GET['grn_to']) : '',
    'payment_status' => isset($_GET['payment_status']) ? trim($_GET['payment_status']) : '',
    'grn_number'     => isset($_GET['grn_number']) ? trim($_GET['grn_number']) : '',
    'branch_id'      => isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0
];
extract($filters);

// -------------------------------------------------------------------------
// 3. FETCH VENDORS WITH DYNAMIC TOTALS
// -------------------------------------------------------------------------

// A. Build Filter Conditions for the Subquery (Calculations)
$calc_where = [];
if (!empty($grn_from)) { $calc_where[] = "grn.grn_date >= '" . safeEscape($conn, $grn_from) . "'"; }
if (!empty($grn_to))   { $calc_where[] = "grn.grn_date <= '" . safeEscape($conn, $grn_to) . "'"; }
if (!empty($grn_number)) { $calc_where[] = "grn.grn_number LIKE '%" . safeEscape($conn, $grn_number) . "%'"; }
if ($branch_id > 0)    { $calc_where[] = "grn.branch_id = " . $branch_id; }

$calc_sql_where = !empty($calc_where) ? "WHERE " . implode(' AND ', $calc_where) : "";

// B. Build Filter Conditions for the Main Vendor List
$vendor_where_parts = [];
if ($vendor_id > 0) $vendor_where_parts[] = "v.vendor_id = " . $vendor_id;
if (!empty($status)) $vendor_where_parts[] = "v.status = '" . safeEscape($conn, $status) . "'";
if (!empty($email)) $vendor_where_parts[] = "v.email LIKE '%" . safeEscape($conn, $email) . "%'";
if (!empty($phone)) $vendor_where_parts[] = "v.phone LIKE '%" . safeEscape($conn, $phone) . "%'";
$vendor_where_sql = !empty($vendor_where_parts) ? "WHERE " . implode(' AND ', $vendor_where_parts) : "";

// C. Dynamic Query - FIXED Branch ID JOIN
$sql = "
    SELECT 
        v.*, 
        COALESCE(stats.total_bill, 0) AS total_bill, 
        COALESCE(stats.total_paid, 0) AS total_paid,
        (COALESCE(stats.total_bill, 0) - COALESCE(stats.total_paid, 0)) AS balance
    FROM vendors v
    LEFT JOIN (
        SELECT 
            grn.vendor_id,
            SUM(
                GREATEST(
                    COALESCE(grn.total_amount, 0) - COALESCE(rtn.rtn_val, 0),
                    0
                )
                + COALESCE(grn.transportation, 0)
            ) AS total_bill,
            SUM(COALESCE(grn.paid_amount, 0)) AS total_paid
        FROM goods_receipts grn
        LEFT JOIN (
            SELECT 
                grnrt.grn_id, 
                SUM(gri.total_amount) AS rtn_val 
            FROM goods_return_items gri 
            JOIN goods_return_notes grnrt 
                ON grnrt.return_id = gri.return_id 
            GROUP BY grnrt.grn_id
        ) rtn 
            ON rtn.grn_id = grn.grn_id
        $calc_sql_where
        GROUP BY grn.vendor_id
    ) stats 
        ON stats.vendor_id = v.vendor_id
    $vendor_where_sql
    ORDER BY v.vendor_name ASC
";

$vendors = runSql($conn, $sql);

// Apply Payment Status Filter (PHP Side)
if (!empty($payment_status)) {
    $temp = [];
    foreach ($vendors as $v) {
        $bal = (float)$v['balance'];
        if ($payment_status === 'Paid' && abs($bal) < 0.01) {
            $temp[] = $v;
        } elseif ($payment_status === 'Pending' && $bal >= 0.01) {
            $temp[] = $v;
        }
    }
    $vendors = $temp;
}

// -------------------------------------------------------------------------
// 4. FETCH LEDGER DETAILS FOR MODALS
// -------------------------------------------------------------------------
$vendorLedgerMap = [];

if (!empty($vendors)) {
    $ids = array_column($vendors, 'vendor_id');
    if (!empty($ids)) {
        $idList = implode(',', $ids);
        
        $grn_where = ["grn.vendor_id IN ($idList)"];
        $pay_where = ["vgp.vendor_id IN ($idList)"];
        
        if (!empty($grn_from)) {
            $ef = safeEscape($conn, $grn_from);
            $grn_where[] = "grn.grn_date >= '$ef'";
            $pay_where[] = "vgp.paid_at >= '$ef 00:00:00'";
        }
        if (!empty($grn_to)) {
            $et = safeEscape($conn, $grn_to);
            $grn_where[] = "grn.grn_date <= '$et'";
            $pay_where[] = "vgp.paid_at <= '$et 23:59:59'";
        }
        if (!empty($grn_number)) {
            $eg = safeEscape($conn, $grn_number);
            $cond = "(grn.grn_number LIKE '%$eg%' OR grn.invoice_number LIKE '%$eg%')";
            $grn_where[] = $cond;
            $pay_where[] = "(vgp.payment_reference LIKE '%$eg%' OR vgp.invoice_no LIKE '%$eg%' OR grn.grn_id IN (SELECT grn_id FROM goods_receipts WHERE vendor_id IN ($idList) AND $cond))";
        }
        if ($branch_id > 0) {
            $grn_where[] = "grn.branch_id = " . $branch_id;
            $pay_where[] = "vgp.branch_id = " . $branch_id;
        }

        $grn_sql = " WHERE " . implode(' AND ', $grn_where);
        $pay_sql = " WHERE " . implode(' AND ', $pay_where);

        // QUERY 1: GRNs 
        $q1 = "
            SELECT
                grn.vendor_id, 
                'GRN' AS type, 
                CAST(grn.grn_date AS CHAR CHARACTER SET utf8) AS txn_date, 
                CAST(CONCAT(grn.grn_date, ' 00:00:00') AS CHAR CHARACTER SET utf8) AS sort_date,
                CAST(COALESCE(grn.invoice_number, grn.grn_number) AS CHAR CHARACTER SET utf8) AS doc_no, 
                CAST(grn.grn_number AS CHAR CHARACTER SET utf8) AS particulars,
                CAST(COALESCE(b.branch_name, 'Multiple') AS CHAR CHARACTER SET utf8) AS branch,
                (GREATEST(COALESCE(grn.total_amount, 0) - COALESCE(rgrn.rtn, 0), 0) + COALESCE(grn.transportation, 0)) AS amt_in,
                COALESCE(grn.paid_amount, 0) AS amt_out,
                CAST(grn.status AS CHAR CHARACTER SET utf8) AS status_db, 
                CAST(NULL AS CHAR CHARACTER SET utf8) AS pay_method
            FROM goods_receipts grn
            LEFT JOIN (SELECT grnrt.grn_id, SUM(gri.total_amount) AS rtn FROM goods_return_items gri JOIN goods_return_notes grnrt ON grnrt.return_id = gri.return_id GROUP BY grnrt.grn_id) rgrn ON rgrn.grn_id = grn.grn_id
            LEFT JOIN branches b ON b.branch_id = grn.branch_id
            $grn_sql
        ";

        // QUERY 2: PAYMENTS
        $q2 = "
            SELECT
                vgp.vendor_id, 
                'PAYMENT' AS type, 
                CAST(DATE(vgp.paid_at) AS CHAR CHARACTER SET utf8) AS txn_date, 
                CAST(vgp.paid_at AS CHAR CHARACTER SET utf8) AS sort_date,
                CAST(COALESCE(vgp.payment_reference, vgp.invoice_no) AS CHAR CHARACTER SET utf8) AS doc_no, 
                CAST(CONCAT('Payment for ', grn.grn_number) AS CHAR CHARACTER SET utf8) AS particulars,
                CAST(b.branch_name AS CHAR CHARACTER SET utf8) AS branch, 
                0 AS amt_in, 
                0 AS amt_out,
                CAST('Paid' AS CHAR CHARACTER SET utf8) AS status_db, 
                CAST(vgp.method AS CHAR CHARACTER SET utf8) AS pay_method
            FROM vendor_grn_payments vgp
            JOIN goods_receipts grn ON grn.grn_id = vgp.grn_id
            LEFT JOIN branches b ON b.branch_id = vgp.branch_id
            $pay_sql
        ";

        // SORTING: Pending (Balance >= 0.01) First, Then Paid
        $full_query = "
            SELECT * FROM (($q1) UNION ALL ($q2)) AS combined_tbl
            ORDER BY 
                CASE WHEN (amt_in - amt_out) >= 0.01 THEN 0 ELSE 1 END ASC, 
                sort_date ASC
        ";
        
        $txns = runSql($conn, $full_query);

        foreach ($txns as $t) {
            $vendorLedgerMap[(int)$t['vendor_id']][] = $t;
        }
    }
}

$all_vendors = runSql($conn, "SELECT vendor_id, vendor_name FROM vendors ORDER BY vendor_name");
$all_branches = runSql($conn, "SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vendor Reports</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.10/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-buttons-bs5@2.4.2/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
  body { background-color: #f8f9fa; font-family: system-ui, sans-serif; }
  .filter-card { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-radius: 12px; margin-bottom: 25px; background: white; }
  .filter-body { padding: 20px; }
  .badge-soft-success { background:#d1fae5; color:#065f46; border-radius:20px; padding:5px 10px; font-weight:600; font-size: 0.75rem; }
  .badge-soft-warning { background:#fef3c7; color:#92400e; border-radius:20px; padding:5px 10px; font-weight:600; font-size: 0.75rem; }
  .main-card { border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border-radius: 12px; overflow: hidden; background: white; }
  #vendorsTable th { background-color: #1a202c; color: #fff; padding: 15px; }
  #vendorsTable td { padding: 15px; vertical-align: middle; }
  
  /* Ledger Modal Table */
  .row-pending { background-color: #fff9e6; }
  .row-paid { background-color: #ffffff; }
  
  .grid-filters { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; }
  @media (max-width: 1200px) { .grid-filters { grid-template-columns: repeat(3, 1fr); } }
  @media (max-width: 768px) { .grid-filters { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<?php include 'nav.php'; ?>

<div class="container-fluid p-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold m-0">Vendor Reports</h3>
    <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#filterPanel">Toggle Filters</button>
  </div>
  
  <div class="collapse show" id="filterPanel">
    <div class="filter-card">
      <div class="filter-body">
        <form method="GET" action="" id="filterForm">
          <div class="grid-filters">
            <div><label>Vendor</label><select name="vendor_id" id="vendor_id" class="form-select"><option value="">All</option><?php foreach ($all_vendors as $av): ?><option value="<?= $av['vendor_id'] ?>" <?= $vendor_id == $av['vendor_id']?'selected':'' ?>><?= h($av['vendor_name']) ?></option><?php endforeach; ?></select></div>
            <div><label>Status</label><select name="status" id="status" class="form-select"><option value="">All</option><option value="Active" <?= $status=='Active'?'selected':'' ?>>Active</option><option value="Inactive" <?= $status=='Inactive'?'selected':'' ?>>Inactive</option></select></div>
            <div><label>Email</label><input type="text" name="email" id="email" class="form-control" value="<?= h($email) ?>"></div>
            <div><label>Phone</label><input type="text" name="phone" id="phone" class="form-control" value="<?= h($phone) ?>"></div>
            <div><label>GRN No</label><input type="text" name="grn_number" id="grn_number" class="form-control" value="<?= h($grn_number) ?>"></div>
            <div><label>From</label><input type="date" name="grn_from" id="grn_from" class="form-control" value="<?= h($grn_from) ?>"></div>
            <div><label>To</label><input type="date" name="grn_to" id="grn_to" class="form-control" value="<?= h($grn_to) ?>"></div>
            <div><label>Payment</label><select name="payment_status" id="payment_status" class="form-select"><option value="">All</option><option value="Paid" <?= $payment_status=='Paid'?'selected':'' ?>>Paid</option><option value="Pending" <?= $payment_status=='Pending'?'selected':'' ?>>Pending</option></select></div>
            <div><label>Branch</label><select name="branch_id" id="branch_id" class="form-select"><option value="">All</option><?php foreach ($all_branches as $ab): ?><option value="<?= $ab['branch_id'] ?>" <?= $branch_id == $ab['branch_id']?'selected':'' ?>><?= h($ab['branch_name']) ?></option><?php endforeach; ?></select></div>
            <div class="d-flex align-items-end gap-2">
              <button type="submit" class="btn btn-primary flex-grow-1">Apply</button>
              <button type="button" class="btn btn-secondary" id="clearFilters">Clear</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="main-card">
    <div class="table-responsive">
      <table id="vendorsTable" class="table table-hover w-100 mb-0">
        <thead>
          <tr>
            <th>Vendor Name</th>
            <th>Contact</th>
            <th>Status</th>
            <th class="text-end">Total Bill</th>
            <th class="text-end">Total Paid</th>
            <th class="text-end">Balance</th>
            <th class="text-center">State</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php
        if (!empty($vendors)) {
          foreach ($vendors as $v) {
            $vid = (int)$v['vendor_id'];
            $bal = (float)$v['balance'];
            $isPaid = (abs($bal) < 0.01);
            
            echo '<tr>';
            echo '<td><strong>'.h($v['vendor_name']).'</strong></td>';
            echo '<td>'.h($v['phone']).'</td>';
            echo '<td>'.h($v['status']).'</td>';
            echo '<td class="text-end">'.h(nf($v['total_bill'])).'</td>';
            echo '<td class="text-end text-success">'.h(nf($v['total_paid'])).'</td>';
            echo '<td class="text-end fw-bold '.($isPaid?'text-muted':'text-danger').'">'.h(nf($bal)).'</td>';
            echo '<td class="text-center">'.($isPaid ? '<span class="badge-soft-success">PAID</span>' : '<span class="badge-soft-warning">PENDING</span>').'</td>';
            echo '<td class="text-center">';
            echo '<button type="button" class="btn btn-sm btn-dark shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#ledgerModal'.$vid.'">Ledger</button>';
            echo '</td>';
            echo '</tr>';
          }
        } else {
          echo '<tr><td colspan="8" class="text-center py-5 text-muted">No vendors found.</td></tr>';
        }
        ?>
        </tbody>
        <tfoot>
          <tr class="table-light fw-bold">
            <td colspan="3" class="text-end">Page Totals:</td>
            <td class="text-end"></td>
            <td class="text-end"></td>
            <td class="text-end"></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<?php if (!empty($vendors)): ?>
  <?php foreach ($vendors as $v): $vid = (int)$v['vendor_id']; $ledger = $vendorLedgerMap[$vid] ?? []; ?>
    <div class="modal fade" id="ledgerModal<?= $vid ?>" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header bg-dark text-white">
            <h5 class="modal-title">Ledger: <?= h($v['vendor_name']) ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-0">
            <div class="table-responsive">
              <table class="table table-bordered mb-0">
                <thead class="sticky-top bg-white">
                  <tr>
                    <th>Date</th>
                    <th>Doc No</th>
                    <th>Particulars</th>
                    <th>Branch</th>
                    <th class="text-end">In (Bill)</th>
                    <th class="text-end">Out (Paid)</th>
                    <th class="text-end">Balance</th>
                    <th class="text-center">Status</th>
                  </tr>
                </thead>
                <tbody>
                <?php
                if (!empty($ledger)) {
                  foreach ($ledger as $l) {
                    $in = (float)$l['amt_in'];
                    $out = (float)$l['amt_out'];
                    $rowBal = $in - $out;
                    $statusText = (abs($rowBal) < 0.01 || $l['type'] === 'PAYMENT') ? 'PAID' : 'PENDING';
                    $badgeClass = ($statusText === 'PAID') ? 'badge-soft-success' : 'badge-soft-warning';
                    
                    // Visual Style: Pending rows slightly highlighted
                    $trClass = ($statusText === 'PENDING') ? 'row-pending' : 'row-paid';
                    
                    if ($l['status_db'] === 'Cancelled') {
                        $statusText = 'CANCELLED';
                        $badgeClass = 'bg-danger text-white rounded px-2';
                    }
                    
                    echo '<tr class="'.$trClass.'">';
                    echo '<td>'.h($l['txn_date']).'</td>';
                    echo '<td>'.h($l['doc_no']).'</td>';
                    echo '<td>'.h($l['particulars']);
                    if($l['pay_method']) echo ' <span class="badge bg-info">'.h($l['pay_method']).'</span>';
                    echo '</td>';
                    echo '<td>'.h($l['branch']).'</td>';
                    echo '<td class="text-end">'.($in > 0 ? h(nf($in)) : '-').'</td>';
                    echo '<td class="text-end">'.($out > 0 ? h(nf($out)) : '-').'</td>';
                    echo '<td class="text-end fw-bold">'.h(nf($rowBal)).'</td>';
                    echo '<td class="text-center"><span class="'.$badgeClass.'">'.$statusText.'</span></td>';
                    echo '</tr>';
                  }
                } else {
                  echo '<tr><td colspan="8" class="text-center p-3 text-muted">No transactions found for this period.</td></tr>';
                }
                ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

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
function parseMoney(s){ return parseFloat((typeof s==='string'?s.replace(/,/g,''):s)||0); }
function formatMoney(n){ return Number(n).toFixed(2); }

jQuery(function($){
  $('#vendor_id, #branch_id').select2({ theme: 'bootstrap4', placeholder: "Select...", allowClear: true });
  $('#status, #payment_status, #vendor_id, #branch_id, #grn_from, #grn_to').on('change', function(){ $('#filterForm').submit(); });
  $('#email, #phone, #grn_number').on('keyup', function(){ clearTimeout(window.t); window.t=setTimeout(function(){$('#filterForm').submit()},800); });

  // Clear Filters Button
  $('#clearFilters').on('click', function(){
    $('#vendor_id').val('').trigger('change');
    $('#status').val('').trigger('change');
    $('#email').val('');
    $('#phone').val('');
    $('#grn_number').val('');
    $('#grn_from').val('');
    $('#grn_to').val('');
    $('#payment_status').val('').trigger('change');
    $('#branch_id').val('').trigger('change');
    $('#filterForm').submit();
  });

  $('#vendorsTable').DataTable({
    pageLength: 25, order: [[0,'asc']], scrollX: true,
    dom: '<"d-flex justify-content-between mb-3"lBf>rtip',
    buttons: [
      { extend:'excelHtml5', title:'Vendor-Summary', exportOptions:{columns:[0,1,2,3,4,5]} },
      { extend:'print', title:'Vendor Summary', exportOptions:{columns:[0,3,4,5]} }
    ],
    footerCallback: function(row,data,start,end,display){
      var api = this.api();
      var bill = api.column(3,{page:'current'}).data().reduce((a,b)=>parseMoney(a)+parseMoney(b),0);
      var paid = api.column(4,{page:'current'}).data().reduce((a,b)=>parseMoney(a)+parseMoney(b),0);
      var bal = api.column(5,{page:'current'}).data().reduce((a,b)=>parseMoney(a)+parseMoney(b),0);
      $(api.column(3).footer()).html(formatMoney(bill));
      $(api.column(4).footer()).html(formatMoney(paid));
      $(api.column(5).footer()).html(formatMoney(bal));
    }
  });
});
</script>
</body>
</html>