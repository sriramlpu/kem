<?php
// kmk/finance/fixed_expenses_edit.php (FINAL - Fix for 1064 Syntax Error)
declare(strict_types=1);

// Assuming functions.php is accessible and defines upData and getRowValues
require_once __DIR__ . '/../functions.php'; 

/** Safe escape */
function h($x){ return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }

// =========================================================================
// Fixed Expense Types & Frequencies (omitted for brevity)
// =========================================================================

$expense_types = [
    'current_bill' => 'Current Bill',
    'rent'         => 'Rent',
    'water_bill'   => 'Water Bill',
    'pf'           => 'PF/Statutory Fees',
    'wifi_bill'    => 'Wifi/Internet Bill',
    'other'        => 'Other - Manual Entry' 
];

$frequencies = [
    'Monthly'     => 'Monthly',
    'Quarterly'   => 'Every 3 Months',
    'Half-Yearly' => 'Every 6 Months',
    'Annually'    => 'Annually (12 Months)'
];

$errors = [];
$expense_id = (int)($_GET['id'] ?? 0);
$record = null;

// =========================================================================
// Fetch Existing Record (omitted for brevity, assume original logic remains)
// =========================================================================

if ($expense_id > 0) {
    // Use global getRowValues function
    $record = getRowValues('fixed_expenses', $expense_id, 'id');

    if (!$record) {
        $errors[] = 'Failed to fetch record from database.';
    }
} else {
    $errors[] = 'Invalid or missing expense ID.';
}


// =========================================================================
// POST Submission Logic (Update)
// =========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $expense_id > 0) {
    // Collect and sanitize
    $expense_type      = trim((string)($_POST['expense_type'] ?? ''));
    $custom_desc       = trim((string)($_POST['custom_description'] ?? '')); 
    $notes             = trim((string)($_POST['notes'] ?? ''));
    $amount            = (float)($_POST['amount'] ?? 0.0);
    $start_month_year  = trim((string)($_POST['start_month_year'] ?? ''));
    $due_day           = (int)($_POST['due_day'] ?? 0);
    $frequency         = trim((string)($_POST['frequency'] ?? ''));
    $account_no        = trim((string)($_POST['account_no'] ?? ''));
    $ifsc_code         = trim((string)($_POST['ifsc_code'] ?? ''));

    // Validation (omitted for brevity, keep the original full validation logic here)
    if (!array_key_exists($expense_type, $expense_types)) { $errors[] = 'Invalid Expense Type selected.'; }
    if (!array_key_exists($frequency, $frequencies)) { $errors[] = 'Invalid Frequency selected.'; }
    if ($amount <= 0) { $errors[] = 'Amount must be greater than 0.'; }
    if (!preg_match('/^\d{4}-\d{2}$/', $start_month_year)) { $errors[] = 'Recurrence Start Month is required and must be in YYYY-MM format.'; }
    if ($due_day < 1 || $due_day > 31) { $errors[] = 'Due Day must be between 1 and 31.'; }
    if ($account_no === '') { $errors[] = 'Pay To Account Number is required for payment processing.'; }
    if ($ifsc_code === '') { $errors[] = 'Pay To IFSC Code is required for payment processing.'; }
    if ($expense_type === 'other' && $custom_desc === '') { $errors[] = 'Custom Description is required when selecting "Other - Manual Entry".'; }


    if (!$errors) {
        $final_notes = ($expense_type === 'other') ? $custom_desc : $notes;
        $final_notes = ($final_notes !== '') ? $final_notes : null;
        $start_date_db = $start_month_year . '-01';

        // Build update row
        $update_data = [
            'expense_type'  => $expense_type,
            'amount'        => $amount,
            'start_date'    => $start_date_db, 
            'frequency'     => $frequency,
            'due_day'       => $due_day,
            'notes'         => $final_notes, 
            'account_no'    => $account_no,
            'ifsc_code'     => $ifsc_code,
        ];
        
        // --- FIX: Pass raw column name for WHERE condition ---
        $where_condition = ['id' => $expense_id]; 

        // Update using global upData function
        $ok = upData('fixed_expenses', $update_data, $where_condition); // <<< FIX APPLIED HERE

        if ($ok) {
            header('Location: fixed_expenses.php?updated=1');
            exit;
        } else {
            $errors[] = 'Update failed. Please check the PHP error log for the exact query failure reason.';
        }
    }
}

// =========================================================================
// Set Form Values (Sticky/Initial) (omitted for brevity, assume original logic remains)
// =========================================================================

// Use POST data if validation failed, otherwise use fetched record data
$expenseTypeVal     = $_POST['expense_type'] ?? $record['expense_type'] ?? '';
$amountVal          = $_POST['amount'] ?? $record['amount'] ?? '';
$startMonthYearVal  = $_POST['start_month_year'] ?? substr($record['start_date'] ?? date('Y-m-d'), 0, 7) ?? date('Y-m');
$dueDayVal          = $_POST['due_day'] ?? $record['due_day'] ?? '1';
$frequencyVal       = $_POST['frequency'] ?? $record['frequency'] ?? 'Monthly';
$accountNoVal       = $_POST['account_no'] ?? $record['account_no'] ?? '';
$ifscCodeVal        = $_POST['ifsc_code'] ?? $record['ifsc_code'] ?? '';

