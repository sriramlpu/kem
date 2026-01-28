<?php
/**
 * Record Advance Payment (advance_payment.php)
 * Uses functions from kem/functions.php and Bootstrap 5 for styling.
 * Path: kem/cashier/advance_payment.php
 */
require_once("../auth.php");
requireRole(['Cashier','Admin']);

// =================================================================================
// 🔥 DEBUGGING SECTION: REMOVE AFTER FIXING
// =================================================================================
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Log errors to file for production debugging
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error_log.txt');

// =================================================================================
// 0. CONFIGURATION AND INITIALIZATION
// =================================================================================

// Critical: Require functions.php
$functions_path = __DIR__ . '/../functions.php';
if (!file_exists($functions_path)) {
    die("FATAL ERROR: functions.php not found at: " . $functions_path);
}
require_once $functions_path;

// Session management


// Helper function for current user
function current_user_id(): int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;
}

$current_user_id = current_user_id();
$error_message = '';

// Initialize form variables for sticky form
$submitted_entity_type = $_POST['entity_type'] ?? '';
$submitted_entity_id = $_POST['entity_id'] ?? '';
$submitted_amount = $_POST['amount'] ?? '';
$submitted_notes = $_POST['notes'] ?? '';
$submitted_payment_method = $_POST['payment_method'] ?? '';
$submitted_ref_number = $_POST['ref_number'] ?? '';
$submitted_vendor_ac_no = $_POST['vendor_ac_no_for_advance'] ?? ''; 
$submitted_vendor_ifsc = $_POST['vendor_ifsc_for_advance'] ?? '';
// 👇 ADDED FOR MANUAL DATE ENTRY
$submitted_advance_date = $_POST['advance_date'] ?? date('Y-m-d'); // Default to today
// 👆 END ADDITION

// =================================================================================
// 1. DATA FETCHING FUNCTIONS
// =================================================================================

function fetchVendors(): array {
    try {
        $sql = "SELECT vendor_id, vendor_name, COALESCE(account_number, '') as account_number, 
                COALESCE(ifsc, '') as ifsc 
                FROM vendors 
                WHERE status = 'Active' 
                ORDER BY vendor_name";
        $result = exeSql($sql);
        return is_array($result) ? $result : [];
    } catch (Exception $e) {
        error_log("Error fetching vendors: " . $e->getMessage());
        return [];
    }
}

function fetchEmployees(): array {
    try {
        $sql = "SELECT id, employee_name, employee_uid 
                FROM employees 
                ORDER BY employee_name";
        $result = exeSql($sql);
        return is_array($result) ? $result : [];
    } catch (Exception $e) {
        error_log("Error fetching employees: " . $e->getMessage());
        return [];
    }
}

$vendors = fetchVendors();
$employees = fetchEmployees();

