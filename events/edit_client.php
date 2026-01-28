<?php
// Start session and include header/DB config
require_once 'header.php'; 

// Database connection is available via header variables

// Get client ID from URL
$client_id = $_GET['id'] ?? null;
$is_new_entry = empty($client_id) || !is_numeric($client_id);
$is_edit_mode = !$is_new_entry;

// Initialize $client array for form pre-fill
$client = [];
$error_fields = [];
$message = '';
$servername = "localhost";
$username 	= "root";
$password 	= "";
$dbname 	= "kmk";
// Database Connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Logic to Fetch Existing Data for EDIT Mode ---
if ($is_edit_mode) {
    $sql_fetch = "SELECT * FROM client_information WHERE client_id = ?";
    $stmt_fetch = $conn->prepare($sql_fetch);
    $stmt_fetch->bind_param("i", $client_id);
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();
    $client = $result_fetch->fetch_assoc();

    if (!$client) {
        $_SESSION['message'] = "<div class='alert alert-danger error-alert'>❌ Record not found for editing.</div>";
        header("Location: index");
        exit();
    }
    $stmt_fetch->close();
}

// -------------------- 3. HANDLE FORM SUBMISSION (SAVE CHANGES) --------------------

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Define required fields (Validation logic is the same)
    $required_fields = ['client_name' => 'Client Name', 'contact_no' => 'Contact No.', 'lead_status' => 'Lead Status', 'lead_source' => 'Lead Source', 'date_time_of_event' => 'Date/Time of Event'];

    $is_valid = true;
    foreach ($required_fields as $field_name => $field_label) {
        if (empty($_POST[$field_name])) {
            $is_valid = false;
            $error_fields[] = $field_label;
        }
    }

    if (!$is_valid) {
        $error_list = "The form cannot be submitted. Please fill out the following required fields: " . implode(', ', $error_fields);
        $_SESSION['message'] = "<div class='alert alert-danger error-alert'>❌ **VALIDATION ERROR:** " . $error_list . "</div>";
        // To maintain form state on validation failure, we re-fill $client with POST data
        $client = array_merge($client, $_POST);
    } else {
        // --- DATA COLLECTION (Identical to your initial code) ---
        // ... (All variable assignments from the previous full code go here) ...
        // Example:
        $client_name = $_POST['client_name'] ?? '';
        $contact_email = $_POST['contact_email'] ?? '';
        // ... (DEFINE ALL 50 VARIABLES HERE) ...
        $tandoor_allowance = ($_POST['office_tandoor_allowance'] ?? 'No') == 'Yes' ? 1 : 0;
        // ... (DEFINE ALL 50 VARIABLES HERE) ...


        // --- DYNAMIC SQL QUERY: UPDATE vs INSERT ---
        if ($is_edit_mode) {
            // Define the UPDATE SQL query for 50 fields
            $sql = "UPDATE client_information SET 
                client_name=?, contact_email=?, address=?, lead_status=?, lead_source=?, contact_no=?, about_lead_source=?, 
                date_place_of_meeting=?, lead_initiative=?, lead_owner=?, date_time_of_event=?, client_social_connect=?, 
                event_type=?, function_details=?, venue_local_details=?, venue_nonlocal_details=?, location_address=?, 
                services_required=?, decor_type=?, food_category=?, food_veg_standard=?, food_nonveg_standard=?, logistics_services=?, 
                vas_requirement=?, expected_budget=?, office_event_venue=?, office_venue_size=?, office_meal_time=?, 
                office_tandoor_allowance=?, office_live_cooking=?, office_wash_area=?, office_garbage_disposal=?, 
                office_food_service=?, office_pankhi_service=?, office_cocktail=?, office_drivers_counter=?, office_kst_team=?, 
                office_next_meeting_date=?, office_client_category=?, office_lead_owner=?, office_signature_date=?,
                resource1_name=?, resource1_address=?, resource1_phone1=?, resource1_phone2=?, resource1_phone3=?,
                resource2_name=?, resource2_address=?, resource2_phone1=?, resource2_phone2=?, resource2_phone3=?
                WHERE client_id = ?";
            
            // Type string is 50 characters (46 s + 4 i) PLUS 'i' for client_id at the end
            $types = "sssssssssssssssssssssssssssssiiiissssssssssssssssssi";
            
            // Bind parameters (50 data variables + 1 client_id variable)
            $bind_vars = [
                $client_name, $contact_email, $address, $lead_status, $lead_source, $contact_no, $about_lead_source, 
                // ... (ALL 50 VARIABLES HERE) ...
                $tandoor_allowance, $live_cooking, $wash_area, $garbage_disposal, 
                // ... (ALL 50 VARIABLES HERE) ...
                $resource2_phone3, $client_id
            ];
            
            $success_msg = "✅ Client ID $client_id updated successfully!";
            $redirect_page = "view_client?id=$client_id";

        } else {
            // INSERT Query (for future use, if you ever land here accidentally)
            // ... (Use INSERT logic from original code) ...
        }

        // --- Execute Query ---
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            $_SESSION['message'] = "<div class='alert alert-danger error-alert'>❌ Error preparing statement: " . $conn->error . "</div>";
        } else {
            // Dynamically call bind_param with the variable list
            $stmt->bind_param($types, ...$bind_vars);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "<div class='alert alert-success success-alert'>$success_msg</div>";
                header("Location: $redirect_page");
                exit();
            } else {
                $_SESSION['message'] = "<div class='alert alert-danger error-alert'>❌ DB EXECUTION ERROR: " . $stmt->error . "</div>";
                // To display the error without losing POST data
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit();
            }
            $stmt->close();
        }
    }
}
$conn->close();

