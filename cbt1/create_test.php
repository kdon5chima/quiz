<?php
// create_test.php
session_start();
require_once 'config.php';

// --- GLOBAL CONFIGURATION (Add this list) ---
$ALLOWED_CLASS_YEARS = ['JSS1', 'JSS2', 'JSS3', 'SSS1', 'SSS2', 'SSS3'];
// You can also add a special value for "All Classes"
$ALL_CLASSES_VALUE = 'ALL'; 
// ---------------------------------------------

// --- Access Control and Setup ---
// NOTE: Assuming you have is_admin() and is_teacher() functions defined in config.php
if (!function_exists('is_admin')) { function is_admin() { return isset($_SESSION["user_type"]) && $_SESSION["user_type"] === "admin"; } }
if (!function_exists('is_teacher')) { function is_teacher() { return isset($_SESSION["user_type"]) && $_SESSION["user_type"] === "teacher"; } }

if (!is_admin() && !is_teacher()) { 
    $_SESSION['error_message'] = "Unauthorized access. Please log in as Admin or Teacher.";
    header("location: login.php");
    exit;
}

$creator_id = $_SESSION['user_id'] ?? null; 
if (!$creator_id) {
    header("location: logout.php"); 
    exit;
}

$error_message = '';
$class_year_input = ''; // Initialize for form display
// NEW: Initialize for form display
$max_attempts_input = htmlspecialchars($_POST['max_attempts'] ?? 1); 

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_test'])) {
    
    // 1. Input Filtering
    $title = trim($_POST['title']);
    $duration = filter_input(INPUT_POST, 'duration', FILTER_VALIDATE_INT);
    // NEW: Capture and validate max_attempts
    $max_attempts = filter_input(INPUT_POST, 'max_attempts', FILTER_VALIDATE_INT);
    
    // ⬅️ FIX: Capture the string input from the new select field
    $class_year_input = trim($_POST['class_year'] ?? '');
    
    // 2. Validation
    if (empty($title)) {
        $error_message = "Test Title is required.";
    } elseif ($duration === false || $duration <= 0) {
        $error_message = "Valid Test Duration (in minutes) is required.";
    } elseif ($max_attempts === false || $max_attempts <= 0) { // NEW VALIDATION
        $error_message = "Maximum Attempts must be a positive whole number (1 or more).";
    }
    
    // ⬅️ CRITICAL FIX: Validate class year against the allowed string array
    if (empty($class_year_input) || (!in_array($class_year_input, $ALLOWED_CLASS_YEARS) && $class_year_input !== $ALL_CLASSES_VALUE)) {
        $error_message = "Target Class Year must be selected from the list (e.g., JSS1, SSS3).";
    }

    // Update form variable to retain value on error
    $max_attempts_input = htmlspecialchars($_POST['max_attempts'] ?? 1);
    
    // 3. Prepare Value for Database
    // If the special 'ALL' value is selected, we save it as NULL or the empty string.
    $class_year_db_value = ($class_year_input === $ALL_CLASSES_VALUE) ? null : $class_year_input;
    
    // 4. Database Insertion if no errors found
    if (empty($error_message)) {
        try {
            // Updated SQL: ADDED max_attempts to the list of columns
            $sql = "
                INSERT INTO tests 
                (title, duration_minutes, class_year, created_by, is_active, max_attempts) 
                VALUES (:title, :duration, :class_year, :created_by, 0, :max_attempts)
            ";
            
            $stmt = $pdo->prepare($sql);
            
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->bindParam(':duration', $duration, PDO::PARAM_INT);
            // NEW: Bind max_attempts parameter
            $stmt->bindParam(':max_attempts', $max_attempts, PDO::PARAM_INT); 
            
            // ⬅️ CRITICAL FIX: Bind as String (PARAM_STR) or NULL, not INT
            if ($class_year_db_value === null) {
                $stmt->bindValue(':class_year', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindParam(':class_year', $class_year_db_value, PDO::PARAM_STR); 
            }
            
            $stmt->bindParam(':created_by', $creator_id, PDO::PARAM_INT); 
            
            if ($stmt->execute()) {
                $test_id = $pdo->lastInsertId();
                $_SESSION['success_message'] = "Test '{$title}' created successfully! Now add the questions.";
                
                header("location: add_questions.php?test_id={$test_id}");
                exit;
            }
        } catch (PDOException $e) {
            error_log("Test Creation Error: " . $e->getMessage());
            $error_message = "Database Error: Could not create test. Check your database schema: class_year must accept strings (VARCHAR) and max_attempts must be INT.";
        }
    }
}

