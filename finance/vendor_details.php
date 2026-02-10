<?php
/**
 * REQUESTER: Integrated Fund Request (Advanced Version)
 * Path: finance/payment1.php
 * UPDATED: Integrated calculation logic from vendor_details1.php.
 * UPDATED: Fixed balance math (Gross - Returns + Trans) and added Fixed Obligation lookups.
 */
declare(strict_types=1);

require_once("../functions.php");

/* ---------- Tiny Utils (KMK Standard) ---------- */
if (!function_exists('v')) { function v($k, $d = null) { return $_POST[$k] ?? $_GET[$k] ?? $d; } }
if (!function_exists('i')) { function i($x) { return is_numeric($x) ? (int)$x : 0; } }
if (!function_exists('s')) { function s($x) { return trim((string)($x ?? '')); } }
if (!function_exists('h')) { function h($x) { return htmlspecialchars((string)($x ?? ''), ENT_QUOTES, 'UTF-8'); } }

if (!function_exists('json_out')) {
    function json_out($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/* ---------- 1. AJAX API ROUTER ---------- */
if (isset($_GET['ajax'])) {
    $act = $_GET['ajax'];

    // A. Fetch GRNs for Vendor (Logic matched to vendor_details1.php)
    if ($act === 'grns') {
        $vid = i(v('vendor_id'));
        $bid = i(v('branch_id'));
        
        // Joined with subqueries to get accurate Returns and Paid values
        $sql = "SELECT gr.grn_id, gr.grn_number, gr.total_amount, gr.transportation, gr.invoice_number, gr.paid_amount,
                (SELECT SUM(total_amount) FROM goods_return_notes WHERE grn_id = gr.grn_id) as rtn_val
                FROM goods_receipts gr JOIN purchase_orders po ON po.po_id = gr.po_id
                WHERE gr.vendor_id = $vid AND po.branch_id = $bid ORDER BY gr.grn_id DESC";
        
        $rows = exeSql($sql) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $gid = (int)$r['grn_id'];
            $returns = (float)($r['rtn_val'] ?? 0);
            
            // MATH: (Gross Amount - Returns) + Transportation
            // Consistent with vendor_details1.php logic
            $gross_in = max(0, (float)$r['total_amount'] - $returns) + (float)$r['transportation'];
            $paid_out = (float)($r['paid_amount'] ?? 0);
            
            $bal = $gross_in - $paid_out;
            
            if ($bal > 0.01) {
                $out[] = [
                    'id' => $gid, 
                    'no' => $r['grn_number'], 
                    'inv' => $r['invoice_number'], 
                    'total' => $gross_in, 
                    'paid' => $paid_out, 
                    'bal' => $bal
                ];
            }
        }
        json_out($out);
    }

    // B. Fixed Obligation Lookups
    if ($act === 'fixed_list') {
        json_out(exeSql("SELECT id, expense_type, (amount - IFNULL(balance_paid, 0)) as remaining FROM fixed_expenses HAVING remaining > 0") ?: []);
    }
    
    if ($act === 'fixed_one') {
        $fid = i(v('id'));
        $row = exeSql("SELECT *, amount as total, balance_paid as paid, (amount - IFNULL(balance_paid, 0)) as bal FROM fixed_expenses WHERE id = $fid LIMIT 1");
        json_out($row ? $row[0] : []);
    }

    // C. Event Component Lookup
    if ($act === 'events') {
        json_out(exeSql("SELECT event_id, event_name FROM events ORDER BY event_id DESC") ?: []);
    }
    if ($act === 'event_items') {
        $eid = i(v('event_id'));
        json_out(exeSql("SELECT item_id, item_name, balance FROM event_items WHERE event_id = $eid AND balance > 0") ?: []);
    }

    // D. Expense & Duplicate Check
    if ($act === 'expense_summary') {
        $p = s(v('purpose'));
        $row = exeSql("SELECT amount, balance_paid FROM expenses WHERE purpose = '".addslashes($p)."' ORDER BY id DESC LIMIT 1");
        if($row) {
            $r = $row[0];
            json_out(['total' => (float)$r['amount'], 'paid' => (float)$r['balance_paid'], 'bal' => (float)$r['amount'] - (float)$r['balance_paid']]);
        }
        json_out(['total'=>0,'paid'=>0,'bal'=>0]);
    }

    if ($act === 'dup_check') {
        $type = s(v('type'));
        $ref = s(v('ref'));
        $exists = exeSql("SELECT request_id FROM payment_requests WHERE request_type='$type' AND status IN ('SUBMITTED','APPROVED') AND (payload_json LIKE '%$ref%') LIMIT 1");
        json_out(['duplicate' => !empty($exists)]);
    }
    
    if ($act === 'vendor_meta') {
        $vid = i(v('vendor_id'));
        $row = exeSql("SELECT v.account_number, v.ifsc, t.redemption_points 
                       FROM vendors v 
                       LEFT JOIN vendor_totals t ON t.vendor_id = v.vendor_id 
                       WHERE v.vendor_id = $vid LIMIT 1");
        json_out($row ? $row[0] : []);
    }
    exit;
}

/* ---------- 2. SUBMISSION HANDLER ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pay_for = s(v('pay_for'));
    $payload = $_POST;
    unset($payload['action']);

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

    $newId = insData('payment_requests', $data);
    header("Location: dashboard.php?msg=submitted&rid=$newId");
    exit;
}

include 'header.php';
include 'nav.php';

$branches = exeSql("SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
$vendors = exeSql("SELECT vendor_id, vendor_name FROM vendors ORDER BY vendor_name");
$purposes = exeSql("SELECT DISTINCT purpose FROM expenses WHERE purpose IS NOT NULL ORDER BY purpose");
?>

<div class="container py-5" style="max-width: 1100px;">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <div>
            <h1 class="h3 mb-0 text-primary fw-bold">Integrated Payment Desk</h1>
            <p class="text-muted small mb-0">Multi-category fund request portal with ledger synchronization.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill px-4">Back to Dashboard</a>
    </div>

    <form method="POST" id="payForm">
        <!-- Category Selector -->
        <div class="card mb-4 border-start border-4 border-primary shadow-sm">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-muted text-uppercase">Request Category</label>
                        <select name="pay_for" id="pay_for" class="form-select border-2 border-primary fw-bold" required>
                            <option value="">-- Choose Category --</option>
                            <option value="vendor">Vendor Settlement (GRN Linked)</option>
                            <option value="expenses">Operational Expense</option>
                            <option value="fixed">Fixed Obligation (Rent/Bills)</option>
                            <option value="event">Event Material/Services</option>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <div id="dup_warning" class="alert alert-warning py-2 mb-0 d-none" style="font-size:0.8rem;">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> Warning: A similar request is already pending in the system.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- VENDOR BLOCK -->
        <div id="vendor_block" class="card mb-4 d-none border-0 rounded-4 shadow-sm overflow-hidden">
            <div class="card-header bg-info text-white fw-bold py-3">Vendor & Invoice Specification</div>
            <div class="card-body p-4 bg-light">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">VENDOR NAME</label>
                        <select name="vendor_id" id="v_id" class="form-select select2">
                            <option value="">-- Choose Vendor --</option>
                            <?php foreach($vendors as $v): ?><option value="<?= $v['vendor_id'] ?>"><?= h($v['vendor_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">PROCESSING BRANCH</label>
                        <select name="branch_id" id="v_branch" class="form-select">
                            <option value="">-- Choose Branch --</option>
                            <?php foreach($branches as $b): ?><option value="<?= $b['branch_id'] ?>"><?= h($b['branch_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row g-3 mb-4">
                    <div class="col-md-8">
                        <div class="mb-2"><label class="form-label small fw-bold">SELECT PENDING INVOICES (FIFO Split)</label></div>
                        <div id="grn_container" class="p-3 bg-white border rounded-3" style="max-height:300px; overflow-y:auto;">
                            <div class="text-muted text-center py-4">Select vendor and branch to load invoices...</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-white border rounded-3 h-100">
                             <label class="form-label small fw-bold text-muted">VENDOR MASTER INFO</label>
                             <div id="vendor_meta_display" class="small mt-2">
                                 <div class="text-muted italic">Select vendor to view details...</div>
                             </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FIXED OBLIGATION BLOCK -->
        <div id="fixed_block" class="card mb-4 d-none border-0 rounded-4 shadow-sm overflow-hidden">
            <div class="card-header bg-secondary text-white fw-bold py-3">Fixed Recurring Obligation</div>
            <div class="card-body p-4 bg-light">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">SELECT OBLIGATION</label>
                        <select name="fixed_id" id="f_id" class="form-select select2"></select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">SCHEDULE DETAILS</label>
                        <input type="text" id="f_meta" class="form-control bg-white" readonly placeholder="N/A">
                    </div>
                </div>
            </div>
        </div>

        <!-- EVENT BLOCK -->
        <div id="event_block" class="card mb-4 d-none border-0 rounded-4 shadow-sm overflow-hidden">
            <div class="card-header bg-warning text-dark fw-bold py-3">Event Billing Linkage</div>
            <div class="card-body p-4 bg-light">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">SELECT EVENT</label>
                        <select name="event_id" id="e_id" class="form-select select2"></select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">SERVICE COMPONENT</label>
                        <select name="event_item_id" id="e_item" class="form-select"></select>
                    </div>
                </div>
            </div>
        </div>

        <!-- EXPENSE BLOCK -->
        <div id="expenses_block" class="card mb-4 d-none border-0 rounded-4 shadow-sm overflow-hidden">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">PURPOSE / CATEGORY</label>
                        <select name="purpose" id="exp_purpose" class="form-select select2">
                            <option value="">-- Select Purpose --</option>
                            <?php foreach($purposes as $p): ?><option value="<?= h($p['purpose']) ?>"><?= h($p['purpose']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">BRANCH</label>
                        <select name="branch_id" id="exp_branch" class="form-select">
                            <?php foreach($branches as $b): ?><option value="<?= $b['branch_id'] ?>"><?= h($b['branch_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- SETTLEMENT SUMMARY -->
        <div id="settlement_block" class="card mb-5 d-none shadow-lg border-0 rounded-4 overflow-hidden">
            <div class="card-header bg-dark text-white py-3 d-flex justify-content-between">
                <h6 class="mb-0 fw-bold">FINANCIAL SETTLEMENT</h6>
                <div id="split_summary" class="small text-warning fw-bold"></div>
            </div>
            <div class="card-body p-4">
                <div class="row g-4 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small text-muted text-uppercase">Calculated Balance</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">₹</span>
                            <input type="text" id="view_bal" class="form-control bg-light border-0 fw-bold text-danger" readonly value="0.00">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold text-dark">AMOUNT TO PAY NOW *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-primary text-white">₹</span>
                            <input type="number" step="0.01" name="pay_now" id="pay_now" class="form-control border-primary fw-bold" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase">Justification / Remarks</label>
                        <textarea name="notes" class="form-control" rows="1" placeholder="Reason for fund request..."></textarea>
                    </div>
                </div>

                <div id="allocation_preview" class="mt-4 p-3 bg-primary-subtle rounded-3 d-none border border-primary-subtle">
                    <span class="small fw-bold text-primary text-uppercase">Automatic Allocation Preview:</span>
                    <div id="allocation_list" class="mt-1 small"></div>
                </div>
            </div>
        </div>

        <div id="hidden_splits"></div>

        <div class="text-center mb-5">
            <button type="submit" class="btn btn-primary btn-lg rounded-pill shadow-lg px-5 py-3 fw-bold">
                <i class="bi bi-send-check me-2"></i> Submit Fund Request
            </button>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    const q = s => document.querySelector(s);
    const payFor = q('#pay_for'), pNow = q('#pay_now');

    payFor.onchange = () => {
        ['vendor','event','expenses','fixed','settlement'].forEach(id => q('#'+id+'_block')?.classList.add('d-none'));
        q('#settlement_block').classList.toggle('d-none', !payFor.value);
        
        if (payFor.value === 'vendor') q('#vendor_block').classList.remove('d-none');
        if (payFor.value === 'event') { q('#event_block').classList.remove('d-none'); loadEvents(); }
        if (payFor.value === 'expenses') q('#expenses_block').classList.remove('d-none');
        if (payFor.value === 'fixed') { q('#fixed_block').classList.remove('d-none'); loadFixed(); }
        
        q('#view_bal').value = '0.00';
    };

    /** Vendor Meta & GRN Loading **/
    const refreshVendor = async () => {
        const vid = q('#v_id').value, bid = q('#v_branch').value;
        if (!vid) return;
        
        // 1. Load Bank & Points info
        const meta = await fetch(`?ajax=vendor_meta&vendor_id=${vid}`).then(r => r.json());
        q('#vendor_meta_display').innerHTML = `
            <div class="mb-1"><strong>A/c:</strong> ${meta.account_number || 'N/A'}</div>
            <div class="mb-1"><strong>IFSC:</strong> ${meta.ifsc || 'N/A'}</div>
            <div class="text-info fw-bold"><strong>Avail. Points:</strong> ₹${parseFloat(meta.redemption_points || 0).toLocaleString()}</div>
        `;

        if (!bid) return;

        // 2. Load Invoices
        const data = await fetch(`?ajax=grns&vendor_id=${vid}&branch_id=${bid}`).then(r => r.json());
        let html = data.length ? '' : '<div class="text-center py-3 text-muted">No pending invoices found.</div>';
        data.forEach(g => {
            html += `<label class="d-flex justify-content-between align-items-center p-3 border-bottom border-light-subtle cursor-pointer hover-bg-light">
                <div class="d-flex align-items-center gap-3">
                    <input type="checkbox" class="grn-cb form-check-input border-2" value="${g.id}" data-bal="${g.bal}" data-no="${g.no}">
                    <div><div class="fw-bold text-dark">${g.no}</div><div class="small text-muted">Inv: ${g.inv || 'N/A'}</div></div>
                </div>
                <div class="text-end">
                    <div class="small text-muted">Outstanding</div>
                    <div class="fw-bold text-danger">₹${g.bal.toLocaleString()}</div>
                </div>
            </label>`;
        });
        q('#grn_container').innerHTML = html;
        updateBalance();
    };
    q('#v_id').onchange = q('#v_branch').onchange = refreshVendor;

    function updateBalance() {
        let total = 0;
        document.querySelectorAll('.grn-cb:checked').forEach(cb => total += parseFloat(cb.dataset.bal));
        q('#view_bal').value = total.toFixed(2);
        doSplit();
    }

    /** FIFO Split Logic **/
    function doSplit() {
        if (payFor.value !== 'vendor') return;
        const cbs = Array.from(document.querySelectorAll('.grn-cb:checked'));
        let rem = parseFloat(pNow.value || 0);
        let html = '', hidden = '';
        cbs.forEach(cb => {
            let b = parseFloat(cb.dataset.bal);
            let amt = Math.min(rem, b);
            if (amt > 0) {
                html += `<div class="d-flex justify-content-between border-bottom pb-1 mb-1"><span>${cb.dataset.no}</span><strong>₹${amt.toFixed(2)}</strong></div>`;
                hidden += `<input type="hidden" name="grn_ids[]" value="${cb.value}"><input type="hidden" name="grn_splits[${cb.value}]" value="${amt}">`;
                rem -= amt;
            }
        });
        q('#allocation_list').innerHTML = html;
        q('#allocation_preview').classList.toggle('d-none', !html);
        q('#hidden_splits').innerHTML = hidden;
    }

    /** Fixed Logic **/
    async function loadFixed() {
        const res = await fetch('?ajax=fixed_list').then(r => r.json());
        q('#f_id').innerHTML = '<option value="">-- Select Obligation --</option>' + res.map(x => `<option value="${x.id}">${x.expense_type.toUpperCase()}</option>`).join('');
    }
    q('#f_id').onchange = async () => {
        if(!q('#f_id').value) return;
        const x = await fetch('?ajax=fixed_one&id=' + q('#f_id').value).then(r => r.json());
        q('#f_meta').value = `${x.frequency} | Due Day: ${x.due_day}`;
        q('#view_bal').value = parseFloat(x.bal).toFixed(2);
    };

    /** Event Logic **/
    async function loadEvents() {
        const res = await fetch('?ajax=events').then(r => r.json());
        q('#e_id').innerHTML = '<option value="">-- Choose Event --</option>' + res.map(e => `<option value="${e.event_id}">${e.event_name}</option>`).join('');
    }
    q('#e_id').onchange = async () => {
        const res = await fetch('?ajax=event_items&event_id=' + q('#e_id').value).then(r => r.json());
        q('#e_item').innerHTML = '<option value="">-- Component --</option>' + res.map(i => `<option value="${i.item_id}" data-bal="${i.balance}">${i.item_name}</option>`).join('');
    };
    q('#e_item').onchange = () => {
        const opt = q('#e_item').selectedOptions[0];
        q('#view_bal').value = opt?.dataset.bal || '0.00';
    };

    /** Init & Listeners **/
    pNow.oninput = doSplit;
    document.addEventListener('change', e => { if(e.target.classList.contains('grn-cb')) updateBalance(); });
    
    $(document).ready(function() {
        $('.select2').select2({ theme: 'bootstrap4', width: '100%' });
    });
</script>

<style>
    .cursor-pointer { cursor: pointer; }
    .hover-bg-light:hover { background-color: #f8f9fa; }
    .grn-cb:checked + div { color: #0d6efd; }
</style>

<?php include 'footer.php'; ?>