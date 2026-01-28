<?php

/**
 * CASHIER: Payments View (Unified Report)
 * FIXED: Explicitly aliased p.id as record_id to avoid collision with master table IDs.
 * UPDATED: Added Redemption Used and Salary Breakdown details.
 */

ob_start();
require_once("../auth.php");
requireRole(['Cashier', 'Admin']);
require_once(__DIR__ . '/../functions.php');

if (!function_exists('h')) {
    function h($v)
    {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

function fetch_unified_disbursals(array $f): array
{
    $type      = $f['type'] ?? '';
    $vendor_id = (int)($f['vendor_id'] ?? 0);
    $emp_id    = (int)($f['employee_id'] ?? 0);
    $branch_id = (int)($f['branch_id'] ?? 0);
    $from      = ($f['fromDt'] && strpos($f['fromDt'], 'undefined') === false) ? $f['fromDt'] : null;
    $to        = ($f['toDt'] && strpos($f['toDt'], 'undefined') === false) ? $f['toDt'] : null;

    $combined = [];

    // --- A. Vendor Disbursements ---
    if ($type === '' || $type === 'vendor') {
        $w = ["1=1"];
        if ($vendor_id) $w[] = "p.vendor_id = $vendor_id";
        if ($from) $w[] = "p.paid_at >= '$from'";
        if ($to)   $w[] = "p.paid_at <= '$to'";
        if ($branch_id) $w[] = "p.branch_id = $branch_id";

        // Aliasing p.id as record_id to avoid master table collision
        $sql = "SELECT p.*, p.id AS record_id, v.vendor_name, v.account_number, v.ifsc, b.branch_name, g.grn_number
                FROM vendor_grn_payments p
                LEFT JOIN vendors v ON v.vendor_id = p.vendor_id
                LEFT JOIN branches b ON b.branch_id = p.branch_id
                LEFT JOIN goods_receipts g ON g.grn_id = p.grn_id
                WHERE " . implode(' AND ', $w);
        
        $rows = @exeSql($sql) ?: [];
        foreach ($rows as $r) {
            $combined[] = [
                'id'         => (int)$r['record_id'],
                'source'     => 'vendor',
                'paid_at'    => $r['paid_at'],
                'party'      => $r['vendor_name'] ?? ('Vendor #' . $r['vendor_id']),
                'details'    => "GRN: " . ($r['grn_number'] ?: 'N/A'),
                'ac_no'      => $r['account_number'] ?? '',
                'ifsc'       => $r['ifsc'] ?? '',
                'mode'       => strtoupper($r['method'] ?? ''),
                'net'        => (float)($r['amount'] ?? 0),
                'adv'        => (float)($r['advance_used'] ?? 0),
                'red'        => (float)($r['redemption_used'] ?? 0),
                'total'      => (float)($r['amount'] ?? 0) + (float)($r['advance_used'] ?? 0) + (float)($r['redemption_used'] ?? 0),
                'voucher_no' => $r['voucher_no'] ?? '',
                'invoice_no' => $r['invoice_no'] ?? ''
            ];
        }
    }

    // --- B. Employee Salary ---
    if ($type === '' || $type === 'employee') {
        $w = ["1=1"];
        if ($emp_id) $w[] = "p.employee_id = $emp_id";
        if ($from) $w[] = "p.paid_at >= '$from'";
        if ($to)   $w[] = "p.paid_at <= '$to'";
        if ($branch_id) $w[] = "e.branch_id = $branch_id";

        $sql = "SELECT p.*, p.id AS record_id, e.employee_name, e.bank_name, e.ifsc_code, b.branch_name 
                FROM employee_salary_payments p
                LEFT JOIN employees e ON e.id = p.employee_id
                LEFT JOIN branches b ON b.branch_id = e.branch_id
                WHERE " . implode(' AND ', $w);

        $rows = @exeSql($sql) ?: [];
        foreach ($rows as $r) {
            $combined[] = [
                'id'         => (int)$r['record_id'],
                'source'     => 'employee',
                'paid_at'    => $r['paid_at'],
                'party'      => $r['employee_name'] ?? ('Emp #' . $r['employee_id']),
                'details'    => "Salary Period: " . ($r['pay_period'] ?? 'N/A'),
                'ac_no'      => $r['bank_name'] ?? '',
                'ifsc'       => $r['ifsc_code'] ?? '',
                'mode'       => 'SALARY',
                'net'        => (float)($r['amount'] ?? 0),
                'adv'        => (float)($r['advance'] ?? 0),
                'red'        => 0.00,
                'total'      => (float)($r['amount'] ?? 0) + (float)($r['advance'] ?? 0),
                'voucher_no' => $r['voucher_no'] ?? '',
                'invoice_no' => $r['invoice_no'] ?? ''
            ];
        }
    }

    // --- C. Office Expenses ---
    if ($type === '' || $type === 'expense') {
        $w = ["1=1"];
        if ($from) $w[] = "e.paid_at >= '$from'";
        if ($to)   $w[] = "e.paid_at <= '$to'";

        $sql = "SELECT e.*, e.id AS record_id FROM expenses e WHERE " . implode(' AND ', $w);

        $rows = @exeSql($sql) ?: [];
        foreach ($rows as $r) {
            $combined[] = [
                'id'         => (int)$r['record_id'],
                'source'     => 'expense',
                'paid_at'    => $r['paid_at'],
                'party'      => $r['purpose'] ?? 'General Expense',
                'details'    => $r['remark'] ?? 'N/A',
                'ac_no'      => $r['account_no'] ?? '',
                'ifsc'       => $r['ifsc_code'] ?? '',
                'mode'       => strtoupper($r['method'] ?? ''),
                'net'        => (float)($r['amount'] ?? 0),
                'adv'        => (float)($r['advance'] ?? 0),
                'red'        => 0.00,
                'total'      => (float)($r['amount'] ?? 0) + (float)($r['advance'] ?? 0),
                'voucher_no' => $r['voucher_no'] ?? '',
                'invoice_no' => $r['invoice_no'] ?? ''
            ];
        }
    }

    usort($combined, function ($a, $b) {
        return strtotime($b['paid_at']) <=> strtotime($a['paid_at']);
    });

    return array_slice($combined, 0, 2000);
}

if (isset($_GET['ajax'])) {
    try {
        $act = $_GET['ajax'];
        $f = [
            'type' => $_GET['type'] ?? '',
            'vendor_id' => (int)($_GET['vendor_id'] ?? 0),
            'employee_id' => (int)($_GET['employee_id'] ?? 0),
            'branch_id' => (int)($_GET['branch_id'] ?? 0),
            'fromDt' => ($_GET['from'] && $_GET['from'] !== 'undefined') ? $_GET['from'] . " 00:00:00" : null,
            'toDt' => ($_GET['to'] && $_GET['to'] !== 'undefined') ? $_GET['to'] . " 23:59:59" : null
        ];

        $data = fetch_unified_disbursals($f);
        while (ob_get_level()) {
            ob_end_clean();
        }

        if ($act === 'list') {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['data' => $data]);
            exit;
        }
    } catch (Exception $e) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode(['data' => [], 'error' => $e->getMessage()]);
        exit;
    }
}

