<?php
/**
 * CASHIER: Advance History Page.
 * UPDATED: Integrated with standard portal design (header/nav/footer).
 * UPDATED: Functional "View" feature with detailed Modal.
 */
require_once("../auth.php");
requireRole(['Cashier', 'Admin']);

require_once("header.php");
require_once("nav.php");

/**
 * 1. FILTER PARAMETERS
 */
$filter_type = $_GET['type'] ?? 'all';
$filter_entity_id = (int)($_GET['entity_id'] ?? 0);
$filter_start = $_GET['start_date'] ?? date('Y-m-01');
$filter_end = $_GET['end_date'] ?? date('Y-m-d');

/**
 * 2. DATA LOOKUPS FOR FILTERS
 */
$vendors = exeSql("SELECT vendor_id, vendor_name FROM vendors WHERE status = 'Active' ORDER BY vendor_name") ?: [];
$employees = exeSql("SELECT id, employee_name, employee_uid FROM employees ORDER BY employee_name") ?: [];

/**
 * 3. MAIN QUERY BUILDING
 */
$where = ["DATE(a.created_at) BETWEEN '$filter_start' AND '$filter_end'"];

if ($filter_type !== 'all') {
    $where[] = "a.entity_type = '" . addslashes($filter_type) . "'";
}
if ($filter_entity_id > 0) {
    $where[] = "a.entity_id = $filter_entity_id";
}

$where_clause = implode(" AND ", $where);

// Joined with users to get creator_name
$sql = "SELECT a.*, 
        CASE a.entity_type 
            WHEN 'vendor' THEN v.vendor_name 
            WHEN 'employee' THEN e.employee_name 
            ELSE 'N/A' 
        END AS entity_name,
        u.username AS creator_name
        FROM advances a
        LEFT JOIN vendors v ON a.entity_type = 'vendor' AND a.entity_id = v.vendor_id
        LEFT JOIN employees e ON a.entity_type = 'employee' AND a.entity_id = e.id
        LEFT JOIN users u ON a.created_by = u.user_id
        WHERE $where_clause
        ORDER BY a.advance_id DESC";

$history = exeSql($sql) ?: [];

/**
 * Helper to display description/notes safely
 */
function short_notes($text, $limit = 60) {
    if (!$text) return 'N/A';
    if (strlen($text) <= $limit) return h($text);
    return '<span title="'.h($text).'">'.h(substr($text, 0, $limit)).'...</span>';
}
?>

