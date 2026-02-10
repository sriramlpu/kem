<?php

declare(strict_types=1);

session_start();
require_once("../functions.php");

/** Utility Helpers **/
if (!function_exists('v')) {
    function v($k, $d = null)
    {
        return $_POST[$k] ?? $_GET[$k] ?? $d;
    }
}
if (!function_exists('i')) {
    function i($x)
    {
        return is_numeric($x) ? (int)$x : 0;
    }
}
if (!function_exists('s')) {
    function s($x)
    {
        return trim((string)$x);
    }
}
if (!function_exists('h')) {
    function h($x)
    {
        return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('json_out')) {
    function json_out($data, $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data);
        exit;
    }
}

/* ---------- GRN & RETURNS LOGIC (From payment1.php) ---------- */
function fetch_pending_grns($vendor_id, $branch_id)
{
    $rows = exeSql("SELECT gr.grn_id, gr.grn_number, gr.total_amount, gr.transportation 
                    FROM goods_receipts gr JOIN purchase_orders po ON po.po_id = gr.po_id
                    WHERE gr.vendor_id = $vendor_id AND po.branch_id = $branch_id ORDER BY gr.grn_id DESC") ?: [];

    $out = [];
    foreach ($rows as $r) {
        $gid = (int)$r['grn_id'];
        $rtRow = exeSql("SELECT SUM(total_amount) as total FROM goods_return_notes WHERE grn_id=$gid");
        $rt = (float)($rtRow[0]['total'] ?? 0);

        $paidRow = exeSql("SELECT (IFNULL(SUM(amount),0) + IFNULL(SUM(advance_used),0) + IFNULL(SUM(redemption_used),0)) AS total_credit FROM vendor_grn_payments WHERE grn_id=$gid");
        $paid = (float)($paidRow[0]['total_credit'] ?? 0);

        $total = max(0.0, (float)$r['total_amount'] + (float)$r['transportation'] - $rt);
        $balance = $total - $paid;

        if ($balance > 0.01) {
            $out[] = [
                'grn_id' => $gid,
                'grn_number' => $r['grn_number'],
                'gross_amount' => $r['total_amount'],
                'transport' => $r['transportation'],
                'returns' => $rt,
                'total_amount' => $total,
                'paid' => $paid,
                'balance' => $balance
            ];
        }
    }
    return $out;
}

/* ---------- AJAX ROUTER ---------- */
if (isset($_GET['ajax'])) {
    $act = $_GET['ajax'];

    if ($act === 'grns') {
        json_out(fetch_pending_grns(i(v('vendor_id')), i(v('branch_id'))));
    }

    if ($act === 'vendor_bank') {
        $vid = i(v('vendor_id'));
        $v = exeSql("SELECT account_number, ifsc FROM vendors WHERE vendor_id=$vid LIMIT 1");
        json_out($v ? $v[0] : ['account_number' => '', 'ifsc' => '']);
    }

    if ($act === 'expense_summary') {
        $purpose = s(v('purpose'));
        $row = exeSql("SELECT amount, balance_paid, (amount - balance_paid) as remaining_balance FROM expenses WHERE purpose='" . addslashes($purpose) . "' ORDER BY id DESC LIMIT 1");
        if ($row) {
            json_out(['total' => (float)$row[0]['amount'], 'paid' => (float)$row[0]['balance_paid'], 'balance' => (float)$row[0]['remaining_balance']]);
        } else {
            json_out(['total' => 0, 'paid' => 0, 'balance' => 0]);
        }
    }

    if ($act === 'fixed_list') {
        json_out(exeSql("SELECT id, expense_type, (amount-IFNULL(balance_paid,0)) as remaining_balance FROM fixed_expenses HAVING remaining_balance > 0 ORDER BY id DESC") ?: []);
    }

    if ($act === 'fixed_one') {
        $id = i(v('id'));
        $row = exeSql("SELECT *, amount as total, balance_paid as paid, (amount-IFNULL(balance_paid,0)) as balance FROM fixed_expenses WHERE id=$id LIMIT 1");
        json_out($row ? $row[0] : []);
    }
    exit;
}

/* ---------- SUBMIT LOGIC ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pay_for = s(v('pay_for'));
    $rid = i(v('rid', 0));
    $payload = $_POST;
    unset($payload['rid']);

    $data = [
        'request_type' => $pay_for,
        'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        'requested_by' => (int)($_SESSION['userId'] ?? 1),
        'vendor_id'    => i(v('vendor_id')) ?: null,
        'branch_id'    => i(v('branch_id')) ?: null,
        'total_amount' => (float)s(v('pay_now', '0')),
        'status'       => 'SUBMITTED',
        'updated_at'   => date('Y-m-d H:i:s')
    ];

    if ($rid > 0) {
        upData('payment_requests', $data, ["request_id=$rid"]);
        $newId = $rid;
    } else {
        $newId = insData('payment_requests', $data);
    }
    header("Location: dashboard.php?msg=" . ($rid ? "updated" : "submitted") . "&rid=$newId");
    exit;
}

require_once("./header.php");
require_once("./nav.php");

$branches = exeSql("SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
$vendors = exeSql("SELECT vendor_id, vendor_name FROM vendors ORDER BY vendor_name");
$purposes = exeSql("SELECT DISTINCT purpose FROM expenses WHERE purpose IS NOT NULL AND TRIM(purpose)<>'' ORDER BY purpose");
?>
<style>
    body {
        background-color: #f8fafb;
        font-family: 'Inter', system-ui, sans-serif;
    }

    .card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .form-label {
        font-weight: 600;
        color: #4b5563;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-select,
    .form-control {
        border-radius: 8px;
        padding: 0.6rem 1rem;
        border: 1px solid #d1d5db;
    }

    .insight-card {
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 15px;
    }

    .insight-value {
        font-weight: 800;
        font-size: 1.1rem;
        color: #111827;
        display: block;
    }

    .insight-label {
        font-size: 0.7rem;
        color: #6b7280;
        font-weight: 700;
        text-transform: uppercase;
    }

    .grn-list {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 10px;
        max-height: 250px;
        overflow-y: auto;
        background: #fff;
    }

    .grn-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px;
        border-bottom: 1px solid #f1f3f5;
        cursor: pointer;
        border-radius: 6px;
    }

    .grn-item:hover {
        background-color: #f8f9fa;
    }

    .grn-amounts {
        font-size: 0.75rem;
        color: #6c757d;
    }

    .hide {
        display: none !important;
    }
</style>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-primary fw-bold">Integrated Payment Desk</h1>
            <p class="text-muted small">Consolidated fund request portal for vendors and operations.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill px-4">⬅ Dashboard</a>
    </div>

    <form method="post" id="paymentForm">
        <?php if (isset($_GET['rid'])): ?><input type="hidden" name="rid" value="<?= (int)$_GET['rid'] ?>"><?php endif; ?>

        <!-- Category Section -->
        <div class="card mb-4 border-start border-4 border-primary">
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Payment Category</label>
                        <select name="pay_for" id="pay_for" class="form-select border-2 border-primary" required>
                            <option value="">-- Choose Category --</option>
                            <option value="vendor">Vendor / Supplier Payment</option>
                            <option value="expenses">General Office Expenses</option>
                            <option value="fixed">Fixed Obligations (Rent/Bills)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- VENDOR BLOCK -->
        <div id="vendor_block" class="card mb-4 hide border-start border-4 border-info">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-dark">Vendor & GRN Selection</h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Vendor Name</label>
                        <select name="vendor_id" id="vendor_id" class="form-select">
                            <option value="">-- Select Vendor --</option>
                            <?php foreach ($vendors as $v): ?><option value="<?= $v['vendor_id'] ?>"><?= h($v['vendor_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Processing Branch</label>
                        <select name="branch_id" id="branch_id" class="form-select">
                            <option value="">-- Select Branch --</option>
                            <?php foreach ($branches as $b): ?><option value="<?= $b['branch_id'] ?>"><?= h($b['branch_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Select Pending GRNs</label>
                    <div id="grn_list" class="grn-list text-muted p-4 text-center">Select vendor and branch to load pending invoices...</div>
                </div>

                <div class="p-3 bg-light rounded-3 border">
                    <span class="insight-label">Vendor Master Bank Details</span>
                    <input type="text" id="vendor_bank_view" class="form-control-plaintext fw-bold" readonly value="Not Loaded">
                </div>
            </div>
        </div>

        <!-- EXPENSES BLOCK -->
        <div id="expenses_block" class="card mb-4 hide border-start border-4 border-secondary">
            <div class="card-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Expense Purpose</label>
                        <select name="purpose" id="purpose" class="form-select">
                            <option value="">-- Select Purpose --</option>
                            <?php foreach ($purposes as $p): ?><option value="<?= h($p) ?>"><?= h($p) ?></option><?php endforeach; ?>
                            <option value="__other__">Other (New Purpose)...</option>
                        </select>
                    </div>
                    <div id="custom_purpose_wrap" class="col-md-6 hide">
                        <label class="form-label">Describe New Purpose</label>
                        <input type="text" name="custom_purpose" id="custom_purpose" class="form-control" placeholder="Office maintenance, fuel, etc.">
                    </div>
                </div>
                <div id="exp_insight" class="hide p-3 bg-light rounded border">
                    <span class="insight-label">Previous Purpose Summary</span>
                    <div id="exp_summary_text" class="fw-bold small text-muted"></div>
                </div>
            </div>
        </div>

        <!-- FIXED BLOCK -->
        <div id="fixed_block" class="card mb-4 hide border-start border-4 border-dark">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Obligation Type</label>
                        <select name="fixed_id" id="fixed_id" class="form-select">
                            <option value="">-- Select Fixed Item --</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Schedule & Notes</label>
                        <input type="text" id="fixed_meta" class="form-control bg-light border-0" readonly>
                    </div>
                </div>
            </div>
        </div>

        <!-- AMOUNTS SECTION -->
        <div id="amounts_block" class="card mb-5 hide shadow-lg">
            <div class="card-header bg-dark text-white py-3">
                <h6 class="mb-0 fw-bold text-white">Financial Settlement</h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Total Balance</label>
                        <input type="number" id="view_total" class="form-control bg-light border-0 fw-bold" readonly value="0.00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Paid Till Date</label>
                        <input type="number" id="view_paid" class="form-control bg-light border-0 fw-bold" readonly value="0.00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Current Outstanding</label>
                        <input type="number" id="view_balance" class="form-control bg-light border-0 fw-bold text-danger" readonly value="0.00">
                    </div>
                </div>

                <div class="row g-4 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label text-dark fw-bold">Amount to Pay Now *</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light">₹</span>
                            <input type="number" step="0.01" name="pay_now" id="pay_now" class="form-control border-primary fw-bold text-primary" placeholder="0.00" required>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Internal Justification / Reference</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Describe why this payment is being requested..."></textarea>
                    </div>
                </div>

                <div id="split_preview" class="hide mt-4 p-3 rounded-3" style="background:#eef6ff; border:1px solid #cfe2ff;">
                    <span class="insight-label text-primary">Payment Allocation Preview:</span>
                    <div id="split_preview_content" class="mt-2 small"></div>
                </div>
            </div>
        </div>

        <!-- Hidden Data -->
        <div id="dynamic_hidden"></div>

        <div class="d-flex justify-content-center mb-5">
            <button type="submit" id="submitBtn" class="btn btn-primary px-5 py-3 fw-bold rounded-pill shadow-lg">
                🚀 Raise Fund Request
            </button>
        </div>
    </form>
</div>

<script>
    const q = s => document.querySelector(s);
    const payFor = q('#pay_for'),
        amountsBl = q('#amounts_block'),
        pNow = q('#pay_now');

    payFor.onchange = () => {
        document.querySelectorAll('.card:not(:first-child)').forEach(f => f.classList.add('hide'));
        amountsBl.classList.add('hide');
        const block = q('#' + payFor.value + '_block');
        if (block) block.classList.remove('hide');
        if (payFor.value) amountsBl.classList.remove('hide');
        updateSplits();
    };

    /** GRN LOGIC **/
    const refreshGrns = async () => {
        const vid = q('#vendor_id').value,
            bid = q('#branch_id').value;
        if (!vid || !bid) return;
        q('#grn_list').innerHTML = 'Refreshing pending invoices...';
        const res = await fetch(`?ajax=grns&vendor_id=${vid}&branch_id=${bid}`);
        const data = await res.json();
        q('#grn_list').innerHTML = data.length ? '' : 'No outstanding GRNs found.';
        data.forEach(g => {
            const item = document.createElement('label');
            item.className = 'grn-item';
            // REMOVED 'checked' attribute so items are unchecked by default
            item.innerHTML = `
                <input type="checkbox" class="grn-cb form-check-input" value="${g.grn_id}" data-total="${g.total_amount}" data-paid="${g.paid}" data-bal="${g.balance}">
                <div class="grn-amounts">
                    <strong>${g.grn_number}</strong><br>
                    Total: ₹${g.total_amount.toFixed(2)} | Paid: ₹${g.paid.toFixed(2)} | Bal: <span class="text-danger fw-bold">₹${g.balance.toFixed(2)}</span>
                </div>`;
            q('#grn_list').appendChild(item);
        });
        recalcVendor();
        const bank = await fetch(`?ajax=vendor_bank&vendor_id=${vid}`).then(r => r.json());
        q('#vendor_bank_view').value = (bank.account_number || 'N/A') + ' / ' + (bank.ifsc || 'N/A');
    };
    q('#vendor_id').onchange = q('#branch_id').onchange = refreshGrns;

    function recalcVendor() {
        const cbs = Array.from(document.querySelectorAll('.grn-cb:checked'));
        let t = 0,
            p = 0,
            b = 0;
        cbs.forEach(cb => {
            t += parseFloat(cb.dataset.total);
            p += parseFloat(cb.dataset.paid);
            b += parseFloat(cb.dataset.bal);
        });
        q('#view_total').value = t.toFixed(2);
        q('#view_paid').value = p.toFixed(2);
        q('#view_balance').value = b.toFixed(2);
        updateSplits();
    }

    /** EXPENSE LOGIC **/
    q('#purpose').onchange = async () => {
        const p = q('#purpose').value;
        q('#custom_purpose_wrap').classList.toggle('hide', p !== '__other__');
        if (p && p !== '__other__') {
            const j = await fetch(`?ajax=expense_summary&purpose=${encodeURIComponent(p)}`).then(r => r.json());
            q('#view_total').value = j.total.toFixed(2);
            q('#view_paid').value = j.paid.toFixed(2);
            q('#view_balance').value = j.balance.toFixed(2);
            q('#exp_insight').classList.remove('hide');
            q('#exp_summary_text').textContent = `Total Booked: ₹${j.total.toLocaleString()} | Outstanding: ₹${j.balance.toLocaleString()}`;
        } else {
            q('#exp_insight').classList.add('hide');
        }
    };

    /** FIXED LOGIC **/
    q('#fixed_id').onfocus = async () => {
        if (q('#fixed_id').options.length > 1) return;
        const data = await fetch('?ajax=fixed_list').then(r => r.json());
        data.forEach(r => q('#fixed_id').add(new Option(`${r.expense_type.toUpperCase()} (Bal: ₹${r.remaining_balance})`, r.id)));
    };
    q('#fixed_id').onchange = async () => {
        if (!q('#fixed_id').value) return;
        const j = await fetch('?ajax=fixed_one&id=' + q('#fixed_id').value).then(r => r.json());
        q('#fixed_meta').value = `${j.frequency} | Due: ${j.due_day} | ${j.notes}`;
        q('#view_total').value = j.total;
        q('#view_paid').value = j.paid;
        q('#view_balance').value = j.balance;
    };

    /** SPLIT & HIDDEN PAYLOAD LOGIC **/
    function updateSplits() {
        if (payFor.value !== 'vendor') {
            q('#split_preview').classList.add('hide');
            q('#dynamic_hidden').innerHTML = '';
            return;
        }
        const cbs = Array.from(document.querySelectorAll('.grn-cb:checked'));
        let rem = parseFloat(pNow.value || 0);
        let html = '<table class="table table-sm mb-0 mt-1">';
        const splits = [];
        cbs.forEach(cb => {
            const b = parseFloat(cb.dataset.bal);
            const amt = Math.min(rem, b);
            if (amt > 0) {
                html += `<tr><td>${cb.parentElement.querySelector('strong').textContent}</td><td class="text-end">₹${amt.toFixed(2)}</td></tr>`;
                splits.push({
                    id: cb.value,
                    amt: amt
                });
                rem -= amt;
            }
        });
        html += '</table>';
        q('#split_preview_content').innerHTML = html;
        q('#split_preview').classList.toggle('hide', splits.length === 0);

        q('#dynamic_hidden').innerHTML = '';
        splits.forEach(s => {
            q('#dynamic_hidden').innerHTML += `<input type="hidden" name="grn_ids[]" value="${s.id}"><input type="hidden" name="grn_splits[${s.id}]" value="${s.amt}">`;
        });
    }

    pNow.oninput = updateSplits;
    document.addEventListener('change', e => {
        if (e.target.classList.contains('grn-cb')) recalcVendor();
    });
</script>
<?php require_once("footer.php"); ?>