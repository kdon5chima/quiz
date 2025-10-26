<?php
// register_student.php - Allows a teacher to register a new student account.

// ====================================================
// CONFIG & INITIALIZATION
// ====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
} 
require_once 'config.php';
require_once 'helpers.php'; 

// Access Control: Only teachers can register students
require_login('teacher'); 
$user_id = (int)$_SESSION["user_id"];

$username = $full_name = $class_year = $password = $confirm_password = '';
$success_message = '';
$error_message = '';

// Define the standard class levels for consistency
$class_levels = [
    'JSS1', 'JSS2', 'JSS3', 
    'SSS1', 'SSS2', 'SSS3'
];

// ====================================================
// FORM SUBMISSION HANDLING
// ====================================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Sanitize and Validate Inputs
    $username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING));
    $full_name = trim(filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING));
    // CRITICAL FIX: Ensure class_year is one of the valid options
    $class_year = trim(filter_input(INPUT_POST, 'class_year', FILTER_SANITIZE_STRING)); 
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($password) || empty($full_name) || empty($class_year)) {
        $error_message = "All fields are required.";
    } elseif (!in_array($class_year, $class_levels)) { // New validation check
        $error_message = "Invalid Class/Year Level selected.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $username)) {
        $error_message = "Username must be 4-20 alphanumeric characters or underscores.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        try {
            // 2. Check if username already exists
            $sql_check = "SELECT user_id FROM users WHERE username = :username";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt_check->execute();
            
            if ($stmt_check->rowCount() > 0) {
                $error_message = "This username is already taken. Please choose another one.";
            } else {
                // 3. Insert New Student
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $user_type = 'student'; // Fixed type for this registration page

                $sql_insert = "
                    INSERT INTO users (username, password, full_name, user_type, class_year) 
                    VALUES (:username, :password, :full_name, :user_type, :class_year)
                ";
                $stmt_insert = $pdo->prepare($sql_insert);
                
                $stmt_insert->bindParam(':username', $username, PDO::PARAM_STR);
                $stmt_insert->bindParam(':password', $hashed_password, PDO::PARAM_STR);
                $stmt_insert->bindParam(':full_name', $full_name, PDO::PARAM_STR);
                $stmt_insert->bindParam(':user_type', $user_type, PDO::PARAM_STR);
                $stmt_insert->bindParam(':class_year', $class_year, PDO::PARAM_STR);
                
                if ($stmt_insert->execute()) {
                    $success_message = "Student **{$full_name}** registered successfully! Username: **{$username}**";
                    // Clear fields upon success
                    $username = $full_name = $class_year = $password = $confirm_password = '';
                } else {
                    $error_message = "Failed to register student. Please try again.";
                }
            }
        } catch (PDOException $e) {
            error_log("Student Registration Error: " . $e->getMessage());
            $error_message = "A database error occurred during registration. Please check the server logs.";
        }
    }
}

$pdo = null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register Student | Teacher Panel</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body class="bg-light">
    
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="teacher_dashboard.php"><i class="fas fa-chalkboard-teacher mr-2"></i>Teacher Panel</a>
            <a href="logout.php" class="btn btn-danger ml-auto"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container my-5">
        <div class="card shadow p-4 mx-auto" style="max-width: 600px;">
            <h3 class="card-title text-center mb-4"><i class="fas fa-user-plus mr-2"></i> Register New Student</h3>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                
                <div class="form-group">
                    <label for="full_name">Student Full Name</label>
                    <input type="text" name="full_name" class="form-control" id="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="username">Username (for login)</label>
                    <input type="text" name="username" class="form-control" id="username" value="<?php echo htmlspecialchars($username); ?>" required>
                    <small class="form-text text-muted">Must be 4-20 alphanumeric characters or underscores.</small>
                </div>

                <div class="form-group">
                    <label for="class_year">Class/Year Level</label>
                    <select name="class_year" class="form-control" id="class_year" required>
                        <option value="">Select Class/Year</option>
                        <?php foreach ($class_levels as $level): ?>
                            <option value="<?php echo htmlspecialchars($level); ?>" 
                                <?php echo ($class_year === $level) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($level); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" class="form-control" id="password" required>
                    <small class="form-text text-muted">Minimum 6 characters.</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" id="confirm_password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save mr-2"></i> Register Student</button>
            </form>
            
            <p class="text-center mt-3"><a href="teacher_dashboard.php">Go back to Dashboard</a></p>

        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>