<?php
/**
 * FINANCE: Edit Fixed Obligation
 * Path: finance/fixed_expenses_edit.php
 */
require_once("../auth.php");
requireRole(['Admin', 'Finance']);
require_once("../functions.php");

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: fixed_expenses.php"); exit; }

include 'header.php';
include 'nav.php';

$expense_types = [
    'current_bill' => 'Current Bill',
    'rent'         => 'Rent',
    'water_bill'   => 'Water Bill',
    'pf'           => 'PF/Statutory Fees',
    'wifi_bill'    => 'Wifi/Internet Bill',
    'other'        => 'Other - Manual Entry'
];
$frequencies = ['Monthly', 'Quarterly', 'Half-Yearly', 'Annually'];
?>

<div class="container py-4" style="max-width: 850px;">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <div>
            <h2 class="fw-bold h4 mb-0 text-dark">Modify Obligation</h2>
            <p class="text-muted small mb-0">Update recurrence details and tracked balance.</p>
        </div>
        <a href="fixed_expenses.php" class="btn btn-outline-secondary rounded-pill px-4 fw-bold">Cancel</a>
    </div>

    <div id="alertBox"></div>

    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-header bg-primary text-white py-3">
            <h6 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Obligation Parameters</h6>
        </div>
        <div class="card-body p-4 bg-light">
            <form id="editForm">
                <input type="hidden" name="id" value="<?= $id ?>">
                
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">CATEGORY</label>
                        <select name="expense_type" id="e_type" class="form-select border-2" required>
                            <?php foreach($expense_types as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">START DATE</label>
                        <input type="date" name="start_date" id="e_start" class="form-control" required>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">BASE AMOUNT (₹)</label>
                        <input type="number" step="0.01" name="amount" id="e_amount" class="form-control border-2 fw-bold text-dark" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">FREQUENCY</label>
                        <select name="frequency" id="e_freq" class="form-select">
                            <?php foreach($frequencies as $f): ?><option value="<?= $f ?>"><?= $f ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">DUE DAY</label>
                        <input type="number" name="due_day" id="e_due" min="1" max="31" class="form-control">
                    </div>
                </div>

                <div class="row g-3 mb-4 p-3 bg-white rounded-4 border">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">TOTAL PAID SO FAR (₹)</label>
                        <input type="number" step="0.01" name="balance_paid" id="e_paid" class="form-control border-2 text-success fw-bold">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">A/C NUMBER</label>
                        <input type="text" name="account_no" id="e_account" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">IFSC CODE</label>
                        <input type="text" name="ifsc_code" id="e_ifsc" class="form-control">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted">NOTES</label>
                    <textarea name="notes" id="e_notes" class="form-control" rows="3"></textarea>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg rounded-pill shadow py-3 fw-bold">
                        <i class="bi bi-check-lg me-2"></i> Update Obligation Record
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(async function() {
    try {
        const res = await fetch('api/fixed_expenses_api.php?action=get&id=<?= $id ?>');
        const j = await res.json();
        if (j.status === 'success') {
            const r = j.record;
            $('#e_type').val(r.expense_type);
            $('#e_amount').val(r.amount);
            $('#e_start').val(r.start_date);
            $('#e_freq').val(r.frequency);
            $('#e_due').val(r.due_day);
            $('#e_paid').val(r.balance_paid);
            $('#e_account').val(r.account_no);
            $('#e_ifsc').val(r.ifsc_code);
            $('#e_notes').val(r.notes);
        }
    } catch (e) { alert('Load failed'); }

    $('#editForm').on('submit', async function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fd.append('action', 'update');

        const res = await fetch('api/fixed_expenses_api.php', { method: 'POST', body: fd });
        const j = await res.json();
        if (j.status === 'success') {
            $('#alertBox').html('<div class="alert alert-success fw-bold rounded-4 shadow-sm mb-4">Record updated! Redirecting...</div>');
            setTimeout(() => window.location.href = 'fixed_expenses.php', 1200);
        } else {
            $('#alertBox').html('<div class="alert alert-danger fw-bold rounded-4 shadow-sm mb-4">' + j.message + '</div>');
        }
    });
});
</script>

<?php include 'footer.php'; ?>