ob_end_flush();
require_once("header.php");
require_once("nav.php");

$vendors   = exeSql("SELECT vendor_id, vendor_name FROM vendors ORDER BY vendor_name") ?: [];
$employees = exeSql("SELECT id, employee_name FROM employees ORDER BY employee_name") ?: [];
$branches  = exeSql("SELECT branch_id, branch_name FROM branches ORDER BY branch_name") ?: [];
?>

<style>
    .filter-card {
        background: #fff;
        border: 1px solid #eef2f3;
        border-radius: 16px;
    }

    .table-container {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #eef2f3;
        padding: 20px;
    }

    #tbl thead th {
        background-color: #f8fafb;
        color: #6c757d;
        font-size: 0.7rem;
        text-transform: uppercase;
        font-weight: 700;
        border: none;
        padding: 12px;
    }

    #tbl tbody td {
        font-size: 0.8rem;
        padding: 12px;
        border-bottom: 1px solid #f1f3f5;
    }

    .badge-source {
        font-size: 0.6rem;
        padding: 4px 8px;
        border-radius: 30px;
        text-transform: uppercase;
        font-weight: 700;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #0d6efd !important;
        color: white !important;
        border-radius: 50%;
        border: none !important;
    }

    .select2-container--default .select2-selection--single {
        height: 42px !important;
        border: 2px solid #eef2f3 !important;
        border-radius: 10px !important;
        padding-top: 6px !important;
        background-color: #fff !important;
    }
</style>

