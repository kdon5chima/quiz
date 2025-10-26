<?php
/**
 * Core application functions.
 * This file must be included in every script that requires utility functions 
 * such as authentication checks.
 */

/**
 * Checks if the currently logged-in user is an administrator.
 * * It relies on $_SESSION being active (session_start() must be called).
 * The user's role is typically set during the login process.
 * * @return bool True if the user is an admin, false otherwise.
 */
function is_admin(): bool {
    // Check if the session is set and the user's role is 'admin'
    // This assumes your login process sets $_SESSION['role'] to 'admin' for admins.
    return isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Utility function to check if any user is logged in.
 * Use this in config.php or other pages where a simple login check is needed.
 * * @return bool True if a user is logged in, false otherwise.
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}
