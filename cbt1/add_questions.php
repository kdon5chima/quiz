<?php
// add_questions.php
session_start();
// NOTE: Assuming config.php contains the $pdo connection and helper functions (like require_login, is_admin)
require_once 'config.php';

// ----------------------------------------------------
// 1. ACCESS CONTROL - Allow Admin OR Teacher
// ----------------------------------------------------
require_login(['admin', 'teacher']);

$is_admin = is_admin(); // Store role for cleaner HTML logic

// Assuming require_login or a prior login script sets $_SESSION['user_id']
// This is critical for the authorization check below.
$current_user_id = $_SESSION['user_id'] ?? null; 

// --------------------
// 2. Initialization and Test ID Validation & AUTHORIZATION CHECK
// --------------------
$test_id = filter_input(INPUT_GET, 'test_id', FILTER_VALIDATE_INT);
$test_title = '';
$class_name = "N/A (Group Unassigned)"; // Initialize for display
$class_year = null; // Initialize for database fetching
$error_message = $_SESSION['error_message'] ?? '';
$success_message = $_SESSION['success_message'] ?? '';

// Clear session messages after retrieval
unset($_SESSION['error_message'], $_SESSION['success_message']);

// Initialize form data for fresh load or repopulation after error
$question_text_input = '';
$options_input = ['A' => '', 'B' => '', 'C' => '', 'D' => ''];
$correct_option_key_input = '';

// Check for and load session data if the user was redirected due to a form validation error (PRG pattern)
if (isset($_SESSION['form_data'])) {
    $question_text_input = $_SESSION['form_data']['question_text'] ?? '';
    $options_input['A'] = $_SESSION['form_data']['option_a'] ?? '';
    $options_input['B'] = $_SESSION['form_data']['option_b'] ?? '';
    $options_input['C'] = $_SESSION['form_data']['option_c'] ?? '';
    $options_input['D'] = $_SESSION['form_data']['option_d'] ?? '';
    $correct_option_key_input = $_SESSION['form_data']['correct_option'] ?? '';
    unset($_SESSION['form_data']);
}


// Determine redirection page based on role if an error occurs
$redirect_page_on_error = $is_admin ? "manage_tests.php" : "teacher_dashboard.php";

// Check if a test ID is provided and valid
if (!$test_id) {
    $_SESSION['error_message'] = "Invalid or missing Test ID.";
    header("location: " . $redirect_page_on_error);
    exit;
}

// Fetch Test Details, Class Information, and perform Authorization Check
try {
    // Fetch test title, associated class, and the creator's user_id
    $sql_test = "SELECT title, class_year, user_id AS test_creator_id FROM tests WHERE test_id = :test_id";
    $bindings = [':test_id' => $test_id];

    // CRITICAL AUTHORIZATION CHECK: If not an admin, restrict results to tests created by the current user
    if (!$is_admin && $current_user_id) {
        $sql_test .= " AND user_id = :user_id";
        $bindings[':user_id'] = $current_user_id;
    } elseif (!$is_admin && !$current_user_id) {
        // Fallback for missing user ID session data (should be caught by require_login, but good practice)
        $_SESSION['error_message'] = "Authentication failed. Please log in again.";
        header("location: logout.php"); 
        exit;
    }
    // END CRITICAL AUTHORIZATION CHECK

    $stmt_test = $pdo->prepare($sql_test);
    
    foreach ($bindings as $key => $value) {
        $stmt_test->bindValue($key, $value);
    }
    $stmt_test->execute();
    $test = $stmt_test->fetch(PDO::FETCH_ASSOC);

    if ($test) {
        $test_title = htmlspecialchars($test['title']);
        $class_year = $test['class_year'];

        if (!empty($class_year)) {
            $class_name = htmlspecialchars($class_year); // Use the class_year value as the class_name
        } 
        
    } else {
        // This message now covers both "Test not found" and "Test not authorized"
        $_SESSION['error_message'] = "Test not found or you are not authorized to edit it.";
        header("location: " . $redirect_page_on_error);
        exit;
    }
} catch (PDOException $e) {
    error_log("Database error fetching test details in add_questions.php: " . $e->getMessage()); // <-- This is writing the error
    $_SESSION['error_message'] = "A database error occurred while fetching test details.";
    // ...
}

