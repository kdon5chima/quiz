<?php
// edit_user.php
session_start();
require_once 'config.php';

if (!is_admin()) {
    header("location: login.php");
    exit;
}

$user_id = isset($_GET['user_id']) ? filter_var($_GET['user_id'], FILTER_VALIDATE_INT) : null;
if (!$user_id) {
    header("location: manage_users.php?error=MissingID");
    exit;
}

$full_name = $username = $class_year = "";
$full_name_err = $class_year_err = $password_err = "";
$user_data = null;

// --- Fetch User Data ---
try {
    $sql_fetch = "SELECT full_name, username, class_year FROM users WHERE user_id = :user_id AND user_type = 'student'";
    $stmt_fetch = $pdo->prepare($sql_fetch);
    $stmt_fetch->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_fetch->execute();
    $user_data = $stmt_fetch->fetch();

    if (!$user_data) {
        header("location: manage_users.php?error=UserNotFound");
        exit;
    }

    $full_name = $user_data['full_name'];
    $username = $user_data['username'];
    $class_year = $user_data['class_year'];

} catch (PDOException $e) {
    die("Database error loading user data.");
}

// --- Process Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $update_fields = [];
    $update_sql_parts = [];

    if ($action === 'update_details') {
        // A. Update Details Logic
        if (empty(trim($_POST["full_name"]))) {
            $full_name_err = "Please enter the student's full name.";
        } else {
            $full_name = trim($_POST["full_name"]);
            $update_fields['full_name'] = $full_name;
            $update_sql_parts[] = "full_name = :full_name";
        }
        
        if (empty(trim($_POST["class_year"]))) {
            $class_year_err = "Please select the class year.";
        } else {
            $class_year = trim($_POST["class_year"]);
            $update_fields['class_year'] = $class_year;
            $update_sql_parts[] = "class_year = :class_year";
        }
        
        if (empty($full_name_err) && empty($class_year_err) && !empty($update_sql_parts)) {
            try {
                $sql_update = "UPDATE users SET " . implode(', ', $update_sql_parts) . " WHERE user_id = :user_id";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                
                foreach ($update_fields as $key => &$value) {
                    $stmt_update->bindParam(":$key", $value);
                }
                
                if ($stmt_update->execute()) {
                    $_SESSION['success_message'] = "User details updated successfully.";
                    header("location: manage_users.php");
                    exit;
                } else {
                    $error_message = "Error updating details.";
                }
            } catch (PDOException $e) {
                $error_message = "Database error: " . $e->getMessage();
            }
        }
        
    } elseif ($action === 'reset_password') {
        // B. Reset Password Logic
        $new_password = trim($_POST["new_password"]);
        if (empty($new_password)) {
            $password_err = "Please enter a new password.";
        } elseif (strlen($new_password) < 6) {
            $password_err = "Password must have at least 6 characters.";
        } else {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            try {
                $sql_reset = "UPDATE users SET password_hash = :password_hash WHERE user_id = :user_id";
                $stmt_reset = $pdo->prepare($sql_reset);
                $stmt_reset->bindParam(':password_hash', $password_hash, PDO::PARAM_STR);
                $stmt_reset->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                
                if ($stmt_reset->execute()) {
                    $_SESSION['success_message'] = "Password for {$username} reset successfully.";
                    header("location: manage_users.php");
                    exit;
                } else {
                    $error_message = "Error resetting password.";
                }
            } catch (PDOException $e) {
                $error_message = "Database error: " . $e->getMessage();
            }
        }
    }
}
unset($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User | Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Edit Student: <?php echo htmlspecialchars($full_name); ?> (<?php echo htmlspecialchars($username); ?>)</h2>
        <p>User ID: #<?php echo $user_id; ?></p>
        
        <?php if (isset($error_message)): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-info text-white">Update Details</div>
                    <div class="card-body">
                        <form action="edit_user.php?user_id=<?php echo $user_id; ?>" method="post">
                            <input type="hidden" name="action" value="update_details">

                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="full_name" class="form-control <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($full_name); ?>">
                                <span class="invalid-feedback"><?php echo $full_name_err; ?></span>
                            </div>

                            <div class="form-group">
                                <label>Class Year</label>
                                <select name="class_year" class="form-control <?php echo (!empty($class_year_err)) ? 'is-invalid' : ''; ?>">
                                    <option value="">-- Select Year --</option>
                                    <?php
                                        $years = ['Year 7', 'Year 8', 'Year 9', 'Year 10', 'Year 11', 'Year 12'];
                                        foreach ($years as $y) {
                                            $selected = ($class_year == $y) ? 'selected' : '';
                                            echo "<option value=\"{$y}\" {$selected}>{$y}</option>";
                                        }
                                    ?>
                                </select>
                                <span class="invalid-feedback"><?php echo $class_year_err; ?></span>
                            </div>
                            
                            <input type="submit" class="btn btn-info" value="Save Details">
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">Reset Password</div>
                    <div class="card-body">
                        <form action="edit_user.php?user_id=<?php echo $user_id; ?>" method="post">
                            <input type="hidden" name="action" value="reset_password">
                            
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                                <span class="invalid-feedback"><?php echo $password_err; ?></span>
                            </div>
                            <div class="alert alert-warning small">
                                The student's current password will be immediately overridden by this new one.
                            </div>
                            <input type="submit" class="btn btn-warning" value="Reset Password">
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <a href="manage_users.php" class="btn btn-secondary">← Back to User List</a>
        </div>
    </div>
</body>
</html>