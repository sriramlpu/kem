<?php
/**
 * finance/api/events_api.php — Events + Items API
 * Endpoints:
 *  - list, create, edit, delete, getEvent
 *  - create_with_items : create event + insert provided items
 *  - edit_with_items   : update event + replace items with provided ones (transactional)
 *  - list_items, add_item, edit_item, delete_item (compat)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../functions.php';

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

function jfail($msg){ echo json_encode(['status'=>'error','message'=>$msg]); exit; }
function jok($data=null){ echo json_encode(['status'=>'success','data'=>$data]); exit; }

/** Utils **/
function sanitize_regex($input) {
  $input = (string)$input;
  $input = trim($input);
  return preg_replace('/[^a-zA-Z0-9\s\-]/', '', $input);
}
function q($s) { return addslashes((string)$s); }
function build_search_where($searchRaw) {
  $where = "WHERE 1=1";
  if ($searchRaw === null || $searchRaw === '') return $where;
  $pattern = sanitize_regex($searchRaw);
  if ($pattern !== '') {
    $pattern = q($pattern);
    $where .= " AND (event_name REGEXP '{$pattern}' OR venue_location REGEXP '{$pattern}' OR mobile_number REGEXP '{$pattern}' OR email REGEXP '{$pattern}')";
  } else {
    $s = '%' . q($searchRaw) . '%';
    $where .= " AND (event_name LIKE '{$s}' OR venue_location LIKE '{$s}' OR mobile_number LIKE '{$s}' OR email LIKE '{$s}')";
  }
  return $where;
}

