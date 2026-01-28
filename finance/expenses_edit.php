<?php
// kmk/finance/expenses_edit.php
declare(strict_types=1);
require_once dirname(__DIR__) . '/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: expenses.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Edit Expense</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style> body { background:#f6f8fb; } </style>
</head>
<body>
<?php include 'nav.php'; ?>

<div class="container py-4" style="max-width: 720px;">
  <h3 class="mb-3">Edit Expense</h3>

  <div id="errBox" class="alert alert-danger d-none"></div>
  <div id="okBox" class="alert alert-success d-none"></div>

  <form id="expForm" class="card p-3">
    <input type="hidden" id="expId" name="id" value="<?php echo (int)$id; ?>" />

    <div class="mb-3">
      <label class="form-label">Purpose *</label>
      <input type="text" name="purpose" id="purpose" class="form-control" required />
    </div>

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Amount (₹) *</label>
        <input type="number" step="1" name="amount" id="amount" class="form-control" required />
      </div>
      <div class="col-md-6">
        <label class="form-label">Total Paid (₹)</label>
        <input type="number" step="1" name="balance_paid" id="balance_paid" class="form-control" />
      </div>
    </div>

    <div class="mb-3 mt-3">
      <label class="form-label">Remark</label>
      <textarea name="remark" id="remark" rows="2" class="form-control"></textarea>
    </div>

    <div class="d-flex gap-2">
      <button class="btn btn-primary" type="submit">Update</button>
      <a class="btn btn-secondary" href="expenses.php">Cancel</a>
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const API = 'api/expenses_api.php';
  const id  = document.getElementById('expId').value;
  const errBox = document.getElementById('errBox');
  const okBox  = document.getElementById('okBox');
  const form   = document.getElementById('expForm');

  function showErr(msg){
    errBox.textContent = msg || 'Something went wrong';
    errBox.classList.remove('d-none'); okBox.classList.add('d-none');
  }
  function showOk(msg){
    okBox.textContent = msg || 'Saved';
    okBox.classList.remove('d-none'); errBox.classList.add('d-none');
  }

  async function loadExpense(){
    try {
      const r = await fetch(API + '?action=get&id=' + encodeURIComponent(id));
      const t = await r.text();
      let j;
      try { j = JSON.parse(t); } catch(e){ showErr('API did not return JSON. Response: ' + t.slice(0,300)); return; }
      if (j.status !== 'success' || !j.expense){ showErr(j.message || 'Expense not found'); return; }

      const exp = j.expense;
      document.getElementById('purpose').value      = exp.purpose || '';
      document.getElementById('amount').value       = exp.amount ?? '';
      document.getElementById('balance_paid').value = exp.balance_paid ?? '';
      document.getElementById('remark').value       = exp.remark || '';
    } catch(e){ showErr('Failed to load expense.'); }
  }
  loadExpense();

  form.addEventListener('submit', async function(e){
    e.preventDefault();

    const purpose = document.getElementById('purpose').value.trim();
    const amount  = parseInt(document.getElementById('amount').value || '0', 10);
    const paid    = parseInt(document.getElementById('balance_paid').value || '0', 10);

    if (!purpose){ showErr('Purpose is required'); return; }
    if (!(amount > 0)){ showErr('Amount must be greater than zero'); return; }
    if (paid < 0){ showErr('Total paid cannot be negative'); return; }
    if (paid > amount){ showErr('Total paid cannot exceed amount'); return; }

    const fd = new FormData(form);
    fd.append('action','update');

    try {
      const r = await fetch(API, { method:'POST', body: fd });
      const t = await r.text();
      let j;
      try { j = JSON.parse(t); } catch(_){ showErr('API did not return JSON. Response: ' + t.slice(0,300)); return; }
      if (j.status === 'success') {
        showOk('Expense updated');
        setTimeout(()=>{ window.location.href = 'expenses.php?updated=1'; }, 600);
      } else {
        showErr(j.message || 'Update failed');
      }
    } catch(err){ showErr('Request failed.'); }
  });
})();
</script>
</body>
</html>
