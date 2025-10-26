<?php
// view_result.php - Displays the student's score, answers, and the correct answers for a completed test.

session_start();
require_once 'config.php';
require_once 'helpers.php'; 

// --- AUTHENTICATION CHECK ---
if (!isset($_SESSION["user_id"])) {
    header("location: login.php");
    exit;
}
$user_id = $_SESSION["user_id"];
$user_type = $_SESSION["user_type"] ?? '';

// Input validation
$result_id = filter_input(INPUT_GET, 'result_id', FILTER_VALIDATE_INT);

if (!$result_id) {
    header("location: student_dashboard.php?error=" . urlencode("Result ID missing."));
    exit;
}

$result_info = null;
$error_message = '';
$questions_with_results = [];
$secure_mapping = [];

// --- INITIALIZE SUMMARY VARIABLES FROM DATABASE COLUMNS ---
$total_questions = 0;
$total_correct = 0;
$total_wrong = 0;
$total_unanswered = 0;

try {
    // 1. Fetch Result and Test Information
    $sql_result = "
        SELECT 
            r.result_id, r.user_id, r.test_id, r.score, r.is_submitted, r.submission_time, r.secure_answer_map,
            r.total_questions, r.correct_answers, r.wrong_answers, r.unanswered, 
            t.title, t.duration_minutes
        FROM results r
        JOIN tests t ON r.test_id = t.test_id
        WHERE r.result_id = :result_id
    ";
    
    // Check access rights
    if ($user_type === 'student') {
        $sql_result .= " AND r.user_id = :user_id";
    }

    $stmt_result = $pdo->prepare($sql_result);
    $stmt_result->bindParam(':result_id', $result_id, PDO::PARAM_INT);
    if ($user_type === 'student') {
        $stmt_result->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    }
    $stmt_result->execute();
    $result_info = $stmt_result->fetch(PDO::FETCH_ASSOC);

    if (!$result_info) {
        $error_message = "Result not found or access denied.";
    } elseif (!(int)$result_info['is_submitted']) {
        $error_message = "This test has not yet been submitted or scored.";
    } else {
        $test_id = $result_info['test_id'];
        
        // --- POPULATE SUMMARY VARIABLES FROM DATABASE ---
        $total_questions = (int)($result_info['total_questions'] ?? 0);
        $total_correct = (int)($result_info['correct_answers'] ?? 0);
        $total_wrong = (int)($result_info['wrong_answers'] ?? 0);
        $total_unanswered = (int)($result_info['unanswered'] ?? 0);
        
        // Correctly process the secure_answer_map
        $secure_mapping_raw = $result_info['secure_answer_map'] ? json_decode($result_info['secure_answer_map'], true) : [];
        $secure_mapping = $secure_mapping_raw;
        
        // 2. Fetch All Questions for the Test, Student Answers, and Correct Answers
        $sql_data = "
            SELECT 
                q.question_id, 
                q.question_text, 
                o_correct.option_key AS correct_original_key,
                sa.selected_option AS student_selected_hash, 
                o.option_key,
                o.option_text
            FROM questions q
            LEFT JOIN student_answers sa ON q.question_id = sa.question_id AND sa.result_id = :result_id
            LEFT JOIN options o_correct ON q.question_id = o_correct.question_id AND o_correct.is_correct = 1
            JOIN options o ON q.question_id = o.question_id
            WHERE q.test_id = :test_id
            ORDER BY q.question_id ASC, o.option_key ASC 
        ";
        
        $stmt_data = $pdo->prepare($sql_data);
        $stmt_data->bindParam(':test_id', $test_id, PDO::PARAM_INT);
        $stmt_data->bindParam(':result_id', $result_id, PDO::PARAM_INT);
        $stmt_data->execute();
        $raw_data = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

        // 3. Process Raw Data into a Structured Array
        $current_q_id = null;
        $q_index = -1;

        foreach ($raw_data as $row) {
            $q_id = $row['question_id'];

            if ($q_id !== $current_q_id) {
                $current_q_id = $q_id;
                $q_index++;
                
                $questions_with_results[$q_index] = [
                    'question_id'           => $q_id,
                    'student_selected_hash' => is_string($row['student_selected_hash']) && $row['student_selected_hash'] !== '' ? $row['student_selected_hash'] : null,
                    'question_text'         => $row['question_text'],
                    'options'               => [],
                    'correct_key'           => $row['correct_original_key'], 
                    'student_key'           => null, 
                    'is_scored_correct'     => false,
                    'status'                => 'unanswered', 
                ];
            }

            // Store option details
            $questions_with_results[$q_index]['options'][$row['option_key']] = [
                'text' => $row['option_text'],
                'is_correct' => ($row['option_key'] === $row['correct_original_key'])
            ];
        }
        
        // 4. Decode Answers and Finalize Scoring Flags
        foreach ($questions_with_results as $index => &$q_data) {
            $q_id = $q_data['question_id'];
            $student_hash = (string)($q_data['student_selected_hash'] ?? '');
            
            $hash_to_key_map = $secure_mapping[$q_id] ?? []; 

            $student_key = null; 

            // Decode the hash to the original option key directly
            if ($student_hash !== '' && !empty($hash_to_key_map)) {
                $student_key = $hash_to_key_map[$student_hash] ?? null;
            }
            
            // Set the student key for display
            $q_data['student_key'] = $student_key;
            
            // Set the final flags for display and determine status
            $correct_original_key = $q_data['correct_key'];

            $is_correct = ($student_key !== null && $student_key === $correct_original_key);
            $q_data['is_scored_correct'] = $is_correct;
            
            // Set the status based on the logic
            if ($student_key === null) {
                $q_data['status'] = 'unanswered';
            } elseif ($is_correct) {
                $q_data['status'] = 'correct';
            } else {
                $q_data['status'] = 'incorrect';
            }
        }
        // Remove reference to avoid side effects
        unset($q_data);
    }

} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
} catch (Exception $e) {
    $error_message = "Application Error: " . $e->getMessage();
}

