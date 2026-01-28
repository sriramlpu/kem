<?php
// Include the header file which starts the session and sets up DB variables
require_once 'header.php'; 
$servername = "localhost";
$username 	= "kmkglobal_web";
$password 	= "tI]rfPhdOo9zHdKw";
$dbname 	= "kmkglobal_web";
// Connect to the database
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// SQL Query to select the most important summary data
$sql = "SELECT client_id, client_name, contact_no, date_time_of_event, event_type, lead_owner, expected_budget FROM client_information ORDER BY client_id DESC";
$result = $conn->query($sql);

?>

<h2 class="mb-4"><i class="fas fa-list-alt"></i> All Client Events</h2>

<div class="card shadow-sm">
    <div class="card-header bg-primary text-white">
        Client Summary List
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Client Name</th>
                        <th scope="col">Contact</th>
                        <th scope="col">Event Date</th>
                        <th scope="col">Event Type</th>
                        <th scope="col">Lead Owner</th>
                        <th scope="col">Budget</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result->num_rows > 0): 
                        while($row = $result->fetch_assoc()):
                            // Format date for better readability
                            $event_date = date('M d, Y', strtotime($row['date_time_of_event']));
                            $event_time = date('h:i A', strtotime($row['date_time_of_event']));
                    ?>
                    <tr>
                        <th scope="row"><?php echo htmlspecialchars($row['client_id']); ?></th>
                        <td><?php echo htmlspecialchars($row['client_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['contact_no']); ?></td>
                        <td>
                            <strong><?php echo $event_date; ?></strong><br>
                            <small class="text-muted"><?php echo $event_time; ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($row['event_type']); ?></td>
                        <td><?php echo htmlspecialchars($row['lead_owner']); ?></td>
                        <td><?php echo htmlspecialchars($row['expected_budget']); ?></td>
                        <td>
                            <a href="view_client?id=<?php echo $row['client_id']; ?>" class="btn btn-sm btn-info text-white me-1" title="View Details">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="edit_client?id=<?php echo $row['client_id']; ?>" class="btn btn-sm btn-edit" title="Edit Client">
                                <i class="fas fa-edit"></i>
                            </a>
                        </td>
                    </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <i class="fas fa-exclamation-circle me-2"></i> No client records found. Start by adding a new client!
                        </td>
                    </tr>
                    <?php
                    endif;
                    $conn->close();
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div> </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>