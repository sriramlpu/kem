<?php
// =================================================================
// 1. SETUP & SESSION HANDLING (Must be at the very top)
// =================================================================
session_start(); // Start session to handle messages across redirects
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$servername = "localhost";
$username   = "kmkglobal_web";
$password   = "tI]rfPhdOo9zHdKw";
$dbname     = "kmkglobal_web"; 

$message = ""; 

// A. Check for message from previous redirect (The Fix for your issue)
if (isset($_SESSION['temp_message'])) {
    $message = $_SESSION['temp_message'];
    unset($_SESSION['temp_message']); // Clear it so it doesn't show again on refresh
}

// Connect to Database
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("<div style='padding:20px; background:red; color:white;'>FATAL ERROR: Could not connect to database.</div>");
}

// =================================================================
// 2. HANDLE FORM SUBMISSION
// =================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validation
    $required_fields = ['client_name', 'contact_no', 'lead_status', 'lead_source', 'date_time_of_event'];
    $missing = [];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing[] = ucwords(str_replace('_', ' ', $field));
        }
    }

    if (!empty($missing)) {
        // Validation Error (No Redirect, so user keeps their typed data)
        $message = "<script>document.addEventListener('DOMContentLoaded', function() { showNotification('❌ Missing fields: " . implode(', ', $missing) . "', 'danger'); });</script>";
    } else {
        try {
            // Data Collection
            $client_name           = trim($_POST['client_name']);
            $contact_no            = trim($_POST['contact_no']);
            $contact_email         = trim($_POST['contact_email'] ?? '');
            $address               = trim($_POST['address'] ?? '');
            $lead_status           = $_POST['lead_status'];
            $lead_source           = $_POST['lead_source'];
            $about_lead_source     = trim($_POST['about_lead_source'] ?? '');
            $date_place_of_meeting = trim($_POST['date_place_of_meeting'] ?? '');
            $lead_initiative       = trim($_POST['lead_initiative'] ?? '');
            $lead_owner            = trim($_POST['lead_owner'] ?? '');
            
            // Date Fix
            $raw_date = $_POST['date_time_of_event'];
            $date_time_of_event = str_replace("T", " ", $raw_date);
            if (strlen($date_time_of_event) == 16) {
                $date_time_of_event .= ":00"; 
            }

            $client_social_connect = trim($_POST['client_social_connect'] ?? '');
            $function_details      = trim($_POST['function_details'] ?? '');
            $location_address      = trim($_POST['location_address'] ?? '');
            
            $event_type        = isset($_POST['event_type']) ? implode(', ', $_POST['event_type']) : '';
            $venue_local       = isset($_POST['venue_local']) ? implode(', ', $_POST['venue_local']) : '';
            $venue_nonlocal    = isset($_POST['venue_nonlocal']) ? implode(', ', $_POST['venue_nonlocal']) : '';
            $services_required = isset($_POST['services_required']) ? implode(', ', $_POST['services_required']) : '';

            // Database Insert
            $sql = "INSERT INTO clients (
                client_name, contact_email, address, lead_status, lead_source, contact_no, about_lead_source, 
                date_place_of_meeting, lead_initiative, lead_owner, date_time_of_event, client_social_connect, 
                event_type, function_details, venue_local_details, venue_nonlocal_details, location_address, services_required
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) { throw new Exception("Prepare failed: " . $conn->error); }

            $stmt->bind_param("ssssssssssssssssss", 
                $client_name, $contact_email, $address, $lead_status, $lead_source, $contact_no, $about_lead_source, 
                $date_place_of_meeting, $lead_initiative, $lead_owner, $date_time_of_event, $client_social_connect, 
                $event_type, $function_details, $venue_local, $venue_nonlocal, $location_address, $services_required
            );
            
            if ($stmt->execute()) {
                // SUCCESS LOGIC UPDATE:
                // 1. Store success message in SESSION
                $_SESSION['temp_message'] = "<script>document.addEventListener('DOMContentLoaded', function() { showNotification('✅ <strong>SUCCESS!</strong> Client Record Created Successfully.', 'success'); });</script>";
                
                // 2. Redirect to SAME page to clear POST data (PRG Pattern)
                header("Location: " . $_SERVER['PHP_SELF']);
                exit(); 
            } else {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $stmt->close();

        } catch (Exception $e) {
            $err = addslashes($e->getMessage());
            $message = "<script>document.addEventListener('DOMContentLoaded', function() { showNotification('❌ Database Error: $err', 'danger'); });</script>";
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>KMK Client Entry Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* --- PROFESSIONAL CSS --- */
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #27ae60;
        }
        
        body { 
            background: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-bottom: 80px;
        }
        
        .container-main { 
            max-width: 1100px; 
            margin: 30px auto; 
            background-color: white; 
            border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            padding: 0;
        }
        
        .form-header { 
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white; 
            padding: 30px;
            text-align: center; 
            position: relative;
        }

        .form-header::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--accent-color), #f1c40f);
        }
        
        .form-body { padding: 40px; }
        
        fieldset {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
            background: #fff;
            position: relative;
        }
        
        legend { 
            font-weight: 700; 
            font-size: 1.1rem;
            color: var(--primary-color);
            float: none;
            width: auto;
            padding: 0 10px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 5px;
        }
        
        .form-label { font-weight: 600; color: #555; font-size: 0.95rem; }
        .required-star { color: var(--accent-color); font-weight: bold; margin-left: 3px; }
        
        /* Floating Notification (Top Right) */
        .notification-area {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        .error-notification {
            min-width: 350px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            animation: slideIn 0.4s ease-out;
            border-left: 5px solid;
            margin-bottom: 10px;
        }
        .alert-danger { border-left-color: #dc3545; }
        .alert-success { border-left-color: #198754; }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Review Modal Styles */
        .review-row { border-bottom: 1px solid #eee; padding: 10px 0; display: flex; }
        .review-label { font-weight: bold; color: #666; width: 40%; }
        .review-value { font-weight: 600; color: #000; width: 60%; }
    </style>
</head>
<body>
    
    <div id="notification-area" class="notification-area"></div>
    <?php echo $message; ?>

    <div class="container container-main">
        <div class="form-header">
            <h1 class="mb-2"><i class="bi bi-person-rolodex"></i> KMK Client Entry</h1>
            <p class="mb-0 opacity-75">Complete the form below to create a new client record.</p>
        </div>

        <div class="form-body">
            <form id="clientForm" method="POST" action="">
                
                <fieldset>
                    <legend><i class="bi bi-person-lines-fill"></i> Client Contact Information</legend>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Client Name <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="client_name" name="client_name" value="<?php echo htmlspecialchars($_POST['client_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact No. <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="contact_no" name="contact_no" value="<?php echo htmlspecialchars($_POST['contact_no'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="2"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 border-end">
                            <label class="form-label d-block">Lead Status <span class="required-star">*</span></label>
                            <?php 
                                $curr_status = $_POST['lead_status'] ?? '';
                                foreach (['Existing', 'Referred', 'New', 'Proposed'] as $st) {
                                    $checked = ($curr_status == $st) ? 'checked' : '';
                                    echo "<div class='form-check form-check-inline'>
                                            <input class='form-check-input' type='radio' name='lead_status' value='$st' $checked>
                                            <label class='form-check-label'>$st</label>
                                          </div>";
                                }
                            ?>
                        </div>
                        <div class="col-md-6 ps-md-4">
                            <label class="form-label d-block">Lead Source <span class="required-star">*</span></label>
                            <?php 
                                $curr_source = $_POST['lead_source'] ?? '';
                                foreach (['New', 'Old'] as $src) {
                                    $checked = ($curr_source == $src) ? 'checked' : '';
                                    echo "<div class='form-check form-check-inline'>
                                            <input class='form-check-input' type='radio' name='lead_source' value='$src' $checked>
                                            <label class='form-check-label'>$src</label>
                                          </div>";
                                }
                            ?>
                            <input type="text" class="form-control mt-2 form-control-sm" name="about_lead_source" placeholder="Additional Source Details" value="<?php echo htmlspecialchars($_POST['about_lead_source'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-5">
                            <label class="form-label">Date & Place of Meeting</label>
                            <input type="text" class="form-control" name="date_place_of_meeting" value="<?php echo htmlspecialchars($_POST['date_place_of_meeting'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Lead Initiative</label>
                            <input type="text" class="form-control" name="lead_initiative" value="<?php echo htmlspecialchars($_POST['lead_initiative'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Lead Owner</label>
                            <input type="text" class="form-control" name="lead_owner" value="<?php echo htmlspecialchars($_POST['lead_owner'] ?? ''); ?>">
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend><i class="bi bi-calendar-event"></i> Event Details</legend>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Date/Time of Event <span class="required-star">*</span></label>
                            <input type="datetime-local" class="form-control" id="date_time_of_event" name="date_time_of_event" value="<?php echo htmlspecialchars($_POST['date_time_of_event'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Client Social Connect</label>
                            <input type="text" class="form-control" name="client_social_connect" value="<?php echo htmlspecialchars($_POST['client_social_connect'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label d-block">Event Type</label>
                            <?php 
                                $curr_type = $_POST['event_type'] ?? [];
                                foreach (['Social Event', 'Corporate Event'] as $t) {
                                    $checked = in_array($t, $curr_type) ? 'checked' : '';
                                    echo "<div class='form-check'>
                                            <input class='form-check-input event-type-chk' type='checkbox' name='event_type[]' value='$t' $checked>
                                            <label class='form-check-label'>$t</label>
                                          </div>";
                                }
                            ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Function Details</label>
                        <textarea class="form-control" name="function_details" rows="1"><?php echo htmlspecialchars($_POST['function_details'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-primary">Venue (Local)</label>
                            <div>
                                <?php 
                                    $v_local = $_POST['venue_local'] ?? [];
                                    foreach (['Local', 'Outdoor', 'Indoor'] as $o) {
                                        $checked = in_array($o, $v_local) ? 'checked' : '';
                                        echo "<div class='form-check form-check-inline'>
                                                <input class='form-check-input' type='checkbox' name='venue_local[]' value='$o' $checked>
                                                <label class='form-check-label'>$o</label>
                                              </div>";
                                    }
                                ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-primary">Venue (Non-Local)</label>
                            <div>
                                <?php 
                                    $v_non = $_POST['venue_nonlocal'] ?? [];
                                    foreach (['Nonlocal', 'Outdoor', 'Indoor'] as $o) {
                                        $checked = in_array($o, $v_non) ? 'checked' : '';
                                        echo "<div class='form-check form-check-inline'>
                                                <input class='form-check-input' type='checkbox' name='venue_nonlocal[]' value='$o' $checked>
                                                <label class='form-check-label'>$o</label>
                                              </div>";
                                    }
                                ?>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Location Address</label>
                        <textarea class="form-control" name="location_address" rows="1"><?php echo htmlspecialchars($_POST['location_address'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3 p-3 bg-light rounded border">
                        <label class="form-label fw-bold">Services Required:</label><br>
                        <?php 
                            $serv = $_POST['services_required'] ?? [];
                            foreach (['Decor', 'Food', 'Logistics', 'VAS'] as $o) {
                                $checked = in_array($o, $serv) ? 'checked' : '';
                                echo "<div class='form-check form-check-inline'>
                                        <input class='form-check-input service-chk' type='checkbox' name='services_required[]' value='$o' $checked>
                                        <label class='form-check-label'>$o</label>
                                      </div>";
                            }
                        ?>
                    </div>
                </fieldset>
                
                <div class="text-center mt-4">
                    <button type="button" class="btn btn-lg btn-success px-5 shadow rounded-pill" onclick="validateAndReview()">
                        <i class="bi bi-check2-circle"></i> REVIEW & SUBMIT
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="bi bi-clipboard-data"></i> Confirm Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning py-2 small">
                        <i class="bi bi-info-circle-fill"></i> Please verify information before saving to the database.
                    </div>
                    
                    <div class="review-row"><span class="review-label">Client Name:</span> <span class="review-value" id="rev_name"></span></div>
                    <div class="review-row"><span class="review-label">Contact:</span> <span class="review-value" id="rev_contact"></span></div>
                    <div class="review-row"><span class="review-label">Date of Event:</span> <span class="review-value" id="rev_date"></span></div>
                    <div class="review-row"><span class="review-label">Lead Status:</span> <span class="review-value" id="rev_status"></span></div>
                    <div class="review-row"><span class="review-label">Lead Source:</span> <span class="review-value" id="rev_source"></span></div>
                    <div class="review-row"><span class="review-label">Event Type:</span> <span class="review-value" id="rev_type"></span></div>
                    <div class="review-row"><span class="review-label">Services:</span> <span class="review-value" id="rev_services"></span></div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Edit</button>
                    <button type="button" class="btn btn-success fw-bold" onclick="submitRealForm()">
                        CONFIRM & SAVE <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 1. Notification Function (Top Right)
        function showNotification(msg, type = 'danger') {
            const area = document.getElementById('notification-area');
            const div = document.createElement('div');
            div.className = `alert alert-${type} error-notification`;
            div.innerHTML = msg;
            
            // Add close button
            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'btn-close float-end';
            closeBtn.onclick = function() { div.remove(); };
            div.appendChild(closeBtn);

            area.appendChild(div);

            // Auto-hide after 5 seconds
            setTimeout(() => {
                div.style.opacity = '0';
                setTimeout(() => div.remove(), 500);
            }, 5000);
        }

        // 2. Validate & Open Modal
        function validateAndReview() {
            // Get Required Values
            const name = document.getElementById('client_name').value.trim();
            const contact = document.getElementById('contact_no').value.trim();
            const date = document.getElementById('date_time_of_event').value;
            const statusEl = document.querySelector('input[name="lead_status"]:checked');
            const sourceEl = document.querySelector('input[name="lead_source"]:checked');
            
            let missing = [];
            if (!name) missing.push("Client Name");
            if (!contact) missing.push("Contact No");
            if (!date) missing.push("Date of Event");
            if (!statusEl) missing.push("Lead Status");
            if (!sourceEl) missing.push("Lead Source");

            // Check if errors exist
            if (missing.length > 0) {
                const msg = `<strong>Incomplete Form:</strong><br>You must fill in the following fields:<br>• ${missing.join('<br>• ')}`;
                showNotification(msg, 'danger');
                return; // Stop. Do not open modal.
            }

            // Fill Modal Data
            document.getElementById('rev_name').textContent = name;
            document.getElementById('rev_contact').textContent = contact;
            document.getElementById('rev_date').textContent = date.replace('T', ' ');
            document.getElementById('rev_status').textContent = statusEl.value;
            document.getElementById('rev_source').textContent = sourceEl.value;
            
            // Get Checkbox lists
            const eventTypes = Array.from(document.querySelectorAll('.event-type-chk:checked')).map(c => c.value).join(', ') || '-';
            const services = Array.from(document.querySelectorAll('.service-chk:checked')).map(c => c.value).join(', ') || '-';
            
            document.getElementById('rev_type').textContent = eventTypes;
            document.getElementById('rev_services').textContent = services;

            // Show Modal
            new bootstrap.Modal(document.getElementById('reviewModal')).show();
        }

        // 3. Submit Actual Form
        function submitRealForm() {
            document.getElementById('clientForm').submit();
        }
    </script>
</body>
</html>