// --------------------
// 3. Handle Form Submission (Add Question)
// --------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_question'])) {
    
    // --- A. Input Filtering and Validation ---
    $question_text = trim($_POST['question_text']);
    $options = [
        'A' => trim($_POST['option_a']),
        'B' => trim($_POST['option_b']),
        'C' => trim($_POST['option_c']),
        'D' => trim($_POST['option_d']),
    ];
    $correct_option_key = $_POST['correct_option'];

    // Repopulate variables with submitted values for potential error display
    $question_text_input = $question_text;
    $options_input = $options;
    $correct_option_key_input = $correct_option_key;

    $validation_error = '';
    // Validation
    if (empty($question_text) || empty($options['A']) || empty($options['B']) || empty($options['C']) || empty($options['D'])) {
        $validation_error = "All fields (question and all four options) are required.";
    } elseif (!in_array($correct_option_key, ['A', 'B', 'C', 'D'])) {
        $validation_error = "Invalid selection for the correct answer.";
    }

    if ($validation_error) {
        // Use PRG pattern for validation errors
        $_SESSION['error_message'] = $validation_error;
        $_SESSION['form_data'] = $_POST; // Store submitted data
        header("location: add_questions.php?test_id=" . $test_id . "&page=" . ($current_page ?: 1) . "#addQuestionForm");
        exit;
    } else {
        
        // --- B. Database Transaction ---
        try {
            $pdo->beginTransaction();

            // 3.1. Insert the Question
            $sql_q = "INSERT INTO questions (test_id, question_text) VALUES (:test_id, :question_text)";
            $stmt_q = $pdo->prepare($sql_q);
            $stmt_q->bindParam(':test_id', $test_id, PDO::PARAM_INT);
            $stmt_q->bindParam(':question_text', $question_text, PDO::PARAM_STR);
            $stmt_q->execute();
            $question_id = $pdo->lastInsertId();

            // 3.2. Insert the Options
            $sql_opt = "INSERT INTO options (question_id, option_key, option_text, is_correct) VALUES (:question_id, :option_key, :option_text, :is_correct)";
            $stmt_opt = $pdo->prepare($sql_opt);

            foreach ($options as $key => $text) {
                $is_correct = ($key == $correct_option_key) ? 1 : 0;
                
                // Using bindValue inside loop is safer and clearer than bindParam
                $stmt_opt->bindValue(':question_id', $question_id, PDO::PARAM_INT);
                $stmt_opt->bindValue(':option_key', $key, PDO::PARAM_STR);
                $stmt_opt->bindValue(':option_text', $text, PDO::PARAM_STR);
                $stmt_opt->bindValue(':is_correct', $is_correct, PDO::PARAM_INT);
                $stmt_opt->execute();
            }

            $pdo->commit();
            
            // Redirect to the Last Page to show new question
            $limit_per_page = 10; // Must match the limit used in Section 4 (Pagination)
            
            // 1. Re-fetch the total count of questions *after* the insert
            $sql_count_new = "SELECT COUNT(*) FROM questions WHERE test_id = :test_id";
            $stmt_count_new = $pdo->prepare($sql_count_new);
            $stmt_count_new->bindParam(':test_id', $test_id, PDO::PARAM_INT);
            $stmt_count_new->execute();
            $new_total_questions = (int)$stmt_count_new->fetchColumn();
            
            // 2. Calculate the page the new question will be on (the last page)
            $new_last_page = ceil($new_total_questions / $limit_per_page);
            
            $_SESSION['success_message'] = "Question added successfully to **{$test_title}**! Add another one.";
            
            // 3. Redirect to the last page using Post/Redirect/Get pattern
            // Anchor changed to #questionList for better UX
            header("location: add_questions.php?test_id=" . $test_id . "&page=" . $new_last_page . "#questionList");
            exit; // TERMINATE SCRIPT AFTER REDIRECT
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Database Error on Question Insert: " . $e->getMessage());
            $_SESSION['error_message'] = "Database Error: Failed to add question. Please check logs.";
            header("location: add_questions.php?test_id=" . $test_id . "&page=" . ($current_page ?: 1) . "#addQuestionForm");
            exit;
        }
    }
}


// ----------------------------------------------------
// 4. Get total questions AND list of questions for display (WITH PAGINATION)
// ----------------------------------------------------
$questions_list = [];
$total_questions = 0;
$limit_per_page = 10; // Questions per page (The target number of questions)
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$total_pages = 1;

