<?php
session_start();

// Unset all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

echo "<script>window.location.href='/kem/login.php';</script>";
exit();

?>
