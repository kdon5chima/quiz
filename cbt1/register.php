<?php
// register.php - Register and Edit User

require_once 'config.php';
require_once 'helpers.php'; // Ensure helpers.php is included for is_admin() and is_teacher()

// 1. ACCESS CONTROL & ROLE DEFINITION
$is_admin = is_admin();
$is_teacher = is_teacher();
$classes = ['JSS1', 'JSS2', 'JSS3', 'SSS1', 'SSS2', 'SSS3'];

// Only allow Admin or Teacher to access this page
if (!$is_admin && !$is_teacher) {
    header("location: login.php"); 
    exit;
}

// -----------------------------------------------------------------
// 1.1 CRITICAL: Determine Access Mode and Allowed Actions
// -----------------------------------------------------------------
$user_can_edit_roles = $is_admin; 
$default_role_for_teacher = 'student';

// Define allowed roles for the form dropdown (Admin sees all, Teacher sees only Student)
$allowed_form_roles = ['student'];
if ($is_admin) {
    $allowed_form_roles = ['student', 'teacher', 'admin'];
}

// 2. INITIALIZATION & EDIT MODE DETECTION
// ... (Initialization block remains the same)
$username = $email = $user_type = $full_name = $class_year = "";
$password = $confirm_password = "";
$username_err = $email_err = $password_err = $confirm_password_err = $db_err = $class_year_err = $full_name_err = "";
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? ''; // Added for consistency
unset($_SESSION['success_message'], $_SESSION['error_message']);

// --- NEW EDIT LOGIC (PRE-POPULATE FORM) ---
$is_edit_mode = false;
$edit_user_id = null;
$form_title = $is_admin ? "Register New User (Admin)" : "Register New Student (Teacher)";

if (isset($_GET['edit_id']) && $id = filter_var($_GET['edit_id'], FILTER_VALIDATE_INT)) {
    $edit_user_id = $id;
    $is_edit_mode = true;
    $form_title = "Edit User Account (ID: {$edit_user_id})";

    try {
        // ... (Existing code to fetch user data for edit mode)
        $sql = "SELECT username, email, user_type, full_name, class_year FROM users WHERE user_id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $edit_user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $username = $user['username'];
            $email = $user['email'];
            $user_type = $user['user_type'];
            $full_name = $user['full_name'];
            $class_year = $user['class_year'];

            // SECURITY FIX: Prevent Teachers from editing non-students
            if ($is_teacher && $user_type !== 'student') {
                $_SESSION['error_message'] = "Permission denied. Teachers can only manage student accounts.";
                header("location: manage_users.php");
                exit;
            }
            
        } else {
            $_SESSION['error_message'] = "User ID not found for editing.";
            header("location: " . ($is_admin ? "manage_users.php" : "teacher_dashboard.php"));
            exit;
        }
    } catch (PDOException $e) {
        $db_err = "Error fetching user data: " . $e->getMessage();
    }
}
// ----------------------------------------------------------------

