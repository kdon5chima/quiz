<?php
// api/save_answer.php - Saves the student's answer using the secure hash.

// -----------------------------------------------------------------------
// IMPORTANT NOTE: session_start() has been removed here. 
// It must only be executed in config.php (or a global bootstrap file)
// to avoid "Headers already sent" errors and warnings.
// -----------------------------------------------------------------------
require_once '../config.php'; 
require_once '../helpers.php'; 

// Set the header BEFORE any output to prevent "Headers already sent" errors.
header('Content-Type: application/json');

// Get the user's IP for better security logging
$user_ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

// Function to handle and log errors cleanly
function send_error($message, $http_code = 500) {
    global $user_ip;
    http_response_code($http_code);
    
    // Log errors with IP for security/debugging
    error_log("API Error ({$http_code}) at save_answer.php from IP: {$user_ip}. Message: {$message}");
    
    echo json_encode(["success" => false, "error" => $message]);
    exit;
}

// --- 1. ACCESS CONTROL AND INITIAL CHECKS ---
// Assuming is_student() is properly defined in config.php or helpers.php
if (!is_student() || $_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_SESSION["user_id"])) {
    send_error("Unauthorized access or wrong method.", 403);
}

$user_id = $_SESSION["user_id"];

// Get and sanitize inputs
$result_id = filter_input(INPUT_POST, 'result_id', FILTER_VALIDATE_INT);
$question_id = filter_input(INPUT_POST, 'question_id', FILTER_VALIDATE_INT);

// CRITICAL: Get the secure hash sent from the front-end
$secure_hash = filter_input(INPUT_POST, 'selected_option', FILTER_SANITIZE_STRING) ?? ''; 

if (!$result_id || !$question_id) {
    send_error("Invalid ID format.", 400);
}

// 2. SECURE HASH VALIDATION
$answer_to_save = null; // Default to NULL/empty for un-answering/clearing answer

// Check if the input hash is a non-empty string
if (!empty($secure_hash) && is_string($secure_hash)) {
    
    // Check security map existence
    if (!isset($_SESSION['secure_test_map'][$result_id]) || !isset($_SESSION['secure_test_map'][$result_id][$question_id])) {
        // IMPROVED LOGGING: Added IP and user ID
        error_log("Security error (Map not found): User {$user_id} from IP {$user_ip}. R:{$result_id}, Q:{$question_id}.");
        send_error('Security map not found. Session expired or tampered.', 401);
    }
    
    $secure_map_q = $_SESSION['secure_test_map'][$result_id][$question_id];

    // Validate the hash against the map keys
    if (array_key_exists($secure_hash, $secure_map_q)) { 
        $answer_to_save = $secure_hash; // Validated secure hash string
    } else {
        // IMPROVED LOGGING: Added IP, user ID, and the failed hash
        error_log("Security breach attempt (Invalid Hash): User {$user_id} from IP {$user_ip}. Hash '{$secure_hash}' not in session map for QID {$question_id}.");
        send_error('Invalid option selected (Security check failed).', 400);
    }
}


try {
    // 3. Authorization Check (Result belongs to User AND is not submitted)
    // NOTE: This check adds a small overhead but is CRITICAL for security.
    $check_sql = "SELECT 1 FROM results WHERE result_id = :result_id AND user_id = :user_id AND is_submitted = 0";
    $stmt_check = $pdo->prepare($check_sql);
    $stmt_check->bindParam(':result_id', $result_id, PDO::PARAM_INT);
    $stmt_check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_check->execute();

    if ($stmt_check->rowCount() === 0) {
        send_error('Test not found, unauthorized, or already submitted.', 403);
    }

    // CRITICAL: If answer_to_save is null (unanswered), use an explicit empty string 
    // to ensure compatibility with the database.
    $final_value_to_save = ($answer_to_save === null) ? '' : $answer_to_save;

    // 4. Insert or Update the student_answers table (UPSERT)
    // PERFORMANCE OPTIMIZATION: Relies on a UNIQUE KEY on (result_id, question_id) 
    // in the 'student_answers' table for fast updates.
    $sql_upsert = "
        INSERT INTO student_answers (result_id, question_id, selected_option) 
        VALUES (:result_id, :question_id, :final_value_to_save)
        ON DUPLICATE KEY UPDATE selected_option = VALUES(selected_option)
    ";
    $stmt_upsert = $pdo->prepare($sql_upsert);
    $stmt_upsert->bindParam(':result_id', $result_id, PDO::PARAM_INT);
    $stmt_upsert->bindParam(':question_id', $question_id, PDO::PARAM_INT);
    
    // Bind the final value as a string (either the hash or '')
    $stmt_upsert->bindParam(':final_value_to_save', $final_value_to_save, PDO::PARAM_STR); 
    
    $stmt_upsert->execute();

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    // Log the actual database error internally
    $db_error_message = $e->getMessage();
    error_log("DB Error saving answer: " . $db_error_message);
    
    // Send a generic, non-technical error to the client for security
    send_error('Database operation failed. Please try again.', 500);
}
?>