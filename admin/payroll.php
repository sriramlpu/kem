<?php

declare(strict_types=1);

/**
 * 1. INITIALIZE DATABASE & UTILITIES
 */
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
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

/**
 * 2. AJAX ROUTER
 */
if (isset($_GET['ajax'])) {
    $act = $_GET['ajax'];

    if ($act === 'employees_bulk') {
        $branch_id = i(v('branch_id'));
        $period = s(v('pay_period'));

        // FIXED: Added pr.request_type = 'employee' to the JOIN condition.
        // This prevents independent 'advance' requests from blocking the monthly 'employee' salary run.
        $sql = "SELECT 
                    e.id, 
                    e.employee_name, 
                    e.role, 
                    e.salary,
                    e.professional_tax,
                    e.pf_percent,
                    e.esi_percent,
                    pr.status AS pr_status,
                    pr.request_id AS pr_id,
                    (SELECT SUM(amount - IFNULL(recovered_amount, 0)) FROM advances WHERE entity_id = e.id AND entity_type = 'employee' AND status = 'Active') AS adv_bal
                FROM employees e 
                LEFT JOIN payment_requests pr ON (
                    pr.employee_id = e.id 
                    AND pr.request_type = 'employee'
                    AND pr.status IN ('SUBMITTED','APPROVED','READY_FOR_CASHIER','PAID')
                    AND pr.payload_json LIKE '%\"pay_period\":\"$period\"%'
                )
                WHERE e.branch_id = $branch_id AND e.status = 'Active' 
                ORDER BY e.employee_name";

        $rows = exeSql($sql);
        json_out($rows ?: []);
    }

    if ($act === 'employee_info') {
        $eid = i(v('employee_id'));
        $emp = exeSql("SELECT role, salary, pf_percent, esi_percent, professional_tax FROM employees WHERE id=$eid LIMIT 1");
        $adv = exeSql("SELECT SUM(amount - IFNULL(recovered_amount, 0)) as bal FROM advances WHERE entity_id=$eid AND entity_type='employee' AND status='Active'");

        json_out([
            'role' => $emp[0]['role'] ?? 'N/A',
            'salary' => (float)($emp[0]['salary'] ?? 0),
            'pt' => (float)($emp[0]['professional_tax'] ?? 0),
            'pf_percent' => (float)($emp[0]['pf_percent'] ?? 12.00),
            'esi_percent' => (float)($emp[0]['esi_percent'] ?? 0.75),
            'advance_balance' => (float)($adv[0]['bal'] ?? 0)
        ]);
    }
    exit;
}