$pdo = null; // Close connection
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create New Test | Admin/Teacher</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style> 
        .sidebar { min-height: 100vh; background-color: #343a40; } 
        .sidebar a { color: #f8f9fa; padding: 10px 15px; display: block; } 
        .sidebar a:hover { background-color: #495057; text-decoration: none; } 
    </style>
</head>
<body>
<div class="d-flex">
    <div class="sidebar text-white p-3">
        <h4 class="mb-4"><?php echo is_admin() ? 'Admin Panel' : 'Teacher Panel'; ?></h4>
        <?php if (is_admin()): ?>
            <a href="admin_dashboard.php">📊 Dashboard</a>
            <a href="register.php">👤 Register User</a>
            <a href="manage_tests.php" class="font-weight-bold">📝 Manage Tests</a>
            <a href="view_results.php">📈 View Results</a>
            <a href="manage_users.php">🛠️ Manage Users</a>
            <a href="admin_profile.php">⚙️ My Profile</a>
        <?php else: ?>
            <a href="teacher_dashboard.php">📊 Dashboard</a>
            <a href="manage_tests.php" class="font-weight-bold">📝 Manage Tests</a>
            <a href="grade_submissions.php">✅ Grade Submissions</a>
            <a href="view_reports.php">📈 View Reports</a>
        <?php endif; ?>
        <a href="logout.php" class="btn btn-danger btn-sm mt-3">Logout</a>
    </div>

    <div class="container-fluid p-4">
        <h2>Create New Test</h2>

        <?php if (!empty($error_message)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-body">
                <form action="create_test.php" method="POST">
                    
                    <div class="form-group">
                        <label for="title">Test Title</label>
                        <input type="text" name="title" id="title" class="form-control" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="class_year">Target Class Year</label>
                        <select class="form-control" id="class_year" name="class_year" required>
                            <option value="<?php echo $ALL_CLASSES_VALUE; ?>" 
                                <?php echo ($class_year_input === $ALL_CLASSES_VALUE) ? 'selected' : ''; ?>>
                                -- Assign to All Classes --
                            </option>
                            <option value="" disabled>--- Select a specific class ---</option>
                            <?php 
                            // Loop through the defined allowed array
                            foreach ($ALLOWED_CLASS_YEARS as $year): ?>
                                <option value="<?php echo htmlspecialchars($year); ?>" 
                                    <?php echo ($class_year_input === $year) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($year); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">This determines which students will see the test.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="duration">Duration (in minutes)</label>
                        <input type="number" name="duration" id="duration" class="form-control" required min="1" value="<?php echo htmlspecialchars($_POST['duration'] ?? ''); ?>">
                        <small class="form-text text-muted">e.g., 60 for a one-hour test.</small>
                    </div>
                    
                    <!-- NEW FIELD: Maximum Attempts Allowed -->
                    <div class="form-group">
                        <label for="max_attempts">Maximum Attempts Allowed</label>
                        <input type="number" name="max_attempts" id="max_attempts" class="form-control" required min="1" value="<?php echo $max_attempts_input; ?>">
                        <small class="form-text text-muted">Set the maximum number of times a student can submit this test (e.g., 1 for no retakes, 3 for three chances).</small>
                    </div>

                    <div class="form-group text-right">
                        <button type="submit" name="submit_test" class="btn btn-success btn-lg">
                            Create Test & Add Questions
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
