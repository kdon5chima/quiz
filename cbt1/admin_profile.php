<?php
// admin_profile.php - Admin's personal profile and password management
session_start();
require_once 'config.php';

// Check if user is logged in and is an admin
if (!is_admin()) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$username = $full_name = "";
$username_err = $password_err = $confirm_password_err = "";
$success_message = "";

// 1. Fetch current user data for pre-filling the form
try {
    $sql = "SELECT username, full_name FROM users WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->execute();
    if ($stmt->rowCount() == 1) {
        $row = $stmt->fetch();
        $username = $row['username'];
        $full_name = $row['full_name'];
    }
} catch (PDOException $e) {
    error_log("Profile Fetch Error: " . $e->getMessage());
}

// 2. Handle POST request (Update Profile)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate and sanitize inputs
    $new_full_name = trim($_POST["full_name"]);
    $new_password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);

    // Basic Validation (You can add more)
    if (empty($new_full_name)) {
        $username_err = "Please enter your full name.";
    }

    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            $password_err = "Password must have at least 6 characters.";
        }
        if (empty($confirm_password)) {
            $confirm_password_err = "Please confirm the new password.";
        } elseif ($new_password != $confirm_password) {
            $confirm_password_err = "Passwords did not match.";
        }
    }

    // Proceed if no errors
    if (empty($username_err) && empty($password_err) && empty($confirm_password_err)) {
        try {
            // Start SQL UPDATE query
            $sql = "UPDATE users SET full_name = :full_name";
            $params = [':full_name' => $new_full_name, ':user_id' => $user_id];
            
            // If password is provided, hash it and add to query
            if (!empty($new_password)) {
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $sql .= ", password = :password";
                $params[':password'] = $password_hash;
            }
            
            $sql .= " WHERE user_id = :user_id";
            
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                // Update session variable
                $_SESSION["full_name"] = $new_full_name;
                $success_message = "Profile updated successfully!";
            } else {
                $error_message = "Error updating profile.";
            }

        } catch (PDOException $e) {
            error_log("Profile Update Error: " . $e->getMessage());
            $error_message = "An unexpected error occurred during update.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile | Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style> .sidebar { min-height: 100vh; background-color: #343a40; } .sidebar a { color: #f8f9fa; padding: 10px 15px; display: block; } .sidebar a:hover { background-color: #495057; text-decoration: none; }</style>
</head>
<body>
<div class="d-flex">
    <div class="sidebar text-white p-3">
        <h4 class="mb-4">Admin Panel</h4>
        <a href="admin_dashboard.php">📊 Dashboard</a>
        <a href="register.php">👤 Register Student</a>
        <a href="manage_tests.php">📝 Manage Tests</a>
        <a href="view_result.php">📈 View Results</a>
        <a href="manage_users.php">🛠️ Manage Users</a>
        <a href="admin_profile.php" class="font-weight-bold">⚙️ My Profile</a>
        <a href="logout.php" class="btn btn-danger btn-sm mt-3">Logout</a>
    </div>

    <div class="container-fluid p-4">
        <h2>⚙️ My Profile Settings</h2>
        <p class="text-muted">Update your account information and password.</p>

        <?php if (!empty($success_message)): ?><div class="alert alert-success mt-3"><?php echo $success_message; ?></div><?php endif; ?>
        <?php if (!empty($error_message)): ?><div class="alert alert-danger mt-3"><?php echo $error_message; ?></div><?php endif; ?>
        
        <div class="row">
            <div class="col-md-6">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($username); ?>" disabled>
                        <small class="form-text text-muted">Username cannot be changed.</small>
                    </div>

                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($full_name); ?>" required>
                        <span class="invalid-feedback"><?php echo $username_err; ?></span>
                    </div>

                    <hr>
                    
                    <h4>Change Password (Optional)</h4>
                    <p class="text-muted small">Leave fields blank to keep current password.</p>

                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                        <span class="invalid-feedback"><?php echo $password_err; ?></span>
                    </div> 
                    
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
                        <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
                    </div>

                    <div class="form-group mt-4">
                        <input type="submit" class="btn btn-primary" value="Update Profile">
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>