<?php
// edit_profile.php

require_once 'config.php';

// Access Control: Only Teachers (and Admins accessing Teacher data) can use this.
require_login(['teacher', 'admin']);
$user_id = $_SESSION['user_id'];
$is_admin = is_admin();

$error_message = '';
$success_message = '';
$user_data = [];

// Fetch current user data
try {
    $sql = "SELECT full_name, email FROM users WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        $error_message = "Profile data not found.";
        // Exit if crucial data is missing
        header("location: teacher_profile.php"); 
        exit;
    }
} catch (PDOException $e) {
    $error_message = "Database error fetching profile: " . $e->getMessage();
}

// Handle POST request for updating profile
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_full_name = trim($_POST['full_name']);
    $new_email = trim($_POST['email']);

    if (empty($new_full_name) || empty($new_email)) {
        $error_message = "Full Name and Email fields are required.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } else {
        try {
            $sql = "UPDATE users SET full_name = :full_name, email = :email WHERE user_id = :user_id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':full_name', $new_full_name, PDO::PARAM_STR);
            $stmt->bindParam(':email', $new_email, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                // Update session variables immediately
                $_SESSION['full_name'] = $new_full_name;
                
                $success_message = "Profile updated successfully!";
                // Update the form data to reflect new changes
                $user_data['full_name'] = $new_full_name;
                $user_data['email'] = $new_email;
            } else {
                $error_message = "Failed to update profile. Please try again.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error during update: " . $e->getMessage();
        }
    }
}

unset($pdo);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h2>⚙️ Edit My Profile</h2>
    <hr>
    
    <div class="row">
        <div class="col-md-8">
            <?php if (!empty($error_message)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
            <?php if (!empty($success_message)): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>

            <div class="card shadow">
                <div class="card-body">
                    <form action="edit_profile.php" method="POST">
                        
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" name="full_name" id="full_name" class="form-control" required 
                                value="<?php echo htmlspecialchars($_POST['full_name'] ?? $user_data['full_name'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" name="email" id="email" class="form-control" required 
                                value="<?php echo htmlspecialchars($_POST['email'] ?? $user_data['email'] ?? ''); ?>">
                        </div>

                        <div class="form-group text-right">
                            <a href="<?php echo $is_admin ? 'admin_dashboard.php' : 'teacher_dashboard.php'; ?>" class="btn btn-info mr-2">
                                ← Back to Dashboard
                            </a>
                            <a href="teacher_profile.php" class="btn btn-secondary mr-2">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                        </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>