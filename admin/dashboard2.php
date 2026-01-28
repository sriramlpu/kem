<?php
require_once("header.php");
require_once("nav.php");
require_once("../functions.php");

/**
 * Access Control
 */
if (
    !isset($_SESSION["roleName"]) ||
    ($_SESSION["roleName"] !== 'Requester' && $_SESSION["roleName"] !== 'Admin')
) {
    session_destroy();
    echo '<script>alert("Access denied. Only Authorized can login."); window.location.href = "../login.php";</script>';
    exit;
}

$userId = $_SESSION["userId"];

/**
 * UTILS
 */
$active = $_GET['tab'] ?? 'dashboard';
$sub = strtolower((string)($_GET['sub'] ?? 'pending'));
$mapStatus = ['pending' => 'SUBMITTED', 'approved' => 'APPROVED', 'cashier' => 'READY_FOR_CASHIER', 'paid' => 'PAID'];
if (!isset($mapStatus[$sub])) $sub = 'pending';
$statusFilter = $mapStatus[$sub];

function h($x)
{
    return htmlspecialchars((string)$x, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * SCHEMA HELPERS
 */
function table_exists(string $t): bool
{
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', $t);
    $rows = exeSql("SHOW TABLES LIKE '$t'");
    return is_array($rows) && count($rows) > 0;
}

function has_col(string $table, string $col): bool
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $col = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
    $rows = exeSql("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$table' AND COLUMN_NAME='$col' LIMIT 1");
    return is_array($rows) && count($rows) > 0;
}

/**
 * ACTION HANDLERS
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_cashier') {
    $rid = (int)($_POST['request_id'] ?? 0);
    $row = exeSql("SELECT status FROM payment_requests WHERE request_id=$rid LIMIT 1");
    $cur = $row ? (string)($row[0]['status'] ?? '') : '';

    if ($cur === 'APPROVED') {
        $now = date('Y-m-d H:i:s');
        upData('payment_requests', [
            'status' => 'READY_FOR_CASHIER',
            'updated_at' => $now
        ], ["request_id=$rid"]);

        if (table_exists('payment_actions')) {
            insData('payment_actions', [
                'request_id' => $rid,
                'action' => 'SEND_TO_CASHIER',
                'actor_id' => (int)($_SESSION['userId'] ?? 1),
                'comment' => 'Forwarded to Cashier from Requester dashboard',
                'acted_at' => $now
            ]);
        }
    }
    // Correct redirection using extensionless URL
    header("Location: dashboard?tab=" . urlencode($active) . "&sub=" . urlencode($sub));
    exit;
}

/**
 * LOOKUP HELPERS
 */
$_CACHE = ['B' => [], 'V' => [], 'E' => []];

function branch_name(?int $id): string
{
    global $_CACHE;
    if (!$id) return 'N/A';
    if (isset($_CACHE['B'][$id])) return $_CACHE['B'][$id];
    $name = '';
    $table = table_exists('branches') ? 'branches' : 'branch';
    $r = exeSql("SELECT branch_name FROM $table WHERE branch_id=$id LIMIT 1");
    if ($r) $name = (string)$r[0]['branch_name'];
    return $_CACHE['B'][$id] = $name ?: 'Unknown';
}

function vendor_name(?int $id): string
{
    global $_CACHE;
    if (!$id) return 'N/A';
    if (isset($_CACHE['V'][$id])) return $_CACHE['V'][$id];
    $name = '';
    $table = table_exists('vendors') ? 'vendors' : 'vendor';
    $r = exeSql("SELECT vendor_name FROM $table WHERE vendor_id=$id LIMIT 1");
    if ($r) $name = (string)$r[0]['vendor_name'];
    return $_CACHE['V'][$id] = $name ?: 'Unknown';
}

function employee_info(?int $id): array
{
    global $_CACHE;
    if (!$id) return ['name' => 'N/A', 'salary' => 0.0];
    if (isset($_CACHE['E'][$id])) return $_CACHE['E'][$id];
    $row = ['name' => 'N/A', 'salary' => 0.0];
    $table = table_exists('employees') ? 'employees' : 'employee';
    $r = exeSql("SELECT employee_name AS name, salary FROM $table WHERE id=$id LIMIT 1");
    if ($r) $row = $r[0];
    return $_CACHE['E'][$id] = ['name' => $row['name'], 'salary' => (float)$row['salary']];
}

/**
 * DATA FETCHING
 */
function vendor_grn_summary(array $grn_ids): array
{
    $ids = array_values(array_unique(array_map('intval', $grn_ids)));
    if (!$ids) return ['grn_numbers' => [], 'total' => 0.0, 'paid' => 0.0];
    $idCsv = implode(',', $ids);
    $rows = exeSql("SELECT grn_number FROM goods_receipts WHERE grn_id IN ($idCsv)");
    return ['grn_numbers' => array_column($rows ?: [], 'grn_number')];
}

$allRows = exeSql("SELECT * FROM payment_requests ORDER BY updated_at DESC, request_id DESC") ?: [];
$kpis = ['SUBMITTED' => 0, 'APPROVED' => 0, 'READY_FOR_CASHIER' => 0, 'PAID' => 0];
foreach ($allRows as $r) {
    if (isset($kpis[$r['status']])) $kpis[$r['status']]++;
}

/**
 * RENDER HELPERS
 */
function badge(string $s): string
{
    $c = match ($s) {
        'SUBMITTED' => 'primary',
        'APPROVED' => 'success',
        'READY_FOR_CASHIER' => 'info',
        'PAID' => 'secondary',
        default => 'light'
    };
    return '<span class="badge bg-' . $c . '">' . h($s) . '</span>';
}

function subActive($name, $current)
{
    return strtolower($name) === strtolower($current) ? 'active' : '';
}

/**
 * TABLE RENDERER: CONSOLIDATED
 */
function render_consolidated_table(array $rows, string $emptyMsg = 'No data.', string $currentTab = 'dashboard')
{ ?>
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body p-2">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle small">
                    <thead class="table-light">
                        <tr>
                            <th>Type</th>
                            <th>Details / Breakdown</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?><tr>
                                <td colspan="5" class="text-center py-4 text-muted small italic"><?= h($emptyMsg) ?></td>
                            </tr>
                            <?php else: foreach ($rows as $r):
                                $type = (string)($r['request_type'] ?? 'unknown');
                                $p = json_decode($r['payload_json'] ?? '{}', true) ?: [];

                                if ($type === 'employee') {
                                    $details = "<strong>Payroll: " . h(employee_info((int)$r['employee_id'])['name']) . "</strong><br>";
                                    $gross = $p['gross_salary'] ?? 0;
                                    $lop = $p['lop_amount'] ?? 0;
                                    $pf = $p['pf_deduction'] ?? 0;
                                    $esi = $p['esi_deduction'] ?? 0;
                                    $details .= "<small class='text-muted'>Gross: ₹$gross | LOP: ₹$lop | PF: ₹$pf | ESI: ₹$esi</small>";
                                } elseif ($type === 'vendor') {
                                    $details = "Vendor: " . h(vendor_name((int)$r['vendor_id']));
                                } else {
                                    $details = "Purpose: " . h($p['purpose'] ?? ($p['fixed_id'] ?? 'N/A'));
                                }
                            ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?= ucfirst($type) ?></span></td>
                                    <td><?= $details ?></td>
                                    <td><strong class="text-primary">₹<?= number_format((float)$r['total_amount'], 2) ?></strong></td>
                                    <td><?= badge($r['status']) ?></td>
                                    <td>
                                        <?php if ($r['status'] === 'SUBMITTED'): ?>
                                            <a class="btn btn-sm btn-outline-primary" href="<?= ($type === 'employee' ? 'payroll' : 'payment') ?>?rid=<?= $r['request_id'] ?>">Edit</a>
                                        <?php elseif ($r['status'] === 'APPROVED'): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="send_cashier">
                                                <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                                <button class="btn btn-sm btn-primary">Send to Cashier</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                        <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php }

/**
 * KPI CARDS
 */
function render_type_kpi_cards(string $type)
{
    $rows = exeSql("SELECT status FROM payment_requests WHERE request_type='" . addslashes($type) . "'");
    $k = ['SUBMITTED' => 0, 'APPROVED' => 0, 'READY_FOR_CASHIER' => 0, 'PAID' => 0];
    foreach ($rows ?: [] as $r) {
        if (isset($k[$r['status']])) $k[$r['status']]++;
    }
    $t = ucfirst($type);
?>
    <div class="row g-3 mb-4 text-center">
        <?php foreach (['SUBMITTED' => ['Pending', 'primary', 'pending'], 'APPROVED' => ['Approved', 'success', 'approved'], 'READY_FOR_CASHIER' => ['With Cashier', 'info', 'cashier'], 'PAID' => ['Completed', 'secondary', 'paid']] as $st => $cfg): ?>
            <div class="col-md-3">
                <a class="text-decoration-none" href="dashboard?tab=<?= $type ?>&sub=<?= $cfg[2] ?>">
                    <div class="p-3 border rounded bg-white shadow-sm h-100">
                        <div class="small fw-bold text-muted text-uppercase"><?= $cfg[0] ?> <?= $t ?></div>
                        <div class="h3 mb-0 fw-bold text-<?= $cfg[1] ?>"><?= $k[$st] ?></div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php
}

/**
 * CATEGORY TABLE
 */
function render_type_table(array $rows, string $type)
{ ?>
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body p-2">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle small">
                    <thead class="table-light">
                        <tr>
                            <th><?= $type === 'vendor' ? 'Vendor' : ($type === 'employee' ? 'Employee' : 'Purpose') ?></th>
                            <th>Details</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?><tr>
                                <td colspan="5" class="text-center py-4 text-muted small italic">No requests found.</td>
                            </tr>
                            <?php else: foreach ($rows as $r):
                                $p = json_decode($r['payload_json'] ?? '{}', true) ?: [];
                            ?>
                                <tr>
                                    <td><?= $type === 'vendor' ? h(vendor_name((int)$r['vendor_id'])) : ($type === 'employee' ? h(employee_info((int)$r['employee_id'])['name']) : h($p['purpose'] ?? 'N/A')) ?></td>
                                    <td>
                                        <?php if ($type === 'employee'): ?>
                                            Period: <?= h($p['pay_period'] ?? 'N/A') ?>
                                        <?php elseif ($type === 'vendor'): ?>
                                            GRNs: <?= implode(', ', (array)($p['grn_ids'] ?? [])) ?>
                                        <?php else: ?>
                                            <?= h($p['notes'] ?? '—') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong class="text-dark">₹<?= number_format((float)$r['total_amount'], 2) ?></strong></td>
                                    <td><?= badge($r['status']) ?></td>
                                    <td>
                                        <?php if ($r['status'] === 'APPROVED'): ?>
                                            <form method="post" class="d-inline"><input type="hidden" name="action" value="send_cashier"><input type="hidden" name="request_id" value="<?= $r['request_id'] ?>"><button class="btn btn-sm btn-primary">Forward</button></form>
                                        <?php elseif ($r['status'] === 'SUBMITTED'): ?>
                                            <a class="btn btn-sm btn-outline-primary" href="<?= ($type === 'employee' ? 'payroll' : 'payment') ?>?rid=<?= $r['request_id'] ?>">Edit</a>
                                            <?php else: ?>—<?php endif; ?>
                                    </td>
                                </tr>
                        <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php }
?>

<style>
    .page-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 20px 0;
    }

    .nav-tabs .nav-link {
        border: none;
        padding: 12px 20px;
        font-weight: 700;
        color: #6c757d;
    }

    .nav-tabs .nav-link.active {
        color: #0d6efd;
        border-bottom: 3px solid #0d6efd !important;
        background: transparent;
    }