<style>
    .filter-card { background: #fff; border: 1px solid #eef2f3; border-radius: 16px; }
    .history-table thead th { background-color: #f8fafb; color: #6c757d; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; border: none; }
    .history-table tbody td { border-bottom: 1px solid #f1f3f5; font-size: 0.85rem; padding: 15px 12px; }
    .badge-status { font-size: 0.65rem; padding: 5px 10px; border-radius: 30px; font-weight: 700; }
    .modal-detail-label { font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
    .modal-detail-value { font-size: 0.95rem; font-weight: 600; color: #1e293b; }
</style>

<div class="container-fluid">
    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <div>
            <h2 class="fw-bold h4 mb-0 text-dark">Advance Payment History</h2>
            <p class="text-muted small mb-0">Track all funds disbursed as advances to vendors or staff.</p>
        </div>
        <a href="cdashboard.php" class="btn btn-outline-secondary rounded-pill px-4 fw-bold shadow-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Desk
        </a>
    </div>

    <!-- Filter Bar -->
    <div class="card filter-card shadow-sm mb-4 border-0">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-muted">Entity Type</label>
                    <select name="type" id="type_filter" class="form-select border-2 rounded-3">
                        <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>All Entities</option>
                        <option value="vendor" <?= $filter_type === 'vendor' ? 'selected' : '' ?>>Vendors</option>
                        <option value="employee" <?= $filter_type === 'employee' ? 'selected' : '' ?>>Employees</option>
                    </select>
                </div>

                <div class="col-md-3" id="entity_wrap" style="<?= $filter_type === 'all' ? 'display:none;' : '' ?>">
                    <label class="form-label fw-bold small text-muted" id="entity_label">Select Entity</label>
                    <select name="entity_id" id="entity_id" class="form-select border-2 rounded-3">
                        <option value="0">-- Select --</option>
                        <?php if ($filter_type === 'vendor'): ?>
                            <?php foreach ($vendors as $v): ?>
                                <option value="<?= $v['vendor_id'] ?>" <?= $filter_entity_id == $v['vendor_id'] ? 'selected' : '' ?>><?= h($v['vendor_name']) ?></option>
                            <?php endforeach; ?>
                        <?php elseif ($filter_type === 'employee'): ?>
                            <?php foreach ($employees as $e): ?>
                                <option value="<?= $e['id'] ?>" <?= $filter_entity_id == $e['id'] ? 'selected' : '' ?>><?= h($e['employee_name']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-bold small text-muted">Start Date</label>
                    <input type="date" name="start_date" class="form-control border-2 rounded-3" value="<?= h($filter_start) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-bold small text-muted">End Date</label>
                    <input type="date" name="end_date" class="form-control border-2 rounded-3" value="<?= h($filter_end) ?>">
                </div>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100 py-2 rounded-pill fw-bold shadow-sm">
                        <i class="bi bi-filter me-1"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Table -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-5">
        <div class="table-responsive">
            <table class="table table-hover history-table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Date</th>
                        <th>Payee Name</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Description / Purpose</th>
                        <th class="pe-4 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted italic">No advance records found for the selected filters.</td></tr>
                    <?php else: foreach ($history as $row): 
                        $isVendor = ($row['entity_type'] === 'vendor');
                    ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?= date('d M Y', strtotime($row['created_at'] ?? $row['advance_date'])) ?></div>
                                <div class="text-muted" style="font-size:0.7rem;"><?= date('h:i A', strtotime($row['created_at'])) ?></div>
                            </td>
                            <td><strong><?= h($row['entity_name']) ?></strong></td>
                            <td>
                                <span class="badge badge-status bg-<?= $isVendor ? 'danger' : 'primary' ?>-subtle text-<?= $isVendor ? 'danger' : 'primary' ?> border border-<?= $isVendor ? 'danger' : 'primary' ?>-subtle">
                                    <?= ucfirst($row['entity_type']) ?>
                                </span>
                            </td>
                            <td><strong class="text-primary fs-6">₹<?= number_format((float)$row['amount'], 2) ?></strong></td>
                            <td>
                                <span class="badge badge-status bg-<?= $row['status'] === 'Active' ? 'warning' : 'success' ?> shadow-none">
                                    <?= strtoupper($row['status']) ?>
                                </span>
                            </td>
                            <td><?= short_notes($row['description'] ?? $row['notes'] ?? 'N/A') ?></td>
                            <td class="pe-4 text-end">
                                <button type="button" 
                                        class="btn btn-sm btn-light border rounded-pill px-3 fw-bold" 
                                        onclick='showAdvanceDetails(<?= json_encode($row) ?>)'>
                                    View
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADVANCE DETAILS MODAL -->
<div class="modal fade" id="advanceDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="fw-bold text-dark mb-0">Advance Receipt Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="modal-detail-label">Total Disbursed</div>
                    <div id="m_amount" class="h2 fw-bold text-primary mb-1">₹0.00</div>
                    <span id="m_status" class="badge rounded-pill bg-warning shadow-none">ACTIVE</span>
                </div>

                <div class="row g-3">
                    <div class="col-6">
                        <div class="modal-detail-label">Payee / Recipient</div>
                        <div id="m_entity_name" class="modal-detail-value">-</div>
                    </div>
                    <div class="col-6">
                        <div class="modal-detail-label">Entity Type</div>
                        <div id="m_entity_type" class="modal-detail-value">-</div>
                    </div>
                    <hr class="my-2 opacity-50">
                    <div class="col-6">
                        <div class="modal-detail-label">Advance Date</div>
                        <div id="m_advance_date" class="modal-detail-value">-</div>
                    </div>
                    <div class="col-6">
                        <div class="modal-detail-label">Method / Mode</div>
                        <div id="m_payment_method" class="modal-detail-value">-</div>
                    </div>
                    <div class="col-6">
                        <div class="modal-detail-label">Reference / UTR</div>
                        <div id="m_ref_number" class="modal-detail-value">-</div>
                    </div>
                    <div class="col-6">
                        <div class="modal-detail-label">Created By</div>
                        <div id="m_creator" class="modal-detail-value">-</div>
                    </div>
                    <hr class="my-2 opacity-50">
                    <div class="col-12">
                        <div class="modal-detail-label">Breakdown</div>
                        <div class="d-flex justify-content-between small mb-1 text-muted">
                            <span>Recovered to Date:</span>
                            <span id="m_recovered" class="fw-bold text-success">₹0.00</span>
                        </div>
                        <div class="d-flex justify-content-between small fw-bold text-dark">
                            <span>Outstanding Balance:</span>
                            <span id="m_balance">₹0.00</span>
                        </div>
                    </div>
                    <div class="col-12 mt-3 bg-light p-3 rounded-3">
                        <div class="modal-detail-label mb-1">Description / Notes</div>
                        <div id="m_description" class="small text-secondary">-</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function showAdvanceDetails(data) {
        const modal = new bootstrap.Modal(document.getElementById('advanceDetailModal'));
        
        document.getElementById('m_amount').textContent = '₹' + parseFloat(data.amount).toLocaleString('en-IN', {minimumFractionDigits: 2});
        document.getElementById('m_entity_name').textContent = data.entity_name;
        document.getElementById('m_entity_type').textContent = data.entity_type.toUpperCase();
        document.getElementById('m_status').textContent = data.status.toUpperCase();
        document.getElementById('m_status').className = 'badge rounded-pill shadow-none ' + (data.status === 'Active' ? 'bg-warning text-dark' : 'bg-success');
        
        document.getElementById('m_advance_date').textContent = data.advance_date || data.created_at.split(' ')[0];
        document.getElementById('m_payment_method').textContent = data.payment_method || 'N/A';
        document.getElementById('m_ref_number').textContent = data.ref_number || 'N/A';
        document.getElementById('m_creator').textContent = data.creator_name || 'System';
        
        const recovered = parseFloat(data.recovered_amount || 0);
        const balance = parseFloat(data.amount) - recovered;
        
        document.getElementById('m_recovered').textContent = '₹' + recovered.toLocaleString('en-IN', {minimumFractionDigits: 2});
        document.getElementById('m_balance').textContent = '₹' + balance.toLocaleString('en-IN', {minimumFractionDigits: 2});
        document.getElementById('m_description').textContent = data.description || data.notes || 'No description provided.';
        
        modal.show();
    }

    document.addEventListener('DOMContentLoaded', function() {
        const typeSelect = document.getElementById('type_filter');
        const entityWrap = document.getElementById('entity_wrap');
        const entitySelect = document.getElementById('entity_id');
        const entityLabel = document.getElementById('entity_label');

        const vendors = <?= json_encode($vendors) ?>;
        const employees = <?= json_encode($employees) ?>;

        typeSelect.addEventListener('change', function() {
            const val = this.value;
            if (val === 'all') {
                entityWrap.style.display = 'none';
                entitySelect.innerHTML = '<option value="0">-- Select --</option>';
            } else {
                entityWrap.style.display = 'block';
                entityLabel.textContent = 'Select ' + val.charAt(0).toUpperCase() + val.slice(1);
                
                let options = '<option value="0">-- Select --</option>';
                const list = (val === 'vendor') ? vendors : employees;
                const idKey = (val === 'vendor') ? 'vendor_id' : 'id';
                const nameKey = (val === 'vendor') ? 'vendor_name' : 'employee_name';

                list.forEach(item => {
                    options += `<option value="${item[idKey]}">${item[nameKey]}</option>`;
                });
                entitySelect.innerHTML = options;
            }
        });
    });
</script>

<?php require_once("footer.php"); ?>