/**
 * 3. SUBMIT LOGIC
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['entry_mode'] ?? 'bulk';
    $branch_id = i(v('branch_id'));
    $userId = (int)($_SESSION['userId'] ?? 1);

    if ($mode === 'advance') {
        $eid = i(v('employee_id'));
        $amt = (float)s(v('pay_now', '0'));
        $payload = $_POST;

        insData('payment_requests', [
            'request_type' => 'advance',
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'requested_by' => $userId,
            'employee_id'  => $eid,
            'branch_id'    => $branch_id,
            'total_amount' => $amt,
            'status'       => 'SUBMITTED'
        ]);
        header("Location: dashboard.php?msg=advance_success");
        exit;
    } else {
        $period = v('pay_period', date('Ym'));
        if (isset($_POST['emp_ids']) && is_array($_POST['emp_ids'])) {
            foreach ($_POST['emp_ids'] as $eid) {
                $eid = (int)$eid;
                $net = (float)($_POST['pay_now_bulk'][$eid] ?? 0);
                if ($net <= 0) continue;

                $payload = [
                    'pay_for' => 'employee',
                    'employee_id' => $eid,
                    'branch_id' => $branch_id,
                    'pay_period' => $period,
                    'gross_salary' => (float)$_POST['gross_val'][$eid],
                    'lop_days' => (float)$_POST['lop_days'][$eid],
                    'lop_amount' => (float)$_POST['lop_amount'][$eid],
                    'incentives' => (float)$_POST['incentives'][$eid],
                    'ot_amount' => (float)$_POST['ot_amount'][$eid],
                    'pf_deduction' => (float)$_POST['pf_deduction'][$eid],
                    'esi_deduction' => (float)$_POST['esi_deduction'][$eid],
                    'tax_deduction' => (float)$_POST['tax_deduction'][$eid],
                    'tds_deduction' => (float)$_POST['tds_deduction'][$eid],
                    'pay_now' => $net
                ];

                insData('payment_requests', [
                    'request_type' => 'employee',
                    'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    'requested_by' => $userId,
                    'employee_id' => $eid,
                    'branch_id' => $branch_id,
                    'total_amount' => $net,
                    'status' => 'SUBMITTED'
                ]);
            }
            header("Location: dashboard.php?msg=bulk_success");
            exit;
        }
    }
}

require_once("./header.php");
require_once("./nav.php");
$branches = exeSql("SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
?>
<style>
    body {
        background-color: #f8fafc;
        font-family: 'Inter', sans-serif;
    }

    .table thead th {
        background: #f8fafc;
        color: #1e293b;
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
        border-bottom: 2px solid #e2e8f0;
        padding: 15px 10px;
    }

    .emp-row td {
        padding: 12px 10px;
        border-bottom: 1px solid #f1f5f9;
    }

    .sticky-identity {
        position: sticky;
        left: 0;
        background: white;
        z-index: 5;
        border-right: 2px solid #f1f5f9;
    }

    .badge-ready {
        background: #dcfce7;
        color: #15803d;
        border: 1px solid #bbf7d0;
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 0.65rem;
    }

    .text-danger-bold {
        color: #dc3545 !important;
        font-weight: 800;
    }

    .text-success-bold {
        color: #198754 !important;
        font-weight: 800;
        font-size: 1.15rem;
    }

    .text-pf {
        color: #856404;
        font-weight: 700;
    }

    .text-esi {
        color: #055160;
        font-weight: 700;
    }

    .form-control-sm {
        border: 1px solid #cbd5e1;
        border-radius: 6px;
    }

    .small-details {
        font-size: 0.7rem;
        color: #64748b;
        font-weight: 600;
    }
</style>

<div class="container-fluid py-4 bg-light min-vh-100">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
            <div>
                <h1 class="h3 mb-0 text-dark fw-bold">Simplified Payroll Desk</h1>
                <p class="text-muted small mb-0">Manage payouts with direct deductions.</p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-secondary rounded-pill px-4 shadow-sm fw-bold">⬅ Dashboard</a>
        </div>

        <!-- Mode Switcher -->
        <div class="mb-5 d-flex justify-content-center">
            <div class="btn-group p-1 bg-white shadow-sm rounded-pill border" role="group">
                <input type="radio" class="btn-check" name="mode_toggle" id="mode_bulk" checked onclick="switchMode('bulk')">
                <label class="btn btn-outline-primary border-0 rounded-pill px-4 fw-bold" for="mode_bulk">Bulk Payroll</label>
                <input type="radio" class="btn-check" name="mode_toggle" id="mode_advance" onclick="switchMode('advance')">
                <label class="btn btn-outline-primary border-0 rounded-pill px-4 fw-bold" for="mode_advance">Salary Advance</label>
            </div>
        </div>

        <form method="post" id="payrollForm">
            <input type="hidden" name="entry_mode" id="entry_mode" value="bulk">

            <div class="card mb-4 rounded-4 shadow-sm">
                <div class="card-body p-4 bg-white text-center">
                    <div class="row g-4 justify-content-center">
                        <div class="col-md-3 text-start">
                            <label class="form-label fw-bold small text-muted">Branch</label>
                            <select name="branch_id" id="branch_id" class="form-select border-2 fw-bold" required>
                                <option value="">-- Choose Branch --</option>
                                <?php foreach ($branches as $b): ?><option value="<?= $b['branch_id'] ?>"><?= h($b['branch_name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div id="bulk_settings" class="col-md-8">
                            <div class="row g-3">
                                <div class="col-md-4 text-start">
                                    <label class="form-label fw-bold small text-muted">Pay Period (YYYYMM)</label>
                                    <input type="text" name="pay_period" id="pay_period" class="form-control border-2 text-center fw-bold" value="<?= date('Ym') ?>" maxlength="6">
                                </div>
                                <div class="col-md-8 text-start">
                                    <label class="form-label fw-bold small text-muted">Search Employee</label>
                                    <input type="text" id="emp_search" class="form-control border-2" placeholder="Quick find names...">
                                </div>
                            </div>
                        </div>
                        <div id="advance_settings" class="col-md-8 d-none">
                            <label class="form-label fw-bold small text-muted">Select Employee</label>
                            <select name="employee_id" id="adv_employee_id" class="form-select border-2">
                                <option value="">-- Select --</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div id="bulk_container" class="card shadow-sm border-0 d-none rounded-4 overflow-hidden mb-5">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="payrollTable">
                        <thead>
                            <tr>
                                <th class="ps-4">Status</th>
                                <th><input type="checkbox" id="master_check" class="form-check-input"></th>
                                <th class="sticky-identity bg-white" style="min-width:180px;">Employee</th>
                                <th>Gross</th>
                                <th style="width:75px;">LOP Days</th>
                                <th>LOP Amt</th>
                                <th style="width:85px;">OT Amt</th>
                                <th style="width:85px;">Incentive</th>
                                <th style="min-width:140px;">PF / ESI</th>
                                <th>PT</th>
                                <th>TDS</th>
                                <th class="text-end pe-4 text-success-bold">Net Payout</th>
                            </tr>
                        </thead>
                        <tbody id="bulk_body"></tbody>
                    </table>
                </div>
                <div class="card-footer bg-white p-4 border-0 border-top">
                    <div class="d-flex justify-content-between align-items-center">
                        <div id="status_msg" class="fw-bold text-primary small">Selected: 0 Employees</div>
                        <button type="submit" id="submitBtn" class="btn btn-primary px-5 py-2 fw-bold rounded-pill shadow-lg" disabled>Raise Payroll Requests</button>
                    </div>
                </div>
            </div>

            <!-- Individual Advance Panel -->
            <div id="advance_container" class="d-none">
                <div class="row">
                    <div class="col-md-5 mx-auto">
                        <div class="card shadow-lg border-0 rounded-4 text-center">
                            <div class="card-header bg-warning text-dark py-3 px-4 fw-bold">SALARY ADVANCE REQUEST</div>
                            <div class="card-body p-4">
                                <div class="row g-3 mb-4">
                                    <div class="col-6 border-end"><label class="small text-muted d-block fw-bold">GROSS</label><span id="adv_info_salary" class="fw-bold h5">₹0.00</span></div>
                                    <div class="col-6"><label class="small text-muted d-block fw-bold">O/S ADVANCE</label><span id="adv_info_outstanding" class="fw-bold h5 text-danger">₹0.00</span></div>
                                </div>
                                <div class="mb-3"><label class="form-label fw-bold">Requested Amount</label><input type="number" step="0.01" name="pay_now" class="form-control form-control-lg border-primary fw-bold text-center" placeholder="0.00"></div>
                                <div class="mb-4 text-start"><label class="form-label fw-bold">Reason</label><textarea name="notes" class="form-control" rows="2"></textarea></div><button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow">Raise Advance Request</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    const q = s => document.querySelector(s);
    const bSel = q('#branch_id'),
        pSel = q('#pay_period'),
        bulkBody = q('#bulk_body'),
        submitBtn = q('#submitBtn');

    function switchMode(mode) {
        q('#entry_mode').value = mode;
        if (mode === 'bulk') {
            q('#bulk_settings').classList.remove('d-none');
            q('#advance_settings').classList.add('d-none');
            q('#advance_container').classList.add('d-none');
            bSel.onchange();
        } else {
            q('#bulk_settings').classList.add('d-none');
            q('#bulk_container').classList.add('d-none');
            q('#advance_settings').classList.remove('d-none');
            q('#advance_container').classList.remove('d-none');
            refreshAdvanceEmployees();
        }
    }

    async function refreshAdvanceEmployees() {
        if (!bSel.value) return;
        const res = await fetch(`?ajax=employees_bulk&branch_id=${bSel.value}&pay_period=${pSel.value}`).then(r => r.json());
        q('#adv_employee_id').innerHTML = '<option value=\"\">-- Select --</option>' + res.map(e => `<option value=\"${e.id}\">${e.employee_name} (${e.role})</option>`).join('');
    }

    q('#adv_employee_id').onchange = async () => {
        if (!q('#adv_employee_id').value) return;
        const data = await fetch(`?ajax=employee_info&employee_id=${q('#adv_employee_id').value}`).then(r => r.json());
        q('#adv_info_salary').textContent = '₹' + parseFloat(data.salary).toLocaleString('en-IN');
        q('#adv_info_outstanding').textContent = '₹' + parseFloat(data.advance_balance).toLocaleString('en-IN');
    };

    bSel.onchange = pSel.onchange = async () => {
        if (!bSel.value || !pSel.value) {
            q('#bulk_container').classList.add('d-none');
            return;
        }
        if (q('#entry_mode').value === 'advance') {
            refreshAdvanceEmployees();
            return;
        }

        bulkBody.innerHTML = '<tr><td colspan=\"12\" class=\"text-center p-5\"><div class=\"spinner-border text-primary\"></div></td></tr>';
        q('#bulk_container').classList.remove('d-none');

        const data = await fetch(`?ajax=employees_bulk&branch_id=${bSel.value}&pay_period=${pSel.value}`).then(r => r.json());
        bulkBody.innerHTML = '';

        data.forEach(e => {
            const gross = parseFloat(e.salary || 0);
            const pt_master = parseFloat(e.professional_tax || 0);
            const advBal = parseFloat(e.adv_bal || 0);
            const hasRequest = e.pr_status !== null;

            const tr = document.createElement('tr');
            tr.className = 'emp-row' + (hasRequest ? ' table-light opacity-50' : '');
            tr.dataset.id = e.id;
            tr.dataset.search = (e.employee_name + ' ' + e.role).toLowerCase();
            tr.dataset.pfPct = e.pf_percent || 12;
            tr.dataset.esiPct = e.esi_percent || 0.75;

            tr.innerHTML = `
                <td class=\"ps-4\">${hasRequest ? `<span class=\"badge bg-warning text-dark border small\">${e.pr_status}</span>` : `<span class=\"badge-ready\">Ready</span>`}</td>
                <td><input type=\"checkbox\" name=\"emp_ids[]\" value=\"${e.id}\" class=\"row-check form-check-input\" ${hasRequest ? 'disabled' : ''}></td>
                <td class=\"sticky-identity\">
                    <div class=\"fw-bold text-dark text-uppercase\" style=\"font-size:0.85rem;\">${e.employee_name}</div>
                    <div class=\"small-details text-uppercase\">${e.role}</div>
                    ${advBal > 0 ? `<div class=\"badge bg-danger-subtle text-danger small mt-1\">Adv: ₹${advBal.toLocaleString()}</div>` : ''}
                </td>
                <td class=\"fw-bold\">₹${gross.toLocaleString('en-IN')}<input type=\"hidden\" name=\"gross_val[${e.id}]\" value=\"${gross}\"></td>
                <td><input type=\"number\" name=\"lop_days[${e.id}]\" value=\"0\" step=\"0.5\" class=\"form-control form-control-sm lop-days\" style=\"width:60px\" ${hasRequest ? 'disabled' : ''}></td>
                <td class=\"text-danger-bold\"><span class=\"lop-amt-display\">0</span><input type=\"hidden\" name=\"lop_amount[${e.id}]\" class=\"lop-amt-hidden\"></td>
                <td><input type=\"number\" name=\"ot_amount[${e.id}]\" value=\"0\" class=\"form-control form-control-sm ot-amt\" style=\"width:75px\" ${hasRequest ? 'disabled' : ''}></td>
                <td><input type=\"number\" name=\"incentives[${e.id}]\" value=\"0\" class=\"form-control form-control-sm incentives\" style=\"width:75px\" ${hasRequest ? 'disabled' : ''}></td>
                <td>
                    <div class=\"small-details text-pf\">PF: ₹<span class=\"pf-display\">0</span></div>
                    <div class=\"small-details mt-1 text-esi\">ESI: ₹<span class=\"esi-display\">0</span></div>
                    <input type=\"hidden\" name=\"pf_deduction[${e.id}]\" class=\"pf-hidden\">
                    <input type=\"hidden\" name=\"esi_deduction[${e.id}]\" class=\"esi-hidden\">
                </td>
                <td><input type=\"number\" name=\"tax_deduction[${e.id}]\" value=\"${pt_master}\" class=\"form-control form-control-sm tax\" style=\"width:65px\" ${hasRequest ? 'disabled' : ''}></td>
                <td><input type=\"number\" name=\"tds_deduction[${e.id}]\" value=\"0\" class=\"form-control form-control-sm tds-val\" style=\"width:65px\" ${hasRequest ? 'disabled' : ''}></td>
                <td class=\"text-end pe-4 text-success-bold\">₹ <span class=\"net-display\">0.00</span><input type=\"hidden\" name=\"pay_now_bulk[${e.id}]\" class=\"net-hidden\"></td>
            `;
            bulkBody.appendChild(tr);
            calculateRow(e.id);
        });
        updateSelection();
    };

    function calculateRow(eid) {
        const row = document.querySelector(`.emp-row[data-id=\"${eid}\"]`);
        if (!row) return;

        const mGross = parseFloat(row.querySelector('input[name^=\"gross_val\"]').value || 0);
        const lopDays = parseFloat(row.querySelector('.lop-days').value || 0);
        const ot = parseFloat(row.querySelector('.ot-amt').value || 0);
        const inc = parseFloat(row.querySelector('.incentives').value || 0);
        const pt = parseFloat(row.querySelector('.tax').value || 0);
        const tds = parseFloat(row.querySelector('.tds-val').value || 0);

        const pfPct = parseFloat(row.dataset.pfPct || 12);
        const esiPct = parseFloat(row.dataset.esiPct || 0.75);

        const periodStr = q('#pay_period').value;
        let dim = 30;
        if (periodStr.length === 6) dim = new Date(parseInt(periodStr.substring(0, 4)), parseInt(periodStr.substring(4, 6)), 0).getDate();

        const lopAmt = Math.round((mGross / dim) * lopDays);
        row.querySelector('.lop-amt-display').textContent = lopAmt.toLocaleString('en-IN');
        row.querySelector('.lop-amt-hidden').value = lopAmt.toFixed(2);

        const earnedGross = mGross - lopAmt;
        const pf = Math.round(earnedGross * (pfPct / 100));
        row.querySelector('.pf-display').textContent = pf.toLocaleString('en-IN');
        row.querySelector('.pf-hidden').value = pf;

        const totalEarned = earnedGross + ot + inc;
        const esi = (totalEarned > 21000) ? 0 : Math.ceil(totalEarned * (esiPct / 100));
        row.querySelector('.esi-display').textContent = esi.toLocaleString('en-IN');
        row.querySelector('.esi-hidden').value = esi;

        const net = Math.max(0, totalEarned - (pf + esi + pt + tds));
        row.querySelector('.net-display').textContent = net.toLocaleString('en-IN', {
            minimumFractionDigits: 2
        });
        row.querySelector('.net-hidden').value = net.toFixed(2);
    }

    bulkBody.addEventListener('input', e => {
        if (e.target.closest('.emp-row')) calculateRow(e.target.closest('.emp-row').dataset.id);
    });
    q('#master_check').onchange = () => {
        document.querySelectorAll('.row-check:not(:disabled)').forEach(c => {
            c.checked = q('#master_check').checked;
            calculateRow(c.value);
        });
        updateSelection();
    };
    bulkBody.addEventListener('change', () => updateSelection());
    q('#emp_search').oninput = () => {
        const v = q('#emp_search').value.toLowerCase();
        document.querySelectorAll('.emp-row').forEach(r => r.style.display = r.dataset.search.includes(v) ? '' : 'none');
    };

    function updateSelection() {
        const count = document.querySelectorAll('.row-check:checked').length;
        q('#status_msg').textContent = `Selected: ${count} Employees`;
        submitBtn.disabled = count === 0;
    }
</script>
<?php require_once("footer.php"); ?>