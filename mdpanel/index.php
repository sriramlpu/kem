<?php
// Set error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Kolkata');

// Database connection details
$servername = "localhost";
$username 	= "root";
$password 	= "";
$dbname 	= "kmk";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {	
	// Error handling for database connection failure
	// In a professional environment, this would log the error and show a user-friendly message.
	die("Connection failed: " . $conn->connect_error);	
}

// ---------------------------------------------------------------------------------------
// 1. INPUT HANDLING & UTILITIES
// ---------------------------------------------------------------------------------------

// Get filter parameters - ALL FILTERS DEFAULT TO 'all'
// Defaults to the first day and last day of the current month
$startDate = isset($_GET['start_date']) && $_GET['start_date'] != '' ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) && $_GET['end_date'] != '' ? $_GET['end_date'] : date('Y-m-t');
$branchFilter = isset($_GET['branch']) ? $_GET['branch'] : 'all';
$vendorFilter = isset($_GET['vendor']) ? $_GET['vendor'] : 'all';
$eventFilter = isset($_GET['event']) ? $_GET['event'] : 'all';
$employeeFilter = isset($_GET['employee']) ? $_GET['employee'] : 'all';
$section = isset($_GET['section']) ? $_GET['section'] : 'summary'; // Determines current view (Summary, Vendors, etc.)

// Format dates for SQL to include entire day range
$startDateSql = $startDate . ' 00:00:00';
$endDateSql = $endDate . ' 23:59:59';

/**
 * Formats a number as Indian Rupee currency.
 */
function currency($n): string {	
	// Format to 2 decimal places with comma separation and Rupee symbol
	return '₹' . number_format((float)$n, 2);	
}

/**
 * Executes a prepared statement and fetches a single float value (for aggregates like SUM).
 */
function fetch_one_float(mysqli $conn, string $sql, string $types = '', array $params = []): float {
	$stmt = $conn->prepare($sql);
	if (!$stmt) {
		error_log("SQL Prepare Error: " . $conn->error . " | SQL: " . $sql);
		return 0.0;
	}
	
	if ($types && $params) {	
		$bindParams = array_merge([$types], $params);
		$ref = [];
		// PHP 5.6+ compatible way to pass array elements by reference to bind_param
		foreach($bindParams as $key => $value) $ref[$key] = &$bindParams[$key];
		call_user_func_array([$stmt, 'bind_param'], $ref);
	}
	
	$stmt->execute();
	$stmt->bind_result($val);
	if (!$stmt->fetch()) {	
		$val = 0.0;	
	}
	$stmt->close();
	return (float)$val;
}

/**
 * Executes a prepared statement and fetches all results as an associative array.
 */
function fetch_all(mysqli $conn, string $sql, string $types = '', array $params = []): array {
	$stmt = $conn->prepare($sql);
	if (!$stmt) {
		error_log("SQL Prepare Error: " . $conn->error . " | SQL: " . $sql);
		return [];
	}
	
	if ($types && $params) {	
		$bindParams = array_merge([$types], $params);
		$ref = [];
		foreach($bindParams as $key => $value) $ref[$key] = &$bindParams[$key];
		call_user_func_array([$stmt, 'bind_param'], $ref);
	}
	
	$stmt->execute();
	$result = $stmt->get_result();
	$data = [];
	while ($row = $result->fetch_assoc()) {
		$data[] = $row;
	}
	$stmt->close();
	return $data;
}

/**
 * Helper function to retrieve the branch name based on branch_id from the branches table.
 */
function get_branch_name_by_id(mysqli $conn, $branch_id) {
    $sql = "SELECT branch_name FROM branches WHERE branch_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("SQL Prepare Error for branch name lookup: " . $conn->error);
        return null;
    }
    
    $stmt->bind_param('i', $branch_id);
    $stmt->execute();
    $stmt->bind_result($branchName);
    if ($stmt->fetch()) {
        $stmt->close();
        return $branchName;
    }
    $stmt->close();
    return null;
}

/**
 * Calculates the number of months between two dates, including the start and end month.
 */
function calculate_month_difference($start, $end) {
    try {
        $date1 = new DateTime($start);
        $date2 = new DateTime($end);
        
        $interval = $date1->diff($date2);
        
        // Calculate total months difference
        $months = $interval->y * 12 + $interval->m;
        
        // If the dates are not in the same month, we need to count the end month too,
        // which is done by adding 1 to the interval month difference.
        // If they are in the same month, the difference is 0, so we count 1 month.
        return max(1, $months + 1);

    } catch (Exception $e) {
        // Fallback in case of invalid date formats
        return 1;
    }
}


// ---------------------------------------------------------------------------------------
// 2. SUMMARY DATA (For all sections - KPIs)
// ---------------------------------------------------------------------------------------

// Check if a branch filter is applied and fetch the corresponding branch name (string)
$selectedBranchName = null;
if ($branchFilter !== 'all') {
    $selectedBranchName = get_branch_name_by_id($conn, $branchFilter);
}

// Base parameters for time range
$baseParams = [$startDateSql, $endDateSql];
$baseTypes = 'ss';


// Total Revenue (Events) - Not filtered by Branch/Vendor/Event in Summary as relations are complex or absent.
$revenueSql = "SELECT COALESCE(SUM(ei.amount_received), 0) FROM event_items ei";
$revenueParams = $baseParams;
$revenueTypes = $baseTypes;

