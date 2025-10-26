<?php
// view_all_results.php (Integrated Admin & Teacher Version)


require_once 'config.php';

// --- ACCESS CONTROL ---
require_login(['admin', 'teacher']); 
$user_id = $_SESSION['user_id'];
$is_admin = is_admin();
// ----------------------

$error_message = '';
$results = []; 

try {
    global $pdo;

    // Fetch results, joining student, test, and the test creator's name
    $sql = "
        SELECT 
            r.result_id, 
            u.full_name AS student_name, 
            t.title AS test_title, 
            creator.full_name AS creator_name,
            r.correct_answers,
            r.total_questions,
            r.score,
            r.submission_time
        FROM results r
        -- CRITICAL FIX: Use LEFT JOINs to prevent submitted results from disappearing 
        -- if a student (u), test (t), or creator (creator) record is deleted.
        LEFT JOIN users u ON r.user_id = u.user_id 
        LEFT JOIN tests t ON r.test_id = t.test_id
        LEFT JOIN users creator ON t.created_by = creator.user_id 
        WHERE r.is_submitted = 1
    ";

    $params = [];
    
    // CONDITION: Filter results if the user is a Teacher
    if (!$is_admin) {
        // Teacher filter must be applied to tests they created
        $sql .= " AND t.created_by = :user_id ";
        $params[':user_id'] = $user_id;
    }
    
    $sql .= " ORDER BY r.submission_time DESC ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Database error loading results list.";
    error_log("Results List Error: " . $e->getMessage());
}

unset($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View All Results | <?php echo $is_admin ? 'Admin' : 'Teacher'; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
            <a href="view_all_results.php" class="font-weight-bold">📈 View All Results</a>
            <a href="manage_users.php">🛠️ Manage Users</a>
            <a href="admin_profile.php">⚙️ My Profile</a>
        <?php else: ?>
            <a href="teacher_dashboard.php">📊 Dashboard</a>
            <a href="manage_tests.php">📝 Manage Tests</a>
            <a href="grade_submissions.php">✅ Grade Submissions</a>
            <a href="view_reports.php">📊 View Reports</a>
            <a href="view_all_results.php" class="font-weight-bold">📈 Results Center</a>
            <a href="teacher_profile.php">⚙️ My Profile</a>
        <?php endif; ?>
        
        <a href="logout.php" class="btn btn-danger btn-sm mt-3">Logout</a>
    </div>

    <div class="container-fluid p-4">
        <h2>📈 <?php echo $is_admin ? 'All Student Test Results' : 'Your Test Results'; ?></h2>
        <p class="text-muted"><?php echo $is_admin ? 'A comprehensive list of all submitted tests.' : 'A comprehensive list of results for tests you created.'; ?></p>

        <?php if (!empty($error_message)): ?><div class="alert alert-danger mt-3"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
        
        <div class="card shadow mb-4">
            <div class="card-header bg-secondary text-white">
                Submitted Results (<?php echo count($results); ?> Total)
            </div>
            <div class="card-body">
                <?php if (empty($results)): ?>
                    <div class="alert alert-info">No submitted test results found<?php echo $is_admin ? '.' : ' for your tests.'; ?></div>
                <?php else: ?>
                    <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered">
                        <thead class="thead-dark">
                            <tr>
                                <th>ID</th>
                                <th>Student Name</th>
                                <th>Test Title</th>
                                <?php if ($is_admin): ?><th>Creator</th><?php endif; ?> <th>Score (%)</th>
                                <th>Correct/Total</th>
                                <th>Submitted On</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($result['result_id']); ?></td>
                                
                                <td><?php echo htmlspecialchars($result['student_name'] ?? 'DELETED USER'); ?></td>
                                <td><?php echo htmlspecialchars($result['test_title'] ?? 'DELETED TEST'); ?></td>
                                
                                <?php if ($is_admin): ?>
                                    <td><?php echo htmlspecialchars($result['creator_name'] ?? 'DELETED CREATOR'); ?></td>
                                <?php endif; ?>
                                
                                <td class="font-weight-bold text-<?php echo $result['score'] >= 50 ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars($result['score']); ?>%</td>
                                <td><?php echo htmlspecialchars($result['correct_answers']) . ' / ' . htmlspecialchars($result['total_questions']); ?></td>
                                <td><?php echo htmlspecialchars((new DateTime($result['submission_time']))->format('M j, H:i')); ?></td>
                                <td>
                                    <a href="view_result_details.php?result_id=<?php echo htmlspecialchars($result['result_id']); ?>" class="btn btn-sm btn-info">
        View Detail
    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>