<?php
/**
 * FINANCE: Create Expense
 * Path: finance/expenses_create.php
 */
require_once("../auth.php");
requireRole(['Admin', 'Finance']);
require_once("../functions.php");

include 'header.php';
include 'nav.php';
?>

<div class="container py-4" style="max-width: 800px;">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <div>
            <h2 class="fw-bold h4 mb-0 text-dark">Record New Expense</h2>
            <p class="text-muted small mb-0">Log operational disbursements and bank details.</p>
        </div>
        <a href="expenses.php" class="btn btn-outline-secondary rounded-pill px-4 fw-bold shadow-sm">Cancel</a>
    </div>

    <div id="alertBox"></div>

    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-header bg-dark text-white py-3">
            <h6 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2"></i>Expense Entry Form</h6>
        </div>
        <div class="card-body p-4 bg-light">
            <form id="expenseForm">
                <div class="row g-3 mb-4">
                    <div class="col-md-12">
                        <label class="form-label small fw-bold text-muted">PURPOSE / CATEGORY *</label>
                        <input type="text" name="purpose" class="form-control border-2" placeholder="e.g., Office Maintenance, Electricity" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">TOTAL AMOUNT (₹) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control border-2 fw-bold text-primary" placeholder="0.00" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">INITIAL PAID AMOUNT (₹)</label>
                        <input type="number" step="0.01" name="balance_paid" class="form-control" value="0.00">
                    </div>
                </div>

                <div class="row g-3 mb-4 p-3 bg-white rounded-4 border">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">BENEFICIARY A/C NUMBER</label>
                        <input type="text" name="account_no" class="form-control" placeholder="Enter bank account">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">IFSC CODE</label>
                        <input type="text" name="ifsc_code" class="form-control" placeholder="BANK0001234">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted">ADDITIONAL REMARKS</label>
                    <textarea name="remark" class="form-control" rows="3" placeholder="Reference notes for ledger..."></textarea>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg rounded-pill shadow py-3 fw-bold">
                        <i class="bi bi-save2 me-2"></i> Save Expense Record
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#expenseForm').on('submit', async function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fd.append('action', 'create');

        try {
            const res = await fetch('api/expenses_api.php', { method: 'POST', body: fd });
            const j = await res.json();
            if (j.status === 'success') {
                $('#alertBox').html('<div class="alert alert-success fw-bold rounded-4 shadow-sm mb-4"><i class="bi bi-check-circle me-2"></i>Expense created! Redirecting...</div>');
                setTimeout(() => window.location.href = 'expenses.php', 1500);
            } else {
                $('#alertBox').html('<div class="alert alert-danger fw-bold rounded-4 shadow-sm mb-4"><i class="bi bi-exclamation-circle me-2"></i>' + j.message + '</div>');
            }
        } catch (err) {
            $('#alertBox').html('<div class="alert alert-danger fw-bold rounded-4 shadow-sm mb-4">Network error occurred.</div>');
        }
    });
});
</script>

<?php include 'footer.php'; ?>