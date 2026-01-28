<?php
// KMK/events/api/client_sales_update_api.php

header('Content-Type: application/json');

// --- DATABASE CONNECTION CONFIGURATION (Local) ---
// Note: This API uses direct MySQLi prepared statements for security and stability.
$servername = "localhost";
$username  = "root";
$password  = "";
$dbname   = "kmk";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => "Connection failed: " . $conn->connect_error]);
  exit();
}

// --- REQUIRED GLOBAL TABLE DEFINITIONS (For functions.php, which is included below) ---
// The connection used for the included functions is assumed to be handled internally by functions.php's setup.
$clientsTable = 'clients'; 
$proposalItemsTable = 'proposal_items';
$servTable = 'service'; 
$distTable = 'districts';
$docsTable = 'doctors_executives';
$specTable = 'specialities';
$cityTable = 'cities';
$adminTable = 'admin_users';

if (!defined('TABLE_ISSUES')) define('TABLE_ISSUES', 'issues');
if (!defined('TABLE_REPLIES')) define('TABLE_REPLIES', 'issue_replies');
if (!defined('TABLE_STATUS')) define('TABLE_STATUS', 'issue_status');
if (!defined('TABLE_ITEMS')) define('TABLE_ITEMS', 'proposal_items');

// --- CORRECTED INCLUDE PATH ---
// Path: KMK/events/api/ --> KMK/functions.php (Two levels up)
require_once('../../functions.php'); 

// -------------------- 2. INPUT VALIDATION --------------------
$client_id = $_POST['client_id'] ?? null;
$budget_draft_sales = $_POST['budget_draft_sales'] ?? null;
$sales_notes = $_POST['sales_notes'] ?? '';

if (!$client_id || !is_numeric($client_id)) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => "Invalid Client ID provided."]);
  $conn->close();
  exit();
}

$budget_draft_sales = filter_var($budget_draft_sales, FILTER_VALIDATE_FLOAT); 

if ($budget_draft_sales === false || $budget_draft_sales < 0) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => "Proposed Budget must be a non-negative numeric value."]);
  $conn->close();
  exit();
}

// -------------------- 2.1. WORKFLOW STAGE CHECK --------------------
$sql_check = "SELECT workflow_stage FROM clients WHERE client_id = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("i", $client_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$client_row = $result_check->fetch_assoc();
$stmt_check->close();

if (!$client_row) {
  http_response_code(404);
  echo json_encode(['success' => false, 'error' => "Client not found."]);
  $conn->close();
  exit();
}

$current_stage = $client_row['workflow_stage'];
$allowed_stages = ['LEAD_CREATED', 'SALES_DRAFT', 'REJECTED']; 

if (!in_array($current_stage, $allowed_stages)) {
  http_response_code(403);
  echo json_encode(['success' => false, 'error' => "Proposal is currently in **{$current_stage}** and cannot be edited or sent for review."]);
  $conn->close();
  exit();
}

// New workflow status is Admin Review, and admin_status is PENDING
$new_workflow_stage = 'ADMIN_REVIEW';
$admin_status = 'PENDING';

// -------------------- 3. COLLECT FORM DATA --------------------
$services_required = isset($_POST['services_required']) ? implode(', ', $_POST['services_required']) : '';
$decor_type = $_POST['decor_type'] ?? '';
$food_category = $_POST['food_category'] ?? '';
$food_veg_standard = $_POST['food_veg_standard'] ?? '';
$food_nonveg_standard = $_POST['food_nonveg_standard'] ?? '';
$logistics_services = $_POST['logistics_services'] ?? '';
$vas_requirement = $_POST['vas_requirement'] ?? '';
$expected_budget = $_POST['expected_budget'] ?? '';

$office_event_venue = $_POST['office_event_venue'] ?? '';
$office_venue_size = $_POST['office_venue_size'] ?? '';
$office_meal_time = $_POST['office_meal_time'] ?? '';

// Boolean fields (converted to 1/0 integer)
$tandoor_allowance = (($_POST['office_tandoor_allowance'] ?? 'No') === 'Yes') ? 1 : 0;
$live_cooking = (($_POST['office_live_cooking'] ?? 'No') === 'Yes') ? 1 : 0;
$wash_area = (($_POST['office_wash_area'] ?? 'No') === 'Yes') ? 1 : 0;
$garbage_disposal = (($_POST['office_garbage_disposal'] ?? 'No') === 'Yes') ? 1 : 0;

