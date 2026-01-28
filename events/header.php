<?php
// Start the session to handle success/error messages across pages
session_start();

// Define the base URL for navigation (adjust if needed)
$BASE_URL = "/kmk/events/"; // Example: Adjust this to your folder path

// Database Connection Details (Place this in a separate config.php for larger apps, but keeping it here for simplicity)
$servername = "localhost";
$username = "your_db_user";     // <<--- REPLACE
$password = "your_db_password"; // <<--- REPLACE
$dbname = "your_database_name"; // <<--- REPLACE

// Function to safely display messages stored in the session
function display_session_message() {
    if (isset($_SESSION['message'])) {
        echo $_SESSION['message'];
        unset($_SESSION['message']); // Clear the message after displaying it
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>KMK Event Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --border-color: #dee2e6;
        }
        body { 
            background: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding-top: 70px; /* Space for fixed navbar */
        }
        .navbar {
            background: linear-gradient(90deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }
        .navbar-brand, .nav-link {
            color: white !important;
            font-weight: 500;
        }
        .container { 
            max-width: 1200px; 
            margin-top: 20px;
            padding: 20px;
            background-color: white; 
            border-radius: 15px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .detail-card {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .detail-card h5 {
            color: var(--primary-color);
            border-bottom: 2px solid #eee;
            padding-bottom: 5px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .detail-item {
            padding: 5px 0;
        }
        .detail-item strong {
            color: var(--secondary-color);
            margin-right: 10px;
        }
        /* TOP-RIGHT MESSAGE STYLES */
        .error-alert, .success-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            max-width: 400px;
            animation: slideIn 0.5s forwards;
            font-weight: bold;
            border-radius: 8px;
            padding: 15px;
        }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        @keyframes slideIn {
            from { right: -400px; opacity: 0; }
            to { right: 20px; opacity: 1; }
        }
        .btn-edit { background-color: var(--warning-color); color: white; }
        .btn-cancel { background-color: var(--accent-color); color: white; }
        .btn-save { background-color: var(--success-color); color: white; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top shadow">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">KMK Client Portal</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="index.php"><i class="fas fa-plus-circle"></i> New Client Entry</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_all_clients.php"><i class="fas fa-list-alt"></i> View All Clients</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="main-content">
    <?php display_session_message(); // Display any stored message ?>
    <div class="container">