<?php
// edit_question.php
session_start();
require_once 'config.php'; 

// Check if the user is an admin or teacher (Admin check is already in the original code, retaining it)
// NOTE: We rely on require_once 'config.php' to handle the database connection ($pdo) and potential is_admin() check if present there.
if (!isset($_SESSION["user_type"]) || $_SESSION["user_type"] !== "admin") {
    // Assuming 'admin' is the required user type for this page
    header("location: login.php");
    exit;
}

// --- Configuration and Initialization ---
$option_keys = ['A', 'B', 'C', 'D'];
$error_message = '';
$success_message = '';
$test_title = '';
$question_text = '';
$options_data = [];
$correct_option_key = '';

// Initialize options_data structure with default values
foreach ($option_keys as $key) {
    $options_data[$key] = ['id' => null, 'text' => '', 'correct' => 0];
}

// Get Question and Test IDs, prioritize POST data if submission failed, otherwise use GET
$question_id = filter_input(INPUT_POST, 'question_id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_GET, 'question_id', FILTER_VALIDATE_INT);
$test_id = filter_input(INPUT_POST, 'test_id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_GET, 'test_id', FILTER_VALIDATE_INT);

// Check for valid IDs
if (!$question_id || !$test_id) {
    $_SESSION['error_message'] = "Invalid or missing ID parameters.";
    header("location: manage_tests.php");
    exit;
}

// Handle success message passed via session (PRG pattern)
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// ------------------------------------
// Step 1: Handle Form Submission (Update)
// ------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_question'])) {
    
    // A. Input Filtering
    $new_question_text = trim($_POST['question_text']);
    $new_options = [];
    foreach ($option_keys as $key) {
        $input_name = 'option_' . strtolower($key);
        // Cast to string and trim
        $new_options[$key] = trim((string)($_POST[$input_name] ?? ''));
    }
    $new_correct_option_key = $_POST['correct_option'] ?? '';

    // Repopulate form fields with submitted data for potential error display (XSS FIX APPLIED HERE)
    $question_text = htmlspecialchars($new_question_text);
    $correct_option_key = htmlspecialchars($new_correct_option_key);
    $repopulated_options_data = [];
    foreach ($option_keys as $key) {
        $repopulated_options_data[$key]['text'] = htmlspecialchars($new_options[$key]);
        $repopulated_options_data[$key]['correct'] = ($key == $new_correct_option_key) ? 1 : 0;
    }
    // Only overwrite options_data if a form submission occurred
    $options_data = $repopulated_options_data;


    // B. Validation
    if (empty($new_question_text)) {
        $error_message = "The question text is required.";
    } elseif (count(array_filter($new_options)) !== 4) { // Correct validation
        $error_message = "All four options are required.";
    } elseif (!in_array($new_correct_option_key, $option_keys)) {
        $error_message = "Invalid selection for the correct answer.";
    } else {
        
        // C. Database Transaction
        try {
            // Fetch current option IDs (required for UPDATE)
            $sql_fetch_ids = "SELECT option_key, option_id FROM options WHERE question_id = :question_id ORDER BY option_key";
            $stmt_fetch_ids = $pdo->prepare($sql_fetch_ids);
            $stmt_fetch_ids->bindParam(':question_id', $question_id, PDO::PARAM_INT);
            $stmt_fetch_ids->execute();
            
            // Create the mapping: [option_key => option_id]
            $existing_option_ids_map = $stmt_fetch_ids->fetchAll(PDO::FETCH_KEY_PAIR);
            
            if (count($existing_option_ids_map) !== 4) {
                // If the data is corrupted and doesn't have 4 options, throw an error
                throw new Exception("Inconsistent data: Expected 4 options, found " . count($existing_option_ids_map) . ". Cannot update.");
            }

            $pdo->beginTransaction();

            // 1. Update the Question text
            $sql_q_update = "UPDATE questions SET question_text = :question_text WHERE question_id = :question_id AND test_id = :test_id";
            $stmt_q_update = $pdo->prepare($sql_q_update);
            $stmt_q_update->bindParam(':question_text', $new_question_text, PDO::PARAM_STR);
            $stmt_q_update->bindParam(':question_id', $question_id, PDO::PARAM_INT);
            $stmt_q_update->bindParam(':test_id', $test_id, PDO::PARAM_INT); 
            $stmt_q_update->execute();

            // 2. Update the Options
            $sql_opt_update = "UPDATE options SET option_text = :option_text, is_correct = :is_correct WHERE option_id = :option_id";
            $stmt_opt_update = $pdo->prepare($sql_opt_update);

            foreach ($new_options as $key => $text) {
                $is_correct = ($key == $new_correct_option_key) ? 1 : 0;
                
                // Get the ID using the correct map
                if (!isset($existing_option_ids_map[$key])) {
                    throw new Exception("Missing option ID for key: {$key}.");
                }
                $option_id = $existing_option_ids_map[$key];

                $stmt_opt_update->bindValue(':option_text', $text, PDO::PARAM_STR);
                $stmt_opt_update->bindValue(':is_correct', $is_correct, PDO::PARAM_INT);
                $stmt_opt_update->bindValue(':option_id', $option_id, PDO::PARAM_INT);
                $stmt_opt_update->execute();
            }

            $pdo->commit();
            
            // Redirect after success (PRG)
            $_SESSION['success_message'] = "Question updated successfully!";
            // *** CHANGE IS HERE: Redirect to the list page (add_questions.php) ***
            header("location: add_questions.php?test_id={$test_id}");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Log the detailed error, show a safe message to the user
            error_log("Database Error on Question Update: " . $e->getMessage());
            $error_message = "Database Error: Failed to update question. Please try again. (Details logged: " . $e->getMessage() . ")";
            
            // NOTE: Repopulation is already handled before the try block, so no need to repeat here.
        }
    }
}

// ------------------------------------
// Step 2: Fetch Current Data (For initial load OR after failed POST attempt)
// ------------------------------------

// Fetch Test Title (needed for display regardless of whether the question fetch below succeeds)
try {
    $sql_test = "SELECT title FROM tests WHERE test_id = :test_id";
    $stmt_test = $pdo->prepare($sql_test);
    $stmt_test->bindParam(':test_id', $test_id, PDO::PARAM_INT);
    $stmt_test->execute();
    $test = $stmt_test->fetch(PDO::FETCH_ASSOC);
    $test_title = $test ? htmlspecialchars($test['title']) : 'Unknown Test';
    
    // Final check to ensure test exists
    if (!$test) {
        $_SESSION['error_message'] = "Test ID {$test_id} not found.";
        header("location: manage_tests.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching test title: " . $e->getMessage());
    $error_message = "Error fetching test details.";
}


// Only fetch and overwrite data if:
// 1. It is a GET request (initial load) OR 
// 2. The POST failed with a database error (not a validation error), or it was a success which redirects and lands here as a GET.
if ($_SERVER["REQUEST_METHOD"] !== "POST" || (isset($_POST['update_question']) && empty($error_message)) || (!empty($error_message) && !isset($_POST['update_question']))) {
    
    try {
        // Fetch Question and Options
        $sql_fetch = "
            SELECT q.question_text, o.option_id, o.option_key, o.option_text, o.is_correct
            FROM questions q
            JOIN options o ON q.question_id = o.question_id
            WHERE q.question_id = :question_id AND q.test_id = :test_id
            ORDER BY FIELD(o.option_key, 'A', 'B', 'C', 'D')
        ";
        $stmt_fetch = $pdo->prepare($sql_fetch);
        $stmt_fetch->bindParam(':question_id', $question_id, PDO::PARAM_INT);
        $stmt_fetch->bindParam(':test_id', $test_id, PDO::PARAM_INT);
        $stmt_fetch->execute();
        
        $fetched_rows = $stmt_fetch->fetchAll(PDO::FETCH_ASSOC);

        // Final check to ensure question exists
        if (empty($fetched_rows)) {
            $_SESSION['error_message'] = "Question not found or does not belong to Test ID {$test_id}.";
            header("location: add_questions.php?test_id={$test_id}");
            exit;
        }

        // Process fetched data
        $question_text = htmlspecialchars($fetched_rows[0]['question_text']); 
        $options_data = []; // Reset array
        $correct_option_key = '';
        
        // Re-initialize options_data structure with fetched values
        foreach ($option_keys as $key) {
            $options_data[$key] = ['id' => null, 'text' => '', 'correct' => 0];
        }
        
        foreach ($fetched_rows as $row) {
            $key = $row['option_key'];
            if (!in_array($key, $option_keys)) continue;

            $options_data[$key]['id'] = $row['option_id'];
            $options_data[$key]['text'] = htmlspecialchars($row['option_text']); // XSS fix here
            $options_data[$key]['correct'] = $row['is_correct'];
            
            if ($row['is_correct']) {
                $correct_option_key = $key;
            }
        }
        
    } catch (PDOException $e) {
        // If an error occurred in the fetch, we override the display variables
        error_log("Error fetching question data: " . $e->getMessage());
        $error_message = "Error fetching question data. Please check database logs.";
    }
}


unset($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Question | Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style> 
        body { background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background-color: #343a40; } 
        .sidebar a { color: #f8f9fa; padding: 12px 15px; display: block; border-left: 3px solid transparent; } 
        .sidebar a:hover { background-color: #495057; text-decoration: none; border-left: 3px solid #ffc107; } 
        .sidebar .active { border-left: 3px solid #ffc107; background-color: #495057; }
        .card-header { font-weight: bold; }
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .btn-warning:hover {
            background-color: #e0a800;
            border-color: #e0a800;
        }
    </style>
</head>
<body>
<div class="d-flex">
    <div class="sidebar text-white p-3">
        <h4 class="mb-4">Admin Panel</h4>
        <a href="admin_dashboard.php">📊 Dashboard</a>
        <a href="register.php">👤 Register Student</a>
        <a href="manage_tests.php" class="active">📝 Manage Tests</a>
        <a href="view_results.php">📈 View Results</a>
        <a href="manage_users.php">🛠️ Manage Users</a>
        <a href="admin_profile.php">⚙️ My Profile</a>
        <a href="logout.php" class="btn btn-danger btn-sm mt-3 w-100">Logout</a>
    </div>

    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Edit Question (ID: <?php echo htmlspecialchars($question_id); ?>)</h2>
            <a href="add_questions.php?test_id=<?php echo htmlspecialchars($test_id); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Test Questions
            </a>
        </div>
        
        <p class="lead">Test: <strong><?php echo $test_title; ?></strong></p>

        <?php if ($success_message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

        <div class="card shadow mb-5">
            <div class="card-header bg-warning text-dark">
                Modify Question Details
            </div>
            <div class="card-body">
                <form action="edit_question.php" method="POST">
                    <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($question_id); ?>">
                    <input type="hidden" name="test_id" value="<?php echo htmlspecialchars($test_id); ?>">
                    
                    <div class="form-group">
                        <label for="question_text">Question Text</label>
                        <textarea name="question_text" id="question_text" class="form-control" rows="3" required><?php echo $question_text; ?></textarea>
                    </div>

                    <hr>
                    <h5 class="mt-4 mb-3">Multiple Choice Options (A, B, C, D)</h5>

                    <?php 
                    foreach ($option_keys as $key): 
                        // Safely access current option text/data
                        $current_option_text = $options_data[$key]['text'] ?? '';
                    ?>
                        <div class="form-group row align-items-center">
                            <label class="col-sm-1 col-form-label font-weight-bold" for="option_<?php echo strtolower($key); ?>"><?php echo htmlspecialchars($key); ?>.</label>
                            <div class="col-sm-8">
                                <input type="text" name="option_<?php echo strtolower($key); ?>" id="option_<?php echo strtolower($key); ?>" class="form-control" 
                                    value="<?php echo $current_option_text; ?>" required>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="correct_option" id="correct_<?php echo $key; ?>" value="<?php echo $key; ?>" required 
                                        <?php echo ($correct_option_key == $key) ? 'checked' : ''; ?>>
                                    <label class="form-check-label text-success font-weight-bold" for="correct_<?php echo $key; ?>">
                                        Correct Answer
                                    </label>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="form-group mt-4 text-right">
                        <button type="submit" name="update_question" class="btn btn-warning btn-lg">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
