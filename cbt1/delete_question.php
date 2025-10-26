<?php
// delete_question.php

/**
 * Handles the deletion of a specific question from a test,
 * ensuring only administrators can access this script.
 * Options associated with the question are deleted via ON DELETE CASCADE
 * set on the database foreign key constraint.
 */

session_start();
require_once 'config.php'; // Contains database connection ($pdo) and is_admin() function

// Check if the user is an admin
if (!is_admin()) {
    // If not an admin, redirect to login page
    header("location: login.php");
    exit;
}

// Get and sanitize IDs and optional page number from the URL
$question_id = filter_input(INPUT_GET, 'question_id', FILTER_VALIDATE_INT);
$test_id = filter_input(INPUT_GET, 'test_id', FILTER_VALIDATE_INT);
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1; // Get the page number for redirect

// Check for valid IDs
if (!$question_id || !$test_id) {
    $_SESSION['error_message'] = "Invalid or missing Question ID or Test ID.";
    header("location: manage_tests.php");
    exit;
}

// Default redirect path
$redirect_url = "add_questions.php?test_id={$test_id}";

try {
    // Start a transaction for safe operation
    $pdo->beginTransaction();

    // 1. Get question text before deletion for the success message
    $sql_fetch = "SELECT question_text FROM questions WHERE question_id = :question_id AND test_id = :test_id";
    $stmt_fetch = $pdo->prepare($sql_fetch);
    $stmt_fetch->bindParam(':question_id', $question_id, PDO::PARAM_INT);
    $stmt_fetch->bindParam(':test_id', $test_id, PDO::PARAM_INT);
    $stmt_fetch->execute();
    $question = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

    if (!$question) {
        // Question not found or doesn't belong to the specified test
        $pdo->rollBack();
        $_SESSION['error_message'] = "Question not found or does not belong to this test.";
    } else {
        $question_text = htmlspecialchars($question['question_text']);

        // 2. Delete the question (ON DELETE CASCADE should handle associated options)
        $sql_delete = "DELETE FROM questions WHERE question_id = :question_id";
        $stmt_delete = $pdo->prepare($sql_delete);
        $stmt_delete->bindParam(':question_id', $question_id, PDO::PARAM_INT);
        
        if ($stmt_delete->execute()) {
            // Success
            $pdo->commit();
            $_SESSION['success_message'] = "Question '{$question_text}' (ID: {$question_id}) successfully deleted.";
            
            // 3. PAGINATION LOGIC: Determine where to redirect
            $limit_per_page = 10;
            
            // a. Get the new total count of questions
            $sql_count = "SELECT COUNT(*) FROM questions WHERE test_id = :test_id";
            $stmt_count = $pdo->prepare($sql_count);
            $stmt_count->bindParam(':test_id', $test_id, PDO::PARAM_INT);
            $stmt_count->execute();
            $new_total_questions = (int)$stmt_count->fetchColumn(); 
            
            // b. Calculate the new total pages
            $new_total_pages = $new_total_questions > 0 ? ceil($new_total_questions / $limit_per_page) : 1;
            
            // c. Set the target page for redirection
            $target_page = $current_page;
            
            // If the original page number is now greater than the new total pages, go back one page.
            if ($current_page > $new_total_pages) {
                $target_page = $new_total_pages; 
            }
            
            // Build the final redirect URL
            $redirect_url = "add_questions.php?test_id={$test_id}&page={$target_page}";

        } else {
            // Deletion failed
            $pdo->rollBack();
            $_SESSION['error_message'] = "Failed to delete question from the database.";
        }
    }

} catch (PDOException $e) {
    // Catch any unexpected database errors
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = "Database Error: " . $e->getMessage();
}

unset($pdo);

// 4. Redirect using the determined URL (PRG pattern)
header("location: " . $redirect_url);
exit;
?>