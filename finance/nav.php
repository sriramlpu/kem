<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$currentPage = basename($_SERVER['PHP_SELF']);
$userName = $_SESSION['userName'] ?? 'Guest';
$userRole = strtolower($_SESSION['roleName'] ?? '');

$panelLink = '';
$panelLabel = '';
if ($userRole === 'admin') {
    $panelLink = '../admin/dashboard.php';
    $panelLabel = 'Admin Dashboard';
} elseif ($userRole === 'requester') {
    $panelLink = '../requester/dashboard.php';
    $panelLabel = 'Requester Panel';
}
?>
<nav class="navbar navbar-expand-lg bg-white fixed-top border-bottom py-2 shadow-sm">
  <div class="container-fluid px-4">
    <a class="navbar-brand me-4" href="index.php">
      <img src="../assets/img/logo.jpg" alt="Logo" height="45">
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#financeNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="financeNav">
      <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-2">
        <li class="nav-item">
          <a class="nav-link px-3 fw-semibold <?= $currentPage === 'vendor.php' ? 'text-primary' : 'text-dark' ?>" href="vendor.php">
            <i class="bi bi-shop me-1"></i> Vendors
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link px-3 fw-semibold <?= $currentPage === 'employees.php' ? 'text-primary' : 'text-dark' ?>" href="employees.php">
            <i class="bi bi-people me-1"></i> Employees
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link px-3 fw-semibold <?= $currentPage === 'expenses.php' ? 'text-primary' : 'text-dark' ?>" href="expenses.php">
            <i class="bi bi-wallet2 me-1"></i> Expenses
          </a>
        </li>
      </ul>

      <div class="d-flex align-items-center gap-3">
        <?php if (!empty($panelLink)): ?>
          <a href="<?= $panelLink ?>" class="btn btn-primary btn-sm px-3 shadow-sm rounded-pill">
            <i class="bi bi-person-workspace me-1"></i> <?= $panelLabel ?>
          </a>
        <?php endif; ?>
        
        <div class="vr mx-2 text-muted opacity-25 d-none d-lg-block"></div>
        
        <span class="small fw-bold text-secondary d-none d-md-block">
          <i class="bi bi-person-circle me-1"></i> <?= h($userName) ?>
        </span>
        
        <a href="../logout.php" class="btn btn-outline-danger btn-sm px-3 rounded-pill">
          <i class="bi bi-box-arrow-right"></i>
        </a>
      </div>
    </div>
  </div>
</nav>