/* -------------------- LIST -------------------- */
if ($action === 'list') {
  $isDT   = isset($_POST['draw']) || isset($_POST['start']) || isset($_POST['length']);
  $search = $_POST['search'] ?? ($_POST['filter_search'] ?? null);
  $draw   = intval($_POST['draw'] ?? 1);
  $start  = intval($_POST['start'] ?? 0);
  $length = intval($_POST['length'] ?? 10);
  if (!$isDT) { $start = 0; $length = intval($_POST['limit'] ?? 200); }

  $where = build_search_where($search);
  $totalRecords = (int)($exe = exeSql("SELECT COUNT(*) c FROM events")) ? ($exe[0]['c'] ?? 0) : 0;
  $totalFiltered = (int)($exe = exeSql("SELECT COUNT(*) c FROM events {$where}")) ? ($exe[0]['c'] ?? 0) : 0;

  $rows = exeSql("
      SELECT event_id AS id, event_name, venue_location, mobile_number, email, address, billing_address
      FROM events
      {$where}
      ORDER BY event_id DESC
      LIMIT {$start}, {$length}
  ") ?: [];

  $data = []; $sno = $start + 1;
  foreach ($rows as $r) { $r['sno'] = $sno++; $data[] = $r; }

  if ($isDT) {
    echo json_encode(["draw"=>$draw,"recordsTotal"=>$totalRecords,"recordsFiltered"=>$totalFiltered,"data"=>$data]); exit;
  } else { jok($data); }
}

/* -------------------- BASIC CREATE/EDIT/DELETE -------------------- */
elseif ($action === 'create' || $action === 'edit' || $action === 'delete' || $action === 'getEvent') {

  if ($action === 'create') {
    $event_name      = trim($_POST['event_name'] ?? '');
    $venue_location  = trim($_POST['venue_location'] ?? '');
    $mobile_number   = trim($_POST['mobile_number'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $address         = trim($_POST['address'] ?? '');
    $billing_address = trim($_POST['billing_address'] ?? '');
    if ($event_name==='' || $venue_location==='' || $mobile_number==='' || $email==='') jfail('Required fields missing');

    $ok = insData('events', [
      'event_name'=>$event_name,'venue_location'=>$venue_location,'mobile_number'=>$mobile_number,'email'=>$email,
      'address'=>$address,'billing_address'=>$billing_address
    ]);
    if ($ok === false) jfail('Insert failed');
    jok();
  }

  elseif ($action === 'edit') {
    $id = (int)($_POST['id'] ?? 0); if ($id<=0) $id = (int)($_POST['event_id'] ?? 0);
    if ($id <= 0) jfail('Invalid ID.');

    $event_name      = trim($_POST['event_name'] ?? '');
    $venue_location  = trim($_POST['venue_location'] ?? '');
    $mobile_number   = trim($_POST['mobile_number'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $address         = trim($_POST['address'] ?? '');
    $billing_address = trim($_POST['billing_address'] ?? '');

    $ok = upData('events', [
      'event_name'=>$event_name,'venue_location'=>$venue_location,'mobile_number'=>$mobile_number,'email'=>$email,
      'address'=>$address,'billing_address'=>$billing_address
    ], ['event_id'=>$id]);

    // ACCEPT 0 affected rows as success; only false means SQL error
    if ($ok === false) jfail('Update failed');
    jok();
  }

  elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0); if ($id<=0) jfail('ID required');
    $ok = exeSql("DELETE FROM events WHERE event_id={$id}");
    if ($ok === false) jfail('Delete failed');
    jok();
  }

  elseif ($action === 'getEvent') {
    $id = (int)($_POST['id'] ?? 0); if ($id<=0) jfail('Invalid ID.');
    $row = getRowValues('events', $id, 'event_id');
    if ($row) { $row['id'] = $row['event_id']; jok($row); } else { jfail('Not found'); }
  }
}

/* -------------------- CREATE WITH ITEMS -------------------- */
elseif ($action === 'create_with_items') {
  // 1) Create event
  $event_name      = trim($_POST['event_name'] ?? '');
  $venue_location  = trim($_POST['venue_location'] ?? '');
  $mobile_number   = trim($_POST['mobile_number'] ?? '');
  $email           = trim($_POST['email'] ?? '');
  $address         = trim($_POST['address'] ?? '');
  $billing_address = trim($_POST['billing_address'] ?? '');
  if ($event_name==='' || $venue_location==='' || $mobile_number==='' || $email==='') jfail('Required fields missing');

  $ok = insData('events', [
    'event_name'=>$event_name,'venue_location'=>$venue_location,'mobile_number'=>$mobile_number,'email'=>$email,
    'address'=>$address,'billing_address'=>$billing_address
  ]);
  if ($ok === false) jfail('Insert failed');

  // get new id (prefer LAST_INSERT_ID(); fallback MAX(event_id))
  $rid = exeSql("SELECT LAST_INSERT_ID() AS id");
  $event_id = (int)($rid[0]['id'] ?? 0);
  if ($event_id <= 0) {
    $rid = exeSql("SELECT MAX(event_id) AS id FROM events");
    $event_id = (int)($rid[0]['id'] ?? 0);
  }
  if ($event_id <= 0) jfail('Could not get new event id');

  // 2) Insert items (Breakfast/Lunch/Dinner posted as items[NAME][...])
  $items = $_POST['items'] ?? [];
  foreach ($items as $name => $row) {
    $name = trim((string)($row['item_name'] ?? $name));
    $quantity = (float)($row['quantity'] ?? 0);
    $price    = (float)($row['price'] ?? 0);
    $recv     = (float)($row['amount_received'] ?? 0);
    if ($quantity <= 0) continue; // skip empty rows
    $total = $quantity * $price;
    $balance = $total - $recv;

    $ok = insData('event_items', [
      'event_id'=>$event_id, 'item_name'=>$name, 'quantity'=>$quantity, 'price'=>$price,
      'total_amount'=>$total, 'amount_received'=>$recv, 'balance'=>$balance, 'remark'=>''
    ]);
    if ($ok === false) jfail('Insert item failed');
  }

  jok(['event_id'=>$event_id]);
}

/* -------------------- EDIT WITH ITEMS (TRANSACTION) -------------------- */
elseif ($action === 'edit_with_items') {
  $id = (int)($_POST['id'] ?? 0); if ($id<=0) $id = (int)($_POST['event_id'] ?? 0);
  if ($id <= 0) jfail('Invalid ID.');

  $event_name      = trim($_POST['event_name'] ?? '');
  $venue_location  = trim($_POST['venue_location'] ?? '');
  $mobile_number   = trim($_POST['mobile_number'] ?? '');
  $email           = trim($_POST['email'] ?? '');
  $address         = trim($_POST['address'] ?? '');
  $billing_address = trim($_POST['billing_address'] ?? '');

  // Start transaction to avoid losing items on partial failure
  exeSql("START TRANSACTION");

  $ok = upData('events', [
    'event_name'=>$event_name,'venue_location'=>$venue_location,'mobile_number'=>$mobile_number,'email'=>$email,
    'address'=>$address,'billing_address'=>$billing_address
  ], ['event_id'=>$id]);

  // Only false is a failure (0 affected rows is OK)
  if ($ok === false) { exeSql("ROLLBACK"); jfail('Update failed'); }

  // Replace items with what was submitted (simple + robust)
  $delOk = exeSql("DELETE FROM event_items WHERE event_id={$id}");
  if ($delOk === false) { exeSql("ROLLBACK"); jfail('Failed to clear old items'); }

  $items = $_POST['items'] ?? [];
  foreach ($items as $name => $row) {
    $name = trim((string)($row['item_name'] ?? $name));
    $quantity = (float)($row['quantity'] ?? 0);
    $price    = (float)($row['price'] ?? 0);
    $recv     = (float)($row['amount_received'] ?? 0);
    if ($quantity <= 0) continue;
    $total = $quantity * $price;
    $balance = $total - $recv;

    $ok = insData('event_items', [
      'event_id'=>$id, 'item_name'=>$name, 'quantity'=>$quantity, 'price'=>$price,
      'total_amount'=>$total, 'amount_received'=>$recv, 'balance'=>$balance, 'remark'=>''
    ]);
    if ($ok === false) { exeSql("ROLLBACK"); jfail('Insert item failed'); }
  }

  exeSql("COMMIT");
  jok(['event_id'=>$id]);
}

/* -------------------- ITEMS HELPERS (optional/legacy) -------------------- */
elseif ($action === 'list_items') {
  $event_id = (int)($_POST['event_id'] ?? 0);
  if ($event_id<=0) jfail('event_id required');
  $rows = exeSql("
    SELECT item_id,event_id,item_name,quantity,price,
      COALESCE(total_amount,COALESCE(quantity,0)*COALESCE(price,0)) AS total_amount,
      COALESCE(amount_received,0) AS amount_received,
      COALESCE(balance, COALESCE(total_amount,COALESCE(quantity,0)*COALESCE(price,0)) - COALESCE(amount_received,0)) AS balance,
      remark,created_at
    FROM event_items
    WHERE event_id={$event_id}
    ORDER BY item_id DESC
  ") ?: [];
  jok($rows);
}
elseif ($action === 'add_item') {
  $event_id = (int)($_POST['event_id'] ?? 0);
  $item_name = trim($_POST['item_name'] ?? '');
  $quantity = (float)($_POST['quantity'] ?? 0);
  $price = (float)($_POST['price'] ?? 0);
  $recv = (float)($_POST['amount_received'] ?? 0);
  if ($event_id<=0) jfail('event_id required'); if ($item_name==='') jfail('item_name required'); if ($quantity<0||$price<0||$recv<0) jfail('Invalid input');
  $total = $quantity*$price; $balance = $total - $recv;
  $ok = insData('event_items', [
    'event_id'=>$event_id,'item_name'=>$item_name,'quantity'=>$quantity,'price'=>$price,
    'total_amount'=>$total,'amount_received'=>$recv,'balance'=>$balance
  ]);
  if ($ok === false) jfail('Add item failed');
  jok();
}
elseif ($action === 'edit_item') {
  $item_id = (int)($_POST['item_id'] ?? 0); if ($item_id<=0) jfail('item_id required');
  $row = exeSql("SELECT * FROM event_items WHERE item_id={$item_id} LIMIT 1"); if (!$row) jfail('Item not found');

  $item_name = trim($_POST['item_name'] ?? $row[0]['item_name']);
  $quantity = array_key_exists('quantity',$_POST) ? (float)$_POST['quantity'] : (float)$row[0]['quantity'];
  $price = array_key_exists('price',$_POST) ? (float)$_POST['price'] : (float)$row[0]['price'];
  $recv = array_key_exists('amount_received',$_POST) ? (float)$_POST['amount_received'] : (float)$row[0]['amount_received'];
  $remark = array_key_exists('remark',$_POST) ? trim($_POST['remark']) : (string)$row[0]['remark'];

  $total = $quantity*$price; $balance = $total - $recv;

  $ok = upData('event_items', [
    'item_name'=>$item_name,'quantity'=>$quantity,'price'=>$price,
    'total_amount'=>$total,'amount_received'=>$recv,'balance'=>$balance,'remark'=>$remark
  ], ['item_id'=>$item_id]);

  // Only false is failure
  if ($ok === false) jfail('Update item failed');
  jok();
}
elseif ($action === 'delete_item') {
  $item_id = (int)($_POST['item_id'] ?? 0); if ($item_id<=0) jfail('item_id required');
  $ok = exeSql("DELETE FROM event_items WHERE item_id={$item_id}");
  if ($ok === false) jfail('Delete item failed');
  jok();
}

/* -------------------- INVALID -------------------- */
else {
  jfail('Invalid action');
}
