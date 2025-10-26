<?php
// view_result_details.php - Admin view for detailed test results.

// 1. **CRITICAL DEBUGGING STEP: Force Error Reporting**
//    Remove these lines once the project is in a production environment.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php'; 
require_once 'helpers.php'; 

// Check if $pdo connection object is available from config.php
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Configuration Error: Database connection object (\$pdo) not initialized correctly.");
}

// -------------------------------------------------------------------------
// Authorization Check
// -------------------------------------------------------------------------
if (!function_exists('is_admin') || !is_admin()) {
    header("location: login.php");
    exit;
}

// -------------------------------------------------------------------------
// Input Validation
// -------------------------------------------------------------------------
$result_id = isset($_GET['result_id']) ? filter_var($_GET['result_id'], FILTER_VALIDATE_INT) : null;

if (!$result_id) {
    // Use an internal error page or simple message for better user experience
    $error_message = "Error: Result ID Missing from the request.";
    // Setting defaults so HTML doesn't crash
    $report = [];
    $student_info = ['name' => 'N/A', 'class_id' => 'N/A'];
    $test_info = ['title' => 'N/A', 'score' => 0, 'time' => date('Y-m-d H:i:s')];
} else {
    $report = [];
    $student_info = [];
    $test_info = [];
    $error_message = '';

    try {
        // 1. Fetch Result and Student/Test Info
        // CORRECTION APPLIED HERE: Changed u.class_id to u.class_year
        $sql_info = "
            SELECT 
                r.score, 
                r.end_time, 
                t.title AS test_title, 
                u.full_name, 
                u.class_year  /* <-- CORRECTED COLUMN NAME */
            FROM results r
            JOIN tests t ON r.test_id = t.test_id
            JOIN users u ON r.user_id = u.user_id
            WHERE r.result_id = :result_id AND r.is_submitted = TRUE
        ";
        $stmt_info = $pdo->prepare($sql_info);
        $stmt_info->bindParam(':result_id', $result_id, PDO::PARAM_INT);
        $stmt_info->execute();
        $info = $stmt_info->fetch(PDO::FETCH_ASSOC); // Ensure associative array fetch

        if (!$info) {
            $error_message = "Result ID ($result_id) not found or test was not submitted.";
        } else {
            // CORRECTION APPLIED HERE: Assigned $info['class_year'] to 'class_id' key
            $student_info = ['name' => $info['full_name'], 'class_id' => $info['class_year']];
            $test_info = ['title' => $info['test_title'], 'score' => $info['score'], 'time' => $info['end_time']];
        }
        
        // 2. PHASE 2: Fetch Detailed Question/Answer Breakdown (Only if info was found)
        if (empty($error_message)) {
            $sql_questions_answers = "
                SELECT 
                    q.question_id, 
                    q.question_text, 
                    q.correct_option,
                    sa.selected_option AS student_selection_key 
                FROM results r
                JOIN questions q ON q.test_id = r.test_id
                LEFT JOIN student_answers sa ON sa.question_id = q.question_id AND sa.result_id = r.result_id
                WHERE r.result_id = :result_id
                ORDER BY q.question_id
            ";
            $stmt_qa = $pdo->prepare($sql_questions_answers);
            $stmt_qa->bindParam(':result_id', $result_id, PDO::PARAM_INT);
            $stmt_qa->execute();
            $questions_raw = $stmt_qa->fetchAll(PDO::FETCH_ASSOC);

            // Fetch ALL options
            $question_ids = array_column($questions_raw, 'question_id');
            $options_by_question = [];

            if (!empty($question_ids)) {
                // Securely handle IN clause using prepared statements
                $in_clause = implode(',', array_fill(0, count($question_ids), '?'));

                $sql_options = "
                    SELECT question_id, option_key, option_text
                    FROM options 
                    WHERE question_id IN ({$in_clause})
                    ORDER BY question_id, option_key";
                    
                $stmt_options = $pdo->prepare($sql_options);
                $stmt_options->execute($question_ids);
                $all_options = $stmt_options->fetchAll(PDO::FETCH_ASSOC);

                // Reorganize options by question_id and option_key for easy lookup
                foreach ($all_options as $opt) {
                    $options_by_question[$opt['question_id']][$opt['option_key']] = $opt['option_text'];
                }
            }
            
            // 3. PHASE 3: Process Report
            foreach ($questions_raw as $row) {
                $question_id = $row['question_id'];
                
                // Use null coalescing for safe access
                $options = $options_by_question[$question_id] ?? []; 
                $student_selection = $row['student_selection_key']; 
                
                // If student_selection is null, treat it as unanswered (score is zero)
                $is_correct = ($student_selection !== null && $student_selection == $row['correct_option']);

                $report[] = [
                    'id' => $question_id,
                    'text' => $row['question_text'],
                    'correct_option' => $row['correct_option'],
                    // Store the key if available, otherwise 'Unanswered' string
                    'student_selection' => $student_selection ?: 'Unanswered',
                    'is_correct' => $is_correct,
                    'options' => $options
                ];
            }
        } // End if (empty($error_message))

    } catch (PDOException $e) {
        // Display detailed PDO error for debugging
        $error_message = "Database Error: " . htmlspecialchars($e->getMessage());
    }
} // End if (!$result_id) check

