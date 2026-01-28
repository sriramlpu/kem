<?php
// Include your required functions
require_once('../../functions.php');  // This is your custom functions file
require_once('../../kem/functions.php');  // This is your custom functions file

session_start();

// Ensure the user is logged in as a salesperson
if (!isset($_SESSION['sales_person_id'])) {
    echo json_encode(['error' => 'Access Denied']);
    exit;
}

// Get the salesperson's ID from the session
$salesperson_id = $_SESSION['sales_person_id'];

// Fetch the salesperson's name from the session (assuming the name is stored in the session)
$salesperson_name = $_SESSION['sales_person_name'];  // Assuming the name is stored in the session

// Fetch dashboard stats using your custom function
$sql_stats = "
    SELECT
        COUNT(*) as total_leads,
        SUM(CASE WHEN workflow_stage IN ('LEAD_CREATED', 'SALES_DRAFT') THEN 1 ELSE 0 END) as draft_proposals,
        SUM(CASE WHEN workflow_stage = 'ADMIN_REVIEW' THEN 1 ELSE 0 END) as pending_admin,
        SUM(CASE WHEN workflow_stage = 'ADMIN_APPROVED' THEN 1 ELSE 0 END) as admin_approved,
        SUM(CASE WHEN workflow_stage = 'EXECUTIVE_REVIEW' THEN 1 ELSE 0 END) as pending_executive,
        SUM(CASE WHEN workflow_stage = 'EXECUTIVE_APPROVED' THEN 1 ELSE 0 END) as completed
    FROM clients
    WHERE assigned_to = :salesperson_id
";

// Prepare and execute the query
$stmt = exeSql($sql_stats, ['salesperson_id' => $salesperson_id]);

if ($stmt) {
    // Return the stats along with the salesperson's name as a JSON response
    $response = [
        'salesperson_name' => $salesperson_name,  // Ensure the name is returned
        'stats' => $stmt[0]
    ];
    echo json_encode($response);  // Return stats and name
} else {
    echo json_encode(['error' => 'Failed to fetch stats']);
}
?>
