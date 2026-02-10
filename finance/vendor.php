<?php
/**
 * FINANCE: Vendor Summary & Ledger
 * Path: finance/vendor.php
 * UPDATED: Strictly matched to the structure provided in the request.
 * LOGIC: Branch filter (Code-1), Grouped Ledger (One row per GRN), and Payment Truth from vendor_grn_payments.
 * UI: 8-column structure with pre-generated modals for all vendors.
 * FIXED: Removed redundant h() and nf() definitions to prevent clashing with header.php.
 */

// -------------------------------------------------------------------------
// 1. UTILITIES & AUTH
// -------------------------------------------------------------------------
require_once("../auth.php");
requireRole(['Cashier', 'Approver', 'Admin']);
require_once("../functions.php");

/** Early Utils (Needed before header for filtering) **/
if (!function_exists('s')) { function s($x) { return trim((string)($x ?? '')); } }
if (!function_exists('i')) { function i($x) { return is_numeric($x) ? (int)$x : 0; } }

// -------------------------------------------------------------------------
// 2. GET FILTERS
// -------------------------------------------------------------------------
$filters = [
    'vendor_id'      => isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : 0,
    'status'         => isset($_GET['status']) ? trim((string)$_GET['status']) : '',
    'email'          => isset($_GET['email']) ? trim((string)$_GET['email']) : '',
    'phone'          => isset($_GET['phone']) ? trim((string)$_GET['phone']) : '',
    'grn_from'       => isset($_GET['grn_from']) ? trim((string)$_GET['grn_from']) : '',
    'grn_to'         => isset($_GET['grn_to']) ? trim((string)$_GET['grn_to']) : '',
    'payment_status' => isset($_GET['payment_status']) ? trim((string)$_GET['payment_status']) : '',
    'grn_number'     => isset($_GET['grn_number']) ? trim((string)$_GET['grn_number']) : '',
    'branch_id'      => isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0
];
extract($filters);

// -------------------------------------------------------------------------
// 3. AJAX LEDGER ENDPOINT (Grouped by GRN - One row per GRN)
// -------------------------------------------------------------------------
if (isset($_GET['ajax_ledger'])) {
    if (ob_get_length()) ob_clean();
    $vid = i($_GET['ajax_ledger']);
    
    $sql = "
        SELECT 
            gr.grn_id, gr.grn_date, gr.grn_number, b.branch_name,
            (gr.total_amount + gr.transportation - IFNULL(rtn.rtn_val,0)) AS amt_in,
            COALESCE(payments.paid_val, 0) AS amt_out
        FROM goods_receipts gr
        INNER JOIN branches b ON b.branch_id = gr.branch_id
        LEFT JOIN (
            SELECT grn_id, SUM(total_amount) AS rtn_val 
            FROM goods_return_notes GROUP BY grn_id
        ) rtn ON rtn.grn_id = gr.grn_id
        LEFT JOIN (
            SELECT grn_id, SUM(amount + IFNULL(advance_used,0) + IFNULL(redemption_used,0)) AS paid_val 
            FROM vendor_grn_payments GROUP BY grn_id
        ) payments ON payments.grn_id = gr.grn_id
        WHERE gr.vendor_id = $vid
        ORDER BY (amt_in - COALESCE(payments.paid_val, 0)) DESC, gr.grn_date DESC
    ";
    
    $ledger = exeSql($sql) ?: [];

    echo '<table class="table table-bordered mb-0 small">
            <thead class="bg-light text-uppercase fw-bold" style="font-size:0.7rem;">
                <tr>
                    <th>Date</th><th>GRN #</th><th>Branch</th><th class="text-end">Total Bill</th><th class="text-end">Amount Paid</th><th class="text-end">Balance</th>
                </tr>
            </thead>
            <tbody>';
    
    if (empty($ledger)) {
        echo '<tr><td colspan="6" class="text-center p-4">No records found.</td></tr>';
    } else {
        foreach($ledger as $row) {
            $bal = (float)$row['amt_in'] - (float)$row['amt_out'];
            $trClass = ($bal > 0.01) ? 'bg-warning-subtle' : '';
            echo "<tr class='$trClass'>
                    <td>".date('d-M-Y', strtotime($row['grn_date']))."</td>
                    <td><b>{$row['grn_number']}</b></td>
                    <td>{$row['branch_name']}</td>
                    <td class='text-end fw-semibold'>₹".number_format($row['amt_in'],2)."</td>
                    <td class='text-end text-success fw-semibold'>₹".number_format($row['amt_out'],2)."</td>
                    <td class='text-end fw-bold text-danger'>₹".number_format($bal,2)."</td>
                  </tr>";
        }
    }
    echo '</tbody></table>';
    exit;
}

