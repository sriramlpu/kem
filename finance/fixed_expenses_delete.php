<?php
/**
 * FINANCE: Delete Fixed Obligation (Redirect Fallback)
 * Path: finance/fixed_expenses_delete.php
 * UPDATED: Integrated with KMK standard utilities and advanced safety checks.
 * LOGIC: Prevents deletion if payments are realized or requests are pending in the system.
 */
declare(strict_types=1);

// Ensure output buffering to prevent header issues during redirect
if (!ob_get_level()) ob_start();

require_once dirname(__DIR__) . '/auth.php';
requireRole(['Admin', 'Finance']); // Only Admins allowed to delete master financial obligations
require_once dirname(__DIR__) . '/functions.php';

/** Tiny Utils (KMK Standard parity with payment1.php) **/
if (!function_exists('v')) { function v($k, $d = null) { return $_POST[$k] ?? $_GET[$k] ?? $d; } }
if (!function_exists('i')) { function i($x) { return is_numeric($x) ? (int)$x : 0; } }

$id = i(v('id'));

if ($id <= 0) {
    header("Location: fixed_expenses.php?err=invalid_id");
    exit;
}

try {
    // 1. Fetch record for validation
    $check = exeSql("SELECT balance_paid FROM fixed_expenses WHERE id = $id LIMIT 1");
    
    if (!$check) {
        header("Location: fixed_expenses.php?err=not_found");
        exit;
    }

    // 2. Realized Payment Safety Check
    // If the obligation has already started receiving payments, it cannot be hard-deleted.
    if ((float)($check[0]['balance_paid'] ?? 0) > 0) {
        header("Location: fixed_expenses.php?err=has_payments");
        exit;
    }

    /** * 3. Workflow Integrity Check:
     * Check if any fund requests (Submitted, Approved, or Paid) are linked to this obligation.
     * Since fixed_id is stored inside JSON payload_json, we perform a LIKE lookup.
     */
    $checkRequests = exeSql("SELECT request_id FROM payment_requests 
                             WHERE payload_json LIKE '%\"fixed_id\":$id%' 
                             OR payload_json LIKE '%\"fixed_id\":\"$id\"%' 
                             LIMIT 1");
                             
    if (!empty($checkRequests)) {
        // Prevent deletion of obligations linked to existing payment workflow items
        header("Location: fixed_expenses.php?err=has_linked_requests");
        exit;
    }

    // 4. Perform hard delete of the obligation tracker
    $ok = exeSql("DELETE FROM fixed_expenses WHERE id = $id LIMIT 1");

    if ($ok === false) {
        header("Location: fixed_expenses.php?err=delete_failed");
    } else {
        header("Location: fixed_expenses.php?msg=deleted");
    }
} catch (Exception $e) {
    header("Location: fixed_expenses.php?err=" . urlencode($e->getMessage()));
}

ob_end_flush();
exit;