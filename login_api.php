<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once("functions.php");

// Read JSON body
$data     = json_decode(file_get_contents("php://input"), true);
$username = isset($data['username']) ? trim($data['username']) : '';
$password = isset($data['password']) ? $data['password'] : '';

if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(["error" => "Username and password are required."]);
    exit;
}


$sql  = "SELECT * FROM users WHERE email = '$username' LIMIT 1";
$user = exeSql($sql);

if ($user && count($user) > 0) {
    $userRow = $user[0];

    // Verify password hash
    if (password_verify($password, $userRow['password'])) {
        $roleId   = $userRow['role_id'];
        $roleRow  = getValues('roles', "role_id = '$roleId'");
        $roleName = isset($roleRow['role_name']) ? trim($roleRow['role_name']) : '';

        // Set session
        $_SESSION['userId']   = $userRow['user_id'];
        $_SESSION['userName'] = $userRow['username'];
        $_SESSION['roleName'] = $roleName;

        // Map role -> redirect (supports existing ones)
        switch (strtolower($roleName)) {
            case 'admin':
                $redirect = './admin/main_dashboard.php';
                break;

            case 'branch':
                $redirect = './branch/create_indent.php';
                break;

            case 'inventory':
                $redirect = './inventory/index.php';
                break;

            case 'finance': 
                $redirect = './finance/index.php';
                break;

            case 'mdpanel': 
                $redirect = './mdpanel/index.php';
                break;
            
            case 'requester': 
                $redirect = './admin/dashboard.php';
                break;

            // If you still have this older/combo role name in DB, send it to finance panel
            case 'accounts and procurement':
                $redirect = './finance/index.php';
                break;
            case 'approver':
                $redirect = './approver/dashboard';
                break;
            case 'cashier':
                $redirect = './cashier/dashboard';
                break;
            case 'salesperson':
                $redirect = './events/sales-person';
                break;
            case 'executive manager':
                $redirect = './events/executive-manager/dashboard';
                break;
            case 'budget manager':
                $redirect = './events/budget-manager';
                break;
            

            default:
                // Safe fallback
                $redirect = './index.php';
                break;
        }

        echo json_encode([
            "message" => "Login successful",
            "user" => [
                "userId"   => $userRow['user_id'],
                "username" => $userRow['username'],
                "role"     => $roleName,
                "redirect" => $redirect
            ]
        ]);
        exit;
    }

    http_response_code(401);
    echo json_encode(["error" => "Invalid credentials"]);
    exit;
}

http_response_code(401);
echo json_encode(["error" => "Invalid credentials"]);
