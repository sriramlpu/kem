<?php
/**
 * KMK/events/sales_person/client_view_proposal
 * Displays the complete, finalized (or in-progress) proposal details, including
 * itemized costs and budget summary.
 */

// Start session at the very top
session_start();

// --- 1. CONFIGURATION & REQUIRED INCLUDES (Mirroring previous file structure) ---

// Global table definitions (must be defined BEFORE including functions if used there)
$clientsTable = 'clients'; 
$proposalItemsTable = 'proposal_items';

// Placeholder table names for functions (if they are global)
$servTable = 'service'; 
$distTable = 'districts';
$docsTable = 'doctors_executives';
$specTable = 'specialities';
$cityTable = 'cities';
$adminTable = 'admin_users';

// Constants assumed by your functions for context (define if necessary)
if (!defined('TABLE_ISSUES')) define('TABLE_ISSUES', 'issues');
if (!defined('TABLE_REPLIES')) define('TABLE_REPLIES', 'issue_replies');
if (!defined('TABLE_STATUS')) define('TABLE_STATUS', 'issue_status');
if (!defined('TABLE_ITEMS')) define('TABLE_ITEMS', 'proposal_items'); 


// --- INCLUDE CORRECTED FUNCTIONS PATH ---
// Path: KMK/events/sales_person/ --> KMK/functions (Two levels up)
// NOTE: Uncomment this line if the actual functions file exists
// require_once('../../functions'); 


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

// --- 3. DATA RETRIEVAL & CALCULATION ---

$client_id = $_GET['client_id'] ?? null;
$client_data = null;
$proposal_items = [];
$calculated_subtotal = 0.0;
$calculated_final_budget = 0.0; // Initialize outside the if block

// Fetch client data (Secured with prepared statement)
if ($client_id) {
    $sql = "SELECT * FROM {$clientsTable} WHERE client_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $client_data = $result->fetch_assoc();
    } else {
        $stmt->close();
        die("Client not found.");
    }
    $stmt->close();
    
    // Fetch proposal items (Secured with prepared statement, ordered for presentation)
    $sql_items = "SELECT * FROM {$proposalItemsTable} WHERE client_id = ? ORDER BY FIELD(item_type, 'menu', 'decor', 'logistics', 'vas'), item_name";
    $stmt_items = $conn->prepare($sql_items);
    $stmt_items->bind_param("i", $client_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    
    while ($row = $result_items->fetch_assoc()) {
        $proposal_items[] = $row;
        // Calculate subtotal from all proposal items' total_price column
        $calculated_subtotal += (float)$row['total_price'];
    }
    $stmt_items->close();
    
    // Calculate the final budget based on item totals and stored discount
    $stored_discount = (float)($client_data['discount_amount'] ?? 0.0);
    $calculated_final_budget = $calculated_subtotal - $stored_discount;
}