// Filter Revenue by Date
$revenueSql .= " WHERE ei.created_at BETWEEN ? AND ?";
$total_revenue = fetch_one_float($conn, $revenueSql, $revenueTypes, $revenueParams);


// Vendor Payments - Filtered by Branch if selected.
$vendorPaymentSql = "SELECT COALESCE(SUM(vgp.amount), 0) FROM vendor_grn_payments vgp 
                     WHERE vgp.paid_at BETWEEN ? AND ?";
$vendorPaymentParams = $baseParams;
$vendorPaymentTypes = $baseTypes;

if ($branchFilter !== 'all') {
    // Applying Branch filter to Vendor Payments KPI
    $vendorPaymentSql .= " AND vgp.branch_id = ?"; 
    $vendorPaymentTypes .= 'i';
    $vendorPaymentParams[] = intval($branchFilter);
}
$vendor_payments = fetch_one_float($conn, $vendorPaymentSql, $vendorPaymentTypes, $vendorPaymentParams);


// Employee Payments - Filtered by Branch if selected.
$employeePaymentSql = "SELECT COALESCE(SUM(esp.amount), 0) FROM employee_salary_payments esp
                       JOIN employees e ON esp.employee_id = e.id
                       WHERE esp.paid_at BETWEEN ? AND ?";
$employeePaymentParams = $baseParams;
$employeePaymentTypes = $baseTypes;

// Applying Branch filter to Employee Payment KPI (requires branch name lookup)
if ($branchFilter !== 'all' && $selectedBranchName) {
    $employeePaymentSql .= " AND e.branch = ?";
    $employeePaymentTypes .= 's';
    $employeePaymentParams[] = $selectedBranchName;
}
$employee_payments = fetch_one_float($conn, $employeePaymentSql, $employeePaymentTypes, $employeePaymentParams);


// Other Expenses - Filters by Date only.
$other_expenses = fetch_one_float($conn,
    "SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE paid_at BETWEEN ? AND ?",
    'ss', $baseParams);

// Net Profit (Revenue - Total Expenses)
$net_profit = $total_revenue - ($vendor_payments + $employee_payments + $other_expenses);

// ---------------------------------------------------------------------------------------
// 3. SECTION-SPECIFIC DATA (Only load data for current section)
// ---------------------------------------------------------------------------------------

// Initialize data arrays
$vendors_data = $employees_data = $events_data = $event_items_data = $expenses_data = [];

