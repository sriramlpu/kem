<?php
header('Content-Type: application/json');
require_once('../../functions.php');
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

// Helper: sanitize regex input to allow only safe characters
function sanitize_regex($input)
{
    return preg_replace('/[^a-zA-Z0-9\s\-]/', '', $input);
}

if ($action === 'list') {
    $draw = intval($_POST['draw'] ?? 1);
    $start = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 10);
    $where = "WHERE 1=1";

    // Regex-safe text search filter
    if (!empty($_POST['filter_search'])) {
        $search = sanitize_regex($_POST['filter_search']);
        if ($search !== '') {
            $where .= " AND (u.username REGEXP '$search' OR u.email REGEXP '$search' OR r.role_name REGEXP '$search')";
        }
    }

    // Total records (before filtering)
    $totalRecords = getCount("users");

    // Total records (after filtering)
    $totalFilteredRow = exeSql("SELECT COUNT(*) as total FROM users u LEFT JOIN roles r ON u.role_id = r.role_id $where");
    $totalFiltered = $totalFilteredRow[0]['total'];

    // Fetch filtered data with pagination
    $sqlData = exeSql("SELECT u.user_id AS id, u.username, u.email, u.phone, u.status, u.created_at, u.last_login_at, r.role_name, b.branch_name as branch_name FROM users u LEFT JOIN roles r ON u.role_id = r.role_id
    LEFT JOIN branches b on u.branch_id = b.branch_id
     $where ORDER BY u.user_id DESC LIMIT $start, $length");

    $data = [];
    $sno = $start + 1;
    foreach ($sqlData as $row) {
        $row['sno'] = $sno++;
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

// CREATE user
if ($action === 'create') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role_id = intval($_POST['role_id'] ?? 0);
    $dept_id = intval($_POST['dept_id'] ?? 0);
    $branch_id = intval($_POST['branch_id'] ?? 0);
    $phone = intval($_POST['phone'] ?? 0);
    $status = trim($_POST['status'] ?? 'active');

    if (!$username || !$email) {
        echo json_encode(['status' => 'error', 'message' => 'Username, email and password are required']);
        exit;
    }

    // Check for duplicate username
    $getCountUsername = getCount("users", "username = '$username'");
    if ($getCountUsername > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Username already exists']);
        exit;
    }

    // Check for duplicate email
    $getCountEmail = getCount("users", "email = '$email'");
    if ($getCountEmail > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Email already exists']);
        exit;
    }

    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = excuteSql("INSERT INTO users (username, email, phone, role_id, status, password) VALUES ('$username', '$email', '$phone', '$role_id',  '$status', '$password_hash')");

    if ($stmt) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Insert failed']);
    }
    exit;
}

// EDIT user
if ($action === 'edit') {
    $id = intval($_POST['id']);
    $username = $_POST['username'];
    $email = $_POST['email'];
    // $password = $_POST['password'];
    $role_id = intval($_POST['role_id'] ?? 0);
    $dept_id = intval($_POST['dept_id'] ?? 0);
    $status = $_POST['status'];

    // Check for duplicate username (excluding current record)
    $getCountUsername = getCount("users", "username = '$username' AND user_id != $id");
    if ($getCountUsername > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Username already exists']);
        exit;
    }

    // Check for duplicate email (excluding current record)
    $getCountEmail = getCount("users", "email = '$email' AND user_id != $id");
    if ($getCountEmail > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Email already exists']);
        exit;
    }

    // Build update query
    $updateFields = "username='$username', email='$email', role_id='$role_id', status='$status', dept_id = '$dept_id'";

    // Only update password if provided
    // if (!empty($password)) {
    //     $password_hash = password_hash($password, PASSWORD_DEFAULT);
    //     $updateFields .= ", password_hash='$password_hash'";
    // }

    $sql = excuteSql("UPDATE users SET $updateFields WHERE user_id='$id'");
    if ($sql) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Update failed']);
    }
    exit;
}

// DELETE user
if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['status' => 'error', 'message' => 'ID required']);
        exit;
    }

    $sql = excuteSql("DELETE FROM users WHERE user_id='$id'");
    if ($sql) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Delete failed']);
    }
    exit;
}

// FETCH single user
if ($action === 'getUser') {
    $id = intval($_POST['id']);
    $user = getRowValues('users', $id, 'user_id');
    if ($user) {
        // Add an 'id' field that matches what the frontend expects
        $user['id'] = $user['user_id'];
        echo json_encode(['status' => 'success', 'data' => $user]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
    }
    exit;
}

if ($action === 'getUsers') {

    $user = getSubject('users');
    if ($user) {
        echo json_encode(['status' => 'success', 'data' => $user]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
    }
    exit;
}

