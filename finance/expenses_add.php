<?php
// kmk/finance/expenses_create.php
declare(strict_types=1);
require_once __DIR__ . '/../functions.php';

/** Safe escape */
function h($x) { return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }

/**
 * Normalize a scalar coming from POST:
 * - trim strings
 * - convert empty strings to null (so we never send '' into a UNIQUE column)
 */
function norm(?string $v): ?string {
  if ($v === null) return null;
  $v = trim($v);
  return ($v === '') ? null : $v;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Collect & normalize
  $purpose = norm($_POST['purpose'] ?? null);
  $amount  = (int)($_POST['amount'] ?? 0); // INTEGER ONLY
  $account = norm($_POST['account_no'] ?? null);
  $ifsc    = norm($_POST['ifsc_code'] ?? null);
  $remark  = norm($_POST['remark'] ?? null);

  // Validate (keep it simple)
  if ($purpose === null) $errors[] = 'Purpose is required.';
  if ($amount <= 0)      $errors[] = 'Amount must be a whole number greater than 0.';
  if ($account === null) $errors[] = 'Account Number is required.';
  if ($ifsc === null)    $errors[] = 'IFSC Code is required.';

  if (!$errors) {
    /**
     * Build insert row:
     * IMPORTANT:
     * - Do NOT include voucher_no or invoice_no at all. Let them default to NULL.
     * - Never pass empty strings for unique/nullable fields.
     */
    $row = [
      'purpose'    => $purpose,
      'amount'     => $amount, // INT
      'account_no' => $account,
      'ifsc_code'  => $ifsc,
      'remark'     => $remark,         // may be NULL
      'paid_at'    => date('Y-m-d H:i:s'),
    ];

    $ok = false;

    if (function_exists('insData')) {
      // Use project helper – it should only insert provided columns.
      // Since we didn't set voucher_no/invoice_no, they won't be sent as ''.
      $ok = @insData('expenses', $row);
    } elseif (function_exists('exeSql')) {
      // Fallback: raw insert that emits proper NULL for nulls
      $cols = array_keys($row);
      $vals = array_map(function($v){
        if ($v === null) return 'NULL';
        if (is_int($v))  return (string)$v;
        return "'".addslashes((string)$v)."'";
      }, array_values($row));

      $sql = "INSERT INTO `expenses` (`".implode("`,`", $cols)."`) VALUES (".implode(",", $vals).")";
      $ok = @exeSql($sql) !== false;
    } else {
      $errors[] = 'DB helpers (insData/exeSql) not found in functions.php.';
    }

    if ($ok) {
      header('Location: expenses.php?created=1');
      exit;
    } else {
      $errors[] = 'Insert failed. Ensure the `expenses` table has: purpose, amount(INT), account_no, ifsc_code, remark(NULL), paid_at (NOT NULL), and voucher_no/invoice_no are NULLable with default NULL.';
    }
  }
}

// Sticky values on validation error (use raw POST to preserve user typing)
$purposeVal = $_POST['purpose']    ?? '';
$amountVal  = $_POST['amount']     ?? '';
$accVal     = $_POST['account_no'] ?? '';
$ifscVal    = $_POST['ifsc_code']  ?? '';
$remarkVal  = $_POST['remark']     ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Create Expense</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link href="https://kit.fontawesome.com/a076d05399.css" rel="stylesheet">
<style>
  body{ background:#f6f8fb; }
  .card{ border-radius:14px; box-shadow:0 4px 16px rgba(0,0,0,.06); }
  .form-label{ font-weight:600; }
</style>
</head>
<body>
<?php if (file_exists(__DIR__.'/nav.php')) include 'nav.php'; ?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-1">Create Expense</h3>
    <a href="expenses.php" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-list me-1"></i> Back to Expenses
    </a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <strong>Couldn’t save:</strong>
      <ul class="mb-0">
        <?php foreach ($errors as $e): ?>
          <li><?= h($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body">
      <form method="post" novalidate>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Purpose For <span class="text-danger">*</span></label>
            <input type="text" name="purpose" class="form-control"
                   value="<?= h($purposeVal) ?>" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
            <input type="number" min="1" step="1" name="amount" class="form-control"
                   value="<?= h($amountVal) ?>" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Account Number <span class="text-danger">*</span></label>
            <input type="text" name="account_no" class="form-control"
                   inputmode="numeric" autocomplete="off"
                   value="<?= h($accVal) ?>" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">IFSC Code <span class="text-danger">*</span></label>
            <input type="text" name="ifsc_code" class="form-control"
                   value="<?= h($ifscVal) ?>" required>
          </div>

          <div class="col-12">
            <label class="form-label">Remark</label>
            <input type="text" name="remark" class="form-control"
                   value="<?= h($remarkVal) ?>" placeholder="Optional note">
          </div>
        </div>

        <hr class="my-4">

        <div class="d-flex justify-content-between">
          <a href="expenses.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Cancel
          </a>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Save Expense
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
