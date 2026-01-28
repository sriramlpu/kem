<?php
/**
 * Global Navigation: Updated to support Admin, Requester, Approver, and Cashier roles.
 * Path: nav.php
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userName = $_SESSION['userName'] ?? 'User';
// Role check (priority: local $role variable, then session)
$navRole = $role ?? $_SESSION['roleName'] ?? '';
?>
</head>

<body class="index-page">

  <header id="header" class="header sticky-top">
    <div class="container-fluid container-xl position-relative">

      <div class="top-row d-flex align-items-center">
        <a href="#" class="logo d-flex align-items-center">
          <img src="../assets/img/logo.jpg" alt="logo">
        </a>
        <div class="nav-wrap m-auto">
          <div class="container d-flex justify-content-center position-relative">
            <nav id="navmenu" class="navmenu">
              <ul>
                <?php if($navRole == 'Admin'): ?>
                  <!-- Admin Menu -->
                  <li><a href="users.php">Users</a></li>
                  <li class="dropdown"><a href="#"><span>Masters</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a>
                    <ul>
                      <li class="dropdown"><a href="#"><span>Items</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a>
                        <ul>
                          <li><a href="categories.php">Categories</a></li>
                          <li><a href="sub_categories.php">Sub Categories</a></li>
                          <li><a href="items.php">Items</a></li>
                        </ul>
                      </li>
                      <li><a href="vendors.php">Vendors</a></li>
                      <li><a href="branches.php">Branches</a></li>
                      <li><a href="bank_accounts.php">Accounts</a></li>
                    </ul>
                  </li>
                  <li class="dropdown"><a href="#"><span>Requisition</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a>
                    <ul>
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
                    </ul>
                  </li>
                  <li class="dropdown"><a href="#"><span>Reports</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a>
                    <ul>
                      <li><a href="po_report.php">PO Report</a></li>
                      <li><a href="grn_report.php">GRN Report</a></li>
                      <li><a href="grn_itemwise_report.php">Itemwise Report</a></li>
                    </ul>
                  </li>
                  <li class="dropdown"><a href="#"><span>Dashboards</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a>
                    <ul>
                      <li><a href="/kem/finance/">Finance</a></li>
                      <li><a href="/kem/admin/dashboard.php">Requester</a></li>
                      <li><a href="/kem/approver/appdashboard.php">Approver</a></li>
                      <li><a href="/kem/cashier/cdashboard.php">Cashier</a></li>
                    </ul>
                  </li>

                <?php elseif ($navRole == 'Requester'): ?>
                  <!-- Requester Menu -->
                  <li><a href="dashboard.php">Dashboard</a></li>
                  <li class="dropdown"><a href="#"><span>Masters</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a>
                    <ul>
                      <li><a href="vendors.php">Vendors</a></li>
                      <li><a href="branches.php">Branches</a></li>
                    </ul>
                  </li>
                  <li class="dropdown"><a href="#"><span>Requisition</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a>
                    <ul>
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
                    </ul>
                  </li>

                <?php elseif ($navRole == 'Approver'): ?>
                  <!-- Approver Menu -->
                  <li><a href="appdashboard">Approval Desk</a></li>
                  <li><a href="payments_view">Payment History</a></li>

                <?php elseif ($navRole == 'Cashier'): ?>
                  <!-- Cashier Menu -->
                  <li><a href="cdashboard.php">Payment Desk</a></li>
                  <li><a href="advance_history">Advances</a></li>
                  <li><a href="payments_view">History</a></li>
                  <li><a href="vendor_redemption_management.php">Redemption</a></li>
                <?php endif; ?>
              </ul>
              <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
            </nav>
          </div>
        </div>

        <div class="d-flex align-items-center ms-auto">
          <span class="d-none d-md-block fw-bold me-3 text-secondary" style="white-space: nowrap; max-width: 150px; overflow: hidden; text-overflow: ellipsis;">
            Hello, <?php echo htmlspecialchars($userName); ?>
          </span>
          <div class="ms-2">
            <a href="../logout.php" class="btn btn-sm btn-danger">
              <i class="bi bi-box-arrow-right"></i> Logout
            </a>
          </div>
        </div>
      </div>
    </div>
  </header>

  <main class="main">
    <div id="alert-container"></div>
    <div class="container-fluid py-4">