// Determine initial values for custom_description and notes fields
$customDescVal = '';
$notesVal      = '';

if ($expenseTypeVal === 'other') {
    $customDescVal = $_POST['custom_description'] ?? $record['notes'] ?? '';
    $notesVal      = ''; 
} else {
    $notesVal      = $_POST['notes'] ?? $record['notes'] ?? '';
    $customDescVal = ''; 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Edit Recurring Fixed Expense</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
<style>
    body{ background:#f6f8fb; }
    .card{ border-radius:14px; box-shadow:0 4px 16px rgba(0,0,0,.06); }
    .form-label{ font-weight:600; }
    .hidden { display: none; }
</style>
</head>
<body>
<?php // if (file_exists(__DIR__.'/nav.php')) include 'nav.php'; // HTML continues below ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-1">Edit Recurring Fixed Expense (ID: <?= h($expense_id) ?>)</h3>
        <a href="fixed_expenses.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-list me-1"></i> Back to Fixed Expenses
        </a>
    </div>

    <?php if (!$record && $expense_id > 0): ?>
        <div class="alert alert-danger">
            <strong>Error:</strong> Record not found or failed to load.
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <strong>Couldn’t update:</strong>
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= h($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($record): ?>
    <div class="card">
        <div class="card-body">
            <form method="post" novalidate>
                <div class="row g-3">
                    
                    <div class="col-12">
                        <label for="expense_type" class="form-label">Expense Type <span class="text-danger">*</span></label>
                        <select name="expense_type" id="expense_type" class="form-select" required>
                            <option value="">Select Expense Type</option>
                            <?php foreach ($expense_types as $key => $name): ?>
                                <option value="<?= h($key) ?>" <?= $key === $expenseTypeVal ? 'selected' : '' ?>>
                                    <?= h($name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 hidden" id="custom-description-field">
                        <label for="custom_description" class="form-label">Custom Description <span class="text-danger">*</span></label>
                        <input type="text" name="custom_description" id="custom_description" class="form-control"
                            value="<?= h($customDescVal) ?>" placeholder="Enter a descriptive name for this expense">
                    </div>
                    
                    <div class="col-md-6">
                        <label for="frequency" class="form-label">Recurring Frequency <span class="text-danger">*</span></label>
                        <select name="frequency" id="frequency" class="form-select" required>
                            <option value="">Select Recurrence</option>
                            <?php foreach ($frequencies as $key => $name): ?>
                                <option value="<?= h($key) ?>" <?= $key === $frequencyVal ? 'selected' : '' ?>>
                                    <?= h($name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="amount" class="form-label">Recurring Amount (₹) <span class="text-danger">*</span></label>
                        <input type="number" min="0.01" step="0.01" name="amount" id="amount" class="form-control"
                            value="<?= h($amountVal) ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label for="start_month_year" class="form-label">Recurrence Start Month <span class="text-danger">*</span></label>
                        <input type="month" name="start_month_year" id="start_month_year" class="form-control"
                            value="<?= h($startMonthYearVal) ?>" required>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="due_day" class="form-label">Due Day (1-31) <span class="text-danger">*</span></label>
                        <input type="number" min="1" max="31" step="1" name="due_day" id="due_day" class="form-control"
                            value="<?= h($dueDayVal) ?>" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Pay To Account Number <span class="text-danger">*</span></label>
                        <input type="text" name="account_no" class="form-control"
                               inputmode="numeric" autocomplete="off"
                               value="<?= h($accountNoVal) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Pay To IFSC Code <span class="text-danger">*</span></label>
                        <input type="text" name="ifsc_code" class="form-control"
                               value="<?= h($ifscCodeVal) ?>" required>
                    </div>

                    <div class="col-12 hidden" id="notes-field">
                        <label for="notes" class="form-label">Additional Notes</label>
                        <input type="text" name="notes" id="notes" class="form-control"
                            value="<?= h($notesVal) ?>" placeholder="Optional contract or vendor details">
                    </div>
                    
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between">
                    <a href="fixed_expenses.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// JavaScript for showing/hiding custom field (omitted for brevity, assume original logic remains)
$(document).ready(function() {
    const $expenseType = $('#expense_type');
    const $customDescField = $('#custom-description-field');
    const $customDescInput = $('#custom_description');
    const $notesField = $('#notes-field');
    const $notesInput = $('#notes');

    function toggleCustomField() {
        const isOther = $expenseType.val() === 'other';

        if (isOther) {
            $customDescField.removeClass('hidden');
            $customDescInput.prop('required', true);
            $notesField.addClass('hidden');
            $notesInput.prop('required', false);
        } else {
            $customDescField.addClass('hidden');
            $customDescInput.prop('required', false);
            $notesField.removeClass('hidden');
            $notesInput.prop('required', false); 
        }
    }

    $expenseType.on('change', toggleCustomField);

    // Initial check (Important for sticky values after failed POST)
    if (document.readyState === 'complete') {
        toggleCustomField();
    } else {
        $(window).on('load', toggleCustomField);
    }
    
    // Set initial state based on current value immediately on document ready
    toggleCustomField();
});
</script>
</body>
</html>