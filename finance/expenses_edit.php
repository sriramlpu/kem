<?php
/**
 * FINANCE: Edit Expense
 * Path: finance/expenses_edit.php
 */
require_once("../auth.php");
requireRole(['Admin', 'Finance']);
require_once("../functions.php");

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: expenses.php"); exit; }

include 'header.php';
include 'nav.php';
?>

<div class="container py-4" style="max-width: 800px;">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <div>
            <h2 class="fw-bold h4 mb-0 text-dark">Modify Expense Record</h2>
            <p class="text-muted small mb-0">Update ledger entries and payment realization.</p>
        </div>
        <a href="expenses.php" class="btn btn-outline-secondary rounded-pill px-4 fw-bold shadow-sm">Cancel</a>
    </div>

    <div id="alertBox"></div>

    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-header bg-primary text-white py-3">
            <h6 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Transaction Details</h6>
        </div>
        <div class="card-body p-4 bg-light">
            <form id="editForm">
                <input type="hidden" name="id" value="<?= $id ?>">
                
                <div class="row g-3 mb-4">
                    <div class="col-md-12">
                        <label class="form-label small fw-bold text-muted text-uppercase">Purpose / Category</label>
                        <input type="text" name="purpose" id="e_purpose" class="form-control border-2" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase">Gross Amount (₹)</label>
                        <input type="number" step="0.01" name="amount" id="e_amount" class="form-control border-2 fw-bold text-dark" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase">Total Realized / Paid (₹)</label>
                        <input type="number" step="0.01" name="balance_paid" id="e_paid" class="form-control border-2 text-success fw-bold">
                    </div>
                </div>

                <div class="row g-3 mb-4 p-3 bg-white rounded-4 border">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">A/C NUMBER</label>
                        <input type="text" name="account_no" id="e_account" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">IFSC CODE</label>
                        <input type="text" name="ifsc_code" id="e_ifsc" class="form-control">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted">INTERNAL REMARK</label>
                    <textarea name="remark" id="e_remark" class="form-control" rows="3"></textarea>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg rounded-pill shadow py-3 fw-bold">
                        <i class="bi bi-check-lg me-2"></i> Update Expense Entry
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(async function() {
    // Load existing data
    try {
        const res = await fetch('api/expenses_api.php?action=getExpense&id=<?= $id ?>');
        const j = await res.json();
        if (j.status === 'success') {
            const exp = j.expense;
            $('#e_purpose').val(exp.purpose);
            $('#e_amount').val(exp.amount);
            $('#e_paid').val(exp.balance_paid);
            $('#e_account').val(exp.account_no);
            $('#e_ifsc').val(exp.ifsc_code);
            $('#e_remark').val(exp.remark);
        } else {
            alert('Expense not found');
            window.location.href = 'expenses.php';
        }
    } catch (e) {
        alert('Failed to load record.');
    }

    // Submit update
    $('#editForm').on('submit', async function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fd.append('action', 'update');

        const res = await fetch('api/expenses_api.php', { method: 'POST', body: fd });
        const j = await res.json();
        if (j.status === 'success') {
            $('#alertBox').html('<div class="alert alert-success fw-bold rounded-4 shadow-sm mb-4">Record updated. Redirecting...</div>');
            setTimeout(() => window.location.href = 'expenses.php', 1200);
        } else {
            $('#alertBox').html('<div class="alert alert-danger fw-bold rounded-4 shadow-sm mb-4">' + j.message + '</div>');
        }
    });
});
</script>

<?php include 'footer.php'; ?>