<?php
// teacher_dashboard.php - ENHANCED VISUAL DESIGN

// ====================================================
// CONFIG & INITIALIZATION
// ====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
} 
require_once 'config.php';
require_once 'helpers.php'; // Provides require_login() and other helpers

// Enforce Access: Only 'teacher' role is allowed
require_login('teacher'); 

// Safely retrieve teacher session data
$user_id = (int)$_SESSION["user_id"]; 
$full_name = htmlspecialchars($_SESSION["full_name"] ?? 'Teacher');
$username = htmlspecialchars($_SESSION["username"] ?? 'N/A');

// --- Teacher-Specific Logic Initialization ---
$total_tests = 0;
$total_questions = 0;
$error_message = '';
$recent_results = []; 

/**
 * Helper function to determine score badge class.
 * (Best practice suggests moving this to helpers.php)
 */
function get_score_badge_class(int $percent_score): string {
    if ($percent_score >= 70) {
        return 'badge-success';
    } elseif ($percent_score >= 50) {
        return 'badge-warning';
    } else {
        return 'badge-danger';
    }
}

try {
    // 1. Count Total Tests created by this teacher (Bound safely)
    $sql_tests_count = "SELECT COUNT(test_id) FROM tests WHERE created_by = :user_id";
    $stmt_tests_count = $pdo->prepare($sql_tests_count);
    $stmt_tests_count->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_tests_count->execute();
    $total_tests = $stmt_tests_count->fetchColumn();

    // 2. Count Total Questions created by this teacher (using a JOIN)
    $sql_questions_count = "
        SELECT COUNT(q.question_id) 
        FROM questions q
        JOIN tests t ON q.test_id = t.test_id
        WHERE t.created_by = :user_id
    ";
    $stmt_questions_count = $pdo->prepare($sql_questions_count);
    $stmt_questions_count->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_questions_count->execute();
    $total_questions = $stmt_questions_count->fetchColumn();

    // 3. Fetch recent student results (for this teacher's tests only)
    $sql_recent_results = "
        SELECT 
            u.full_name, t.title, r.score, r.total_questions, r.submission_time, r.result_id 
        FROM results r
        JOIN users u ON r.user_id = u.user_id
        JOIN tests t ON r.test_id = t.test_id
        WHERE t.created_by = :user_id AND r.is_submitted = 1
        ORDER BY r.submission_time DESC 
        LIMIT 5
    ";
    $stmt_recent_results = $pdo->prepare($sql_recent_results);
    $stmt_recent_results->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_recent_results->execute();
    $recent_results = $stmt_recent_results->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Database Error: Could not fetch teacher data.";
    error_log("Teacher Dashboard DB Error: " . $e->getMessage());
} catch (Exception $e) {
    $error_message = "Application Error: " . $e->getMessage();
}

$pdo = null; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Dashboard | CBT System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .stat-card { transition: transform 0.2s, box-shadow 0.2s; border-radius: 0.5rem; overflow: hidden; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15) !important; }
        .card-icon { font-size: 2rem; opacity: 0.8; }
    </style>
</head>
<body>
    
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="teacher_dashboard.php"><i class="fas fa-chalkboard-teacher mr-2"></i>Teacher Dashboard</a>
            <span class="navbar-text ml-auto mr-3 text-white-50">
                Welcome, Prof. <?php echo $full_name; ?>
            </span>
            <a href="logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container mt-5">
        
        <?php if ($error_message): ?><div class="alert alert-danger shadow-sm"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

        <h3 class="mb-4 text-secondary">Overview</h3>
        <div class="row mb-5">
            
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card text-white bg-primary stat-card shadow">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-uppercase mb-0">Tests Created</h5>
                            <p class="card-text display-4 font-weight-bold"><?php echo htmlspecialchars($total_tests); ?></p>
                        </div>
                        <i class="fas fa-clipboard-list card-icon"></i>
                    </div>
                    <div class="card-footer bg-primary border-0">
                        <a href="manage_tests.php" class="text-white small text-decoration-none">Manage Tests <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card text-white bg-success stat-card shadow">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-uppercase mb-0">Total Questions</h5>
                            <p class="card-text display-4 font-weight-bold"><?php echo htmlspecialchars($total_questions); ?></p>
                        </div>
                        <i class="fas fa-question-circle card-icon"></i>
                    </div>
                    <div class="card-footer bg-success border-0">
                        <a href="manage_tests.php" class="text-white small text-decoration-none">Edit Question Bank <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card text-dark bg-light stat-card shadow">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title text-uppercase mb-0">Results Center</h5>
                            <p class="card-text display-4 text-secondary font-weight-bold"><i class="fas fa-chart-bar"></i></p>
                        </div>
                    </div>
                    <div class="card-footer bg-light border-0">
                        <a href="view_reports.php" class="text-secondary small text-decoration-none">View All Reports <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>
            </div>
        </div>
        
        <h3 class="mb-4 text-secondary">Quick Actions</h3>
        <div class="card shadow mb-5">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="manage_students.php" class="btn btn-warning btn-block btn-lg text-white"><i class="fas fa-users mr-2"></i> Manage Students</a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="register_student.php" class="btn btn-secondary btn-block btn-lg"><i class="fas fa-user-plus mr-2"></i> Register Student</a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="manage_tests.php" class="btn btn-primary btn-block btn-lg"><i class="fas fa-edit mr-2"></i> Create/Manage Tests</a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="grade_submissions.php" class="btn btn-success btn-block btn-lg"><i class="fas fa-list-alt mr-2"></i> Review Submissions</a>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-4 mb-3">
                        <a href="view_reports.php" class="btn btn-info btn-block btn-lg"><i class="fas fa-search-chart mr-2"></i> View Reports</a> 
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="teacher_profile.php" class="btn btn-secondary btn-block btn-lg"><i class="fas fa-cog mr-2"></i> My Profile</a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="logout.php" class="btn btn-danger btn-block btn-lg"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card shadow">
            <div class="card-header bg-white border-bottom-0">
                <h5 class="mb-0"><i class="fas fa-history mr-2"></i>Recent Student Submissions (Your Tests)</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Student</th>
                            <th>Test Title</th>
                            <th>Score</th>
                            <th>Time Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_results)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No student submissions yet for your tests.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent_results as $result): 
                                // Calculate percentage score
                                $percent_score = ($result['total_questions'] > 0) 
                                             ? round(($result['score'] / $result['total_questions']) * 100)
                                             : 0;
                                // Use helper function to get badge class
                                $score_badge_class = get_score_badge_class((int)$percent_score);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($result['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($result['title']); ?></td>
                                <td>
                                    <span class="badge <?php echo $score_badge_class; ?>"><?php echo htmlspecialchars($percent_score); ?>%</span>
                                    <small class="text-muted">(<?php echo htmlspecialchars($result['score']); ?>/<?php echo htmlspecialchars($result['total_questions']); ?>)</small>
                                </td>
                                <td><?php echo htmlspecialchars(date("M j, H:i", strtotime($result['submission_time']))); ?></td>
                                <td>
                                    <a href="view_results.php?result_id=<?php echo $result['result_id']; ?>" class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>