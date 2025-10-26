<?php
// add_edit_question.php - Handles adding new questions and editing existing ones.
session_start();
require_once 'config.php';

if (!is_admin()) {
    header("location: login.php");
    exit;
}

$test_id = $_GET['test_id'] ?? null;
$question_id = $_GET['id'] ?? null;

if (empty($test_id)) {
    $_SESSION['error_message'] = "Cannot add or edit question without a Test ID.";
    header("location: manage_tests.php");
    exit;
}

$test_name = "";
$question_text = $option_a = $option_b = $option_c = $option_d = $correct_option = "";
$question_err = "";

$is_edit = $question_id !== null;
$page_title = $is_edit ? 'Edit Question' : 'Add New Question';

// 1. Fetch Test Name
try {
    $stmt_test = $pdo->prepare("SELECT test_name FROM tests WHERE test_id = :test_id");
    $stmt_test->bindParam(":test_id", $test_id, PDO::PARAM_INT);
    $stmt_test->execute();
    if ($stmt_test->rowCount() == 0) {
        $_SESSION['error_message'] = "Test not found.";
        header("location: manage_tests.php");
        exit;
    }
    $test_name = $stmt_test->fetchColumn();
} catch (PDOException $e) {
    error_log("Test Name Fetch Error: " . $e->getMessage());
}

// 2. Fetch question data for editing
if ($is_edit) {
    try {
        $sql = "SELECT question_text, option_a, option_b, option_c, option_d, correct_option FROM questions WHERE question_id = :question_id AND test_id = :test_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":question_id", $question_id, PDO::PARAM_INT);
        $stmt->bindParam(":test_id", $test_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $row = $stmt->fetch();
            $question_text = $row['question_text'];
            $option_a = $row['option_a'];
            $option_b = $row['option_b'];
            $option_c = $row['option_c'];
            $option_d = $row['option_d'];
            $correct_option = $row['correct_option'];
        } else {
            $question_err = "Question not found.";
            $is_edit = false; 
        }
    } catch (PDOException $e) {
        error_log("Question Fetch Error: " . $e->getMessage());
        $question_err = "Database error while fetching question.";
    }
}

// 3. Handle POST submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($question_err)) {
    // Collect and validate form data
    $question_text = trim($_POST["question_text"]);
    $option_a = trim($_POST["option_a"]);
    $option_b = trim($_POST["option_b"]);
    $option_c = trim($_POST["option_c"]);
    $option_d = trim($_POST["option_d"]);
    $correct_option = trim($_POST["correct_option"]);
    
    if (empty($question_text) || empty($option_a) || empty($option_b) || empty($correct_option)) {
        $question_err = "Please fill in the question text, Option A, Option B, and the Correct Option.";
    }

    if (empty($question_err)) {
        try {
            if ($is_edit) {
                // UPDATE query
                $sql = "UPDATE questions SET question_text = :text, option_a = :a, option_b = :b, option_c = :c, option_d = :d, correct_option = :correct WHERE question_id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(":id", $question_id, PDO::PARAM_INT);
            } else {
                // INSERT query
                $sql = "INSERT INTO questions (test_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (:test_id, :text, :a, :b, :c, :d, :correct)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(":test_id", $test_id, PDO::PARAM_INT);
            }

            $stmt->bindParam(":text", $question_text, PDO::PARAM_STR);
            $stmt->bindParam(":a", $option_a, PDO::PARAM_STR);
            $stmt->bindParam(":b", $option_b, PDO::PARAM_STR);
            $stmt->bindParam(":c", $option_c, PDO::PARAM_STR);
            $stmt->bindParam(":d", $option_d, PDO::PARAM_STR);
            $stmt->bindParam(":correct", $correct_option, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = $is_edit ? "Question updated successfully!" : "Question added successfully!";
                header("location: manage_questions.php?test_id=$test_id");
                exit;
            } else {
                $question_err = "Failed to save the question.";
            }

        } catch (PDOException $e) {
            error_log("Question Save Error: " . $e->getMessage());
            $question_err = "An unexpected error occurred: " . $e->getMessage();
        }
    }
}
unset($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?> | Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style> .sidebar { min-height: 100vh; background-color: #343a40; } .sidebar a { color: #f8f9fa; padding: 10px 15px; display: block; } .sidebar a:hover { background-color: #495057; text-decoration: none; }</style>
</head>
<body>
<div class="d-flex">
    <div class="sidebar text-white p-3">
        <h4 class="mb-4">Admin Panel</h4>
        <a href="admin_dashboard.php">📊 Dashboard</a>
        <a href="register.php">👤 Register Student</a>
        <a href="manage_tests.php" class="font-weight-bold">📝 Manage Tests</a>
        <a href="view_results.php">📈 View Results</a>
        <a href="manage_users.php">🛠️ Manage Users</a>
        <a href="admin_profile.php">⚙️ My Profile</a>
        <a href="logout.php" class="btn btn-danger btn-sm mt-3">Logout</a>
    </div>

    <div class="container-fluid p-4">
        <h2><?php echo $page_title; ?> for: <?php echo htmlspecialchars($test_name); ?></h2>
        <p class="text-muted">Fill in the details for the multiple-choice question.</p>
        
        <?php if (!empty($question_err)): ?><div class="alert alert-danger mt-3"><?php echo $question_err; ?></div><?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . ($is_edit ? "?test_id=$test_id&id=$question_id" : "?test_id=$test_id"); ?>" method="post">
                    
                    <div class="form-group">
                        <label>Question Text</label>
                        <textarea name="question_text" class="form-control" rows="3" required><?php echo htmlspecialchars($question_text); ?></textarea>
                    </div>
                    
                    <hr>
                    
                    <h4>Answer Options</h4>

                    <div class="form-group">
                        <label>Option A</label>
                        <input type="text" name="option_a" class="form-control" value="<?php echo htmlspecialchars($option_a); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Option B</label>
                        <input type="text" name="option_b" class="form-control" value="<?php echo htmlspecialchars($option_b); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Option C</label>
                        <input type="text" name="option_c" class="form-control" value="<?php echo htmlspecialchars($option_c); ?>">
                        <small class="form-text text-muted">Optional</small>
                    </div>

                    <div class="form-group">
                        <label>Option D</label>
                        <input type="text" name="option_d" class="form-control" value="<?php echo htmlspecialchars($option_d); ?>">
                        <small class="form-text text-muted">Optional</small>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label>Correct Answer</label>
                        <select name="correct_option" class="form-control" required>
                            <option value="">Select Correct Option</option>
                            <?php 
                                $options = ['A', 'B', 'C', 'D'];
                                foreach ($options as $opt) {
                                    $selected = ($correct_option == $opt) ? 'selected' : '';
                                    echo "<option value='{$opt}' {$selected}>{$opt}</option>";
                                }
                            ?>
                        </select>
                    </div>

                    <div class="form-group mt-4">
                        <input type="submit" class="btn btn-primary" value="Save Question">
                        <a href="manage_questions.php?test_id=<?php echo $test_id; ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>