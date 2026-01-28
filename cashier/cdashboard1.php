<?php
/**
 * CASHIER: Dashboard Customized for Payment Workflow.
 * - FINAL FIX: Cashier Dashboard displays payment requests pending for payment and completed payments.
 * - NEW FEATURE: Added 'fixed' as a new request type/tab for the Cashier.
 * - UPDATED: Display requester username and approver username
 * Path: kmk/finance/cashier/dashboard.php
 * * NOTE ON HTTP 500 ERROR: If this code produces an HTTP 500 error, 
 * the problem is almost certainly a fatal error inside the required 'functions.php'
 * or the 'exeSql' function that it contains. Ensure that file exists and works correctly.
 */
require_once("../auth.php");
requireRole(['Cashier','Admin']);
session_start(); 

// Use trim() and strtolower() for robust role checking
$userRole = strtolower(trim($_SESSION['roleName'] ?? ''));
$userName = ($_SESSION['userName'] ?? 'User');
$isAdmin = ($userRole === 'admin');

// --- ADMIN DASHBOARD LINKS ---
// NOTE: Update these paths if your file structure is different
$requesterLink = '../admin/dashboard.php'; 
$approverLink = '../approver/dashboard.php';
$financeLink = '../finance'; // Assuming the main Finance panel (Admin panel from previous context)
// --- END ADMIN DASHBOARD LINKS ---


// !!! CRITICAL CHECK: This file MUST exist and MUST NOT contain fatal errors.
require __DIR__ . '/../functions.php';

