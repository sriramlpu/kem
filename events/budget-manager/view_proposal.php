<?php
session_start();
// -------------------- DATABASE CONNECTION --------------------
$servername = "localhost";
$username   = "kmkglobal_web";
$password   = "tI]rfPhdOo9zHdKw";
$dbname     = "kmkglobal_web";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// -------------------- FETCH CLIENT DETAILS --------------------
$client_id = $_GET['client_id'] ?? 0;

if (!$client_id) {
    die("Invalid client ID");
}

$sql = "SELECT * FROM clients WHERE client_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();
$client = $result->fetch_assoc();
$stmt->close();

if (!$client) {
    die("Client not found");
}

// -------------------- FETCH PROPOSAL ITEMS --------------------
$sql_items = "SELECT proposal_item_id as item_id, item_name, 
              COALESCE(quantity, 1) as quantity,
              COALESCE(unit_price, 0) as unit_price,
              COALESCE(total_price, 0) as amount,
              item_type as item_description 
              FROM proposal_items 
              WHERE client_id = ? 
              ORDER BY proposal_item_id";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $client_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();

$items = [];
while ($row = $result_items->fetch_assoc()) {
    $items[] = $row;
}
$stmt_items->close();

$conn->close();

// Calculate totals
$subtotal = 0;
if (!empty($items)) {
    foreach ($items as $item) {
        $subtotal += isset($item['amount']) && $item['amount'] !== null ? floatval($item['amount']) : 0.00;
    }
}
$discount = isset($client['discount_amount']) && $client['discount_amount'] !== null ? floatval($client['discount_amount']) : 0.00;
$final_budget = isset($client['budget_draft_sales']) && $client['budget_draft_sales'] !== null ? floatval($client['budget_draft_sales']) : 0.00;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View & Edit Proposal - <?php echo htmlspecialchars($client['client_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px 0;
        }
        
        .container-fluid {
            max-width: 1400px;
        }
        
        .page-header {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .section-title {
            font-weight: 700;
            color: #212529;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }
        
        .detail-item label {
            font-weight: 600;
            color: #6c757d;
            font-size: 0.85rem;
            display: block;
            margin-bottom: 5px;
        }
        
        .detail-item .value {
            color: #212529;
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .status-badge {
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
        
        .notes-section {
            background: #e7f3ff;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            margin-top: 15px;
            border-radius: 5px;
        }
        
        .items-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead {
            background: var(--primary-color);
            color: white;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .add-item-btn {
            width: 100%;
            padding: 15px;
            border: 2px dashed var(--primary-color);
            background: transparent;
            color: var(--primary-color);
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .add-item-btn:hover {
            background: var(--primary-color);
            color: white;
        }

        #message-box {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            max-width: 400px;
        }

        .delete-btn {
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        tr:hover .delete-btn {
            opacity: 1;
        }

        .budget-summary {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .summary-row:last-child {
            border-bottom: none;
            border-top: 3px solid var(--primary-color);
            padding-top: 20px;
            margin-top: 10px;
        }

        .summary-label {
            font-size: 1.1rem;
            color: #495057;
            font-weight: 500;
        }

        .summary-value {
            font-size: 1.3rem;
            font-weight: 600;
            color: #212529;
        }

        .summary-row:last-child .summary-label {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .summary-row:last-child .summary-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .editable-value {
            display: inline-block;
            min-width: 150px;
            text-align: right;
        }

        .edit-input-wrapper {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .discount-row {
            color: var(--danger-color);
        }

        .discount-row .summary-label {
            color: var(--danger-color);
        }

        .discount-row .summary-value {
            color: var(--danger-color);
        }

        .edit-mode-active .summary-row {
            background: #fff9e6;
            padding: 15px;
            border-radius: 8px;
            margin: 5px 0;
        }

        .edit-icon {
            cursor: pointer;
            opacity: 0.5;
            transition: opacity 0.3s;
            margin-left: 10px;
        }

        .edit-icon:hover {
            opacity: 1;
        }
    </style>
</head>
<body>

<div id="message-box"></div>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mb-1">
                    <i class="bi bi-file-earmark-text text-primary me-2"></i>
                    Proposal Details
                </h1>
                <p class="text-muted mb-0">Client ID: <?php echo $client['client_id']; ?></p>
            </div>
            <div>
                <span class="status-badge badge-<?php echo strtolower($client['admin_status'] ?? 'pending'); ?>">
                    <?php echo strtoupper($client['admin_status'] ?? 'PENDING'); ?>
                </span>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column - Client Info -->
        <div class="col-lg-4">
            <div class="info-card">
                <h4 class="section-title">
                    <span><i class="bi bi-person-circle me-2"></i>Client Information</span>
                </h4>
                
                <div class="detail-grid">
                    <div class="detail-item">
                        <label><i class="bi bi-person me-1"></i>Client Name</label>
                        <div class="value"><?php echo htmlspecialchars($client['client_name']); ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <label><i class="bi bi-telephone me-1"></i>Contact Number</label>
                        <div class="value"><?php echo htmlspecialchars($client['contact_no']); ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <label><i class="bi bi-envelope me-1"></i>Email</label>
                        <div class="value"><?php echo htmlspecialchars($client['email'] ?? 'N/A'); ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <label><i class="bi bi-star me-1"></i>Lead Status</label>
                        <div class="value"><?php echo htmlspecialchars($client['lead_status']); ?></div>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <h4 class="section-title">
                    <span><i class="bi bi-calendar-event me-2"></i>Event Details</span>
                </h4>
                
                <div class="detail-grid">
                    <div class="detail-item">
                        <label><i class="bi bi-calendar-date me-1"></i>Event Date</label>
                        <div class="value"><?php echo date('M d, Y', strtotime($client['date_time_of_event'])); ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <label><i class="bi bi-calendar-event me-1"></i>Event Type</label>
                        <div class="value"><?php echo htmlspecialchars($client['event_type'] ?? 'N/A'); ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <label><i class="bi bi-gear me-1"></i>Services Required</label>
                        <div class="value"><?php echo htmlspecialchars($client['services_required'] ?? 'N/A'); ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <label><i class="bi bi-basket me-1"></i>Food Category</label>
                        <div class="value"><?php echo htmlspecialchars($client['food_category'] ?? 'N/A'); ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <label><i class="bi bi-palette me-1"></i>Decor Type</label>
                        <div class="value"><?php echo htmlspecialchars($client['decor_type'] ?? 'N/A'); ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <label><i class="bi bi-cash-stack me-1"></i>Expected Budget</label>
                        <div class="value"><?php echo htmlspecialchars($client['expected_budget'] ?? 'N/A'); ?></div>
                    </div>
                </div>

                <?php if (!empty($client['sales_notes'])): ?>
                    <div class="notes-section">
                        <strong><i class="bi bi-journal-text me-1"></i>Sales Notes:</strong><br>
                        <?php echo nl2br(htmlspecialchars($client['sales_notes'])); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($client['admin_notes'])): ?>
                    <div class="alert alert-secondary mt-3">
                        <strong><i class="bi bi-person-badge me-1"></i>Admin Notes:</strong><br>
                        <?php echo nl2br(htmlspecialchars($client['admin_notes'])); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="text-center mt-3">
                <a href="admin_approval" class="btn btn-outline-secondary btn-lg">
                    <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Right Column - Budget & Items -->
        <div class="col-lg-8">
            <!-- Items Table -->
            <div class="info-card">
                <h4 class="section-title">
                    <span><i class="bi bi-list-check me-2"></i>Proposal Items</span>
                    <button class="btn btn-sm btn-primary" onclick="toggleEditMode()" id="edit-mode-btn">
                        <i class="bi bi-pencil me-1"></i><span id="edit-mode-text">Edit Mode</span>
                    </button>
                </h4>

                <div class="items-table">
                    <table class="table table-hover" id="items-table">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="25%">Item Name</th>
                                <th width="30%">Description</th>
                                <th width="10%">Qty</th>
                                <th width="12%">Unit Price (₹)</th>
                                <th width="13%">Amount (₹)</th>
                                <th width="10%" class="text-center edit-mode-only" style="display: none;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="items-tbody">
                            <?php if (empty($items)): ?>
                                <tr id="no-items-row">
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        No items added yet
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $index => $item): ?>
                                    <tr data-item-id="<?php echo $item['item_id']; ?>">
                                        <td><?php echo $index + 1; ?></td>
                                        <td>
                                            <span class="view-mode"><?php echo htmlspecialchars($item['item_name']); ?></span>
                                            <input type="text" class="form-control form-control-sm edit-mode-only" 
                                                   style="display: none;" value="<?php echo htmlspecialchars($item['item_name']); ?>" 
                                                   data-field="item_name">
                                        </td>
                                        <td>
                                            <span class="view-mode"><?php echo htmlspecialchars($item['item_description'] ?? ''); ?></span>
                                            <input type="text" class="form-control form-control-sm edit-mode-only" 
                                                   style="display: none;" value="<?php echo htmlspecialchars($item['item_description'] ?? ''); ?>" 
                                                   data-field="item_description">
                                        </td>
                                        <td>
                                            <span class="view-mode"><?php echo number_format($item['quantity'], 0); ?></span>
                                            <input type="number" class="form-control form-control-sm edit-mode-only" 
                                                   style="display: none;" value="<?php echo $item['quantity']; ?>" 
                                                   step="1" data-field="quantity" onchange="updateItemAmount(this)">
                                        </td>
                                        <td>
                                            <span class="view-mode">₹<?php echo number_format($item['unit_price'], 2); ?></span>
                                            <input type="number" class="form-control form-control-sm edit-mode-only" 
                                                   style="display: none;" value="<?php echo $item['unit_price']; ?>" 
                                                   step="0.01" data-field="unit_price" onchange="updateItemAmount(this)">
                                        </td>
                                        <td>
                                            <span class="view-mode">₹<?php echo number_format(isset($item['amount']) ? floatval($item['amount']) : 0, 2); ?></span>
                                            <input type="number" class="form-control form-control-sm edit-mode-only" 
                                                   style="display: none;" value="<?php echo isset($item['amount']) ? floatval($item['amount']) : 0; ?>" 
                                                   step="0.01" data-field="amount" readonly>
                                        </td>
                                        <td class="text-center edit-mode-only" style="display: none;">
                                            <button class="btn btn-sm btn-success me-1" onclick="saveItem(<?php echo $item['item_id']; ?>)">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-btn" onclick="deleteItem(<?php echo $item['item_id']; ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot id="add-item-row" style="display: none;">
                            <tr class="table-warning">
                                <td>+</td>
                                <td><input type="text" class="form-control form-control-sm" id="new-item-name" placeholder="Item name"></td>
                                <td><input type="text" class="form-control form-control-sm" id="new-item-desc" placeholder="Description"></td>
                                <td><input type="number" class="form-control form-control-sm" id="new-item-qty" placeholder="1" step="1" value="1"></td>
                                <td><input type="number" class="form-control form-control-sm" id="new-item-price" placeholder="0.00" step="0.01"></td>
                                <td><input type="number" class="form-control form-control-sm" id="new-item-amount" placeholder="0.00" step="0.01" readonly></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-success" onclick="addNewItem()">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                    <button class="btn btn-sm btn-secondary" onclick="cancelNewItem()">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <button class="add-item-btn mt-3 edit-mode-only" id="add-item-btn" style="display: none;" onclick="showAddItemRow()">
                    <i class="bi bi-plus-circle me-2"></i>Add New Item
                </button>
            </div>

            <!-- Budget Summary -->
            <div class="budget-summary" id="budget-summary">
                <h4 class="section-title mb-4">
                    <span><i class="bi bi-calculator me-2"></i>Budget Summary</span>
                </h4>

                <div class="summary-row">
                    <div class="summary-label">
                        <i class="bi bi-receipt me-2"></i>Subtotal (Items)
                    </div>
                    <div class="summary-value editable-value" id="subtotal-display">
                        ₹<?php echo number_format($subtotal, 2); ?>
                    </div>
                </div>

                <div class="summary-row discount-row">
                    <div class="summary-label">
                        <i class="bi bi-tag me-2"></i>Discount
                        <i class="bi bi-pencil-square edit-icon edit-mode-only" style="display: none;" onclick="editDiscount()"></i>
                    </div>
                    <div class="summary-value editable-value">
                        <span id="discount-display" class="view-mode">- ₹<?php echo number_format($discount, 2); ?></span>
                        <div class="edit-input-wrapper edit-mode-only" style="display: none;">
                            <input type="number" class="form-control form-control-sm" id="discount-input" 
                                   value="<?php echo $discount; ?>" step="0.01" style="width: 150px;" onchange="calculateFinalBudget()">
                        </div>
                    </div>
                </div>

                <div class="summary-row">
                    <div class="summary-label">
                        <i class="bi bi-currency-rupee me-2"></i>Final Budget
                        <i class="bi bi-pencil-square edit-icon edit-mode-only" style="display: none;" onclick="editBudget()"></i>
                    </div>
                    <div class="summary-value editable-value">
                        <span id="budget-display" class="view-mode">₹<?php echo number_format($final_budget, 2); ?></span>
                        <div class="edit-input-wrapper edit-mode-only" style="display: none;">
                            <input type="number" class="form-control form-control-sm" id="budget-input" 
                                   value="<?php echo $final_budget; ?>" step="0.01" style="width: 150px;">
                        </div>
                    </div>
                </div>

                <div class="mt-4 edit-mode-only" style="display: none;">
                    <button class="btn btn-success btn-lg w-100" onclick="saveBudgetAndDiscount()">
                        <i class="bi bi-check-circle me-2"></i>Save Budget & Discount
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const clientId = <?php echo $client_id; ?>;
let editMode = false;

function showMessage(message, type = 'success') {
    const msgBox = document.getElementById('message-box');
    msgBox.innerHTML = `
        <div class='alert alert-${type} alert-dismissible fade show'>
            <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    setTimeout(() => {
        msgBox.innerHTML = '';
    }, 3000);
}

function toggleEditMode() {
    editMode = !editMode;
    
    const viewModeElements = document.querySelectorAll('.view-mode');
    const editModeElements = document.querySelectorAll('.edit-mode-only');
    const editModeText = document.getElementById('edit-mode-text');
    const editModeBtn = document.getElementById('edit-mode-btn');
    const budgetSummary = document.getElementById('budget-summary');
    
    if (editMode) {
        viewModeElements.forEach(el => el.style.display = 'none');
        editModeElements.forEach(el => {
            if (el.tagName === 'TH' || el.tagName === 'TD') {
                el.style.display = 'table-cell';
            } else {
                el.style.display = 'block';
            }
        });
        editModeText.textContent = 'Cancel Edit';
        editModeBtn.classList.remove('btn-primary');
        editModeBtn.classList.add('btn-warning');
        budgetSummary.classList.add('edit-mode-active');
    } else {
        viewModeElements.forEach(el => el.style.display = 'inline');
        editModeElements.forEach(el => el.style.display = 'none');
        editModeText.textContent = 'Edit Mode';
        editModeBtn.classList.remove('btn-warning');
        editModeBtn.classList.add('btn-primary');
        budgetSummary.classList.remove('edit-mode-active');
        cancelNewItem();
    }
}

function updateItemAmount(element) {
    const row = element.closest('tr');
    const qtyInput = row.querySelector('[data-field="quantity"]');
    const priceInput = row.querySelector('[data-field="unit_price"]');
    const amountInput = row.querySelector('[data-field="amount"]');
    
    const qty = parseFloat(qtyInput.value) || 0;
    const price = parseFloat(priceInput.value) || 0;
    const amount = qty * price;
    
    amountInput.value = amount.toFixed(2);
    calculateSubtotal();
}

function calculateSubtotal() {
    let subtotal = 0;
    document.querySelectorAll('#items-tbody tr[data-item-id]').forEach(row => {
        const amountInput = row.querySelector('[data-field="amount"]');
        if (amountInput) {
            subtotal += parseFloat(amountInput.value) || 0;
        }
    });
    document.getElementById('subtotal-display').textContent = '₹' + subtotal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    calculateFinalBudget();
}

function calculateFinalBudget() {
    const subtotalText = document.getElementById('subtotal-display').textContent.replace('₹', '').replace(/,/g, '');
    const subtotal = parseFloat(subtotalText) || 0;
    const discount = parseFloat(document.getElementById('discount-input').value) || 0;
    const finalBudget = subtotal - discount;
    
    document.getElementById('budget-input').value = finalBudget.toFixed(2);
}

// Update new item amount calculation
document.addEventListener('DOMContentLoaded', function() {
    const qtyInput = document.getElementById('new-item-qty');
    const priceInput = document.getElementById('new-item-price');
    const amountInput = document.getElementById('new-item-amount');
    
    function updateNewItemAmount() {
        const qty = parseFloat(qtyInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;
        amountInput.value = (qty * price).toFixed(2);
    }
    
    if (qtyInput && priceInput) {
        qtyInput.addEventListener('input', updateNewItemAmount);
        priceInput.addEventListener('input', updateNewItemAmount);
    }
});

function editDiscount() {
    // Already in edit mode via edit icon
}

function editBudget() {
    // Already in edit mode via edit icon
}

function saveBudgetAndDiscount() {
    const budget = document.getElementById('budget-input').value;
    const discount = document.getElementById('discount-input').value;
    
    if (!budget || budget < 0) {
        showMessage('Please enter a valid budget amount', 'danger');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'update_budget_discount');
    formData.append('client_id', clientId);
    formData.append('budget', budget);
    formData.append('discount', discount);

    fetch('update_proposal_api', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('budget-display').textContent = '₹' + parseFloat(budget).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('discount-display').textContent = '- ₹' + parseFloat(discount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            showMessage('Budget and discount updated successfully!');
            toggleEditMode();
        } else {
            showMessage(data.error || 'Failed to update budget and discount', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Network error occurred', 'danger');
    });
}

function saveItem(itemId) {
    const row = document.querySelector(`tr[data-item-id="${itemId}"]`);
    const itemName = row.querySelector('[data-field="item_name"]').value;
    const itemDesc = row.querySelector('[data-field="item_description"]').value;
    const quantity = row.querySelector('[data-field="quantity"]').value;
    const unitPrice = row.querySelector('[data-field="unit_price"]').value;
    const amount = row.querySelector('[data-field="amount"]').value;

    if (!itemName || !quantity || quantity < 0 || !unitPrice || unitPrice < 0) {
        showMessage('Please enter valid item details', 'danger');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'update_item');
    formData.append('item_id', itemId);
    formData.append('item_name', itemName);
    formData.append('item_description', itemDesc);
    formData.append('quantity', quantity);
    formData.append('unit_price', unitPrice);
    formData.append('amount', amount);

    fetch('update_proposal_api', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const viewModes = row.querySelectorAll('.view-mode');
            viewModes[0].textContent = itemName;
            viewModes[1].textContent = itemDesc;
            viewModes[2].textContent = parseInt(quantity);
            viewModes[3].textContent = '₹' + parseFloat(unitPrice).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            viewModes[4].textContent = '₹' + parseFloat(amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            calculateSubtotal();
            showMessage('Item updated successfully!');
        } else {
            showMessage(data.error || 'Failed to update item', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Network error occurred', 'danger');
    });
}

function deleteItem(itemId) {
    if (!confirm('Are you sure you want to delete this item?')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete_item');
    formData.append('item_id', itemId);

    fetch('update_proposal_api', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const row = document.querySelector(`tr[data-item-id="${itemId}"]`);
            row.remove();
            calculateSubtotal();
            renumberRows();
            showMessage('Item deleted successfully!');
            
            if (document.querySelectorAll('#items-tbody tr[data-item-id]').length === 0) {
                document.getElementById('items-tbody').innerHTML = `
                    <tr id="no-items-row">
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            No items added yet
                        </td>
                    </tr>
                `;
            }
        } else {
            showMessage(data.error || 'Failed to delete item', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Network error occurred', 'danger');
    });
}

function showAddItemRow() {
    document.getElementById('add-item-row').style.display = 'table-footer-group';
    document.getElementById('add-item-btn').style.display = 'none';
}

function cancelNewItem() {
    document.getElementById('add-item-row').style.display = 'none';
    if (document.getElementById('add-item-btn')) {
        document.getElementById('add-item-btn').style.display = 'block';
    }
    document.getElementById('new-item-name').value = '';
    document.getElementById('new-item-desc').value = '';
    document.getElementById('new-item-qty').value = '1';
    document.getElementById('new-item-price').value = '';
    document.getElementById('new-item-amount').value = '';
}

function addNewItem() {
    const itemName = document.getElementById('new-item-name').value;
    const itemDesc = document.getElementById('new-item-desc').value;
    const quantity = document.getElementById('new-item-qty').value;
    const unitPrice = document.getElementById('new-item-price').value;
    const amount = document.getElementById('new-item-amount').value;

    if (!itemName || !quantity || quantity < 0 || !unitPrice || unitPrice < 0) {
        showMessage('Please enter valid item details', 'danger');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add_item');
    formData.append('client_id', clientId);
    formData.append('item_name', itemName);
    formData.append('item_description', itemDesc);
    formData.append('quantity', quantity);
    formData.append('unit_price', unitPrice);
    formData.append('amount', amount);

    fetch('update_proposal_api', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const noItemsRow = document.getElementById('no-items-row');
            if (noItemsRow) {
                noItemsRow.remove();
            }

            const tbody = document.getElementById('items-tbody');
            const rowCount = tbody.querySelectorAll('tr[data-item-id]').length;
            const newRow = document.createElement('tr');
            newRow.setAttribute('data-item-id', data.item_id);
            newRow.innerHTML = `
                <td>${rowCount + 1}</td>
                <td>
                    <span class="view-mode" style="display: none;">${itemName}</span>
                    <input type="text" class="form-control form-control-sm edit-mode-only" 
                           value="${itemName}" data-field="item_name">
                </td>
                <td>
                    <span class="view-mode" style="display: none;">${itemDesc}</span>
                    <input type="text" class="form-control form-control-sm edit-mode-only" 
                           value="${itemDesc}" data-field="item_description">
                </td>
                <td>
                    <span class="view-mode" style="display: none;">${parseInt(quantity)}</span>
                    <input type="number" class="form-control form-control-sm edit-mode-only" 
                           value="${quantity}" step="1" data-field="quantity" onchange="updateItemAmount(this)">
                </td>
                <td>
                    <span class="view-mode" style="display: none;">₹${parseFloat(unitPrice).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                    <input type="number" class="form-control form-control-sm edit-mode-only" 
                           value="${unitPrice}" step="0.01" data-field="unit_price" onchange="updateItemAmount(this)">
                </td>
                <td>
                    <span class="view-mode" style="display: none;">₹${parseFloat(amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                    <input type="number" class="form-control form-control-sm edit-mode-only" 
                           value="${amount}" step="0.01" data-field="amount" readonly>
                </td>
                <td class="text-center edit-mode-only">
                    <button class="btn btn-sm btn-success me-1" onclick="saveItem(${data.item_id})">
                        <i class="bi bi-check-lg"></i>
                    </button>
                    <button class="btn btn-sm btn-danger delete-btn" onclick="deleteItem(${data.item_id})">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(newRow);
            
            cancelNewItem();
            calculateSubtotal();
            showMessage('Item added successfully!');
        } else {
            showMessage(data.error || 'Failed to add item', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Network error occurred', 'danger');
    });
}

function renumberRows() {
    document.querySelectorAll('#items-tbody tr[data-item-id]').forEach((row, index) => {
        row.querySelector('td:first-child').textContent = index + 1;
    });
}
</script>

</body>
</html>