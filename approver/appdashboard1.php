<?php
/**
 * APPROVER: Dashboard Customized for Approval Workflow.
 * - FINAL FIX: KPI cards now correctly link to their respective status pills on ALL tabs (Dashboard, Vendor, Employee, Expenses).
 * - NEW FEATURE: Added 'fixed' as a new request type/tab.
 * - NEW FIX: Modified the 'fixed' expense reference display to show the actual expense type instead of just "Fixed Item:".
 * - NEW FIX: Added username lookup to show actual username in "Requested By" column instead of "User" + ID.
 * - Edit/Review action is restricted ONLY to requests with status 'SUBMITTED'.
 * Path: kmk/finance/approver/dashboard.php
 */
session_start();
 
// Use trim() and strtolower() for robust role checking
$userRole = strtolower(trim($_SESSION['roleName'] ?? ''));
$userName = ($_SESSION['userName'] ?? 'User');
// --- ADDED: Get User ID for display ---
$userId = ($_SESSION['userId'] ?? 0); 
// --- END ADDED ---

// --- NEW LOGIC FOR ADMIN DASHBOARD DROPDOWN ---
$isAdmin = ($userRole === 'admin');

// NOTE: Update these paths if your file structure is different
$requesterLink = '../admin/dashboard.php'; 
$cashierLink = '../cashier/dashboard.php';
$financeLink = '../finance';
// --- END NEW LOGIC ---


// $userId = $_SESSION["userId"];
require_once("../auth.php");
requireRole(['Approver','Admin']);


// Assuming this file exists and contains exeSql() and other necessary functions.
require __DIR__ . '/../functions.php';

