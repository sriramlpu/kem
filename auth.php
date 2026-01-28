<?php
declare(strict_types=1);

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Restrict access to specific roles.
 * Example usage: requireRole(['Approver', 'Cashier']);
 */
function requireRole(array $allowedRoles): void
{
    // If user not logged in
    // $role=$_SESSION["roleName"]
    // echo "<script>console.log($role);</script>";
    if (!isset($_SESSION["userId"])) {
        header("Location: ../login.php");
        exit;
    }

    // If role not set or not allowed (except Admin)
    if (
        !isset($_SESSION["roleName"]) ||
        (!in_array($_SESSION["roleName"], $allowedRoles) && $_SESSION["roleName"] !== 'Admin')
    ) {
        session_destroy();
        echo '<script>alert("Access denied. You are not authorized to view this page."); window.location.href = "../login.php";</script>';
        exit;
    }
}
