<?php
// get_vendors.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$host = 'localhost';
$dbname = 'kmkglobal_web';
$username = 'kmkglobal_web';
$password = 'tI]rfPhdOo9zHdKw';

try {
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

    // adjust this query if your vendors table has diff column names
    $stmt = $pdo->query("SELECT vendor_id, vendor_name FROM vendors ORDER BY vendor_name ASC");
    $vendors = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'message' => 'Vendors loaded successfully',
        'data' => $vendors,
        'count' => count($vendors)
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error'   => $e->getMessage()
    ]);
    exit;
}
