 </main>

 <footer id="footer" class="footer position-relative dark-background p-3">

     <div class="container">
       <div class="row">
         <div class="col-12 text-center">
             <p class="mb-0">© <span class="sitename">KMK</span>. All rights reserved.</p>
             <div class="credits">
               <!-- All the links in the footer should remain intact. -->
               <!-- You can delete the links only if you've purchased the pro version. -->
               <!-- Licensing information: https://bootstrapmade.com/license/ -->
               <!-- Purchase the pro version with working PHP/AJAX contact form: [buy-url] -->
               <!-- Designed by <a href="https://bootstrapmade.com/">BootstrapMade</a> -->
             </div>
           </div>
         </div>
     </div>

 </footer>

 <!-- Scroll Top -->
 <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

 <!-- Preloader -->
 <div id="preloader">
   <div></div>
   <div></div>
   <div></div>
   <div></div>
 </div>

 <!-- Vendor JS Files -->
 <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
 <script src="../assets/vendor/imagesloaded/imagesloaded.pkgd.min.js"></script>

 <!-- Main JS File -->
 <script src="../assets/js/main.js"></script>
 <!-- DataTables JS -->
 <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
 <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

 <!-- Buttons JS -->
 <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
 <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
 <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
 <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
 <script src="https://cdn.datatables.net/plug-ins/1.13.6/pagination/ellipses.js"></script>


 <!-- JSZip -->
 <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

 <!-- Select2 Plugin -->
 <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

 <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
 <script>
   $(document).ready(function() {
     $('.select2').select2({});
   });

   $(document).ready(function() {
     $('#itemSelect').select2({
       placeholder: "Select Items",
       allowClear: true,
       width: '100%',
       closeOnSelect: false // 🔑 Keep dropdown open when selecting multiple items
     });

   });
 </script>

<!-- <script>
 $(document).ready(function() {
  // Load PO Edit Requests
  function loadPOEditRequests() {
    $.getJSON('./api/purchase_order_api.php', { action: 'pending_edit_requests' }, function(res) {
      const $list = $('#poEditRequestsList').empty();
      if (res.status === 'success' && res.data.length > 0) {
        $('#poEditRequestCount').text(res.data.length);
        res.data.forEach(req => {
          const item = $(`
            <div class="list-group-item d-flex justify-content-between align-items-start">
              <div>
                <strong>PO#${req.order_number}</strong> by ${req.user_name}<br>
                <small>${req.requested_date}</small>
              </div>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-success approve-request" data-id="${req.po_id}">✔</button>
                <button class="btn btn-danger reject-request" data-id="${req.po_id}">✖</button>
              </div>
            </div>
          `);
          $list.append(item);
        });
      } else {
        $('#poEditRequestCount').text(0);
        $list.html('<div class="text-center text-muted py-2">No pending requests</div>');
      }
    });
  }

  // Toggle dropdown
  $('#poEditRequestsBtn').on('click', function() {
    $('#poEditRequestsDropdown').toggle();
    loadPOEditRequests();
  });

  // Approve request
  $(document).on('click', '.approve-request', function() {
    const poId = $(this).data('id');
    Swal.fire({
      title: 'Approve Edit?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes',
      cancelButtonText: 'Cancel'
    }).then(result => {
      if (result.isConfirmed) {
        $.post('./api/purchase_order_api.php', { action: 'approve_edit_request', po_id: poId }, function(res) {
          if (res.status === 'success') {
            Swal.fire('Approved!', '', 'success');
            loadPOEditRequests();
            $('#purchaseOrdersTable').DataTable().ajax.reload(null, false);
          } else {
            Swal.fire('Failed', res.message || 'Unable to approve', 'error');
          }
        }, 'json');
      }
    });
  });

  // Reject request
  $(document).on('click', '.reject-request', function() {
    const poId = $(this).data('id');
    Swal.fire({
      title: 'Reject Edit?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes',
      cancelButtonText: 'Cancel'
    }).then(result => {
      if (result.isConfirmed) {
        $.post('./api/purchase_order_api.php', { action: 'reject_edit_request', po_id: poId }, function(res) {
          if (res.status === 'success') {
            Swal.fire('Rejected!', '', 'success');
            loadPOEditRequests();
            $('#purchaseOrdersTable').DataTable().ajax.reload(null, false);
          } else {
            Swal.fire('Failed', res.message || 'Unable to reject', 'error');
          }
        }, 'json');
      }
    });
  });

  // Auto-refresh every 30s (optional)
  setInterval(loadPOEditRequests, 30000);
});
</script> -->
 </body>

 </html>