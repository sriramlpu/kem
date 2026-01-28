<?php
// KMK/events/sales_person/client_sales_update

// Start session at the very top
session_start();

// --- REQUIRED GLOBAL TABLE DEFINITIONS ---
// These global variables must be defined BEFORE including functions
$clientsTable = 'clients'; 
$proposalItemsTable = 'proposal_items';

// Placeholder table names for functions (if they are global)
$servTable = 'service'; 
$distTable = 'districts';
$docsTable = 'doctors_executives';
$specTable = 'specialities';
$cityTable = 'cities';
$adminTable = 'admin_users';

// Constants assumed by your functions for context (define if necessary)
if (!defined('TABLE_ISSUES')) define('TABLE_ISSUES', 'issues');
if (!defined('TABLE_REPLIES')) define('TABLE_REPLIES', 'issue_replies');
if (!defined('TABLE_STATUS')) define('TABLE_STATUS', 'issue_status');
if (!defined('TABLE_ITEMS')) define('TABLE_ITEMS', 'proposal_items'); 


// --- INCLUDE CORRECTED FUNCTIONS PATH ---
// Path: KMK/events/sales_person/ --> KMK/functions (Two levels up)
require_once('../../functions.php'); 

// -------------------- 1. DATABASE CONNECTION CONFIGURATION --------------------
// This mysqli connection is used for initial data fetching and listing only.
$servername = "localhost";
$username   = "kmkglobal_web";
$password   = "tI]rfPhdOo9zHdKw";
$dbname     = "kmkglobal_web";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = ""; 
$client_data = null;
$client_id = $_GET['client_id'] ?? null; 
$is_editable = false; // Flag to control form submission button visibility

