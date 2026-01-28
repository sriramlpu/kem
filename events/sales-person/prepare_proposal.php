<?php
/**
 * KMK/events/sales_person/client_proposal_prepare
 * Allows a sales person to build a detailed proposal including decor, menu items,
 * and additional services for a specific client.
 */

// Start session at the very top
session_start();

// --- 1. CONFIGURATION & REQUIRED INCLUDES (Mirroring client_sales_update structure) ---

// Simple placeholder session check and setting (IMPROVEMENT: Should use a proper login system)
if (!isset($_SESSION['sales_person'])) {
    $_SESSION['sales_person'] = 'Sales Representative';
}

// Global table definitions (must be defined BEFORE including functions if used there)
$clientsTable = 'clients'; 
$decorPackagesTable = 'decor_packages';
$menuItemsTable = 'menu_items';

// Placeholder table names for functions (if they are global)
$servTable = 'service'; 
$distTable = 'districts';
$docsTable = 'doctors_executives';
$specTable = 'specialities';
$cityTable = 'cities';
$adminTable = 'admin_users';
$proposalItemsTable = 'proposal_items'; // Added for context/completeness

// Constants assumed by your functions for context (define if necessary)
if (!defined('TABLE_ISSUES')) define('TABLE_ISSUES', 'issues');
if (!defined('TABLE_REPLIES')) define('TABLE_REPLIES', 'issue_replies');
if (!defined('TABLE_STATUS')) define('TABLE_STATUS', 'issue_status');
if (!defined('TABLE_ITEMS')) define('TABLE_ITEMS', 'proposal_items'); 


// --- INCLUDE CORRECTED FUNCTIONS PATH ---
// Path: KMK/events/sales_person/ --> KMK/functions (Two levels up)
// NOTE: This assumes 'functions' exists two levels up, similar to client_sales_update
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

// --- 3. DATA RETRIEVAL ---

$client_id = $_GET['client_id'] ?? null;
$client_data = null;

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
        // Attempt to close statement before dying
        $stmt->close(); 
        die("Client not found for ID: " . htmlspecialchars($client_id));
    }
    $stmt->close();
} else {
    die("Client ID is required to prepare a proposal.");
}

// Fetch menu items
$menu_items = [];
$sql_menu = "SELECT * FROM menu_items WHERE is_active = 1 ORDER BY category, standard_level, item_name";
$result_menu = $conn->query($sql_menu);
if ($result_menu) {
    while ($row = $result_menu->fetch_assoc()) {
        // Group menu items by category for display
        $menu_items[$row['category']][] = $row;
    }
}