<div class="container-fluid px-4 mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <div>
            <h2 class="fw-bold h4 mb-0 text-dark">Unified Payment Ledger</h2>
            <p class="text-muted small mb-0">Consolidated history for Vendors, Employees, and Expenses.</p>
        </div>
        <a href="dashboard" class="btn btn-sm btn-outline-secondary rounded-pill px-4 fw-bold">Back to Desk</a>
    </div>

    <!-- Filters -->
    <div class="card filter-card shadow-sm mb-4 border-0">
        <div class="card-body p-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-muted">Category</label>
                    <select id="f_type" class="form-select select2">
                        <option value="">All Categories</option>
                        <option value="vendor">Vendor Payouts</option>
                        <option value="employee">Salary Payouts</option>
                        <option value="expense">Office Expenses</option>
                    </select>
                </div>
                <div class="col-md-2" id="vendor_wrap">
                    <label class="form-label fw-bold small text-muted">Vendor</label>
                    <select id="f_vendor" class="form-select select2">
                        <option value="">All Vendors</option>
                        <?php foreach ($vendors as $v): ?><option value="<?= $v['vendor_id'] ?>"><?= h($v['vendor_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2" id="employee_wrap" style="display:none;">
                    <label class="form-label fw-bold small text-muted">Employee</label>
                    <select id="f_employee" class="form-select select2">
                        <option value="">All Employees</option>
                        <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= h($e['employee_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-muted">From Date</label>
                    <input type="date" id="f_from" class="form-control" value="<?= date('Y-m-d', strtotime('-30 days')) ?>" style="height:42px; border:2px solid #eef2f3; border-radius:10px;">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-muted">To Date</label>
                    <input type="date" id="f_to" class="form-control" value="<?= date('Y-m-d') ?>" style="height:42px; border:2px solid #eef2f3; border-radius:10px;">
                </div>
                <div class="col-md-2">
                    <button id="btn_search" class="btn btn-primary w-100 py-2 rounded-pill fw-bold shadow-sm" style="height:42px;"><i class="bi bi-search me-1"></i> Search</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="table-container shadow-sm mb-5">
        <div class="table-responsive">
            <table id="tbl" class="table table-hover align-middle mb-0 display" style="width:100%">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Date</th>
                        <th>Payee / Details</th>
                        <th>A/C info</th>
                        <th class="text-end">Cash Paid</th>
                        <th class="text-end">Adv Used</th>
                        <th class="text-end">Redemp.</th>
                        <th class="text-end">Total Val</th>
                        <th class="text-end no-sort">Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        let dt = null;
        $('.select2').select2({
            width: '100%'
        });

        function loadTable() {
            const p = new URLSearchParams({
                ajax: 'list',
                type: $('#f_type').val(),
                vendor_id: $('#f_vendor').val(),
                employee_id: $('#f_employee').val(),
                from: $('#f_from').val(),
                to: $('#f_to').val()
            });

            if (dt) dt.destroy();

            dt = $('#tbl').DataTable({
                ajax: {
                    url: '?' + p.toString(),
                    dataSrc: 'data'
                },
                columns: [{
                        data: 'source',
                        render: d => {
                            const c = d === 'vendor' ? 'danger' : (d === 'employee' ? 'primary' : 'secondary');
                            return `<span class="badge badge-source bg-${c}-subtle text-${c} border border-${c}-subtle">${d}</span>`;
                        }
                    },
                    {
                        data: 'paid_at',
                        render: d => d ? d.split(' ')[0] : '-'
                    },
                    {
                        data: null,
                        render: r => `<strong>${r.party}</strong><br><small class="text-muted">${r.details}</small>`
                    },
                    {
                        data: null,
                        render: r => `<small class="text-muted">${r.ac_no || '-'}<br>${r.ifsc || '-'}</small>`
                    },
                    {
                        data: 'net',
                        className: 'text-end fw-bold',
                        render: d => '₹' + parseFloat(d || 0).toLocaleString('en-IN', {
                            minimumFractionDigits: 2
                        })
                    },
                    {
                        data: 'adv',
                        className: 'text-end text-danger',
                        render: d => '₹' + parseFloat(d || 0).toLocaleString('en-IN', {
                            minimumFractionDigits: 2
                        })
                    },
                    {
                        data: 'red',
                        className: 'text-end text-success',
                        render: d => '₹' + parseFloat(d || 0).toLocaleString('en-IN', {
                            minimumFractionDigits: 2
                        })
                    },
                    {
                        data: 'total',
                        className: 'text-end fw-bold text-dark',
                        render: d => '₹' + parseFloat(d || 0).toLocaleString('en-IN', {
                            minimumFractionDigits: 2
                        })
                    },
                    {
                        data: null,
                        className: 'text-end',
                        orderable: false,
                        render: r => {
                            // CRITICAL: Passing all identifiers to prevent "Record Not Found"
                            return `<a href="print_voucher?id=${r.id}&type=${r.source}&voucher=${r.voucher_no}&invoice=${r.invoice_no}" target="_blank" class="btn btn-sm btn-light border rounded-pill px-3 fw-bold">Print</a>`;
                        }
                    }
                ],
                order: [
                    [1, 'desc']
                ],
                pageLength: 25,
                dom: '<"d-flex justify-content-between align-items-center mb-3"f>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
                language: {
                    search: "",
                    searchPlaceholder: "Search report..."
                }
            });
        }

        $('#f_type').on('change', function() {
            const v = $(this).val();
            $('#vendor_wrap').toggle(v === '' || v === 'vendor');
            $('#employee_wrap').toggle(v === 'employee');
        });

        $('#btn_search').on('click', loadTable);
        loadTable();
    });
</script>

<?php require_once("footer.php"); ?>