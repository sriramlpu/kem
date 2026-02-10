<?php
/**
 * FINANCE: Create Fixed Obligation
 * Path: finance/fixed_expenses_create.php
 */
require_once("../auth.php");
requireRole(['Admin', 'Finance']);
require_once("../functions.php");

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
            <h2 class="fw-bold h4 mb-0 text-dark">Register Obligation</h2>
            <p class="text-muted small mb-0">Define recurring liability parameters.</p>
        </div>
        <a href="fixed_expenses.php" class="btn btn-outline-secondary rounded-pill px-4 fw-bold">Cancel</a>
    </div>

    <div id="alertBox"></div>

    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-header bg-dark text-white py-3">
            <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i>New Recurring Item</h6>
        </div>
        <div class="card-body p-4 bg-light">
            <form id="fixedForm">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">EXPENSE CATEGORY *</label>
                        <select name="expense_type" class="form-select border-2" required>
                            <option value="">-- Choose --</option>
                            <?php foreach($expense_types as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">START DATE *</label>
                        <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">BASE AMOUNT (₹) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control border-2 fw-bold text-primary" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">FREQUENCY</label>
                        <select name="frequency" class="form-select">
                            <?php foreach($frequencies as $f): ?><option value="<?= $f ?>"><?= $f ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">DUE DAY (1-31)</label>
                        <input type="number" name="due_day" min="1" max="31" class="form-control" value="1">
                    </div>
                </div>

                <div class="row g-3 mb-4 p-3 bg-white rounded-4 border">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">VENDORS A/C (OPTIONAL)</label>
                        <input type="text" name="account_no" class="form-control" placeholder="Beneficiary account">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">IFSC CODE</label>
                        <input type="text" name="ifsc_code" class="form-control" placeholder="BANK0001234">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted">INTERNAL DESCRIPTION / NOTES</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Contract details, premises info, etc..."></textarea>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg rounded-pill shadow py-3 fw-bold">
                        <i class="bi bi-save2 me-2"></i> Register Obligation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#fixedForm').on('submit', async function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fd.append('action', 'create');

        try {
            const res = await fetch('api/fixed_expenses_api.php', { method: 'POST', body: fd });
            const j = await res.json();
            if (j.status === 'success') {
                $('#alertBox').html('<div class="alert alert-success fw-bold rounded-4 shadow-sm mb-4">Obligation registered! Redirecting...</div>');
                setTimeout(() => window.location.href = 'fixed_expenses.php', 1200);
            } else {
                $('#alertBox').html('<div class="alert alert-danger fw-bold rounded-4 shadow-sm mb-4">' + j.message + '</div>');
            }
        } catch (err) { alert('Network error'); }
    });
});
</script>

<?php include 'footer.php'; ?>