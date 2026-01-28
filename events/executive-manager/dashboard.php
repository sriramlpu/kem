<?php

session_start(); 

$userName = ($_SESSION['userName']);
require_once("../../auth.php");
requireRole(['Executive Manager']);
// Check if user is logged in (optional - add your authentication check)
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'executive') {
//     header("Location: login.php");
//     exit();
// }

 $servername = "localhost";
 $username = "kmkglobal_web";
 $password = "tI]rfPhdOo9zHdKw";
 $dbname = "kmkglobal_web"; 

 $conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { 
    die("Connection failed: " . $conn->connect_error); 
}

 $message = "";

// -------------------- 1. HANDLE PROPOSAL APPROVAL/REJECTION --------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_proposal') {
    $client_id = $_POST['client_id'] ?? null;
    $decision = $_POST['decision'] ?? null; // 'APPROVED' or 'REJECTED'
    $executive_notes = $_POST['executive_notes'] ?? '';
    $executive_name = $_SESSION['username'] ?? 'Executive Manager';

    if ($client_id && $decision) {
        $conn->begin_transaction();
        
        try {
            // Update clients table
            if ($decision == 'APPROVED') {
                $workflow_stage = 'EXECUTIVE_APPROVED';
                $executive_status = 'APPROVED';
            } else {
                $workflow_stage = 'REJECTED';
                $executive_status = 'REJECTED';
            }
            
            $sql_update = "UPDATE clients 
                          SET executive_status = ?,
                              executive_notes = ?,
                              executive_approved_by = ?,
                              executive_approved_at = NOW(),
                              workflow_stage = ?
                          WHERE client_id = ?";
            
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("ssssi", $executive_status, $executive_notes, $executive_name, $workflow_stage, $client_id);
            
            if (!$stmt_update->execute()) {
                throw new Exception('Failed to update proposal');
            }
            $stmt_update->close();
            
            // Log activity
            $action_type = ($decision == 'APPROVED') ? 'EXECUTIVE_APPROVED' : 'EXECUTIVE_REJECTED';
            $action_desc = ($decision == 'APPROVED') 
                ? 'Proposal approved by Executive Manager' 
                : 'Proposal rejected by Executive Manager';
            
            $sql_log = "INSERT INTO activity_log 
                       (client_id, action_by, action_type, action_description, new_status) 
                       VALUES (?, ?, ?, ?, ?)";
            
            $stmt_log = $conn->prepare($sql_log);
            $stmt_log->bind_param("issss", $client_id, $executive_name, $action_type, $action_desc, $workflow_stage);
            $stmt_log->execute();
            $stmt_log->close();
            
            $conn->commit();
            
            $message = "<div class='alert alert-success success-alert'>✅ Proposal {$decision} successfully for Client #{$client_id}.</div>";
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "<div class='alert alert-danger error-alert'>❌ Error: " . $e->getMessage() . "</div>";
        }
    }
}