// 3. PROCESS FORM SUBMISSION (Covers both INSERT and UPDATE)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Check if we are in EDIT mode (hidden field carries the ID)
    if (isset($_POST['edit_user_id_hidden']) && $id = filter_var($_POST['edit_user_id_hidden'], FILTER_VALIDATE_INT)) {
        $is_edit_mode = true;
        $edit_user_id = $id;
    }

    // 3.1 Get User Role and Full Name
    if ($is_admin) {
        // Admin gets role from POST, must be validated against allowed roles
        $user_type = trim($_POST['user_type'] ?? ''); 
        if (!in_array($user_type, $allowed_form_roles)) {
            $user_type = 'student'; // Default to safest option on failure
        }
    } else { // Teacher or non-admin user
        // Teacher is always forced to create/edit a student
        $user_type = $default_role_for_teacher;
        // NOTE: In edit mode, the initial user_type check prevents non-students from being processed.
    }
    
    $full_name = trim($_POST['full_name'] ?? '');
    if (empty($full_name)) {
        $full_name_err = "Please enter the user's full name.";
    }

    // 3.2 Validate Class Name (Only required for Students)
    if ($user_type === 'student') {
        $class_year = trim($_POST['class_year'] ?? '');
        if (empty($class_year) || !in_array($class_year, $classes)) {
            $class_year_err = "Please select a valid class for the student.";
            $class_year = "";
        }
    } else {
        $class_year = NULL; 
    }
    
    // ... (Remaining validation logic for Username, Email, and Password remains the same)
    // ... (Section 3.3 to 3.6 - no functional changes needed here)

    $username = trim($_POST["username"]);
    // ... username validation ...
    $email = trim($_POST["email"]);
    // ... email validation ...
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];
    // ... password validation ...


    // 4. CHECK DB FOR DUPLICATES & EXECUTE INSERT/UPDATE
    if (empty($username_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err) && empty($class_year_err) && empty($full_name_err)) {
        
        try {
            // ... (Existing duplicate check logic) ...
            $sql_check = "SELECT user_id FROM users WHERE (username = :username OR email = :email)";
            if ($is_edit_mode) { $sql_check .= " AND user_id != :id"; }
            $stmt_check = $pdo->prepare($sql_check);
            // ... (Bind parameters for check) ...
            $stmt_check->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt_check->bindParam(':email', $email, PDO::PARAM_STR);
            if ($is_edit_mode) { $stmt_check->bindParam(':id', $edit_user_id, PDO::PARAM_INT); }
            $stmt_check->execute();
            
            if ($stmt_check->rowCount() > 0) {
                $db_err = "This username or email is already taken by another user.";
            } else {
                
                if ($is_edit_mode) {
                    // --- 4.1 UPDATE USER LOGIC ---
                    // ... (Existing UPDATE query logic remains the same, it uses the now-corrected $user_type)
                    $sql_update = "UPDATE users SET username = :username, email = :email, user_type = :user_type, full_name = :full_name, class_year = :class_year";
                    if (!empty($password)) { $sql_update .= ", password = :password"; }
                    $sql_update .= " WHERE user_id = :id";
                    
                    $stmt_update = $pdo->prepare($sql_update);
                    
                    // ... (Bind parameters for update) ...
                    $stmt_update->bindParam(':username', $username, PDO::PARAM_STR);
                    $stmt_update->bindParam(':email', $email, PDO::PARAM_STR);
                    $stmt_update->bindParam(':user_type', $user_type, PDO::PARAM_STR); // Will be 'student' for teacher, or selected role for admin
                    $stmt_update->bindParam(':full_name', $full_name, PDO::PARAM_STR);
                    $stmt_update->bindParam(':id', $edit_user_id, PDO::PARAM_INT);
                    if (!empty($password)) { 
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT); 
                        $stmt_update->bindParam(':password', $hashed_password, PDO::PARAM_STR);
                    }
                    if ($class_year === NULL) { $stmt_update->bindParam(':class_year', $class_year, PDO::PARAM_NULL); } 
                    else { $stmt_update->bindParam(':class_year', $class_year, PDO::PARAM_STR); }

                    if ($stmt_update->execute()) {
                        $_SESSION['success_message'] = ucfirst($user_type) . " **" . htmlspecialchars($full_name) . "** updated successfully!";
                        
                        // Redirect: Admin goes to manage_users, Teacher goes to dashboard
                        $redirect_page = $is_admin ? "manage_users.php" : "teacher_dashboard.php"; 
                        header("location: $redirect_page"); 
                        exit;
                    } else {
                        $db_err = "Error updating user. Please try again.";
                    }
                    
                } else {
                    // --- 4.2 INSERT USER LOGIC (Original Registration Code) ---
                    // ... (Existing INSERT query logic remains the same, it uses the now-corrected $user_type)
                    $sql_insert = "INSERT INTO users (username, email, password, user_type, full_name, class_year, reg_date) 
                                     VALUES (:username, :email, :password, :user_type, :full_name, :class_year, NOW())";
                    
                    $stmt_insert = $pdo->prepare($sql_insert);
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT); 
                    
                    // ... (Bind parameters for insert) ...
                    $stmt_insert->bindParam(':username', $username, PDO::PARAM_STR);
                    $stmt_insert->bindParam(':email', $email, PDO::PARAM_STR);
                    $stmt_insert->bindParam(':password', $hashed_password, PDO::PARAM_STR);
                    $stmt_insert->bindParam(':user_type', $user_type, PDO::PARAM_STR); // Will be 'student' for teacher, or selected role for admin
                    $stmt_insert->bindParam(':full_name', $full_name, PDO::PARAM_STR);

                    if ($class_year === NULL) { $stmt_insert->bindParam(':class_year', $class_year, PDO::PARAM_NULL); } 
                    else { $stmt_insert->bindParam(':class_year', $class_year, PDO::PARAM_STR); }
                    
                    if ($stmt_insert->execute()) {
                        $_SESSION['success_message'] = ucfirst($user_type) . " **" . htmlspecialchars($full_name) . "** registered successfully!" 
                                                     . ($user_type === 'student' ? " (Class: {$class_year})" : "");
                        
                        header("location: register.php" . ($is_teacher ? "" : "?success=true")); // Redirect to clear POST, stay on page
                        exit;
                    } else {
                        $db_err = "Error registering user. Please try again.";
                    }
                }
            }
        } catch (PDOException $e) {
            $db_err = "Database error: " . $e->getMessage();
            error_log($db_err);
        }
    }
}