// Close connection
unset($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Result Details | Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style> 
        .sidebar { 
            min-height: 100vh; 
            background-color: #343a40;
            flex-shrink: 0;
            width: 250px;
        } 
        .sidebar a { 
            color: #f8f9fa; 
            padding: 10px 15px; 
            display: block; 
        } 
        .sidebar a:hover { 
            background-color: #495057; 
            text-decoration: none; 
        } 
        /* Custom Answer Highlighting */
        .correct-answer { 
            background-color: #d4edda !important; /* light green */
            font-weight: bold; 
            border-color: #c3e6cb !important; 
        } 
        .incorrect-answer { 
            background-color: #f8d7da !important; /* light red */
            border-color: #f5c6cb !important; 
        } 
        .unanswered { 
            background-color: #fff3cd !important; /* light yellow */
            border-color: #ffeeba !important; 
        } 
        .card-header.alert-warning {
            background-color: #ffc107;
            color: #343a40;
        }
        @media (max-width: 768px) {
            .d-flex {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                min-height: auto;
            }
        }
    </style>
</head>
<body>
<div class="d-flex">
    <div class="sidebar text-white p-3">
        <h4 class="mb-4">Admin Panel</h4>
        <a href="admin_dashboard.php">📊 Dashboard</a>
        <a href="register.php">👤 Register Student</a>
        <a href="manage_tests.php">📝 Manage Tests</a>
        <a href="view_result.php" class="font-weight-bold">📈 View Results</a>
        <a href="manage_users.php">🛠️ Manage Users</a>
        <a href="admin_profile.php">⚙️ My Profile</a>
        <a href="logout.php" class="btn btn-danger btn-block mt-3">Logout</a>
    </div>

    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Detailed Result Review</h2>
            <a href="view_result.php" class="btn btn-secondary">← Back to Results List</a>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message); ?></div>
        <?php else: ?>
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    Summary for Test: <strong><?= htmlspecialchars($test_info['title']); ?></strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-2 mb-md-0">
                            <strong>Student:</strong> <?= htmlspecialchars($student_info['name']); ?> (Class ID: <?= htmlspecialchars($student_info['class_id']); ?>)
                        </div>
                        <div class="col-md-4 mb-2 mb-md-0">
                            <strong>Final Score:</strong> 
                            <span class="badge badge-<?php echo ($test_info['score'] >= 50 ? 'success' : 'danger'); ?> h5 p-2">
                                <?= htmlspecialchars($test_info['score']); ?>%
                            </span>
                        </div>
                        <div class="col-md-4">
                            <strong>Submitted:</strong> <?= htmlspecialchars(date("M j, Y H:i", strtotime($test_info['time']))); ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php foreach ($report as $index => $q): ?>
                <?php
                    $q_num = $index + 1;
                    $card_class = 'alert-secondary'; // Default
                    $result_badge = '';
                    
                    // Logic to set the card color and badge based on the result
                    if ($q['is_correct']) {
                        $card_class = 'alert-success';
                        $result_badge = '<span class="badge badge-success h5">CORRECT</span>';
                    } elseif ($q['student_selection'] == 'Unanswered') {
                        $card_class = 'alert-warning';
                        $result_badge = '<span class="badge badge-warning h5">UNANSWERED</span>';
                    } else {
                        $card_class = 'alert-danger';
                        $result_badge = '<span class="badge badge-danger h5">INCORRECT</span>';
                    }
                ?>
                <div class="card shadow mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center <?= $card_class; ?>">
                        <h5 class="m-0">Question <?= $q_num; ?></h5>
                        <div class="text-right">
                            <?= $result_badge; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="card-text h5 mb-3"><strong>Question:</strong> <?= htmlspecialchars($q['text']); ?></p>
                        
                        <ul class="list-group">
                            <?php 
                            // Iterate over options collected from the 'options' table
                            foreach ($q['options'] as $label => $option_text): 
                                $list_class = '';
                                
                                // 1. Highlight the CORRECT answer (Green)
                                if ($label == $q['correct_option']) {
                                    $list_class = 'correct-answer'; 
                                } 
                                
                                // 2. Highlight the INCORRECT choice (Red), overriding Green only if selected AND wrong
                                if ($label == $q['student_selection'] && !$q['is_correct']) {
                                    $list_class = 'incorrect-answer'; 
                                }
                            ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center <?= $list_class; ?>">
                                    <strong><?= htmlspecialchars($label); ?>.</strong> 
                                    <span class="flex-grow-1 mx-2"><?= htmlspecialchars($option_text); ?></span>
                                    <?php 
                                        if ($label == $q['student_selection'] && $q['student_selection'] != 'Unanswered'): 
                                            $badge_class = $q['is_correct'] ? 'badge-success' : 'badge-danger';
                                    ?>
                                        <span class="badge <?= $badge_class; ?>">Student's Choice</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                            
                            <?php if ($q['student_selection'] == 'Unanswered'): ?>
                                <li class="list-group-item unanswered">
                                    **Unanswered:** Student did not attempt this question. (Correct was **<?= htmlspecialchars($q['correct_option']); ?>**)
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>