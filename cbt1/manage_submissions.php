<?php
// manage_submissions.php - Central hub for Admins/Teachers to view and manage active and recent submissions.

// --- CONFIGURATION & SECURITY ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php'; 
require_once 'helpers.php'; // Contains is_admin(), is_teacher() etc.

// Check if $pdo connection object is available
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Configuration Error: Database connection object (\$pdo) not initialized correctly.");
}

// --- ACCESS CONTROL ---
if (!is_admin() && !is_teacher()) {
    header("location: login.php");
    exit;
}

// -------------------------------------------------------------------------
// DATA RETRIEVAL
// -------------------------------------------------------------------------

$submissions = [];
$error_message = filter_input(INPUT_GET, 'error', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$success_message = filter_input(INPUT_GET, 'success', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

try {
    // Select all recent or active results, including student and test names.
    // We order by submission_time (for completed tests) and then by start_time (for active tests).
    $sql = "
        SELECT 
            r.result_id, r.user_id, r.test_id, r.score, 
            r.start_time, r.submission_time, r.is_submitted,
            u.full_name AS student_name, 
            t.title AS test_title,
            t.duration_minutes
        FROM results r
        JOIN users u ON r.user_id = u.user_id
        JOIN tests t ON r.test_id = t.test_id
        WHERE r.start_time IS NOT NULL 
        ORDER BY r.is_submitted ASC, r.start_time DESC
        LIMIT 50 -- Limit to last 50 attempts for performance
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Database Error: Could not fetch submissions. " . htmlspecialchars($e->getMessage());
    error_log("Manage Submissions PDO Error: " . $e->getMessage());
}

unset($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Submissions | Admin/Teacher</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Active & Recent Test Submissions</h2>
        <a href="admin_dashboard.php" class="btn btn-info">← Dashboard</a>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <p class="text-muted">
        This list shows up to the last 50 started tests. "Active" submissions (score N/A) can be monitored live.
    </p>

    <?php if (empty($submissions)): ?>
        <div class="alert alert-warning">No test submissions or active tests found in the database.</div>
    <?php else: ?>
        <div class="table-responsive shadow-sm">
            <table class="table table-hover table-striped">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>Student Name</th>
                        <th>Test Title</th>
                        <th>Status</th>
                        <th>Score</th>
                        <th>Time Left (Approx.)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $sub): ?>
                        <?php
                        $is_active = $sub['is_submitted'] == 0 || $sub['is_submitted'] === null;
                        
                        $status_class = $is_active ? 'table-warning' : 'table-success';
                        $status_text = $is_active ? 'Active' : 'Submitted';
                        $score_display = $is_active ? 'N/A' : htmlspecialchars($sub['score']) . '%';
                        
                        $time_left_display = 'N/A';
                        $time_left_badge = 'secondary';
                        
                        if ($is_active && $sub['duration_minutes'] > 0) {
                            try {
                                $start_time = new DateTime($sub['start_time']);
                                $duration = new DateInterval('PT' . $sub['duration_minutes'] . 'M');
                                $end_time = clone $start_time;
                                $end_time->add($duration);
                                $now = new DateTime();
                                
                                if ($now > $end_time) {
                                    $time_left_display = 'TIME EXPIRED';
                                    $time_left_badge = 'danger';
                                    $status_class = 'table-danger'; // Highlight expired tests
                                } else {
                                    $time_diff = $now->diff($end_time);
                                    $minutes = $time_diff->h * 60 + $time_diff->i;
                                    $time_left_display = $minutes . 'm ' . $time_diff->s . 's';
                                    $time_left_badge = 'info';
                                }
                            } catch (Exception $e) {
                                $time_left_display = 'Error';
                            }
                        }
                        ?>
                        <tr class="<?php echo $status_class; ?>">
                            <td><?php echo htmlspecialchars($sub['result_id']); ?></td>
                            <td><?php echo htmlspecialchars($sub['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($sub['test_title']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $is_active ? 'warning' : 'success'; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td><?php echo $score_display; ?></td>
                            <td>
                                <span class="badge badge-<?php echo $time_left_badge; ?>">
                                    <?php echo $time_left_display; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($is_active): ?>
                                    <a href="view_submission.php?user_id=<?php echo $sub['user_id']; ?>&test_id=<?php echo $sub['test_id']; ?>" class="btn btn-sm btn-primary">
                                        Monitor Live
                                    </a>
                                <?php else: ?>
                                    <a href="view_result_details.php?result_id=<?php echo $sub['result_id']; ?>" class="btn btn-sm btn-outline-dark">
                                        View Result
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>