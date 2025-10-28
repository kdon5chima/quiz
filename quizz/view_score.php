<?php
// ==========================================================
// 1. Force Error Reporting
error_reporting(E_ALL); 
ini_set('display_errors', 1);
// ==========================================================

require_once "db_connect.php"; 

// --- Security Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'participant') {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$attempt_id = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : null;

// Ensure we have a valid attempt ID
if (!$attempt_id) {
    header("location: participant_dashboard.php");
    exit;
}

// ----------------------------------------------------------
// FETCH RESULTS DATA FROM DATABASE
// ----------------------------------------------------------
$sql_results = "
    SELECT 
        a.score, 
        a.contestant_number, 
        q.title,
        q.quiz_id,
        (SELECT COUNT(*) FROM questions WHERE quiz_id = q.quiz_id) AS total_questions_count,
        (SELECT COUNT(*) FROM user_answers WHERE attempt_id = a.attempt_id AND is_correct = 1) AS correct_count,
        (SELECT COUNT(*) FROM user_answers WHERE attempt_id = a.attempt_id AND submitted_answer IS NOT NULL AND submitted_answer != '') AS answered_count
    FROM attempts a
    JOIN quizzes q ON a.quiz_id = q.quiz_id
    WHERE a.attempt_id = ? AND a.user_id = ?";

$results = null;

if ($stmt = $conn->prepare($sql_results)) {
    $stmt->bind_param("ii", $attempt_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $results = $row;
    }
    $stmt->close();
}

if (!$results) {
    // Attempt not found or doesn't belong to this user
    header("location: participant_dashboard.php");
    exit;
}

// Map fetched data to variables for display
$score = $results['score'];
$total_questions = $results['total_questions_count']; // Total possible questions
$correct_count = $results['correct_count']; // Correctly answered questions
$answered_count = $results['answered_count']; // Total questions answered (not blank)

$percentage = ($total_questions > 0) ? round(($score / $total_questions) * 100) : 0;
$contestant_number = htmlspecialchars($results['contestant_number']);
$quiz_title = htmlspecialchars($results['title']) . " Results";


$result_message = "Well done! Your quiz attempt is complete.";
$result_class = "text-success";
if ($percentage < 50) {
    $result_message = "You completed the quiz. Review the material and try again!";
    $result_class = "text-warning-custom"; // Use custom class for better warning styling
}

// Determine if the main score circle should be green, blue, or red
$score_circle_class = "bg-primary";
if ($percentage >= 75) {
    $score_circle_class = "bg-success";
} elseif ($percentage < 50) {
    $score_circle_class = "bg-danger";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $quiz_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        .results-container { max-width: 600px; margin-top: 50px; }
        .card { border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .score-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            color: white;
            margin: 0 auto 20px;
            /* Default background, adjusted by PHP logic */
        }
        .bg-primary { background: linear-gradient(45deg, #0d6efd, #0b5ed7); }
        .bg-success { background: linear-gradient(45deg, #198754, #157347); }
        .bg-danger { background: linear-gradient(45deg, #dc3545, #b02a37); }
        
        /* Custom warning color for text readability */
        .text-warning-custom { color: #cc8400; font-weight: 600;} 
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-secondary shadow-sm">
        <div class="container results-container">
            <a class="navbar-brand fw-bold" href="participant_dashboard.php"><i class="fas fa-brain"></i> Quiz Competition</a>
        </div>
    </nav>

    <div class="container results-container">
        <div class="card p-5 text-center">
            <h1 class="mb-4 display-5 fw-bold text-primary"><i class="fas fa-trophy me-2"></i> Quiz Completed!</h1>
            
            <p class="h4 mb-4">Participant Number: <span class="fw-bold text-danger"><?php echo $contestant_number; ?></span></p>

            <div class="score-circle <?php echo $score_circle_class; ?>">
                <?php echo $percentage; ?>%
            </div>
            
            <p class="h2 mb-4 <?php echo $result_class; ?>">
                <?php echo $result_message; ?>
            </p>

            <ul class="list-group list-group-flush mb-4 text-start">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    **Correct Answers:** <span class="badge bg-success rounded-pill"><?php echo $correct_count; ?></span>
                </li>
                 <li class="list-group-item d-flex justify-content-between align-items-center">
                    **Answered Questions:** <span class="badge bg-info rounded-pill"><?php echo $answered_count; ?> / <?php echo $total_questions; ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    **Total Questions:** <span class="badge bg-primary rounded-pill"><?php echo $total_questions; ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    **Your Final Score:** <span class="badge bg-warning text-dark rounded-pill"><?php echo $score; ?> Points</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center text-muted small">
                    Attempt ID: <?php echo $attempt_id; ?>
                </li>
            </ul>

            <a href="participant_dashboard.php" class="btn btn-primary btn-lg mt-3"><i class="fas fa-tachometer-alt me-2"></i> Go to Dashboard</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
