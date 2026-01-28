<?php
// kmk/finance/fixed_expenses_add.php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';
function h($x){ return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }

$expense_types = [
  'current_bill' => 'Current Bill',
  'rent'         => 'Rent',
  'water_bill'   => 'Water Bill',
  'pf'           => 'PF',
  'wifi_bill'    => 'Wifi Bill',
  'other'        => 'Other Fixed Expense'
];
$frequencies = ['Monthly','Quarterly','Half-Yearly','Annually'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $expense_type = trim((string)($_POST['expense_type'] ?? ''));
  $amount       = (float)($_POST['amount'] ?? 0);
  $start_in     = trim((string)($_POST['start_date'] ?? ''));
  if ($start_in && preg_match('/^\d{2}-\d{2}-\d{4}$/', $start_in)) {
    [$d,$m,$y] = explode('-', $start_in);
    $start_date = sprintf('%04d-%02d-%02d', (int)$y,(int)$m,(int)$d);
  } else { $start_date = $start_in; }
  $frequency   = trim((string)($_POST['frequency'] ?? ''));
  $due_day     = (int)($_POST['due_day'] ?? 0);
  $notes       = trim((string)($_POST['notes'] ?? ''));
  $paid        = (float)($_POST['balance_paid'] ?? 0);

  if (!isset($expense_types[$expense_type])) $errors[] = 'Invalid expense type.';
  if (!in_array($frequency, $frequencies, true)) $errors[] = 'Invalid frequency.';
  if ($amount <= 0) $errors[] = 'Amount must be greater than 0.';
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) $errors[] = 'Start date must be YYYY-MM-DD.';
  if ($due_day < 1 || $due_day > 31) $errors[] = 'Due day must be between 1 and 31.';
  if ($paid < 0) $errors[] = 'Paid cannot be negative';
  if ($paid > $amount) $errors[] = 'Paid cannot exceed amount';

  if (!$errors) {
    $t  = str_replace("'", "''", $expense_type);
    $nt = ($notes === '') ? 'NULL' : ("'".str_replace("'", "''", $notes)."'");
    // NOTE: remaining_balance is GENERATED — we do NOT insert it
    $sql = "
      INSERT INTO fixed_expenses
        (expense_type, amount, start_date, frequency, due_day, notes, balance_paid)
      VALUES
        ('{$t}', {$amount}, '{$start_date}', '{$frequency}', {$due_day}, {$nt}, {$paid})
    ";
    try {
      $res = exeSql($sql);
      if ($res === false) {
        $errors[] = 'Insert failed (check DB logs).';
      } else {
        header('Location: fixed_expenses.php?created=1');
        exit;
      }
    } catch (Throwable $e) {
      $errors[] = 'Insert exception: '.$e->getMessage();
    }
  }
}

$expenseTypeVal = $_POST['expense_type'] ?? '';
$amountVal      = $_POST['amount'] ?? '';
$startDateVal   = $_POST['start_date'] ?? date('Y-m-d');
$frequencyVal   = $_POST['frequency'] ?? 'Monthly';
$dueDayVal      = $_POST['due_day'] ?? '1';
$notesVal       = $_POST['notes'] ?? '';
$paidVal        = $_POST['balance_paid'] ?? '0';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Add Recurring Fixed Expense</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
<style>
  body{ background:#f6f8fb; }
  .card{ border-radius:14px; box-shadow:0 4px 16px rgba(0,0,0,.06); }
  .form-label{ font-weight:600; }
</style>
</head>
<body>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-1">Add Recurring Fixed Expense</h3>
    <a href="fixed_expenses.php" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-list me-1"></i> Back to Fixed Expenses
    </a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <strong>Couldn’t save:</strong>
      <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body">
      <form method="post" novalidate>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Expense Type <span class="text-danger">*</span></label>
            <select class="form-select" name="expense_type" required>
              <option value="">Select</option>
              <?php foreach ($expense_types as $k=>$v): ?>
                <option value="<?=h($k)?>" <?= $k===$expenseTypeVal?'selected':''?>><?=h($v)?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
            <input type="number" name="amount" min="0.01" step="0.01" class="form-control" value="<?=h($amountVal)?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Start Date <span class="text-danger">*</span></label>
            <input type="date" name="start_date" class="form-control" value="<?=h($startDateVal)?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Frequency <span class="text-danger">*</span></label>
            <select class="form-select" name="frequency" required>
              <option value="">Select</option>
              <?php foreach ($frequencies as $f): ?>
                <option <?= $f===$frequencyVal?'selected':''; ?>><?=h($f)?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Due Day (1–31) <span class="text-danger">*</span></label>
            <input type="number" name="due_day" min="1" max="31" step="1" class="form-control" value="<?=h($dueDayVal)?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Paid so far (₹)</label>
            <input type="number" name="balance_paid" min="0" step="0.01" class="form-control" value="<?=h($paidVal)?>">
          </div>
          <div class="col-md-12">
            <label class="form-label">Notes</label>
            <input type="text" name="notes" class="form-control" value="<?=h($notesVal)?>">
          </div>
        </div>
        <hr class="my-4">
        <div class="d-flex justify-content-between">
          <a href="fixed_expenses.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Cancel</a>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Recurring Expense</button>
        </div>
      </form>
    </div>
  </div>
</div>
</body>
</html>
