<?php
declare(strict_types=1);

require_once __DIR__ . '/../../functions.php';


/* --------- Workflow helpers --------- */
function wf_create_request(array $payload, string $type, ?int $assigned_to = null){
    $ins = [
        'request_type' => $type,
        'status'       => 'SUBMITTED',
        'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        'requested_by' => current_user_id(),
        'assigned_to'  => $assigned_to,
        'vendor_id'    => $payload['vendor_id']   ?? null,
        'employee_id'  => $payload['employee_id'] ?? null,
        'branch_id'    => $payload['branch_id']   ?? null,
        'total_amount' => $payload['__total_amount__'] ?? null,
    ];
    $id = safe_insert('payment_requests',$ins);
    if ($id) wf_log($id,'SUBMIT', 'Request submitted');
    return $id;
}
function wf_update_request(int $id, array $data, string $action, string $comment=''){
    $ok = safe_update('payment_requests', $data, ['request_id'=>$id]);
    if ($ok) wf_log($id, $action, $comment);
    return $ok;
}
function wf_log(int $request_id, string $action, string $comment='', array $diff=[]){
    return safe_insert('payment_actions', [
        'request_id' => $request_id,
        'action'     => $action,
        'actor_id'   => current_user_id(),
        'comment'    => $comment,
        'diff_json'  => $diff ? json_encode($diff, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null
    ]);
}
function wf_get(int $id){
    return getRowValues('payment_requests', $id, 'request_id');
}
function wf_list(string $where='1', string $order='request_id DESC', int $limit=100){
    return exeSql("SELECT * FROM payment_requests WHERE $where ORDER BY $order LIMIT $limit");
}
