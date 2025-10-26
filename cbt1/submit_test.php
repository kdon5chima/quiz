<?php
// submit_test.php - Finalizes the test, calculates the score, and saves the results.

session_start();
require_once 'config.php';
require_once 'helpers.php'; // Contains database and helper functions

// --- AUTHENTICATION CHECK ---
if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? '') !== "student") {
    header("location: login.php");
    exit;
}
$user_id = $_SESSION["user_id"];

// 1. INPUT VALIDATION & INITIAL SETUP
$result_id = filter_input(INPUT_POST, 'result_id', FILTER_VALIDATE_INT);
$submitted_answers = $_POST['answers'] ?? []; // Array of [question_id => secure_hash]

if (!$result_id) {
    header("location: student_dashboard.php?error=" . urlencode("Submission failed: Invalid result ID."));
    exit;
}

// Check if the test was already submitted and that the result belongs to this user
try {
    // We fetch the existing total_questions value here for the fallback later.
    $sql_check = "SELECT is_submitted, test_id, user_id, total_questions FROM results WHERE result_id = :result_id AND user_id = :user_id";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->bindParam(':result_id', $result_id, PDO::PARAM_INT);
    $stmt_check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $result_row = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$result_row || (int)$result_row['user_id'] !== (int)$user_id) {
        header("location: student_dashboard.php?error=" . urlencode("Submission failed: Access denied or invalid test session."));
        exit;
    }

    $test_id = $result_row['test_id'];
    $db_total_questions = (int)($result_row['total_questions'] ?? 0); // Store existing count
    
    if ($result_row['is_submitted']) {
        header("location: view_result.php?result_id=" . $result_id);
        exit;
    }

    // 2. RETRIEVE SECURE MAPPING & QUESTIONS
    
    // Get the map created in take_test.php
    $secure_mapping = $_SESSION['secure_test_map'][$result_id] ?? null;

    // Fetch all questions for this test to get the total count
    $sql_questions = "SELECT question_id FROM questions WHERE test_id = :test_id";
    $stmt_q = $pdo->prepare($sql_questions);
    $stmt_q->bindParam(':test_id', $test_id, PDO::PARAM_INT);
    $stmt_q->execute();
    $all_questions = $stmt_q->fetchAll(PDO::FETCH_COLUMN, 0); // Get array of question_ids

    $total_questions = count($all_questions);
    
    // *** FIX FOR TOTAL_QUESTIONS BEING ZERO IN RESULTS TABLE ***
    // If the live query failed (e.g., questions table issue), use the count saved at the start of the test.
    if ($total_questions === 0 && $db_total_questions > 0) {
        $total_questions = $db_total_questions;
    }
    // **********************************************************
    
    // Initialize array to track scoring data for all questions
    $final_answers = [];
    foreach ($all_questions as $q_id) {
        $final_answers[$q_id] = [
            'selected_hash' => '', 
            'decoded_key' => null, 
            'is_correct' => 0
        ];
    }
    
    // 3. DECODE & SCORE ANSWERS
    
    // Fetch all correct answers in one go
    $sql_correct_options = "
        SELECT q.question_id, o.option_key AS correct_key 
        FROM questions q
        JOIN options o ON q.question_id = o.question_id
        WHERE q.test_id = :test_id AND o.is_correct = 1
    ";
    $stmt_correct = $pdo->prepare($sql_correct_options);
    $stmt_correct->bindParam(':test_id', $test_id, PDO::PARAM_INT);
    $stmt_correct->execute();
    $correct_keys_map = $stmt_correct->fetchAll(PDO::FETCH_KEY_PAIR); // [question_id => correct_key]

    // Prepare to update/insert the student answers
    $sql_upsert_answer = "
        INSERT INTO student_answers (result_id, question_id, selected_option, is_correct)
        VALUES (:result_id, :question_id, :selected_hash, :is_correct)
        ON DUPLICATE KEY UPDATE 
            selected_option = VALUES(selected_option), 
            is_correct = VALUES(is_correct)
    ";
    $stmt_upsert = $pdo->prepare($sql_upsert_answer);
    
    
    // Loop through all questions
    foreach ($final_answers as $q_id => &$answer_data) {
        $q_id = (int)$q_id;
        $correct_key = $correct_keys_map[$q_id] ?? null;
        
        $submitted_hash = $submitted_answers[$q_id] ?? ''; 
        
        // CRITICAL CHECK: Ensure the submitted hash is a string and not the numeric 0
        if (is_numeric($submitted_hash) && (int)$submitted_hash === 0) {
            $submitted_hash = ''; // Treat numeric 0 as 'no answer'
        }
        
        $decoded_key = null;
        
        // 3a. DECODE: Attempt to find the decoded key ('A', 'B', etc.) using the secure map
        if (!empty($submitted_hash) && $secure_mapping) {
            
            // This logic assumes the map structure is: [question_id => [secure_hash => option_key]]
            $q_map_data = $secure_mapping[$q_id] ?? null;
            
            if (is_array($q_map_data) && array_key_exists($submitted_hash, $q_map_data)) {
                $decoded_key = $q_map_data[$submitted_hash];
            }
        } 
        
        // SCORING & DB UPDATE: We only save an answer entry if a valid hash (non-empty string) was submitted.
        if (!empty($submitted_hash)) {

            // Set the hash for saving (this is the submitted hash string)
            $answer_data['selected_hash'] = $submitted_hash;
            
            // Score: 1 if key was decoded and matches correct key, 0 otherwise.
            $answer_data['is_correct'] = ($decoded_key !== null && $decoded_key === $correct_key) ? 1 : 0;
            
            // Finalize Answer in DB (This is CRITICAL for view_result.php)
            $stmt_upsert->bindParam(':result_id', $result_id, PDO::PARAM_INT);
            $stmt_upsert->bindParam(':question_id', $q_id, PDO::PARAM_INT);
            $stmt_upsert->bindParam(':selected_hash', $answer_data['selected_hash'], PDO::PARAM_STR); 
            $stmt_upsert->bindParam(':is_correct', $answer_data['is_correct'], PDO::PARAM_INT);
            $stmt_upsert->execute();
        }
    }
    unset($answer_data); // Break reference

    // 4. FINAL TALLY AND RESULTS TABLE UPDATE
    
    // Recount answered and correct questions directly from the student_answers table
    $sql_answered_count = "
        SELECT COUNT(*) AS answered, SUM(is_correct) AS correct 
        FROM student_answers 
        WHERE result_id = :result_id
    ";
    $stmt_count = $pdo->prepare($sql_answered_count);
    $stmt_count->bindParam(':result_id', $result_id, PDO::PARAM_INT);
    $stmt_count->execute();
    $tally = $stmt_count->fetch(PDO::FETCH_ASSOC);

    $answered_count = (int)($tally['answered'] ?? 0);
    $correct_count = (int)($tally['correct'] ?? 0);
    
    // Calculate final summary counts
    $wrong_count = $answered_count - $correct_count; 
    
    // Use the potentially fixed $total_questions count here
    $unanswered_count = $total_questions - $answered_count;
    
    // Calculate final score percentage
    $final_score = ($total_questions > 0) ? round(($correct_count / $total_questions) * 100) : 0;
    
    // Serialize and Prepare Secure Map for DB
    $serialized_map_data = json_encode($secure_mapping);

    // Update the results table with all final calculated data
    $sql_update_results = "
        UPDATE results 
        SET 
            is_submitted = 1, 
            end_time = NOW(), 
            submission_time = NOW(), 
            score = :final_score,
            total_questions = :total_q, /* Now uses the potentially fixed count */
            correct_answers = :correct,
            wrong_answers = :wrong,
            unanswered = :unanswered,
            secure_answer_map = :map_data
        WHERE result_id = :result_id
    ";

    $stmt_update = $pdo->prepare($sql_update_results);
    $stmt_update->bindParam(':final_score', $final_score, PDO::PARAM_INT);
    $stmt_update->bindParam(':total_q', $total_questions, PDO::PARAM_INT);
    $stmt_update->bindParam(':correct', $correct_count, PDO::PARAM_INT);
    $stmt_update->bindParam(':wrong', $wrong_count, PDO::PARAM_INT);
    $stmt_update->bindParam(':unanswered', $unanswered_count, PDO::PARAM_INT);
    $stmt_update->bindParam(':map_data', $serialized_map_data, PDO::PARAM_STR);
    $stmt_update->bindParam(':result_id', $result_id, PDO::PARAM_INT);
    
    if (!$stmt_update->execute()) {
        throw new Exception("Failed to finalize results table update.");
    }
    
    // 5. CLEAN UP & REDIRECT

    // Remove secure map from session after submission
    unset($_SESSION['secure_test_map'][$result_id]);

    header("location: view_result.php?result_id=" . $result_id);
    exit;

} catch (PDOException $e) {
    error_log("DB Submission Error for result ID {$result_id}: " . $e->getMessage());
    header("location: student_dashboard.php?error=" . urlencode("A database error occurred during submission. Please contact support."));
    exit;
} catch (Exception $e) {
    error_log("App Submission Error for result ID {$result_id}: " . $e->getMessage());
    header("location: student_dashboard.php?error=" . urlencode("An application error occurred during final submission."));
    exit;
}
?>