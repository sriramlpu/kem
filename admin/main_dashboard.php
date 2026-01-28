<?php
require_once("header.php");
require_once("nav.php");

// Helper: safe escape for output
function e($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// Helper to run a query and return results safely
function runAll($dbObj, $sql){
    return $dbObj->getAllResults($sql) ?: [];
}

// 1) Pending PO Edit Requests
$poEditSql = "SELECT r.request_id, r.po_id, r.requested_by, r.request_date, r.status, u.username AS requested_by_name
FROM po_edit_requests r
LEFT JOIN users u ON u.user_id = r.requested_by
WHERE r.status = 'Pending' ORDER BY r.request_date DESC";
$poEditRes = runAll($dbObj, $poEditSql);

// 2) Open Indents (status = Opened)
$indentSql = "SELECT i.indent_id, i.indent_number, i.raised_by, i.indent_date, i.status, u.username AS raised_by_name
FROM indents i
LEFT JOIN users u ON u.user_id = i.raised_by
WHERE i.status = 'Opened' ORDER BY i.created_at DESC";
$indentRes = runAll($dbObj, $indentSql);

// 3) GRNs created this month
$grnCountSql = "SELECT COUNT(*) AS cnt FROM goods_receipts WHERE MONTH(grn_date)=MONTH(CURDATE()) AND YEAR(grn_date)=YEAR(CURDATE())";
$grnCountR = $dbObj->getOneRow($grnCountSql);
$grnCount = ($grnCountR && isset($grnCountR['cnt'])) ? (int)$grnCountR['cnt'] : 0;

// 4) PO summary this month by status
$poSummarySql = "SELECT status, COUNT(*) AS cnt FROM purchase_orders WHERE MONTH(po_date)=MONTH(CURDATE()) AND YEAR(po_date)=YEAR(CURDATE()) GROUP BY status";
$poSummaryRows = runAll($dbObj, $poSummarySql);
$poSummary = ['Pending'=>0,'Partially Fulfilled'=>0,'Completed'=>0,'Cancelled'=>0];
foreach ($poSummaryRows as $row){
    $status = $row['status'] ?? '';
    if (array_key_exists($status, $poSummary)) $poSummary[$status] = (int)$row['cnt'];
}

// 5) Recent 5 GRNs
$recentGrnSql = "SELECT g.grn_id, g.grn_number, g.grn_date, g.total_amount, v.vendor_name
FROM goods_receipts g
LEFT JOIN vendors v ON v.vendor_id = g.vendor_id
ORDER BY g.created_at DESC LIMIT 5";
$recentGrnRes = runAll($dbObj, $recentGrnSql);

// 6) Recent 5 POs
$recentPoSql = "SELECT p.po_id, p.order_number, p.po_date, p.total_amount, p.status, v.vendor_name
FROM purchase_orders p
LEFT JOIN vendors v ON v.vendor_id = p.vendor_id
ORDER BY p.po_date DESC LIMIT 5";
$recentPoRes = runAll($dbObj, $recentPoSql);

?>

  <style>
    .card-stat { padding: 1rem; }
    .small-muted { font-size: 0.85rem; color: #6c757d; }
    table td, table th { vertical-align: middle; }
  </style>

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Admin Dashboard</h2>
    <div><small class="small-muted">Generated: <?php echo date('Y-m-d H:i:s'); ?></small></div>
  </div>

  <!-- Stats Row -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card card-stat">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-0"><?php echo e($grnCount); ?></h5>
            <div class="small-muted">GRNs this month</div>
          </div>
          <div class="fs-3">📦</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card card-stat">
        <div>
          <h5 class="mb-0"><?php echo e($poSummary['Pending']); ?></h5>
          <div class="small-muted">POs - Pending (this month)</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card card-stat">
        <div>
          <h5 class="mb-0"><?php echo e($poSummary['Partially Fulfilled']); ?></h5>
          <div class="small-muted">POs - Partially Fulfilled</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card card-stat">
        <div>
          <h5 class="mb-0"><?php echo e($poSummary['Completed']); ?></h5>
          <div class="small-muted">POs - Completed</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-6 mb-4">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Pending PO Edit Requests</strong>
          <span class="badge bg-warning text-dark"><?php echo count($poEditRes); ?></span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead class="table-light">
                <tr>
                  <th>#</th>
                  <th>PO ID</th>
                  <th>Requested By</th>
                  <th>Requested At</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($poEditRes)): $i=1; foreach($poEditRes as $row): ?>
                <tr>
                  <td><?php echo $i++; ?></td>
                  <td><?php echo e($row['po_id']); ?></td>
                  <td><?php echo e($row['requested_by_name'] ?: $row['requested_by']); ?></td>
                  <td><?php echo e($row['request_date']); ?></td>
                  <td><span class="badge bg-warning text-dark"><?php echo e($row['status']); ?></span></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="5" class="text-center small-muted">No pending requests</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6 mb-4">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Open Indents (Notifications)</strong>
          <span class="badge bg-info"><?php echo count($indentRes); ?></span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead class="table-light">
                <tr>
                  <th>#</th>
                  <th>Indent No</th>
                  <th>Raised By</th>
                  <th>Indent Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($indentRes)): $j=1; foreach($indentRes as $r): ?>
                <tr>
                  <td><?php echo $j++; ?></td>
                  <td><?php echo e($r['indent_number']); ?></td>
                  <td><?php echo e($r['raised_by_name'] ?: $r['raised_by']); ?></td>
                  <td><?php echo e($r['indent_date']); ?></td>
                  <td><span class="badge bg-primary"><?php echo e($r['status']); ?></span></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="5" class="text-center small-muted">No open indents</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Lists -->
  <div class="row">
    <div class="col-lg-6 mb-4">
      <div class="card">
        <div class="card-header"><strong>Recent GRNs</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead class="table-light"><tr><th>#</th><th>GRN No</th><th>Date</th><th>Vendor</th><th>Amount</th></tr></thead>
              <tbody>
                <?php if (count($recentGrnRes)): $k=1; foreach($recentGrnRes as $g): ?>
                <tr>
                  <td><?php echo $k++; ?></td>
                  <td><?php echo e($g['grn_number'] ?: $g['grn_id']); ?></td>
                  <td><?php echo e($g['grn_date']); ?></td>
                  <td><?php echo e($g['vendor_name'] ?? '-'); ?></td>
                  <td><?php echo e(number_format((float)$g['total_amount'],2)); ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="5" class="text-center small-muted">No GRNs found</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6 mb-4">
      <div class="card">
        <div class="card-header"><strong>Recent POs</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead class="table-light"><tr><th>#</th><th>PO No</th><th>Date</th><th>Vendor</th><th>Status</th></tr></thead>
              <tbody>
                <?php if (count($recentPoRes)): $m=1; foreach($recentPoRes as $p): ?>
                <tr>
                  <td><?php echo $m++; ?></td>
                  <td><?php echo e($p['order_number'] ?: $p['po_id']); ?></td>
                  <td><?php echo e($p['po_date']); ?></td>
                  <td><?php echo e($p['vendor_name'] ?? '-'); ?></td>
                  <td><span class="badge bg-secondary"><?php echo e($p['status']); ?></span></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="5" class="text-center small-muted">No POs found</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php
require_once("footer.php")
?>
