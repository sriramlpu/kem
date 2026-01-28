<?php
/**
 * KMK/events/sales_person/client_sales_update_menu
 * This script allows a sales person to select and modify quantities for menu items
 * related to a specific client proposal.
 */

// Start session at the very top
session_start();

// --- 1. CONFIGURATION & REQUIRED INCLUDES (Mirroring client_sales_update structure) ---

// Define tables needed by this script (and assumed to be defined globally)
$clientsTable = 'clients'; 
$proposalItemsTable = 'proposal_items';
$menuItemsTable = 'menu_items';

// Placeholder table names for functions (if included)
// NOTE: These are not strictly used in this specific file's logic but are kept for structural consistency.
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

// Placeholder for functions (assumes its path is correct relative to execution location)
// If a real functions existed, its content would be loaded here.
// require_once('../../functions'); 

// --- 2. USER AUTHENTICATION & DATABASE CONNECTION ---

// Simple placeholder session check and setting
if (!isset($_SESSION['sales_person'])) {
    $_SESSION['sales_person'] = 'Sales Representative';
}

// Database connection details
$servername = "localhost";
$username = "kmkglobal_web";
$password = "tI]rfPhdOo9zHdKw"; // WARNING: Change for production
$dbname = "kmkglobal_web";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- 3. INITIAL DATA RETRIEVAL & SETUP ---

$client_id = $_GET['client_id'] ?? null;
$client_data = null;
$proposal_items_map = []; // Map: [menu_id => quantity]
$is_editable = false;
$total_selected_count = 0;

if (!$client_id) {
    die("Error: Client ID is required.");
}

// Fetch client data (Secured with prepared statement)
$sql_client = "SELECT client_id, client_name, date_time_of_event, event_type, final_budget, workflow_stage FROM {$clientsTable} WHERE client_id = ?";
$stmt_client = $conn->prepare($sql_client);
$stmt_client->bind_param("i", $client_id);
$stmt_client->execute();
$result_client = $stmt_client->get_result();

if ($result_client->num_rows === 1) {
    $client_data = $result_client->fetch_assoc();
    
    // Workflow lock: Editing is disabled if the proposal is approved or under executive review.
    $locked_stages = ['ADMIN_APPROVED', 'EXECUTIVE_REVIEW', 'EXECUTIVE_APPROVED', 'COMPLETED', 'INVOICED'];
    $is_editable = !in_array($client_data['workflow_stage'], $locked_stages);
} else {
    $stmt_client->close(); 
    die("Client not found for ID: " . htmlspecialchars($client_id));
}
$stmt_client->close();

// Fetch previously selected MENU items and their quantity (Secured with prepared statement)
$sql_items = "SELECT item_id, quantity FROM {$proposalItemsTable} WHERE client_id = ? AND item_type = 'menu'";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $client_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();

while ($row = $result_items->fetch_assoc()) {
    $proposal_items_map[(int)$row['item_id']] = (int)$row['quantity'];
}
$stmt_items->close();

// Fetch all available menu items (to display the full selection list)
$menu_items = [];
$sql_menu = "SELECT menu_id, item_name, category, standard_level, price_per_plate, is_active FROM {$menuItemsTable} WHERE is_active = 1 ORDER BY category, standard_level, item_name";
$result_menu = $conn->query($sql_menu);
if ($result_menu) {
    while ($row = $result_menu->fetch_assoc()) {
        // Group menu items by category for display
        $menu_items[$row['category']][] = $row;
    }
}

