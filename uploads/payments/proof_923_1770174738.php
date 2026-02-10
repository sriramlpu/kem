<?php
/**
 * FINANCE: Event Financial Management
 * Handles multi-component billing for events with real-time balance calculation.
 * Presets: Hall Booking, Rooms, Decoration, Food / Catering, Extra Services, Tax / Other.
 */
require_once("../auth.php");
requireRole(['Admin', 'Finance']);
require_once("../functions.php");

$id = (int)($_GET['id'] ?? 0);
$event = null;
$savedItems = [];

// 1. DATA LOOKUP (Edit Mode)
if ($id > 0) {
    $eventRes = exeSql("SELECT * FROM events WHERE event_id = $id LIMIT 1");
    $event = $eventRes ? $eventRes[0] : null;
    $itemsRes = exeSql("SELECT * FROM event_items WHERE event_id = $id") ?: [];
    foreach($itemsRes as $it) {
        $savedItems[$it['item_name']] = $it;
    }
}

// 2. SAVE HANDLER
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbObj->beginTransaction();
    try {
        $eData = [
            'event_name'     => s($_POST['event_name']),
            'venue_location' => s($_POST['venue_location']),
            'mobile_number'  => s($_POST['mobile_number']),
            'email'          => s($_POST['email'])
        ];

        if ($id > 0) {
            upData('events', $eData, ["event_id=$id"]);
            // Refresh breakdown: Delete old items before re-inserting
            exeSql("DELETE FROM event_items WHERE event_id=$id");
        } else {
            $id = insData('events', $eData);
        }

        $items = $_POST['item'] ?? [];
        foreach($items as $name => $v) {
            $q = (float)$v['qty'];
            $p = (float)$v['price'];
            $r = (float)$v['recv'];
            
            // Only save components that have an assigned quantity
            if ($q > 0) {
                $tot = $q * $p;
                insData('event_items', [
                    'event_id'        => $id,
                    'item_name'       => $name,
                    'quantity'        => $q,
                    'unit_price'      => $p,
                    'total_amount'    => $tot,
                    'received_amount' => $r,
                    'balance'         => $tot - $r
                ]);
            }
        }
        $dbObj->commit();
        header("Location: events.php?msg=saved"); 
        exit;
    } catch(Exception $e) {
        $dbObj->rollBack();
        $error = $e->getMessage();
    }
}

include 'header.php';
include 'nav.php';

// Service components based on events_manage1.php
$components = ['Hall Booking', 'Rooms', 'Decoration', 'Food / Catering', 'Extra Services', 'Tax / Other'];
?>

<div class="container py-4" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <div>
            <h3 class="fw-bold m-0 text-dark"><?= $id ? 'Edit Event Financials' : 'Create New Event' ?></h3>
            <p class="text-muted small mb-0">Record venue details and component-wise billing realization.</p>
        </div>
        <a href="events.php" class="btn btn-outline-secondary rounded-pill px-4 fw-bold shadow-sm">Cancel</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger rounded-4 shadow-sm mb-4">
            <i class="bi bi-exclamation-octagon me-2"></i> <?= h($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="eventForm">
        <!-- Event Header Info -->
        <div class="card shadow-sm mb-4 border-0 rounded-4 overflow-hidden">
            <div class="card-header bg-dark text-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle me-2"></i>Primary Event Identity</h6>
            </div>
            <div class="card-body p-4 bg-light">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase">Event Name *</label>
                        <input type="text" name="event_name" class="form-control border-2" value="<?= h($event['event_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase">Venue / Location *</label>
                        <input type="text" name="venue_location" class="form-control border-2" value="<?= h($event['venue_location'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase">Contact Mobile</label>
                        <input type="text" name="mobile_number" class="form-control" value="<?= h($event['mobile_number'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= h($event['email'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Itemization -->
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
            <div class="card-header bg-primary text-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-list-check me-2"></i>Component Breakdown & Balance Tracking</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="itemsTable">
                        <thead class="table-light">
                            <tr class="small text-uppercase fw-bold text-muted">
                                <th class="ps-4">Component Name</th>
                                <th style="width: 100px;">Qty</th>
                                <th style="width: 140px;">Unit Price</th>
                                <th class="text-end" style="width: 140px;">Gross Total</th>
                                <th class="text-end" style="width: 140px;">Received</th>
                                <th class="text-end pe-4" style="width: 140px;">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($components as $name): 
                                $it = $savedItems[$name] ?? ['quantity'=>0, 'unit_price'=>0, 'received_amount'=>0];
                            ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><?= $name ?></td>
                                    <td><input type="number" step="0.01" name="item[<?= $name ?>][qty]" class="form-control form-control-sm qty" value="<?= $it['quantity'] ?>"></td>
                                    <td><input type="number" step="0.01" name="item[<?= $name ?>][price]" class="form-control form-control-sm price" value="<?= $it['unit_price'] ?>"></td>
                                    <td class="text-end fw-bold text-muted">₹<span class="total">0.00</span></td>
                                    <td><input type="number" step="0.01" name="item[<?= $name ?>][recv]" class="form-control form-control-sm text-end recv" value="<?= $it['received_amount'] ?>"></td>
                                    <td class="text-end pe-4 fw-bold text-danger">₹<span class="bal">0.00</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-dark text-white fw-bold">
                            <tr>
                                <td colspan="3" class="ps-4 text-end">Grand Consolidated Totals:</td>
                                <td class="text-end">₹<span id="ft_total">0.00</span></td>
                                <td class="text-end">₹<span id="ft_received">0.00</span></td>
                                <td class="text-end pe-4 text-warning">₹<span id="ft_balance">0.00</span></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-5 text-center">
            <button type="submit" class="btn btn-primary btn-lg rounded-pill shadow-lg px-5 py-3">
                <i class="bi bi-save2 me-2"></i> Finalize Financial Record
            </button>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
/**
 * Dynamic Recalculation: Updates line totals and footer grand totals as the user types.
 */
function recalc(){
    let gTotal = 0, gRecv = 0, gBal = 0;
    $('#itemsTable tbody tr').each(function(){
        const qty = parseFloat($(this).find('.qty').val() || 0);
        const pri = parseFloat($(this).find('.price').val() || 0);
        const rec = parseFloat($(this).find('.recv').val() || 0);
        
        const lineTotal = qty * pri;
        const lineBal = lineTotal - rec;
        
        $(this).find('.total').text(lineTotal.toFixed(2));
        $(this).find('.bal').text(lineBal.toFixed(2));
        
        gTotal += lineTotal;
        gRecv += rec;
        gBal += lineBal;
    });
    
    // Update sticky footer values
    $('#ft_total').text(gTotal.toLocaleString('en-IN', {minimumFractionDigits: 2}));
    $('#ft_received').text(gRecv.toLocaleString('en-IN', {minimumFractionDigits: 2}));
    $('#ft_balance').text(gBal.toLocaleString('en-IN', {minimumFractionDigits: 2}));
}

$(document).on('input', '.qty, .price, .recv', recalc);
$(document).ready(recalc);
</script>

<?php include 'footer.php'; ?>