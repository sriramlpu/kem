<?php

// -------------------- 1. DATABASE CONNECTION CONFIGURATION --------------------
$servername = "localhost";
$username   = "kmkglobal_web";
$password   = "tI]rfPhdOo9zHdKw";
$dbname     = "kmkglobal_web"; // <<--- REPLACE WITH YOUR DB NAME

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = ""; 
$error_fields = []; // Array to store names of fields that failed validation

// Define required fields 
$required_fields = [
    'client_name' => 'Client Name',
    'contact_no' => 'Contact No.',
    'lead_status' => 'Lead Status',
    'lead_source' => 'Lead Source',
    'date_time_of_event' => 'Date/Time of Event'
];

// -------------------- 2. HANDLE FORM SUBMISSION (WITH VALIDATION AND BINDING) --------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $is_valid = true;

    // --- Validation Check ---
    foreach ($required_fields as $field_name => $field_label) {
        if (empty($_POST[$field_name])) {
            $is_valid = false;
            $error_fields[] = $field_label;
        }
    }

    if (!$is_valid) {
        $error_list = "The form cannot be submitted. Please fill out the following required fields: " . implode(', ', $error_fields);
        // This is the custom error message displayed on the top right
        $message = "<div class='alert alert-danger error-alert'>❌ **VALIDATION ERROR:** " . $error_list . "</div>";
    } else {
        
        // --- Step 1: Collect and Prepare Data into dedicated Variables ---
        
        // Client Contact Information
        $client_name = $_POST['client_name'] ?? '';
        $contact_email = $_POST['contact_email'] ?? '';
        $address = $_POST['address'] ?? '';
        $lead_status = $_POST['lead_status'] ?? '';
        $lead_source = $_POST['lead_source'] ?? '';
        $contact_no = $_POST['contact_no'] ?? '';
        $about_lead_source = $_POST['about_lead_source'] ?? '';
        $date_place_of_meeting = $_POST['date_place_of_meeting'] ?? '';
        $lead_initiative = $_POST['lead_initiative'] ?? '';
        $lead_owner = $_POST['lead_owner'] ?? '';
        
        // Event Details
        $date_time_of_event = $_POST['date_time_of_event'] ?? '';
        $client_social_connect = $_POST['client_social_connect'] ?? '';
        $event_type = implode(', ', $_POST['event_type'] ?? []);
        $function_details = $_POST['function_details'] ?? '';
        $venue_local_details = implode(', ', $_POST['venue_local'] ?? []);
        $venue_nonlocal_details = implode(', ', $_POST['venue_nonlocal'] ?? []);
        $location_address = $_POST['location_address'] ?? '';
        
        // Services Required (Moved from Event Particulars)
        $services_required = implode(', ', $_POST['services_required'] ?? []);

        // --- Step 2: Database Insertion (Only if validation passed) ---
        
        // NOTE: SQL Query is updated to target 'clients' table and include only the 17 fields in this form,
        // setting workflow status columns to their default 'PENDING' state.
        $sql = "INSERT INTO clients (
            client_name, contact_email, address, lead_status, lead_source, contact_no, about_lead_source, 
            date_place_of_meeting, lead_initiative, lead_owner, date_time_of_event, client_social_connect, 
            event_type, function_details, venue_local_details, venue_nonlocal_details, location_address,
            services_required
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )";
                
        // The type definition string now matches the 18 parameters: 18 's' (all strings)
        // Note: The previous code had 50 fields, this has 18.
        $types = "ssssssssssssssssss"; 

        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            $message = "<div class='alert alert-danger error-alert'>❌ Error preparing statement: " . $conn->error . "</div>";
        } else {
            // Bind the 18 parameters
            $stmt->bind_param($types, 
                $client_name, $contact_email, $address, $lead_status, $lead_source, $contact_no, $about_lead_source, 
                $date_place_of_meeting, $lead_initiative, $lead_owner, $date_time_of_event, $client_social_connect, 
                $event_type, $function_details, $venue_local_details, $venue_nonlocal_details, $location_address,
                $services_required
            );
            
            if ($stmt->execute()) {
                // Clear post data on success
                $_POST = array(); 
                
                $message = "<div class='alert alert-success success-alert'>✅ New client record created successfully!</div>";
            } else {
                $message = "<div class='alert alert-danger error-alert'>❌ **DB EXECUTION ERROR:** " . $stmt->error . "</div>";
            }
            $stmt->close();
        }
    }
}
$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>KMK Client Information Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* CSS Definitions */
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --border-color: #dee2e6;
        }
        
        body { 
            background: ;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 20px 0;
            position: relative;
        }
        
        .container { 
            max-width: 1200px; 
            margin: 20px auto; 
            padding: 0;
            background-color: white; 
            border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .form-header { 
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white; 
            padding: 25px 30px;
            text-align: center; 
            margin-bottom: 30px;
            position: relative;
        }
        
        .form-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-color), var(--warning-color));
        }
        
        .form-header h1 {
            font-weight: 700;
            margin: 0;
            font-size: 2.2rem;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.3);
        }
        
        form {
            padding: 0 30px 30px;
        }
        
        fieldset {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            background: #fff;
            position: relative;
        }
        
        legend { 
            font-weight: 700; 
            font-size: 1.3rem;
            color: var(--primary-color);
            width: auto;
            padding: 0 15px;
            margin-left: -15px;
            border: none;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        fieldset.text-danger legend {
            color: var(--accent-color);
            background: #fff5f5;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 8px;
        }
        
        .form-check-inline {
            margin-right: 1.5rem;
            margin-bottom: 10px;
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #2ecc71 100%);
            border: none;
            border-radius: 50px;
            padding: 12px 40px;
            font-weight: 700;
            font-size: 1.1rem;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
        }
        
        .border-end {
            border-right: 2px dashed var(--border-color) !important;
        }
        
        /* Custom styles for PHP validation feedback */
        .is-invalid {
            border-color: var(--accent-color) !important;
        }
        
        /* START: TOP-RIGHT ERROR STYLES */
        .error-alert, .success-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            max-width: 400px;
            animation: slideIn 0.5s forwards;
            font-weight: bold;
        }
        
        @keyframes slideIn {
            from { right: -400px; opacity: 0; }
            to { right: 20px; opacity: 1; }
        }
        /* END: TOP-RIGHT ERROR STYLES */
    </style>
