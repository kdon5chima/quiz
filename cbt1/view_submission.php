<?php
// view_submission.php - Allows Admin/Teacher to view a student's active, unsubmitted test.

// --- CONFIGURATION & SECURITY ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php'; 
require_once 'helpers.php'; // Contains is_admin(), is_teacher() etc.

// Check if $pdo connection object is available
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Configuration Error: Database connection object (\$pdo) not initialized correctly.");
}

// --- ACCESS CONTROL ---
if (!is_admin() && !is_teacher()) {
    header("location: login.php");
    exit;
}

// -------------------------------------------------------------------------
// INPUT VALIDATION (Requires both the User ID and Test ID for a specific submission)
// -------------------------------------------------------------------------
$student_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
$test_id = filter_input(INPUT_GET, 'test_id', FILTER_VALIDATE_INT);

if (!$student_id || !$test_id) {
    // Redirect back to a management page with an error
    header("location: manage_submissions.php?error=" . urlencode("Student ID or Test ID missing."));
    exit;
}

$submission_info = null;
$questions_data = [];
$error_message = '';

try {
    // 1. Fetch Active Result ID and Test/Student Info
    // We look for a result that has started but not yet submitted (is_submitted = 0 or NULL)
    $sql_submission = "
        SELECT 
            r.result_id, r.start_time, t.title AS test_title, 
            u.full_name AS student_name, t.duration_minutes
        FROM results r
        JOIN tests t ON r.test_id = t.test_id
        JOIN users u ON r.user_id = u.user_id
        WHERE r.user_id = :student_id 
          AND r.test_id = :test_id
          AND (r.is_submitted IS NULL OR r.is_submitted = 0)
        ORDER BY r.start_time DESC
        LIMIT 1
    ";
    
    $stmt_submission = $pdo->prepare($sql_submission);
    $stmt_submission->bindParam(':student_id', $student_id, PDO::PARAM_INT);
    $stmt_submission->bindParam(':test_id', $test_id, PDO::PARAM_INT);
    $stmt_submission->execute();
    $submission_info = $stmt_submission->fetch(PDO::FETCH_ASSOC);

    if (!$submission_info) {
        $error_message = "No active, unsubmitted test found for this student/test combination.";
    } else {
        $result_id = $submission_info['result_id'];
        
        // 2. Fetch Questions, Options, and Student's Saved Answers
        // We join the three tables: questions, options, and student_answers
        $sql_qa = "
            SELECT 
                q.question_id, 
                q.question_text, 
                o.option_key, 
                o.option_text,
                sa.selected_option AS student_selection 
            FROM questions q
            JOIN options o ON q.question_id = o.question_id
            LEFT JOIN student_answers sa 
                ON q.question_id = sa.question_id 
                AND sa.result_id = :result_id
            WHERE q.test_id = :test_id
            ORDER BY q.question_id, o.option_key
        ";
        
        $stmt_qa = $pdo->prepare($sql_qa);
        $stmt_qa->bindParam(':test_id', $test_id, PDO::PARAM_INT);
        $stmt_qa->bindParam(':result_id', $result_id, PDO::PARAM_INT);
        $stmt_qa->execute();
        $raw_data = $stmt_qa->fetchAll(PDO::FETCH_ASSOC);

        // 3. Group the Data
        foreach ($raw_data as $row) {
            $q_id = $row['question_id'];

            if (!isset($questions_data[$q_id])) {
                $questions_data[$q_id] = [
                    'question_text' => htmlspecialchars($row['question_text']),
                    'student_selection' => $row['student_selection'], // This is the single student choice key
                    'options' => [],
                    'status' => ($row['student_selection'] !== null) ? 'Answered' : 'Unanswered'
                ];
            }
            // Add option text to the question
            $questions_data[$q_id]['options'][$row['option_key']] = htmlspecialchars($row['option_text']);
        }
    }

} catch (PDOException $e) {
    $error_message = "Database Error: Could not retrieve submission details. " . htmlspecialchars($e->getMessage());
    error_log("View Submission PDO Error: " . $e->getMessage());
}

unset($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Active Submission | Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .answered-bg { background-color: #f0f8ff; border-left: 5px solid #007bff; }
        .unanswered-bg { background-color: #fffaf0; border-left: 5px solid #ffc107; }
        .student-choice { font-weight: bold; color: #007bff; }
    </style>
</head>
<body>
<div class="container p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Active Submission View</h2>
        <a href="manage_submissions.php" class="btn btn-secondary">← Back to Management</a>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php elseif ($submission_info): ?>
        
        <div class="card shadow mb-4">
            <div class="card-header bg-info text-white">
                <h4 class="m-0">Monitoring Active Test</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4"><strong>Student:</strong> <?php echo htmlspecialchars($submission_info['student_name']); ?></div>
                    <div class="col-md-4"><strong>Test:</strong> <?php echo htmlspecialchars($submission_info['test_title']); ?></div>
                    <div class="col-md-4"><strong>Start Time:</strong> <?php echo date("M j, Y H:i:s", strtotime($submission_info['start_time'])); ?></div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Total Questions Viewed:</strong> <?php echo count($questions_data); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Answered So Far:</strong> 
                        <?php 
                        $answered_count = array_reduce($questions_data, function($carry, $item) {
                            return $carry + ($item['status'] === 'Answered' ? 1 : 0);
                        }, 0);
                        echo $answered_count;
                        ?>
                    </div>
                </div>
                <p class="mt-3">
                    <small class="text-muted">Status reflects answers saved to the database at the time this page was loaded.</small>
                </p>
            </div>
        </div>

        <?php $question_num = 1; ?>
        <?php foreach ($questions_data as $q_id => $q): ?>
            <?php
                $status_class = ($q['status'] === 'Answered') ? 'answered-bg' : 'unanswered-bg';
            ?>
            <div class="card mb-3 <?php echo $status_class; ?>">
                <div class="card-body">
                    <p class="font-weight-bold mb-2">
                        Q<?php echo $question_num++; ?>: <?php echo $q['question_text']; ?>
                        <span class="badge badge-<?php echo ($q['status'] === 'Answered') ? 'primary' : 'warning'; ?> float-right">
                            <?php echo $q['status']; ?>
                        </span>
                    </p>
                    <ul class="list-group list-group-flush border-top border-bottom">
                        <?php foreach ($q['options'] as $key => $text): ?>
                            <li class="list-group-item d-flex align-items-center py-1">
                                <strong><?php echo $key; ?>.</strong> 
                                <span class="mx-2"><?php echo $text; ?></span>
                                <?php if ($key === $q['student_selection']): ?>
                                    <span class="badge badge-primary ml-auto">Student's Current Choice</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>

</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>