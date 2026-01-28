</head>

<body class="index-page">

  <header id="header" class="header sticky-top">
    <div class="container-fluid container-xl position-relative">

      <div class="top-row d-flex align-items-center">
        <a href="#" class="logo d-flex align-items-center">
          <!-- Uncomment the line below if you also wish to use an image logo -->
          <img src="../assets/img/logo.jpg" alt="logo">
          <!-- <h1 class="sitename">FlexBiz</h1> -->
        </a>
        <div class="nav-wrap m-auto">
          <div class="container d-flex justify-content-center position-relative">
            <nav id="navmenu" class="navmenu">
              <ul>
                <li class="dropdown"><a href="#"><span>Requsition</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a>
                  <ul>
                    <!-- <li><a href="indent.php">Indent</a></li> -->
                     <li class="dropdown"><a href="#"><span>Indent</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a>
                        <ul>
                          <li><a href="create_indent.php">Create Indent</a></li>
                          <li><a href="indent_list.php">View Indents</a></li>
                        </ul>
                      </li>
                  </ul>
                </li>

                <li class="dropdown"><a href="#"><span>Procurement</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a>
                  <ul>
                    <li><a href="purchase_order.php">Purchase Order</a></li>
                    <li><a href="grn.php">GRN</a></li>
                     <li><a href="grn_return.php">GRN Return</a></li>
                    <!-- <li><a href="#">Location Mapping</a></li> -->
                    <!-- <li><a href="#">GRN Inspection</a></li>
                    <li><a href="#">GRN Return</a></li>
                    <li><a href="#">Debit & Credit Notes</a></li> -->
                  </ul>
                </li>

                <li class="dropdown"><a href="#"><span>Reports</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a>
                  <ul>
                    <li><a href="po_report.php">PO Report</a></li>
                    <li><a href="grn_report.php">GRN Report</a></li>
                    <!-- <li><a href="indent_report.php">Indent Report</a></li>
                    <li><a href="branchwise_report.php">Branch wise Stock Report</a></li> -->
                  </ul>
                </li>
              </ul>
              <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
            </nav>
          </div>
        </div>
        <div class="ms-auto me-3 position-relative">
    <button id="poEditRequestsBtn" class="btn btn-sm btn-warning position-relative">
      <i class="bi bi-bell"></i>
      <span id="poEditRequestCount" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
        0
      </span>
    </button>

    <div id="poEditRequestsDropdown" class="dropdown-menu dropdown-menu-end" style="min-width:300px;">
      <h6 class="dropdown-header">PO Edit Requests</h6>
      <div id="poEditRequestsList" class="list-group">
        <div class="text-center text-muted py-2">No pending requests</div>
      </div>
    </div>
  </div>
      </div>

    </div>
  </header>

  <main class="main">
    <div id="alert-container" style="position: fixed; top: 20px; right: 24px; z-index: 9999; min-width: 320px; border-radius:10px;"></div>
    <div class="container-fluid ">