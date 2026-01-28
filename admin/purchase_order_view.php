<?php
require_once("header.php");
require_once("nav.php");
?>


<section class="container-fluid section">
    <div class="row g-4">
        <div class="col-md-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <span>Purchase Orders</span>
                    <a href="purchase_order.php" class="btn btn-danger">Add New PO</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive w-100">
                        <div class="row mb-3" id="poStatusSummary">
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
                        </div>

                        <table class="table table-bordered" id="poTable">
                            <thead>
                                <tr>
                                    <th>PO Number</th>
                                    <th>Branch</th>
                                    <th>Vendor</th>
                                    <th>PO Date</th>
                                    <th>Expected Delivery</th>
                                    <th>Total(₹)</th>
                                    <th>Discount(₹)</th>
                                    <th>Transportation(₹)</th>
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

<!-- Modal for Add/Edit PO -->
<div class="modal fade" id="poModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="poModalTitle">Add Purchase Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="poModalBody">
                <!-- We'll load the existing PO form here via AJAX -->
            </div>
        </div>
    </div>
</div>



<script>
    $(document).ready(function() {
        const poModal = new bootstrap.Modal(document.getElementById('poModal'));

        let currentStatusFilter = ''; // current filter

        const poTable = $('#poTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: './api/purchase_order_api.php',
                type: 'POST',
                data: function(d) {
                    d.action = 'list';
                    d.status = currentStatusFilter; // send filter to API
                },
                dataSrc: function(json) {
                    console.log(json);
                    // update counts
                    $('#pendingCount').text(json.counts.Pending || 0);
                    $('#partialCount').text(json.counts['Partially Fulfilled'] || 0);
                    $('#completedCount').text(json.counts.Completed || 0);
                     $('#cancelledCount').text(json.counts.Cancelled || 0);
                    return json.data;
                }
            },
            columns: [{
                    data: 'order_number'
                },
                {
                    data: 'branch_name'
                },
                {
                    data: 'vendor_name'
                },
                {
                    data: 'po_date'
                },
                {
                    data: 'expected_delivery_date'
                },
                {
                    data: 'total_amount'
                },
                {
                    data: 'discount_amount'
                },
                {
                    data: 'transportation'
                },
                {
    data: 'po_id',
    render: function(data, type, row) {
        return `
            <a href="purchase_order_form.php" class="btn btn-sm btn-primary edit-po" data-id="${data}">Edit</a>
            <button class="btn btn-sm btn-danger delete-po" data-id="${data}">Delete</button>
            <button class="btn btn-sm btn-secondary view-po" data-id="${data}">View</button>
            <button class="btn btn-sm btn-info print-po" data-id="${data}">Print</button>
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
            currentStatusFilter = $(this).data('status'); // set filter
            poTable.ajax.reload();
        });

     $(document).on('click', '.edit-po', function(e) {
    e.preventDefault();
    let po_id = $(this).data('id');

    // Create a hidden form dynamically
    let form = $('<form>', {
        action: 'purchase_order_form.php',
        method: 'POST'
    }).append($('<input>', {
        type: 'hidden',
        name: 'po_id',
        value: po_id
    }));

    // Append form to body and submit
    $('body').append(form);
    form.submit();
});


        // Delete PO
        $(document).on('click', '.delete-po', function() {
            const poId = $(this).data('id');
            if (confirm('Are you sure you want to delete this Purchase Order?')) {
                $.post('./api/purchase_order_api.php', {
                    action: 'delete',
                    po_id: poId
                }, function(res) {
                    if (res.status === 'success') {
                        showAlert('PO deleted successfully', 'success');
                        poTable.ajax.reload(null, false);
                    } else {
                        showAlert('Failed to delete PO: ' + res.message, 'danger');
                    }
                }, 'json');
            }
        });

        // View PO
        $(document).on('click', '.view-po', function() {
            const poId = $(this).data('id');
            $('#poModalTitle').text('View Purchase Order');
            $('#poModalBody').load('./purchase_order_form.php', {
                po_id: poId,
                readonly: 1
            }, function() {
                poModal.show();
            });
        });

        // Add new PO
        $('#addNewPOBtn').on('click', function() {
            $('#poModalTitle').text('Add Purchase Order');
            $('#poModalBody').load('./purchase_order_form.php', function() {
                poModal.show();
            });
        });
    });

    $(document).on('click', '.print-po', function() {
    const poId = $(this).data('id');

    // Fetch the print HTML via AJAX
    $.get(`purchase_order_print.php?po_id=${poId}`, function(html) {
        const iframe = document.getElementById('printFrame');
        const doc = iframe.contentDocument || iframe.contentWindow.document;

        // Write the HTML into the iframe
        doc.open();
        doc.write(html);
        doc.close();

        // Trigger print from the iframe
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
    });
});


</script>

<script src="./js/showAlert.js"></script>
<iframe id="printFrame" style="display:none;"></iframe>
<?php
require_once("footer.php");
?>