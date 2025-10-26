<?php
// config.php

// ----------------------------------------------------------
// 0. TIMEZONE SETTING (CRITICAL ADDITION)
// ----------------------------------------------------------
/**
 * !!! IMPORTANT: Replace 'Africa/Lagos' with your server's local timezone !!!
 * Use the correct format, e.g., 'America/New_York' or 'Europe/London'.
 */
date_default_timezone_set('Africa/Lagos'); 

// ----------------------------------------------------------
// 1. SESSION START & ERROR REPORTING
// ----------------------------------------------------------
// Fixes "Ignoring session_start() because a session is already active" warning
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ERROR REPORTING (CRITICAL FOR DEBUGGING - Should be disabled in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ----------------------------------------------------------
// 2. Database Configuration
// ----------------------------------------------------------
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', ''); 
define('DB_NAME', 'cbt_db1'); 

// Data Source Name (DSN) for PDO connection
define('DB_DSN', 'mysql:host=' . DB_SERVER . ';dbname=' . DB_NAME . ';charset=utf8mb4');

// ----------------------------------------------------------
// 3. PDO Connection Attempt
// ----------------------------------------------------------
try {
    // Establish the connection
    $pdo = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD);
    
    // Set connection attributes for error handling and fetching data
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    // Log the error (best practice)
    error_log("Database Connection Error: " . $e->getMessage());
    
    // Display a generic error to the user
    die("FATAL ERROR: Could not establish a database connection. Please check config.php and your database server. Details: " . $e->getMessage());
}


// ----------------------------------------------------------
// 4. User Role Check Functions
// ----------------------------------------------------------

/**
 * Helper function to check if a user is logged in
 * @return bool
 */
function is_logged_in() {
    // Checks for both 'loggedin' flag (from successful login) AND 'user_id' (for robustness)
    return isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true && isset($_SESSION["user_id"]);
}

/**
 * Checks if a user is currently logged in and has the 'admin' user_type.
 * @return bool
 */
function is_admin() {
    return is_logged_in() && isset($_SESSION["user_type"]) && $_SESSION["user_type"] === 'admin';
}

/**
 * Checks if a user is currently logged in and has the 'teacher' user_type.
 * @return bool
 */
function is_teacher() {
    return is_logged_in() && isset($_SESSION["user_type"]) && $_SESSION["user_type"] === 'teacher';
}

/**
 * Checks if a user is currently logged in and has the 'student' user_type AND CRITICAL class_year.
 * @return bool
 */
function is_student() {
    // ✅ FIX: Checking for 'class_year' for consistency with test access logic.
    // NOTE: You can add back '&& isset($_SESSION["class_id"])' if required elsewhere.
    return is_logged_in() 
        && isset($_SESSION["user_type"]) 
        && $_SESSION["user_type"] === 'student'
        && isset($_SESSION["class_year"]); // CRITICAL: Ensures essential data exists
}

// ----------------------------------------------------------
// 5. Authentication Guard
// ----------------------------------------------------------

/**
 * Ensures the user is logged in, redirecting to login.php if not.
 * Optionally checks for a specific role.
 * @param array|string|null $allowed_roles Roles permitted access (e.g., 'admin', ['teacher', 'admin'])
 * @param string $redirect_to The file to redirect to if role check fails.
 */
function require_login($allowed_roles = null, $redirect_to = 'login.php') {
    if (!is_logged_in()) {
        header("Location: $redirect_to");
        exit;
    }

    if ($allowed_roles !== null) {
        $user_role = $_SESSION['user_type'];
        
        // Ensure allowed_roles is an array for consistent checking
        if (!is_array($allowed_roles)) {
            $allowed_roles = [$allowed_roles];
        }

        if (!in_array($user_role, $allowed_roles)) {
            // Redirect unauthorized user to their default dashboard
            // We use user_type directly as the prefix for the dashboard filename.
            $default_dashboard = $user_role . '_dashboard.php';
            header("Location: $default_dashboard");
            exit;
        }
    }
}
// ----------------------------------------------------------