// =================================================================================
// 2. AJAX ENDPOINT: HANDLE VENDOR BANK UPDATE
// =================================================================================
if (isset($_POST['action']) && $_POST['action'] === 'update_vendor_bank') {
    header('Content-Type: application/json');
    
    $vendor_id = filter_input(INPUT_POST, 'vendor_id', FILTER_VALIDATE_INT);
    $account_number = trim($_POST['account_number'] ?? '');
    $ifsc = strtoupper(trim($_POST['ifsc'] ?? ''));

    if ($vendor_id === false || $vendor_id === null || empty($account_number) || empty($ifsc)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required bank fields.']);
        exit();
    }
    
    try {
        $valAr = [
            'account_number' => $account_number,
            'ifsc' => $ifsc
        ];
        $whAr = [
            'vendor_id' => $vendor_id
        ];
        
        $result = upData('vendors', $valAr, $whAr);
        
        if ($result !== false) {
            echo json_encode(['success' => true, 'message' => 'Vendor bank details updated successfully.']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database update failed.']); 
        }
    } catch (Exception $e) {
        error_log("Bank update error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit();
}

// =================================================================================
// 3. HANDLE FORM SUBMISSION (ADVANCE PAYMENT)
// =================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['action'])) {
    $can_proceed_to_db = true;
    
    // Sanitize and validate input
    $entity_type = $_POST['entity_type'] ?? '';
    $entity_id = filter_input(INPUT_POST, 'entity_id', FILTER_VALIDATE_INT);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $payment_method = $_POST['payment_method'] ?? '';
    $ref_number = trim($_POST['ref_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    // 👇 MANUAL DATE RETRIEVAL AND VALIDATION
    $advance_date = $_POST['advance_date'] ?? null;
    
    // Basic date validation (YYYY-MM-DD format)
    if (!$advance_date || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $advance_date) || strtotime($advance_date) === false) {
        $error_message = "Error: Please select a valid advance date in YYYY-MM-DD format.";
        $can_proceed_to_db = false;
    }
    // 👆 END OF MANUAL DATE ADDITION
    
    $safe_ref_number = ($payment_method !== 'Cash' && $payment_method !== '') ? $ref_number : null;
    if ($safe_ref_number === '') $safe_ref_number = null;

    // Validation
    if (!$entity_type || $entity_id === false || $entity_id === null || 
        $amount === false || $amount === null || $amount <= 0 || !$payment_method) {
        $error_message = "Error: Please fill out all required fields correctly. Amount must be positive.";
        $can_proceed_to_db = false;
    }

    // File upload handling
    if ($can_proceed_to_db) {
        $payment_proof_path = null;
        $upload_dir_fs = __DIR__ . "/advance_proofs/"; 
        $upload_dir_db = "/cashier/advance_proofs/"; 
        $max_file_size = 5 * 1024 * 1024; // 5MB
        
        if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file_info = $_FILES['payment_proof'];
            
            if ($file_info['error'] !== UPLOAD_ERR_OK) {
                $error_message = "File upload error code: " . $file_info['error'];
                $can_proceed_to_db = false;
            } elseif ($file_info['size'] > $max_file_size) {
                $error_message = "Error: File size exceeds 5MB limit.";
                $can_proceed_to_db = false;
            } else {
                // Alternative MIME type detection (finfo_open may not be available on all servers)
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
                $file_extension = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));
                
                // Check file extension
                if (!in_array($file_extension, $allowed_extensions)) {
                    $error_message = "Error: Invalid file type. Only JPG, PNG, and PDF allowed.";
                    $can_proceed_to_db = false;
                } else {
                    // Additional MIME type check if finfo is available
                    $mime_type = '';
                    if (function_exists('finfo_open')) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime_type = finfo_file($finfo, $file_info['tmp_name']);
                        finfo_close($finfo);
                    } elseif (function_exists('mime_content_type')) {
                        $mime_type = mime_content_type($file_info['tmp_name']);
                    } else {
                        // Fallback: trust the uploaded MIME type (less secure)
                        $mime_type = $file_info['type'];
                    }
                    
                    $allowed_mimes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                    
                    if ($mime_type && !in_array(strtolower($mime_type), $allowed_mimes)) {
                        $error_message = "Error: Invalid file type detected. Only JPEG, PNG, and PDF allowed.";
                        $can_proceed_to_db = false;
                    }
                }
                
                if (!$can_proceed_to_db) {
                    // Error already set above
                }
                if (!$can_proceed_to_db) {
                    // Error already set above
                } else {
                    if (!is_dir($upload_dir_fs)) {
                        if (!mkdir($upload_dir_fs, 0755, true)) {
                            $error_message = "Error: Failed to create upload directory.";
                            $can_proceed_to_db = false;
                        }
                    }

                    if ($can_proceed_to_db) {
                        $extension = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));
                        $new_file_name = 'proof_' . date('Ymd_His') . '_' . uniqid() . '.' . $extension;
                        $target_path_fs = $upload_dir_fs . $new_file_name;
                        
                        if (move_uploaded_file($file_info['tmp_name'], $target_path_fs)) {
                            $payment_proof_path = $upload_dir_db . $new_file_name;
                        } else {
                            $error_message = "Error: Failed to save uploaded file.";
                            $can_proceed_to_db = false;
                        }
                    }
                }
            }
        }
    }

    // Database operations
    if ($can_proceed_to_db) {
        try {
            // Table names
            $advances_table = 'advances';
            $vendor_totals_table = 'vendor_totals';
            $employees_table = 'employees';
            $salary_payments_table = 'employee_salary_payments';
            
            // 1. Insert advance record (with advance_date)
            $valAr_advances = [
                'entity_type' => $entity_type,
                'entity_id' => (int)$entity_id,
                'amount' => (float)$amount,
                'notes' => $notes ? $notes : '',
                'created_by' => (int)$current_user_id,
                'payment_method' => $payment_method,
                'ref_number' => $safe_ref_number ? $safe_ref_number : '',
                'advance_date' => $advance_date, // 👈 MANUAL DATE ADDED HERE
                'payment_proof_path' => $payment_proof_path ? $payment_proof_path : ''
            ];
            
            $advance_insert_result = insData($advances_table, $valAr_advances);
            
            // Check if insert was successful
            if (!$advance_insert_result) {
                throw new Exception("Failed to insert advance record. Database returned false.");
            }
            
            // Get the inserted ID
            $advance_id = 0;
            if (is_array($advance_insert_result) && isset($advance_insert_result['lastInsertId'])) {
                $advance_id = (int)$advance_insert_result['lastInsertId'];
            } elseif (is_numeric($advance_insert_result)) {
                $advance_id = (int)$advance_insert_result;
            }
            
            if ($advance_id <= 0) {
                throw new Exception("Failed to retrieve advance ID after insertion.");
            }
            
            // 2. Update running totals based on entity type
            if ($entity_type === 'vendor') {
                // First, check if vendor exists in vendor_totals
                $check_sql = "SELECT vendor_id FROM $vendor_totals_table WHERE vendor_id = " . intval($entity_id);
                $existing = exeSql($check_sql);
                
                if ($existing && is_array($existing) && count($existing) > 0) {
                    // Vendor exists, UPDATE the advance
                    $update_sql = "UPDATE $vendor_totals_table 
                                   SET advance = advance + " . floatval($amount) . " 
                                   WHERE vendor_id = " . intval($entity_id);
                    $result = exeSql($update_sql);
                } else {
                    // Vendor doesn't exist, INSERT new record
                    $insert_sql = "INSERT INTO $vendor_totals_table (vendor_id, advance) 
                                   VALUES (" . intval($entity_id) . ", " . floatval($amount) . ")";
                    $result = exeSql($insert_sql);
                }
                
                if ($result === false) {
                    throw new Exception("Failed to update vendor_totals table for vendor ID: " . $entity_id);
                }
                
            } elseif ($entity_type === 'employee') {
                // Update employee advance
                $sql = "UPDATE $employees_table 
                         SET advance = advance + " . floatval($amount) . " 
                         WHERE id = " . intval($entity_id);
                
                $result = exeSql($sql);
                if ($result === false) {
                    throw new Exception("Failed to update employee advance total");
                }

                // Insert salary payment record
                $current_pay_period = date('Ym'); 
                $advance_note = "Advance #{$advance_id} disbursed on {$advance_date} " . // 👈 Date used here too
                                " via {$payment_method}. Ref: " . ($safe_ref_number ?? 'N/A') . 
                                ". Notes: " . ($notes ? $notes : 'N/A'); 

                $valAr_salary = [
                    'employee_id' => (int)$entity_id,
                    'pay_period' => $current_pay_period,
                    'amount' => 0.00,
                    'note' => $advance_note,
                    'advance' => (float)$amount,
                    'advance_id' => (int)$advance_id
                ];
                
                $salary_result = insData($salary_payments_table, $valAr_salary);
                if (!$salary_result) {
                    throw new Exception("Failed to insert salary payment record");
                }
            }

            // Success - redirect
            $redirect_url = 'advance_history?status=success&entity=' . 
                            urlencode($entity_type) . '&amount=' . urlencode((string)$amount);
            header("Location: $redirect_url");
            exit(); 
            
        } catch (Exception $e) {
            error_log("Database error in advance payment: " . $e->getMessage());
            $error_message = "Transaction failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Advance Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            font-family: sans-serif; 
            background-color: #f8fafc;
        }
        
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            max-width: 400px;
        }
        
        .alert {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
        }
        
        .form-container {
            max-width: 700px;
            margin: 4rem auto;
            padding: 3rem;
            background-color: #fff;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        #vendor_bank_display {
            border-left: 4px solid #ffc107;
            background-color: #fffbe5;
        }
        
        .bank-input {
            text-transform: uppercase;
        }
    </style>
