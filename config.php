<?php
// ssm/config.php

// --- Database Configuration ---
// Replace with your actual database credentials if they differ from XAMPP defaults.
define('DB_HOST', 'localhost');      // Database host (usually 'localhost')
define('DB_USER', 'root');           // Database username (default XAMPP is 'root')
define('DB_PASS', '');               // Database password (default XAMPP is empty)
define('DB_NAME', 'studentsemestermanager'); // Your database name

// --- Establish Database Connection using MySQLi ---
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection status
if ($conn->connect_error) {
    // If connection fails, stop the script and display an error message.
    // In a production environment, you might want to log this error instead of displaying it publicly.
    die("Database Connection Failed: " . $conn->connect_error);
}

// Set the character set to utf8mb4 for broader character support (recommended)
if (!$conn->set_charset("utf8mb4")) {
    // Handle potential error if charset setting fails (optional, but good practice)
    error_log("Error loading character set utf8mb4: " . $conn->error);
    // You might choose to die() here as well, depending on requirements.
}

// --- Session Configuration (Optional but good practice to start here) ---
// Start the session if it's not already started.
// We'll need this for user authentication in later phases.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Base URL Configuration (Optional but helpful for links/redirects) ---
// Detects http or https and the host name to create a base URL.
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$script_name = dirname($_SERVER['SCRIPT_NAME']); // Gets the directory path of the script relative to the document root

// Adjust script_name if the config file is included from a subdirectory
// Example: if config.php is in the root 'ssm/' directory
$base_path = rtrim($script_name, '/\\'); // Remove trailing slashes if any

// Define a constant for the base URL (e.g., http://localhost/ssm)
// Note: Adjust '/ssm' if your project folder has a different name or is nested deeper.
// If XAMPP serves directly from htdocs, and your folder is 'ssm', this should work.
// If your structure is different (e.g., localhost serves 'ssm/public'), adjust accordingly.
define('BASE_URL', $protocol . $host . $base_path);


// --- End of Configuration ---
// The $conn object is now available for use in other scripts that include this file.
// No closing PHP tag needed if it's the end of the file.
?>