<?php
/**
 * FINANCE API: Events Management
 * Handles administrative actions for event records.
 */
require_once("../../functions.php");
header('Content-Type: application/json; charset=UTF-8');

function jexit($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if (!$id) throw new Exception("Invalid Event ID.");

        $dbObj->beginTransaction();
        // 1. Clear items first (FK constraint safety)
        exeSql("DELETE FROM event_items WHERE event_id = $id");
        // 2. Delete parent event
        exeSql("DELETE FROM events WHERE event_id = $id LIMIT 1");
        
        $dbObj->commit();
        jexit(['status' => 'success']);
    }

} catch (Exception $e) {
    if(isset($dbObj)) $dbObj->rollBack();
    http_response_code(400);
    jexit(['status' => 'error', 'message' => $e->getMessage()]);
}