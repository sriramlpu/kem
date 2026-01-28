<?php
session_start();

require_once("../functions.php");

// if (!isset($_SESSION["userId"])) {
//     echo '<script>window.location.href = "../login.php";</script>';
//     exit;
// }
$userId =1;
$userId = $_SESSION["userId"];

$getUser = getValues("users", "user_id = '$userId'"); //new
// $getUser = getRowValues("users",$userId);
$name = $getUser["username"] ?? "";
$email = $getUser["email"] ?? "";
$phone = $getUser["phone"]?? "";
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>KMK Admin Portal</title>
  <meta name="description" content="">
  <meta name="keywords" content="">

  <!-- Favicons -->
  <link href="../assets/img/logo.jpg" rel="icon">

  <link href="../assets/img/logo.jpg" rel="apple-touch-icon">

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="../assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
  <link href="../assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

  <!-- Main CSS File -->
  <link href="../assets/css/main.css" rel="stylesheet">
  
  <!-- Font Awesome CDN -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <!-- Select2 Plugin -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />



  <style>
    .select2-container--default .select2-selection--single {
        height: 38px;
        padding: 6px 12px;
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 24px;
    }

    @media (max-width: 768px) {
        .dataTables_wrapper {
            overflow-x: auto;
        }
    }
    /* Prevent horizontal overflow on mobile */
    body {
        overflow-x: hidden;
    }

    /* Make select2 and buttons responsive */
    .select2-container {
        width: 100% !important;
        max-width: 100%;
    }
    .select2-dropdown {
       z-index:10000;
    }

    .btn-orange{
        background-color: orange;
        color:white;
    }
    .card {
        max-width: 100%;
        overflow-x: hidden;
    }
    @media (max-width: 576px) {
    .card {
        margin: 0;
        padding: 0.75rem;
    }
    }
</style>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- DataTables core -->
<link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet" />
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<!-- Bootstrap 5 styling -->
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- Buttons extension -->
<link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css" rel="stylesheet" />
<link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet" />
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<!-- JSZip for Excel -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<!-- PDF support (optional) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

 
<body>
<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>