// -------------------- 2. HANDLE EXECUTION STATUS UPDATE --------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_execution') {
    $client_id = $_POST['client_id'] ?? null;
    $new_exec_status = $_POST['execution_status'] ?? null;

    if ($client_id && $new_exec_status) {
        $sql = "UPDATE clients SET execution_status = ? WHERE client_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_exec_status, $client_id); 
        
        if ($stmt->execute()) {
            // Log activity
            $executive_name = $_SESSION['username'] ?? 'Executive Manager';
            $sql_log = "INSERT INTO activity_log 
                       (client_id, action_by, action_type, action_description, new_status) 
                       VALUES (?, ?, 'EXECUTION_UPDATE', ?, ?)";
            
            $stmt_log = $conn->prepare($sql_log);
            $action_desc = "Execution status updated to {$new_exec_status}";
            $stmt_log->bind_param("isss", $client_id, $executive_name, $action_desc, $new_exec_status);
            $stmt_log->execute();
            $stmt_log->close();
            
            $message = "<div class='alert alert-success success-alert'>✅ Execution Status Updated to <strong>{$new_exec_status}</strong> for Client #{$client_id}.</div>";
        } else {
            $message = "<div class='alert alert-danger error-alert'>❌ DB Error: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
}

// -------------------- 3. FETCH PENDING PROPOSALS --------------------
 $pending_proposals = [];
 $sql_pending = "SELECT c.*, 
                       (SELECT COUNT(*) FROM proposal_items WHERE client_id = c.client_id) as item_count
                FROM clients c
                WHERE c.workflow_stage = 'EXECUTIVE_REVIEW' 
                AND c.executive_status = 'PENDING'
                ORDER BY c.created_at DESC";

 $result_pending = $conn->query($sql_pending);
if ($result_pending) {
    while ($row = $result_pending->fetch_assoc()) {
        $pending_proposals[] = $row;
    }
}

// -------------------- 4. FETCH ACCEPTED EVENTS FOR EXECUTION --------------------
 $accepted_events = [];
 $sql_accepted = "SELECT client_id, client_name, contact_no, date_time_of_event, 
                        final_budget, sales_notes, execution_status, event_type, location_address
                 FROM clients 
                 WHERE client_status = 'ACCEPTED'
                 ORDER BY date_time_of_event ASC";

 $result_accepted = $conn->query($sql_accepted);
if ($result_accepted) {
    while ($row = $result_accepted->fetch_assoc()) {
        $accepted_events[] = $row;
    }
}

// -------------------- 5. FETCH DASHBOARD STATISTICS --------------------
 $stats = [
    'pending_proposals' => 0,
    'approved_proposals' => 0,
    'active_events' => 0,
    'completed_events' => 0
];

 $sql_stats = "SELECT 
                COUNT(CASE WHEN workflow_stage = 'EXECUTIVE_REVIEW' AND executive_status = 'PENDING' THEN 1 END) as pending_proposals,
                COUNT(CASE WHEN executive_status = 'APPROVED' THEN 1 END) as approved_proposals,
                COUNT(CASE WHEN execution_status = 'ACTIVE' THEN 1 END) as active_events,
                COUNT(CASE WHEN execution_status = 'COMPLETED' THEN 1 END) as completed_events
              FROM clients";

 $result_stats = $conn->query($sql_stats);
if ($result_stats && $row_stats = $result_stats->fetch_assoc()) {
    $stats = $row_stats;
}

 $conn->close();
 $status_options = ['WAITING', 'PLANNING', 'ACTIVE', 'COMPLETED'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Manager Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .container { max-width: 1400px; }
        
        .dashboard-header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 15px;
        }
        
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 30px;
        }
        
        .card-header {
            border-radius: 15px 15px 0 0 !important;
            padding: 20px 25px;
            font-weight: 600;
        }
        
        .table-responsive { max-height: 600px; overflow-y: auto; }
        
        .status-pill { 
            padding: 6px 14px; 
            border-radius: 20px; 
            font-size: 0.85rem; 
            font-weight: 600;
            display: inline-block;
        }
        
        .status-WAITING { background-color: #fef3c7; color: #92400e; }
        .status-PLANNING { background-color: #dbeafe; color: #1e40af; }
        .status-ACTIVE { background-color: #d1fae5; color: #065f46; }
        .status-COMPLETED { background-color: #e5e7eb; color: #374151; }
        
        .status-PENDING { background-color: #fef3c7; color: #92400e; }
        .status-APPROVED { background-color: #d1fae5; color: #065f46; }
        .status-REJECTED { background-color: #fee2e2; color: #991b1b; }
        
        .btn-approve {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
            color: white;
        }
        
        .btn-approve:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
        }
        
        .btn-reject {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border: none;
            color: white;
        }
        
        .btn-reject:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
        }
        
        .error-alert, .success-alert {
            position: fixed; 
            top: 20px; 
            right: 20px; 
            z-index: 1050; 
            max-width: 400px; 
            animation: slideIn 0.5s forwards; 
            font-weight: bold;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        @keyframes slideIn { 
            from { right: -400px; opacity: 0; } 
            to { right: 20px; opacity: 1; } 
        }
        
        .proposal-details {
            background: #f8fafc;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        
        .modal-content {
            border-radius: 15px;
        }
        
        .nav-tabs .nav-link {
            border-radius: 10px 10px 0 0;
            font-weight: 600;
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
    </style>
</head>
<body>
    <?php echo $message; ?>
    
    <div class="container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-2"><i class="bi bi-briefcase-fill text-primary"></i> Executive Manager Dashboard</h1>
                    <p class="text-muted mb-0">Approve proposals and manage event execution</p>
                </div>
                 <div class="ms-2 d-flex">
        <span class="d-none d-md-block fw-bold me-3 text-secondary" style="
        white-space: nowrap; 
        max-width: 150px; /* Adjust this value as needed for long names */
        overflow: hidden; 
        text-overflow: ellipsis;
    ">
        Hello, <?php echo $userName; ?>
    </span>
            <a href="../../logout.php" class="btn btn-sm btn-danger">
              <i class="bi bi-box-arrow-right"></i> Logout
            </a>
          </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: #fef3c7;">
                        <i class="bi bi-clock-history text-warning"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['pending_proposals']; ?></h3>
                    <p class="text-muted mb-0">Pending Proposals</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: #d1fae5;">
                        <i class="bi bi-check-circle-fill text-success"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['approved_proposals']; ?></h3>
                    <p class="text-muted mb-0">Approved Proposals</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: #dbeafe;">
                        <i class="bi bi-play-circle-fill text-primary"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['active_events']; ?></h3>
                    <p class="text-muted mb-0">Active Events</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon" style="background: #e5e7eb;">
                        <i class="bi bi-check-all text-secondary"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['completed_events']; ?></h3>
                    <p class="text-muted mb-0">Completed Events</p>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs mb-4" id="dashboardTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="proposals-tab" data-bs-toggle="tab" data-bs-target="#proposals" type="button">
                    <i class="bi bi-file-earmark-check"></i> Pending Proposals (<?php echo count($pending_proposals); ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="execution-tab" data-bs-toggle="tab" data-bs-target="#execution" type="button">
                    <i class="bi bi-calendar-event"></i> Event Execution (<?php echo count($accepted_events); ?>)
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="dashboardTabsContent">
            <!-- Pending Proposals Tab -->
            <div class="tab-pane fade show active" id="proposals" role="tabpanel">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h4 class="mb-0"><i class="bi bi-hourglass-split"></i> Proposals Awaiting Executive Approval</h4>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Client Info</th>
                                        <th>Event Details</th>
                                        <th>Budget</th>
                                        <th>Items</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($pending_proposals) > 0): ?>
                                        <?php foreach ($pending_proposals as $proposal): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($proposal['client_name']); ?></strong><br>
                                                <small class="text-muted">
                                                    <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($proposal['contact_no']); ?><br>
                                                    <i class="bi bi-hash"></i> Client #<?php echo $proposal['client_id']; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($proposal['event_type'] ?? 'N/A'); ?></strong><br>
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar3"></i> <?php echo date('M d, Y h:i A', strtotime($proposal['date_time_of_event'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <strong class="text-primary">₹<?php echo number_format($proposal['final_budget'], 2); ?></strong><br>
                                                <small class="text-muted">Discount: ₹<?php echo number_format($proposal['discount_amount'], 2); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $proposal['item_count']; ?> Items</span>
                                            </td>
                                            <td>
                                                <span class="status-pill status-PENDING">PENDING</span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info mb-1" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $proposal['client_id']; ?>">
                                                    <i class="bi bi-eye"></i> View
                                                </button>
                                                <button class="btn btn-sm btn-approve mb-1" data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $proposal['client_id']; ?>">
                                                    <i class="bi bi-check-circle"></i> Approve
                                                </button>
                                                <button class="btn btn-sm btn-reject" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $proposal['client_id']; ?>">
                                                    <i class="bi bi-x-circle"></i> Reject
                                                </button>
                                            </td>
                                        </tr>
                                        
                                        <!-- View Modal -->
                                        <div class="modal fade" id="viewModal<?php echo $proposal['client_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-info text-white">
                                                        <h5 class="modal-title">Proposal Details - <?php echo htmlspecialchars($proposal['client_name']); ?></h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="proposal-details">
                                                            <h6>Budget Summary</h6>
                                                            <div class="row">
                                                                <div class="col-md-4">
                                                                    <strong>Subtotal:</strong> ₹<?php echo number_format($proposal['budget_draft_sales'], 2); ?>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <strong>Discount:</strong> ₹<?php echo number_format($proposal['discount_amount'], 2); ?>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <strong>Final:</strong> ₹<?php echo number_format($proposal['final_budget'], 2); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if ($proposal['sales_notes']): ?>
                                                        <div class="proposal-details">
                                                            <h6>Sales Notes</h6>
                                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($proposal['sales_notes'])); ?></p>
                                                        </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($proposal['admin_notes']): ?>
                                                        <div class="proposal-details">
                                                            <h6>Admin Notes</h6>
                                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($proposal['admin_notes'])); ?></p>
                                                            <small class="text-muted">
                                                                Approved by: <?php echo htmlspecialchars($proposal['admin_approved_by']); ?> 
                                                                on <?php echo date('M d, Y h:i A', strtotime($proposal['admin_approved_at'])); ?>
                                                            </small>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Approve Modal -->
                                        <div class="modal fade" id="approveModal<?php echo $proposal['client_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-success text-white">
                                                        <h5 class="modal-title">Approve Proposal</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" action="">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="action" value="update_proposal">
                                                            <input type="hidden" name="client_id" value="<?php echo $proposal['client_id']; ?>">
                                                            <input type="hidden" name="decision" value="APPROVED">
                                                            
                                                            <p>Are you sure you want to approve this proposal for <strong><?php echo htmlspecialchars($proposal['client_name']); ?></strong>?</p>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Executive Notes (Optional)</label>
                                                                <textarea name="executive_notes" class="form-control" rows="3" placeholder="Add any notes or comments..."></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-approve">
                                                                <i class="bi bi-check-circle"></i> Approve Proposal
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Reject Modal -->
                                        <div class="modal fade" id="rejectModal<?php echo $proposal['client_id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title">Reject Proposal</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" action="">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="action" value="update_proposal">
                                                            <input type="hidden" name="client_id" value="<?php echo $proposal['client_id']; ?>">
                                                            <input type="hidden" name="decision" value="REJECTED">
                                                            
                                                            <p>Are you sure you want to reject this proposal for <strong><?php echo htmlspecialchars($proposal['client_name']); ?></strong>?</p>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                                                                <textarea name="executive_notes" class="form-control" rows="3" placeholder="Please provide reason for rejection..." required></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-reject">
                                                                <i class="bi bi-x-circle"></i> Reject Proposal
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" class="text-center text-muted py-5">
                                            <i class="bi bi-inbox" style="font-size: 3rem;"></i><br>
                                            No pending proposals at this time.
                                        </td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Event Execution Tab -->
            <div class="tab-pane fade" id="execution" role="tabpanel">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-calendar-check"></i> Event Execution Management</h4>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Client Name</th>
                                        <th>Event Details</th>
                                        <th>Contact</th>
                                        <th>Final Budget</th>
                                        <th>Execution Status</th>
                                        <th>Update Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($accepted_events) > 0): ?>
                                        <?php foreach ($accepted_events as $event): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($event['client_name']); ?></strong><br>
                                                <small class="text-muted">#<?php echo $event['client_id']; ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($event['event_type'] ?? 'N/A'); ?></strong><br>
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar3"></i> <?php echo date('M d, Y h:i A', strtotime($event['date_time_of_event'])); ?><br>
                                                    <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars(substr($event['location_address'] ?? 'N/A', 0, 30)); ?>...
                                                </small>
                                            </td>
                                            <td><?php echo htmlspecialchars($event['contact_no']); ?></td>
                                            <td><strong class="text-success">₹<?php echo number_format($event['final_budget'], 2); ?></strong></td>
                                            <td>
                                                <span class="status-pill status-<?php echo htmlspecialchars($event['execution_status']); ?>">
                                                    <?php echo htmlspecialchars($event['execution_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form method="POST" action="" class="d-flex gap-2">
                                                    <input type="hidden" name="action" value="update_execution">
                                                    <input type="hidden" name="client_id" value="<?php echo $event['client_id']; ?>">
                                                    <select name="execution_status" class="form-select form-select-sm" style="max-width: 150px;">
                                                        <?php foreach ($status_options as $status): ?>
                                                            <option value="<?php echo $status; ?>" <?php echo ($event['execution_status'] == $status) ? 'selected' : ''; ?>>
                                                                <?php echo $status; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-arrow-repeat"></i> Update
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" class="text-center text-muted py-5">
                                            <i class="bi bi-calendar-x" style="font-size: 3rem;"></i><br>
                                            No events are currently marked as ACCEPTED by the client.
                                        </td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.animation = 'slideOut 0.5s forwards';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            });
        }, 5000);

        // Add slideOut animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideOut {
                from { right: 20px; opacity: 1; }
                to { right: -400px; opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>