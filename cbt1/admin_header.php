<?php 
// admin_header.php - Simple header for administrative pages
// NOTE: This assumes session_start() and require_once 'config.php'/'helpers.php' 
// have already been called in the main PHP file (e.g., admin_class_analysis.php)

$user_full_name = $_SESSION["full_name"] ?? $_SESSION["username"] ?? "Admin"; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style> 
        body { background-color: #f8f9fa; } 
        .sidebar { min-height: 100vh; background-color: #343a40; } 
        .sidebar a { color: #f8f9fa; padding: 10px 15px; display: block; border-bottom: 1px solid #495057;} 
        .sidebar a:hover { background-color: #495057; text-decoration: none; } 
    </style>
</head>
<body>
<div class="d-flex">
    <div class="sidebar text-white p-3">
        <h4 class="mb-4 text-warning">Admin Panel</h4>
        <a href="admin_dashboard.php?view=overview" class="font-weight-bold">📊 Dashboard</a>
        <a href="register.php">👤 Register User</a>
        <a href="manage_tests.php">📝 Manage Tests</a>
        <a href="admin_view_all_results.php">📈 View Results</a> 
        <a href="admin_class_analysis.php" class="bg-secondary">🏆 Class Analysis</a>
        <a href="manage_users.php">🛠️ Manage Users</a>
        <a href="admin_profile.php">⚙️ My Profile</a>
        <a href="logout.php" class="btn btn-danger btn-block mt-3">Logout</a>
    </div>

    <div class="container-fluid p-4">
        <h1><i class="fas fa-tachometer-alt"></i> Welcome Back, <?php echo htmlspecialchars($user_full_name); ?>!</h1>
        <hr>
        ```
Then, you must ensure the closing `</div></div></body></html>` tags are at the very bottom of `admin_class_analysis.php`.