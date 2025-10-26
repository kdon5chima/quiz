<?php
// teacher_profile.php
require_once 'config.php';

// Access Control: Only Teachers (and typically Admins) can access this page.
require_login(['teacher', 'admin']);
$user_id = $_SESSION['user_id'];
$is_admin = is_admin();

$user_data = [];
$error_message = '';

try {
    // Corrected SQL: Use 'user_type' from DB and alias it as 'role'.
    $sql = "SELECT full_name, username, email, user_type AS role FROM users WHERE user_id = :user_id";
    //                                         ^^^^^^^^^^^^^ This is the critical change
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        $error_message = "User data could not be found.";
    }

} catch (PDOException $e) {
    // This block should now rarely be hit
    $error_message = "Database error: Could not load profile data.";
    error_log("Teacher Profile DB Error: " . $e->getMessage());
}

unset($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile | Teacher</title>
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
        <h4 class="mb-4"><?php echo $is_admin ? 'Admin Panel' : 'Teacher Panel'; ?></h4>
        
        <?php if ($is_admin): ?>
            <a href="admin_dashboard.php">📊 Dashboard</a>
            <a href="register.php">👤 Register Student</a>
            <a href="manage_tests.php">📝 Manage Tests</a>
            <a href="grade_submissions.php">✅ Grade Submissions</a>
            <a href="view_reports.php">📊 View Reports</a>
            <a href="view_all_results.php">📈 View All Results</a>
            <a href="manage_users.php">🛠️ Manage Users</a>
            <a href="admin_profile.php">⚙️ My Profile</a>
        <?php else: ?>
            <a href="teacher_dashboard.php">📊 Dashboard</a>
            <a href="manage_tests.php">📝 Manage Tests</a>
            <a href="grade_submissions.php">✅ Grade Submissions</a>
            <a href="view_reports.php">📊 View Reports</a>
            <a href="view_all_results.php">📈 Results Center</a>
            <a href="teacher_profile.php" class="font-weight-bold">⚙️ My Profile</a>
        <?php endif; ?>
        
        <a href="logout.php" class="btn btn-danger btn-sm mt-3">Logout</a>
    </div>

    <div class="container-fluid p-4">
        <h2>👤 My Profile Information</h2>

        <?php if (!empty($error_message)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

        <?php if (!empty($user_data)): ?>
            <div class="card shadow" style="max-width: 600px;">
                <div class="card-header bg-primary text-white">
                    User Details (Role: <?php echo ucfirst($user_data['role']); ?>)
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><strong>Full Name:</strong> <?php echo htmlspecialchars($user_data['full_name']); ?></li>
                        <li class="list-group-item"><strong>Username:</strong> <?php echo htmlspecialchars($user_data['username']); ?></li>
                        <li class="list-group-item"><strong>Email:</strong> <?php echo htmlspecialchars($user_data['email']); ?></li>
                    </ul>
                    
                    <div class="mt-4 text-right">
                        <a href="edit_profile.php" class="btn btn-warning">Edit Profile</a>
                        <a href="change_password.php" class="btn btn-secondary">Change Password</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>