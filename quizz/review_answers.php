<?php
// review_answers.php
require_once "db_connect.php";

// ---------------------------
// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'participant') {
    header("location: login.php");
    exit;
}
// ---------------------------

$attempt_id = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : null;
$user_id = $_SESSION["user_id"];
$attempt_data = null;
$review_details = [];

if (!$attempt_id) {
    header("location: participant_dashboard.php");
    exit;
}

// 1. Fetch Attempt and Quiz Details (including contestant_number)
$sql_attempt = "
    SELECT 
        a.score, 
        a.contestant_number, /* <-- Fetching the contestant number */
        q.title, 
        q.total_questions
    FROM attempts a
    JOIN quizzes q ON a.quiz_id = q.quiz_id
    WHERE a.attempt_id = ? AND a.user_id = ?";

if ($stmt = $conn->prepare($sql_attempt)) {
    $stmt->bind_param("ii", $attempt_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $attempt_data = $result->fetch_assoc();
    } else {
        // Attempt ID is invalid or does not belong to the current user
        header("location: participant_dashboard.php");
        exit;
    }
    $stmt->close();
}

if (!$attempt_data) {
    header("location: participant_dashboard.php");
    exit;
}

// 2. Fetch all Question/Answer Review Details
// FIX: Changed table from attempt_questions to user_answers, and updated column names
$sql_review = "
    SELECT 
        q.question_text, 
        ua.selected_option AS user_answer, /* Correct column for user submission */
        ua.is_correct, 
        q.correct_answer AS correct_answer_text /* Correct column for correct answer */
    FROM user_answers ua /* Correct table name */
    JOIN questions q ON ua.question_id = q.question_id
    WHERE ua.attempt_id = ?";

if ($stmt_review = $conn->prepare($sql_review)) {
    $stmt_review->bind_param("i", $attempt_id);
    $stmt_review->execute();
    $result_review = $stmt_review->get_result();
    
    while($row = $result_review->fetch_assoc()) {
        $review_details[] = $row;
    }
    $stmt_review->close();
}

$total_correct = $attempt_data['score'];
$total_questions = $attempt_data['total_questions'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Answers: <?php echo htmlspecialchars($attempt_data['title']); ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .correct-answer { background-color: #d4edda; border-left: 5px solid #28a745; }
        .incorrect-answer { background-color: #f8d7da; border-left: 5px solid #dc3545; }
        .question-card { margin-bottom: 20px; border-radius: 8px; overflow: hidden; }
        .question-body { padding: 1.5rem; }
        .question-header { padding: 1rem 1.5rem; background-color: #f4f4f4; border-bottom: 1px solid #ddd; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-secondary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="participant_dashboard.php"><i class="fas fa-brain"></i> Quiz Competition</a>
            <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="text-primary"><i class="fas fa-eye"></i> Review: <?php echo htmlspecialchars($attempt_data['title']); ?></h1>
            <a href="view_score.php?attempt_id=<?php echo $attempt_id; ?>" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-chart-bar"></i> View Summary
            </a>
        </div>

        <!-- START: CONTESTANT DISPLAY (NEW SECTION) -->
        <?php 
        $contestant = htmlspecialchars($attempt_data['contestant_number']);
        $score_badge = "<span class='badge bg-success fs-5'>" . $total_correct . " / " . $total_questions . "</span>";
        if (!empty($contestant) && $contestant !== 'N/A'): 
        ?>
        <div class="alert alert-info d-flex justify-content-between align-items-center shadow-sm mb-5">
            <p class="lead fw-bold mb-0">
                Reviewing Submission for: 
                <span class="badge bg-danger fs-5 py-2 px-3 ms-2">
                    <?php echo $contestant; ?>
                </span>
            </p>
            <p class="lead fw-bold mb-0">
                Final Score: <?php echo $score_badge; ?>
            </p>
        </div>
        <?php else: ?>
        <div class="alert alert-info d-flex justify-content-between align-items-center shadow-sm mb-5">
            <p class="lead fw-bold mb-0">
                Final Score: <?php echo $score_badge; ?>
            </p>
        </div>
        <?php endif; ?>
        <!-- END: CONTESTANT DISPLAY -->

        <?php if (!empty($review_details)): ?>
            <?php $question_number = 1; foreach ($review_details as $review): ?>
                <?php 
                    $is_correct = (bool)$review['is_correct'];
                    $card_class = $is_correct ? 'correct-answer' : 'incorrect-answer';
                    $icon_class = $is_correct ? 'fas fa-check-circle text-success' : 'fas fa-times-circle text-danger';
                    $correct_text = htmlspecialchars($review['correct_answer_text']);
                    $user_text = htmlspecialchars($review['user_answer']);
                    
                    // Handle case where user didn't answer
                    if ($user_text === '') {
                        $user_text = "— Not Answered —";
                    }
                ?>

                <div class="card shadow-sm question-card <?php echo $card_class; ?>">
                    <div class="question-header">
                        <h5 class="mb-0">
                            <i class="<?php echo $icon_class; ?> me-2"></i>
                            Question <?php echo $question_number++; ?>
                        </h5>
                    </div>
                    <div class="question-body">
                        <p class="h4 mb-3"><?php echo htmlspecialchars($review['question_text']); ?></p>
                        
                        <hr>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <p class="fw-bold mb-1">Your Answer:</p>
                                <p class="p-2 border rounded bg-white <?php echo $is_correct ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo $user_text; ?>
                                </p>
                            </div>
                            
                            <div class="col-md-6">
                                <p class="fw-bold mb-1">Correct Answer:</p>
                                <p class="p-2 border rounded bg-white text-success">
                                    <?php echo $correct_text; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-warning text-center">
                Could not find detailed answers for this attempt.
            </div>
        <?php endif; ?>
        
        <div class="text-center my-5">
            <a href="participant_dashboard.php" class="btn btn-secondary btn-lg">
                <i class="fas fa-arrow-left"></i> Return to Dashboard
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
