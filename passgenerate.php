<?php
// $mysqli = new mysqli("localhost", "root", "", "payroll");

$hash = password_hash('hussaint', PASSWORD_DEFAULT);

echo $hash;
?>