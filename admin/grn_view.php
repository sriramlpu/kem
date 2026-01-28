<?php
require_once("header.php");
require_once("nav.php");
?>
<section class="container-fluid section">
    <div class="row g-4">
        <div class="col-md-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <span>Goods Received Notes (GRN)</span>
                    <a href="grn_create.php" class="btn btn-danger">Add New GRN</a>
                </div>
                <div class="card-body">
                    <!-- <div class="row mb-3" id="grnStatusSummary">
                        <div class="col-md-2">
                            <span class="badge bg-warning status-filter" data-status="Pending" style="cursor:pointer;">
                                Pending: <span id="pendingCount">0</span>
                            </span>
                        </div>
                        <div class="col-md-2">
                            <span class="badge bg-info status-filter" data-status="Partial" style="cursor:pointer;">
                                Partially Fulfilled: <span id="partialCount">0</span>
                            </span>
                        </div>
                        <div class="col-md-2">
                            <span class="badge bg-success status-filter" data-status="Completed" style="cursor:pointer;">
                                Completed: <span id="completedCount">0</span>
                            </span>
                        </div>
                        <div class="col-md-2">
                            <span class="badge bg-secondary status-filter" data-status="Cancelled" style="cursor:pointer;">
                                Cancelled: <span id="cancelledCount">0</span>
                            </span>
                        </div>
                    </div> -->

                    <div class="table-responsive">
                        <table class="table table-bordered" id="grnTable">
                            <thead>
                                <tr>
                                    <th>GRN No</th>
                                    <th>Vendor</th>
                                    <th>PO No</th>
                                    <th>GRN Date</th>
                                    <th>Invoice No</th>
                                    <th>Total Amount(₹)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    let currentStatusFilter = '';

    const grnTable = $('#grnTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: './api/grn_api.php',
            type: 'POST',
            data: function(d) {
                d.action = 'list';
                d.status = currentStatusFilter;
            }
        },
        columns: [
            { data: 'grn_number' },
            { data: 'vendor_name' },
            { data: 'po_number' },
            { data: 'grn_date' },
            { data: 'invoice_number' },
            { data: 'total_amount' },
            {
                data: 'grn_id',
                render: function(data, type, row) {
                    return `
                        <a href="grn_view_single.php?grn_id=${data}" class="btn btn-sm btn-info">View</a>
                        <a href="grn_form.php?grn_id=${data}" class="btn btn-sm btn-warning">Edit</a>
                        <button class="btn btn-sm btn-danger delete-grn" data-id="${data}">Delete</button>
                        <button class="btn btn-sm btn-secondary print-grn" data-id="${data}">Print</button>
                    `;
                },
                orderable: false,
                searchable: false
            }
        ],
        pageLength: 50,
        lengthMenu: [50, 100, 200]
    });

    // Filter by status
    $(document).on('click', '.status-filter', function() {
        currentStatusFilter = $(this).data('status');
        grnTable.ajax.reload();
    });

    // Delete GRN
    $(document).on('click', '.delete-grn', function() {
        const grnId = $(this).data('id');
        if (confirm('Are you sure you want to delete this GRN?')) {
            $.post('./api/grn_api.php', { action: 'delete', grn_id: grnId }, function(res) {
                if(res.status === 'success') {
                    alert('GRN deleted successfully');
                    grnTable.ajax.reload(null, false);
                } else {
                    alert('Failed to delete GRN: ' + res.message);
                }
            }, 'json');
        }
    });

    // Print GRN
    $(document).on('click', '.print-grn', function() {
        const grnId = $(this).data('id');
        $.get(`grn_print.php?grn_id=${grnId}`, function(html) {
            const iframe = document.getElementById('printFrame');
            const doc = iframe.contentDocument || iframe.contentWindow.document;
            doc.open();
            doc.write(html);
            doc.close();
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        });
    });
});
</script>

<iframe id="printFrame" style="display:none;"></iframe>
<script src="./js/showAlert.js"></script>
<?php require_once("footer.php"); ?>
