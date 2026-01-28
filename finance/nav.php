<?php
$currentPage = basename($_SERVER['PHP_SELF']);
session_start();

$userName = ($_SESSION['userName'] ?? 'Guest'); // Use null coalescing operator for safety
$userRole = strtolower($_SESSION['roleName'] ?? '');

// --- Logic to Determine Panel Button Link and Label ---
$panelLink = '';
$panelLabel = '';

// NOTE: Update these paths (e.g., ../admin/index.php) to match your actual file structure
if ($userRole === 'admin') {
    $panelLink = '../admin/dashboard.php';
    $panelLabel = 'Requester';
} elseif ($userRole === 'requester') {
    $panelLink = '../admin/dashboard.php';
    $panelLabel = 'Requester Panel';
}
// --- End Logic ---
?>
<nav class="kmk-navbar">
  <div class="kmk-nav-inner">
    <a class="kmk-brand " href="clients.php" aria-label="Home">
      <img src="../assets/img/logo.jpg" alt="Logo" class="kmk-logo">
    </a>

    <ul class="kmk-center-links">
      <!--<li>-->
      <!--  <a href="index.php"-->
      <!--     class="kmk-link <?//= $currentPage === 'vendors_list.php' ? 'is-active' : '' ?>">-->
      <!--    Vendor_Reports(Updated)-->
      <!--  </a>-->
      <!--</li>-->
      <li>
        <a href="vendor.php"
           class="kmk-link <?= $currentPage === 'vendor.php' ? 'is-active' : '' ?>">
          Vendors
        </a>
      </li>
      <li>
        <a href="employees.php"
           class="kmk-link <?= $currentPage === 'employees.php' ? 'is-active' : '' ?>">
          Employees
        </a>
      </li>
      <li>
        <a href="expenses.php"
           class="kmk-link <?= $currentPage === 'expenses.php' ? 'is-active' : '' ?>">
          Expenses
        </a>
      </li>
    </ul>

    <div class="kmk-right-section">
      
      <?php if (!empty($panelLink)): ?>
      <a href="<?php echo $panelLink; ?>" class="btn kmk-panel-btn">
        <i class="bi bi-person-workspace"></i> <?php echo $panelLabel; ?>
      </a>
      <?php endif; ?>

      <span class="kmk-username">
        Hello, <?php echo $userName; ?>
      </span>
      <a href="../logout.php" class="btn btn-sm btn-danger">
        <i class="bi bi-box-arrow-right"></i> Logout
      </a>
    </div>
  </div>
</nav>


<style>
  /* Container */
  .kmk-navbar{
    background:#ffffff;
    border-bottom:1px solid #e5e7eb;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    z-index:1000;
  }
  .kmk-nav-inner{
    max-width:1200px;
    margin:0 auto;
    padding:8px 16px;
    /* --- CRITICAL: Use Flexbox for one-line layout --- */
    display: flex;
    justify-content: space-between; /* Puts Logo/Links/Right-Section apart */
    align-items: center; /* Vertically centers all items */
    min-height:56px;
    /* Removed: position: relative; */
  }

  /* Logo left */
  .kmk-brand{
    display:inline-flex;
    align-items:center;
    text-decoration:none;
  }
  .kmk-logo{ height:49px; display:block; }

  /* Centered links */
  .kmk-center-links{
    list-style:none;
    margin:0;
    padding:0;
    
    display:flex;
    gap:24px;
    align-items:center;
    /* This allows the links to fill the middle space while respecting the logo/right-section width */
    flex-grow: 1;
    justify-content: center;
    /* Add a small margin to prevent collision with logo and right section */
    margin: 0 16px; 
  }

  .kmk-link{
    text-decoration:none;
    font-weight:500;
    color:#212529;
    padding:6px 10px;
    border-radius:4px;
    transition:color .15s ease, background .15s ease;
  }
  .kmk-link:hover{ color:#0d6efd; background:#f5f7ff; }
  .kmk-link.is-active{
    color:#0d6efd;
    border-bottom:2px solid #0d6efd;
  }

  /* Right Section Styles */
  .kmk-right-section {
    display: flex;
    align-items: center;
    gap: 12px; /* Space between items (new button, username, logout) */
    /* Ensure it doesn't shrink or grow */
    flex-shrink: 0;
  }

  .kmk-username {
    font-weight: bold;
    color: #6c757d;
    white-space: nowrap;
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  /* --- NEW STYLES FOR PANEL BUTTON --- */
  .kmk-panel-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 12px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    border-radius: 4px;
    /* Primary Button Look (Using your active blue color) */
    background-color: #0d6efd; 
    color: white;
    border: 1px solid #0d6efd;
    transition: background-color .15s ease, border-color .15s ease, opacity .15s ease;
    white-space: nowrap;
  }

  .kmk-panel-btn i {
    margin-right: 6px;
    font-size: 16px;
  }

  .kmk-panel-btn:hover {
    background-color: #0b5ed7; /* Darker blue on hover */
    border-color: #0b5ed7;
    color: white; 
    opacity: 0.9;
  }
  /* --- END NEW STYLES --- */


  /* Small screens - Adjust for responsive design */
  @media (max-width: 992px){
    /* Hide center links on medium screens to save space */
    .kmk-center-links{
      display: none;
    }
    .kmk-nav-inner {
      justify-content: space-between;
    }
    .kmk-username {
      display: none; /* Hide username on smaller screens, just show the buttons */
    }
    /* Panel button can stay visible on small screens */
  }

  /* Push page content below navbar */
  body {
    margin:0;
    padding-top:60px; /* adjust to navbar height */
  }
</style>