$office_food_service = $_POST['office_food_service'] ?? '';
$office_pankhi_service = $_POST['office_pankhi_service'] ?? '';
$office_cocktail = $_POST['office_cocktail'] ?? '';
$office_drivers_counter = $_POST['office_drivers_counter'] ?? '';
$office_kst_team = $_POST['office_kst_team'] ?? '';
$office_next_meeting_date = empty($_POST['office_next_meeting_date']) ? null : $_POST['office_next_meeting_date'];
$office_client_category = $_POST['office_client_category'] ?? '';
$office_lead_owner = $_POST['office_lead_owner'] ?? '';
$office_signature_date = $_POST['office_signature_date'] ?? '';

$resource1_name = $_POST['resource1_name'] ?? '';
$resource1_address = $_POST['resource1_address'] ?? '';
$resource1_phone1 = $_POST['resource1_phone1'] ?? '';
$resource1_phone2 = $_POST['resource1_phone2'] ?? '';
$resource1_phone3 = $_POST['resource1_phone3'] ?? '';
$resource2_name = $_POST['resource2_name'] ?? '';
$resource2_address = $_POST['resource2_address'] ?? '';
$resource2_phone1 = $_POST['resource2_phone1'] ?? '';
$resource2_phone2 = $_POST['resource2_phone2'] ?? '';
$resource2_phone3 = $_POST['resource2_phone3'] ?? '';

// -------------------- 4. DATABASE UPDATE --------------------
$sql = "UPDATE clients SET 
  budget_draft_sales = ?, 
  sales_notes = ?, 
  admin_status = ?,
  workflow_stage = ?,
  services_required = ?, 
  decor_type = ?, 
  food_category = ?, 
  food_veg_standard = ?, 
  food_nonveg_standard = ?, 
  logistics_services = ?, 
  vas_requirement = ?, 
  expected_budget = ?, 
  office_event_venue = ?, 
  office_venue_size = ?, 
  office_meal_time = ?, 
  office_tandoor_allowance = ?, 
  office_live_cooking = ?, 
  office_wash_area = ?, 
  office_garbage_disposal = ?, 
  office_food_service = ?, 
  office_pankhi_service = ?, 
  office_cocktail = ?, 
  office_drivers_counter = ?, 
  office_kst_team = ?, 
  office_next_meeting_date = ?, 
  office_client_category = ?, 
  office_lead_owner = ?, 
  office_signature_date = ?, 
  resource1_name = ?, 
  resource1_address = ?, 
  resource1_phone1 = ?, 
  resource1_phone2 = ?, 
  resource1_phone3 = ?, 
  resource2_name = ?, 
  resource2_address = ?, 
  resource2_phone1 = ?, 
  resource2_phone2 = ?, 
  resource2_phone3 = ?
WHERE client_id = ?";

// Type string: dssssssssssssiiiisssssssssssssssssssssi (39 parameters)
$types = "dssssssssssssiiiisssssssssssssssssssssi";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => "Error preparing statement: " . $conn->error]);
  $conn->close();
  exit();
}

$stmt->bind_param(
  $types,
  $budget_draft_sales,
  $sales_notes,
  $admin_status,
  $new_workflow_stage, 
  $services_required,
  $decor_type,
  $food_category,
  $food_veg_standard,
  $food_nonveg_standard,
  $logistics_services,
  $vas_requirement,
  $expected_budget,
  $office_event_venue,
  $office_venue_size,
  $office_meal_time,
  $tandoor_allowance,
  $live_cooking,
  $wash_area,
  $garbage_disposal,
  $office_food_service,
  $office_pankhi_service,
  $office_cocktail,
  $office_drivers_counter,
  $office_kst_team,
  $office_next_meeting_date,
  $office_client_category,
  $office_lead_owner,
  $office_signature_date,
  $resource1_name,
  $resource1_address,
  $resource1_phone1,
  $resource1_phone2,
  $resource1_phone3,
  $resource2_name,
  $resource2_address,
  $resource2_phone1,
  $resource2_phone2,
  $resource2_phone3,
  $client_id
);

if ($stmt->execute()) {
  if ($stmt->affected_rows > 0) {
    echo json_encode([
      'success' => true, 
      'message' => "Proposal for Client #{$client_id} updated and status changed to **Admin Review**! ✅"
    ]);
  } else {
    echo json_encode([
      'success' => true, 
      'message' => "Proposal saved, but no major changes were detected. The status is now **Admin Review**."
    ]);
  }
} else {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => "DB Execution Error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>