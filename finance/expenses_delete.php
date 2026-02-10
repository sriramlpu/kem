<?php
/**
 * FINANCE: Delete Expense (Redirect Fallback)
 * Path: finance/expenses_delete.php
 */
declare(strict_types=1);

require_once("../auth.php");
requireRole(['Admin', 'Finance']);
require_once("../functions.php");

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: expenses.php?err=invalid_id");
    exit;
}

try {
    // Perform hard delete
    $ok = exeSql("DELETE FROM expenses WHERE id = $id LIMIT 1");

    if ($ok === false) {
        header("Location: expenses.php?err=delete_failed");
    } else {
        header("Location: expenses.php?msg=deleted");
    }
} catch (Exception $e) {
    header("Location: expenses.php?err=" . urlencode($e->getMessage()));
}
exit;