<?php
// Define database connection parameters
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');  // Laragon default username
define('DB_PASSWORD', '');      // Laragon default password (often empty)
define('DB_NAME', 'quiz_competition'); // **Replace with your actual database name**

/* Attempt to connect to MySQL database */
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($conn === false){
    die("ERROR: Could not connect. " . $conn->connect_error);
}

// Set character set to UTF-8 for proper handling of special characters
$conn->set_charset("utf8mb4");

// Start a session to manage user login state across pages
session_start();
?>