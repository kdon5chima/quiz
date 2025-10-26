<?php
// add_edit_test.php - Handles adding new tests and editing existing ones.
session_start();
require_once 'config.php';

if (!is_admin()) {
    header("location: login.php");
    exit;
}

$test_id = $_GET['id'] ?? null;
$test_name = $duration = $class_year = "";
$is_active = 0; // Default to inactive
$test_err = "";

$is_edit = $test_id !== null;
$page_title = $is_edit ? 'Edit Test' : 'Add New Test';

// 1. Fetch data for editing
if ($is_edit) {
    try {
        $sql = "SELECT test_name, duration, class_year, is_active FROM tests WHERE test_id = :test_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":test_id", $test_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $row = $stmt->fetch();
            $test_name = $row['test_name'];
            $duration = $row['duration'];
            $class_year = $row['class_year'];
            $is_active = $row['is_active'];
        } else {
            $test_err = "Test not found.";
            $is_edit = false; // Prevent submission error
        }
    } catch (PDOException $e) {
        error_log("Test Fetch Error: " . $e->getMessage());
        $test_err = "Database error while fetching test.";
    }
}

// 2. Handle POST submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($test_err)) {
    // Collect and validate form data
    $test_name = trim($_POST["test_name"]);
    $duration = (int)trim($_POST["duration"]);
    $class_year = trim($_POST["class_year"]);
    $is_active = isset($_POST["is_active"]) ? 1 : 0;
    
    if (empty($test_name)) {
        $test_err = "Please enter a test name.";
    } elseif ($duration <= 0) {
        $test_err = "Duration must be a positive number of minutes.";
    } elseif (empty($class_year)) {
        $test_err = "Please select a class year.";
    }

    if (empty($test_err)) {
        try {
            if ($is_edit) {
                // UPDATE query
                $sql = "UPDATE tests SET test_name = :test_name, duration = :duration, class_year = :class_year, is_active = :is_active WHERE test_id = :test_id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(":test_id", $test_id, PDO::PARAM_INT);
            } else {
                // INSERT query
                $sql = "INSERT INTO tests (test_name, duration, class_year, is_active) VALUES (:test_name, :duration, :class_year, :is_active)";
                $stmt = $pdo->prepare($sql);
            }

            $stmt->bindParam(":test_name", $test_name, PDO::PARAM_STR);
            $stmt->bindParam(":duration", $duration, PDO::PARAM_INT);
            $stmt->bindParam(":class_year", $class_year, PDO::PARAM_STR);
            $stmt->bindParam(":is_active", $is_active, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = $is_edit ? "Test updated successfully!" : "Test created successfully!";
                header("location: manage_tests.php");
                exit;
            } else {
                $test_err = "Failed to save the test.";
            }

        } catch (PDOException $e) {
            error_log("Test Save Error: " . $e->getMessage());
            $test_err = "An unexpected error occurred: " . $e->getMessage();
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
        <h2><?php echo $page_title; ?></h2>
        <p class="text-muted">Use this form to <?php echo $is_edit ? 'modify' : 'create'; ?> a test.</p>
        
        <?php if (!empty($test_err)): ?><div class="alert alert-danger mt-3"><?php echo $test_err; ?></div><?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . ($is_edit ? "?id=$test_id" : ""); ?>" method="post">
                    
                    <div class="form-group">
                        <label>Test Name</label>
                        <input type="text" name="test_name" class="form-control" value="<?php echo htmlspecialchars($test_name); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Duration (Minutes)</label>
                        <input type="number" name="duration" class="form-control" value="<?php echo htmlspecialchars($duration); ?>" min="1" required>
                    </div>

                    <div class="form-group">
                        <label>Class Year</label>
                        <select name="class_year" class="form-control" required>
                            <option value="">Select Class</option>
                            <?php 
                                $classes = ['JSS1', 'JSS2', 'JSS3', 'SSS1', 'SSS2', 'SSS3'];
                                foreach ($classes as $class) {
                                    $selected = ($class_year == $class) ? 'selected' : '';
                                    echo "<option value='{$class}' {$selected}>{$class}</option>";
                                }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="is_active" value="1" <?php echo $is_active ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">Activate Test (Students can take it)</label>
                    </div>

                    <div class="form-group mt-4">
                        <input type="submit" class="btn btn-primary" value="Save Test">
                        <a href="manage_tests.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>