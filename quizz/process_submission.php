<?php
// ==========================================================
// 1. Force Error Reporting
error_reporting(E_ALL); 
ini_set('display_errors', 1);
// ==========================================================

// NOTE: db_connect.php MUST include session_start();
require_once "db_connect.php"; 

// --- DEBUG CHECKPOINT 1 ---
if (!isset($conn)) {
    die("FATAL ERROR: db_connect.php did not successfully create the \$conn database object.");
}

// ---------------------------
// Security Check: Ensure final submission data exists
if (!isset($_SESSION["final_submission"])) {
    // If submission data is missing, redirect to the dashboard
    header("location: participant_dashboard.php");
    exit;
}

// Extract submission data
$submission_data = $_SESSION['final_submission'];
$attempt_id = $submission_data['attempt_id'];
$quiz_id = $submission_data['quiz_id'];
$user_answers = $submission_data['answers']; // Associative array: [question_id => selected_option]
$contestant_number = $submission_data['contestant_number'];
$user_id = $_SESSION["user_id"]; 

$score = 0;
$total_correct = 0;
$total_questions = count($user_answers);
$all_answers_saved = true; // Flag to track successful saving of individual answers

// ==========================================================
// STEP 1: FETCH CORRECT ANSWERS AND GRADE
// FIX: Changed SELECT column from 'correct_option' to 'correct_answer'
// ==========================================================

$sql_correct_answers = "
    SELECT question_id, correct_answer 
    FROM questions  
    WHERE quiz_id = ?
";

$correct_answers = [];

if ($stmt = $conn->prepare($sql_correct_answers)) {
    $stmt->bind_param("i", $quiz_id);
    
    if (!$stmt->execute()) {
        // Execution failed (e.g., connection lost during query)
        die("FATAL ERROR: SQL execution failed while fetching correct answers: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Store correct answers in a simple map: [question_id => correct_answer]
        // Note: Accessing using 'correct_answer' now
        $correct_answers[$row['question_id']] = $row['correct_answer'];
    }
    $stmt->close();
    
} else {
    // If this error now occurs, it means the 'correct_answer' column is missing from the 'questions' table.
    $mysql_error_message = $conn->error; 
    
    die("
        <h1 style='color: red;'>!!! FATAL DB PREPARATION ERROR (POST-FIX) !!!</h1>
        <p>The system could not prepare the SQL statement. This now likely means the column <code>correct_answer</code> is missing from your <strong>questions</strong> table.</p>
        <hr>
        <strong>Attempted SQL Query:</strong> 
        <pre>" . htmlspecialchars($sql_correct_answers) . "</pre>
        <strong>MySQL Error Details:</strong> 
        <pre>" . htmlspecialchars($mysql_error_message) . "</pre>
        <p>Please ensure the <code>questions</code> table has a column named <code>correct_answer</code>.</p>
    ");
}

// Compare user answers against correct answers
foreach ($user_answers as $q_id => $user_choice) {
    // Note: $user_choice might be '' (empty string) if unanswered.
    $correct_choice = $correct_answers[$q_id] ?? null; // Get correct answer, default to null if question is missing from map

    if ($user_choice !== '' && $user_choice === $correct_choice) {
        $total_correct++;
        // Assuming a simple 1 point per question scoring
        $score++;
    }
}

// ==========================================================
// STEP 2: SAVE INDIVIDUAL USER ANSWERS
// ==========================================================

// Requires a table named 'user_answers' which you have.
$sql_save_answer = "
    INSERT INTO user_answers (attempt_id, question_id, user_choice, is_correct) 
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE user_choice = VALUES(user_choice), is_correct = VALUES(is_correct)
";

if ($stmt_save = $conn->prepare($sql_save_answer)) {
    
    foreach ($user_answers as $q_id => $user_choice) {
        $correct_choice = $correct_answers[$q_id] ?? null;
        // Determine if the answer is correct (1) or incorrect (0)
        // If unanswered ($user_choice is empty), we mark it incorrect (0)
        $is_correct = ($user_choice !== '' && $user_choice === $correct_choice) ? 1 : 0;
        
        $user_choice_sanitized = empty($user_choice) ? NULL : $user_choice; // Store NULL if unanswered
        
        // Use bind_param with 'iisi' (integer, integer, string, integer)
        $stmt_save->bind_param("iisi", $attempt_id, $q_id, $user_choice_sanitized, $is_correct);

        if (!$stmt_save->execute()) {
            error_log("DB Error saving answer for question $q_id in attempt $attempt_id: " . $stmt_save->error);
            $all_answers_saved = false;
        }
    }
    $stmt_save->close();
} else {
    error_log("DB Preparation Error for saving individual answers: " . $conn->error);
    $all_answers_saved = false;
}

// ==========================================================
// STEP 3: UPDATE ATTEMPTS TABLE (Mark as finished, save score)
// ==========================================================

$percentage = $total_questions > 0 ? ($total_correct / $total_questions) * 100 : 0;

$sql_update_attempt = "
    UPDATE attempts 
    SET end_time = NOW(), total_score = ?, total_correct = ?, percentage = ?
    WHERE attempt_id = ? AND user_id = ?
";

if ($stmt_update = $conn->prepare($sql_update_attempt)) {
    // Use bind_param with 'ddiii' (double, double, integer, integer, integer)
    $stmt_update->bind_param("ddiii", $score, $total_correct, $percentage, $attempt_id, $user_id);
    $stmt_update->execute();
    $stmt_update->close();
} else {
    error_log("DB Preparation Error for updating attempt record: " . $conn->error);
}

// ==========================================================
// STEP 4: CLEANUP AND REDIRECT
// ==========================================================

// Store final results for the results page
$_SESSION['latest_results'] = [
    'quiz_id' => $quiz_id,
    'score' => $total_correct,
    'total' => $total_questions,
    'percentage' => round($percentage, 2),
    'contestant_number' => $contestant_number
];

unset($_SESSION['final_submission']); // Clear the final submission buffer

// Redirect to the results display page
header("location: results.php");
exit;
?>