// Load data based on current section
switch($section) {
	case 'vendors':
		// Vendor Data with proper filtering
		$vendors_data_sql = "
			SELECT v.vendor_id, v.vendor_name,	
				   COALESCE(vt.total_bill, 0) as total_bill,
				   COALESCE(SUM(vgp.amount), 0) as total_paid,
				   COALESCE(vt.total_bill - SUM(vgp.amount), 0) as balance_due
			FROM vendors v
			LEFT JOIN vendor_totals vt ON v.vendor_id = vt.vendor_id
            -- Filter vendor payments by date range and branch when calculating Total Paid
            LEFT JOIN vendor_grn_payments vgp ON v.vendor_id = vgp.vendor_id 
                AND vgp.paid_at BETWEEN ? AND ?
			WHERE v.status = 'Active'
		";
        
        $vendorDetailParams = [$startDateSql, $endDateSql];
		$vendorDetailTypes = 'ss';

		if ($vendorFilter !== 'all') {
			$vendors_data_sql .= " AND v.vendor_id = ?";
			$vendorDetailTypes .= 'i';
			$vendorDetailParams[] = intval($vendorFilter);
		}
        
        // APPLYING BRANCH FILTER to limit vendors shown based on payments made at that branch
        if ($branchFilter !== 'all') {
            $vendors_data_sql .= " AND vgp.branch_id = ?";
            $vendorDetailTypes .= 'i';
            $vendorDetailParams[] = intval($branchFilter);
        }

        $vendors_data_sql .= " GROUP BY v.vendor_id, v.vendor_name, vt.total_bill, vt.balance ORDER BY v.vendor_name";

		$vendors_data = fetch_all($conn, $vendors_data_sql, $vendorDetailTypes, $vendorDetailParams);
		break;

	case 'employees':
		// Employee Payments with filtering (Uses same branch name logic)
		$employees_data_sql = "
			SELECT e.id, e.employee_name, e.branch, e.role, e.salary,
				   COALESCE(SUM(esp.amount), 0) as total_paid,
				   MAX(esp.paid_at) as last_payment
			FROM employees e
			LEFT JOIN employee_salary_payments esp ON e.id = esp.employee_id	
				AND esp.paid_at BETWEEN ? AND ?
			WHERE 1=1
		";

		$employeeDetailParams = [$startDateSql, $endDateSql];
		$employeeDetailTypes = 'ss';
        
        // Filter by Branch Name (Used only in 'employees' section)
        if ($branchFilter !== 'all' && $selectedBranchName) {
            $employees_data_sql .= " AND e.branch = ?";
            $employeeDetailTypes .= 's';
            $employeeDetailParams[] = $selectedBranchName;
        }

		if ($employeeFilter !== 'all') {
			$employees_data_sql .= " AND e.id = ?";
			$employeeDetailTypes .= 'i';
			$employeeDetailParams[] = intval($employeeFilter);
		}

		$employees_data_sql .= "
			GROUP BY e.id, e.employee_name, e.branch, e.role, e.salary
			ORDER BY e.employee_name
		";

		$employees_data = fetch_all($conn, $employees_data_sql, $employeeDetailTypes, $employeeDetailParams);

        // Calculate Total Salary Liability and Due Amount for the period
        $monthsInPeriod = calculate_month_difference($startDate, $endDate);
        
        // Initialize aggregate variables
        $aggregate_total_bill = 0.0;
        $aggregate_total_paid = 0.0;

        foreach ($employees_data as &$employee) {
            $monthlySalary = floatval($employee['salary']);
            $totalPaid = floatval($employee['total_paid']);

            // Calculate Total Bill (Liability) for the time period
            $employee['total_bill'] = $monthlySalary * $monthsInPeriod;

            // Calculate Balance Due (Bill - Paid)
            $employee['balance_due'] = $employee['total_bill'] - $totalPaid;
            
            // Aggregate totals for the header
            $aggregate_total_bill += $employee['total_bill'];
            $aggregate_total_paid += $totalPaid;
        }
        unset($employee); // Unset reference after loop
        
        // Calculate aggregate due
        $aggregate_balance_due = $aggregate_total_bill - $aggregate_total_paid;

		break;

	case 'events':
		// Events Data
		$events_data_sql = "
			SELECT	
				e.event_id,	
				e.event_name,	
				COALESCE(SUM(ei.total_amount), 0) as total_amount,
				COALESCE(SUM(ei.amount_received), 0) as amount_received,
				COALESCE(SUM(ei.balance), 0) as balance_due
			FROM events e
			LEFT JOIN event_items ei ON e.event_id = ei.event_id
			WHERE 1=1
		";

		$eventDetailParams = [];
		$eventDetailTypes = '';

		if ($eventFilter !== 'all') {
			$events_data_sql .= " AND e.event_id = ?";
			$eventDetailTypes .= 'i';
			$eventDetailParams[] = intval($eventFilter);
		}
        
        /* -- NOTE FOR FUTURE DEVELOPMENT: 
        -- To filter Events by Branch, the 'events' table or 'event_items' table must have a 'branch_id' column.
        -- If 'branch_id' is added to 'events', uncomment the following block:
        if ($branchFilter !== 'all') {
            $events_data_sql .= " AND e.branch_id = ?";
            $eventDetailTypes .= 'i';
            $eventDetailParams[] = intval($branchFilter);
        }
        */

		$events_data_sql .= "
			GROUP BY e.event_id, e.event_name
			ORDER BY e.event_name
		";

		$events_data = fetch_all($conn, $events_data_sql, $eventDetailTypes, $eventDetailParams);

		// Event Items Data
		$event_items_sql = "
			SELECT	
				e.event_name,
				ei.item_name,
				ei.total_amount,
				ei.amount_received,
				ei.balance,
				ei.created_at
			FROM event_items ei
			LEFT JOIN events e ON ei.event_id = e.event_id
			WHERE ei.created_at BETWEEN ? AND ?
		";

		$eventItemsParams = [$startDateSql, $endDateSql];
		$eventItemsTypes = 'ss';

		if ($eventFilter !== 'all') {
			$event_items_sql .= " AND ei.event_id = ?";
			$eventItemsTypes .= 'i';
			$eventItemsParams[] = intval($eventFilter);
		}

		$event_items_sql .= " ORDER BY e.event_name, ei.created_at DESC";
		$event_items_data = fetch_all($conn, $event_items_sql, $eventItemsTypes, $eventItemsParams);
		break;

	case 'expenses':
		// Other Expenses Data
		$expenses_data_sql = "
			SELECT	
				paid_at, purpose, amount, method, voucher_no, invoice_no, payment_by, remark
			FROM expenses	
			WHERE paid_at BETWEEN ? AND ?
		";

		$expenseDetailParams = [$startDateSql, $endDateSql];
		$expenseDetailTypes = 'ss';

		$expenses_data_sql .= " ORDER BY paid_at DESC";
		$expenses_data = fetch_all($conn, $expenses_data_sql, $expenseDetailTypes, $expenseDetailParams);
		break;

	case 'summary':
	default:
		// No detailed data fetch needed for summary, relies only on KPIs calculated above.
		break;
}

// ---------------------------------------------------------------------------------------
// 4. FILTER LISTS (Always load these for filter dropdowns)
// ---------------------------------------------------------------------------------------

$branches = fetch_all($conn, "SELECT branch_id, branch_name FROM branches WHERE branch_name IS NOT NULL");
$vendors_list = fetch_all($conn, "SELECT vendor_id, vendor_name FROM vendors WHERE status = 'Active'");
$events_list = fetch_all($conn, "SELECT event_id, event_name FROM events");
$employees_list = fetch_all($conn, "SELECT id, employee_name FROM employees ORDER BY employee_name");	