// Calculate initial count for the badge
$total_selected_count = count($proposal_items_map);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Proposal Menu | <?php echo htmlspecialchars($client_data['client_name'] ?? 'Loading...'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* CSS is identical to the provided code for visual consistency */
        body {
            background-color: #f8f9fa; 
            min-height: 100vh;
            padding: 20px 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .container { max-width: 1200px; }
        
        .page-header {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .menu-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        
        .client-info-bar {
            background-color: var(--bs-primary);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .section-title {
            font-weight: 700;
            color: var(--bs-dark);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--bs-info);
        }
        
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
            border-color: var(--bs-success);
            background: #e9f7ef;
        }
        
        .menu-item-card.locked {
            background: #f3f4f6;
            opacity: 0.7;
            cursor: not-allowed;
            border: 1px dashed var(--bs-secondary);
        }
        
        .item-checkbox:checked {
            background-color: var(--bs-success);
            border-color: var(--bs-success);
        }

        .locked-message {
            background-color: var(--bs-danger);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .item-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            flex-shrink: 0;
        }
        
        .category-badge {
            padding: 6px 12px;
            border-radius: 15px;
            margin-bottom: 15px;
            background-color: var(--bs-secondary) !important;
        }
        
        .standard-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
            background-color: var(--bs-light);
        }
        
        .quantity-input {
            width: 65px;
            text-align: center;
            padding: 3px;
            font-size: 0.9rem;
            height: 30px;
        }

        .text-primary {
             color: var(--bs-primary) !important;
        }
        
        .item-row-details {
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="bi bi-pencil-square text-info"></i> Edit Proposal Menu</h1>
                    <p class="text-muted mb-0">Modify menu items for proposal: **<?php echo htmlspecialchars($client_data['client_name'] ?? 'N/A'); ?>**</p>
                </div>
                <div>
                    <a href="view_proposal?client_id=<?php echo $client_id; ?>" class="btn btn-outline-primary me-2">
                        <i class="bi bi-eye"></i> View Proposal
                    </a>
                    <a href="sales_dashboard_main" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>

        <div class="client-info-bar">
            <div class="row">
                <div class="col-md-3">
                    <small><i class="bi bi-person"></i> Client:</small>
                    <div><strong><?php echo htmlspecialchars($client_data['client_name'] ?? 'N/A'); ?></strong></div>
                </div>
                <div class="col-md-3">
                    <small><i class="bi bi-calendar"></i> Event Date:</small>
                    <div><strong><?php echo date('M d, Y', strtotime($client_data['date_time_of_event'] ?? 'now')); ?></strong></div>
                </div>
                <div class="col-md-3">
                    <small><i class="bi bi-tag"></i> Event Type:</small>
                    <div><strong><?php echo htmlspecialchars($client_data['event_type'] ?? 'N/A'); ?></strong></div>
                </div>
                <div class="col-md-3">
                    <small><i class="bi bi-currency-rupee"></i> Current Budget:</small>
                    <div><strong>₹<?php echo number_format($client_data['final_budget'] ?? 0, 2); ?></strong></div>
                </div>
            </div>
        </div>

        <?php if (!$is_editable): ?>
        <div class="locked-message">
            <h5><i class="bi bi-lock-fill"></i> Editing Locked</h5>
            <p class="mb-0">This proposal has been approved by admin and can no longer be edited. Current status: <strong><?php echo htmlspecialchars($client_data['workflow_stage'] ?? 'N/A'); ?></strong></p>
        </div>
        <?php endif; ?>

        <div class="menu-card">
            <h3 class="section-title"><i class="bi bi-menu-button-wide text-info"></i> Edit Menu Items</h3>
            
            <?php if ($is_editable): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> <strong>Tip:</strong> Check/Uncheck items and adjust the **Plates Count** (Quantity). Click Save to apply changes.
            </div>
            <?php endif; ?>

            <div class="mb-3">
                <span id="selectedCountBadge" class="badge bg-success me-2">
                    <i class="bi bi-check-circle"></i> <?php echo $total_selected_count; ?> items currently selected
                </span>
            </div>

            <form id="menuEditForm">
                <input type="hidden" name="client_id" value="<?php echo htmlspecialchars($client_id); ?>">
                
                <?php if (count($menu_items) > 0): ?>
                    <?php foreach ($menu_items as $category => $items): ?>
                    <div class="mb-4">
                        <span class="category-badge text-white bg-secondary">
                            <i class="bi bi-tag-fill"></i> <?php echo htmlspecialchars($category); ?>
                        </span>
                        
                        <div class="row row-cols-1 row-cols-lg-2 g-3">
                            <?php foreach ($items as $item): 
                                $menu_id = $item['menu_id'];
                                $is_selected = array_key_exists($menu_id, $proposal_items_map);
                                $current_qty = $proposal_items_map[$menu_id] ?? 0;
                            ?>
                            <div class="col">
                                <div class="menu-item-card <?php echo $is_selected ? 'selected' : ''; ?> <?php echo !$is_editable ? 'locked' : ''; ?>" 
                                      id="menu_card_<?php echo $menu_id; ?>">
                                    <div class="d-flex align-items-center justify-content-between">
                                        
                                        <div class="item-row-details flex-grow-1">
                                            <input type="checkbox" 
                                                     class="item-checkbox form-check-input" 
                                                     data-menu-id="<?php echo $menu_id; ?>"
                                                     name="menu_items[]"
                                                     value="<?php echo $menu_id; ?>"
                                                     data-price="<?php echo $item['price_per_plate']; ?>"
                                                     <?php echo $is_selected ? 'checked' : ''; ?>
                                                     <?php echo !$is_editable ? 'disabled' : ''; ?>
                                                     onchange="toggleMenuItem(this)">
                                            
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 text-truncate"><?php echo htmlspecialchars($item['item_name']); ?></h6>
                                                <span class="standard-badge bg-light text-dark me-2">
                                                    <?php echo htmlspecialchars($item['standard_level']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex align-items-center flex-shrink-0">
                                            
                                            <div class="text-center me-3">
                                                <small class="text-muted d-block" style="font-size: 0.7rem;">Plates</small>
                                                <input type="number" 
                                                         class="form-control form-control-sm quantity-input" 
                                                         id="qty_<?php echo $menu_id; ?>" 
                                                         value="<?php echo $current_qty; ?>" 
                                                         min="1" 
                                                         <?php echo $is_selected ? '' : 'disabled'; ?>
                                                         <?php echo !$is_editable ? 'disabled' : ''; ?>
                                                         onchange="formChanged = true; updateModel(<?php echo $menu_id; ?>)">
                                            </div>

                                            <div class="text-end">
                                                <strong class="text-primary">₹<?php echo number_format($item['price_per_plate'], 2); ?></strong>
                                                <small class="text-muted d-block" style="font-size: 0.7rem;">/plate</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> No menu items available or loaded.
                    </div>
                <?php endif; ?>

                <?php if ($is_editable): ?>
                <div class="d-flex justify-content-between align-items-center mt-4 p-3 bg-light rounded">
                    <div>
                        <h5 class="mb-0">Ready to save changes?</h5>
                        <small class="text-muted">The budget will be recalculated by the server.</small>
                    </div>
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-save"></i> Save Menu Changes
                    </button>
                </div>
                <?php else: ?>
                <div class="alert alert-secondary text-center">
                    <i class="bi bi-lock"></i> This proposal is locked and cannot be modified.
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store selected menu items with price and quantity for easier API payload construction
        let selectedItemsModel = {}; // { menu_id: { price: price, qty: quantity } }
        let formChanged = false;

        // Initialize model based on PHP data
        document.addEventListener('DOMContentLoaded', () => {
            // PHP data for currently selected items
            const initialMap = <?php echo json_encode($proposal_items_map); ?>;
            
            document.querySelectorAll('.item-checkbox:checked').forEach(checkbox => {
                const menuId = checkbox.value;
                const qtyInput = document.getElementById('qty_' + menuId);
                
                // Initialize model with loaded values
                selectedItemsModel[menuId] = {
                    price: parseFloat(checkbox.dataset.price),
                    qty: parseInt(qtyInput.value) || 1
                };

                // Attach change listeners to quantity inputs
                qtyInput.addEventListener('change', (e) => {
                    const menuId = e.target.id.replace('qty_', '');
                    updateModel(menuId);
                    formChanged = true;
                });
            });
            updateCounter();
        });

        function updateModel(menuId) {
            const qtyInput = document.getElementById('qty_' + menuId);
            let currentQty = parseInt(qtyInput.value) || 0;

            // Ensure quantity is at least 1 if checked, unless the user manually entered 0 or less
            if (currentQty < 1 && document.querySelector(`input[type="checkbox"][value="${menuId}"]`).checked) {
                currentQty = 1;
                qtyInput.value = 1;
            }
            
            if (selectedItemsModel[menuId]) {
                selectedItemsModel[menuId].qty = currentQty;
            }
        }

        function toggleMenuItem(checkbox) {
            const menuId = checkbox.value;
            const card = document.getElementById('menu_card_' + menuId);
            const qtyInput = document.getElementById('qty_' + menuId);
            
            formChanged = true;

            if (checkbox.checked) {
                card.classList.add('selected');
                qtyInput.disabled = false;
                
                // Set quantity to its current input value (or default to 1)
                const initialQty = parseInt(qtyInput.value) > 0 ? parseInt(qtyInput.value) : 1;
                qtyInput.value = initialQty;

                selectedItemsModel[menuId] = {
                    price: parseFloat(checkbox.dataset.price),
                    qty: initialQty
                };
            } else {
                card.classList.remove('selected');
                qtyInput.disabled = true;
                qtyInput.value = 0; 
                
                delete selectedItemsModel[menuId];
            }
            updateCounter();
        }

        function updateCounter() {
            const currentCount = Object.keys(selectedItemsModel).length;
            const badge = document.getElementById('selectedCountBadge');
            if (badge) {
                badge.innerHTML = '<i class="bi bi-check-circle"></i> ' + currentCount + ' items currently selected';
            }
        }

        document.getElementById('menuEditForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const currentCount = Object.keys(selectedItemsModel).length;

            if (currentCount === 0) {
                 if (!confirm('You have deselected all menu items. Are you sure you want to save? This might indicate a major change in the proposal scope.')) {
                     return;
                 }
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            
            if (!confirm('Are you sure you want to save these menu changes? The budget will be recalculated on the server based on the selected quantities.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('client_id', <?php echo $client_id; ?>);
            
            // Build JSON array for proposal items
            for (const menuId in selectedItemsModel) {
                const item = selectedItemsModel[menuId];
                if (item.qty > 0) {
                    formData.append('proposal_items[]', JSON.stringify({
                        id: menuId,
                        qty: item.qty,
                        price: item.price
                    }));
                }
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
            
            // API Endpoint for saving menu items
            fetch('update_menu_items_api', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    formChanged = false; 
                    alert('✅ Menu items updated successfully!');
                    // Redirect to dashboard or proposal view
                    window.location.href = 'sales_dashboard_main?success=menu_updated';
                } else {
                    alert('❌ Error saving menu: ' + data.error);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-save"></i> Save Menu Changes';
                }
            })
            .catch(error => {
                alert('❌ Network error: Failed to connect to API endpoint.');
                console.error('Fetch Error:', error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-save"></i> Save Menu Changes';
            });
        });

        // Warn before leaving if there are unsaved changes
        window.addEventListener('beforeunload', function(e) {
            if (formChanged && <?php echo $is_editable ? 'true' : 'false'; ?>) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
    </script>
</body>
</html>