<?php
// Start the session
session_start();

// 1. Database Connection Details
$servername = "localhost";
$username = "kmkglobal_web";
$password = "tI]rfPhdOo9zHdKw";
$dbname = "kmkglobal_web";

// 2. Create connection
// $conn will be the connection object
$conn = new mysqli($servername, $username, $password, $dbname);

// 3. Check connection
if ($conn->connect_error) {
    // Stop execution and display error if connection fails
    http_response_code(500); // Set HTTP status code for Server Error
    die(json_encode(['error' => 'Database Connection failed: ' . $conn->connect_error]));
}

// ----------------------------------------------------
// The rest of your logic, ensuring secure query execution

// Ensure the user is logged in
if (!isset($_SESSION['userId'])) {
    http_response_code(403); // Set HTTP status code for Forbidden
    echo json_encode(['error' => 'Access Denied: User not logged in']);
    exit;
}

// Get the salesperson's ID from the session
$salesperson_id = $_SESSION['userId'];

// 4. Prepare SQL query using a '?' placeholder for security
$sql_clients = "
    SELECT
        client_id,
        client_name,
        lead_status,
        contact_no,
        date_time_of_event,
        event_type,
        workflow_stage,
        budget_draft_sales
    FROM clients
    WHERE assigned_to = ?  -- Use '?' placeholder
    ORDER BY created_at DESC
";

// 5. Prepare and execute the query using MySQLi Prepared Statements
if ($stmt = $conn->prepare($sql_clients)) {
    // Bind the variable to the placeholder. 's' denotes the type is string.
    // Adjust 's' to 'i' if your userId is an integer. Assuming string for safety.
    $stmt->bind_param("s", $salesperson_id);

    // Execute the statement
    $stmt->execute();

    // Get the result set
    $result = $stmt->get_result();

    $clients = [];
    while ($row = $result->fetch_assoc()) {
        $clients[] = $row;
    }

    // Close the statement
    $stmt->close();

    // 6. Return the JSON response
    if (!empty($clients)) {
        echo json_encode($clients);
    } else {
        // No clients found
        echo json_encode([]);
    }

} else {
    // Handle prepare error
    http_response_code(500);
    echo json_encode(['error' => 'Failed to prepare SQL statement: ' . $conn->error]);
}

// 7. Close the database connection (optional, as PHP usually closes it on script end)
$conn->close();
?>