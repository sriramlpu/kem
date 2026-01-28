<?php
/**
 * KMK/events/sales_person/client_send_to_executive.php
 * Displays an Admin-Approved proposal for final review by the sales person
 * and facilitates submission to the Executive Manager workflow stage via AJAX.
 */

// Start session at the very top
session_start();

// --- 1. CONFIGURATION & REQUIRED INCLUDES (Mirroring previous file structure) ---

// Simple placeholder session check and setting
if (!isset($_SESSION['sales_person'])) {
    $_SESSION['sales_person'] = 'Sales Representative';
}

// Global table definitions (for consistency with other workflow files)
$clientsTable = 'clients'; 
$proposalItemsTable = 'proposal_items';
$menuItemsTable = 'menu_items'; // Example table, though not directly queried here

// Placeholder table names for functions.php (if they are global)
$servTable = 'service'; 
$distTable = 'districts';
$docsTable = 'doctors_executives';
$specTable = 'specialities';
$cityTable = 'cities';
$adminTable = 'admin_users';

// Constants assumed by your functions.php for context (define if necessary)
if (!defined('TABLE_ISSUES')) define('TABLE_ISSUES', 'issues');
if (!defined('TABLE_REPLIES')) define('TABLE_REPLIES', 'issue_replies');
if (!defined('TABLE_STATUS')) define('TABLE_STATUS', 'issue_status');
if (!defined('TABLE_ITEMS')) define('TABLE_ITEMS', 'proposal_items'); 


// --- INCLUDE CORRECTED FUNCTIONS PATH ---
// Path: KMK/events/sales_person/ --> KMK/functions.php (Two levels up)
// NOTE: Uncomment this line if the actual functions.php file exists
// require_once('../../functions.php'); 


// --- 2. DATABASE CONNECTION ---

// WARNING: Hardcoded credentials. These should be moved to a separate, secure config file.
$servername = "localhost";
$username = "kmkglobal_web";
$password = "tI]rfPhdOo9zHdKw"; 
$dbname = "kmkglobal_web";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- 3. DATA RETRIEVAL ---

$client_id = $_GET['client_id'] ?? null;
$client_data = null;
$proposal_items = [];

if (!$client_id) {
    die("Error: Client ID is required.");
}

// Fetch client data - **CRITICAL: Only fetches if the workflow_stage is ADMIN_APPROVED**
$sql = "SELECT * FROM {$clientsTable} WHERE client_id = ? AND workflow_stage = 'ADMIN_APPROVED'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $client_data = $result->fetch_assoc();
} else {
    $stmt->close();
    die("Client not found or not approved by admin. Current status must be 'ADMIN_APPROVED'.");
}
$stmt->close();

// Fetch proposal items (Secured with prepared statement)
$sql_items = "SELECT * FROM {$proposalItemsTable} WHERE client_id = ? ORDER BY item_type, item_name";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $client_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();

