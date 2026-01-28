<?php
declare(strict_types=1);

/**
 * 1. INITIALIZE DATABASE & UTILITIES
 * We must include functions.php first to have access to exeSql() 
 * and other DB helpers without outputting any HTML.
 */
require_once("../functions.php"); // Adjust path if functions.php is in the same directory

/** Utility Helpers **/
if (!function_exists('v')) { function v($k, $d = null) { return $_POST[$k] ?? $_GET[$k] ?? $d; } }
if (!function_exists('i')) { function i($x) { return is_numeric($x) ? (int)$x : 0; } }
if (!function_exists('s')) { function s($x) { return trim((string)$x); } }
if (!function_exists('h')) { function h($x) { return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('json_out')) {
    function json_out($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

/** Schema Helper **/
function get_table_cols(string $table): array {
    $rows = exeSql("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$table'");
    return array_column($rows ?: [], 'COLUMN_NAME');
}

/* ---------- FETCH LOGIC ---------- */
function fetch_pending_grns($vendor_id, $branch_id) {
    $vendor_id = (int)$vendor_id;
    $branch_id = (int)$branch_id;
    $rows = exeSql("SELECT gr.grn_id, gr.grn_number, gr.total_amount, gr.transportation 
                    FROM goods_receipts gr JOIN purchase_orders po ON po.po_id = gr.po_id
                    WHERE gr.vendor_id = $vendor_id AND po.branch_id = $branch_id ORDER BY gr.grn_id DESC") ?: [];

    $out = [];
    foreach ($rows as $r) {
        $gid = (int)$r['grn_id'];
        $rtRow = exeSql("SELECT total_amount FROM goods_return_notes WHERE grn_id=$gid LIMIT 1");
        $rt = (float)($rtRow[0]['total_amount'] ?? 0);

        $paidRow = exeSql("SELECT (IFNULL(SUM(amount),0) + IFNULL(SUM(advance_used),0) + IFNULL(SUM(redemption_used),0)) as total_credit FROM vendor_grn_payments WHERE grn_id=$gid");
        $paid = (float)($paidRow[0]['total_credit'] ?? 0);

        $gross = (float)$r['total_amount'];
        $transport = (float)$r['transportation'];
        $total = max(0, $gross + $transport - $rt);
        $bal = $total - $paid;

        $check_pending = exeSql("SELECT request_id, status FROM payment_requests WHERE status IN ('SUBMITTED','APPROVED','READY_FOR_CASHIER') AND payload_json LIKE '%\"$gid\"%' LIMIT 1");
        $is_pending = !empty($check_pending);
        $p_info = $is_pending ? $check_pending[0] : null;

        if ($bal > 0.01) {
            $out[] = [
                'grn_id' => $gid,
                'grn_number' => $r['grn_number'],
                'gross_amount' => $gross,
                'transport' => $transport,
                'returns' => $rt,
                'total_amount' => $total,
                'paid' => $paid,
                'balance' => $bal,
                'is_pending' => $is_pending,
                'p_rid' => $p_info['request_id'] ?? null,
                'p_status' => $p_info['status'] ?? null
            ];
        }
    }
    return $out;
}

/**
 * 2. AJAX ROUTER (MUST BE BEFORE ANY HTML OUTPUT)
 */
if (isset($_GET['ajax'])) {
    $act = $_GET['ajax'];

    /* --- EMPLOYEE AJAX (COMMENTED) ---
    if ($act === 'employees') {
        $branch_id = i(v('branch_id'));
        json_out(exeSql("SELECT id, employee_name FROM employees WHERE branch_id=$branch_id ORDER BY employee_name") ?: []);
    }
    if ($act === 'employee') {
        $eid = i(v('employee_id'));
        $period = addslashes(s(v('period')));
        $emp = getRowValues('employees', $eid, 'id');
        $paidRow = exeSql("SELECT IFNULL(SUM(amount),0) AS paid FROM employee_salary_payments WHERE employee_id=$eid AND pay_period='$period'");
        json_out(['role' => $emp['role'] ?? '','salary' => (float)($emp['salary'] ?? 0),'paid' => (float)($paidRow[0]['paid'] ?? 0)]);
    }
    ----------------------------------*/

    if ($act === 'grns') json_out(fetch_pending_grns(i(v('vendor_id')), i(v('branch_id'))));

    if ($act === 'vendor_bank') {
        $v = getRowValues('vendors', i(v('vendor_id')), 'vendor_id');
        json_out(['account_number' => $v['account_number'] ?? '', 'ifsc' => $v['ifsc'] ?? '']);
    }

    if ($act === 'expense_summary') {
        $p = s(v('purpose'));
        $row = exeSql("SELECT amount, balance_paid, (amount-balance_paid) as remaining_balance FROM expenses WHERE purpose='" . addslashes($p) . "' ORDER BY id DESC LIMIT 1");
        json_out($row ? ['total' => $row[0]['amount'], 'paid' => $row[0]['balance_paid'], 'balance' => $row[0]['remaining_balance']] : ['total' => 0, 'paid' => 0, 'balance' => 0]);
    }

    if ($act === 'fixed_list') {
        json_out(exeSql("SELECT id, expense_type, amount, balance_paid, (amount-balance_paid) as remaining_balance FROM fixed_expenses HAVING remaining_balance > 0 ORDER BY id DESC") ?: []);
    }

    if ($act === 'fixed_one') {
        $id = i(v('id'));
        $row = exeSql("SELECT *, (amount-balance_paid) as remaining_balance FROM fixed_expenses WHERE id=$id LIMIT 1");
        json_out($row ? ['id' => $row[0]['id'], 'expense_type' => $row[0]['expense_type'], 'frequency' => $row[0]['frequency'], 'due_day' => $row[0]['due_day'], 'notes' => $row[0]['notes'], 'total' => $row[0]['amount'], 'paid' => $row[0]['balance_paid'], 'balance' => $row[0]['remaining_balance']] : []);
    }

    if ($act === 'dup_check') {
        $pf = s(v('pay_for'));
        $rid = i(v('rid', 0));
        $active_statuses = "'SUBMITTED','APPROVED','READY_FOR_CASHIER'";
        if ($pf === 'vendor') {
            $gids = explode(',', s(v('grn_ids', '')));
            if (!empty($gids)) {
                $conds = array_map(fn($id) => "payload_json LIKE '%\"" . (int)$id . "\"%'", $gids);
                $found = exeSql("SELECT request_id, status FROM payment_requests WHERE status IN ($active_statuses) AND request_id != $rid AND (" . implode(' OR ', $conds) . ") LIMIT 1");
                if ($found) json_out(['duplicate' => true, 'request_id' => $found[0]['request_id'], 'status' => $found[0]['status']]);
            }
        }
        json_out(['duplicate' => false]);
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
        'payload_json' => json_encode($payload),
        'requested_by' => (int)($_SESSION['userId'] ?? 1),
        'vendor_id'    => i(v('vendor_id')) ?: null,
        'branch_id'    => i(v('branch_id')) ?: null,
        'total_amount' => (float)s(v('pay_now', '0')),
        'status'       => 'SUBMITTED'
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

/**
 * 3. HTML OUTPUT START
 */
require_once("./header.php");
require_once("./nav.php");

$branches = exeSql("SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
$vendors = exeSql("SELECT vendor_id, vendor_name FROM vendors ORDER BY vendor_name");
$purposes = exeSql("SELECT DISTINCT purpose FROM expenses WHERE purpose IS NOT NULL AND TRIM(purpose)<>'' ORDER BY purpose");
?>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-primary fw-bold"><?= isset($_GET['rid']) ? 'Edit' : 'New' ?> Payment Request</h1>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm rounded-3">⬅ Back to Dashboard</a>
    </div>

    <form method="post" id="paymentForm">
        <?php if (isset($_GET['rid'])): ?><input type="hidden" name="rid" value="<?= (int)$_GET['rid'] ?>"><?php endif; ?>

        <!-- Category Section -->
        <div class="card shadow-sm border-0 mb-4 rounded-4">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="card-title mb-0 fw-bold text-uppercase text-primary small">Transaction Category</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Payment Type</label>
                        <select name="pay_for" id="pay_for" class="form-select rounded-3" required>
                            <option value="">-- Select Type --</option>
                            <option value="vendor">Vendor Payment</option>
                            <option value="expenses">Expenses</option>
                            <option value="fixed">Fixed Expenses</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- VENDOR BLOCK -->
        <div id="vendor_block" class="card shadow-sm border-0 mb-4 d-none rounded-4">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="card-title mb-0 fw-bold text-uppercase text-primary small">Vendor Details</h6>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Vendor Name</label>
                        <select name="vendor_id" id="vendor_id" class="form-select rounded-3">
                            <option value="">-- Select Vendor --</option>
                            <?php foreach ($vendors as $v): ?><option value="<?= $v['vendor_id'] ?>"><?= h($v['vendor_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Processing Branch</label>
                        <select name="branch_id" id="branch_id" class="form-select rounded-3">
                            <option value="">-- Select Branch --</option>
                            <?php foreach ($branches as $b): ?><option value="<?= $b['branch_id'] ?>"><?= h($b['branch_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold small">Pending GRNs</label>
                    <div id="grn_list" class="list-group border rounded-3 overflow-auto shadow-none" style="max-height: 250px;">
                        <div class="list-group-item text-muted text-center py-4 bg-light small italic">Select vendor and branch to view unpaid receipts.</div>
                    </div>
                </div>

                <div class="mb-0">
                    <label class="form-label fw-semibold small">Bank Details (ReadOnly)</label>
                    <input type="text" id="vendor_bank_view" class="form-control bg-light border-0 small" readonly placeholder="Auto-fills on selection">
                </div>
            </div>
        </div>

        <!-- EMPLOYEE BLOCK (PRESERVED IN COMMENTS) -->
        <!-- 
        <div id="employee_block" class="card shadow-sm border-0 mb-4 d-none rounded-4">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="card-title mb-0 fw-bold text-uppercase text-primary small">Employee Salary</h6>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Branch</label>
                        <select id="emp_branch_id" class="form-select">
                            <option value="">-- Select branch --</option>
                            <?php foreach ($branches as $b): ?><option value="<?= $b['branch_id'] ?>"><?= h($b['branch_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Employee</label>
                        <select name="employee_id" id="employee_id" class="form-select">
                            <option value="">-- Select --</option>
                        </select>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Role</label>
                        <input type="text" id="emp_role" class="form-control bg-light border-0" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Salary</label>
                        <input type="text" id="emp_salary" class="form-control bg-light border-0" readonly>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Pay Period (YYYYMM)</label>
                        <input type="text" name="pay_period" id="pay_period" class="form-control" value="<?//= date('Ym') ?>">
                    </div>
                </div>
            </div>
        </div> 
        -->

        <!-- EXPENSES BLOCK -->
        <div id="expenses_block" class="card shadow-sm border-0 mb-4 d-none rounded-4">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="card-title mb-0 fw-bold text-uppercase text-primary small">Expense Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Purpose</label>
                        <select name="purpose" id="purpose" class="form-select rounded-3">
                            <option value="">-- Select Purpose --</option>
                            <?php foreach ($purposes as $p): ?><option value="<?= h($p) ?>"><?= h($p) ?></option><?php endforeach; ?>
                            <option value="__other__">Other (New Purpose)...</option>
                        </select>
                    </div>
                    <div id="custom_purpose_wrap" class="col-md-6 d-none">
                        <label class="form-label fw-semibold small">New Purpose Name</label>
                        <input type="text" name="custom_purpose" id="custom_purpose" class="form-control rounded-3" placeholder="Enter purpose">
                    </div>
                </div>
            </div>
        </div>

        <!-- FIXED BLOCK -->
        <div id="fixed_block" class="card shadow-sm border-0 mb-4 d-none rounded-4">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="card-title mb-0 fw-bold text-uppercase text-primary small">Fixed Obligations</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Expense Type</label>
                        <select name="fixed_id" id="fixed_id" class="form-select rounded-3">
                            <option value="">-- Select --</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Reference / Meta</label>
                        <input type="text" id="fixed_meta" class="form-control bg-light border-0 small" readonly>
                    </div>
                </div>
            </div>
        </div>

        <!-- AMOUNTS SECTION -->
        <div id="amounts_block" class="card shadow-sm border-0 mb-4 d-none rounded-4">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="card-title mb-0 fw-bold text-uppercase text-primary small">Payment Amount Details</h6>
            </div>
            <div class="card-body">
                <div id="dup_alert" class="alert d-none mb-4 fw-medium small rounded-3" role="alert"></div>

                <div class="row g-3">
                    <div class="col-md-3 col-6 text-center text-md-start">
                        <label class="form-label text-secondary small fw-bold">Total Bill</label>
                        <input type="number" step="0.01" id="total_amount" class="form-control bg-light border-0 text-muted fw-semibold" readonly value="0.00">
                    </div>
                    <div class="col-md-3 col-6 text-center text-md-start">
                        <label class="form-label text-secondary small fw-bold">Paid till now</label>
                        <input type="number" step="0.01" id="paid_so_far" class="form-control bg-light border-0 text-muted fw-semibold" readonly value="0.00">
                    </div>
                    <div class="col-md-3 col-6 text-center text-md-start">
                        <label class="form-label text-primary small fw-bold">Current Balance</label>
                        <input type="number" step="0.01" id="view_balance" class="form-control bg-primary-subtle border-0 text-primary fw-bold" readonly value="0.00">
                    </div>
                    <div class="col-md-3 col-6 text-center text-md-start">
                        <label class="form-label text-dark small fw-bold">Amount Paying Now</label>
                        <input type="number" step="0.01" name="pay_now" id="pay_now" class="form-control border-2 border-primary fw-bold" value="0.00" required>
                    </div>
                </div>

                <div id="split_preview" class="mt-4 p-3 bg-light border rounded-3 d-none"></div>
            </div>
        </div>

        <div class="d-flex justify-content-end mb-5">
            <button type="submit" id="submitBtn" class="btn btn-primary px-5 py-3 fw-bold rounded-pill shadow">Raise Request</button>
        </div>
    </form>
</div>

<script>
    /** Robust Selector Helper **/
    const q = s => (typeof s === 'string' ? document.querySelector(s) : s);

    const payFor = q('#pay_for'),
        amountsBl = q('#amounts_block'),
        alertBox = q('#dup_alert'),
        submitBtn = q('#submitBtn');
        
    const vSel = q('#vendor_id'),
        bSel = q('#branch_id'),
        splitPrev = q('#split_preview');

    payFor.onchange = () => {
        document.querySelectorAll('.card:not(:first-child)').forEach(f => f.classList.add('d-none'));
        amountsBl.classList.add('d-none');
        alertBox.classList.add('d-none');
        submitBtn.disabled = false;

        splitPrev.classList.add('d-none');
        splitPrev.innerHTML = '';

        const block = q('#' + payFor.value + '_block');
        if (block) block.classList.remove('d-none');
        if (payFor.value) amountsBl.classList.remove('d-none');
        
        ['total_amount', 'paid_so_far', 'view_balance', 'pay_now'].forEach(id => {
            const el = q('#' + id);
            if(el) el.value = "0.00";
        });
    };

    function getStatusMsg(status, rid) {
        const map = {
            'SUBMITTED': `Request #${rid} is already raised and waiting for approval.`,
            'APPROVED': `Request #${rid} is approved! Forward it to cashier from your Dashboard.`,
            'READY_FOR_CASHIER': `Request #${rid} is currently being processed by the Cashier.`
        };
        return map[status] || `A request (#${rid}) exists with status ${status}.`;
    }

    async function liveDupCheck() {
        const pf = payFor.value;
        if (!pf) return;
        
        const pay = parseFloat(q('#pay_now').value || 0),
              bal = parseFloat(q('#view_balance').value || 0);

        // 1. Strict Balance Logic
        if (pay > bal + 0.01 && pf !== 'employee') {
            showAlert(`Amount exceeds balance! Balance is ₹${bal.toFixed(2)}`, true);
            return;
        }

        // 2. Vendor Conflict Logic (ONLY for Vendors)
        if (pf === 'vendor') {
            const checked = Array.from(document.querySelectorAll('.grn-cb:checked'));
            const pending = checked.filter(cb => cb.dataset.pending === '1');
            
            if (pending.length) {
                showAlert("Conflict: One or more selected GRNs are already in another request (#" + pending.map(cb => cb.dataset.prid).join(', ') + "). Choose wisely.", false);
                return;
            }
            
            if (checked.length) {
                const grnIds = checked.map(cb => cb.value).join(',');
                const rid = '<?= i(v('rid', 0)) ?>';
                const j = await fetch(`?ajax=dup_check&pay_for=vendor&grn_ids=${grnIds}&rid=${rid}`).then(r => r.json());
                if (j.duplicate) {
                    showAlert(getStatusMsg(j.status, j.request_id), false);
                    return;
                }
            }
        }
        clearAlert();
    }

    function showAlert(m, isErr) {
        alertBox.textContent = m;
        alertBox.className = 'alert d-block mb-4 rounded-3 ' + (isErr ? 'alert-danger shadow-sm border-0' : 'alert-warning shadow-sm border-0');
        alertBox.classList.remove('d-none');
        submitBtn.disabled = true;
    }

    function clearAlert() {
        alertBox.classList.add('d-none');
        submitBtn.disabled = false;
    }

    const refreshVendor = async () => {
        if (!vSel.value || !bSel.value) return;
        q('#grn_list').innerHTML = '<div class="text-center p-4 small text-muted italic">Checking for unpaid GRNs...</div>';
        
        const data = await fetch(`?ajax=grns&vendor_id=${vSel.value}&branch_id=${bSel.value}`).then(r => r.json());
        q('#grn_list').innerHTML = data.length ? '' : '<div class="p-4 text-center text-muted small italic">No pending receipts found.</div>';
        
        data.forEach(g => {
            const item = document.createElement('div');
            item.className = 'list-group-item list-group-item-action d-flex align-items-start gap-3 p-3 ' + (g.is_pending ? 'bg-warning-subtle' : '');
            item.innerHTML = `
                    <input type="checkbox" class="form-check-input mt-1 grn-cb" value="${g.grn_id}" data-total="${g.total_amount}" data-paid="${g.paid}" data-bal="${g.balance}" data-num="${g.grn_number}" data-pending="${g.is_pending?1:0}" data-prid="${g.p_rid}">
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong class="text-dark small">${g.grn_number}</strong>
                            ${g.is_pending ? `<span class="badge bg-warning text-dark border-0 small">Stage: ${g.p_status} (#${g.p_rid})</span>` : ''}
                        </div>
                        <div class="text-muted small mt-1">Total: ₹${parseFloat(g.total_amount).toFixed(2)} | Balance: <span class="fw-bold text-primary">₹${parseFloat(g.balance).toFixed(2)}</span></div>
                    </div>`;
            q('#grn_list').appendChild(item);
            item.onclick = (e) => {
                if (e.target.type !== 'checkbox') item.querySelector('input').click();
            };
        });
        
        fetch(`?ajax=vendor_bank&vendor_id=${vSel.value}`).then(r => r.json()).then(j => {
            q('#vendor_bank_view').value = (j.account_number || 'N/A') + ' / ' + (j.ifsc || 'N/A');
        });
        recalcVendor();
    };

    function recalcVendor() {
        let t = 0, p = 0, b = 0;
        document.querySelectorAll('.grn-cb:checked').forEach(cb => {
            t += parseFloat(cb.dataset.total);
            p += parseFloat(cb.dataset.paid);
            b += parseFloat(cb.dataset.bal);
        });
        q('#total_amount').value = t.toFixed(2);
        q('#paid_so_far').value = p.toFixed(2);
        q('#view_balance').value = b.toFixed(2);
        updateSplits();
        liveDupCheck();
    }

    function updateSplits() {
        if (payFor.value !== 'vendor') {
            splitPrev.classList.add('d-none');
            return;
        }
        let rem = parseFloat(q('#pay_now').value || 0),
            html = '<h6 class="fw-bold mb-2 small text-primary">Distribution Split</h6><div class="list-group list-group-flush border rounded-3 bg-white">',
            count = 0;
            
        document.querySelectorAll('.grn-cb:checked').forEach(cb => {
            const amt = Math.min(rem, parseFloat(cb.dataset.bal));
            if (amt > 0) {
                html += `<div class="list-group-item d-flex justify-content-between py-2 small"><span>${cb.dataset.num}</span><span class="fw-bold text-dark">₹${amt.toFixed(2)}</span></div>`;
                rem -= amt;
                count++;
            }
        });
        html += '</div>';
        splitPrev.innerHTML = html;
        splitPrev.classList.toggle('d-none', !count);
    }

    q('#purpose').onchange = async () => {
        const p = q('#purpose').value;
        q('#custom_purpose_wrap').classList.toggle('d-none', p !== '__other__');
        if (p && p !== '__other__') {
            const j = await fetch(`?ajax=expense_summary&purpose=${encodeURIComponent(p)}`).then(r => r.json());
            q('#total_amount').value = j.total;
            q('#paid_so_far').value = j.paid;
            q('#view_balance').value = j.balance;
        }
        updateSplits();
        liveDupCheck();
    };

    q('#fixed_id').onfocus = async () => {
        if (q('#fixed_id').options.length > 1) return;
        const data = await fetch('?ajax=fixed_list').then(r => r.json());
        data.forEach(r => q('#fixed_id').add(new Option(`${r.expense_type.toUpperCase()} (₹${r.remaining_balance})`, r.id)));
    };

    q('#fixed_id').onchange = async () => {
        if (!q('#fixed_id').value) return;
        const j = await fetch('?ajax=fixed_one&id=' + q('#fixed_id').value).then(r => r.json());
        q('#fixed_meta').value = `${j.frequency} | Due Day ${j.due_day} | ${j.notes}`;
        q('#total_amount').value = j.total;
        q('#paid_so_far').value = j.paid;
        q('#view_balance').value = j.balance;
        updateSplits();
        liveDupCheck();
    };

    vSel.onchange = bSel.onchange = refreshVendor;
    q('#pay_now').oninput = () => {
        updateSplits();
        liveDupCheck();
    };
    document.addEventListener('change', e => {
        if (e.target.classList.contains('grn-cb')) recalcVendor();
    });
</script>
<?php require_once("footer.php"); ?>