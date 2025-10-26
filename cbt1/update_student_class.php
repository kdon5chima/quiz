<?php
// update_student_class.php - Handles changing a student's class year

session_start();
require_once 'config.php';
require_once 'helpers.php'; // Assuming is_admin() and require_login() are here

// Ensure only Admins and Teachers can access this page
require_login(['admin', 'teacher']);
$is_admin = is_admin();
$error_message = '';
$success_message = '';
$student_id = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
$student_info = null;

if (!$student_id) {
    // If student ID is missing from GET, try POST
    $student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
}

try {
    // Fetch student's current info
    if ($student_id) {
        $sql_fetch = "SELECT user_id, full_name, user_role, class_year FROM users WHERE user_id = :student_id AND user_role = 'student'";
        $stmt_fetch = $pdo->prepare($sql_fetch);
        $stmt_fetch->bindParam(':student_id', $student_id, PDO::PARAM_INT);
        $stmt_fetch->execute();
        $student_info = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

        if (!$student_info) {
            $error_message = "Student not found or incorrect role.";
            $student_id = null;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $student_info) {
        $new_year = filter_input(INPUT_POST, 'new_class_year', FILTER_SANITIZE_STRING);

        if (empty($new_year)) {
            $error_message = "New class year cannot be empty.";
        } else {
            // Update the student's class_year
            $sql_update = "UPDATE users SET class_year = :new_year WHERE user_id = :student_id";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->bindParam(':new_year', $new_year, PDO::PARAM_STR);
            $stmt_update->bindParam(':student_id', $student_id, PDO::PARAM_INT);
            $stmt_update->execute();

            $student_info['class_year'] = $new_year; // Update displayed info
            $success_message = "Class year for " . htmlspecialchars($student_info['full_name']) . " successfully updated to: " . htmlspecialchars($new_year);
        }
    }
} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
    error_log("Update student class year DB Error: " . $e->getMessage());
}

// NOTE: You would typically fetch a list of possible classes from a settings table here
$class_years = ['Year 1', 'Year 2', 'Year 3', 'Year 4', 'Year 5', 'Year 6', 'JSS 1', 'JSS 2', 'JSS 3', 'SS 1', 'SS 2', 'SS 3']; 
unset($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Student Class</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h2>Student Class Progression</h2>
    <a href="manage_users.php" class="btn btn-secondary mb-3">← Back to User Management</a>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <?php if ($student_info): ?>
        <div class="card p-4 shadow">
            <h4>Update Class Year for: **<?php echo htmlspecialchars($student_info['full_name']); ?>**</h4>
            <p>Current Class: <span class="badge badge-primary"><?php echo htmlspecialchars($student_info['class_year']); ?></span></p>

            <form method="POST">
                <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                
                <div class="form-group">
                    <label for="new_class_year">Select New Class Year:</label>
                    <select name="new_class_year" id="new_class_year" class="form-control" required>
                        <?php foreach ($class_years as $year): ?>
                            <option value="<?php echo htmlspecialchars($year); ?>" 
                                <?php if ($year === $student_info['class_year']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($year); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-success mt-3">Update Class Year</button>
            </form>
        </div>
    <?php elseif (!$student_id): ?>
        <div class="alert alert-info">Please navigate from the user management page to select a student to update.</div>
    <?php endif; ?>
</div>
</body>
</html>