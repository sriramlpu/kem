<?php
// Start session and include header/DB config
require_once 'header.php'; 

// Check for client ID
$client_id = $_GET['id'] ?? null;
if (!$client_id || !is_numeric($client_id)) {
    $_SESSION['message'] = "<div class='alert alert-danger error-alert'>❌ Invalid client ID.</div>";
    header("Location: index.php");
    exit();
}
$servername = "localhost";
$username 	= "kmkglobal_web";
$password 	= "tI]rfPhdOo9zHdKw";
$dbname 	= "kmkglobal_web";
// Connect to the database
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch client data
$sql = "SELECT * FROM client_information WHERE client_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();
$client = $result->fetch_assoc();

if (!$client) {
    $_SESSION['message'] = "<div class='alert alert-danger error-alert'>❌ Client record not found.</div>";
    header("Location: index");
    exit();
}

$stmt->close();
$conn->close();

// Helper function to format boolean fields (1 or 0)
function format_yes_no($value) {
    return $value == 1 ? 'Yes' : 'No';
}
?>

<h2 class="mb-4">Viewing Client Details: <?php echo htmlspecialchars($client['client_name']); ?></h2>

<div class="mb-4 text-end">
    <a href="edit_client?id=<?php echo $client_id; ?>" class="btn btn-edit me-2"><i class="fas fa-edit"></i> Edit Client Details</a>
    <a href="view_all_clients" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="detail-card">
            <h5><i class="fas fa-user-circle"></i> Client & Lead Info</h5>
            <div class="detail-item"><strong>Client Name:</strong> <?php echo htmlspecialchars($client['client_name']); ?></div>
            <div class="detail-item"><strong>Contact No/Email:</strong> <?php echo htmlspecialchars($client['contact_no'] . ' / ' . $client['contact_email']); ?></div>
            <div class="detail-item"><strong>Lead Status:</strong> <?php echo htmlspecialchars($client['lead_status']); ?></div>
            <div class="detail-item"><strong>Lead Owner:</strong> <?php echo htmlspecialchars($client['lead_owner']); ?></div>
            <div class="detail-item"><strong>Last Meeting:</strong> <?php echo htmlspecialchars($client['date_place_of_meeting']); ?></div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="detail-card">
            <h5><i class="fas fa-calendar-alt"></i> Event Specifics</h5>
            <div class="detail-item"><strong>Date/Time:</strong> <?php echo htmlspecialchars($client['date_time_of_event']); ?></div>
            <div class="detail-item"><strong>Type:</strong> <?php echo htmlspecialchars($client['event_type']); ?></div>
            <div class="detail-item"><strong>Location:</strong> <?php echo htmlspecialchars($client['location_address']); ?></div>
            <div class="detail-item"><strong>Venue Details:</strong> <?php echo htmlspecialchars($client['venue_local_details'] . ($client['venue_nonlocal_details'] ? ' / ' . $client['venue_nonlocal_details'] : '')); ?></div>
            <div class="detail-item"><strong>Expected Budget:</strong> <?php echo htmlspecialchars($client['expected_budget']); ?></div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="detail-card">
            <h5><i class="fas fa-concierge-bell"></i> Service Requirements</h5>
            <div class="detail-item"><strong>Services Required:</strong> <?php echo htmlspecialchars($client['services_required']); ?></div>
            <div class="detail-item"><strong>Food Category:</strong> <?php echo htmlspecialchars($client['food_category']); ?></div>
            <div class="detail-item"><strong>Food Standard (Veg):</strong> <?php echo htmlspecialchars($client['food_veg_standard']); ?></div>
            <div class="detail-item"><strong>Decor Type:</strong> <?php echo htmlspecialchars($client['decor_type']); ?></div>
            <div class="detail-item"><strong>Logistics Services:</strong> <?php echo nl2br(htmlspecialchars($client['logistics_services'])); ?></div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="detail-card bg-light border-danger">
            <h5 class="text-danger"><i class="fas fa-lock"></i> Office Use Summary</h5>
            <div class="row">
                <div class="col-md-3 detail-item"><strong>Tandoor:</strong> <?php echo format_yes_no($client['office_tandoor_allowance']); ?></div>
                <div class="col-md-3 detail-item"><strong>Live Cooking:</strong> <?php echo format_yes_no($client['office_live_cooking']); ?></div>
                <div class="col-md-3 detail-item"><strong>Wash Area:</strong> <?php echo format_yes_no($client['office_wash_area']); ?></div>
                <div class="col-md-3 detail-item"><strong>Garbage Disposal:</strong> <?php echo format_yes_no($client['office_garbage_disposal']); ?></div>
            </div>
            <div class="row mt-2">
                <div class="col-md-4 detail-item"><strong>Food Service:</strong> <?php echo htmlspecialchars($client['office_food_service']); ?></div>
                <div class="col-md-4 detail-item"><strong>Pandal/Chairs By:</strong> <?php echo htmlspecialchars($client['office_pankhi_service']); ?></div>
                <div class="col-md-4 detail-item"><strong>KST Team:</strong> <?php echo htmlspecialchars($client['office_kst_team']); ?></div>
            </div>
        </div>
    </div>
</div>

</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>