// Helper function to safely find the name for filter display (unused in this version but kept for utility)
function get_filter_name($list, $key, $value, $default = 'All') {
	if ($value === 'all' || empty($list)) return $default;
	foreach ($list as $item) {
		if ($item[$key] == $value) {
			return htmlspecialchars($item['employee_name'] ?? $item['vendor_name'] ?? $item['event_name'] ?? $item['branch_name'] ?? $default);
		}
	}
	return $default;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>MD Dashboard - Financial Overview</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<!-- Chart.js library removed -->
	<style>
		:root {
			--primary: #2c3e50; /* Dark Blue */
			--secondary: #3498db; /* Bright Blue */
			--success: #2ecc71; /* Green */
			--danger: #e74c3c; /* Red */
			--warning: #f39c12; /* Orange */
			--info: #1abc9c; /* Teal */
			--dark: #34495e; /* Deeper Blue */
		}
		body { 
			background-color: #f8f9fa; 
			font-family: 'Inter', sans-serif; 
		}
		/* Professional Header Styling */
		.dashboard-header { 
			background: linear-gradient(135deg, var(--primary), var(--dark)); 
			color: white; 
			padding: 1.5rem 0; 
			margin-bottom: 2rem; 
			box-shadow: 0 4px 12px rgba(0,0,0,0.2); 
		}
		/* KPI Card Styling */
		.stat-card { 
			border-radius: 10px; 
			box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
			transition: transform 0.3s, box-shadow 0.3s; 
			margin-bottom: 1.5rem; 
			border: none; 
			height: 100%;
			cursor: pointer; /* Indicate clickability */
		}
		.stat-card:hover { 
			transform: translateY(-3px); 
			box-shadow: 0 12px 20px rgba(0,0,0,0.15); 
		}
		.stat-icon { 
			font-size: 2.5rem; 
			opacity: 0.8; 
			margin-bottom: 0.5rem; 
		}
		.stat-value { 
			font-size: 1.8rem; 
			font-weight: 700; 
			margin-bottom: 0.2rem; 
		}
		.stat-label { 
			font-size: 0.9rem; 
			color: #6c757d; 
			text-transform: uppercase; 
			letter-spacing: 1px; 
		}
		/* Filter and Navigation */
		.filter-section { 
			background-color: white; 
			border-radius: 10px; 
			padding: 1.5rem; 
			box-shadow: 0 4px 6px rgba(0,0,0,0.05); 
			margin-bottom: 2rem; 
		}
		.nav-section { 
			background: white; 
			border-radius: 10px; 
			padding: 1rem; 
			margin-bottom: 2rem; 
			box-shadow: 0 4px 6px rgba(0,0,0,0.05); 
		}
		.nav-section .nav-link { 
			border-radius: 8px; 
			margin: 0 5px; 
			font-weight: 500; 
			transition: background-color 0.2s; 
		}
		.nav-section .nav-link.active { 
			background: var(--secondary); 
			color: white; 
			box-shadow: 0 2px 5px rgba(52, 152, 219, 0.4); 
		}
		/* Table and Detail Section Styling */
		.table-card { 
			border-radius: 10px; 
			box-shadow: 0 4px 6px rgba(0,0,0,0.05); 
			margin-bottom: 2rem; 
			border: 1px solid #e9ecef; 
			margin-top: 2rem; /* ADDED TOP MARGIN FOR SPACING */
		}
		.table-card .card-header { 
			background-color: var(--primary); 
			color: white; 
			border-bottom: none; 
			font-weight: 600; 
			padding: 1rem 1.5rem; 
			border-radius: 10px 10px 0 0 !important; 
		}
		.table-card .card-body { 
			padding: 0.5rem 1.5rem 1.5rem 1.5rem; 
		}
		.table-sm th, .table-sm td { 
			font-size: 0.85rem; 
		}
		.due-negative, .profit-negative { 
			color: var(--danger); 
		}
		.due-positive, .profit-positive { 
			color: var(--success); 
		}
		.section-title { 
			border-left: 4px solid var(--secondary); 
			padding-left: 10px; 
			margin: 1.5rem 0; 
			font-weight: 600; 
			color: var(--primary); 
		}
		/* Financial Statement (Summary Replacement) */
		.financial-statement-card { 
			border-radius: 10px; 
			box-shadow: 0 4px 6px rgba(0,0,0,0.05); 
			margin-bottom: 2rem; 
			border: 1px solid #ddd; 
			padding: 20px; 
			background-color: white; 
			margin-top: 2rem; /* ADDED TOP MARGIN FOR SPACING */
		}
		.fs-row { 
			display: flex; 
			justify-content: space-between; 
			padding: 8px 0; 
			border-bottom: 1px dashed #eee; 
		}
		.fs-row.net-profit { 
			border-top: 3px double var(--primary); 
			font-size: 1.2rem; 
			font-weight: 700; 
			margin-top: 10px; 
			padding-top: 10px; 
		}
	</style>
</head>
<body>
	<div class="dashboard-header">
		<div class="container-xl"> <!-- Changed to container-xl for more horizontal space -->
			<div class="row align-items-center">
				<div class="col-md-6">
					<h1><i class="fas fa-chart-bar me-2"></i>Financial Dashboard</h1>
					<p class="mb-0">Complete Financial Overview (View: <?php echo ucfirst($section); ?>)</p>
				</div>
				<div class="col-md-6 text-end">
					<button class="btn btn-outline-light" onclick="window.location.reload()">
						<i class="fas fa-sync-alt me-1"></i> Refresh Data
					</button>
				</div>
			</div>
		</div>
	</div>

	<div class="container-xl"> <!-- Changed to container-xl for more horizontal space -->
		<!-- Section Navigation -->
		<div class="nav-section">
			<ul class="nav nav-pills justify-content-center">
				<li class="nav-item">
					<a class="nav-link <?php echo $section === 'summary' ? 'active' : ''; ?>"	
						href="?section=summary&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>">
						<i class="fas fa-chart-line me-1"></i> Summary
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link <?php echo $section === 'vendors' ? 'active' : ''; ?>"	
						href="?section=vendors&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>">
						<i class="fas fa-truck me-1"></i> Vendors
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link <?php echo $section === 'employees' ? 'active' : ''; ?>"	
						href="?section=employees&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>">
						<i class="fas fa-users me-1"></i> Employees
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link <?php echo $section === 'events' ? 'active' : ''; ?>"	
						href="?section=events&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>">
						<i class="fas fa-calendar-alt me-1"></i> Events
					</a>
				</li>
				<li class="nav-item">
					<a class="nav-link <?php echo $section === 'expenses' ? 'active' : ''; ?>"	
						href="?section=expenses&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>">
						<i class="fas fa-receipt me-1"></i> Other Expenses
					</a>
				</li>
			</ul>
		</div>

		<!-- Filter Section: Show date filters always, plus contextual filters based on section -->
		<div class="filter-section">
			<form method="GET" action="" id="filterForm">
				<input type="hidden" name="section" value="<?php echo $section; ?>">
				
				<div class="row g-3">
					<!-- Date Filters: Always available in all sections -->
					<div class="col-md-3">
						<label class="form-label">From Date</label>
						<input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" onchange="this.form.submit()">
					</div>
					<div class="col-md-3">
						<label class="form-label">To Date</label>
						<input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" onchange="this.form.submit()">
					</div>
					
					<!-- Contextual Filters -->
					
					<?php if ($section === 'vendors'): ?>
					<div class="col-md-3">
						<label class="form-label">Vendor</label>
						<select class="form-select" name="vendor" onchange="this.form.submit()">
							<option value="all" <?php echo $vendorFilter === 'all' ? 'selected' : ''; ?>>All Vendors</option>
							<?php foreach ($vendors_list as $vendor): ?>
								<option value="<?php echo $vendor['vendor_id']; ?>"	
									<?php echo $vendorFilter == $vendor['vendor_id'] ? 'selected' : ''; ?>>
									<?php echo htmlspecialchars($vendor['vendor_name']); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-3"> <!-- Branch Filter for Vendors -->
						<label class="form-label">Branch</label>
						<select class="form-select" name="branch" onchange="this.form.submit()">
							<option value="all" <?php echo $branchFilter === 'all' ? 'selected' : ''; ?>>All Branches</option>
							<?php foreach ($branches as $branch): ?>
								<option value="<?php echo $branch['branch_id']; ?>"	
									<?php echo $branchFilter == $branch['branch_id'] ? 'selected' : ''; ?>>
									<?php echo htmlspecialchars($branch['branch_name']); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<?php endif; ?>

					<?php if ($section === 'employees'): ?>
					<div class="col-md-3">
						<label class="form-label">Employee</label>
						<select class="form-select" name="employee" onchange="this.form.submit()">
							<option value="all" <?php echo $employeeFilter === 'all' ? 'selected' : ''; ?>>All Employees</option>
							<?php foreach ($employees_list as $employee): ?>
								<option value="<?php echo $employee['id']; ?>"	
									<?php echo $employeeFilter == $employee['id'] ? 'selected' : ''; ?>>
									<?php echo htmlspecialchars($employee['employee_name']); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<!-- Branch Filter: For Employees (Detail Table) -->
					<div class="col-md-3">
						<label class="form-label">Branch</label>
						<select class="form-select" name="branch" onchange="this.form.submit()">
							<option value="all" <?php echo $branchFilter === 'all' ? 'selected' : ''; ?>>All Branches</option>
							<?php foreach ($branches as $branch): ?>
								<option value="<?php echo $branch['branch_id']; ?>"	
									<?php echo $branchFilter == $branch['branch_id'] ? 'selected' : ''; ?>>
									<?php echo htmlspecialchars($branch['branch_name']); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<?php endif; ?>

					<!-- Branch Filter: For Summary (KPIs) -->
					<?php if ($section === 'summary'): ?>
					<div class="col-md-3">
						<label class="form-label">Branch</label>
						<select class="form-select" name="branch" onchange="this.form.submit()">
							<option value="all" <?php echo $branchFilter === 'all' ? 'selected' : ''; ?>>All Branches</option>
							<?php foreach ($branches as $branch): ?>
								<option value="<?php echo $branch['branch_id']; ?>"	
									<?php echo $branchFilter == $branch['branch_id'] ? 'selected' : ''; ?>>
									<?php echo htmlspecialchars($branch['branch_name']); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<?php endif; ?>
                    
                    <?php if ($section === 'events'): // Event filter only in Events section ?>
					<div class="col-md-3">
						<label class="form-label">Event</label>
						<select class="form-select" name="event" onchange="this.form.submit()">
							<option value="all" <?php echo $eventFilter === 'all' ? 'selected' : ''; ?>>All Events</option>
							<?php foreach ($events_list as $event): ?>
								<option value="<?php echo $event['event_id']; ?>"	
									<?php echo $eventFilter == $event['event_id'] ? 'selected' : ''; ?>>
									<?php echo htmlspecialchars($event['event_name']); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<!-- SEARCHABLE COMMENT: BRANCH FILTER LOGIC HERE -->
					<!-- NOTE: The Branch filter has been REMOVED from the UI for the Events section. 
                        If 'branch_id' is added to the 'events' table, uncomment the following block to re-enable the filter:
					<div class="col-md-3"> 
						<label class="form-label">Branch</label>
						<select class="form-select" name="branch" onchange="this.form.submit()">
							<option value="all" <//?php echo $branchFilter === 'all' ? 'selected' : ''; ?>>All Branches</option>
							<//?php foreach ($branches as $branch): ?>
								<option value="<//?php echo $branch['branch_id']; ?>"	
									<//?php echo $branchFilter == $branch['branch_id'] ? 'selected' : ''; ?>>
									<//?php echo htmlspecialchars($branch['branch_name']); ?>
								</option>
							<//?php endforeach; ?>
						</select>
					</div>
                    -->
					<?php endif; ?>

				</div>
				
				<div class="row mt-3">
					<div class="col-12 text-end">
						<!-- Removed "Apply Filters" button, kept "Reset Filters" -->
						<a class="btn btn-outline-secondary" href="?section=<?php echo $section; ?>">
							<i class="fas fa-redo me-1"></i> Reset Filters
						</a>
					</div>
				</div>
			</form>
		</div>
		

		<!-- Summary Cards (Show ONLY in Summary section) -->
		<?php if ($section === 'summary'): ?>
		<div class="row">
			
            <!-- Total Revenue (Primary KPI) - NOW CLICKABLE -->
			<div class="col-xl-3 col-md-6">
				<a href="?section=events&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" class="text-decoration-none">
					<div class="card stat-card border-start border-primary border-4">
						<div class="card-body">
							<div class="row align-items-center">
								<div class="col">
									<div class="text-primary stat-icon"><i class="fas fa-money-bill-wave"></i></div>
									<div class="stat-value"><?php echo currency($total_revenue); ?></div>
									<div class="stat-label">Total Revenue</div>
								</div>
							</div>
						</div>
					</div>
				</a>
			</div>
            
            <!-- Net Profit/Loss (Primary KPI) -->
			<div class="col-xl-3 col-md-6">
				<div class="card stat-card border-start border-success border-4">
					<div class="card-body">
						<div class="row align-items-center">
							<div class="col">
								<div class="text-success stat-icon"><i class="fas fa-chart-line"></i></div>
								<div class="stat-value <?php echo $net_profit >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
									<?php echo currency($net_profit); ?>
								</div>
								<div class="stat-label">Net Profit/Loss</div>
							</div>
						</div>
					</div>
				</div>
			</div>
            
            <!-- Vendor Payments (Clickable KPI) -->
			<div class="col-xl-3 col-md-6">
				<a href="?section=vendors&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" class="text-decoration-none">
					<div class="card stat-card border-start border-warning border-4">
						<div class="card-body">
							<div class="row align-items-center">
								<div class="col">
									<div class="text-warning stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
									<div class="stat-value"><?php echo currency($vendor_payments); ?></div>
									<div class="stat-label">Vendor Payments</div>
								</div>
							</div>
						</div>
					</div>
				</a>
			</div>
            
            <!-- Employee Payments (Clickable KPI) -->
			<div class="col-xl-3 col-md-6">
				<a href="?section=employees&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" class="text-decoration-none">
					<div class="card stat-card border-start border-danger border-4">
						<div class="card-body">
							<div class="row align-items-center">
								<div class="col">
									<div class="text-danger stat-icon"><i class="fas fa-users"></i></div>
									<div class="stat-value"><?php echo currency($employee_payments); ?></div>
									<div class="stat-label">Employee Payments</div>
								</div>
							</div>
						</div>
					</div>
				</a>
			</div>

            <!-- Other Expenses (New Clickable KPI, 2nd Row) -->
            <div class="col-xl-3 col-md-6 mt-3">
				<a href="?section=expenses&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" class="text-decoration-none">
                    <div class="card stat-card border-start border-info border-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-info stat-icon"><i class="fas fa-receipt"></i></div>
                                    <div class="stat-value"><?php echo currency($other_expenses); ?></div>
                                    <div class="stat-label">Other Expenses</div>
                                </div>
                            </div>
                        </div>
                    </div>
				</a>
            </div>

		</div>
        <!-- End of KPI Row -->
		<?php endif; ?>

		<!-- Section-Specific Content -->
		<?php if ($section === 'summary'): ?>
			<!-- Summary View - Financial Statement Breakdown (Replaces Chart) -->
			<div class="row">
				<div class="col-12">
					<h5 class="section-title"><i class="fas fa-file-invoice-dollar me-2"></i>Detailed Profit and Loss Summary</h5>
					<div class="financial-statement-card">
						
						<!-- INCOME -->
						<div class="fs-row bg-light fw-bold text-primary border-bottom-0" style="font-size: 1.1rem;">
							<span>INCOME (Total Inflow)</span>
							<span><?php echo currency($total_revenue); ?></span>
						</div>
						<div class="fs-row">
							<span>Total Revenue (Received from Events/Clients)</span>
							<span class="text-success fw-bold"><?php echo currency($total_revenue); ?></span>
						</div>
						
						<!-- EXPENSES -->
						<div class="fs-row bg-light fw-bold mt-4 text-danger border-bottom-0" style="font-size: 1.1rem;">
							<span>EXPENSES (Total Outflow)</span>
							<span><?php echo currency($vendor_payments + $employee_payments + $other_expenses); ?></span>
						</div>
						<div class="fs-row">
							<span>Vendor Payments (Filtered by Branch: <?php echo $selectedBranchName ?? 'All'; ?>)</span>
							<span><?php echo currency($vendor_payments); ?></span>
						</div>
						<div class="fs-row">
							<span>Employee Salary Payments (Filtered by Branch: <?php echo $selectedBranchName ?? 'All'; ?>)</span>
							<span><?php echo currency($employee_payments); ?></span>
						</div>
						<div class="fs-row">
							<span>Other Operating Expenses</span>
							<span><?php echo currency($other_expenses); ?></span>
						</div>
						
						<!-- NET PROFIT -->
						<div class="fs-row net-profit">
							<span>NET PROFIT / (LOSS)</span>
							<span class="<?php echo $net_profit >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
								<?php echo currency($net_profit); ?>
							</span>
						</div>
					</div>
				</div>
			</div>

		<?php elseif ($section === 'vendors'): ?>
			<!-- Vendors Section -->
			<div class="card table-card">
				<div class="card-header d-flex justify-content-between align-items-center">
					<span><i class="fas fa-truck me-2"></i>Vendor Management</span>
					<?php 
						$total_vendor_due = array_sum(array_column($vendors_data, 'balance_due'));
						$total_vendor_bill = array_sum(array_column($vendors_data, 'total_bill'));
						$total_vendor_paid = array_sum(array_column($vendors_data, 'total_paid'));
					?>
					<div>
						<span class="badge bg-secondary me-2">Total Bill: <?php echo currency($total_vendor_bill); ?></span>
						<span class="badge bg-success me-2">Total Paid: <?php echo currency($total_vendor_paid); ?></span>
						<span class="badge bg-danger">Total Due: <?php echo currency($total_vendor_due); ?></span>
					</div>
				</div>
				<div class="card-body">
					<div class="table-responsive">
						<table class="table table-hover table-sm">
							<thead>
								<tr>
									<th>Vendor Name</th>
									<th>Total Bill</th>
									<th>Total Paid</th>
									<th>Balance Due</th>
									<th>Payment Status</th>
									<th>Actions</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($vendors_data as $vendor): ?>
									<tr>
										<td>
											<strong><?php echo htmlspecialchars($vendor['vendor_name']); ?></strong>
										</td>
										<td><?php echo currency($vendor['total_bill']); ?></td>
										<td><?php echo currency($vendor['total_paid']); ?></td>
										<td>
											<span class="due-amount <?php echo $vendor['balance_due'] > 0 ? 'due-negative' : 'due-positive'; ?>">
												<?php echo currency($vendor['balance_due']); ?>
											</span>
										</td>
										<td>
											<?php if ($vendor['balance_due'] == 0): ?>
												<span class="badge badge-paid text-bg-success">Paid</span>
											<?php elseif ($vendor['total_paid'] > 0): ?>
												<span class="badge badge-pending text-bg-warning">Partial</span>
											<?php else: ?>
												<span class="badge badge-unpaid text-bg-danger">Unpaid</span>
											<?php endif; ?>
										</td>
										<td>
											<button class="btn btn-sm btn-outline-primary">View Details</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php if (empty($vendors_data)): ?>
						<div class="text-center py-4">
							<i class="fas fa-truck fa-3x text-muted mb-3"></i>
							<p class="text-muted">No vendors found with current filters</p>
						</div>
					<?php endif; ?>
				</div>
			</div>

		<?php elseif ($section === 'employees'): ?>
			<!-- Employees Section -->
			<div class="card table-card">
				<div class="card-header d-flex justify-content-between align-items-center">
					<span><i class="fas fa-users me-2"></i>Employee Payments (Salary Liability for period)</span>
					
                    <!-- Aggregated Totals in Header (Like Vendors) -->
                    <div>
						<span class="badge bg-secondary me-2">Total Bill: <?php echo currency($aggregate_total_bill); ?></span>
						<span class="badge bg-success me-2">Total Paid: <?php echo currency($aggregate_total_paid); ?></span>
						<span class="badge bg-danger">Total Due: <?php echo currency($aggregate_balance_due); ?></span>
					</div>
                    <!-- End Aggregated Totals -->

				</div>
				<div class="card-body">
					<div class="table-responsive">
						<table class="table table-hover table-sm">
							<thead>
								<tr>
									<th>Employee Name</th>
									<th>Branch</th>
									<th>Role</th>
									<th>Monthly Salary</th>
                                    <th>Total Bill (Liability)</th> <!-- New Header -->
									<th>Total Paid (Period)</th>
                                    <th>Balance Due</th> <!-- New Header -->
									<th>Last Payment</th>
									<th>Status</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($employees_data as $employee): ?>
									<tr>
										<td><?php echo htmlspecialchars($employee['employee_name']); ?></td>
										<td><?php echo htmlspecialchars($employee['branch'] ?? 'N/A'); ?></td>
										<td><span class="badge bg-secondary"><?php echo htmlspecialchars($employee['role'] ?? 'N/A'); ?></span></td>
										<td><?php echo currency($employee['salary']); ?></td>
                                        
                                        <!-- New Cells -->
                                        <td><?php echo currency($employee['total_bill'] ?? 0.00); ?></td>
										<td><?php echo currency($employee['total_paid']); ?></td>
                                        <td>
                                            <span class="due-amount <?php echo floatval($employee['balance_due']) > 0 ? 'due-negative' : 'due-positive'; ?>">
                                                <?php echo currency($employee['balance_due'] ?? 0.00); ?>
                                            </span>
                                        </td>
                                        
										<td>
											<?php echo $employee['last_payment']	
												? date('M d, Y', strtotime($employee['last_payment']))	
												: 'Never'; ?>
										</td>
										<td>
											<?php if (floatval($employee['balance_due']) <= 0): ?>
												<span class="badge bg-success">Paid</span>
											<?php elseif ($employee['total_paid'] > 0): ?>
                                                <span class="badge bg-warning">Partial</span>
											<?php else: ?>
												<span class="badge bg-danger">Unpaid</span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php if (empty($employees_data)): ?>
						<div class="text-center py-4">
							<i class="fas fa-users fa-3x text-muted mb-3"></i>
							<p class="text-muted">No employee payments found for the selected period or filters.</p>
						</div>
					<?php endif; ?>
				</div>
			</div>

		<?php elseif ($section === 'events'): ?>
			<!-- Events Section -->
			<div class="row">
				<div class="col-lg-6">
					<div class="card table-card">
						<div class="card-header">
							<span><i class="fas fa-calendar-alt me-2"></i>Events Summary</span>
						</div>
						<div class="card-body">
							<?php if (!empty($events_data)): ?>
								<?php foreach ($events_data as $event): ?>
									<div class="event-header mb-3">
										<h6 class="mb-2"><?php echo htmlspecialchars($event['event_name']); ?></h6>
										<div class="row text-center">
											<div class="col-4">
												<small>Total Amount</small>
												<div class="fw-bold"><?php echo currency($event['total_amount']); ?></div>
											</div>
											<div class="col-4">
												<small>Received</small>
												<div class="fw-bold text-success"><?php echo currency($event['amount_received']); ?></div>
											</div>
											<div class="col-4">
												<small>Balance</small>
												<div class="fw-bold text-danger"><?php echo currency($event['balance_due']); ?></div>
											</div>
										</div>
										<?php	
											$percentage = $event['total_amount'] > 0	
												? ($event['amount_received'] / $event['total_amount']) * 100	
												: 0;
										?>
										<div class="progress mt-2">
											<div class="progress-bar	
												<?php 
                                                    // FIX: Check if balance_due is zero or negative (fully paid)
                                                    if (floatval($event['balance_due']) <= 0) {
                                                        echo 'bg-success';
                                                    } elseif ($percentage > 0) {
                                                        echo 'bg-warning';
                                                    } else {
                                                        echo 'bg-danger';
                                                    }
                                                ?>"	
												style="width: <?php echo $percentage; ?>%">
											</div>
										</div>
									</div>
								<?php endforeach; ?>
							<?php else: ?>
								<div class="text-center py-4">
									<i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
									<p class="text-muted">No events found with current filters</p>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
				<div class="col-lg-6">
					<div class="card table-card">
						<div class="card-header">
							<span><i class="fas fa-list-alt me-2"></i>Event Items Breakdown</span>
						</div>
						<div class="card-body">
							<div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
								<table class="table table-sm table-hover">
									<thead>
										<tr>
											<th>Event</th>
											<th>Item</th>
											<th>Amount</th>
											<th>Date</th>
											<th>Status</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($event_items_data as $item): ?>
											<tr>
												<td><small><?php echo htmlspecialchars($item['event_name']); ?></small></td>
												<td><small><?php echo htmlspecialchars($item['item_name'] ?? 'N/A'); ?></small></td>
												<td><?php echo currency($item['total_amount']); ?></td>
												<td><small><?php echo date('m/d/y', strtotime($item['created_at'])); ?></small></td>
												<td>
													<?php 
                                                        // FIX: Check if balance is less than or equal to a very small number (or zero) for 'Paid' status.
                                                        if (floatval($item['balance']) <= 0) {
                                                            echo '<span class="badge bg-success">Paid</span>';
                                                        } elseif (floatval($item['amount_received']) > 0) {
                                                            echo '<span class="badge bg-warning">Partial</span>';
                                                        } else {
                                                            echo '<span class="badge bg-danger">Unpaid</span>';
                                                        }
                                                    ?>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
							<?php if (empty($event_items_data)): ?>
								<div class="text-center py-4">
									<i class="fas fa-list fa-3x text-muted mb-3"></i>
									<p class="text-muted">No event items found for the period/event.</p>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>

		<?php elseif ($section === 'expenses'): ?>
			<!-- Other Expenses Section -->
			<div class="card table-card">
				<div class="card-header d-flex justify-content-between align-items-center">
					<span><i class="fas fa-receipt me-2"></i>Other Expenses Details</span>
					<span class="badge bg-info">Total Expenses: <?php echo currency($other_expenses); ?></span>
				</div>
				<div class="card-body">
					<div class="table-responsive">
						<table class="table table-hover table-sm">
							<thead>
								<tr>
									<th>Date</th>
									<th>Purpose</th>
									<th>Amount</th>
									<th>Method</th>
									<th>Reference No.</th>
									<th>Paid By</th>
									<th>Remark</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($expenses_data as $expense): ?>
									<tr>
										<td><?php echo date('M d, Y', strtotime($expense['paid_at'])); ?></td>
										<td><?php echo htmlspecialchars($expense['purpose']); ?></td>
										<td><?php echo currency($expense['amount']); ?></td>
										<td><span class="badge bg-secondary"><?php echo ucfirst($expense['method'] ?? 'N/A'); ?></span></td>
										<td><?php echo htmlspecialchars($expense['voucher_no'] ?? $expense['invoice_no'] ?? 'N/A'); ?></td>
										<td><?php echo htmlspecialchars($expense['payment_by'] ?? 'N/A'); ?></td>
										<td><?php echo htmlspecialchars($expense['remark'] ?? '-'); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php if (empty($expenses_data)): ?>
						<div class="text-center py-4">
							<i class="fas fa-receipt fa-3x text-muted mb-3"></i>
							<p class="text-muted">No expenses found for the selected period</p>
						</div>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