</head>
<body>

    <?php if ($error_message): ?>
    <div class="alert-container">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php endif; ?>

    <div class="form-container">
        
        <a href="dashboard" class="btn btn-outline-secondary mb-4">
            ⬅️ Back to Dashboard
        </a>
        
        <header class="mb-5 text-center">
            <h1 class="h2 fw-bold text-dark border-bottom border-primary border-4 pb-3">
                Record Advance Payment
            </h1>
            <p class="text-secondary mt-2">Streamlined disbursement for Vendors and Employees</p>
        </header>

        <form action="" method="POST" class="needs-validation" enctype="multipart/form-data" novalidate> 
            
            <div class="mb-4">
                <label for="entity_type" class="form-label fw-bold text-secondary">
                    Select Advance For <span class="text-danger">*</span>
                </label>
                <select id="entity_type" name="entity_type" required class="form-select p-3">
                    <option value="">-- Select Type --</option>
                    <option value="vendor" <?php echo $submitted_entity_type == 'vendor' ? 'selected' : ''; ?>>Vendor</option>
                    <option value="employee" <?php echo $submitted_entity_type == 'employee' ? 'selected' : ''; ?>>Employee</option>
                </select>
                <div class="invalid-feedback">Please select an entity type.</div>
            </div>

            <div class="mb-4">
                <label for="entity_id" class="form-label fw-bold text-secondary">
                    Select Name <span class="text-danger">*</span>
                </label>
                <select id="entity_id" name="entity_id" required class="form-select p-3">
                    <option value="">-- Select Entity Type First --</option>
                </select>
                <div class="invalid-feedback">Please select a name.</div>
                
                <div id="vendor_options" class="d-none">
                    <?php foreach ($vendors as $vendor): ?>
                        <option class="vendor-option" value="<?php echo $vendor['vendor_id']; ?>"
                                data-selected="<?php echo $submitted_entity_type == 'vendor' && $submitted_entity_id == $vendor['vendor_id'] ? 'true' : 'false'; ?>"
                                data-bank-ac="<?php echo htmlspecialchars($vendor['account_number']); ?>"
                                data-ifsc="<?php echo htmlspecialchars($vendor['ifsc']); ?>">
                            <?php echo htmlspecialchars($vendor['vendor_name']); ?> (ID: <?php echo $vendor['vendor_id']; ?>)
                        </option>
                    <?php endforeach; ?>
                </div>

                <div id="employee_options" class="d-none">
                    <?php foreach ($employees as $employee): ?>
                        <option class="employee-option" value="<?php echo $employee['id']; ?>"
                                data-selected="<?php echo $submitted_entity_type == 'employee' && $submitted_entity_id == $employee['id'] ? 'true' : 'false'; ?>">
                            <?php echo htmlspecialchars($employee['employee_name']); ?> (UID: <?php echo $employee['employee_uid']; ?>)
                        </option>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="mb-4">
                <label for="advance_date" class="form-label fw-bold text-secondary">
                    Date Advance Was Given <span class="text-danger">*</span>
                </label>
                <input type="date" id="advance_date" name="advance_date" required 
                       max="<?php echo date('Y-m-d'); ?>" 
                       value="<?php echo htmlspecialchars($submitted_advance_date); ?>"
                       class="form-control p-3">
                <div class="invalid-feedback">Please select the date the advance was given.</div>
            </div>
            <div class="mb-4">
                <label for="payment_method" class="form-label fw-bold text-secondary">
                    Payment Method <span class="text-danger">*</span>
                </label>
                <select id="payment_method" name="payment_method" required class="form-select p-3">
                    <option value="">-- Select Method --</option>
                    <option value="Cash" <?php echo $submitted_payment_method == 'Cash' ? 'selected' : ''; ?>>Cash</option>
                    <option value="Bank Transfer" <?php echo $submitted_payment_method == 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer (NEFT/RTGS)</option>
                    <option value="Cheque" <?php echo $submitted_payment_method == 'Cheque' ? 'selected' : ''; ?>>Cheque</option>
                    <option value="UPI" <?php echo $submitted_payment_method == 'UPI' ? 'selected' : ''; ?>>UPI / Digital Wallet</option>
                </select>
                <div class="invalid-feedback">Please select a payment method.</div>
            </div>

            <div id="dynamic_payment_fields" class="mb-4">
                <div id="ref_number_field" class="mb-3 d-none">
                    <label for="ref_number" class="form-label fw-bold text-secondary">
                        Reference Number (<span id="ref_label">e.g., Check No, Txn ID</span>)
                    </label>
                    <input type="text" id="ref_number" name="ref_number" maxlength="255"
                        value="<?php echo htmlspecialchars($submitted_ref_number); ?>"
                        class="form-control p-3">
                </div>

                <div id="vendor_bank_display" class="p-4 rounded d-none mb-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <p class="fw-bold text-warning-emphasis mb-0">
                            🏦 Vendor Bank Details
                        </p>
                        <button type="button" id="edit_bank_btn" class="btn btn-sm btn-info">
                            ✏️ Edit/Update
                        </button>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small text-secondary mb-0">A/C Number:</label>
                        <span id="ac_display" class="d-block h5 text-dark fw-bold mt-1">N/A</span>
                        <input type="hidden" id="ac_hidden_for_submit" name="vendor_ac_no_for_advance" value="<?php echo htmlspecialchars($submitted_vendor_ac_no); ?>">
                        <input type="text" id="ac_input" class="bank-input form-control d-none mt-1 p-3" placeholder="Enter Account Number">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small text-secondary mb-0">IFSC Code:</label>
                        <span id="ifsc_display" class="d-block h5 text-dark fw-bold mt-1">N/A</span>
                        <input type="hidden" id="ifsc_hidden_for_submit" name="vendor_ifsc_for_advance" value="<?php echo htmlspecialchars($submitted_vendor_ifsc); ?>">
                        <input type="text" id="ifsc_input" class="bank-input form-control d-none mt-1 p-3 text-uppercase" placeholder="Enter IFSC Code">
                    </div>
                    
                    <div id="bank_save_area" class="pt-3 border-top d-none">
                        <button type="button" id="save_bank_btn" class="btn btn-success w-100 fw-bold">
                            💾 Save to Master Data
                        </button>
                        <p class="small text-warning-emphasis text-center mt-2">Updates vendor's bank details permanently.</p>
                    </div>

                    <div id="bank_update_status" class="small text-center p-2 rounded fw-bold d-none mt-3"></div>
                </div>
            </div>
            
            <div class="mb-4">
                <label for="amount" class="form-label fw-bold text-secondary">
                    Advance Amount (INR) <span class="text-danger">*</span>
                </label>
                <input type="number" id="amount" name="amount" step="0.01" min="0.01" required 
                        value="<?php echo htmlspecialchars($submitted_amount); ?>"
                        class="form-control p-3 h4 fw-bold">
                <div class="invalid-feedback">Please enter a positive amount.</div>
            </div>

            <div class="mb-4">
                <label for="payment_proof" class="form-label fw-bold text-secondary">
                    Payment Proof <span class="text-muted small">(Image/PDF, Optional, Max 5MB)</span>
                </label>
                <input type="file" id="payment_proof" name="payment_proof" 
                        accept="image/jpeg,image/png,application/pdf" class="form-control">
                <div class="form-text">Recommended for non-cash payments.</div>
            </div>
            
            <div class="mb-4">
                <label for="notes" class="form-label fw-bold text-secondary">
                    Notes/Remarks
                </label>
                <textarea id="notes" name="notes" rows="3" class="form-control p-3"><?php echo htmlspecialchars($submitted_notes); ?></textarea>
            </div>

            <div class="pt-4 border-top">
                <button type="submit" class="btn btn-primary w-100 py-3 fw-bold" style="font-size: 1.25rem;">
                    Record Advance Payment 🚀
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const entityTypeSelect = document.getElementById('entity_type');
            const entityIdSelect = document.getElementById('entity_id');
            const vendorOptions = document.getElementById('vendor_options');
            const employeeOptions = document.getElementById('employee_options');
            
            const paymentMethodSelect = document.getElementById('payment_method');
            const refNumberField = document.getElementById('ref_number_field');
            const refNumberInput = document.getElementById('ref_number');
            const refLabel = document.getElementById('ref_label');
            const vendorBankDisplay = document.getElementById('vendor_bank_display');
            
            const acDisplay = document.getElementById('ac_display');
            const ifscDisplay = document.getElementById('ifsc_display');
            const acInput = document.getElementById('ac_input');
            const ifscInput = document.getElementById('ifsc_input');
            const editBankBtn = document.getElementById('edit_bank_btn');
            const saveBankBtn = document.getElementById('save_bank_btn');
            const bankSaveArea = document.getElementById('bank_save_area');
            const bankUpdateStatus = document.getElementById('bank_update_status');
            const acHidden = document.getElementById('ac_hidden_for_submit');
            const ifscHidden = document.getElementById('ifsc_hidden_for_submit');

            let isEditingBank = false;

            function toggleVisibility(element, show) {
                element.classList.toggle('d-none', !show);
            }

            function syncBankDetailsForSubmission() {
                if (!vendorBankDisplay.classList.contains('d-none')) {
                    acHidden.value = acInput.value.trim();
                    ifscHidden.value = ifscInput.value.trim();
                } else {
                    acHidden.value = '';
                    ifscHidden.value = '';
                }
            }
            
            function displayVendorBankDetails() {
                const selectedOption = entityIdSelect.options[entityIdSelect.selectedIndex];
                let acNo = 'N/A';
                let ifsc = 'N/A';
                
                if (entityTypeSelect.value === 'vendor' && selectedOption.value) {
                    const matchingVendorOption = vendorOptions.querySelector(`option[value="${selectedOption.value}"]`);
                    if (matchingVendorOption) {
                        acNo = matchingVendorOption.getAttribute('data-bank-ac') || 'N/A';
                        ifsc = matchingVendorOption.getAttribute('data-ifsc') || 'N/A';
                    }
                }
                
                acDisplay.textContent = acNo === '' || acNo === 'N/A' ? 'N/A' : acNo;
                ifscDisplay.textContent = ifsc === '' || ifsc === 'N/A' ? 'N/A' : ifsc;
                acInput.value = acNo === 'N/A' ? '' : acNo;
                ifscInput.value = ifsc === 'N/A' ? '' : ifsc;

                isEditingBank = false;
                toggleEditMode(false);
                toggleVisibility(bankUpdateStatus, false);
                syncBankDetailsForSubmission();
            }

            function toggleEditMode(enable) {
                isEditingBank = enable;
                toggleVisibility(acDisplay, !enable);
                toggleVisibility(ifscDisplay, !enable);
                toggleVisibility(acInput, enable);
                toggleVisibility(ifscInput, enable);
                toggleVisibility(bankSaveArea, enable);
                toggleVisibility(bankUpdateStatus, false);
                
                editBankBtn.innerHTML = enable ? '❌ Cancel' : '✏️ Edit/Update';
                
                if (!enable) {
                    acDisplay.textContent = acInput.value.trim() || 'N/A';
                    ifscDisplay.textContent = ifscInput.value.trim() || 'N/A';
                } else {
                    acInput.focus();
                }
            }

            editBankBtn.addEventListener('click', () => {
                if (isEditingBank) {
                    displayVendorBankDetails(); 
                } else {
                    toggleEditMode(true);
                }
            });

            saveBankBtn.addEventListener('click', async () => {
                const vendorId = entityIdSelect.value;
                const accountNumber = acInput.value.trim();
                const ifscCode = ifscInput.value.trim().toUpperCase();
                
                if (!accountNumber || !ifscCode) {
                    bankUpdateStatus.className = 'small text-center p-2 rounded fw-bold bg-danger text-white d-block';
                    bankUpdateStatus.textContent = 'Both fields are required.';
                    toggleVisibility(bankUpdateStatus, true);
                    return;
                }

                bankUpdateStatus.className = 'small text-center p-2 rounded fw-bold bg-info text-white d-block';
                bankUpdateStatus.textContent = 'Saving...';
                toggleVisibility(bankUpdateStatus, true);
                saveBankBtn.disabled = true;

                try {
                    const formData = new FormData();
                    formData.append('action', 'update_vendor_bank');
                    formData.append('vendor_id', vendorId);
                    formData.append('account_number', accountNumber);
                    formData.append('ifsc', ifscCode);

                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        const matchingVendorOption = vendorOptions.querySelector(`option[value="${vendorId}"]`);
                        if (matchingVendorOption) {
                            matchingVendorOption.setAttribute('data-bank-ac', accountNumber);
                            matchingVendorOption.setAttribute('data-ifsc', ifscCode);
                        }

                        bankUpdateStatus.className = 'small text-center p-2 rounded fw-bold bg-success text-white d-block';
                        bankUpdateStatus.textContent = '✓ ' + result.message;
                        
                        toggleEditMode(false);
                        syncBankDetailsForSubmission();
                    } else {
                        bankUpdateStatus.className = 'small text-center p-2 rounded fw-bold bg-danger text-white d-block';
                        bankUpdateStatus.textContent = '✗ ' + result.message;
                    }

                } catch (error) {
                    console.error('AJAX Error:', error);
                    bankUpdateStatus.className = 'small text-center p-2 rounded fw-bold bg-danger text-white d-block';
                    bankUpdateStatus.textContent = '✗ Network or Server Error';
                } finally {
                    saveBankBtn.disabled = false;
                }
            });
            
            acInput.addEventListener('input', syncBankDetailsForSubmission);
            ifscInput.addEventListener('input', syncBankDetailsForSubmission);

            function handleDynamicFields() {
                const selectedMethod = paymentMethodSelect.value;
                const selectedEntity = entityTypeSelect.value;
                const selectedEntityId = entityIdSelect.value;
                
                const requiresRef = selectedMethod === 'Cheque' || selectedMethod === 'UPI' || selectedMethod === 'Bank Transfer';

                // --- Reference Number Field Logic ---
                if (requiresRef) {
                    toggleVisibility(refNumberField, true);
                    refNumberInput.setAttribute('required', 'true');
                    
                    if (selectedMethod === 'Cheque') {
                        refLabel.textContent = 'Check No.';
                    } else if (selectedMethod === 'UPI') {
                        refLabel.textContent = 'UPI/Txn ID';
                    } else if (selectedMethod === 'Bank Transfer') {
                        refLabel.textContent = 'Bank Transfer/Txn ID';
                    }
                } else {
                    toggleVisibility(refNumberField, false);
                    refNumberInput.removeAttribute('required');
                    refLabel.textContent = 'e.g., Check No, Txn ID';
                }
                
                // --- Vendor Bank Details Display Logic ---
                const isVendorBankTransfer = selectedEntity === 'vendor' && selectedMethod === 'Bank Transfer';

                if (isVendorBankTransfer && selectedEntityId !== '') {
                    toggleVisibility(vendorBankDisplay, true);
                    displayVendorBankDetails();
                } else {
                    toggleVisibility(vendorBankDisplay, false);
                    toggleEditMode(false);
                }
                
                syncBankDetailsForSubmission(); 
            }

            function updateEntityDropdown() {
                const selectedType = entityTypeSelect.value;
                
                const oldSelectedId = entityIdSelect.value;
                const submittedId = '<?php echo $submitted_entity_id; ?>';
                
                entityIdSelect.innerHTML = '<option value="">-- Select Name --</option>';

                const optionsContainer = selectedType === 'vendor' ? vendorOptions : employeeOptions;
                const optionClass = selectedType === 'vendor' ? 'vendor-option' : 'employee-option';
                
                if (selectedType === 'vendor' || selectedType === 'employee') {
                    optionsContainer.querySelectorAll(`.${optionClass}`).forEach(option => {
                        const clonedOption = option.cloneNode(true);
                        
                        if (clonedOption.value === oldSelectedId || (submittedId !== '' && clonedOption.value === submittedId)) {
                            clonedOption.selected = true;
                        }
                        
                        entityIdSelect.appendChild(clonedOption);
                    });
                    
                    if (selectedType === '<?php echo $submitted_entity_type; ?>' && submittedId !== '') {
                        entityIdSelect.value = submittedId;
                    }

                } else {
                    entityIdSelect.innerHTML = '<option value="">-- Select Entity Type First --</option>';
                }
                
                handleDynamicFields();
            }

            // Initial call
            updateEntityDropdown();
            
            // Event listeners
            entityTypeSelect.addEventListener('change', updateEntityDropdown);
            entityIdSelect.addEventListener('change', handleDynamicFields);
            paymentMethodSelect.addEventListener('change', handleDynamicFields);
        });
    </script>

</body>
</html>