</style>

<header class="page-title border-bottom pb-3">
    <h2 class="mb-0 text-dark fw-bold">Requester Dashboard</h2>
    <div class="d-flex gap-2">
        <a href="payroll" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">🚀 Payroll Entry</a>
        <a href="payment" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">➕ New Payment Request</a>
    </div>
</header>

<ul class="nav nav-tabs mb-4 border-0">
    <?php foreach (['dashboard' => 'All Overview', 'vendor' => 'Vendors', 'employee' => 'Employees', 'expenses' => 'Expenses', 'fixed' => 'Fixed Expenses'] as $t => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $active === $t ? 'active' : '' ?>" href="dashboard?tab=<?= $t ?>&sub=pending">
                <?= $label ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="tab-content">
    <?php if ($active === 'dashboard'): ?>
        <div class="row g-3 mb-4 text-center">
            <?php foreach (['SUBMITTED' => ['Pending', 'primary', 'pending'], 'APPROVED' => ['Approved', 'success', 'approved'], 'READY_FOR_CASHIER' => ['With Cashier', 'info', 'cashier'], 'PAID' => ['Completed', 'secondary', 'paid']] as $st => $cfg): ?>
                <div class="col-md-3">
                    <a class="text-decoration-none" href="dashboard?tab=dashboard&sub=<?= $cfg[2] ?>">
                        <div class="p-3 bg-white border rounded shadow-sm h-100">
                            <div class="small text-muted fw-bold text-uppercase"><?= $cfg[0] ?> Requests</div>
                            <div class="h2 mb-0 fw-bold text-<?= $cfg[1] ?>"><?= $kpis[$st] ?></div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <ul class="nav nav-pills mb-3 gap-2">
            <?php foreach ($mapStatus as $key => $st): ?>
                <li class="nav-item"><a class="nav-link rounded-pill px-4 <?= $sub === $key ? 'active shadow' : 'bg-white border text-dark' ?>" href="dashboard?tab=dashboard&sub=<?= $key ?>"><?= ucfirst($key) ?></a></li>
            <?php endforeach; ?>
        </ul>

        <?php
        $filtered = array_filter($allRows, fn($r) => $r['status'] === $statusFilter);
        render_consolidated_table($filtered, "No $sub requests found.", 'dashboard');
        ?>

    <?php else: ?>
        <h5 class="mb-3 text-muted fw-bold text-uppercase small"><?= ucfirst($active) ?> Overview</h5>
        <?php render_type_kpi_cards($active); ?>

        <ul class="nav nav-pills mb-3 gap-2">
            <?php foreach ($mapStatus as $key => $st): ?>
                <li class="nav-item"><a class="nav-link rounded-pill px-4 <?= $sub === $key ? 'active shadow' : 'bg-white border text-dark' ?>" href="dashboard?tab=<?= h($active) ?>&sub=<?= $key ?>"><?= ucfirst($key) ?></a></li>
            <?php endforeach; ?>
        </ul>

        <?php
        $filtered = array_filter($allRows, fn($r) => $r['request_type'] === $active && $r['status'] === $statusFilter);
        render_type_table($filtered, $active);
        ?>
    <?php endif; ?>
</div>

<?php require_once("footer.php"); ?>