unset($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Test Results - <?php echo htmlspecialchars($result_info['title'] ?? 'Test'); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { background-color: #f4f7f6; }
        .result-header { background-color: #1a73e8; color: white; }
        .question-card { margin-bottom: 25px; border-left: 5px solid; }
        .correct { border-left-color: #28a745; }
        .incorrect { border-left-color: #dc3545; }
        .unanswered { border-left-color: #ffc107; }
        
        .option-item { 
            padding: 15px; 
            margin-bottom: 10px;
            border-radius: 5px;
            font-size: 1.1rem;
        }
        .option-correct-flag { background-color: #d4edda; border: 1px solid #c3e6cb; font-weight: bold; }
        .option-student-incorrect { background-color: #f8d7da; border: 1px solid #f5c6cb; }
        .option-student-correct { background-color: #a3e8b0; border: 1px solid #73b37d; font-weight: bold; }
        .option-label { margin-right: 15px; font-weight: bold; width: 25px; display: inline-block; }
        
        /* Custom style to increase the font size of the summary badges */
        .summary-badge {
            font-size: 1.1rem !important;
            padding: 10px 15px !important;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php return; endif; ?>

    <div class="card shadow-lg mb-4 result-header">
        <div class="card-body">
            <h1 class="card-title text-center"><?php echo htmlspecialchars($result_info['title']); ?> Results</h1>
            <p class="text-center lead">
                Submitted on: <?php echo date('F j, Y, g:i a', strtotime($result_info['submission_time'])); ?>
            </p>
            <div class="row text-center mt-3">
                <div class="col">
                    <h2 class="display-4 font-weight-bold">
                        <?php echo $total_correct; ?> / <?php echo $total_questions; ?>
                    </h2>
                    <p class="lead">Correct Answers</p>
                </div>
                <div class="col border-left">
                    <?php 
                        $percentage = ($total_questions > 0) ? round(($total_correct / $total_questions) * 100, 1) : 0; 
                        $status_class = ($percentage >= 70) ? 'text-success' : (($percentage >= 50) ? 'text-warning' : 'text-danger');
                    ?>
                    <h2 class="display-4 font-weight-bold <?php echo $status_class; ?>">
                        <?php echo $percentage; ?>%
                    </h2>
                    <p class="lead">Final Score</p>
                </div>
            </div>
            
            <div class="row text-center mt-3">
                <div class="col-4">
                    <span class="badge badge-success summary-badge"><?php echo $total_correct; ?> Correct</span>
                </div>
                <div class="col-4">
                    <span class="badge badge-danger summary-badge"><?php echo $total_wrong; ?> Wrong</span>
                </div>
                <div class="col-4">
                    <span class="badge badge-warning summary-badge"><?php echo $total_unanswered; ?> Unanswered</span>
                </div>
            </div>
        </div>
    </div>
    
    <h2 class="mb-4">Answer Breakdown</h2>

    <?php 
    foreach ($questions_with_results as $q_index => $q_data): 
        $q_num = $q_index + 1;
        
        $status = $q_data['status'];
        $icon = match($status) {
            'correct' => '✅',
            'incorrect' => '❌',
            default => '', 
        };
    ?>
    <div class="card shadow question-card <?php echo $status; ?>">
        <div class="card-header h4 bg-white d-flex justify-content-between align-items-center">
            <span>Question <?php echo $q_num; ?>: <?php echo htmlspecialchars($q_data['question_text']); ?></span>
            
            <span class="badge badge-pill badge-<?php echo ($status == 'correct' ? 'success' : ($status == 'incorrect' ? 'danger' : 'warning')); ?>">
                <?php echo $icon; ?>
            </span>
        </div>
        <div class="card-body">
            <p class="font-weight-bold mb-3">Options:</p>
            <div class="list-group">
                <?php 
                foreach ($q_data['options'] as $option_key => $option):
                    
                    $class = '';
                    $label_text = '';
                    
                    // Check if the current option is the one the student selected
                    $is_student_selected = ($option_key === $q_data['student_key']);
                    
                    // 1. This is the correct option
                    if ($option_key === $q_data['correct_key']) {
                        
                        // If student chose it (meaning the answer is correct)
                        if ($is_student_selected) {
                            $class = 'option-student-correct';
                            $label_text = ' (✅ Your Answer & Correct)';
                        } else {
                            // Correct answer, student missed it/got it wrong
                            $class = 'option-correct-flag';
                            $label_text = ' (Correct Answer)';
                        }
                    } 
                    // 2. Student chose this option AND it was wrong
                    elseif ($is_student_selected) {
                        $class = 'option-student-incorrect';
                        // *** THIS IS THE CRITICAL LINE FOR WRONG ANSWERS ***
                        $label_text = ' (❌ Your Answer)'; 
                    }
                ?>
                <div class="option-item <?php echo $class; ?>">
                    <span class="option-label"><?php echo $option_key; ?>.</span>
                    <?php echo htmlspecialchars($option['text']) . $label_text; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="text-center my-5">
        <a href="student_dashboard.php" class="btn btn-primary btn-lg">Return to Dashboard</a>
    </div>

</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>
</html>