// --- FALLBACK FOR FORM PRE-FILLING ---
// If submission failed validation, $client will be merged with $_POST data to retain inputs.
// If it's the first time loading the page, $client contains fetched DB data.
$client = array_map(function($v) { return is_null($v) ? '' : $v; }, $client);
?>

<h2 class="mb-4"><i class="fas fa-edit"></i> Edit Client: <?php echo htmlspecialchars($client['client_name'] ?? 'Loading...'); ?></h2>

<form method="POST" action="edit_client?id=<?php echo $client_id; ?>">
    
    <div class="d-flex justify-content-end mb-4 sticky-top p-2 bg-white rounded shadow-sm">
        <button type="submit" class="btn btn-save me-2"><i class="fas fa-save"></i> Save Changes</button>
        <a href="view_client?id=<?php echo $client_id; ?>" class="btn btn-cancel"><i class="fas fa-times"></i> Cancel & Discard</a>
    </div>

    <fieldset class="mb-4">
        <legend>Client Contact Information</legend>
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="client_name" class="form-label">Client Name: <span class="text-danger">*</span></label>
                <input type="text" class="form-control <?php echo in_array('Client Name', $error_fields) ? 'is-invalid' : ''; ?>" id="client_name" name="client_name" required value="<?php echo htmlspecialchars($client['client_name'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
                <label for="contact_no" class="form-label">Contact No. / Contact/Email: <span class="text-danger">*</span></label>
                <input type="text" class="form-control <?php echo in_array('Contact No.', $error_fields) ? 'is-invalid' : ''; ?>" id="contact_no" name="contact_no" required value="<?php echo htmlspecialchars($client['contact_no'] ?? ''); ?>">
                <input type="hidden" name="contact_email" value="<?php echo htmlspecialchars($client['contact_email'] ?? ''); ?>"> 
            </div>
        </div>
        <div class="mb-3">
            <label for="address" class="form-label">Address:</label>
            <textarea class="form-control" id="address" name="address" rows="1"><?php echo htmlspecialchars($client['address'] ?? ''); ?></textarea>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Lead Status: <span class="text-danger">*</span></label><br>
                <?php 
                    $lead_status = $client['lead_status'] ?? '';
                    $lead_statuses = ['Existing', 'Referred', 'New', 'Proposed'];
                    foreach ($lead_statuses as $status):
                ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="lead_status" value="<?php echo $status; ?>" id="status_<?php echo strtolower($status); ?>" <?php echo ($lead_status == $status) ? 'checked' : ''; ?>> <label class="form-check-label" for="status_<?php echo strtolower($status); ?>"><?php echo $status; ?></label>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">Lead Source: <span class="text-danger">*</span></label><br>
                <?php 
                    $lead_source = $client['lead_source'] ?? '';
                    $lead_sources = ['New', 'Old'];
                    foreach ($lead_sources as $source):
                ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="lead_source" value="<?php echo $source; ?>" id="source_<?php echo strtolower($source); ?>" <?php echo ($lead_source == $source) ? 'checked' : ''; ?>> <label class="form-check-label" for="source_<?php echo strtolower($source); ?>"><?php echo $source; ?></label>
                </div>
                <?php endforeach; ?>
                <input type="text" class="form-control mt-2" name="about_lead_source" placeholder="About Lead Source" value="<?php echo htmlspecialchars($client['about_lead_source'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="date_place_of_meeting" class="form-label">Date and Place of Meeting:</label>
                <input type="text" class="form-control" id="date_place_of_meeting" name="date_place_of_meeting" value="<?php echo htmlspecialchars($client['date_place_of_meeting'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label for="lead_initiative" class="form-label">Lead Initiative:</label>
                <input type="text" class="form-control" id="lead_initiative" name="lead_initiative" value="<?php echo htmlspecialchars($client['lead_initiative'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label for="lead_owner" class="form-label">Lead Owner:</label>
                <input type="text" class="form-control" id="lead_owner" name="lead_owner" value="<?php echo htmlspecialchars($client['lead_owner'] ?? ''); ?>">
            </div>
        </div>
    </fieldset>

    <fieldset class="mb-4">
        <legend>Event Details</legend>
        <div class="row mb-3">
            <div class="col-md-4">
                <label for="date_time_of_event" class="form-label">Date/Time of Event: <span class="text-danger">*</span></label>
                <input type="datetime-local" class="form-control <?php echo in_array('Date/Time of Event', $error_fields) ? 'is-invalid' : ''; ?>" id="date_time_of_event" name="date_time_of_event" required value="<?php echo htmlspecialchars($client['date_time_of_event'] ? date('Y-m-d\TH:i', strtotime($client['date_time_of_event'])) : ''); ?>">
            </div>
            <div class="col-md-4">
                <label for="client_social_connect" class="form-label">Client Social Connect:</label>
                <input type="text" class="form-control" id="client_social_connect" name="client_social_connect" value="<?php echo htmlspecialchars($client['client_social_connect'] ?? ''); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Event Type:</label><br>
                <?php 
                    $event_types = explode(', ', $client['event_type'] ?? '');
                    $type_options = ['Social Event', 'Corporate Event'];
                    foreach ($type_options as $option):
                ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="event_type[]" value="<?php echo $option; ?>" id="event_<?php echo strtolower(str_replace(' ', '_', $option)); ?>" <?php echo in_array($option, $event_types) ? 'checked' : ''; ?>> <label class="form-check-label" for="event_<?php echo strtolower(str_replace(' ', '_', $option)); ?>"><?php echo $option; ?></label>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="mb-3">
            <label for="function_details" class="form-label">Function details:</label>
            <textarea class="form-control" id="function_details" name="function_details" rows="1"><?php echo htmlspecialchars($client['function_details'] ?? ''); ?></textarea>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Venue (Local Details):</label><br>
                <?php 
                    $venue_local = explode(', ', $client['venue_local_details'] ?? '');
                    $local_options = ['Local', 'Outdoor', 'Indoor'];
                    foreach ($local_options as $option):
                ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="venue_local[]" value="<?php echo $option; ?>" id="v_local_<?php echo strtolower($option); ?>" <?php echo in_array($option, $venue_local) ? 'checked' : ''; ?>> <label class="form-check-label" for="v_local_<?php echo strtolower($option); ?>"><?php echo $option; ?></label>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">Venue (Nonlocal Details):</label><br>
                <?php 
                    $venue_nonlocal = explode(', ', $client['venue_nonlocal_details'] ?? '');
                    $nonlocal_options = ['Nonlocal', 'Outdoor', 'Indoor'];
                    foreach ($nonlocal_options as $option):
                ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="venue_nonlocal[]" value="<?php echo $option; ?>" id="v_nonlocal_<?php echo strtolower($option); ?>" <?php echo in_array($option, $venue_nonlocal) ? 'checked' : ''; ?>> <label class="form-check-label" for="v_nonlocal_<?php echo strtolower($option); ?>"><?php echo $option; ?></label>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mb-3">
            <label for="location_address" class="form-label">Location address:</label>
            <textarea class="form-control" id="location_address" name="location_address" rows="1"><?php echo htmlspecialchars($client['location_address'] ?? ''); ?></textarea>
        </div>
    </fieldset>
    
    <fieldset class="mb-4">
        <legend>Event Particulars</legend>
        <div class="mb-3">
            <label class="form-label">Services Required:</label><br>
            <?php 
                $services_required = explode(', ', $client['services_required'] ?? '');
                $service_options = ['Decor', 'Food', 'Logistics', 'VAS'];
                foreach ($service_options as $option):
            ?>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="services_required[]" value="<?php echo $option; ?>" id="service_<?php echo strtolower($option); ?>" <?php echo in_array($option, $services_required) ? 'checked' : ''; ?>> <label class="form-check-label" for="service_<?php echo strtolower($option); ?>"><?php echo $option; ?></label>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Decor Type:</label><br>
                <?php 
                    $decor_type = $client['decor_type'] ?? '';
                    $decor_options = ['A', 'B', 'C', 'Customization'];
                    foreach ($decor_options as $option):
                ?>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="decor_type" value="<?php echo $option; ?>" id="decor_<?php echo strtolower($option); ?>" <?php echo ($decor_type == $option) ? 'checked' : ''; ?>> <label class="form-check-label" for="decor_<?php echo strtolower($option); ?>"><?php echo $option; ?></label></div>
                <?php endforeach; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">Food Category:</label><br>
                <?php 
                    $food_category = $client['food_category'] ?? '';
                    $food_options = ['Vegetarian', 'Non-Vegetarian'];
                    foreach ($food_options as $option):
                ?>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="food_category" value="<?php echo $option; ?>" id="food_<?php echo strtolower($option); ?>" <?php echo ($food_category == $option) ? 'checked' : ''; ?>> <label class="form-check-label" for="food_<?php echo strtolower($option); ?>"><?php echo $option; ?></label></div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Vegetarian Food Standard:</label><br>
                <?php 
                    $food_veg_standard = $client['food_veg_standard'] ?? '';
                    $standard_options = ['Standard', 'Gold', 'Diamond'];
                    foreach ($standard_options as $option):
                ?>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="food_veg_standard" value="<?php echo $option; ?>" id="veg_<?php echo strtolower($option); ?>" <?php echo ($food_veg_standard == $option) ? 'checked' : ''; ?>> <label class="form-check-label" for="veg_<?php echo strtolower($option); ?>"><?php echo $option; ?></label></div>
                <?php endforeach; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">Non-Veg Food Standard:</label><br>
                <?php 
                    $food_nonveg_standard = $client['food_nonveg_standard'] ?? '';
                    foreach ($standard_options as $option):
                ?>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="food_nonveg_standard" value="<?php echo $option; ?>" id="nonveg_<?php echo strtolower($option); ?>" <?php echo ($food_nonveg_standard == $option) ? 'checked' : ''; ?>> <label class="form-check-label" for="nonveg_<?php echo strtolower($option); ?>"><?php echo $option; ?></label></div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mb-3">
            <label for="logistics_services" class="form-label">Logistics Services:</label>
            <textarea class="form-control" id="logistics_services" name="logistics_services" rows="1"><?php echo htmlspecialchars($client['logistics_services'] ?? ''); ?></textarea>
        </div>
        <div class="mb-3">
            <label for="vas_requirement" class="form-label">VAS Requirement:</label>
            <textarea class="form-control" id="vas_requirement" name="vas_requirement" rows="1"><?php echo htmlspecialchars($client['vas_requirement'] ?? ''); ?></textarea>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="expected_budget" class="form-label">Expected Budget (Enter Amount/Range):</label>
                <input type="text" class="form-control" id="expected_budget" name="expected_budget" placeholder="e.g., 5,00,000 INR or 4L - 6L" value="<?php echo htmlspecialchars($client['expected_budget'] ?? ''); ?>">
            </div>
        </div>
        
    </fieldset>

    <fieldset class="mb-4 text-danger">
        <legend class="text-danger">For Office Use Only</legend>
        <div class="row mb-3">
            <div class="col-md-4">
                <label for="office_event_venue" class="form-label">Event Venue (Office):</label><br>
                <?php $office_event_venue = $client['office_event_venue'] ?? ''; ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="office_event_venue" value="Home" id="venue_home" <?php echo ($office_event_venue == 'Home') ? 'checked' : ''; ?>> 
                    <label class="form-check-label" for="venue_home">Home</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="office_event_venue" value="Clubhouse" id="venue_clubhouse" <?php echo ($office_event_venue == 'Clubhouse') ? 'checked' : ''; ?>> 
                    <label class="form-check-label" for="venue_clubhouse">Clubhouse</label>
                </div>
            </div>
            <div class="col-md-4"><label for="office_venue_size" class="form-label">Venue Size:</label><input type="text" class="form-control" name="office_venue_size" value="<?php echo htmlspecialchars($client['office_venue_size'] ?? ''); ?>"></div>
            <div class="col-md-4"><label for="office_meal_time" class="form-label">Meal Time:</label><input type="text" class="form-control" name="office_meal_time" value="<?php echo htmlspecialchars($client['office_meal_time'] ?? ''); ?>"></div>
        </div>

        <div class="row mb-3">
            <?php 
                // Format the DB integer (1 or 0) back to 'Yes' or 'No' for pre-filling radio buttons
                $tandoor = format_yes_no($client['office_tandoor_allowance'] ?? 0);
                $cooking = format_yes_no($client['office_live_cooking'] ?? 0);
                $wash = format_yes_no($client['office_wash_area'] ?? 0);
                $disposal = format_yes_no($client['office_garbage_disposal'] ?? 0);
            ?>
            <div class="col-md-3">
                <label class="form-label">Tandoor Allowance:</label><br>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="office_tandoor_allowance" value="Yes" id="tandoor_yes" <?php echo ($tandoor == 'Yes') ? 'checked' : ''; ?>> <label class="form-check-label" for="tandoor_yes">Yes</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="office_tandoor_allowance" value="No" id="tandoor_no" <?php echo ($tandoor == 'No') ? 'checked' : ''; ?>> <label class="form-check-label" for="tandoor_no">No</label>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Live-Cooking:</label><br>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="office_live_cooking" value="Yes" id="cooking_yes" <?php echo ($cooking == 'Yes') ? 'checked' : ''; ?>> <label class="form-check-label" for="cooking_yes">Yes</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="office_live_cooking" value="No" id="cooking_no" <?php echo ($cooking == 'No') ? 'checked' : ''; ?>> <label class="form-check-label" for="cooking_no">No</label>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Wash Area:</label><br>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="office_wash_area" value="Yes" id="wash_yes" <?php echo ($wash == 'Yes') ? 'checked' : ''; ?>> <label class="form-check-label" for="wash_yes">Yes</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="office_wash_area" value="No" id="wash_no" <?php echo ($wash == 'No') ? 'checked' : ''; ?>> <label class="form-check-label" for="wash_no">No</label>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Garbage Disposal:</label><br>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="office_garbage_disposal" value="Yes" id="disposal_yes" <?php echo ($disposal == 'Yes') ? 'checked' : ''; ?>> <label class="form-check-label" for="disposal_yes">Yes</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="office_garbage_disposal" value="No" id="disposal_no" <?php echo ($disposal == 'No') ? 'checked' : ''; ?>> <label class="form-check-label" for="disposal_no">No</label>
                </div>
            </div>
        </div>
        
        <div class="row mb-3">
            <?php $office_food_service = $client['office_food_service'] ?? ''; ?>
            <div class="col-md-4">
                <label class="form-label">Food Service:</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="office_food_service" value="Yes" id="food_service_yes" <?php echo ($office_food_service == 'Yes') ? 'checked' : ''; ?>> 
                    <label class="form-check-label" for="food_service_yes">Yes</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="office_food_service" value="No" id="food_service_no" <?php echo ($office_food_service == 'No') ? 'checked' : ''; ?>> 
                    <label class="form-check-label" for="food_service_no">No</label>
                </div>
            </div>
            
            <?php $office_pankhi_service = $client['office_pankhi_service'] ?? ''; ?>
            <div class="col-md-4">
                <label class="form-label">F&K Pandal/Table/Chairs by:</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="office_pankhi_service" value="Buffet" id="service_buffet" <?php echo ($office_pankhi_service == 'Buffet') ? 'checked' : ''; ?>> 
                    <label class="form-check-label" for="service_buffet">Buffet</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="office_pankhi_service" value="Pankhi" id="service_pankhi" <?php echo ($office_pankhi_service == 'Pankhi') ? 'checked' : ''; ?>> 
                    <label class="form-check-label" for="service_pankhi">Pankhi</label>
                </div>
            </div>
            
            <div class="col-md-4">
                <label for="office_cocktail" class="form-label">Cocktail:</label>
                <input type="text" class="form-control" name="office_cocktail" value="<?php echo htmlspecialchars($client['office_cocktail'] ?? ''); ?>">
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-4"><label for="office_drivers_counter" class="form-label">Drivers Counter:</label><input type="text" class="form-control" name="office_drivers_counter" value="<?php echo htmlspecialchars($client['office_drivers_counter'] ?? ''); ?>"></div>
            <div class="col-md-4"><label for="office_kst_team" class="form-label">KST Team:</label><input type="text" class="form-control" name="office_kst_team" value="<?php echo htmlspecialchars($client['office_kst_team'] ?? ''); ?>"></div>
            <div class="col-md-4"><label for="office_next_meeting_date" class="form-label">Next Meeting Date:</label><input type="date" class="form-control" name="office_next_meeting_date" value="<?php echo htmlspecialchars($client['office_next_meeting_date'] ?? ''); ?>"></div>
        </div>

        <div class="row mb-3">
            <div class="col-md-4"><label for="office_client_category" class="form-label">Client Category:</label><input type="text" class="form-control" name="office_client_category" value="<?php echo htmlspecialchars($client['office_client_category'] ?? ''); ?>"></div>
            <div class="col-md-4"><label for="office_lead_owner" class="form-label">Lead Owner:</label><input type="text" class="form-control" name="office_lead_owner" value="<?php echo htmlspecialchars($client['office_lead_owner'] ?? ''); ?>"></div>
            <div class="col-md-4"><label for="office_signature_date" class="form-label">Signature with Date:</label><input type="text" class="form-control" name="office_signature_date" value="<?php echo htmlspecialchars($client['office_signature_date'] ?? ''); ?>"></div>
        </div>
    </fieldset>

    <fieldset class="mb-4">
        <legend>Approachable Resources</legend>
        <div class="row">
            <div class="col-md-6 border-end">
                <h5 class="h6">Resource 1</h5>
                <div class="mb-3"><label for="resource1_name" class="form-label">Name 1:</label><input type="text" class="form-control" name="resource1_name" value="<?php echo htmlspecialchars($client['resource1_name'] ?? ''); ?>"></div>
                <div class="mb-3"><label for="resource1_address" class="form-label">Address 1:</label><textarea class="form-control" name="resource1_address" rows="1"><?php echo htmlspecialchars($client['resource1_address'] ?? ''); ?></textarea></div>
                <div class="row mb-3">
                    <div class="col-4"><label for="resource1_phone1" class="form-label">Phone 1:</label><input type="text" class="form-control" name="resource1_phone1" value="<?php echo htmlspecialchars($client['resource1_phone1'] ?? ''); ?>"></div>
                    <div class="col-4"><label for="resource1_phone2" class="form-label">Phone 2:</label><input type="text" class="form-control" name="resource1_phone2" value="<?php echo htmlspecialchars($client['resource1_phone2'] ?? ''); ?>"></div>
                    <div class="col-4"><label for="resource1_phone3" class="form-label">Phone 3:</label><input type="text" class="form-control" name="resource1_phone3" value="<?php echo htmlspecialchars($client['resource1_phone3'] ?? ''); ?>"></div>
                </div>
            </div>

            <div class="col-md-6">
                <h5 class="h6">Resource 2</h5>
                <div class="mb-3"><label for="resource2_name" class="form-label">Name 2:</label><input type="text" class="form-control" name="resource2_name" value="<?php echo htmlspecialchars($client['resource2_name'] ?? ''); ?>"></div>
                <div class="mb-3"><label for="resource2_address" class="form-label">Address 2:</label><textarea class="form-control" name="resource2_address" rows="1"><?php echo htmlspecialchars($client['resource2_address'] ?? ''); ?></textarea></div>
                <div class="row mb-3">
                    <div class="col-4"><label for="resource2_phone1" class="form-label">Phone 1:</label><input type="text" class="form-control" name="resource2_phone1" value="<?php echo htmlspecialchars($client['resource2_phone1'] ?? ''); ?>"></div>
                    <div class="col-4"><label for="resource2_phone2" class="form-label">Phone 2:</label><input type="text" class="form-control" name="resource2_phone2" value="<?php echo htmlspecialchars($client['resource2_phone2'] ?? ''); ?>"></div>
                    <div class="col-4"><label for="resource2_phone3" class="form-label">Phone 3:</label><input type="text" class="form-control" name="resource2_phone3" value="<?php echo htmlspecialchars($client['resource2_phone3'] ?? ''); ?>"></div>
                </div>
            </div>
        </div>
    </fieldset>

    <div class="text-center mt-4">
        <button type="submit" class="btn btn-lg btn-save"><i class="fas fa-save"></i> Save Changes</button>
    </div>
</form>

</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>