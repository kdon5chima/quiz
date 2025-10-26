<?php
// grade_submissions.php

// NOTE: Ensure your configuration files (config.php, helpers.php) and functions 
// (require_login, is_admin) are correctly defined.

// --- CONFIGURATION & DEBUGGING (Enable if needed) ---
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once 'config.php';
// Assuming require_login is a helper function that handles session start and access control
require_login(['teacher', 'admin']); 

$user_id = $_SESSION['user_id'] ?? null;
$is_admin = is_admin();
$full_name = htmlspecialchars($_SESSION['full_name'] ?? 'Teacher');

$submissions = [];
$error_message = '';

try {
    // 1. Initial SQL: Fetch submissions for tests created by this teacher
    $sql = "
        SELECT 
            r.result_id, u.full_name AS student_name, t.title AS test_title, 
            r.score, 
            -- Note: Using the total questions calculated on submission is often safer than t.total_questions
            r.total_questions, 
            r.submission_time, r.is_submitted
        FROM results r
        JOIN users u ON r.user_id = u.user_id
        JOIN tests t ON r.test_id = t.test_id
        WHERE t.created_by = :user_id AND r.is_submitted = 1
        ORDER BY r.submission_time DESC
    ";
    
    // 2. Conditional Logic: Admins see all results; Teachers only see their own tests
    if ($is_admin) {
        // If an Admin, remove the WHERE clause that filters by 'created_by'
        $sql = str_replace("WHERE t.created_by = :user_id AND r.is_submitted = 1", "WHERE r.is_submitted = 1", $sql);
        $stmt = $pdo->prepare($sql);
    } else {
        $stmt = $pdo->prepare($sql);
        // Bind parameter only if it exists and is needed
        if ($user_id !== null) {
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        } else {
            throw new Exception("User ID is missing for teacher access control.");
        }
    }
    
    $stmt->execute();
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Catch both PDOException and custom Exception
    $error_message = "Database Error: Could not load submissions data. (" . htmlspecialchars($e->getMessage()) . ")";
    error_log("Grade Submissions DB Error: " . $e->getMessage());
}

unset($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Grade Submissions | CBT System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><?php echo $is_admin ? 'All Submitted Results' : 'Submitted Results for ' . $full_name; ?></h2>
        <a href="<?php echo $is_admin ? 'admin_dashboard.php' : 'teacher_dashboard.php'; ?>" class="btn btn-secondary">← Back to Dashboard</a>
    </div>

    <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

    <div class="card shadow">
        <div class="card-header bg-success text-white">
            Recent Test Submissions
        </div>
        <table class="table table-striped mb-0">
            <thead>
                <tr>
                    <th>Test Title</th>
                    <th>Student Name</th>
                    <th>Score</th>
                    <th>Submitted Time</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($submissions)): ?>
                <tr><td colspan="5" class="text-center text-muted">No submissions found for your tests.</td></tr>
                <?php else: ?>
                    <?php foreach ($submissions as $submission): 
                        // Use result column for total questions for consistency
                        $total_questions = $submission['total_questions'] ?? 'N/A'; 
                        $score_text = $submission['score'] . '% (' . $submission['score'] . ' / ' . $total_questions . ')';
                        
                        // CORRECTION APPLIED HERE: Link to the final result page
                        $action_link = "view_result_details.php?result_id=" . $submission['result_id'];
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($submission['test_title']); ?></td>
                        <td><?php echo htmlspecialchars($submission['student_name']); ?></td>
                        <td><?php echo $score_text; ?></td>
                        <td><?php echo htmlspecialchars(date("M j, H:i", strtotime($submission['submission_time']))); ?></td>
                        <td><a href="<?php echo $action_link; ?>" class="btn btn-sm btn-info">View Result</a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>