</head>
<body>
    
    <?php echo $message; // Display submission message/error ?>

<div class="container">
    <div class="form-header">
        <h1>KMK Event Lead Entry</h1>
        <p class="mb-0">Capture initial contact and event details for a new client.</p>
    </div>

    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        
        <fieldset class="mb-4">
            <legend>Client Contact Information</legend>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="client_name" class="form-label">Client Name: <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?php echo in_array('Client Name', $error_fields) ? 'is-invalid' : ''; ?>" id="client_name" name="client_name" required value="<?php echo $_POST['client_name'] ?? ''; ?>">
                </div>
                <div class="col-md-6">
                    <label for="contact_no" class="form-label">Contact No. / Contact/Email: <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?php echo in_array('Contact No.', $error_fields) ? 'is-invalid' : ''; ?>" id="contact_no" name="contact_no" required value="<?php echo $_POST['contact_no'] ?? ''; ?>">
                    <!-- Assuming contact_email is derived or captured elsewhere -->
                    <input type="hidden" name="contact_email" value="<?php echo $_POST['contact_email'] ?? ''; ?>"> 
                </div>
            </div>
            <div class="mb-3">
                <label for="address" class="form-label">Address:</label>
                <textarea class="form-control" id="address" name="address" rows="1"><?php echo $_POST['address'] ?? ''; ?></textarea>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Lead Status: <span class="text-danger">*</span></label><br>
                    <?php 
                        $lead_status = $_POST['lead_status'] ?? '';
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
                        $lead_source = $_POST['lead_source'] ?? '';
                        $lead_sources = ['New', 'Old'];
                        foreach ($lead_sources as $source):
                    ?>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="lead_source" value="<?php echo $source; ?>" id="source_<?php echo strtolower($source); ?>" <?php echo ($lead_source == $source) ? 'checked' : ''; ?>> <label class="form-check-label" for="source_<?php echo strtolower($source); ?>"><?php echo $source; ?></label>
                    </div>
                    <?php endforeach; ?>
                    <input type="text" class="form-control mt-2" name="about_lead_source" placeholder="About Lead Source" value="<?php echo $_POST['about_lead_source'] ?? ''; ?>">
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="date_place_of_meeting" class="form-label">Date and Place of Meeting:</label>
                    <input type="text" class="form-control" id="date_place_of_meeting" name="date_place_of_meeting" value="<?php echo $_POST['date_place_of_meeting'] ?? ''; ?>">
                </div>
                <div class="col-md-3">
                    <label for="lead_initiative" class="form-label">Lead Initiative:</label>
                    <input type="text" class="form-control" id="lead_initiative" name="lead_initiative" value="<?php echo $_POST['lead_initiative'] ?? ''; ?>">
                </div>
                <div class="col-md-3">
                    <label for="lead_owner" class="form-label">Lead Owner:</label>
                    <input type="text" class="form-control" id="lead_owner" name="lead_owner" value="<?php echo $_POST['lead_owner'] ?? ''; ?>">
                </div>
            </div>
        </fieldset>

        <fieldset class="mb-4">
            <legend>Event Details</legend>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="date_time_of_event" class="form-label">Date/Time of Event: <span class="text-danger">*</span></label>
                    <input type="datetime-local" class="form-control <?php echo in_array('Date/Time of Event', $error_fields) ? 'is-invalid' : ''; ?>" id="date_time_of_event" name="date_time_of_event" required value="<?php echo $_POST['date_time_of_event'] ?? ''; ?>">
                </div>
                <div class="col-md-4">
                    <label for="client_social_connect" class="form-label">Client Social Connect:</label>
                    <input type="text" class="form-control" id="client_social_connect" name="client_social_connect" value="<?php echo $_POST['client_social_connect'] ?? ''; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Event Type:</label><br>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="event_type[]" value="Social Event" id="event_social" <?php echo (isset($_POST['event_type']) && in_array('Social Event', $_POST['event_type'])) ? 'checked' : ''; ?>> <label class="form-check-label" for="event_social">Social Event</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="event_type[]" value="Corporate Event" id="event_corporate" <?php echo (isset($_POST['event_type']) && in_array('Corporate Event', $_POST['event_type'])) ? 'checked' : ''; ?>> <label class="form-check-label" for="event_corporate">Corporate Event</label>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="function_details" class="form-label">Function details:</label>
                <textarea class="form-control" id="function_details" name="function_details" rows="1"><?php echo $_POST['function_details'] ?? ''; ?></textarea>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Venue (Local Details):</label><br>
                    <?php 
                        $venue_local = $_POST['venue_local'] ?? [];
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
                        $venue_nonlocal = $_POST['venue_nonlocal'] ?? [];
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
                <textarea class="form-control" id="location_address" name="location_address" rows="1"><?php echo $_POST['location_address'] ?? ''; ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Services Required:</label><br>
                <?php 
                    $services_required = $_POST['services_required'] ?? [];
                    $service_options = ['Decor', 'Food', 'Logistics', 'VAS'];
                    foreach ($service_options as $option):
                ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="services_required[]" value="<?php echo $option; ?>" id="service_<?php echo strtolower($option); ?>" <?php echo in_array($option, $services_required) ? 'checked' : ''; ?>> <label class="form-check-label" for="service_<?php echo strtolower($option); ?>"><?php echo $option; ?></label>
                </div>
                <?php endforeach; ?>
            </div>
        </fieldset>
        
        <div class="text-center mt-4">
            <button type="submit" class="btn btn-lg btn-success">SAVE CLIENT INFORMATION</button>
        </div>
    </form>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
