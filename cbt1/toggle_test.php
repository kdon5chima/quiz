<?php
// toggle_test.php - API endpoint to switch a test between Active (1) and Inactive (0).

session_start();
require_once 'config.php';
require_once 'helpers.php'; // Assuming is_admin() and is_teacher() are here

header('Content-Type: application/json');

// Get current user context
$user_id = $_SESSION['user_id'] ?? null;
// NOTE: Assuming is_admin() returns true/false based on $_SESSION['user_role']
$is_admin = is_admin(); 
$is_teacher = is_teacher();

// ====================================================
// 1. Authorization and Method Check
// ====================================================
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (!$user_id) {
    // User is not logged in
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

if (!$is_admin && !$is_teacher) {
    // User is logged in, but lacks the necessary role
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Authorization denied. User role restricted.']);
    exit;
}

// ====================================================
// 2. Input Validation
// ====================================================
$test_id = filter_input(INPUT_POST, 'test_id', FILTER_VALIDATE_INT);
$current_status = filter_input(INPUT_POST, 'current_status', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1]]);

if (!$test_id || $current_status === false || $current_status === null) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid test ID or status provided.']);
    exit;
}

// Determine the new status: Flip the current status (0 becomes 1, 1 becomes 0)
$new_status = $current_status == 1 ? 0 : 1;

try {
    // ====================================================
    // 3. Ownership Check (CRITICAL SECURITY STEP)
    // ====================================================
    $sql_check = "SELECT created_by FROM tests WHERE test_id = :test_id";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->bindParam(':test_id', $test_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $test_creator_id = $stmt_check->fetchColumn();

    if ($test_creator_id === false) {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Test not found.']);
        exit;
    }
    
    // Deny access if user is a teacher AND not the creator
    if (!$is_admin && $test_creator_id != $user_id) {
        http_response_code(403); // Forbidden
        echo json_encode(['success' => false, 'message' => 'Permission denied. You can only modify tests you created.']);
        exit;
    }
    
    // ====================================================
    // 4. Update the database
    // ====================================================
    $sql_update = "UPDATE tests SET is_active = :new_status, last_modified = NOW() WHERE test_id = :test_id";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->bindParam(':new_status', $new_status, PDO::PARAM_INT);
    $stmt_update->bindParam(':test_id', $test_id, PDO::PARAM_INT);
    $stmt_update->execute();
    
    // Rows affected can be 0 if the status was already the target value.
    // The key is to return the intended status ($new_status) as success=true means it's now set.
    
    // Success response
    echo json_encode([
        'success' => true, 
        'new_status' => $new_status, // <-- FIX: Always return the intended new status
        'message' => 'Test status updated successfully.'
    ]);

} catch (PDOException $e) {
    // Catch database errors
    http_response_code(500); // Internal Server Error
    error_log("DB Error toggling test status: " . $e->getMessage());
    
    // TEMPORARY FIX: EXPOSE THE ERROR TO THE AJAX RESPONSE
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]); 
    // ^^^ THIS WILL SHOW YOU THE MISSING COLUMN/TABLE NAME ^^^
}

exit;