// ***************************************************************
// *** PAGINATION LOGIC: Calculate row-based LIMIT and OFFSET ***
// ***************************************************************
$options_per_question = 4;
// This calculation is CORRECT for joined tables where each question generates 4 rows.
$row_limit = $limit_per_page * $options_per_question; 
$row_offset = 0; // Initialize

try {
    // A) Get total count of questions (used for pagination calculation)
    $sql_count = "SELECT COUNT(*) FROM questions WHERE test_id = :test_id";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->bindParam(':test_id', $test_id, PDO::PARAM_INT);
    $stmt_count->execute();
    $total_questions = (int)$stmt_count->fetchColumn(); 
    
    if ($total_questions > 0) {
        $total_pages = ceil($total_questions / $limit_per_page);

        // CRITICAL FIX: Ensure current page is within bounds and redirect if necessary
        if ($current_page > $total_pages || $current_page < 1) {
            $new_page = max(1, $total_pages); // Go to the last existing page, or 1
            // Use the session to store the error so the user sees it after redirect
            $_SESSION['error_message'] = "The page you requested is invalid. Redirected to page {$new_page}.";
            header("location: add_questions.php?test_id=" . $test_id . "&page=" . $new_page);
            exit;
        }

        // Recalculate offset AFTER page number is finalized
        $row_offset = ($current_page - 1) * $row_limit; 

        // B) Get list of questions and their options for the current page
        $sql_list = "
            SELECT 
                q.question_id, 
                q.question_text, 
                o.option_key, 
                o.option_text, 
                o.is_correct
            FROM questions q
            JOIN options o ON q.question_id = o.question_id
            WHERE q.test_id = :test_id
            ORDER BY q.question_id ASC, o.option_key ASC
            LIMIT :limit OFFSET :offset
        ";
        $stmt_list = $pdo->prepare($sql_list);
        $stmt_list->bindParam(':test_id', $test_id, PDO::PARAM_INT);
        
        // FIX: Use bindValue for LIMIT/OFFSET as it's more robust than bindParam in this context
        $stmt_list->bindValue(':limit', (int)$row_limit, PDO::PARAM_INT);
        $stmt_list->bindValue(':offset', (int)$row_offset, PDO::PARAM_INT);
        
        $stmt_list->execute();
        
        // Group options under each question
        while ($row = $stmt_list->fetch(PDO::FETCH_ASSOC)) {
            $q_id = $row['question_id'];
            
            if (!isset($questions_list[$q_id])) {
                $questions_list[$q_id] = [
                    'id' => $q_id,
                    'text' => htmlspecialchars($row['question_text']),
                    'options' => [],
                    'correct_key' => ''
                ];
            }
            
            $questions_list[$q_id]['options'][$row['option_key']] = htmlspecialchars($row['option_text']);
            
            if ($row['is_correct']) {
                $questions_list[$q_id]['correct_key'] = $row['option_key'];
            }
        }
    }
    
} catch (PDOException $e) {
    error_log("SQL ERROR FETCHING QUESTIONS: " . $e->getMessage());
    // Use session for the error message so it persists after a potential redirect
    $_SESSION['error_message'] = "SQL ERROR FETCHING QUESTIONS: Check your table/column names. Please check logs.";
    $error_message = $_SESSION['error_message']; // Update local variable for immediate display
    $total_questions = -1;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Questions to Test | <?php echo $is_admin ? 'Admin' : 'Teacher'; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style> 
        /* Basic layout styling */
        .d-flex { display: flex; }
        .sidebar { min-width: 220px; min-height: 100vh; background-color: #343a40; } 
        .sidebar a { color: #f8f9fa; padding: 10px 15px; display: block; } 
        .sidebar a:hover { background-color: #495057; text-decoration: none; } 
        /* Custom styling for content */
        .option-correct { font-weight: bold; color: #28a745; background-color: #f0fff0; padding: 2px 5px; border-radius: 4px; }
        .container-fluid { max-width: 100%; }
        .card-header.bg-info { background-color: #17a2b8 !important; }
        .card-header.bg-dark { background-color: #343a40 !important; }
        textarea#question_text { resize: vertical; }
        /* Delete button styling for modal replacement */
        .btn-delete-trigger:hover { cursor: pointer; }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
<div class="d-flex">
    <div class="sidebar text-white p-3">
        <h4 class="mb-4 text-center"><?php echo $is_admin ? 'Admin Panel' : 'Teacher Panel'; ?></h4>
        
        <?php if ($is_admin): ?>
            <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="register.php"><i class="fas fa-user-plus"></i> Register Student</a>
            <a href="view_results.php"><i class="fas fa-chart-line"></i> View Results</a>
            <a href="manage_users.php"><i class="fas fa-users-cog"></i> Manage Users</a>
        <?php else: ?>
            <a href="teacher_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <?php endif; ?>

        <a href="manage_tests.php" class="font-weight-bold"><i class="fas fa-tasks"></i> Manage Tests</a>
        <a href="<?php echo $is_admin ? 'admin_profile.php' : 'teacher_profile.php'; ?>"><i class="fas fa-user-circle"></i> My Profile</a>
        <a href="logout.php" class="btn btn-danger btn-block mt-3"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="container-fluid p-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
            <h2>Adding Questions to: "<span class="text-primary"><?php echo $test_title; ?></span>"</h2>
            <div class="d-flex align-items-center">
                <!-- NEW: Bulk Import Button -->
                <a href="import_questions.php?test_id=<?php echo $test_id; ?>" class="btn btn-success mr-3">
                    <i class="fas fa-file-import"></i> Bulk Import
                </a>
                <!-- END NEW -->
                <p class="h5 mb-0 mr-4 text-muted">
                    <i class="fas fa-layer-group text-info"></i> Target Group: <span class="badge badge-info badge-lg"><?php echo $class_name; ?></span>
                </p>
                <a href="manage_tests.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Finish & Manage Tests</a>
            </div>
        </div>
        
        <p class="lead mb-4">Total Questions Added: <span class="badge badge-primary badge-lg"><?php echo $total_questions > 0 ? $total_questions : 0; ?></span></p>

        <?php if ($success_message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?></div><?php endif; ?>

        <div class="card shadow-lg mb-5 border-info" id="addQuestionForm">
            <div class="card-header bg-info text-white">
                <h5><i class="fas fa-plus-square"></i> Add New Question</h5>
            </div>
            <div class="card-body">
                <!-- NEW: Import Call-to-action within the card -->
                <div class="alert alert-info py-2">
                    Prefer to add questions in bulk? 
                    <a href="import_questions.php?test_id=<?php echo $test_id; ?>" class="alert-link font-weight-bold">
                        Click here to use the Bulk Import Tool <i class="fas fa-upload"></i>
                    </a>
                </div>
                <!-- END NEW -->
                
                <form action="add_questions.php?test_id=<?php echo $test_id; ?>" method="POST">
                    
                    <div class="form-group">
                        <label for="question_text" class="font-weight-bold">Question Text</label>
                        <textarea name="question_text" id="question_text" class="form-control" rows="3" placeholder="Enter the text for the multiple-choice question." required><?php echo htmlspecialchars($question_text_input); ?></textarea>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3"><i class="fas fa-list-ul"></i> Options (A, B, C, D) & Correct Answer</h5>

                    <?php 
                    $option_keys = ['A', 'B', 'C', 'D'];
                    foreach ($option_keys as $key): 
                    ?>
                        <div class="form-group row align-items-center">
                            <label class="col-sm-1 col-form-label font-weight-bold text-center" for="option_<?php echo strtolower($key); ?>"><?php echo $key; ?>.</label>
                            <div class="col-sm-8">
                                <input type="text" name="option_<?php echo strtolower($key); ?>" id="option_<?php echo strtolower($key); ?>" class="form-control" placeholder="Option <?php echo $key; ?> text" value="<?php echo htmlspecialchars($options_input[$key]); ?>" required>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-check custom-control custom-radio">
                                    <input class="custom-control-input" type="radio" name="correct_option" id="correct_<?php echo $key; ?>" value="<?php echo $key; ?>" required 
                                        <?php echo ($correct_option_key_input == $key) ? 'checked' : ''; ?>>
                                    <label class="custom-control-label text-success font-weight-bold" for="correct_<?php echo $key; ?>">
                                        Correct
                                    </label>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="form-group mt-4 text-right">
                        <button type="submit" name="submit_question" class="btn btn-primary btn-lg px-5">
                            <i class="fas fa-plus-circle"></i> Add Question
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($total_questions > 0): ?>
            <div class="card shadow border-dark" id="questionList">
                <div class="card-header bg-dark text-white">
                    <h5><i class="fas fa-clipboard-list"></i> Question List (Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 5%;">#</th>
                                    <th style="width: 70%;">Question & Options</th>
                                    <th style="width: 25%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Calculate the starting question number for the current page
                                $q_number = (($current_page - 1) * $limit_per_page) + 1;
                                foreach ($questions_list as $question_id => $q_data): 
                                ?>
                                    <tr>
                                        <td><?php echo $q_number; ?></td>
                                        <td>
                                            <p class="font-weight-bold mb-2 text-dark">
                                                <?php echo $q_data['text']; ?>
                                            </p>
                                            <ul class="list-unstyled ml-3 mb-0 small">
                                                <?php foreach ($q_data['options'] as $key => $text): ?>
                                                    <li class="py-1 <?php echo ($key == $q_data['correct_key']) ? 'option-correct' : 'text-muted'; ?>">
                                                        <strong>(<?php echo $key; ?>)</strong> <?php echo $text; ?>
                                                        <?php if ($key == $q_data['correct_key']): ?>
                                                            <i class="fas fa-check text-success ml-1"></i>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </td>
                                        <td class="align-middle">
                                            <a href="edit_question.php?question_id=<?php echo $question_id; ?>&test_id=<?php echo $test_id; ?>" class="btn btn-sm btn-primary mb-2 btn-block">
                                                <i class="fas fa-edit"></i> Edit Question
                                            </a>
                                            <button 
                                                class="btn btn-sm btn-danger btn-block btn-delete-trigger" 
                                                data-toggle="modal" 
                                                data-target="#deleteConfirmationModal" 
                                                data-question-id="<?php echo $question_id; ?>" 
                                                data-question-num="<?php echo $q_number; ?>">
                                                <i class="fas fa-trash-alt"></i> Delete Question
                                            </button>
                                        </td>
                                    </tr>
                                <?php 
                                $q_number++;
                                endforeach; 
                                ?>
                            </tbody>
                        </table>
                    </div>


                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Question Pagination">
                                <ul class="pagination justify-content-center mt-3">
                                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                                        <li class="page-item <?php echo ($p == $current_page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="add_questions.php?test_id=<?php echo $test_id; ?>&page=<?php echo $p; ?>#questionList"><?php echo $p; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                        
                        <div class="mt-4 pt-3 border-top text-center">
                            <a href="#addQuestionForm" class="btn btn-info btn-lg">
                                <i class="fas fa-plus-circle"></i> Quickly Add Another Question
                            </a>
                        </div>
                        
                </div>
            </div>
        <?php elseif ($total_questions === 0): ?>
            <div class="alert alert-warning border-left-3 border-warning shadow-sm">
                <i class="fas fa-exclamation-circle"></i> No questions have been added to this test yet. Start by using the form above!
            </div>
        <?php endif; ?>
        
    </div>
</div>

<div class="modal fade" id="deleteConfirmationModal" tabindex="-1" role="dialog" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteConfirmationModalLabel"><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="question-text-placeholder">this question</strong>?</p>
                <p class="text-danger font-weight-bold">This action cannot be undone and will permanently remove the question and all its options.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <a id="confirm-delete-link" href="#" class="btn btn-danger"><i class="fas fa-trash-alt"></i> Delete Permanently</a>
            </div>
        </div>
        <input type="hidden" id="current-page-number" value="<?php echo $current_page; ?>"> 
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // JavaScript to handle the delete confirmation modal
    $('#deleteConfirmationModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget); 
        var questionId = button.data('question-id');
        var questionNum = button.data('question-num');
        var currentPage = $('#current-page-number').val(); // Get current page from PHP
        var modal = $(this);
        
        // Update the modal's text content
        modal.find('#question-text-placeholder').text('Question #' + questionNum);
        
        // Construct the full delete URL, passing the current page for correct redirect after deletion
        var deleteUrl = 'delete_question.php?question_id=' + questionId + '&test_id=<?php echo $test_id; ?>&page=' + currentPage;
        
        // Update the confirmation link's href
        modal.find('#confirm-delete-link').attr('href', deleteUrl);
    });
});
</script>
</body>
</html>
