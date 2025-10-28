<?php
require_once "db_connect.php";

// ---------------------------
// Security Check
// Ensure user is logged in and the method is POST
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("location: login.php");
    exit;
}
if ($_SESSION["user_type"] === 'admin') {
    header("location: admin_dashboard.php"); 
    exit;
}
// ---------------------------

$user_id = $_SESSION["user_id"];
$attempt_id = isset($_POST['attempt_id']) ? (int)$_POST['attempt_id'] : null;
$final_score = 0;

// --- START: NEW CODE TO CAPTURE CONTESTANT NUMBER ---
$contestant_number = isset($_POST['contestant_number']) ? trim($_POST['contestant_number']) : 'N/A';
// --- END: NEW CODE ---

if (!$attempt_id) {
    die("Invalid quiz submission. Missing attempt ID.");
}

// 1. Fetch the associated quiz_id for the attempt (unchanged)
$quiz_id = null;
$sql_get_quiz_id = "SELECT quiz_id FROM attempts WHERE attempt_id = ? AND user_id = ?";
if ($stmt = $conn->prepare($sql_get_quiz_id)) {
    $stmt->bind_param("ii", $attempt_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $quiz_id = $row['quiz_id'];
    }
    $stmt->close();
}

if (!$quiz_id) {
    die("Error: Attempt not found or does not belong to the current user.");
}

// 2. Fetch all correct answers for this quiz (unchanged)
$correct_answers = [];
$sql_correct = "SELECT question_id, correct_answer FROM questions WHERE quiz_id = ?";
if ($stmt = $conn->prepare($sql_correct)) {
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $correct_answers[$row['question_id']] = $row['correct_answer'];
    }
    $stmt->close();
}

// 3. Prepare the statement for saving user answers (user_answers table)
// This matches the logic from your provided submit_quiz.php
$sql_save_answer = "INSERT INTO user_answers (attempt_id, question_id, selected_option, is_correct) VALUES (?, ?, ?, ?)";
$stmt_save = $conn->prepare($sql_save_answer);
$saved_count = 0;

// 4. Loop through submitted answers and calculate score
foreach ($correct_answers as $question_id => $correct_option) {
    // The name attribute in start_quiz.php was 'answer_[question_id]'
    $input_name = 'answer_' . $question_id;
    $selected_option = isset($_POST[$input_name]) ? $_POST[$input_name] : null;

    $is_correct = 0; // FALSE by default

    if ($selected_option !== null) {
        // Compare the submitted answer with the correct answer from the database
        if ($selected_option === $correct_option) {
            $is_correct = 1; // TRUE
            $final_score++;
        }
    }
    
    // Bind parameters and execute the insert into the user_answers table
    // Note: selected_option can be NULL if not answered, but is stored as a string in DB
    $selected_option_db = $selected_option !== null ? $selected_option : '';
    $stmt_save->bind_param("iisi", $attempt_id, $question_id, $selected_option_db, $is_correct);
    if ($stmt_save->execute()) {
        $saved_count++;
    } else {
        // Optional: Log error if insertion fails for a specific answer
        error_log("Failed to insert answer for question_id: $question_id in attempt_id: $attempt_id");
    }
}
$stmt_save->close();

// 5. Update the main attempt record (score, end_time, and CONTESTANT NUMBER)
$sql_update_attempt = "UPDATE attempts SET score = ?, end_time = NOW(), contestant_number = ? WHERE attempt_id = ? AND user_id = ?";

if ($stmt = $conn->prepare($sql_update_attempt)) {
    // Note the bind_param format: 's' for contestant_number
    $stmt->bind_param("isii", $final_score, $contestant_number, $attempt_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

// 6. Cleanup session and redirect (unchanged)
// Remove the quiz session data to allow the next quiz to start cleanly
unset($_SESSION['current_quiz']);

// Redirect to the score page
header("location: view_score.php?attempt_id=" . $attempt_id);
exit;
?>