/* ---------------- Small helpers ---------------- */
function v($k, $d = null)
{
	return $_POST[$k] ?? $_GET[$k] ?? $d;
}
function i($x)
{
	return is_numeric($x) ? (int)$x : 0;
}
function s($x)
{
	return trim((string)($x ?? ''));
}
function h($x)
{
	return htmlspecialchars((string)($x), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function uid(): int
{
	return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;
}

/* ---------------- Lookups (LIVE from DB Schema) ---------------- */
$_B = [];
$_V = [];
$_E = [];
$_G = [];
$_U = [];	// Cache for user lookups

function branch_name(?int $id): string
{
	global $_B;
	if (!$id) return '';
	if (isset($_B[$id])) return $_B[$id];
	$name = '';
	// Assuming table_exists and exeSql are available via functions.php
	$r = exeSql("SELECT branch_name FROM branches WHERE branch_id=$id LIMIT 1");
	if (is_array($r) && $r) $name = (string)($r[0]['branch_name'] ?? '');
	$_B[$id] = $name;
	return $name;
}

function vendor_name(?int $id): string
{
	global $_V;
	if (!$id) return '';
	if (isset($_V[$id])) return $_V[$id];
	$name = '';
	$r = exeSql("SELECT vendor_name FROM vendors WHERE vendor_id=$id LIMIT 1");
	if (is_array($r) && $r) $name = (string)($r[0]['vendor_name'] ?? '');
	$_V[$id] = $name;
	return $name;
}

function employee_info(?int $id): array
{
	global $_E;
	if (!$id) return ['name' => '', 'salary' => 0.0];
	if (isset($_E[$id])) return $_E[$id];
	$row = [];
	$r = exeSql("SELECT employee_name AS name, salary FROM employees WHERE id=$id LIMIT 1");
	if (is_array($r) && $r) {
		$row = $r[0];
	}
	$_E[$id] = ['name' => $row['name'] ?? '', 'salary' => (float)($row['salary'] ?? 0)];
	return $_E[$id];
}

function user_name(?int $id): string
{
	global $_U;
	if (!$id) return 'N/A';
	if (isset($_U[$id])) return $_U[$id];
	$name = '';
	$r = exeSql("SELECT username FROM users WHERE user_id=$id LIMIT 1");
	if (is_array($r) && $r) $name = (string)($r[0]['username'] ?? '');
	$_U[$id] = $name ?: 'User #' . $id;
	return $_U[$id];
}

function get_grn_numbers(array $ids): array
{
	if (empty($ids)) return [];
	$ids = implode(',', array_map('intval', $ids));
	$r = exeSql("SELECT grn_number FROM goods_receipts WHERE grn_id IN ($ids)");
	return is_array($r) ? array_column($r, 'grn_number') : [];
}

/* ---------------- AMOUNT HELPERS ---------------- */
function paynow_from_payload(array $r): float
{
	$payload = json_decode($r['payload_json'] ?? '{}', true) ?: [];
	return (float)($payload['pay_now'] ?? 0);
}

function approved_amount(array $r): float
{
	if (isset($r['approved_amount']) && $r['approved_amount'] !== '') return (float)$r['approved_amount'];
	return paynow_from_payload($r);
}

// Function placeholder for table_exists (needed by the fixed logic fix below)
function table_exists(string $t): bool {
	$t = preg_replace('/[^a-zA-Z0-9_]/','',$t);
	$rows = exeSql("SHOW TABLES LIKE '$t'");
	return is_array($rows) && $rows;
}


/* ---------------- Fetch requests - STATUS FIX ---------------- */
$ACTIVE_STATUSES = ['READY_FOR_CASHIER', 'PAID'];

function fetch_all(?string $type = null, ?string $status = null): array
{
	global $ACTIVE_STATUSES;
	$w = [];

	if ($type) $w[] = "request_type='" . addslashes($type) . "'";

	if ($status) $w[] = "status='" . addslashes($status) . "'";

	if (!$status) {
		$w[] = "status IN ('" . implode("','", $ACTIVE_STATUSES) . "')";
	}

	$sql = "SELECT * FROM payment_requests " . ($w ? ('WHERE ' . implode(' AND ', $w)) : '') . " ORDER BY updated_at DESC, request_id DESC";

	$rows = exeSql($sql);
	return is_array($rows) ? $rows : [];
}

/* KPI counts - STATUS FIX */
function kpi_counts(array $rows): array
{
	$k = ['PENDING' => 0, 'COMPLETED' => 0];

	foreach ($rows as $r) {
		$status = $r['status'] ?? '';
		if ($status === 'READY_FOR_CASHIER') {
			$k['PENDING']++;
		} elseif ($status === 'PAID') {
			$k['COMPLETED']++;
		}
	}
	return $k;
}

/* ---------------- Build datasets ---------------- */
$active = $_GET['tab'] ?? 'dashboard';

$valid_tabs = ['dashboard', 'vendor', 'employee', 'expenses', 'fixed'];
$types = ['vendor', 'employee', 'expenses', 'fixed'];

if (!in_array($active, $valid_tabs)) { $active = 'dashboard'; }

$allRowsNoTypeFilter = fetch_all();
$allKPI = kpi_counts($allRowsNoTypeFilter);

$dataByType = [];
$kpis = [];
foreach ($types as $t) {
	$rowsByType = fetch_all($t);	
	$kpis[$t] = kpi_counts($rowsByType);
	$dataByType[$t] = [
		// FIX: Using anonymous function for wider PHP compatibility
		'PENDING' => array_values(array_filter($rowsByType, function ($r) {
			return ($r['status'] ?? '') === 'READY_FOR_CASHIER';
		})),
		// FIX: Using anonymous function for wider PHP compatibility
		'COMPLETED' => array_values(array_filter($rowsByType, function ($r) {
			return ($r['status'] ?? '') === 'PAID';
		})),
	];
}

/* ---------------- Render helpers ---------------- */

function badge(string $s): string
{
	$map = [
		'READY_FOR_CASHIER' => 'warning',
		'PAID' => 'success',
	];
	$cls = $map[$s] ?? 'light';
	return '<span class="badge bg-' . $cls . '">' . h($s) . '</span>';
}

/**
 * Renders the KPI row. Includes data attributes for pill linking.
 * @param array $k KPI counts.
 * @param string $type_prefix 'all-' for Dashboard, or 'vendor-', 'employee-', etc., for specific tabs.
 */
function kpi_row(array $k, string $type_prefix = '')
{
	$pending_attrs = 'data-bs-toggle="pill" data-bs-target="#' . $type_prefix . 'pending"';
	$completed_attrs = 'data-bs-toggle="pill" data-bs-target="#' . $type_prefix . 'completed"';

	?>
	<div class="row g-3 mb-4">
		<div class="col-md-6">
			<div class="p-3 kpi-card" <?= $pending_attrs ?>>
				<div>Pending (Ready for Cashier)</div>
				<div class="value text-warning"><?= (int)($k['PENDING'] ?? 0) ?></div>
			</div>
		</div>
		<div class="col-md-6">
			<div class="p-3 kpi-card" <?= $completed_attrs ?>>
				<div>Completed (Paid)</div>
				<div class="value text-success"><?= (int)($k['COMPLETED'] ?? 0) ?></div>
			</div>
		</div>
	</div>
<?php }

function render_cashier_table(array $rows, string $heading)
{
	if (empty($rows)) {
		echo '<div class="alert alert-info shadow-sm mt-3 mb-4">No ' . strtolower(h($heading)) . ' requests found.</div>';
		return;
	}

	// Sort by updated_at descending
	usort($rows, function ($a, $b) {
		return strtotime($b['updated_at'] ?? '0') - strtotime($a['updated_at'] ?? '0');
	});

	?>
	<h4 class="mt-4 mb-3"><?= h($heading) ?></h4>
	<div class="card shadow-sm border-0 mb-3">
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table table-striped table-hover align-middle mb-0">
					<thead class="table-light">
						<tr>
							<th>Request #</th>
							<th>Type</th>
							<th>Reference / GRN Nos</th>
							<th>Requested By</th>
							<th>Approved By</th>
							<th>Req. Amount</th>
							<th>App. Amount</th>
							<th>Status</th>
							<th>Updated</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($rows as $r):
							$payload = json_decode($r['payload_json'] ?? '{}', true) ?: [];
							$ref = '';
							$paidNow = approved_amount($r);
							$total = (float)($r['total_amount'] ?? 0);
							$requested_by_id = (int)($r['requested_by'] ?? 0);
							$approved_by_id = (int)($r['approved_by'] ?? 0);
							$requestor = user_name($requested_by_id);
							$approver = $approved_by_id ? user_name($approved_by_id) : '<span class="text-muted">—</span>';
							$status = $r['status'] ?? 'N/A';

							if (($r['request_type'] ?? '') === 'vendor') {
								$vendor_id = i($r['vendor_id'] ?? 0);
								$grn_ids = (array)($payload['grn_ids'] ?? []);
								$grn_numbers = get_grn_numbers($grn_ids);
								$vend_name = vendor_name($vendor_id);
								$ref = 'Vendor: ' . h($vend_name) . '<br><small class="text-muted">GRNs: ' . h(implode(', ', $grn_numbers)) . '</small>';
							} elseif (($r['request_type'] ?? '') === 'employee') {
								$empId = i($r['employee_id'] ?? 0);
								$info = employee_info($empId);
								$ref = 'Employee: ' . h($info['name']) . '<br><small class="text-muted">ID: ' . $empId . '</small>';
							} elseif (($r['request_type'] ?? '') === 'expenses') {
								$purpose = (string)($payload['purpose'] ?? '');
								if ($purpose === '__other__') $purpose = (string)($payload['custom_purpose'] ?? '');
								$ref = 'Purpose: ' . h($purpose);
							}
							// *** FIXED LOGIC: For 'fixed' requests - attempts to display the expense type/purpose ***
							elseif (($r['request_type'] ?? '') === 'fixed') {
								// 1. Check Payload for purpose/expense_type/item
								$purpose = (string)($payload['purpose'] ?? '');
								$expense_type = (string)($payload['expense_type'] ?? '');
								$item = (string)($payload['item'] ?? '');
								$description = (string)($payload['description'] ?? '');

								$main_ref = '';
								$sub_ref = '';

								// Determine the most specific reference name
								$ref_name = trim($purpose) ?: trim($expense_type) ?: trim($item);

								// Fallback: If ref_name is still empty, look up fixed_expenses table using fixed_id
								if (empty($ref_name)) {
									$fixedId = (int)($payload['fixed_id'] ?? 0);
									if ($fixedId > 0 && function_exists('table_exists') && table_exists('fixed_expenses')) {
										$feRow = exeSql("SELECT expense_type FROM fixed_expenses WHERE id=$fixedId LIMIT 1");
										if ($feRow) {
											$ref_name = (string)($feRow[0]['expense_type'] ?? 'N/A');
										}
									}
								}
								
								// Final clean-up/check for "other" custom purpose
								if ($ref_name === '_other_') {
									$ref_name = (string)($payload['custom_purpose'] ?? 'N/A');
								}

								// Set main reference
								$main_ref = 'Fixed Expense: ' . h(ucfirst($ref_name ?: 'N/A'));

								// Set sub reference using the description if present
								if (trim($description)) {
									$sub_ref = 'Details: ' . h($description);
								}

								// Combine main and sub references
								$ref = $main_ref;
								if ($sub_ref) {
									$ref .= '<br><small class="text-muted">' . $sub_ref . '</small>';
								}
							}
							// *** END FIXED LOGIC ***
						?>
							<tr class="<?= $status === 'READY_FOR_CASHIER' ? 'table-warning' : ($status === 'PAID' ? 'table-success' : '') ?>">
								<td>#<?= (int)($r['request_id'] ?? 0) ?></td>
								<td><?= h(ucfirst($r['request_type'] ?? 'N/A')) ?></td>
								<td><?= $ref ?></td>
								<td>
									<div class="fw-medium"><?= h($requestor) ?></div>
									<small class="text-muted">Requester</small>
								</td>
								<td>
									<div class="fw-medium"><?= $approver ?></div>
									<?php if ($approved_by_id): ?>
										<small class="text-muted">Approver</small>
									<?php endif; ?>
								</td>
								<td>₹<?= number_format($total, 2) ?></td>
								<td>₹<?= number_format($paidNow, 2) ?></td>
								<td><?= badge($status) ?></td>
								<td><?= h($r['updated_at'] ?? 'N/A') ?></td>
								<td>
									<?php if ($status === 'READY_FOR_CASHIER'): ?>
										<a class="btn btn-sm btn-success" href="payment?rid=<?= (int)($r['request_id'] ?? 0) ?>">Pay Now</a>
									<?php else: ?>
										—
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
<?php }

/* ---------------- HTML ---------------- */
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Cashier Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
	body{background:#fff}
	.page-title{
        display:flex;
        justify-content:space-between;
        align-items:center;
        margin:14px 0 18px;
        /* Ensure there's space for the title, buttons, and user info */
        flex-wrap: wrap; 
        gap: 15px;
    }
	.kpi-card{border:1px solid #e5e7eb;border-radius:12px;background:#fafafa; height: 100%; cursor: pointer; transition: background-color 0.15s;}
	.kpi-card:hover {background-color: #f0f0f0;}
	.kpi-card .value{font-size:26px;font-weight:800}
	.tab-content > .tab-pane.active {
		display: block;
	}
	.tab-content > .tab-pane:not(.active) {
		display: none;
	}
	.nav-tabs .nav-link {
		color: #0d6efd;
	}
	.nav-tabs .nav-link.active {
		color: #495057;
		background-color: #fff;
		border-color: #dee2e6 #dee2e6 #fff;
	}

    /* --- Custom Header Styles --- */
    .header-actions {
        display: flex;
        gap: 8px; /* Spacing between Cashier buttons and the new dropdown */
        align-items: center;
        flex-wrap: wrap;
    }
    .user-actions {
        display: flex;
        gap: 12px;
        align-items: center;
    }
    .user-actions .fw-bold {
        white-space: nowrap; 
        max-width: 150px;
        overflow: hidden; 
        text-overflow: ellipsis;
    }

    /* --- STYLES FOR ADMIN DROPDOWN BUTTON --- */
    .kmk-panel-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 6px 12px;
      font-size: 14px;
      font-weight: 600;
      text-decoration: none;
      border-radius: 4px;
      background-color: #0d6efd; 
      color: white;
      border: 1px solid #0d6efd;
      transition: background-color .15s ease, border-color .15s ease, opacity .15s ease;
      white-space: nowrap;
    }

    .kmk-panel-btn:hover {
      background-color: #0b5ed7;
      border-color: #0b5ed7;
      color: white; 
      opacity: 0.9;
    }
    .dropdown-item i {
        margin-right: 6px;
    }
    
    /* --- CSS FOR HOVER DROPDOWN --- */
    /* This overrides the Bootstrap default for click to enable hover */
    .dropdown:hover .dropdown-menu {
        display: block;
        margin-top: 0; 
    }
</style>
</head>
<body class="container-fluid p-4">

<header class="page-title">
	<h2 class="mb-0">Cashier Dashboard</h2>
	
    <div class="header-actions">
        
        
        <a href="advance_history" class="btn btn-outline-info">
			Advance History
		</a>
		<a href="payments_view" class="btn btn-warning">
			Payment View
		</a>
		<a href="advance_payment" class="btn btn-primary">
			Make Advance Payment
		</a>
		<a href="vendor_redemption_management.php" class="btn btn-success">
			Manage Redemption Points
		</a>
		<?php if ($isAdmin): ?>
        <div class="dropdown">
          <button class="btn kmk-panel-btn dropdown-toggle" type="button" aria-expanded="false">
            Dashboards
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow">
            <li><a class="dropdown-item" href="<?php echo $requesterLink; ?>"> Requester </a></li>
            <li><a class="dropdown-item" href="<?php echo $approverLink; ?>"> Approver </a></li>
            <li><a class="dropdown-item" href="<?php echo $financeLink; ?>"> Finance </a></li>
          </ul>
        </div>
        <?php endif; ?>
	</div>

    <div class="user-actions">
    	<span class="d-none d-md-block fw-bold text-secondary">
            Hello, <?php echo $userName; ?>
        </span>
        <a href="../logout.php" class="btn btn-sm btn-danger">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</header>


<ul class="nav nav-tabs mb-3" role="tablist">
	<?php foreach ($valid_tabs as $tab): ?>
	<li class="nav-item">
		<a class="nav-link <?= $active===$tab?'active':'' ?>"	
			href="?tab=<?= h($tab) ?>"	
			role="tab"
			data-bs-toggle="tab"
			data-bs-target="#tab-<?= h($tab) ?>">
			<?= h(ucfirst($tab)) ?><?= $tab === 'dashboard' ? ' (All Types)' : '' ?>
		</a>
	</li>
	<?php endforeach; ?>
</ul>

<div class="tab-content">
	
	<div class="tab-pane <?= $active==='dashboard'?'show active':'' ?>" id="tab-dashboard" role="tabpanel">
		<?php kpi_row($allKPI, 'all-'); ?>

		<ul class="nav nav-pills mb-3" id="dashboard-pills" role="tablist">
			<li class="nav-item">
				<a class="nav-link active" data-bs-toggle="pill" id="all-pending-tab" href="#all-pending" role="tab" aria-controls="all-pending" aria-selected="true">Ready for Payment</a>
			</li>
			<li class="nav-item">
				<a class="nav-link" data-bs-toggle="pill" id="all-completed-tab" href="#all-completed" role="tab" aria-controls="all-completed" aria-selected="false">Paid/Completed</a>
			</li>
		</ul>
		<div class="tab-content">
			<div class="tab-pane show active" id="all-pending" role="tabpanel" aria-labelledby="all-pending-tab">
				<?php foreach($types as $type): ?>
					<?php render_cashier_table($dataByType[$type]['PENDING'], 'Ready for Payment - ' . ucfirst($type)); ?>
				<?php endforeach; ?>
			</div>
			<div class="tab-pane fade" id="all-completed" role="tabpanel" aria-labelledby="all-completed-tab">
				<?php foreach($types as $type): ?>
					<?php render_cashier_table($dataByType[$type]['COMPLETED'], 'Paid Requests - ' . ucfirst($type)); ?>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<?php foreach($types as $type): ?>
	<div class="tab-pane fade <?= $active===$type?'show active':'' ?>" id="tab-<?= h($type) ?>" role="tabpanel">
		<?php kpi_row($kpis[$type], h($type) . '-'); ?>
		
		<ul class="nav nav-pills mb-3" id="<?= h($type) ?>-pills" role="tablist">
			<li class="nav-item">
				<a class="nav-link active" data-bs-toggle="pill" id="<?= h($type) ?>-pending-tab" href="#<?= h($type) ?>-pending" role="tab" aria-controls="<?= h($type) ?>-pending" aria-selected="true">Ready for Payment</a>
			</li>
			<li class="nav-item">
				<a class="nav-link" data-bs-toggle="pill" id="<?= h($type) ?>-completed-tab" href="#<?= h($type) ?>-completed" role="tab" aria-controls="<?= h($type) ?>-completed" aria-selected="false">Paid/Completed</a>
			</li>
		</ul>
		<div class="tab-content">
			<div class="tab-pane show active" id="<?= h($type) ?>-pending" role="tabpanel" aria-labelledby="<?= h($type) ?>-pending-tab">
				<?php	
					render_cashier_table($dataByType[$type]['PENDING'], 'Ready for Payment - ' . ucfirst($type));	
				?>
			</div>
			<div class="tab-pane fade" id="<?= h($type) ?>-completed" role="tabpanel" aria-labelledby="<?= h($type) ?>-completed-tab">
				<?php	
					render_cashier_table($dataByType[$type]['COMPLETED'], 'Paid Requests - ' . ucfirst($type));	
				?>
			</div>
		</div>
	</div>
	<?php endforeach; ?>
	
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// --- Ensures tabs load data correctly on page load ---
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const activeTabName = urlParams.get('tab') || 'dashboard';
    
    // 1. Manually activate the correct main tab link based on URL
    const mainTabLink = document.querySelector(`.nav-tabs .nav-link[href="?tab=${activeTabName}"]`);
    if (mainTabLink) {
        mainTabLink.classList.add('active');
        
        // 2. Activate the corresponding main tab pane
        const targetId = mainTabLink.getAttribute('data-bs-target').substring(1);
        const targetPane = document.getElementById(targetId);
        
        if (targetPane) {
            // Ensure the main tab pane is visible
            document.querySelectorAll('.tab-content > .tab-pane').forEach(el => el.classList.remove('active', 'show'));
            targetPane.classList.add('show', 'active');
            
            // 3. CRITICAL FIX: Manually activate the default nested 'Pending' pill content within this main pane
            const defaultPillLink = targetPane.querySelector('.nav-pills .nav-link.active');
            if (defaultPillLink) {
                const targetPillContentId = defaultPillLink.getAttribute('href').substring(1);
                const targetPillContent = document.getElementById(targetPillContentId);
                
                // Clear all other pill content within this pane
                targetPane.querySelectorAll('.tab-content .tab-pane').forEach(el => el.classList.remove('active', 'show'));
                
                // Activate the default Pending pill content
                if (targetPillContent) {
                    targetPillContent.classList.add('show', 'active');
                }
            }
        }
    }
    
    // --- PART 4: KPI Card Click Handler (For interaction) ---
    document.querySelectorAll('.kpi-card[data-bs-toggle="pill"]').forEach(card => {
        card.addEventListener('click', function() {
            const targetId = this.getAttribute('data-bs-target');
            const targetLink = document.querySelector(`.nav-pills a[href="${targetId}"]`);
            
            if (targetLink) {
                const pill = new bootstrap.Tab(targetLink);
                pill.show();
                
                const targetContent = document.querySelector(targetId);
                if (targetContent) {
                    const pillContentContainer = targetContent.closest('.tab-content');
                    pillContentContainer.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active', 'show'));
                    targetContent.classList.add('show', 'active');
                }
            }
        });
    });

    // --- PART 5: Tab Shown Event Listener (For switching main tabs) ---
    document.querySelectorAll('.nav-tabs a[data-bs-toggle="tab"]').forEach(tabLink => {
         tabLink.addEventListener('shown.bs.tab', function (event) {
             const targetPane = document.querySelector(event.target.getAttribute('data-bs-target'));
             if (targetPane) {
                  const defaultPillLink = targetPane.querySelector('.nav-pills .nav-link.active');
                  if (defaultPillLink) {
                      const targetPillId = defaultPillLink.getAttribute('href').substring(1);
                      const targetPillContent = document.getElementById(targetPillId);
                      
                      if (targetPillContent) {
                          // Manually force activation
                          targetPane.querySelectorAll('.tab-content .tab-pane').forEach(el => el.classList.remove('active', 'show'));
                          targetPillContent.classList.add('show', 'active');
                      }
                  }
             }
         });
    });
});
</script>
</body>
</html>