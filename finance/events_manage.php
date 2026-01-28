<?php
// finance/events_manage.php
session_start();
require_once __DIR__ . '/../functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$event = null;
$items = [];

if ($id > 0) {
  $row = exeSql("SELECT * FROM events WHERE event_id = {$id} LIMIT 1");
  $event = $row ? $row[0] : null;
  $items = exeSql("SELECT * FROM event_items WHERE event_id = {$id}") ?: [];
}

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

// helper to get saved value for preset item names
function saved_item($items, $name) {
  foreach ($items as $it) {
    if (($it['item_name'] ?? '') === $name) return $it;
  }
  return null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= $id ? 'Edit Event' : 'Add Event' ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f6f8fb; }
    .card { max-width: 1000px; margin: 24px auto; }
    .table thead th { white-space: nowrap; }
    .readonly { background: #f8f9fa; }
  </style>
</head>
<body>
<?php if (file_exists(__DIR__.'/nav.php')) include __DIR__.'/nav.php'; ?>

<div class="container py-4">
  <div class="card">
    <div class="card-body">
      <h4 class="mb-3"><?= $id ? 'Edit Event' : 'Add Event' ?></h4>

      <form id="eventForm">
        <input type="hidden" name="action" value="<?= $id ? 'edit_with_items' : 'create_with_items' ?>">
        <?php if ($id): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

        <!-- Event fields -->
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Event Name *</label>
            <input type="text" name="event_name" class="form-control" required value="<?= h($event['event_name'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Venue Location *</label>
            <input type="text" name="venue_location" class="form-control" required value="<?= h($event['venue_location'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Mobile Number *</label>
            <input type="text" name="mobile_number" class="form-control" required value="<?= h($event['mobile_number'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email *</label>
            <input type="email" name="email" class="form-control" required value="<?= h($event['email'] ?? '') ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label">Address</label>
            <textarea name="address" class="form-control" rows="3"><?= h($event['address'] ?? '') ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Billing Address</label>
            <textarea name="billing_address" class="form-control" rows="3"><?= h($event['billing_address'] ?? '') ?></textarea>
          </div>
        </div>

        <hr class="my-4">

        <!-- Items table (always visible) -->
        <h5 class="mb-2">Event Items</h5>
        <p class="text-muted">Enter quantities and price; totals and balance calculate automatically. Leave quantity 0 to skip.</p>

        <div class="table-responsive">
          <table class="table table-bordered align-middle" id="itemsTable">
            <thead class="table-light">
              <tr>
                <th style="width:40px">#</th>
                <th>Item</th>
                <th style="width:140px">Qty</th>
                <th style="width:140px">Price</th>
                <th style="width:140px">Total</th>
                <th style="width:160px">Amount Received</th>
                <th style="width:140px">Balance</th>
              </tr>
            </thead>
            <tbody>
              <?php
                $preset = ['Breakfast','Lunch','Dinner'];
                $i=1;
                foreach ($preset as $name):
                  $sv = saved_item($items, $name) ?? [];
              ?>
              <tr data-item="<?= $name ?>">
                <td><?= $i++ ?></td>
                <td class="fw-medium">
                  <?= $name ?>
                  <input type="hidden" name="items[<?= $name ?>][item_name]" value="<?= $name ?>">
                </td>
                <td><input type="number" name="items[<?= $name ?>][quantity]" class="form-control form-control-sm qty" min="0" step="1" value="<?= h($sv['quantity'] ?? 0) ?>"></td>
                <td><input type="number" name="items[<?= $name ?>][price]" class="form-control form-control-sm price" min="0" step="0.01" value="<?= h($sv['price'] ?? 0) ?>"></td>
                <td class="total">0.00</td>
                <td><input type="number" name="items[<?= $name ?>][amount_received]" class="form-control form-control-sm recv" min="0" step="0.01" value="<?= h($sv['amount_received'] ?? 0) ?>"></td>
                <td class="bal">0.00</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr class="fw-bold">
                <td colspan="4" class="text-end">Totals:</td>
                <td id="ft_total">0.00</td>
                <td id="ft_received">0.00</td>
                <td id="ft_balance">0.00</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Buttons at the bottom -->
        <div class="mt-3 d-flex gap-2">
          <button type="submit" class="btn btn-primary"><?= $id ? 'Update' : 'Save' ?></button>
          <a href="events.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script>
function money(n){ return (parseFloat(n||0)).toFixed(2); }

function recalc(){
  let t=0, r=0, b=0;
  $('#itemsTable tbody tr').each(function(){
    const q = parseFloat($(this).find('.qty').val()||0);
    const p = parseFloat($(this).find('.price').val()||0);
    const total = q*p;
    $(this).find('.total').text(money(total));
    const recv = parseFloat($(this).find('.recv').val()||0);
    const bal = total - recv;
    $(this).find('.bal').text(money(bal));
    t += total; r += recv; b += bal;
  });
  $('#ft_total').text(money(t));
  $('#ft_received').text(money(r));
  $('#ft_balance').text(money(b));
}
$(document).on('input', '.qty,.price,.recv', recalc);
recalc();

$('#eventForm').on('submit', function(e){
  e.preventDefault();
  const payload = $(this).serialize();
  $.post('api/events_api.php', payload, function(res){
    if(res && res.status==='success'){
      window.location.href = 'events.php';
    } else {
      alert(res.message || 'Save failed');
    }
  }, 'json').fail(function(){
    alert('Network error');
  });
});
</script>
</body>
</html>