// -------------------------------------------------------------------------
// 4. FETCH VENDORS WITH DYNAMIC TOTALS
// -------------------------------------------------------------------------

// A. Build Filter Conditions for the Subquery (Calculations)
$calc_where = ["1=1"];
if (!empty($grn_from))   { $calc_where[] = "grn.grn_date >= '" . addslashes($grn_from) . "'"; }
if (!empty($grn_to))     { $calc_where[] = "grn.grn_date <= '" . addslashes($grn_to) . "'"; }
if (!empty($grn_number)) { $calc_where[] = "grn.grn_number LIKE '%" . addslashes($grn_number) . "%'"; }
if ($branch_id > 0)      { $calc_where[] = "grn.branch_id = " . $branch_id; }

$calc_sql_where = "WHERE " . implode(' AND ', $calc_where);

// B. Build Filter Conditions for the Main Vendor List
$vendor_where_parts = ["1=1"];
if ($vendor_id > 0)   $vendor_where_parts[] = "v.vendor_id = " . $vendor_id;
if (!empty($status))  $vendor_where_parts[] = "v.status = '" . addslashes($status) . "'";
if (!empty($email))   $vendor_where_parts[] = "v.email LIKE '%" . addslashes($email) . "%'";
if (!empty($phone))   $vendor_where_parts[] = "v.phone LIKE '%" . addslashes($phone) . "%'";

// Branch filter logic (Code-1): If branch is selected, only show vendors with GRNs in that branch
if ($branch_id > 0) {
    $vendor_where_parts[] = "stats.vendor_id IS NOT NULL";
}

$vendor_where_sql = "WHERE " . implode(' AND ', $vendor_where_parts);

// Payment aggregation from vendor_grn_payments table
$payAggSql = "
    SELECT 
        grn_id,
        SUM(COALESCE(amount,0) + COALESCE(advance_used,0) + COALESCE(redemption_used,0)) AS paid_total
    FROM vendor_grn_payments
    GROUP BY grn_id
";

// Main Dynamic Query
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
            SUM(COALESCE(pay.paid_total, 0)) AS total_paid
        FROM goods_receipts grn
        LEFT JOIN (
            SELECT 
                grnrt.grn_id, 
                SUM(gri.total_amount + gri.discount_amount) AS rtn_val 
            FROM goods_return_items gri 
            JOIN goods_return_notes grnrt 
                ON grnrt.return_id = gri.return_id 
            GROUP BY grnrt.grn_id
        ) rtn 
            ON rtn.grn_id = grn.grn_id
        LEFT JOIN (
            $payAggSql
        ) pay ON pay.grn_id = grn.grn_id
        $calc_sql_where
        GROUP BY grn.vendor_id
    ) stats 
        ON stats.vendor_id = v.vendor_id
    $vendor_where_sql
    ORDER BY v.vendor_name ASC
";

$vendors = exeSql($sql) ?: [];

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
// 5. FETCH LEDGER DETAILS FOR MODALS
// -------------------------------------------------------------------------
$vendorLedgerMap = [];

