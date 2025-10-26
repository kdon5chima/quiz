<?php
// view_reports.php

require_once 'config.php';

// Access Control: Only Teachers (and typically Admins) can access this page.
require_login(['teacher', 'admin']);
$user_id = $_SESSION['user_id'];
$is_admin = is_admin();
$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'Teacher');

$tests = [];
$error_message = '';

try {
    // Fetch list of tests to run reports on
    $sql = "
        SELECT t.test_id, t.title, COUNT(r.result_id) AS total_submissions
        FROM tests t
        LEFT JOIN results r ON t.test_id = r.test_id
        WHERE t.created_by = :user_id 
        GROUP BY t.test_id, t.title
        ORDER BY t.test_id DESC
    ";

    // Admins see all tests; Teachers only see their own
    if ($is_admin) {
        $sql = str_replace("WHERE t.created_by = :user_id", "", $sql);
        $stmt = $pdo->prepare($sql);
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    }

    $stmt->execute();
    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Database Error: Could not load test list for reports.";
    error_log("View Reports DB Error: " . $e->getMessage());
}

unset($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Reports | CBT System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Reports & Analytics</h2>
        <a href="<?php echo $is_admin ? 'admin_dashboard.php' : 'teacher_dashboard.php'; ?>" class="btn btn-secondary">← Back to Dashboard</a>
    </div>

    <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

    <div class="card shadow">
        <div class="card-header bg-info text-white">
            Select a Test to Generate Reports
        </div>
        <table class="table table-striped mb-0">
            <thead>
                <tr>
                    <th>Test ID</th>
                    <th>Test Title</th>
                    <th>Total Submissions</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tests)): ?>
                <tr><td colspan="4" class="text-center text-muted">No tests found to generate reports.</td></tr>
                <?php else: ?>
                    <?php foreach ($tests as $test): ?>
                    <tr>
                        <td><?php echo $test['test_id']; ?></td>
                        <td><?php echo htmlspecialchars($test['title']); ?></td>
                        <td><?php echo $test['total_submissions']; ?></td>
                        <td>
                            <a href="test_report_detail.php?test_id=<?php echo $test['test_id']; ?>" class="btn btn-sm btn-primary">View Report</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>