$conn->close();
if (!isset($client_data)) {
    // If client_id was provided but didn't return data, or if no ID was provided.
    die("Invalid Client ID provided or client data could not be loaded.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposal: <?php echo htmlspecialchars($client_data['client_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            padding: 20px 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .container {
            max-width: 1200px;
        }
        
        .proposal-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 30px;
        }
        
        .section-header {
            font-size: 1.5rem;
            font-weight: 600;
            color: #0d6efd; /* Bootstrap primary color */
            margin-bottom: 20px;
            padding-bottom: 5px;
            border-bottom: 2px solid #0d6efd;
            display: flex;
            align-items: center;
        }

        .info-row {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .item-type-badge {
            padding: 4px 8px;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .budget-box {
            background-color: #e9ecef; /* Light gray background */
            color: #212529;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #dee2e6;
        }
        
        .final-price {
            background-color: #0d6efd;
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-top: 15px;
        }
        
        /* Workflow Status Badges for Sidebar */
        .status-badge {
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 1rem;
        }

        /* Print Styles */
        @media print {
            body {
                background-color: white !important;
                padding: 0;
            }
            .proposal-container {
                box-shadow: none !important;
                border: none;
                padding: 0;
            }
            .btn {
                display: none !important;
            }
            .section-header {
                color: #212529 !important;
                border-bottom-color: #212529 !important;
            }
            .page-header {
                box-shadow: none !important;
                padding: 0;
                margin-bottom: 20px;
                border-bottom: 2px solid #ccc;
            }
            .budget-box, .final-price {
                background-color: #f1f1f1 !important; /* Lighter background for print */
                -webkit-print-color-adjust: exact;
                color: #212529 !important;
            }
            .final-price h2, .final-price h5 {
                color: #212529 !important; /* Ensure final price text is dark in print */
            }
            .info-row {
                border-bottom: 1px solid #ccc;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4 p-3 bg-white rounded shadow-sm">
            <div>
                <h1 class="h3 mb-0">Proposal for **<?php echo htmlspecialchars($client_data['client_name']); ?>**</h1>
                <p class="text-muted mb-0"><i class="bi bi-calendar-check"></i> Event Date: <?php echo date('F d, Y', strtotime($client_data['date_time_of_event'])); ?></p>
            </div>
            <div>
                <a href="sales_dashboard_main" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="bi bi-printer"></i> Print Proposal
                </button>
            </div>
        </div>
        <div class="proposal-container">
            <div class="row">
                <div class="col-lg-8">
                    
                    <div class="mb-4">
                        <div class="section-header"><i class="bi bi-person-circle me-2"></i> Client & Contact Details</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-row"><strong>Name:</strong> <?php echo htmlspecialchars($client_data['client_name']); ?></div>
                                <div class="info-row"><strong>Contact No:</strong> <?php echo htmlspecialchars($client_data['contact_no']); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-row"><strong>Email:</strong> <?php echo htmlspecialchars($client_data['contact_email'] ?? 'N/A'); ?></div>
                                <div class="info-row"><strong>Lead Source:</strong> <?php echo htmlspecialchars($client_data['lead_source']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="section-header"><i class="bi bi-calendar-event me-2"></i> Event Details</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-row"><strong>Event Type:</strong> <?php echo htmlspecialchars($client_data['event_type'] ?? 'N/A'); ?></div>
                                <div class="info-row"><strong>Date & Time:</strong> <?php echo date('l, F d, Y h:i A', strtotime($client_data['date_time_of_event'])); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-row"><strong>Location:</strong> <?php echo htmlspecialchars($client_data['location_address'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                        <?php if (!empty($client_data['function_details'])): ?>
                        <div class="mt-3 p-3 bg-light rounded">
                            <h6 class="text-primary fw-bold">Function Specifics:</h6>
                            <p class="mb-0 small text-muted"><?php echo nl2br(htmlspecialchars($client_data['function_details'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (count($proposal_items) > 0): ?>
                    <div class="mb-4">
                        <div class="section-header"><i class="bi bi-list-check me-2"></i> Proposed Items & Services</div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>Type</th>
                                        <th>Item/Service</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-end">Unit Price</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $type_badges = [
                                        'menu' => 'bg-success',
                                        'decor' => 'bg-info',
                                        'logistics' => 'bg-warning text-dark',
                                        'vas' => 'bg-primary'
                                    ];
                                    
                                    foreach ($proposal_items as $item): 
                                        $badge_class = $type_badges[$item['item_type']] ?? 'bg-secondary';
                                    ?>
                                    <tr>
                                        <td><span class="item-type-badge <?php echo $badge_class; ?> text-white"><?php echo ucfirst($item['item_type']); ?></span></td>
                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                                        <td class="text-end">₹<?php echo number_format($item['unit_price'], 2); ?></td>
                                        <td class="text-end"><strong>₹<?php echo number_format($item['total_price'], 2); ?></strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-active">
                                        <th colspan="4" class="text-end">Subtotal (Items):</th>
                                        <th class="text-end"><strong>₹<?php echo number_format($calculated_subtotal, 2); ?></strong></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-4">
                    
                    <div class="mb-4 budget-box">
                        <div class="section-header border-bottom-0 p-0 mb-3" style="color:#0d6efd"><i class="bi bi-diagram-3 me-2"></i> Proposal Status</div>
                        
                        <div class="text-center mb-3">
                            <?php
                            // Determine status badge configuration
                            $stage_config = [
                                'LEAD_CREATED' => ['badge' => 'secondary', 'text' => 'Lead Created'],
                                'SALES_DRAFT' => ['badge' => 'warning', 'text' => 'Draft in Progress'],
                                'ADMIN_REVIEW' => ['badge' => 'info', 'text' => 'Admin Review'],
                                'ADMIN_APPROVED' => ['badge' => 'success', 'text' => 'Admin Approved'],
                                'EXECUTIVE_REVIEW' => ['badge' => 'primary', 'text' => 'Executive Review'],
                                'EXECUTIVE_APPROVED' => ['badge' => 'success', 'text' => 'Proposal Finalized'],
                                'REJECTED' => ['badge' => 'danger', 'text' => 'Rejected']
                            ];
                            $config = $stage_config[$client_data['workflow_stage'] ?? 'LEAD_CREATED'] ?? $stage_config['LEAD_CREATED'];
                            ?>
                            <span class="status-badge bg-<?php echo $config['badge']; ?> text-white shadow-sm">
                                **<?php echo $config['text']; ?>**
                            </span>
                        </div>
                        
                        <div class="info-row small">
                            **Admin Approval:** <span class="badge bg-<?php echo ($client_data['admin_status'] ?? '') == 'APPROVED' ? 'success' : (($client_data['admin_status'] ?? '') == 'REJECTED' ? 'danger' : 'warning'); ?>">
                                <?php echo htmlspecialchars($client_data['admin_status'] ?? 'PENDING'); ?>
                            </span>
                        </div>
                        
                        <div class="info-row small">
                            **Executive Approval:** <span class="badge bg-<?php echo ($client_data['executive_status'] ?? '') == 'APPROVED' ? 'success' : (($client_data['executive_status'] ?? '') == 'REJECTED' ? 'danger' : 'warning'); ?>">
                                <?php echo htmlspecialchars($client_data['executive_status'] ?? 'PENDING'); ?>
                            </span>
                        </div>
                    </div>

                    <div class="mb-4 budget-box">
                        <div class="section-header border-bottom-0 p-0 mb-3" style="color:#198754"><i class="bi bi-currency-rupee me-2"></i> Pricing Summary</div>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span class="fw-bold">Subtotal (Items + Services):</span>
                            <span>₹<?php echo number_format($calculated_subtotal, 2); ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between border-bottom pb-2 mb-3">
                            <span class="fw-bold text-danger">Discount Applied:</span>
                            <span class="text-danger">**- ₹<?php echo number_format($client_data['discount_amount'] ?? 0.0, 2); ?>**</span>
                        </div>
                        
                        <div class="final-price">
                            <h5 class="mb-1 text-uppercase">Final Client Budget</h5>
                            <h2 class="mb-0">**₹<?php echo number_format($calculated_final_budget, 2); ?>**</h2>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="section-header"><i class="bi bi-chat-left-text me-2"></i> Internal Notes</div>
                        
                        <?php if (!empty($client_data['sales_notes'])): ?>
                        <div class="mb-2 p-2 bg-light rounded border-start border-primary border-4 small">
                            <h6 class="text-primary fw-bold mb-1">Sales Notes:</h6>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($client_data['sales_notes'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($client_data['admin_notes']) || !empty($client_data['executive_notes'])): ?>
                        <div class="p-2 bg-light rounded border-start border-success border-4 small">
                            <h6 class="text-success fw-bold mb-1">Approval Notes:</h6>
                            <?php if (!empty($client_data['admin_notes'])): ?><p class="mb-0">**Admin:** <?php echo nl2br(htmlspecialchars($client_data['admin_notes'])); ?></p><?php endif; ?>
                            <?php if (!empty($client_data['executive_notes'])): ?><p class="mb-0">**Executive:** <?php echo nl2br(htmlspecialchars($client_data['executive_notes'])); ?></p><?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>