if (!empty($vendors)) {
    $ids = array_column($vendors, 'vendor_id');
    if (!empty($ids)) {
        $idList = implode(',', $ids);
        $grn_where = ["grn.vendor_id IN ($idList)"];

        if (!empty($grn_from))   { $grn_where[] = "grn.grn_date >= '" . addslashes($grn_from) . "'"; }
        if (!empty($grn_to))     { $grn_where[] = "grn.grn_date <= '" . addslashes($grn_to) . "'"; }
        if (!empty($grn_number)) { $grn_where[] = "(grn.grn_number LIKE '%" . addslashes($grn_number) . "%' OR grn.invoice_number LIKE '%" . addslashes($grn_number) . "%')"; }
        if ($branch_id > 0)      { $grn_where[] = "grn.branch_id = " . $branch_id; }

        $grn_sql_where = " WHERE " . implode(' AND ', $grn_where);

        $q1 = "
            SELECT
                grn.vendor_id, 
                'GRN' AS type, 
                CAST(grn.grn_date AS CHAR) AS txn_date, 
                CAST(CONCAT(grn.grn_date, ' 00:00:00') AS CHAR) AS sort_date,
                CAST(COALESCE(grn.invoice_number, grn.grn_number) AS CHAR) AS doc_no, 
                CAST(grn.grn_number AS CHAR) AS particulars,
                CAST(COALESCE(b.branch_name, 'Multiple') AS CHAR) AS branch,
                (GREATEST(COALESCE(grn.total_amount, 0) - COALESCE(rgrn.rtn, 0), 0) + COALESCE(grn.transportation, 0)) AS amt_in,
                COALESCE(pay.paid_total, 0) AS amt_out,
                CAST(grn.status AS CHAR) AS status_db, 
                CAST(pay.methods AS CHAR) AS pay_method
            FROM goods_receipts grn
            LEFT JOIN (
                SELECT grnrt.grn_id, SUM(gri.total_amount + gri.discount_amount) AS rtn
                FROM goods_return_items gri
                JOIN goods_return_notes grnrt ON grnrt.return_id = gri.return_id
                GROUP BY grnrt.grn_id
            ) rgrn ON rgrn.grn_id = grn.grn_id
            LEFT JOIN branches b ON b.branch_id = grn.branch_id
            LEFT JOIN (
                SELECT 
                    grn_id,
                    SUM(COALESCE(amount,0) + COALESCE(advance_used,0) + COALESCE(redemption_used,0)) AS paid_total,
                    GROUP_CONCAT(DISTINCT method ORDER BY method SEPARATOR ', ') AS methods
                FROM vendor_grn_payments
                GROUP BY grn_id
            ) pay ON pay.grn_id = grn.grn_id
            $grn_sql_where
        ";

        $full_ledger_query = "
            SELECT * FROM ($q1) AS combined_tbl
            ORDER BY 
                CASE WHEN (amt_in - amt_out) >= 0.01 THEN 0 ELSE 1 END ASC,
                sort_date ASC
        ";

        $txns = exeSql($full_ledger_query) ?: [];

        foreach ($txns as $t) {
            $vendorLedgerMap[(int)$t['vendor_id']][] = $t;
        }
    }
}

$all_vendors = exeSql("SELECT vendor_id, vendor_name FROM vendors ORDER BY vendor_name");
$all_branches = exeSql("SELECT branch_id, branch_name FROM branches ORDER BY branch_name");

require_once("header.php");
require_once("nav.php");
?>