while ($row = $result_items->fetch_assoc()) {
    $proposal_items[] = $row;
}
$stmt_items->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send to Executive | <?php echo htmlspecialchars($client_data['client_name'] ?? 'Proposal Review'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        
        .container {
            max-width: 1000px;
        }
        
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .approval-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        
        .success-badge {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .info-section {
            background: #f9fafb;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .budget-highlight {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            margin: 20px 0;
        }
        
        .items-table {
            margin-top: 20px;
        }
        
        .items-table th {
            background: #f3f4f6;
            font-weight: 600;
        }

        .btn-send-executive {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            border: none;
            color: white;
            padding: 15px 30px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .btn-send-executive:hover {
            background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(124, 58, 237, 0.3);
        }

        .btn-send-executive:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }

        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
            border-width: 0.2em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="bi bi-send-check"></i> Send to Executive Manager</h1>
                    <p class="text-muted mb-0">Review admin-approved proposal before final submission</p>
                </div>
                <a href="sales_dashboard_main.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <div class="approval-card">
            <div class="success-badge">
                <i class="bi bi-check-circle-fill"></i> <strong>ADMIN APPROVED</strong>
            </div>
            
            <h3 class="mb-4">Proposal Details</h3>
            
            <div class="info-section">
                <h5 class="mb-3"><i class="bi bi-person-circle"></i> Client Information</h5>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Client Name:</strong> <?php echo htmlspecialchars($client_data['client_name'] ?? 'N/A'); ?></p>
                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($client_data['contact_no'] ?? 'N/A'); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($client_data['contact_email'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Event Type:</strong> <?php echo htmlspecialchars($client_data['event_type'] ?? 'N/A'); ?></p>
                        <p><strong>Event Date:</strong> <?php echo date('M d, Y h:i A', strtotime($client_data['date_time_of_event'] ?? 'now')); ?></p>
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($client_data['location_address'] ?? 'N/A'); ?></p>
                    </div>
                </div>
            </div>

            <div class="budget-highlight">
                <h4 class="mb-3">Budget Breakdown</h4>
                <div class="row">
                    <div class="col-md-4">
                        <p class="mb-1">Subtotal</p>
                        <h3 class="mb-0">₹<?php echo number_format($client_data['budget_draft_sales'] ?? 0, 2); ?></h3>
                    </div>
                    <div class="col-md-4">
                        <p class="mb-1">Discount</p>
                        <h3 class="mb-0">₹<?php echo number_format($client_data['discount_amount'] ?? 0, 2); ?></h3>
                    </div>
                    <div class="col-md-4">
                        <p class="mb-1"><strong>Final Budget</strong></p>
                        <h2 class="mb-0"><strong>₹<?php echo number_format($client_data['final_budget'] ?? 0, 2); ?></strong></h2>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <h5 class="mb-3"><i class="bi bi-list-check"></i> Proposal Items</h5>
                <div class="table-responsive items-table">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Item/Service Name</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $type_icons = [
                                'decor' => 'bi-palette',
                                'menu' => 'bi-menu-button-wide',
                                'logistics' => 'bi-truck',
                                'vas' => 'bi-star'
                            ];
                            
                            foreach ($proposal_items as $item): 
                                $icon = $type_icons[$item['item_type']] ?? 'bi-circle';
                            ?>
                            <tr>
                                <td>
                                    <i class="<?php echo $icon; ?>"></i> 
                                    <?php echo ucfirst($item['item_type']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>₹<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td><strong>₹<?php echo number_format($item['total_price'], 2); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (!empty($client_data['sales_notes'])): ?>
            <div class="info-section mt-4">
                <h5 class="mb-3"><i class="bi bi-chat-left-text"></i> Sales Notes</h5>
                <p><?php echo nl2br(htmlspecialchars($client_data['sales_notes'])); ?></p>
            </div>
            <?php endif; ?>

            <?php if (!empty($client_data['admin_notes'])): ?>
            <div class="info-section mt-4">
                <h5 class="mb-3"><i class="bi bi-shield-check"></i> Admin Approval Notes</h5>
                <p><?php echo nl2br(htmlspecialchars($client_data['admin_notes'])); ?></p>
                <p class="text-muted mb-0">
                    <small>Approved by: <?php echo htmlspecialchars($client_data['admin_approved_by'] ?? 'N/A'); ?> 
                    on <?php echo date('M d, Y h:i A', strtotime($client_data['admin_approved_at'] ?? 'now')); ?></small>
                </p>
            </div>
            <?php endif; ?>

            <div class="d-grid gap-2 mt-4">
                <button type="button" id="sendToExecutiveBtn" class="btn btn-send-executive">
                    <i class="bi bi-send-fill"></i> Send to Executive Manager
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        // Get client_id from URL
        const clientId = <?php echo json_encode($client_id); ?>;

        // Handle Send to Executive button click
        $('#sendToExecutiveBtn').on('click', function() {
            // Show confirmation dialog
            if (!confirm('Are you sure you want to send this proposal to Executive Manager for final approval?')) {
                return;
            }

            const button = $(this);
            const originalHTML = button.html();

            // Disable button and show loading state
            button.prop('disabled', true);
            button.html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Sending...');

            // Send AJAX request to update the workflow status
            $.ajax({
                url: 'send_to_executive_api.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    client_id: clientId,
                    // The API endpoint should update the 'clients' table workflow_stage to 'EXECUTIVE_REVIEW'
                    new_stage: 'EXECUTIVE_REVIEW' 
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        showAlert('success', response.message);
                        
                        // Redirect after 2 seconds
                        setTimeout(function() {
                            window.location.href = 'sales_dashboard_main.php?success=sent_to_executive';
                        }, 2000);
                    } else {
                        // Show error message
                        showAlert('danger', response.message || 'Failed to send proposal to executive');
                        
                        // Re-enable button
                        button.prop('disabled', false);
                        button.html(originalHTML);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    let errorMessage = 'A network error occurred while submitting the proposal.';
                    
                    try {
                        // Attempt to parse non-JSON error response for better message
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            errorMessage = response.message;
                        }
                    } catch (e) {
                         // Fallback to default message
                    }
                    
                    showAlert('danger', errorMessage);
                    
                    // Re-enable button
                    button.prop('disabled', false);
                    button.html(originalHTML);
                }
            });
        });

        // Function to show alert messages
        function showAlert(type, message) {
            // Remove any existing alerts first
            $('.alert.position-fixed').remove(); 
            
            const iconClass = type === 'success' ? 'check-circle' : 'exclamation-triangle';
            const alertHTML = `
                <div class="alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3" 
                     style="z-index: 9999; min-width: 300px; max-width: 500px;" role="alert">
                    <i class="bi bi-${iconClass}-fill me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            
            $('body').append(alertHTML);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut('slow', function() {
                    $(this).remove();
                });
            }, 5000);
        }
    });
    </script>
</body>
</html>