/* ---------------- Small helpers ---------------- */
function v($k,$d=null){return $_POST[$k]??$_GET[$k]??$d;}
function i($x){return is_numeric($x)?(int)$x:0;}
function s($x){return trim((string)($x??''));}
function h($x){return htmlspecialchars((string)$x,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}

/**
 * FIX APPLIED HERE: Now returns the actual user ID or 0, not 1.
 */
function uid(): int { return (int)($_SESSION['userId'] ?? 0); } 

function table_exists(string $t): bool {
	$t = preg_replace('/[^a-zA-Z0-9_]/','',$t);
	$rows = exeSql("SHOW TABLES LIKE '$t'");
	return is_array($rows) && $rows;
}
function has_col(string $table,string $col): bool {
	$table = preg_replace('/[^a-zA-Z0-9_]/','',$table);
	$col	= preg_replace('/[^a-zA-Z0-9_]/','',$col);
	$rows = exeSql("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$table' AND COLUMN_NAME='$col' LIMIT 1");
	return is_array($rows) && $rows;
}

/* ---------------- Lookups (LIVE from DB Schema) ---------------- */
$_B = []; $_V = []; $_E = []; $_G = []; $_U = [];

function branch_name(?int $id): string {
	global $_B; if (!$id) return '';
	if (isset($_B[$id])) return $_B[$id];
	$name='';
	$r = exeSql("SELECT branch_name FROM branches WHERE branch_id=$id LIMIT 1");
	if (is_array($r)&&$r) $name = (string)($r[0]['branch_name'] ?? '');
	$_B[$id]=$name; return $name;
}
function vendor_name(?int $id): string {
	global $_V; if (!$id) return '';
	if (isset($_V[$id])) return $_V[$id];
	$name='';
	$r=exeSql("SELECT vendor_name FROM vendors WHERE vendor_id=$id LIMIT 1");
	if (is_array($r)&&$r) $name=(string)($r[0]['vendor_name'] ?? '');
	$_V[$id]=$name; return $name;
}
function employee_info(?int $id): array {
	global $_E; if (!$id) return ['name'=>'','salary'=>0.0];
	if (isset($_E[$id])) return $_E[$id];
	$row=[];
	$r=exeSql("SELECT employee_name AS name, salary FROM employees WHERE id=$id LIMIT 1");
	if (is_array($r)&&$r) $row=$r[0];
	$_E[$id]=['name'=>$row['name']??'','salary'=>(float)($row['salary']??0)];
	return $_E[$id];
}
function username_lookup(?int $id): string {
	global $_U; 
	if (!$id) return 'N/A';
	if (isset($_U[$id])) return $_U[$id];
	
	$name = '';
	$r = exeSql("SELECT username FROM users WHERE user_id=$id LIMIT 1");
	if (is_array($r) && $r) {
		$name = (string)($r[0]['username'] ?? '');
	}
	
	$_U[$id] = $name ? $name : 'User#' . $id;
	return $_U[$id];
}
function get_grn_numbers(array $grn_ids): array {
	global $_G;
	$numbers = [];
	$ids_to_fetch = [];
	foreach ($grn_ids as $id) {
		$id = (int)$id;
		if ($id <= 0) continue;
		if (isset($_G[$id])) {
			$numbers[] = $_G[$id];
		} else {
			$ids_to_fetch[] = $id;
		}
	}
	if (count($ids_to_fetch) > 0) {
		$idCsv = implode(',', $ids_to_fetch);
		$rows = exeSql("SELECT grn_id, grn_number FROM goods_receipts WHERE grn_id IN ($idCsv)");
		if (is_array($rows)) {
			foreach ($rows as $r) {
				$id = (int)($r['grn_id'] ?? 0);
				$number = (string)($r['grn_number'] ?? ('#'.$id));
				$_G[$id] = $number;
				$numbers[] = $number;
			}
		}
	}
	return array_unique($numbers);
}

/* ---------------- AMOUNT HELPERS ---------------- */
function paynow_from_payload(array $r): float {
	$payload = json_decode($r['payload_json'] ?? '{}', true) ?: [];
	return (float)($payload['pay_now'] ?? 0);
}
function approved_amount(array $r): float {
	if (isset($r['approved_amount']) && $r['approved_amount']!=='') return (float)$r['approved_amount'];
	return paynow_from_payload($r);
}

/* ---------------- Fetch requests ---------------- */
// IMPORTANT: Only statuses relevant for the Approver's active view
$ACTIVE_STATUSES = ['SUBMITTED','APPROVED','RETURNED'];

function fetch_all(?string $type=null, ?string $status=null): array {
	global $ACTIVE_STATUSES;
	$w=[];	
	
	if($type) $w[]="request_type='".addslashes($type)."'";	
	
	if($status) $w[]="status='".addslashes($status)."'";
	
	// Always filter by the statuses we want to see (Submitted, Approved, Returned)
	$w[] = "status IN ('".implode("','", $ACTIVE_STATUSES)."')";

	$sql = "SELECT * FROM payment_requests ".($w?('WHERE '.implode(' AND ',$w)):'')." ORDER BY updated_at DESC, request_id DESC";
	
	$rows = exeSql($sql);
	return is_array($rows)?$rows:[];
}

/* KPI counts */
function kpi_counts(array $rows): array {
	$k = ['SUBMITTED'=>0,'APPROVED'=>0,'RETURNED'=>0];
	
	foreach($rows as $r){	
		$status = $r['status'] ?? '';
		if(isset($k[$status])) {
			$k[$status]++;
		}
	}
	return $k;
}

/* ---------------- Build datasets ---------------- */
$active = $_GET['tab'] ?? 'dashboard';

// *** MODIFIED LINE: Added 'fixed' to valid_tabs and types ***
$valid_tabs = ['dashboard', 'vendor', 'employee', 'expenses', 'fixed'];
$types = ['vendor','employee','expenses', 'fixed'];
// *** END MODIFIED LINE ***

if (!in_array($active, $valid_tabs)) { $active = 'dashboard'; }

// 1. Fetch ALL relevant rows (Submitted, Approved, Returned)
$allRowsNoTypeFilter = fetch_all();	
$allKPI	= kpi_counts($allRowsNoTypeFilter);

// 3. Prepare type-specific datasets and KPIs (used by ALL tabs)
$dataByType = [];
$kpis = [];
foreach($types as $t){
	$rowsByType = fetch_all($t); // Fetch rows for this specific type
	$kpis[$t] = kpi_counts($rowsByType);
	$dataByType[$t] = [
		'SUBMITTED' => array_values(array_filter($rowsByType, fn($r) => ($r['status'] ?? '') === 'SUBMITTED')),
		'APPROVED'	=> array_values(array_filter($rowsByType, fn($r) => ($r['status'] ?? '') === 'APPROVED')),
		'RETURNED'	=> array_values(array_filter($rowsByType, fn($r) => ($r['status'] ?? '') === 'RETURNED')),
	];
}


/* ---------------- Render helpers ---------------- */

function badge(string $s): string {
	$map=['SUBMITTED'=>'primary','APPROVED'=>'success','RETURNED'=>'danger']; // Removed PAID
	$cls = $map[$s] ?? 'light';
	return '<span class="badge bg-'.$cls.'">'.h($s).'</span>';
}

/**
 * Renders the KPI row. Includes data attributes for pill linking.
 * @param array $k KPI counts.
 * @param string $type_prefix 'all-' for Dashboard, or 'vendor-', 'employee-', etc., for specific tabs.
 */
function kpi_row(array $k, string $type_prefix = ''){	
	// Data attributes for linking the KPI card to the pills below it
	$submitted_attrs = 'data-bs-toggle="pill" data-bs-target="#' . $type_prefix . 'submitted"';
	$approved_attrs = 'data-bs-toggle="pill" data-bs-target="#' . $type_prefix . 'approved"';
	$returned_attrs = 'data-bs-toggle="pill" data-bs-target="#' . $type_prefix . 'returned"';

	?>
	<div class="row g-3 mb-4">
		<div class="col-md-4">
			<div class="p-3 kpi-card" <?= $submitted_attrs ?>>
				<div>Pending (Submitted)</div>
				<div class="value text-primary"><?= (int)($k['SUBMITTED'] ?? 0) ?></div>
			</div>
		</div>
		<div class="col-md-4">
			<div class="p-3 kpi-card" <?= $approved_attrs ?>>
				<div>Approved (Ready for Cashier)</div>
				<div class="value text-success"><?= (int)($k['APPROVED'] ?? 0) ?></div>
			</div>
		</div>
		<div class="col-md-4">
			<div class="p-3 kpi-card" <?= $returned_attrs ?>>
				<div>Returned</div>
				<div class="value text-danger"><?= (int)($k['RETURNED'] ?? 0) ?></div>
			</div>
		</div>
	</div>
<?php }

// Consolidated function for rendering the Approver's tables
function render_approver_table(array $rows, string $heading){
	if (empty($rows)) {
		echo '<div class="alert alert-info shadow-sm mt-3 mb-4">No ' . strtolower(h($heading)) . ' requests found.</div>';
		return;
	}
	// Sort by updated_at descending
	usort($rows, function($a, $b) {
		return strtotime($b['updated_at'] ?? '0') - strtotime($a['updated_at'] ?? '0');
	});
	
	$title = $heading;

	?>
	<h4 class="mt-4 mb-3"><?=h($title)?></h4>
	<div class="card shadow-sm border-0 mb-3"><div class="card-body p-0">
		<div class="table-responsive">
			<table class="table table-striped table-hover align-middle mb-0">
				<thead class="table-light">
					<tr>
						<th>Request #</th>
						<th>Type</th>
						<th>Reference / GRN Nos</th>
						<th>Requested By</th>
						<th>Req. Amount</th>
						<th>App. Amount</th>
						<th>Status</th>
						<th>Updated</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach($rows as $r):
					$payload = json_decode($r['payload_json']??'{}',true) ?: [];
					$ref = '';
					$paidNow = approved_amount($r);
					$total = (float)($r['total_amount'] ?? 0);
					$requestor = username_lookup(i($r['requested_by'] ?? 0));
					$status = $r['status'] ?? 'N/A';
					
					if (($r['request_type'] ?? '') === 'vendor') {
						$vendor_id = i($payload['vendor_id']??0);
						$grn_ids = (array)($payload['grn_ids']??[]);
						$grn_numbers = get_grn_numbers($grn_ids);
						$vend_name = vendor_name($vendor_id);
						$ref = 'Vendor: ' . h($vend_name) . '<br><small class="text-muted">GRNs: ' . h(implode(', ', $grn_numbers)) . '</small>';
					} elseif (($r['request_type'] ?? '') === 'employee') {
						$empId = i($payload['employee_id']??0);
						$info = employee_info($empId);
						$ref = 'Employee: ' . h($info['name']) . '<br><small class="text-muted">ID: ' . $empId . '</small>';
					} elseif (($r['request_type'] ?? '') === 'expenses') {
						$purpose = (string)($payload['purpose']??'');
						if ($purpose==='_other_') $purpose = (string)($payload['custom_purpose']??'');
						$ref = 'Purpose: ' . h($purpose);
					}
					// *** MODIFIED LOGIC: For 'fixed' requests to display expense type ***
					elseif (($r['request_type'] ?? '') === 'fixed') {
						$purpose = (string)($payload['purpose'] ?? ''); // Try to get purpose
						
						// If purpose is missing or generic, use fixed_id fallback to look up the expense_type from the table
						if (empty($purpose) || $purpose === '_other_') {
							$fixedId = (int)($payload['fixed_id'] ?? 0);
							if ($fixedId > 0 && table_exists('fixed_expenses')) {
								$feRow = exeSql("SELECT expense_type FROM fixed_expenses WHERE id=$fixedId LIMIT 1");
								if ($feRow) {
									$purpose = (string)($feRow[0]['expense_type'] ?? 'N/A');
								}
							}
						}
						
						// Use custom_purpose if applicable (from the '_other_' case)
						if ($purpose === '_other_') {
							$purpose = (string)($payload['custom_purpose'] ?? 'N/A');
						}
						
						// Display the determined purpose/expense type
						$ref = 'Fixed Expense: ' . h(ucfirst($purpose));
					}
					// *** END MODIFIED LOGIC ***
				?>
					<tr class="<?= $status === 'APPROVED' ? 'table-warning' : '' ?>">
						<td>#<?= (int)($r['request_id'] ?? 0) ?></td>
						<td><?= h(ucfirst($r['request_type'] ?? 'N/A')) ?></td>
						<td><?= $ref ?></td>
						<td><?= h($requestor) ?></td>
						<td>₹<?= number_format($total,2) ?></td>
						<td>₹<?= number_format($paidNow,2) ?></td>
						<td><?= badge($status) ?></td>
						<td><?= h($r['updated_at'] ?? 'N/A') ?></td>
						<td>
							<?php if ($status === 'SUBMITTED'): ?>
								<a class="btn btn-sm btn-primary" href="payment?rid=<?= (int)($r['request_id'] ?? 0) ?>">Review/Edit</a>
							<?php else: ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div></div>
<?php
}

/* ---------------- HTML ---------------- */
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Approver Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
	body{background:#fff}
	.page-title{display:flex;justify-content:space-between;align-items:center;margin:14px 0 18px}
    /* Adjusted page-title > div to handle spacing */
    .page-title > div {
        align-items: center;
        gap: 12px; /* Ensure spacing between the button, username, and logout */
        display: flex; /* Ensure flex layout is always active here */
    }
	.kpi-card{border:1px solid #e5e7eb;border-radius:12px;background:#fafafa; height: 100%; cursor: pointer; transition: background-color 0.15s;} /* Added cursor pointer and transition */
	.kpi-card:hover {background-color: #f0f0f0;} /* Slight hover effect */
	.kpi-card .value{font-size:26px;font-weight:800}
	/* Ensure active tab/pill content areas are visible immediately */
	.tab-content > .tab-pane.active {
		display: block;
	}
	.tab-content > .tab-pane:not(.active) {
		display: none;
	}
	/* Style the main tabs correctly using Bootstrap's default styling */
	.nav-tabs .nav-link {
		color: #0d6efd; /* Default Bootstrap link color */
	}
	.nav-tabs .nav-link.active {
		color: #495057; /* Darker color for active tab */
		background-color: #fff;
		border-color: #dee2e6 #dee2e6 #fff;
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
      background-color: #0b5ed7; /* Darker blue on hover */
      border-color: #0b5ed7;
      color: white; 
      opacity: 0.9;
    }
    .dropdown-item i {
        margin-right: 6px;
    }
    /* --- CSS FOR HOVER DROPDOWN (NEW) --- */
    .dropdown:hover .dropdown-menu {
        display: block;
        margin-top: 0; /* Ensures it sits right under the button */
    }
    /* --- END ADMIN DROPDOWN STYLES --- */
    
    .d-none.d-md-block.fw-bold.text-secondary {
        /* This style block ensures the username formatting is consistent */
        white-space: nowrap; 
        max-width: 150px;
        overflow: hidden; 
        text-overflow: ellipsis;
    }

</style>
</head>
<body class="container-fluid p-4">

<header class="page-title">
	<h2 class="mb-0">Approver Dashboard</h2>
	
    <div class="ms-2 d-flex align-items-center gap-3">
        
        <?php if ($isAdmin): ?>
        <div class="dropdown">
          <button class="btn kmk-panel-btn dropdown-toggle" type="button" aria-expanded="false">
            <i class="bi bi-gear"></i> Dashboards
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow">
            <li><a class="dropdown-item" href="<?php echo $requesterLink; ?>"><i class="bi bi-person-lines-fill"></i> Requester</a></li>
            <li><a class="dropdown-item" href="<?php echo $cashierLink; ?>"><i class="bi bi-cash-stack"></i> Cashier</a></li>
             <li><a class="dropdown-item" href="<?php echo $financeLink; ?>"<i class="bi bi-cash-stack"></i> finance</a></li>
          </ul>
        </div>
        <?php endif; ?>
        
                <div class="text-end d-none d-md-block" style="line-height: 1.2;">
            <div class="fw-bold text-dark">
                <?= h($userName) ?>
            </div>
            
        </div>
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
				<a class="nav-link active" data-bs-toggle="pill" id="all-submitted-tab" href="#all-submitted" role="tab" aria-controls="all-submitted" aria-selected="true">Submitted (Pending)</a>
			</li>
			<li class="nav-item"><a class="nav-link" data-bs-toggle="pill" id="all-approved-tab" href="#all-approved" role="tab" aria-controls="all-approved" aria-selected="false">Approved (Ready for Cashier)</a></li>
			<li class="nav-item"><a class="nav-link" data-bs-toggle="pill" id="all-returned-tab" href="#all-returned" role="tab" aria-controls="all-returned" aria-selected="false">Returned</a></li>
		</ul>
		<div class="tab-content">
			<div class="tab-pane show active" id="all-submitted" role="tabpanel" aria-labelledby="all-submitted-tab">
				<?php foreach($types as $type): ?>
					<?php render_approver_table($dataByType[$type]['SUBMITTED'], 'Submitted Requests - ' . ucfirst($type)); ?>
				<?php endforeach; ?>
			</div>
			<div class="tab-pane fade" id="all-approved" role="tabpanel" aria-labelledby="all-approved-tab">
				<?php foreach($types as $type): ?>
					<?php render_approver_table($dataByType[$type]['APPROVED'], 'Approved Requests - ' . ucfirst($type)); ?>
				<?php endforeach; ?>
			</div>
			<div class="tab-pane fade" id="all-returned" role="tabpanel" aria-labelledby="all-returned-tab">
				<?php foreach($types as $type): ?>
					<?php render_approver_table($dataByType[$type]['RETURNED'], 'Returned Requests - ' . ucfirst($type)); ?>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<?php foreach($types as $type): ?>
	<div class="tab-pane fade <?= $active===$type?'show active':'' ?>" id="tab-<?= h($type) ?>" role="tabpanel">
		<?php kpi_row($kpis[$type], h($type) . '-'); ?>
		
		<ul class="nav nav-pills mb-3" id="<?= h($type) ?>-pills" role="tablist">
			<li class="nav-item">
				<a class="nav-link active" data-bs-toggle="pill" id="<?= h($type) ?>-submitted-tab" href="#<?= h($type) ?>-submitted" role="tab" aria-controls="<?= h($type) ?>-submitted" aria-selected="true">Submitted (Pending)</a>
			</li>
			<li class="nav-item"><a class="nav-link" data-bs-toggle="pill" id="<?= h($type) ?>-approved-tab" href="#<?= h($type) ?>-approved" role="tab" aria-controls="<?= h($type) ?>-approved" aria-selected="false">Approved</a></li>
			<li class="nav-item"><a class="nav-link" data-bs-toggle="pill" id="<?= h($type) ?>-returned-tab" href="#<?= h($type) ?>-returned" role="tab" aria-controls="<?= h($type) ?>-returned" aria-selected="false">Returned</a></li>
		</ul>
		<div class="tab-content">
			<div class="tab-pane show active" id="<?= h($type) ?>-submitted" role="tabpanel" aria-labelledby="<?= h($type) ?>-submitted-tab">
				<?php	
					render_approver_table($dataByType[$type]['SUBMITTED'], 'Submitted Requests for ' . ucfirst($type));	
				?>
			</div>
			<div class="tab-pane fade" id="<?= h($type) ?>-approved" role="tabpanel" aria-labelledby="<?= h($type) ?>-approved-tab">
				<?php	
					render_approver_table($dataByType[$type]['APPROVED'], 'Approved Requests for ' . ucfirst($type));	
				?>
			</div>
			<div class="tab-pane fade" id="<?= h($type) ?>-returned" role="tabpanel" aria-labelledby="<?= h($type) ?>-returned-tab">
				<?php	
					render_approver_table($dataByType[$type]['RETURNED'], 'Returned Requests for ' . ucfirst($type));	
				?>
			</div>
		</div>
	</div>
	<?php endforeach; ?>
	
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
                
                // 3. CRITICAL FIX: Manually activate the default nested 'Submitted' pill content within this main pane
                const defaultPillLink = targetPane.querySelector('.nav-pills .nav-link.active');
                if (defaultPillLink) {
                    const targetPillContentId = defaultPillLink.getAttribute('href').substring(1);
                    const targetPillContent = document.getElementById(targetPillContentId);
                    
                    // Clear all other pill content within this pane
                    targetPane.querySelectorAll('.tab-content .tab-pane').forEach(el => el.classList.remove('active', 'show'));
                    
                    // Activate the default Submitted pill content
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