// -------------------- 2. DATA RETRIEVAL (Initial Page Load) --------------------
if ($client_id) {
    $sql_fetch = "SELECT *, workflow_stage FROM clients WHERE client_id = ?"; 
    $stmt_fetch = $conn->prepare($sql_fetch);
    $stmt_fetch->bind_param("i", $client_id); 
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();
    
    if ($result_fetch->num_rows === 1) {
        $client_data = $result_fetch->fetch_assoc();
        
        $editable_stages = ['LEAD_CREATED', 'SALES_DRAFT', 'REJECTED'];
        if (in_array($client_data['workflow_stage'], $editable_stages)) {
            $is_editable = true;
        } else {
            $message = "<div class='alert alert-info error-alert'>ℹ️ **Proposal is currently in **{$client_data['workflow_stage']}**. The Save/Submit button is hidden as no major changes should be made at this stage.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger error-alert'>❌ Error: Client ID not found.</div>";
        $client_id = null;
    }
    $stmt_fetch->close();
}

// Fetch list of clients for the dropdown selector
$client_list = [];
if (!$client_id) {
    $sql_list = "SELECT client_id, client_name, date_time_of_event, workflow_stage FROM clients ORDER BY client_name ASC"; 
    $result_list = $conn->query($sql_list);
    if ($result_list) {
        while ($row = $result_list->fetch_assoc()) {
            $client_list[] = $row;
        }
    }
}

$conn->close();

// --- HELPER FUNCTIONS ---
function is_checked($field, $value, $data_key, $client_data) {
    if ($client_data && isset($client_data[$data_key]) && !empty($client_data[$data_key])) {
        $db_values = explode(', ', $client_data[$data_key]);
        return in_array($value, $db_values) ? 'checked' : '';
    }
    return '';
}

function is_radio_selected($field, $value, $data_key, $client_data) {
    if ($client_data && isset($client_data[$data_key]) && $client_data[$data_key] !== '') {
        // Handle boolean fields stored as 1/0
        if (in_array($data_key, ['office_tandoor_allowance', 'office_live_cooking', 'office_wash_area', 'office_garbage_disposal'])) {
            $db_value = ($client_data[$data_key] == 1) ? 'Yes' : 'No';
            return ($db_value == $value) ? 'checked' : '';
        }
        return ($client_data[$data_key] == $value) ? 'checked' : '';
    }
    return '';
}

function get_value($data_key, $client_data) {
    if ($client_data && isset($client_data[$data_key])) {
        if (in_array($data_key, ['office_next_meeting_date']) && ($client_data[$data_key] == '0000-00-00' || $client_data[$data_key] == '0000-00-00 00:00:00')) {
             return '';
        }
        return htmlspecialchars($client_data[$data_key]);
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Update | <?php echo $client_data ? 'Client: ' . get_value('client_name', $client_data) : 'Select Client'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --success-color: #27ae60;
            --border-color: #dee2e6;
        }
        body { 
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 20px 0;
        }
        .container { 
            max-width: 1200px; 
            margin: 20px auto; 
            background-color: white; 
            border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .form-header { 
            background: linear-gradient(135deg, var(--primary-color) 0%, #00aaff 100%);
            color: white; 
            padding: 25px 30px;
            text-align: center;
        }
        .form-header h1 { font-weight: 700; margin: 0; font-size: 2.2rem; text-shadow: 1px 1px 3px rgba(0,0,0,0.3); }
        form { padding: 0 30px 30px; }
        fieldset { 
            border: 2px solid var(--border-color); 
            border-radius: 10px; 
            padding: 20px; 
            margin-bottom: 30px; 
            background: #fff;
        }
        legend { 
            font-weight: 700; 
            font-size: 1.3rem; 
            color: var(--secondary-color); 
            width: auto; 
            padding: 0 15px; 
            margin-left: -15px;
        }
        .form-label { font-weight: 600; color: var(--secondary-color); margin-bottom: 8px; }
        .form-check-inline { margin-right: 1.5rem; margin-bottom: 10px; }
        .btn-success { 
            background: linear-gradient(135deg, var(--success-color) 0%, #2ecc71 100%); 
            border: none; 
            border-radius: 50px; 
            padding: 12px 40px; 
            font-weight: 700; 
            font-size: 1.1rem;
        }
        .error-alert, .success-alert { 
            position: fixed; 
            top: 20px; 
            right: 20px; 
            z-index: 1050; 
            max-width: 400px; 
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        #message-box { position: fixed; top: 20px; right: 20px; z-index: 1050; }
        .readonly-value { 
            background-color: #f1f1f1; 
            padding: 8px 12px; 
            border-radius: 6px; 
            border: 1px solid #ddd; 
            display: block;
        }
        .border-end { border-right: 2px dashed var(--border-color) !important; }
    </style>
</head>
<body>
    
    <div id="message-box">
        <?php echo $message; ?>
    </div>

<div class="container">
    <div class="form-header mb-4">
        <h1>Sales Proposal Management</h1>
        <p class="lead mb-0">Update the proposal details for the selected client.</p>
    </div>

    <?php if (!$client_id || !$client_data): ?>
        
        <div class="card p-4 m-4">
            <h3 class="card-title text-center">Select a Client to Update</h3>
            <p class="text-center text-muted">Choose a client who needs a proposal or revision.</p>
            <form method="GET" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="input-group mb-3">
                    <select class="form-select" name="client_id" required>
                        <option value="">-- Select Client --</option>
                        <?php foreach ($client_list as $client): ?>
                            <?php 
                                $date = date('M d, Y', strtotime($client['date_time_of_event']));
                                $label = "ID: {$client['client_id']} - {$client['client_name']} ({$client['workflow_stage']}, Event: {$date})";
                            ?>
                            <option value="<?php echo htmlspecialchars($client['client_id']); ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary" type="submit">Load Client</button>
                </div>
            </form>
        </div>

    <?php else: ?>
    
        <form id="salesUpdateForm" method="POST" onsubmit="submitProposal(event);">
            <input type="hidden" name="client_id" value="<?php echo htmlspecialchars($client_id); ?>">

            <h2 class="text-secondary mb-4 mt-4">Editing Proposal for: <span class="text-primary"><?php echo get_value('client_name', $client_data); ?></span></h2>
            
            <div class="mb-4">
                <span class="badge bg-primary fs-5">Current Status: <?php echo get_value('workflow_stage', $client_data); ?></span>
            </div>


            <fieldset class="mb-4 bg-light">
                <legend class="bg-light">Lead Data (Read-Only)</legend>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Client Name:</label>
                        <span class="readonly-value"><?php echo get_value('client_name', $client_data); ?></span>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contact No.:</label>
                        <span class="readonly-value"><?php echo get_value('contact_no', $client_data); ?></span>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Lead Status:</label>
                        <span class="readonly-value"><?php echo get_value('lead_status', $client_data); ?></span>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Lead Source:</label>
                        <span class="readonly-value"><?php echo get_value('lead_source', $client_data); ?></span>
                    </div>
                </div>
            </fieldset>

            <fieldset class="mb-4 bg-light">
                <legend class="bg-light">Event Overview (Read-Only)</legend>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Date/Time of Event:</label>
                        <span class="readonly-value"><?php echo get_value('date_time_of_event', $client_data); ?></span>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Event Type:</label>
                        <span class="readonly-value"><?php echo get_value('event_type', $client_data); ?></span>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Location Address:</label>
                        <span class="readonly-value"><?php echo get_value('location_address', $client_data); ?></span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Function details:</label>
                    <span class="readonly-value"><?php echo get_value('function_details', $client_data); ?></span>
                </div>
            </fieldset>

            <fieldset class="mb-4 bg-white">
                <legend class="text-primary">Sales & Budget Proposal</legend>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="expected_budget" class="form-label">Client's Expected Budget:</label>
                        <input type="text" class="form-control" name="expected_budget" id="expected_budget" 
                               value="<?php echo get_value('expected_budget', $client_data); ?>" 
                               placeholder="e.g., 5,00,000 INR">
                    </div>
                    <div class="col-md-6">
                        <label for="budget_draft_sales" class="form-label">Sales Proposed Budget (Numeric) *:</label>
                        <input type="number" step="0.01" class="form-control" name="budget_draft_sales" id="budget_draft_sales" 
                               value="<?php echo get_value('budget_draft_sales', $client_data); ?>" 
                               placeholder="Enter amount (e.g., 500000)" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="sales_notes" class="form-label">Sales Proposal Notes:</label>
                    <textarea class="form-control" id="sales_notes" name="sales_notes" rows="3" placeholder="Explain the key services and justification."><?php echo get_value('sales_notes', $client_data); ?></textarea>
                </div>
            </fieldset>

            <fieldset class="mb-4">
                <legend>Event Particulars</legend>
                <div class="mb-3">
                    <label class="form-label">Services Required:</label><br>
                    <?php 
                        $service_options = ['Decor', 'Food', 'Logistics', 'VAS'];
                        foreach ($service_options as $option):
                    ?>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="services_required[]" value="<?php echo $option; ?>" id="service_<?php echo strtolower($option); ?>" <?php echo is_checked('services_required', $option, 'services_required', $client_data); ?>> 
                        <label class="form-check-label" for="service_<?php echo strtolower($option); ?>"><?php echo $option; ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Decor Type:</label><br>
                        <?php 
                            $decor_options = ['A', 'B', 'C', 'Customization'];
                            foreach ($decor_options as $option):
                        ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="decor_type" value="<?php echo $option; ?>" id="decor_<?php echo strtolower($option); ?>" <?php echo is_radio_selected('decor_type', $option, 'decor_type', $client_data); ?>> 
                            <label class="form-check-label" for="decor_<?php echo strtolower($option); ?>"><?php echo $option; ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Food Category:</label><br>
                        <?php 
                            $food_options = ['Vegetarian', 'Non-Vegetarian'];
                            foreach ($food_options as $option):
                        ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="food_category" value="<?php echo $option; ?>" id="food_<?php echo strtolower(str_replace('-', '', $option)); ?>" <?php echo is_radio_selected('food_category', $option, 'food_category', $client_data); ?>> 
                            <label class="form-check-label" for="food_<?php echo strtolower(str_replace('-', '', $option)); ?>"><?php echo $option; ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Vegetarian Food Standard:</label><br>
                        <?php 
                            $standard_options = ['Standard', 'Gold', 'Diamond'];
                            foreach ($standard_options as $option):
                        ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="food_veg_standard" value="<?php echo $option; ?>" id="veg_<?php echo strtolower($option); ?>" <?php echo is_radio_selected('food_veg_standard', $option, 'food_veg_standard', $client_data); ?>> 
                            <label class="form-check-label" for="veg_<?php echo strtolower($option); ?>"><?php echo $option; ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Non-Veg Food Standard:</label><br>
                        <?php 
                            foreach ($standard_options as $option):
                        ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="food_nonveg_standard" value="<?php echo $option; ?>" id="nonveg_<?php echo strtolower($option); ?>" <?php echo is_radio_selected('food_nonveg_standard', $option, 'food_nonveg_standard', $client_data); ?>> 
                            <label class="form-check-label" for="nonveg_<?php echo strtolower($option); ?>"><?php echo $option; ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="logistics_services" class="form-label">Logistics Services:</label>
                    <textarea class="form-control" id="logistics_services" name="logistics_services" rows="2"><?php echo get_value('logistics_services', $client_data); ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="vas_requirement" class="form-label">VAS Requirement:</label>
                    <textarea class="form-control" id="vas_requirement" name="vas_requirement" rows="2"><?php echo get_value('vas_requirement', $client_data); ?></textarea>
                </div>
            </fieldset>

            <fieldset class="mb-4">
                <legend class="text-danger">For Office Use Only</legend>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Event Venue (Office):</label><br>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="office_event_venue" value="Home" id="venue_home" <?php echo is_radio_selected('office_event_venue', 'Home', 'office_event_venue', $client_data); ?>> 
                            <label class="form-check-label" for="venue_home">Home</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="office_event_venue" value="Clubhouse" id="venue_clubhouse" <?php echo is_radio_selected('office_event_venue', 'Clubhouse', 'office_event_venue', $client_data); ?>> 
                            <label class="form-check-label" for="venue_clubhouse">Clubhouse</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="office_venue_size" class="form-label">Venue Size:</label>
                        <input type="text" class="form-control" name="office_venue_size" id="office_venue_size" value="<?php echo get_value('office_venue_size', $client_data); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="office_meal_time" class="form-label">Meal Time:</label>
                        <input type="text" class="form-control" name="office_meal_time" id="office_meal_time" value="<?php echo get_value('office_meal_time', $client_data); ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <?php 
                        $binary_options = ['Tandoor Allowance' => 'office_tandoor_allowance', 'Live-Cooking' => 'office_live_cooking', 'Wash Area' => 'office_wash_area', 'Garbage Disposal' => 'office_garbage_disposal'];
                        foreach ($binary_options as $label => $name):
                    ?>
                    <div class="col-md-3">
                        <label class="form-label"><?php echo $label; ?>:</label><br>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="<?php echo $name; ?>" value="Yes" id="<?php echo $name; ?>_yes" <?php echo is_radio_selected($name, 'Yes', $name, $client_data); ?>> 
                            <label class="form-check-label" for="<?php echo $name; ?>_yes">Yes</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="<?php echo $name; ?>" value="No" id="<?php echo $name; ?>_no" <?php echo is_radio_selected($name, 'No', $name, $client_data); ?>> 
                            <label class="form-check-label" for="<?php echo $name; ?>_no">No</label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Food Service:</label><br>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="office_food_service" value="Yes" id="food_service_yes" <?php echo is_radio_selected('office_food_service', 'Yes', 'office_food_service', $client_data); ?>> 
                            <label class="form-check-label" for="food_service_yes">Yes</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="office_food_service" value="No" id="food_service_no" <?php echo is_radio_selected('office_food_service', 'No', 'office_food_service', $client_data); ?>> 
                            <label class="form-check-label" for="food_service_no">No</label>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">F&K Pandal/Table/Chairs:</label><br>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="office_pankhi_service" value="Buffet" id="service_buffet" <?php echo is_radio_selected('office_pankhi_service', 'Buffet', 'office_pankhi_service', $client_data); ?>> 
                            <label class="form-check-label" for="service_buffet">Buffet</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="office_pankhi_service" value="Pankhi" id="service_pankhi" <?php echo is_radio_selected('office_pankhi_service', 'Pankhi', 'office_pankhi_service', $client_data); ?>> 
                            <label class="form-check-label" for="service_pankhi">Pankhi</label>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="office_cocktail" class="form-label">Cocktail:</label>
                        <input type="text" class="form-control" name="office_cocktail" id="office_cocktail" value="<?php echo get_value('office_cocktail', $client_data); ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="office_drivers_counter" class="form-label">Drivers Counter:</label>
                        <input type="text" class="form-control" name="office_drivers_counter" id="office_drivers_counter" value="<?php echo get_value('office_drivers_counter', $client_data); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="office_kst_team" class="form-label">KST Team:</label>
                        <input type="text" class="form-control" name="office_kst_team" id="office_kst_team" value="<?php echo get_value('office_kst_team', $client_data); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="office_next_meeting_date" class="form-label">Next Meeting Date:</label>
                        <input type="date" class="form-control" name="office_next_meeting_date" id="office_next_meeting_date" value="<?php echo get_value('office_next_meeting_date', $client_data); ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="office_client_category" class="form-label">Client Category:</label>
                        <input type="text" class="form-control" name="office_client_category" id="office_client_category" value="<?php echo get_value('office_client_category', $client_data); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="office_lead_owner" class="form-label">Lead Owner:</label>
                        <input type="text" class="form-control" name="office_lead_owner" id="office_lead_owner" value="<?php echo get_value('office_lead_owner', $client_data); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="office_signature_date" class="form-label">Signature with Date:</label>
                        <input type="text" class="form-control" name="office_signature_date" id="office_signature_date" value="<?php echo get_value('office_signature_date', $client_data); ?>">
                    </div>
                </div>
            </fieldset>

            <fieldset class="mb-4">
                <legend>Approachable Resources</legend>
                <div class="row">
                    <div class="col-md-6 border-end">
                        <h5 class="h6">Resource 1</h5>
                        <div class="mb-3">
                            <label for="resource1_name" class="form-label">Name 1:</label>
                            <input type="text" class="form-control" name="resource1_name" id="resource1_name" value="<?php echo get_value('resource1_name', $client_data); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="resource1_address" class="form-label">Address 1:</label>
                            <textarea class="form-control" name="resource1_address" id="resource1_address" rows="2"><?php echo get_value('resource1_address', $client_data); ?></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4">
                                <label for="resource1_phone1" class="form-label">Phone 1:</label>
                                <input type="text" class="form-control" name="resource1_phone1" id="resource1_phone1" value="<?php echo get_value('resource1_phone1', $client_data); ?>">
                            </div>
                            <div class="col-4">
                                <label for="resource1_phone2" class="form-label">Phone 2:</label>
                                <input type="text" class="form-control" name="resource1_phone2" id="resource1_phone2" value="<?php echo get_value('resource1_phone2', $client_data); ?>">
                            </div>
                            <div class="col-4">
                                <label for="resource1_phone3" class="form-label">Phone 3:</label>
                                <input type="text" class="form-control" name="resource1_phone3" id="resource1_phone3" value="<?php echo get_value('resource1_phone3', $client_data); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <h5 class="h6">Resource 2</h5>
                        <div class="mb-3">
                            <label for="resource2_name" class="form-label">Name 2:</label>
                            <input type="text" class="form-control" name="resource2_name" id="resource2_name" value="<?php echo get_value('resource2_name', $client_data); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="resource2_address" class="form-label">Address 2:</label>
                            <textarea class="form-control" name="resource2_address" id="resource2_address" rows="2"><?php echo get_value('resource2_address', $client_data); ?></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col-4">
                                <label for="resource2_phone1" class="form-label">Phone 1:</label>
                                <input type="text" class="form-control" name="resource2_phone1" id="resource2_phone1" value="<?php echo get_value('resource2_phone1', $client_data); ?>">
                            </div>
                            <div class="col-4">
                                <label for="resource2_phone2" class="form-label">Phone 2:</label>
                                <input type="text" class="form-control" name="resource2_phone2" id="resource2_phone2" value="<?php echo get_value('resource2_phone2', $client_data); ?>">
                            </div>
                            <div class="col-4">
                                <label for="resource2_phone3" class="form-label">Phone 3:</label>
                                <input type="text" class="form-control" name="resource2_phone3" id="resource2_phone3" value="<?php echo get_value('resource2_phone3', $client_data); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </fieldset>

            <?php if ($is_editable): ?>
            <div class="text-center mt-4">
                <button type="submit" class="btn btn-lg btn-success" id="submitBtn">SAVE & SEND TO ADMIN FOR APPROVAL</button>
            </div>
            <?php else: ?>
            <div class="text-center mt-4">
                <p class="text-muted fw-bold">The proposal is currently being reviewed or is completed. Submission is disabled.</p>
            </div>
            <?php endif; ?>

        </form>
    <?php endif; ?>

    <div class="text-center mt-4 mb-4">
        <a href="dashboard" class="btn btn-outline-secondary">← Back to Sales Dashboard</a>
    </div>

</div>

<script>
function submitProposal(event) {
    event.preventDefault();
    
    const form = document.getElementById('salesUpdateForm');
    const formData = new FormData(form);
    const submitBtn = document.getElementById('submitBtn');
    const msgBox = document.getElementById('message-box');

    submitBtn.disabled = true;
    submitBtn.innerText = 'Processing...';
    msgBox.innerHTML = '';

    // CORRECTED FETCH PATH: From sales_person/ to events/api/
    fetch('../api/client_sales_update_api', { 
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            msgBox.innerHTML = `<div class='alert alert-success success-alert'>✅ ${data.message}</div>`;
            setTimeout(() => {
                window.location.href = 'dashboard?success=proposal_created'; 
            }, 1000);
        } else {
            const errorMessage = data.error || 'Unknown error occurred.';
            msgBox.innerHTML = `<div class='alert alert-danger error-alert'>❌ Error: ${errorMessage}</div>`;
        }
    })
    .catch(error => {
        console.error('Submission Error:', error);
        msgBox.innerHTML = `<div class='alert alert-danger error-alert'>❌ Network Error: ${error.message}</div>`;
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerText = 'SAVE & SEND TO ADMIN FOR APPROVAL';
    });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>