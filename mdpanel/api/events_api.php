<?php
header('Content-Type: application/json');
require_once('../../functions.php');
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

// Helper: sanitize regex input to allow only safe characters
function sanitize_regex($input) {
    return preg_replace('/[^a-zA-Z0-9\s\-]/', '', $input);
}

// LIST events
if ($action === 'getEvents') {
    $draw = intval($_POST['draw'] ?? 1);
    $start = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 10);
    $where = "WHERE 1=1";
    
    // Apply branch filter
    if (!empty($_POST['branch_id'])) {
        $branch_id = intval($_POST['branch_id']);
        $where .= " AND e.branch_id = $branch_id";
    }
    
    // Apply date filters
    if (!empty($_POST['start_date'])) {
        $start_date = $_POST['start_date'];
        $where .= " AND e.event_date >= '$start_date'";
    }
    
    if (!empty($_POST['end_date'])) {
        $end_date = $_POST['end_date'];
        $where .= " AND e.event_date <= '$end_date'";
    }
    
    // Apply event type filter
    if (!empty($_POST['event_type'])) {
        $event_type = sanitize_regex($_POST['event_type']);
        $where .= " AND e.event_type = '$event_type'";
    }
    
    // Regex-safe text search filter
    if (!empty($_POST['filter_search'])) {
        $search = sanitize_regex($_POST['filter_search']);
        if ($search !== '') {
            $where .= " AND (e.event_name REGEXP '$search' OR e.event_type REGEXP '$search' OR b.branch_name REGEXP '$search')";
        }
    }
    
    // Total records (before filtering)
    $totalRecords = getCount("events e");
    
    // Total records (after filtering)
    $totalFilteredRow = exeSql("SELECT COUNT(*) as total FROM events e 
                               LEFT JOIN branches b ON e.branch_id = b.branch_id $where");
    $totalFiltered = $totalFilteredRow[0]['total'];
    
    // Fetch filtered data with pagination
    $sqlData = exeSql("SELECT e.event_id, e.event_date, e.event_name, e.event_type, 
                              b.branch_name, 
                              (SELECT COUNT(*) FROM event_attendees WHERE event_id = e.event_id) AS attendees_count,
                              e.status
                       FROM events e
                       LEFT JOIN branches b ON e.branch_id = b.branch_id
                       $where 
                       ORDER BY e.event_id DESC 
                       LIMIT $start, $length");
    
    $data = [];
    $sno = $start + 1;
    foreach ($sqlData as $row) {
        $row['sno'] = $sno++;
        $row['event_id'] = $row['event_id'];
        $data[] = $row;
    }
    
    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => intval($totalRecords),
        "recordsFiltered" => intval($totalFiltered),
        "data" => $data
    ]);
    exit;
}

// GET event details
if ($action === 'getEventDetails') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['status'=>'error', 'message'=>'ID required']);
        exit;
    }
    
    // Get event details
    $event = exeSql("SELECT e.event_id, e.event_date, e.event_name, e.event_type, 
                            b.branch_name, e.description, e.status
                     FROM events e
                     LEFT JOIN branches b ON e.branch_id = b.branch_id
                     WHERE e.event_id = $id");
    
    if (!$event) {
        echo json_encode(['status'=>'error','message'=>'Event not found']);
        exit;
    }
    
    // Get attendees count
    $attendeesCount = getCount("event_attendees", "event_id = $id");
    
    // Get attendees list
    $attendees = exeSql("SELECT attendee_name AS name, email, phone, registration_date 
                        FROM event_attendees 
                        WHERE event_id = $id 
                        ORDER BY registration_date");
    
    echo json_encode([
        'status'=>'success',
        'data' => [
            'event_id' => $event[0]['event_id'],
            'event_date' => $event[0]['event_date'],
            'event_name' => $event[0]['event_name'],
            'event_type' => $event[0]['event_type'],
            'branch_name' => $event[0]['branch_name'],
            'description' => $event[0]['description'],
            'status' => $event[0]['status'],
            'attendees_count' => $attendeesCount,
            'attendees' => $attendees
        ]
    ]);
    exit;
}

// GET branches for dropdown
if ($action === 'getBranches') {
    $branches = exeSql("SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
    echo json_encode(['status'=>'success','data'=>$branches]);
    exit;
}

echo json_encode(['status'=>'error', 'message'=>'Invalid action']);
?>