<style>
  body { background-color: #f8f9fa; font-family: system-ui, sans-serif; }
  .filter-card { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-radius: 12px; margin-bottom: 25px; background: white; }
  .filter-body { padding: 20px; }
  .badge-soft-success { background:#d1fae5; color:#065f46; border-radius:20px; padding:5px 10px; font-weight:600; font-size: 0.75rem; }
  .badge-soft-warning { background:#fef3c7; color:#92400e; border-radius:20px; padding:5px 10px; font-weight:600; font-size: 0.75rem; }
  .main-card { border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border-radius: 12px; overflow: hidden; background: white; }
  #vendorsTable th { background-color: #1a202c; color: #fff; padding: 15px; }
  #vendorsTable td { padding: 15px; vertical-align: middle; }
  .row-pending { background-color: #fff9e6; }
  .row-paid { background-color: #ffffff; }
  .grid-filters { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; }
  @media (max-width: 1200px) { .grid-filters { grid-template-columns: repeat(3, 1fr); } }
  @media (max-width: 768px) { .grid-filters { grid-template-columns: 1fr; } }
  .select2-container--bootstrap4 .select2-selection--single { height: 38px !important; border-radius: 8px !important; }
</style>

<div class="container-fluid p-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold m-0 text-dark">Vendor Reports</h3>
    <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="collapse" data-bs-target="#filterPanel">Toggle Filters</button>
  </div>

  <div class="collapse show" id="filterPanel">
    <div class="filter-card">
      <div class="filter-body">
        <form method="GET" action="" id="filterForm">
          <div class="grid-filters">
            <div><label class="form-label small fw-bold text-muted">Vendor</label><select name="vendor_id" id="vendor_id" class="form-select select2"><option value="">All</option><?php foreach ($all_vendors as $av): ?><option value="<?= $av['vendor_id'] ?>" <?= $vendor_id == $av['vendor_id']?'selected':'' ?>><?= h($av['vendor_name']) ?></option><?php endforeach; ?></select></div>
            <div><label class="form-label small fw-bold text-muted">Status</label><select name="status" id="status" class="form-select"><option value="">All</option><option value="Active" <?= $status=='Active'?'selected':'' ?>>Active</option><option value="Inactive" <?= $status=='Inactive'?'selected':'' ?>>Inactive</option></select></div>
            <div><label class="form-label small fw-bold text-muted">Email</label><input type="text" name="email" id="email" class="form-control" value="<?= h($email) ?>"></div>
            <div><label class="form-label small fw-bold text-muted">Phone</label><input type="text" name="phone" id="phone" class="form-control" value="<?= h($phone) ?>"></div>
            <div><label class="form-label small fw-bold text-muted">GRN No</label><input type="text" name="grn_number" id="grn_number" class="form-control" value="<?= h($grn_number) ?>"></div>
            <div><label class="form-label small fw-bold text-muted">From</label><input type="date" name="grn_from" id="grn_from" class="form-control" value="<?= h($grn_from) ?>"></div>
            <div><label class="form-label small fw-bold text-muted">To</label><input type="date" name="grn_to" id="grn_to" class="form-control" value="<?= h($grn_to) ?>"></div>
            <div><label class="form-label small fw-bold text-muted">Payment</label><select name="payment_status" id="payment_status" class="form-select"><option value="">All</option><option value="Paid" <?= $payment_status=='Paid'?'selected':'' ?>>Paid</option><option value="Pending" <?= $payment_status=='Pending'?'selected':'' ?>>Pending</option></select></div>
            <div><label class="form-label small fw-bold text-muted">Branch</label><select name="branch_id" id="branch_id" class="form-select select2"><option value="">All</option><?php foreach ($all_branches as $ab): ?><option value="<?= $ab['branch_id'] ?>" <?= $branch_id == $ab['branch_id']?'selected':'' ?>><?= h($ab['branch_name']) ?></option><?php endforeach; ?></select></div>
            <div class="d-flex align-items-end gap-2">
              <button type="submit" class="btn btn-primary flex-grow-1 rounded-pill">Apply</button>
              <button type="button" class="btn btn-secondary rounded-pill" id="clearFilters">Clear</button>
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
        <?php foreach ($vendors as $v): $vid = (int)$v['vendor_id']; $bal = (float)$v['balance']; $isPaid = (abs($bal) < 0.01); ?>
          <tr>
            <td><strong><?= h($v['vendor_name']) ?></strong></td>
            <td><?= h($v['phone']) ?></td>
            <td><?= h($v['status']) ?></td>
            <td class="text-end"><?= h(nf($v['total_bill'])) ?></td>
            <td class="text-end text-success"><?= h(nf($v['total_paid'])) ?></td>
            <td class="text-end fw-bold <?= $isPaid?'text-muted':'text-danger' ?>"><?= h(nf($bal)) ?></td>
            <td class="text-center"><?= $isPaid ? '<span class="badge-soft-success">PAID</span>' : '<span class="badge-soft-warning">PENDING</span>' ?></td>
            <td class="text-center">
              <button type="button" class="btn btn-sm btn-dark shadow-sm px-3 rounded-pill" data-bs-toggle="modal" data-bs-target="#ledgerModal<?= $vid ?>">Ledger</button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="table-light fw-bold">
            <td></td>
            <td></td>
            <td class="text-end">Page Totals:</td>
            <td class="text-end"></td>
            <td class="text-end"></td>
            <td class="text-end"></td>
            <td></td>
            <td></td>
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
        <div class="modal-content border-0 shadow-lg rounded-4">
          <div class="modal-header bg-dark text-white py-3">
            <h5 class="modal-title fw-bold">Ledger: <?= h($v['vendor_name']) ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-0">
            <div class="table-responsive">
              <table class="table table-bordered mb-0 small">
                <thead class="sticky-top bg-light">
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
                <?php if (!empty($ledger)): ?>
                  <?php foreach ($ledger as $l): 
                    $in = (float)$l['amt_in']; $out = (float)$l['amt_out']; $rowBal = $in - $out;
                    $statusText = (abs($rowBal) < 0.01) ? 'PAID' : 'PENDING';
                    $badgeClass = ($statusText === 'PAID') ? 'badge-soft-success' : 'badge-soft-warning';
                    $trClass = ($statusText === 'PENDING') ? 'row-pending' : 'row-paid';
                    if ($l['status_db'] === 'Cancelled') { $statusText = 'CANCELLED'; $badgeClass = 'bg-danger text-white rounded px-2'; }
                  ?>
                    <tr class="<?= $trClass ?>">
                      <td><?= h($l['txn_date']) ?></td>
                      <td><?= h($l['doc_no']) ?></td>
                      <td><?= h($l['particulars']) ?></td>
                      <td><?= h($l['branch']) ?></td>
                      <td class="text-end"><?= ($in > 0 ? h(nf($in)) : '-') ?></td>
                      <td class="text-end"><?= ($out > 0 ? h(nf($out)) : '-') ?></td>
                      <td class="text-end fw-bold"><?= h(nf($rowBal)) ?></td>
                      <td class="text-center"><span class="<?= $badgeClass ?>"><?= $statusText ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="8" class="text-center p-3 text-muted">No transactions found for this period.</td></tr>
                <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer border-0">
            <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.10/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.10/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons-bs5@2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-buttons@2.4.2/js/buttons.print.min.js"></script>

<script>
function parseMoney(s){ 
    if(!s) return 0;
    if(typeof s === 'number') return s;
    return parseFloat(s.toString().replace(/[^\d.-]/g, '')) || 0; 
}
function formatMoney(n){ return n.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }

$(document).ready(function(){
  $('.select2').select2({ theme: 'bootstrap4', width: '100%' });

  $('#status, #payment_status, #vendor_id, #branch_id, #grn_from, #grn_to').on('change', function(){ $('#filterForm').submit(); });
  $('#email, #phone, #grn_number').on('keyup', function(){ 
      clearTimeout(window.t); 
      window.t = setTimeout(function(){ $('#filterForm').submit(); }, 800); 
  });

  $('#clearFilters').on('click', function(){
    $('#vendor_id, #status, #payment_status, #branch_id').val('').trigger('change');
    $('#email, #phone, #grn_number, #grn_from, #grn_to').val('');
    $('#filterForm').submit();
  });

  $('#vendorsTable').DataTable({
    pageLength: 25, 
    order: [[0,'asc']], 
    scrollX: true,
    dom: '<"d-flex justify-content-between mb-3"lBf>rtip',
    buttons: [
      { extend:'excelHtml5', title:'Vendor-Summary', className: 'btn btn-sm btn-outline-success rounded-pill px-3', exportOptions:{columns:[0,1,2,3,4,5]} },
      { extend:'print', title:'Vendor Summary', className: 'btn btn-sm btn-outline-info rounded-pill px-3', exportOptions:{columns:[0,3,4,5]} }
    ],
    language: {
      emptyTable: "No vendors found.",
      zeroRecords: "No vendors found."
    },
    footerCallback: function(row, data, start, end, display){
      var api = this.api();
      var bill = api.column(3,{page:'current'}).data().reduce((a,b)=>parseMoney(a)+parseMoney(b),0);
      var paid = api.column(4,{page:'current'}).data().reduce((a,b)=>parseMoney(a)+parseMoney(b),0);
      var bal  = api.column(5,{page:'current'}).data().reduce((a,b)=>parseMoney(a)+parseMoney(b),0);
      
      $(api.column(3).footer()).html('₹' + formatMoney(bill));
      $(api.column(4).footer()).html('₹' + formatMoney(paid));
      $(api.column(5).footer()).html('₹' + formatMoney(bal));
    }
  });
});
</script>

<?php require_once("footer.php"); ?>