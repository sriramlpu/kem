<?php
/**
 * FINANCE: Events Listing & Financial Summary
 * UPDATED: Integrated logic from events1.php with modern UI.
 * FEATURES: Multi-column filtering, DataTables Export, Financial Summary.
 */
require_once("../auth.php");
requireRole(['Admin', 'Finance']);
require_once("../functions.php");

include 'header.php';
include 'nav.php';

// Pull events with cumulative financial stats
$sql = "SELECT e.*, 
        (SELECT SUM(total_amount) FROM event_items WHERE event_id = e.event_id) as total_bill,
        (SELECT SUM(received_amount) FROM event_items WHERE event_id = e.event_id) as total_received
        FROM events e 
        ORDER BY e.event_id DESC";
$events = exeSql($sql) ?: [];

// Get unique event names for the filter dropdown
$event_names = array_unique(array_column($events, 'event_name'));
sort($event_names);
?>

<div class="container-fluid px-4 mt-2">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <div>
            <h2 class="fw-bold h4 mb-0 text-dark">Event Billing Console</h2>
            <p class="text-muted small mb-0">Overview of hall bookings, realization, and payment balances.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="events_manage.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                <i class="bi bi-calendar-plus me-1"></i> New Event
            </a>
            <button class="btn btn-outline-secondary rounded-pill px-3 fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#filterPanel">
                <i class="bi bi-filter"></i>
            </button>
        </div>
    </div>

    <!-- Collapsible Filters -->
    <div class="collapse" id="filterPanel">
        <div class="card shadow-sm mb-4 border-0 rounded-4">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">EVENT NAME</label>
                        <select id="filterEventName" class="form-select select2">
                            <option value="">All Events</option>
                            <?php foreach($event_names as $name): ?>
                                <option value="<?= h($name) ?>"><?= h($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">VENUE / LOCATION</label>
                        <input type="text" id="filterVenue" class="form-control" placeholder="Search venue...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">MOBILE</label>
                        <input type="text" id="filterMobile" class="form-control" placeholder="Search contact...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">FROM DATE</label>
                        <input type="date" id="filterStartDate" class="form-control">
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button id="resetFilters" class="btn btn-light border w-100 fw-bold">Reset</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Events Table -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="eventsTable" class="table table-hover align-middle mb-0" style="width:100%">
                    <thead>
                        <tr>
                            <th class="ps-4">Date</th>
                            <th>Event / Location</th>
                            <th>Client Contact</th>
                            <th class="text-end">Gross Bill</th>
                            <th class="text-end text-success">Received</th>
                            <th class="text-end text-danger">Balance</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($events as $e): 
                            $total = (float)($e['total_bill'] ?? 0);
                            $received = (float)($e['total_received'] ?? 0);
                            $bal = $total - $received;
                        ?>
                            <tr>
                                <td class="ps-4 small fw-medium"><?= date('d-M-Y', strtotime($e['created_at'])) ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= h($e['event_name']) ?></div>
                                    <div class="small text-muted text-uppercase"><i class="bi bi-geo-alt me-1"></i><?= h($e['venue_location']) ?></div>
                                </td>
                                <td>
                                    <div class="small fw-semibold"><?= h($e['mobile_number']) ?></div>
                                    <div class="small text-muted"><?= h($e['email']) ?></div>
                                </td>
                                <td class="text-end fw-semibold">₹<?= number_format($total, 2) ?></td>
                                <td class="text-end text-success fw-semibold">₹<?= number_format($received, 2) ?></td>
                                <td class="text-end text-danger fw-bold">₹<?= number_format($bal, 2) ?></td>
                                <td class="text-end pe-4">
                                    <div class="btn-group shadow-sm border rounded-pill overflow-hidden bg-white">
                                        <a href="events_manage.php?id=<?= $e['event_id'] ?>" class="btn btn-sm btn-white border-0" title="Edit Financials">
                                            <i class="bi bi-pencil-square text-primary"></i>
                                        </a>
                                        <button class="btn btn-sm btn-white border-0 delBtn" data-id="<?= $e['event_id'] ?>" title="Delete Event">
                                            <i class="bi bi-trash text-danger"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.select2').select2({ theme: 'bootstrap4', width: '100%' });

    const dt = $('#eventsTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        dom: '<"p-3 d-flex justify-content-between align-items-center"fB>rt<"p-3 d-flex justify-content-between align-items-center"ip>',
        buttons: [
            { extend: 'excel', className: 'btn btn-sm btn-outline-success rounded-pill px-3', text: '<i class="bi bi-file-earmark-excel me-1"></i> Excel' },
            { extend: 'print', className: 'btn btn-sm btn-outline-info rounded-pill px-3', text: '<i class="bi bi-printer me-1"></i> Print' }
        ],
        language: { search: "", searchPlaceholder: "Search summary..." }
    });

    // Custom Filters Logic
    $('#filterEventName').on('change', function(){ dt.column(1).search(this.value).draw(); });
    $('#filterVenue').on('keyup change', function(){ dt.column(1).search(this.value).draw(); });
    $('#filterMobile').on('keyup change', function(){ dt.column(2).search(this.value).draw(); });
    
    $('#resetFilters').on('click', function(e){
        e.preventDefault();
        $('#filterEventName').val('').trigger('change');
        $('#filterVenue, #filterMobile, #filterStartDate').val('');
        dt.columns().search('').draw();
    });

    // Delete Event
    $(document).on('click','.delBtn', function(){
        const id = $(this).data('id');
        if(!confirm('Delete this event record? This will also remove all component billing data.')) return;
        
        $.post('api/events_api.php', {action: 'delete', id: id}, function(res){
            if(res && res.status === 'success'){
                location.reload();
            } else {
                alert(res.message || 'Delete failed');
            }
        }, 'json').fail(function(){
            alert('Network error occurred.');
        });
    });
});
</script>

<?php include 'footer.php'; ?>