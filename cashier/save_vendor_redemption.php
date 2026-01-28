<?php
// save_vendor_redemption.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST,OPTIONS');

$debug_mode = true;

$host = 'localhost';
$dbname = 'kmkglobal_web';
$username = 'kmkglobal_web';
$password = 'tI]rfPhdOo9zHdKw';

// small helper
function json_out($arr, $code = 200) {
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_out(['success' => true]);
}

try {
    // 1. collect data from POST / raw
    $data = [];

    if (!empty($_POST)) {
        $data = $_POST;
    } else {
        $raw = file_get_contents('php://input');
        if ($raw !== '') {
            $json = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $json;
            } else {
                parse_str($raw, $parsed);
                if (!empty($parsed)) {
                    $data = $parsed;
                }
            }
        }
    }

    if (empty($data)) {
        json_out([
            'success' => false,
            'message' => 'No data received',
            'debug'   => $debug_mode ? ['raw' => file_get_contents('php://input')] : null,
        ], 400);
    }

    // 2. validate
    $vendor_id = isset($data['vendor_id']) ? (int)$data['vendor_id'] : 0;
    $redemption_points = isset($data['redemption_points']) ? (float)$data['redemption_points'] : null;

    if ($vendor_id <= 0) {
        json_out([
            'success' => false,
            'message' => 'Missing or invalid vendor_id'
        ], 400);
    }

    if ($redemption_points === null || $redemption_points < 0) {
        json_out([
            'success' => false,
            'message' => 'Missing or invalid redemption_points'
        ], 400);
    }

    if ($redemption_points > 999999.99) {
        json_out([
            'success' => false,
            'message' => 'Points value too large'
        ], 400);
    }

    // 3. connect
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    $pdo->beginTransaction();

    // 4. ensure vendor exists
    $chkVendor = $pdo->prepare("SELECT vendor_id, vendor_name FROM vendors WHERE vendor_id = ?");
    $chkVendor->execute([$vendor_id]);
    $vendor = $chkVendor->fetch();
    if (!$vendor) {
        $pdo->rollBack();
        json_out([
            'success' => false,
            'message' => 'Vendor not found'
        ], 404);
    }

    // 5. check if vendor_totals row exists
    $chkTotal = $pdo->prepare("SELECT redemption_points FROM vendor_totals WHERE vendor_id = ?");
    $chkTotal->execute([$vendor_id]);
    $existing = $chkTotal->fetch();

    $old_points = 0.00;
    if ($existing) {
        $old_points = (float)$existing['redemption_points'];
        // UPDATE
        $upd = $pdo->prepare("
            UPDATE vendor_totals
            SET redemption_points = ?, updated_at = CURRENT_TIMESTAMP
            WHERE vendor_id = ?
        ");
        $upd->execute([$redemption_points, $vendor_id]);
        $message = 'Redemption points updated successfully';
    } else {
        // INSERT
        $ins = $pdo->prepare("
            INSERT INTO vendor_totals
            (vendor_id, total_bill, total_paid, balance, updated_at, advance, redemption_points)
            VALUES (?, 0.00, 0.00, 0.00, CURRENT_TIMESTAMP, 0.00, ?)
        ");
        $ins->execute([$vendor_id, $redemption_points]);
        $message = 'Redemption points added successfully';
    }

    // 6. read back
    $get = $pdo->prepare("SELECT redemption_points, updated_at FROM vendor_totals WHERE vendor_id = ?");
    $get->execute([$vendor_id]);
    $current = $get->fetch();

    $pdo->commit();

    $out = [
        'success' => true,
        'message' => $message,
        'vendor_id' => $vendor_id,
        'vendor_name' => $vendor['vendor_name'],
        'current_points' => isset($current['redemption_points']) ? (float)$current['redemption_points'] : (float)$redemption_points,
        'previous_points' => $old_points,
        'updated_at' => $current['updated_at'] ?? date('Y-m-d H:i:s'),
    ];

    if ($debug_mode) {
        $out['debug'] = [
            'operation' => $existing ? 'update' : 'insert',
            'received'  => $data,
            'method'    => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
        ];
    }

    json_out($out);

} catch (Throwable $e) {
    json_out([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'debug'   => $debug_mode ? [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ] : null
    ], 500);
}
