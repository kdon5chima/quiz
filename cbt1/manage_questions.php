<?php
session_start();
require_once 'config.php'; 

if (!is_admin()) {
    header("location: login.php");
    exit;
}

$test_id = isset($_GET['test_id']) ? filter_var($_GET['test_id'], FILTER_VALIDATE_INT) : null;
$test_info = null;

if (!$test_id) {
    // If no test_id is provided, redirect to test management
    header("location: manage_tests.php");
    exit;
}

// Fetch Test Information
try {
    $stmt = $pdo->prepare("SELECT title FROM tests WHERE test_id = :test_id");
    $stmt->bindParam(':test_id', $test_id, PDO::PARAM_INT);
    $stmt->execute();
    $test_info = $stmt->fetch();
    if (!$test_info) {
        header("location: manage_tests.php?error=TestNotFound");
        exit;
    }
} catch (PDOException $e) {
    die("Database error fetching test info: " . $e->getMessage());
}

// Initialize error variables
$q_text = $op_a = $op_b = $op_c = $op_d = $correct_op = "";
$q_err = $a_err = $b_err = $c_err = $d_err = $corr_err = "";

// --- Process Question Addition ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_question'])) {
    
    // 1. Validation
    if (empty(trim($_POST["question_text"]))) { $q_err = "Question text is required."; } else { $q_text = trim($_POST["question_text"]); }
    if (empty(trim($_POST["option_a"]))) { $a_err = "Option A is required."; } else { $op_a = trim($_POST["option_a"]); }
    if (empty(trim($_POST["option_b"]))) { $b_err = "Option B is required."; } else { $op_b = trim($_POST["option_b"]); }
    if (empty(trim($_POST["option_c"]))) { $c_err = "Option C is required."; } else { $op_c = trim($_POST["option_c"]); }
    if (empty(trim($_POST["option_d"]))) { $d_err = "Option D is required."; } else { $op_d = trim($_POST["option_d"]); }
    if (empty(trim($_POST["correct_option"]))) { $corr_err = "Correct answer must be selected."; } else { $correct_op = trim($_POST["correct_option"]); }

    if (empty($q_err) && empty($a_err) && empty($b_err) && empty($c_err) && empty($d_err) && empty($corr_err)) {
        
        $sql = "INSERT INTO questions (test_id, question_text, option_a, option_b, option_c, option_d, correct_option) 
                VALUES (:test_id, :q_text, :op_a, :op_b, :op_c, :op_d, :correct_op)";
        
        if ($stmt = $pdo->prepare($sql)) {
            $stmt->bindParam(":test_id", $test_id, PDO::PARAM_INT);
            $stmt->bindParam(":q_text", $q_text);
            $stmt->bindParam(":op_a", $op_a);
            $stmt->bindParam(":op_b", $op_b);
            $stmt->bindParam(":op_c", $op_c);
            $stmt->bindParam(":op_d", $op_d);
            $stmt->bindParam(":correct_op", $correct_op);
            
            if ($stmt->execute()) {
                // Success: Clear form variables and reload
                $success_msg = "Question added successfully!";
                $q_text = $op_a = $op_b = $op_c = $op_d = $correct_op = "";
            } else {
                $error_msg = "Error adding question.";
            }
            unset($stmt);
        }
    }
}

// Fetch Existing Questions for display
try {
    $q_sql = "SELECT question_id, question_text, correct_option FROM questions WHERE test_id = :test_id ORDER BY question_id";
    $q_stmt = $pdo->prepare($q_sql);
    $q_stmt->bindParam(':test_id', $test_id, PDO::PARAM_INT);
    $q_stmt->execute();
    $existing_questions = $q_stmt->fetchAll();
} catch (PDOException $e) {
    $existing_questions = [];
    $error_fetch = "Could not load existing questions.";
}

