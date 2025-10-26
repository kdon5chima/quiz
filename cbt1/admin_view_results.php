<?php
// view_results.php
session_start();
require_once 'config.php';

if (!is_admin()) {
    header("location: login.php");
    exit;
}

$error_message = '';

// Fetch all submitted test results
$results = [];
try {
    $sql = "
        SELECT 
            r.result_id, 
            r.score, 
            r.end_time,
            u.full_name AS student_name, 
            u.class_year,
            t.title AS test_title
        FROM results r
        JOIN users u ON r.user_id = u.user_id
        JOIN tests t ON r.test_id = t.test_id
        WHERE r.is_submitted = TRUE
        ORDER BY r.end_time DESC
    ";
    $results = $pdo->query($sql)->fetchAll();
} catch (PDOException $e) {
    $error_message = "Could not load results: " . $e->getMessage();
}

unset($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Results | Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style> .sidebar { min-height: 100vh; background-color: #343a40; } .sidebar a { color: #f8f9fa; padding: 10px 15px; display: block; } .sidebar a:hover { background-color: #495057; text-decoration: none; } </style>
</head>
<body>
<div class="d-flex">
    <div class="sidebar text-white p-3">
    <h4 class="mb-4">Admin Panel</h4>
    <a href="admin_dashboard.php">📊 Dashboard</a>
    <a href="register.php">👤 Register Student</a>
    <a href="manage_tests.php">📝 Manage Tests</a>
    <a href="view_results.php" class="font-weight-bold">📈 View Results</a>
    <a href="manage_users.php">🛠️ Manage Users</a>
    <a href="admin_profile.php">⚙️ My Profile</a>
    <a href="logout.php" class="btn btn-danger btn-sm mt-3">Logout</a>
</div>

    <div class="container-fluid p-4">
        <h2>View Test Results</h2>
        
        <?php if ($error_message): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                All Submitted Test Results (<?php echo count($results); ?> Total)
            </div>
            <div class="card-body">
                <?php if (empty($results)): ?>
                    <div class="alert alert-info">No tests have been submitted by students yet.</div>
                <?php else: ?>
                    <table class="table table-striped table-hover table-bordered">
                        <thead class="thead-dark">
                            <tr>
                                <th>Result ID</th>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th>Test Title</th>
                                <th>Score (%)</th>
                                <th>Submitted Time</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result): ?>
                            <tr>
                                <td><?php echo $result['result_id']; ?></td>
                                <td><?php echo htmlspecialchars($result['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($result['class_year']); ?></td>
                                <td><?php echo htmlspecialchars($result['test_title']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo ($result['score'] >= 50 ? 'success' : 'danger'); ?>">
                                        <?php echo $result['score']; ?>%
                                    </span>
                                </td>
                                <td><?php echo date("M j, Y H:i", strtotime($result['end_time'])); ?></td>
                                <td>
                                    <a href="view_result_details.php?result_id=<?php echo $result['result_id']; ?>" class="btn btn-sm btn-info">View Details</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>