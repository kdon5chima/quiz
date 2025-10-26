<?php
// view_test_result.php - Displays the score and a detailed breakdown of a submitted test.

session_start();
require_once 'config.php'; // Assumes config.php handles PDO initialization ($pdo)

// --- AUTHENTICATION & INPUT VALIDATION ---
if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? '') !== "student") {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$result_id = filter_input(INPUT_GET, 'result_id', FILTER_VALIDATE_INT);
$message = filter_input(INPUT_GET, 'message', FILTER_SANITIZE_SPECIAL_CHARS);
$error = null;

if (!$result_id) {
    header("location: student_dashboard.php?error=" . urlencode("Result ID missing for viewing."));
    exit;
}

$result_data = null;
$test_questions = []; // Stores question, options, student answer, and correct answer

try {
    // 1. Fetch the Submitted Result and Test Details
    $sql_result = "
        SELECT 
            r.*, t.title, t.duration_minutes
        FROM 
            results r
        JOIN 
            tests t ON r.test_id = t.test_id
        WHERE 
            r.result_id = :result_id 
            AND r.user_id = :user_id        /* Security: Must belong to the logged-in student */
            AND r.is_submitted = 1;         /* Must be a submitted and scored test */
    ";
    
    $stmt_result = $pdo->prepare($sql_result);
    $stmt_result->bindParam(':result_id', $result_id, PDO::PARAM_INT);
    $stmt_result->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_result->execute();
    $result_data = $stmt_result->fetch(PDO::FETCH_ASSOC);

    if (!$result_data) {
        throw new Exception("Test result not found, not submitted, or access denied.");
    }

    $test_id = $result_data['test_id'];
    $student_answers = json_decode($result_data['selected_answers'], true) ?? [];
    
    // 2. Fetch all questions and their options/correct answers for review
    $sql_review = "
        SELECT 
            q.question_id, q.question_text, o.option_id, o.option_key, o.option_text, o.is_correct
        FROM 
            questions q
        JOIN 
            options o ON q.question_id = o.question_id
        WHERE 
            q.test_id = :test_id
        ORDER BY q.question_id ASC, o.option_key ASC
    ";
    
    $stmt_review = $pdo->prepare($sql_review);
    $stmt_review->bindParam(':test_id', $test_id, PDO::PARAM_INT);
    $stmt_review->execute();
    $review_raw_data = $stmt_review->fetchAll(PDO::FETCH_ASSOC);

    // 3. Structure the data for easy display
    foreach ($review_raw_data as $row) {
        $q_id = $row['question_id'];
        
        if (!isset($test_questions[$q_id])) {
            $test_questions[$q_id] = [
                'question_text' => $row['question_text'],
                'student_answer_key' => $student_answers[$q_id] ?? null,
                'correct_answer_key' => null,
                'options' => []
            ];
        }
        
        $test_questions[$q_id]['options'][] = [
            'key' => $row['option_key'],
            'text' => $row['option_text']
        ];
        
        if ($row['is_correct'] == 1) {
            $test_questions[$q_id]['correct_answer_key'] = $row['option_key'];
        }
    }

} catch (Exception $e) {
    error_log("Result review error (Result ID: {$result_id}): " . $e->getMessage());
    $error = "Could not load test review details: " . $e->getMessage();
}

$pdo = null;

// Determine total questions for display
$total_questions = count($test_questions);
$total_correct = ($result_data) ? round(($result_data['score'] / 100) * $total_questions) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Test Result: <?php echo htmlspecialchars($result_data['title'] ?? 'N/A'); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .container { max-width: 900px; margin-top: 30px; }
        .score-box { background-color: #e9ecef; padding: 20px; border-radius: 5px; margin-bottom: 30px; }
        .question-card { margin-bottom: 20px; border-left: 5px solid; padding-left: 15px; }
        .correct { border-color: #28a745; }
        .incorrect { border-color: #dc3545; }
        .option-item { padding: 8px 12px; border-radius: 5px; margin-bottom: 5px; }
        .correct-answer { background-color: #d4edda; color: #155724; font-weight: bold; }
        .student-incorrect-answer { background-color: #f8d7da; color: #721c24; font-weight: bold; }
        .student-correct-answer { background-color: #d1ecf1; color: #0c5460; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <a href="student_dashboard.php" class="btn btn-outline-secondary btn-sm mb-4"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    
    <h1 class="mb-4 text-primary"><i class="fas fa-medal"></i> Test Result Review</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($result_data): ?>
        <div class="score-box shadow-sm">
            <h3 class="text-dark"><?php echo htmlspecialchars($result_data['title']); ?></h3>
            <p class="mb-1">Submitted: <?php echo date('F j, Y, g:i a', strtotime($result_data['submission_time'] ?? $result_data['start_time'])); ?></p>
            <p class="h5 text-muted">Duration: <?php echo $result_data['duration_minutes']; ?> minutes</p>
            
            <hr>
            <div class="d-flex justify-content-between align-items-center">
                <p class="h3 mb-0 text-success">Final Score:</p>
                <span class="h1 mb-0 text-success font-weight-bold"><?php echo htmlspecialchars($result_data['score']); ?>%</span>
            </div>
            <p class="text-right text-muted"><?php echo $total_correct; ?> correct out of <?php echo $total_questions; ?> questions</p>
        </div>

        <h3 class="mt-5 mb-3"><i class="fas fa-list-ol"></i> Question Breakdown</h3>
        
        <?php foreach ($test_questions as $q_id => $q_data): 
            $student_key = $q_data['student_answer_key'];
            $correct_key = $q_data['correct_answer_key'];
            $is_correct = ($student_key !== null && $student_key === $correct_key);
            $card_class = $is_correct ? 'correct' : 'incorrect';
        ?>
            <div class="card question-card <?php echo $card_class; ?> shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">
                        Question <?php echo array_search($q_id, array_keys($test_questions)) + 1; ?>: 
                        <?php echo htmlspecialchars($q_data['question_text']); ?>
                    </h5>
                    
                    <p class="font-weight-bold mt-3 mb-1">
                        Your Answer: 
                        <?php if ($is_correct): ?>
                            <span class="badge badge-success">CORRECT <i class="fas fa-check"></i></span>
                        <?php elseif ($student_key === null): ?>
                            <span class="badge badge-secondary">NOT ANSWERED</span>
                        <?php else: ?>
                            <span class="badge badge-danger">INCORRECT <i class="fas fa-times"></i></span>
                        <?php endif; ?>
                    </p>
                    
                    <ul class="list-unstyled mt-2">
                        <?php foreach ($q_data['options'] as $option): 
                            $option_class = 'option-item border';
                            
                            // Highlight the correct answer
                            if ($option['key'] === $correct_key) {
                                $option_class = 'option-item correct-answer';
                            }
                            
                            // Highlight the student's selected answer
                            if ($option['key'] === $student_key) {
                                $option_class = $is_correct ? 'option-item student-correct-answer' : 'option-item student-incorrect-answer';
                            }
                        ?>
                            <li class="<?php echo $option_class; ?>">
                                <strong><?php echo htmlspecialchars($option['key']); ?>.</strong> 
                                <?php echo htmlspecialchars($option['text']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($test_questions)): ?>
            <p class="alert alert-warning">No questions or data found for this test.</p>
        <?php endif; ?>

    <?php endif; ?>
    
    <div class="text-center mt-5 mb-5">
        <a href="student_dashboard.php" class="btn btn-primary btn-lg"><i class="fas fa-home"></i> Return to Dashboard</a>
    </div>

</div>

</body>
</html>