unset($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Questions: <?php echo htmlspecialchars($test_info['title']); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style> .sidebar { min-height: 100vh; background-color: #343a40; } .sidebar a { color: #f8f9fa; padding: 10px 15px; display: block; } .sidebar a:hover { background-color: #495057; text-decoration: none; } </style>
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
        <h2>Questions for: **<?php echo htmlspecialchars($test_info['title']); ?>**</h2>
        <p class="text-muted">Test ID: #<?php echo $test_id; ?> | Current Questions: <?php echo count($existing_questions); ?></p>
        
        <?php if (isset($success_msg)): ?><div class="alert alert-success"><?php echo $success_msg; ?></div><?php endif; ?>
        <?php if (isset($error_msg)): ?><div class="alert alert-danger"><?php echo $error_msg; ?></div><?php endif; ?>

        <div class="row">
            <div class="col-lg-5 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">Add New Question</div>
                    <div class="card-body">
                        <form action="manage_questions.php?test_id=<?php echo $test_id; ?>" method="post">
                            <div class="form-group">
                                <label>Question Text</label>
                                <textarea name="question_text" class="form-control <?php echo (!empty($q_err)) ? 'is-invalid' : ''; ?>" rows="3"><?php echo htmlspecialchars($q_text); ?></textarea>
                                <span class="invalid-feedback"><?php echo $q_err; ?></span>
                            </div>

                            <?php 
                            $options = [
                                'option_a' => ['label' => 'A', 'value' => $op_a, 'err' => $a_err],
                                'option_b' => ['label' => 'B', 'value' => $op_b, 'err' => $b_err],
                                'option_c' => ['label' => 'C', 'value' => $op_c, 'err' => $c_err],
                                'option_d' => ['label' => 'D', 'value' => $op_d, 'err' => $d_err]
                            ];
                            foreach ($options as $name => $opt): ?>
                            <div class="form-group">
                                <label>Option <?php echo $opt['label']; ?></label>
                                <input type="text" name="<?php echo $name; ?>" class="form-control <?php echo (!empty($opt['err'])) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($opt['value']); ?>">
                                <span class="invalid-feedback"><?php echo $opt['err']; ?></span>
                            </div>
                            <?php endforeach; ?>
                            
                            <div class="form-group">
                                <label>Correct Option</label>
                                <select name="correct_option" class="form-control <?php echo (!empty($corr_err)) ? 'is-invalid' : ''; ?>">
                                    <option value="">-- Select Correct Answer --</option>
                                    <option value="A" <?php echo ($correct_op == 'A') ? 'selected' : ''; ?>>A</option>
                                    <option value="B" <?php echo ($correct_op == 'B') ? 'selected' : ''; ?>>B</option>
                                    <option value="C" <?php echo ($correct_op == 'C') ? 'selected' : ''; ?>>C</option>
                                    <option value="D" <?php echo ($correct_op == 'D') ? 'selected' : ''; ?>>D</option>
                                </select>
                                <span class="invalid-feedback"><?php echo $corr_err; ?></span>
                            </div>

                            <input type="submit" name="add_question" class="btn btn-success btn-block" value="Add Question">
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card shadow">
                    <div class="card-header bg-secondary text-white">Question List</div>
                    <div class="card-body">
                        <?php if (empty($existing_questions)): ?>
                            <div class="alert alert-info">No questions added yet.</div>
                        <?php else: ?>
                            <ul class="list-group">
                                <?php foreach ($existing_questions as $index => $q): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div class="text-truncate" style="max-width: 80%;">
                                            <small class="text-muted">Q<?php echo $index + 1; ?>:</small>
                                            <?php echo htmlspecialchars(substr($q['question_text'], 0, 80)) . (strlen($q['question_text']) > 80 ? '...' : ''); ?>
                                        </div>
                                        <span class="badge badge-primary badge-pill">
                                            Ans: <?php echo htmlspecialchars($q['correct_option']); ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <div class="mt-3 text-center">
                            <a href="manage_tests.php" class="btn btn-warning">Finished. Back to Tests</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>