<?php

// -------------------- 1. DATABASE CONNECTION CONFIGURATION --------------------

// NOTE: Updated credentials as per request.
$servername = "localhost";
$username = "kmkglobal_web";
$password = "tI]rfPhdOo9zHdKw"; // <<<--- UPDATED PASSWORD
$dbname = "kmkglobal_web";    // <<<--- UPDATED DB NAME

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// -------------------- 2. DASHBOARD DATA RETRIEVAL --------------------

// --- STATS: Total Clients (Using the 'clients' table) ---
$total_clients = 0;
// CORRECTED: Using client_id instead of id
$sql_total = "SELECT COUNT(client_id) AS total FROM clients";
$result_total = $conn->query($sql_total);
if ($result_total) {
    $row_total = $result_total->fetch_assoc();
    $total_clients = $row_total['total'];
}

// --- STATS: New Leads (e.g., Lead Status = 'New') (Using the 'clients' table) ---
$new_leads = 0;
// CORRECTED: Using client_id instead of id
$sql_new_leads = "SELECT COUNT(client_id) AS new_leads FROM clients WHERE lead_status = 'New'";
$result_new_leads = $conn->query($sql_new_leads);
if ($result_new_leads) {
    $row_new_leads = $result_new_leads->fetch_assoc();
    $new_leads = $row_new_leads['new_leads'];
}

// --- STATS: Upcoming Events (Events in the future) (Using the 'clients' table) ---
$upcoming_events = 0;
// CORRECTED: Using client_id instead of id
// Assuming date_time_of_event is a valid datetime column
$sql_upcoming = "SELECT COUNT(client_id) AS upcoming FROM clients WHERE date_time_of_event >= NOW()";
$result_upcoming = $conn->query($sql_upcoming);
if ($result_upcoming) {
    $row_upcoming = $result_upcoming->fetch_assoc();
    $upcoming_events = $row_upcoming['upcoming'];
}

// --- TABLE DATA: Fetch all client records for the table (Using the 'clients' table) ---
$clients_data = [];
// CORRECTED: Selecting client_id instead of id
$sql_clients = "SELECT client_id, client_name, contact_no, lead_status, date_time_of_event, event_type FROM clients ORDER BY date_time_of_event DESC";
$result_clients = $conn->query($sql_clients);

if ($result_clients) {
    while ($row = $result_clients->fetch_assoc()) {
        $clients_data[] = $row;
    }
}

$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>KMK Client Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container { padding-top: 30px; }
        .card-title { font-weight: 600; }
        .card-text { font-size: 2.5rem; font-weight: bold; }
        .dashboard-header {
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }
        .table-responsive { max-height: 400px; overflow-y: auto; }
        .sticky-top { top: 0; } /* Ensures the table header stays visible when scrolling */
    </style>
</head>
<body>
<div class="container">

    <h1 class="dashboard-header text-primary">Client & Event Overview</h1>

    <div class="row mb-5">
        <div class="col-md-4">
            <div class="card text-white bg-primary mb-3 shadow">
                <div class="card-body">
                    <h5 class="card-title">Total Clients</h5>
                    <p class="card-text"><?php echo $total_clients; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-success mb-3 shadow">
                <div class="card-body">
                    <h5 class="card-title">New Leads</h5>
                    <p class="card-text"><?php echo $new_leads; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-warning mb-3 shadow">
                <div class="card-body">
                    <h5 class="card-title">Upcoming Events</h5>
                    <p class="card-text"><?php echo $upcoming_events; ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-lg">
        <div class="card-header bg-white">
            <h4 class="mb-0">Recent Client & Event Records (Last <?php echo count($clients_data); ?>)</h4>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Client Name</th>
                            <th>Contact No.</th>
                            <th>Lead Status</th>
                            <th>Event Type</th>
                            <th>Date/Time of Event</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($clients_data) > 0): ?>
                            <?php foreach ($clients_data as $client): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($client['client_name']); ?></td>
                                    <td><?php echo htmlspecialchars($client['contact_no']); ?></td>
                                    <td>
                                        <?php
                                            // Dynamic badge coloring based on lead status
                                            $badge_class = 'bg-secondary';
                                            if ($client['lead_status'] == 'New') $badge_class = 'bg-info';
                                            if ($client['lead_status'] == 'Active') $badge_class = 'bg-success';
                                            if ($client['lead_status'] == 'Closed') $badge_class = 'bg-danger';
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($client['lead_status']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($client['event_type']); ?></td>
                                    <td><?php echo date('M d, Y H:i A', strtotime($client['date_time_of_event'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No client records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="text-center mt-4 mb-5">
        <a href="client_form_page.php" class="btn btn-outline-primary btn-lg shadow-sm">← Go to Client Entry Form</a>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