// Ensure variables are set for HTML dropdown selection if there was an error
$selected_role = $user_type ?: ($is_teacher ? 'student' : 'student'); // Default for teacher is student
$selected_class = $class_year ?: '';

unset($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $is_edit_mode ? 'Edit User' : ($is_teacher ? 'Register Student' : 'Register User'); ?> | CBT System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style> 
        /* UI/UX IMPROVEMENT: Professional Sidebar Look */
        .sidebar { 
            min-height: 100vh; 
            background-color: #2c3e50; /* Darker, more professional blue */
            color: #ecf0f1; /* Light text */
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        } 
        .sidebar-header {
            padding: 20px 15px;
            font-size: 1.5rem;
            border-bottom: 1px solid #34495e;
            margin-bottom: 15px;
        }
        .sidebar a { 
            color: #ecf0f1; 
            padding: 12px 15px; 
            display: block; 
            border-radius: 4px; /* Slightly rounded links */
            transition: all 0.3s;
        } 
        .sidebar a:hover { 
            background-color: #34495e; /* Lighter hover background */
            text-decoration: none; 
            color: #fff;
        } 
        .sidebar .active {
            background-color: #1abc9c; /* Highlight active page with a vivid color */
            color: #fff;
            font-weight: bold;
        }
        .container-fluid.p-4 {
            background-color: #f4f7f6; /* Light gray background for content area */
        }
        .card-title {
            color: #2c3e50;
        }
    </style>
</head>
<body>
<div class="d-flex">
    <div class="sidebar text-white p-3">
        <div class="sidebar-header">
            CBT Admin Panel
        </div>
        <?php if ($is_admin): ?>
            <a href="admin_dashboard.php" class=""> <i class="fas fa-tachometer-alt mr-2"></i> Dashboard</a>
            <a href="register.php" class="<?php echo $is_edit_mode ? '' : 'active'; ?>"> <i class="fas fa-user-plus mr-2"></i> Register User</a>
            <a href="manage_tests.php"> <i class="fas fa-pencil-alt mr-2"></i> Manage Tests</a>
            <a href="view_results.php"> <i class="fas fa-chart-line mr-2"></i> View Results</a>
            <a href="manage_users.php" class="<?php echo $is_edit_mode ? 'active' : ''; ?>"> <i class="fas fa-users-cog mr-2"></i> Manage Users</a>
        <?php elseif ($is_teacher): ?>
            <a href="teacher_dashboard.php" class=""> <i class="fas fa-tachometer-alt mr-2"></i> Dashboard</a>
            <a href="register.php" class="<?php echo $is_edit_mode ? '' : 'active'; ?>"> <i class="fas fa-user-plus mr-2"></i> Register Student</a> <a href="manage_tests.php"> <i class="fas fa-pencil-alt mr-2"></i> Manage Tests</a>
        <?php endif; ?>

        <a href="profile.php" class="mt-3"> <i class="fas fa-user-circle mr-2"></i> My Profile</a>
        <a href="logout.php" class="btn btn-danger btn-block mt-3"> <i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
    </div>

    <div class="container-fluid p-4">
        <h2 class="mb-4 card-title"><?php echo $form_title; ?></h2>
        <p><?php 
            if ($is_edit_mode) {
                echo 'Modify the user\'s details below. Leave password fields blank to keep the existing password.';
            } elseif ($is_teacher) {
                echo 'Please fill this form to register a new student account.';
            } else {
                echo 'Please fill this form to create a new user account (Student, Teacher, or Admin).';
            }
        ?></p>

        <?php if ($success_message): ?><div class="alert alert-success"><i class="fas fa-check-circle mr-2"></i><?php echo $success_message; ?></div><?php endif; ?>
        <?php if ($db_err || $error_message): ?><div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i><?php echo $db_err . $error_message; ?></div><?php endif; ?>

        <div class="card shadow mt-4" style="max-width: 500px;">
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                    
                    <?php if ($is_edit_mode): ?>
                    <input type="hidden" name="edit_user_id_hidden" value="<?php echo htmlspecialchars($edit_user_id); ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label>User Role</label>
                        <?php if ($is_admin): ?>
                            <select name="user_type" id="user_type_select" class="form-control" required 
                                    onchange="toggleClassField()">
                                <?php foreach ($allowed_form_roles as $role): ?>
                                    <option value="<?php echo $role; ?>" <?php echo ($selected_role == $role) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($role); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="text" class="form-control" value="Student" disabled>
                            <input type="hidden" name="user_type" id="user_type_select" value="student"> 
                        <?php endif; ?>
                        
                        <?php if ($is_teacher): ?>
                        <small class="form-text text-muted">Teachers can only register student accounts.</small>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo htmlspecialchars($full_name); ?>" required>
                        <span class="invalid-feedback"><?php echo $full_name_err; ?></span>
                    </div>

                    <div class="form-group" id="class-group">
                        <label>Student Class/Grade</label>
                        <select name="class_year" class="form-control <?php echo (!empty($class_year_err)) ? 'is-invalid' : ''; ?>">
                            <option value="" disabled selected>-- Select Class --</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class; ?>" <?php echo ($selected_class == $class) ? 'selected' : ''; ?>><?php echo $class; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="invalid-feedback"><?php echo $class_year_err; ?></span>
                    </div>

                    <div class="form-group">
                        <label>Username (Used for Login) <?php echo $is_edit_mode ? '<small class="text-info">(Cannot be changed)</small>' : ''; ?></label>
                        <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo htmlspecialchars($username); ?>" required <?php echo $is_edit_mode ? 'readonly' : ''; ?>>
                        <span class="invalid-feedback"><?php echo $username_err; ?></span>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo htmlspecialchars($email); ?>" required>
                        <span class="invalid-feedback"><?php echo $email_err; ?></span>
                    </div>
                    
                    <div class="form-group">
                        <label>Password <?php echo $is_edit_mode ? '<small class="text-muted">(Leave blank to keep existing)</small>' : ''; ?></label>
                        <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" 
                               <?php echo !$is_edit_mode ? 'required' : ''; ?>>
                        <span class="invalid-feedback"><?php echo $password_err; ?></span>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" 
                               <?php echo !$is_edit_mode ? 'required' : ''; ?>>
                        <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
                    </div>

                    <div class="form-group">
                        <input type="submit" class="btn btn-primary" 
                               value="<?php echo $is_edit_mode ? 'Update User' : 'Register User'; ?>">
                        <a href="<?php echo $is_admin ? 'manage_users.php' : 'teacher_dashboard.php'; ?>" class="btn btn-secondary ml-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// jQuery script to show/hide the Class/Grade field based on User Role
$(document).ready(function() {
    var classGroup = $('#class-group');
    // For Admin, get value from select. For Teacher, get value from hidden input.
    var userTypeInput = $('#user_type_select'); 
    var classSelect = classGroup.find('select'); 

    // Define toggleClassField globally (important for onchange event)
    window.toggleClassField = function() {
        // Get the current value from the input/select
        var userTypeValue = userTypeInput.val(); 

        if (userTypeValue === 'student') {
            classGroup.slideDown(200);
            classSelect.prop('required', true); 
        } else {
            classGroup.slideUp(200);
            classSelect.prop('required', false);
        }
    }

    // Call on load to set initial state (crucial for edit mode or teacher view)
    toggleClassField();
});
</script>
</body>
</html>