// Fetch decor packages
$decor_packages = [];
$sql_decor = "SELECT * FROM decor_packages WHERE is_active = 1 ORDER BY package_type";
$result_decor = $conn->query($sql_decor);
if ($result_decor) {
    while ($row = $result_decor->fetch_assoc()) {
        $decor_packages[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prepare Proposal | <?php echo htmlspecialchars($client_data['client_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Base Styling - Using neutral Bootstrap colors */
        body {
            /* Light gray background is professional */
            background-color: #f8f9fa; 
            min-height: 100vh;
            padding: 20px 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .proposal-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-header {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .client-info-card {
            /* Using standard Bootstrap primary color */
            background-color: var(--bs-primary); 
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .section-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        
        .section-title {
            font-weight: 700;
            color: var(--bs-dark);
            margin-bottom: 20px;
            padding-bottom: 10px;
            /* Underline using Bootstrap info color for professionalism */
            border-bottom: 3px solid var(--bs-info); 
        }
        
        /* Menu & Decor Item Styling */
        .menu-item-card {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 10px 15px;
            margin-bottom: 10px;
            transition: all 0.2s ease;
            background: #ffffff;
            height: 100%;
        }
        
        .menu-item-card.selected {
            /* Green for selection */
            border-color: var(--bs-success); 
            background: #e9f7ef; /* Very light green */
        }

        .decor-package-card {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .decor-package-card.selected {
            border-color: var(--bs-success); 
            background: #e9f7ef;
        }
        
        .item-checkbox:checked {
            background-color: var(--bs-success);
            border-color: var(--bs-success);
        }

        .quantity-input {
            width: 65px;
            text-align: center;
            padding: 3px;
            font-size: 0.9rem;
            height: 30px;
        }
        
        .item-details {
            flex-grow: 1;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* Badge Styling */
        .category-badge {
            padding: 6px 12px;
            border-radius: 15px;
            margin-bottom: 15px;
            /* Using secondary for categories */
            background-color: var(--bs-secondary) !important; 
        }
        .standard-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            /* Using light/dark text */
            background-color: var(--bs-light); 
        }
        
        /* Budget Summary Styling */
        .budget-summary {
            position: sticky;
            top: 20px;
            /* Using success color for positive financial summary */
            background-color: var(--bs-success); 
            color: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .budget-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.3);
        }
        
        /* Ensure price text color looks good */
        .menu-item-card .text-primary {
             color: var(--bs-primary) !important;
        }
    </style>
</head>
<body>
    <div class="proposal-container">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="bi bi-file-earmark-plus text-info"></i> Prepare Proposal</h1>
                    <p class="text-muted mb-0">Create a detailed proposal for the client: **<?php echo htmlspecialchars($client_data['client_name']); ?>**</p>
                </div>
                <a href="sales_dashboard_main" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <div class="client-info-card">
            <h4 class="mb-3"><i class="bi bi-person-circle"></i> Client & Event Details</h4>
            <div class="row">
                <div class="col-md-3">
                    <strong>Event Date:</strong><br>
                    <?php echo date('M d, Y h:i A', strtotime($client_data['date_time_of_event'])); ?>
                </div>
                <div class="col-md-3">
                    <strong>Event Type:</strong><br>
                    <?php echo htmlspecialchars($client_data['event_type'] ?? 'N/A'); ?>
                </div>
                <div class="col-md-3">
                    <strong>Contact:</strong><br>
                    <?php echo htmlspecialchars($client_data['contact_no']); ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small mb-0">Expected Guest Count:</label>
                    <input type="number" id="guestCount" class="form-control form-control-sm" value="50" min="1" onchange="updateBudget()">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                
                <div class="section-card">
                    <h3 class="section-title"><i class="bi bi-palette text-info"></i> Decor Packages (Select One)</h3>
                    <div id="decorPackages" class="row row-cols-md-2 g-3">
                        <?php foreach ($decor_packages as $package): ?>
                        <div class="col">
                            <div class="decor-package-card" data-decor-id="<?php echo $package['decor_id']; ?>" data-price="<?php echo $package['base_price']; ?>" onclick="selectDecor(<?php echo $package['decor_id']; ?>, <?php echo $package['base_price']; ?>, this)">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($package['package_name']); ?></h5>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($package['package_type']); ?></span>
                                        <p class="text-muted mt-2 mb-0 small"><?php echo htmlspecialchars($package['description'] ?? ''); ?></p>
                                    </div>
                                    <div class="text-end">
                                        <h4 class="text-primary mb-0">₹<?php echo number_format($package['base_price'], 2); ?></h4>
                                        <small class="text-muted">Flat Price</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="section-card">
                    <h3 class="section-title"><i class="bi bi-menu-button-wide text-info"></i> Menu Items (Price per Plate)</h3>
                    
                    <?php foreach ($menu_items as $category => $items): ?>
                    <div class="mb-4">
                        <span class="category-badge text-white bg-secondary">
                            <i class="bi bi-tag-fill me-1"></i> <?php echo htmlspecialchars($category); ?>
                        </span>
                        
                        <div class="row row-cols-1 row-cols-lg-2 g-3">
                            <?php foreach ($items as $item): ?>
                            <div class="col">
                                <div class="menu-item-card" id="menu_card_<?php echo $item['menu_id']; ?>">
                                    <div class="d-flex align-items-center justify-content-between">
                                        
                                        <div class="item-details">
                                            <input type="checkbox" 
                                                     class="item-checkbox form-check-input" 
                                                     id="menu_<?php echo $item['menu_id']; ?>" 
                                                     value="<?php echo $item['menu_id']; ?>"
                                                     data-price="<?php echo $item['price_per_plate']; ?>"
                                                     onchange="toggleMenuItem(this)">
                                            
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 text-truncate"><?php echo htmlspecialchars($item['item_name']); ?></h6>
                                                <span class="standard-badge bg-light text-dark">
                                                    <?php echo htmlspecialchars($item['standard_level']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="text-end d-flex align-items-center flex-shrink-0">
                                            <div class="text-end me-2">
                                                <strong class="text-primary small">₹<?php echo number_format($item['price_per_plate'], 2); ?></strong>
                                            </div>

                                            <div class="text-center">
                                                <input type="number" 
                                                         class="form-control quantity-input" 
                                                         id="qty_<?php echo $item['menu_id']; ?>" 
                                                         value="0" 
                                                         min="0" 
                                                         onchange="updateBudget()" 
                                                         disabled>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="section-card">
                    <h3 class="section-title"><i class="bi bi-plus-circle text-info"></i> Additional Services (Flat Costs)</h3>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small mb-1">Logistics Cost:</label>
                            <input type="number" id="logisticsCost" class="form-control form-control-sm" value="0" min="0" step="0.01" onchange="updateBudget()">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small mb-1">VAS Cost (Value Added Services):</label>
                            <input type="number" id="vasCost" class="form-control form-control-sm" value="0" min="0" step="0.01" onchange="updateBudget()">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small mb-1">Logistics Details</label>
                        <textarea id="logisticsDetails" class="form-control form-control-sm" rows="2" placeholder="e.g., Transport charges, setup labor details..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small mb-1">VAS Details</label>
                        <textarea id="vasDetails" class="form-control form-control-sm" rows="2" placeholder="e.g., Specialized lighting, sound system rental..."></textarea>
                    </div>
                </div>

                <div class="section-card">
                    <h3 class="section-title"><i class="bi bi-chat-left-text text-info"></i> Proposal Notes</h3>
                    <textarea id="salesNotes" class="form-control" rows="4" placeholder="Add notes about this proposal, special client requests, or justifications for the pricing/discount..."></textarea>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="budget-summary">
                    <h4 class="mb-4 text-center"><i class="bi bi-calculator"></i> Budget Summary</h4>
                    
                    <div class="budget-row">
                        <span>Decor Package:</span>
                        <strong id="decorCost">₹0.00</strong>
                    </div>
                    
                    <div class="budget-row">
                        <span>Menu Subtotal:</span>
                        <strong id="menuCost">₹0.00</strong>
                    </div>
                    
                    <div class="budget-row">
                        <span>Logistics Cost:</span>
                        <strong id="logisticsSummary">₹0.00</strong>
                    </div>
                    
                    <div class="budget-row">
                        <span>VAS Cost:</span>
                        <strong id="vasSummary">₹0.00</strong>
                    </div>
                    
                    <div class="budget-row">
                        <span><strong>Gross Subtotal:</strong></span>
                        <strong id="subtotal">₹0.00</strong>
                    </div>
                    
                    <div class="mb-3 mt-3">
                        <label class="form-label small">Discount Amount:</label>
                        <input type="number" id="discountAmount" class="form-control form-control-sm" value="0" min="0" step="0.01" onchange="updateBudget()">
                    </div>
                    
                    <div class="budget-row">
                        <span><h5 class="mb-0">Final Budget:</h5></span>
                        <h4 class="mb-0" id="finalBudget">₹0.00</h4>
                    </div>
                    
                    <button class="btn btn-light btn-lg w-100 mt-4" onclick="submitProposal()">
                        <i class="bi bi-send-check"></i> Submit Proposal to Admin
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store selected decor and menu items state
        let selectedDecor = 0;
        let selectedDecorPrice = 0;
        let selectedMenuItems = {}; // { menu_id: { id: menu_id, price: unit_price, qty: quantity } }

        /**
         * Toggles the selection state of a menu item and updates its quantity.
         * @param {HTMLInputElement} checkbox The checkbox element.
         */
        function toggleMenuItem(checkbox) {
            const menuId = checkbox.value;
            const card = document.getElementById('menu_card_' + menuId);
            const qtyInput = document.getElementById('qty_' + menuId);

            if (checkbox.checked) {
                card.classList.add('selected');
                qtyInput.disabled = false;
                
                // Default quantity is the Guest Count, min 1
                const defaultQty = Math.max(1, parseInt(document.getElementById('guestCount').value) || 1);
                qtyInput.value = defaultQty;
                
                selectedMenuItems[menuId] = {
                    id: menuId,
                    price: parseFloat(checkbox.dataset.price),
                    qty: defaultQty 
                };
            } else {
                card.classList.remove('selected');
                qtyInput.disabled = true;
                qtyInput.value = 0; 
                
                delete selectedMenuItems[menuId];
            }
            updateBudget();
        }

        /**
         * Handles decor package selection (exclusive choice).
         * @param {number} decorId The ID of the decor package.
         * @param {number} price The base price of the package.
         * @param {HTMLElement} currentTarget The clicked card element.
         */
        function selectDecor(decorId, price, currentTarget) {
            // Remove previous selection from all cards
            document.querySelectorAll('.decor-package-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selection to clicked card
            currentTarget.classList.add('selected');
            
            selectedDecor = decorId;
            selectedDecorPrice = price;
            updateBudget();
        }

        /**
         * Recalculates and updates the budget summary.
         */
        function updateBudget() {
            const guestCount = parseInt(document.getElementById('guestCount').value) || 0; 
            const logisticsCost = parseFloat(document.getElementById('logisticsCost').value) || 0;
            const vasCost = parseFloat(document.getElementById('vasCost').value) || 0;
            const discountAmount = parseFloat(document.getElementById('discountAmount').value) || 0;

            // Update menu item quantities from inputs and calculate cost
            let menuCost = 0;
            for (const menuId in selectedMenuItems) {
                const qtyInput = document.getElementById('qty_' + menuId);
                const currentQty = Math.max(0, parseInt(qtyInput.value) || 0);
                
                // Keep the input value updated
                qtyInput.value = currentQty;

                // Update quantity in the JS model and calculate cost
                selectedMenuItems[menuId].qty = currentQty;
                menuCost += selectedMenuItems[menuId].price * currentQty;
            }

            // Calculate subtotal
            const subtotal = selectedDecorPrice + menuCost + logisticsCost + vasCost;
            const finalBudget = Math.max(0, subtotal - discountAmount); 

            // Update display
            document.getElementById('decorCost').textContent = '₹' + selectedDecorPrice.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('menuCost').textContent = '₹' + menuCost.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('logisticsSummary').textContent = '₹' + logisticsCost.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('vasSummary').textContent = '₹' + vasCost.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('subtotal').textContent = '₹' + subtotal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('finalBudget').textContent = '₹' + finalBudget.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        /**
         * Submits the entire proposal data to the API.
         */
        function submitProposal() {
            const submitBtn = document.querySelector('.budget-summary button');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Submitting...';
            
            updateBudget(); // Final calculation sync
            
            const clientId = <?php echo $client_id; ?>;
            const guestCount = parseInt(document.getElementById('guestCount').value) || 0;
            const logisticsCost = parseFloat(document.getElementById('logisticsCost').value) || 0;
            const vasCost = parseFloat(document.getElementById('vasCost').value) || 0;
            const discountAmount = parseFloat(document.getElementById('discountAmount').value) || 0;
            const logisticsDetails = document.getElementById('logisticsDetails').value;
            const vasDetails = document.getElementById('vasDetails').value;
            const salesNotes = document.getElementById('salesNotes').value;

            // Get the calculated subtotal before discount
            const subtotalText = document.getElementById('subtotal').textContent.replace('₹', '').replace(/,/g, '');
            const subtotal = parseFloat(subtotalText) || 0;
            
            // --- Client-Side Validation ---
            if (selectedDecor === 0) {
                 alert('🛑 Please select a Decor Package.');
                 submitBtn.disabled = false;
                 submitBtn.innerHTML = '<i class="bi bi-send-check"></i> Submit Proposal to Admin';
                 return;
            }
            if (Object.keys(selectedMenuItems).length === 0) {
                 alert('🛑 Please select at least one Menu Item.');
                 submitBtn.disabled = false;
                 submitBtn.innerHTML = '<i class="bi bi-send-check"></i> Submit Proposal to Admin';
                 return;
            }
            if (guestCount <= 0) {
                 alert('🛑 Guest count must be greater than zero.');
                 submitBtn.disabled = false;
                 submitBtn.innerHTML = '<i class="bi bi-send-check"></i> Submit Proposal to Admin';
                 return;
            }

            // Prepare form data for API
            const formData = new FormData();
            formData.append('client_id', clientId);
            formData.append('budget_draft_sales', subtotal); // Sending the gross subtotal
            formData.append('discount_amount', discountAmount);
            formData.append('sales_notes', salesNotes);
            formData.append('expected_guest_count', guestCount); 
            formData.append('decor_id', selectedDecor);
            formData.append('logistics_cost', logisticsCost);
            formData.append('logistics_details', logisticsDetails);
            formData.append('vas_cost', vasCost);
            formData.append('vas_details', vasDetails);
            
            // Menu Items with Quantity (Serialized)
            for (const menuId in selectedMenuItems) {
                const item = selectedMenuItems[menuId];
                // Append structured data for the backend to process and insert into proposal_items
                formData.append('proposal_items[]', JSON.stringify({
                    id: item.id,
                    qty: item.qty,
                    price: item.price 
                }));
            }

            // Submit via AJAX to the dedicated API endpoint
            fetch('save_proposal_api', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Proposal submitted successfully to Admin for review!');
                    window.location.href = 'sales_dashboard_main?success=proposal_created';
                } else {
                    alert('❌ Error submitting proposal: ' + data.error);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-send-check"></i> Submit Proposal to Admin';
                }
            })
            .catch(error => {
                alert('❌ Network error: Failed to connect to API endpoint.');
                console.error('Fetch Error:', error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-send-check"></i> Submit Proposal to Admin';
            });
        }

        // Initialize budget on page load
        document.addEventListener('DOMContentLoaded', updateBudget);
    </script>
</body>
</html>