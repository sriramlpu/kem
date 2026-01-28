 </main>

  <footer id="footer" class="footer position-relative">

    <div class="container">
      <div class="row gy-5">

        <div class="col-lg-4">
          <div class="footer-brand">
            <a href="index.php" class="logo d-flex align-items-center mb-3">
              <span class="sitename">FlexBiz</span>
            </a>
            <p class="tagline">Innovating the digital landscape with elegant solutions and timeless design.</p>

            <div class="social-links mt-4">
              <a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
              <a href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
              <a href="#" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
              <a href="#" aria-label="Twitter"><i class="bi bi-twitter-x"></i></a>
              <a href="#" aria-label="Dribbble"><i class="bi bi-dribbble"></i></a>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="footer-links-grid">
            <div class="row">
              <div class="col-6 col-md-4">
                <h5>Company</h5>
                <ul class="list-unstyled">
                  <li><a href="#">About Us</a></li>
                  <li><a href="#">Our Team</a></li>
                  <li><a href="#">Careers</a></li>
                  <li><a href="#">Newsroom</a></li>
                </ul>
              </div>
              <div class="col-6 col-md-4">
                <h5>Services</h5>
                <ul class="list-unstyled">
                  <li><a href="#">Web Development</a></li>
                  <li><a href="#">UI/UX Design</a></li>
                  <li><a href="#">Digital Strategy</a></li>
                  <li><a href="#">Branding</a></li>
                </ul>
              </div>
              <div class="col-6 col-md-4">
                <h5>Support</h5>
                <ul class="list-unstyled">
                  <li><a href="#">Help Center</a></li>
                  <li><a href="#">Contact Us</a></li>
                  <li><a href="#">Privacy Policy</a></li>
                  <li><a href="#">Terms of Service</a></li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-2">
          <div class="footer-cta">
            <h5>Let's Connect</h5>
            <a href="contact.php" class="btn btn-outline">Get in Touch</a>
          </div>
        </div>

      </div>
    </div>

    <div class="footer-bottom">
      <div class="container">
        <div class="row">
          <div class="col-12">
            <div class="footer-bottom-content">
              <p class="mb-0">© <span class="sitename">Myebsite</span>. All rights reserved.</p>
              <div class="credits">
                <!-- All the links in the footer should remain intact. -->
                <!-- You can delete the links only if you've purchased the pro version. -->
                <!-- Licensing information: https://bootstrapmade.com/license/ -->
                <!-- Purchase the pro version with working PHP/AJAX contact form: [buy-url] -->
                Designed by <a href="https://bootstrapmade.com/">BootstrapMade</a>
              </div>
            </div>
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

  <!-- JSZip -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<!-- Select2 Plugin -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
  $(document).ready(function() {
    $('.select2').select2({});
  });

   $(document).ready(function() {
    $('#itemSelect').select2({
    placeholder: "Select Items",
    allowClear: true,
    width: '100%',
    closeOnSelect: false   // 🔑 Keep dropdown open when selecting multiple items
});

});

</script>
</body>
</html>