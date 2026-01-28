<?php
// kmk/finance/expenses_delete.php
declare(strict_types=1);
require_once dirname(__DIR__) . '/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  header('Location: expenses.php?err=invalid'); exit;
}

// Hard delete this expense
$ok = exeSql("DELETE FROM expenses WHERE id={$id} LIMIT 1");

// Redirect back with a flag
if ($ok === false) {
  header('Location: expenses.php?err=delete_failed'); exit;
}
header('Location: expenses.php?deleted=1'); exit;
