<?php
// finance/events.php
session_start();
require_once __DIR__ . '/../functions.php';

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function nf($n){ return number_format((float)$n, 2, '.', ''); }

// Pull events newest first
$events = exeSql("
    SELECT event_id, event_name, venue_location, mobile_number, email, created_at
    FROM events
    ORDER BY event_id DESC
") ?: [];

// Extract and sort unique event names for the dropdown filter
$event_names = array_unique(array_column($events, 'event_name'));
sort($event_names);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Events</title>

<!-- Bootstrap + DataTables -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.10/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
<link href="https://kit.fontawesome.com/a076d05399.css" rel="stylesheet">

<style>
body { background:#f6f8fb; }

/* DataTables wrapper */
.dataTables_wrapper .top-bar {
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-bottom:10px;
}

.items-table { width: 100%; font-size: 0.85rem; border-collapse: collapse; }
.items-table th, .items-table td { padding: 4px; border: 1px solid #ddd; text-align: center; white-space: nowrap; }
.items-table tfoot td { font-weight: 700; background: #f8f9fa; }

/* Export Excel / Print Buttons */
#eventsTable_wrapper .dt-buttons .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.65rem;
    font-size: 0.875rem;
    font-weight: 500;
    border-radius: 0.375rem;
    border: 1px solid #ddd;
    color: #fff;
    cursor: pointer;
    transition: background 0.2s, color 0.2s;
}

#eventsTable_wrapper .dt-buttons .buttons-excel {
    background-color: #198754;
    border-color: #198754;
}
#eventsTable_wrapper .dt-buttons .buttons-excel:hover {
    background-color: #157347;
    border-color: #157347;
}
#eventsTable_wrapper .dt-buttons .buttons-print {
    background-color: #0dcaf0;
    border-color: #0dcaf0;
}
#eventsTable_wrapper .dt-buttons .buttons-print:hover {
    background-color: #31d2f2;
    border-color: #31d2f2;
}
#eventsTable_wrapper .dt-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 10px;
}
</style>
</head>
<body>
<?php if (file_exists(__DIR__.'/nav.php')) include __DIR__.'/nav.php'; ?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Events</h3>
        <a href="events_manage.php" class="btn btn-primary">Add Event</a>
    </div>

    <div class="card">
        <div class="card-body">
            
            <!-- Filters -->
            <div class="row g-2 mb-3">
                <div class="col-md-3">
                    <select id="filterEventName" class="form-select form-select-sm">
                        <option value="">All Event Names</option>
                        <?php foreach ($event_names as $name): ?>
                        <option value="<?= h($name) ?>"><?= h($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" id="filterVenue" class="form-control form-control-sm" placeholder="Search Venue">
                </div>
                <div class="col-md-2">
                    <input type="text" id="filterMobile" class="form-control form-control-sm" placeholder="Search Mobile">
                </div>
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">Date Range:</span>
                        <input type="date" id="filterStartDate" class="form-control">
                        <input type="date" id="filterEndDate" class="form-control">
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col text-end">
                    <button id="resetFilters" class="btn btn-outline-secondary btn-sm">Reset Filters</button>
                </div>
            </div>

            <div class="table-responsive" style="overflow-x:auto;">
                <table id="eventsTable" class="table table-bordered table-striped align-middle w-100 nowrap">
                    <thead class="table-dark">
                        <tr>
                            <th style="width:60px">S.No</th>
                            <th>Event Name</th>
                            <th>Venue</th>
                            <th>Mobile</th>
                            <th>Email</th>
                            <th>Created At</th>
                            <th>Total Amount</th>
                            <th>Amount Received</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Details (Items)</th>
                            <th style="width:140px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($events)): $s=1; ?>
                        <?php foreach ($events as $e): 
                            $eid = (int)$e['event_id'];
                            $items = $eid ? exeSql("
                                SELECT
                                  COALESCE(item_name, remark) AS item_for,
                                  COALESCE(quantity, 0) AS quantity,
                                  COALESCE(price, 0) AS price,
                                  COALESCE(total_amount, COALESCE(quantity,0) * COALESCE(price,0)) AS total_amount,
                                  COALESCE(amount_received, 0) AS amount_received,
                                  COALESCE(balance,
                                    COALESCE(total_amount, COALESCE(quantity,0)*COALESCE(price,0))
                                    - COALESCE(amount_received,0)) AS balance
                                FROM event_items
                                WHERE event_id = {$eid}
                                ORDER BY item_id DESC
                            ") : [];

                            $sum_total = $sum_recv = $sum_bal = 0;
                            foreach ($items as &$it) {
                                $ta = (float)($it['total_amount'] ?? 0);
                                $rcv = (float)($it['amount_received'] ?? 0);
                                $bal = (float)($it['balance'] ?? ($ta - $rcv));
                                $sum_total += $ta; $sum_recv += $rcv; $sum_bal += $bal;
                                $it['total_amount'] = $ta; $it['amount_received'] = $rcv; $it['balance'] = $bal;
                            } unset($it);

                            $statusHtml = ($sum_bal == 0.0)
                                ? '<span class="badge bg-success">Received</span>'
                                : '<span class="badge bg-warning text-dark">Pending</span>';
                        ?>
                        <tr>
                            <td><?= $s++; ?></td>
                            <td><?= h($e['event_name']) ?></td>
                            <td><?= h($e['venue_location']) ?></td>
                            <td><?= h($e['mobile_number']) ?></td>
                            <td><?php if($e['email']): ?><a href="mailto:<?= h($e['email']) ?>"><?= h($e['email']) ?></a><?php endif;?></td>
                            <td><?= h(date("d M Y, h:i A", strtotime($e['created_at']))) ?></td>
                            <td><?= h(nf($sum_total)) ?></td>
                            <td><?= h(nf($sum_recv)) ?></td>
                            <td><?= h(nf($sum_bal)) ?></td>
                            <td><?= $statusHtml ?></td>
                            <td>
                                <div class="table-responsive">
                                    <table class="items-table table table-sm mb-0">
                                        <thead class="table-secondary">
                                            <tr>
                                                <th>Items For</th>
                                                <th>Quantity</th>
                                                <th>Price</th>
                                                <th>Total Amount</th>
                                                <th>Amount Received</th>
                                                <th>Balance</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if($items): foreach($items as $it): 
                                                $itemStatusHtml = ((float)$it['balance'] == 0.0)
                                                    ? '<span class="badge bg-success">Received</span>'
                                                    : '<span class="badge bg-warning text-dark">Pending</span>';
                                            ?>
                                            <tr>
                                                <td><?= h($it['item_for']) ?></td>
                                                <td><?= h($it['quantity']) ?></td>
                                                <td><?= h(nf($it['price'])) ?></td>
                                                <td><?= h(nf($it['total_amount'])) ?></td>
                                                <td><?= h(nf($it['amount_received'])) ?></td>
                                                <td><?= h(nf($it['balance'])) ?></td>
                                                <td><?= $itemStatusHtml ?></td>
                                            </tr>
                                            <?php endforeach; else: ?>
                                            <tr><td colspan="7" class="text-muted">No items</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="3" class="text-end">Totals:</td>
                                                <td><?= h(nf($sum_total)) ?></td>
                                                <td><?= h(nf($sum_recv)) ?></td>
                                                <td><?= h(nf($sum_bal)) ?></td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </td>
                            <td>
                                <a class="btn btn-sm btn-outline-primary" href="events_manage.php?id=<?= $eid ?>">Edit</a>
                                <button class="btn btn-sm btn-outline-danger ms-1 delBtn" data-id="<?= $eid ?>">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="12" class="text-center">No events found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.10/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.10/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<script>
(function(){
    $(function(){
        // Custom date range filter
        $.fn.dataTable.ext.search.push(function(settings, data){
            const startDateStr = $('#filterStartDate').val();
            const endDateStr = $('#filterEndDate').val();
            const dateCell = data[5]; // "Created At" column text
            if(!startDateStr && !endDateStr) return true;

            let minDate = startDateStr ? new Date(startDateStr) : null;
            let maxDate = endDateStr ? new Date(endDateStr) : null;

            // Table cell is like: "04 Oct 2025, 02:15 PM"
            const dateParts = dateCell.split(',')[0].trim(); // "04 Oct 2025"
            const cellDate = new Date(dateParts);

            if(minDate && isNaN(minDate.getTime())) minDate = null;
            if(maxDate && isNaN(maxDate.getTime())) maxDate = null;
            if(isNaN(cellDate.getTime())) return false;

            if(minDate && cellDate < minDate) return false;
            if(maxDate){
                let adj = new Date(maxDate);
                adj.setDate(adj.getDate() + 1); // make end inclusive
                if(cellDate >= adj) return false;
            }
            return true;
        });

        // DataTable
        const dt = $('#eventsTable').DataTable({
            pageLength: 10,
            order: [[1,'asc']],
            dom: '<"top-bar"lBf>rtip',
            buttons: [
                {
                    extend:'excel',
                    text:'<i class="fas fa-file-excel"></i> Export Excel',
                    className:'btn btn-success',
                    // Export S.No..Status (0..9). Skip Details (10) & Actions (11)
                    exportOptions:{ columns: [0,1,2,3,4,5,6,7,8,9] }
                },
                {
                    extend:'print',
                    text:'<i class="fas fa-print"></i> Print',
                    className:'btn btn-info',
                    exportOptions:{ columns: [0,1,2,3,4,5,6,7,8,9] },
                    title: '',
                    customize: function (win) {
                        // Sum helper over printed columns: 6=Total Amount, 7=Amount Received, 8=Balance
                        const api = $('#eventsTable').DataTable();
                        const numeric = (s) => parseFloat(String(s).replace(/<[^>]*>/g,'').replace(/[^\d.\-]/g,'')) || 0;
                        const sumCol = (idx) => api.column(idx, { search:'applied', page:'all' })
                                                  .data().toArray().reduce((a,v)=>a+numeric(v),0);

                        const totalAmount = sumCol(6).toFixed(2);
                        const totalRecv   = sumCol(7).toFixed(2);
                        const totalBal    = sumCol(8).toFixed(2);
                        const nowStr = new Date().toLocaleString();

                        const $doc = $(win.document);

                        // ======== Letterhead at the VERY TOP ========
                        const headerHtml = `
                          <div id="print-brand">
                            <div class="brand-top">
                              <div class="company">KMK GLOBAL LIMITED</div>
                              <img src="../assets/img/logo.jpg" alt="Logo">
                            </div>

                            <div class="top-row">
                              <div class="left"><span class="section-title">EVENT DETAILS</span></div>
                              <div class="right">
                                <div class="totals">
                                  <div class="now">${nowStr}</div>
                                  <div><strong>Total Amount:</strong> ${totalAmount}</div>
                                  <div><strong>Amount Received:</strong> ${totalRecv}</div>
                                  <div><strong>Balance:</strong> ${totalBal}</div>
                                </div>
                              </div>
                            </div>

                            <hr class="rule">
                          </div>
                        `;

                        // ======== Footer (address; edit as needed) ========
                        const footerHtml = `
                          <div id="print-footer">
                            <hr class="rule">
                            71-4-671, Ground Floor, Vijayawada, Andhra Pradesh, 520007, India
                          </div>
                        `;

                        $doc.find('body').prepend(headerHtml);
                        $doc.find('body').append(footerHtml);

                        // ======== Print CSS ========
                        $doc.find('head').append(`
                          <style>
                            @page { margin: 12mm; }
                            body { font-size: 10pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

                            /* Top brand block */
                            #print-brand .brand-top {
                              display: flex; flex-direction: column; align-items: center; margin: 0 0 4px 0;
                            }
                            #print-brand .brand-top .company {
                              font-weight: 900; font-size: 36pt;  /* BIGGER NAME */
                              text-transform: uppercase; letter-spacing: 1px; text-align: center; margin-bottom: 6px;
                            }
                            #print-brand .brand-top img {
                              height: 80px; object-fit: contain;   /* BIGGER LOGO */
                            }

                            /* Title + totals row */
                            #print-brand .top-row {
                              display: flex; justify-content: space-between; align-items: flex-end; gap: 8px; margin-top: 6px;
                            }
                            .section-title { font-weight: 800; font-size: 16pt; letter-spacing: .4px; } /* BIGGER title */
                            .totals { text-align: right; font-size: 10pt; line-height: 1.25; }
                            .totals .now { margin-bottom: 2px; }

                            .rule { border: 1px solid #000; margin: 6px 0 10px; }

                            table.dataTable:first-of-type { margin-top: 2mm; }
                            table.dataTable th, table.dataTable td { white-space: nowrap; }

                            /* Footer fixed at bottom */
                            #print-footer {
                              position: fixed; bottom: 10mm; left: 0; right: 0; text-align: center; font-size: 10pt;
                            }
                          </style>
                        `);

                        // Compact table in print
                        $doc.find('table').addClass('compact').css('font-size','inherit');
                    }
                }
            ],
            scrollX:true
        });

        // Filters
        $('#filterEventName').on('change', function(){ dt.column(1).search(this.value).draw(); });
        $('#filterVenue').on('keyup change clear', function(){ dt.column(2).search(this.value).draw(); });
        $('#filterMobile').on('keyup change clear', function(){ dt.column(3).search(this.value).draw(); });
        $('#filterStartDate,#filterEndDate').on('change', function(){ dt.draw(); });

        // Reset filters
        $('#resetFilters').on('click', function(e){
            e.preventDefault();
            $('#filterEventName').val('');
            $('#filterVenue').val('');
            $('#filterMobile').val('');
            $('#filterStartDate').val('');
            $('#filterEndDate').val('');
            dt.column(1).search('');
            dt.column(2).search('');
            dt.column(3).search('');
            dt.draw();
        });

        // Delete event
        $(document).on('click','.delBtn', function(){
            if(!confirm('Delete this event?')) return;
            const id = $(this).data('id');
            $.post('api/events_api.php',{action:'delete',id},function(res){
                if(res && res.status==='success'){ location.reload(); }
                else{ alert(res.message || 'Delete failed'); }
            },'json').fail(function(){ alert('Network error'); });
        });
    });
})();
</script>
</body>
</html>
