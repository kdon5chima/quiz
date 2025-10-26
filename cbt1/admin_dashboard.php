<?php
// admin_dashboard.php

/**
 * Admin Dashboard Page: Displays key system statistics and lists 
 * all users (separated into Teachers and Students) for quick analysis,
 * including pagination for user lists.
 */

// 1. Load Configuration & Enforce Access
session_start();
// NOTE: It's assumed 'config.php' establishes the $pdo connection and security settings.
require_once 'config.php'; 

// Check if user is logged in AND has the 'admin' role.
// Define 'require_login' if it hasn't been defined in an included file.
if (!function_exists('require_login')) {
    function require_login($role) {
        if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? '') !== $role) {
            header("location: login.php");
            exit;
        }
    }
}
require_login('admin'); 

// 2. Dashboard Variables & Session Management
$user_full_name = $_SESSION["full_name"] ?? $_SESSION["username"] ?? "Admin"; 
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

$total_tests = 0;
$total_students = 0;
$total_teachers = 0; // Will include teachers and admins
$total_submissions = 0;
$latest_results = [];
$error_message = '';

// --- Pagination Setup ---
$limit = 20; 
$current_view = filter_input(INPUT_GET, 'view', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'overview';

// Validate view parameter
if (!in_array($current_view, ['overview', 'teachers', 'students'])) {
    $current_view = 'overview';
}

// Ensure current_page is a positive integer
$current_page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?? 1);
$offset = ($current_page - 1) * $limit;

$total_users_in_view = 0;
$paginated_users = [];
$total_pages = 1;

try {
    // 3. Get Total Counts (used for stat cards)
    $stmt_tests = $pdo->query("SELECT COUNT(*) FROM tests");
    $total_tests = $stmt_tests->fetchColumn();

    $stmt_students = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_type = 'student'");
    $stmt_students->execute();
    $total_students = $stmt_students->fetchColumn();

    // Count staff (teachers + admins)
    $stmt_teachers_count = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_type IN ('teacher', 'admin')"); 
    $stmt_teachers_count->execute();
    $total_teachers = $stmt_teachers_count->fetchColumn();

    $stmt_submissions = $pdo->query("SELECT COUNT(*) FROM results WHERE is_submitted = 1");
    $total_submissions = $stmt_submissions->fetchColumn();
    
    // 4. Paginated User List Fetching
    if ($current_view === 'teachers' || $current_view === 'students') {
        
        $where_clause = ($current_view === 'teachers') 
            ? "WHERE user_type IN ('teacher', 'admin')" 
            : "WHERE user_type = 'student'";

        // Get total count for pagination bar
        $stmt_count = $pdo->query("SELECT COUNT(*) FROM users " . $where_clause);
        $total_users_in_view = $stmt_count->fetchColumn();
        
        // Calculate total pages
        $total_pages = ceil($total_users_in_view / $limit);
        
        // Ensure the current page is not beyond the last page
        $current_page = max(1, min($current_page, $total_pages));
        $offset = ($current_page - 1) * $limit; // Recalculate offset if page changed

        // Fetch paginated list
        $sql_paginated = "
            SELECT user_id, username, full_name, user_type, class_year FROM users 
            $where_clause
            ORDER BY full_name ASC 
            LIMIT :limit OFFSET :offset
        ";
        $stmt_paginated = $pdo->prepare($sql_paginated);
        
        // CRITICAL FIX: Use bindValue with PDO::PARAM_INT for LIMIT and OFFSET
        $stmt_paginated->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt_paginated->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt_paginated->execute();
        $paginated_users = $stmt_paginated->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 5. Get Latest 5 Submissions
    $sql_latest = "
        SELECT 
            r.result_id, 
            u.full_name, 
            t.title AS test_name, 
            r.score,
            r.submission_time
        FROM results r
        JOIN users u ON r.user_id = u.user_id
        JOIN tests t ON r.test_id = t.test_id 
        WHERE r.is_submitted = 1
        ORDER BY r.submission_time DESC
        LIMIT 5
    ";
    $stmt_latest = $pdo->query($sql_latest);
    $latest_results = $stmt_latest->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard Data Error: " . $e->getMessage());
    $error_message = "Could not load dashboard data due to a system error. See logs for details.";
}

$pdo = null; // Close the database connection

/**
 * Helper function to render Bootstrap pagination links (IMPROVED for cleaner disabled links).
 * @param string $view The current view ('teachers' or 'students').
 * @param int $currentPage The current page number.
 * @param int $totalPages The total number of pages.
 * @return string HTML for pagination.
 */
function renderPagination($view, $currentPage, $totalPages) {
    if ($totalPages <= 1) {
        return '';
    }
    $html = '<nav><ul class="pagination justify-content-center">';
    $base_url = 'admin_dashboard.php?view=' . urlencode($view);

    // --- Previous Button ---
    $disabled_prev = ($currentPage <= 1) ? 'disabled' : '';
    $prevPageLink = ($currentPage <= 1) ? '#' : $base_url . '&page=' . ($currentPage - 1);
    
    $html .= '<li class="page-item ' . $disabled_prev . '"><a class="page-link" href="' . $prevPageLink . '">Previous</a></li>';

    // --- Page Numbers (simplified to show 5 pages max) ---
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    // Adjust start/end to ensure a reasonable number of links are shown when near boundaries
    if ($end - $start < 4) {
        $start = max(1, $end - 4);
    }
    if ($end - $start < 4) {
        $end = min($totalPages, $start + 4);
    }

    // First page and ellipsis
    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '&page=1">1</a></li>';
        if ($start > 2) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }

    // Main page loop
    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $currentPage) ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $base_url . '&page=' . $i . '">' . $i . '</a></li>';
    }

    // Last page and ellipsis
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '&page=' . $totalPages . '">' . $totalPages . '</a></li>';
    }

    // --- Next Button ---
    $disabled_next = ($currentPage >= $totalPages) ? 'disabled' : '';
    $nextPageLink = ($currentPage >= $totalPages) ? '#' : $base_url . '&page=' . ($currentPage + 1);

    $html .= '<li class="page-item ' . $disabled_next . '"><a class="page-link" href="' . $nextPageLink . '">Next</a></li>';

    $html .= '</ul></nav>';
    return $html;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | CBT System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style> 
        /* Custom styles */
        body { background-color: #f8f9fa; } 
        .sidebar { min-height: 100vh; background-color: #343a40; } 
        .sidebar a { color: #f8f9fa; padding: 10px 15px; display: block; border-bottom: 1px solid #495057;} 
        .sidebar a:hover { background-color: #495057; text-decoration: none; } 
        .card-icon { font-size: 3rem; opacity: 0.3; } 
        .list-section { margin-top: 30px; }
        .list-view-card { margin-top: 20px; margin-bottom: 50px; }
    </style>
</head>
<body>
<div class="d-flex">
    <div class="sidebar text-white p-3">
        <h4 class="mb-4 text-warning">Admin Panel</h4>
        <a href="admin_dashboard.php?view=overview" class="font-weight-bold <?= $current_view === 'overview' ? 'bg-secondary' : '' ?>">📊 Dashboard</a>
        <a href="register.php">👤 Register User</a>
        <a href="manage_tests.php">📝 Manage Tests</a>
        <a href="view_all_results.php">📈 View Results</a> 
        <a href="manage_users.php">🛠️ Manage Users</a>
        <a href="admin_profile.php">⚙️ My Profile</a>
        <a href="logout.php" class="btn btn-danger btn-block mt-3">Logout</a>
    </div>

    <div class="container-fluid p-4">
        <h1><i class="fas fa-tachometer-alt"></i> Welcome Back, <?= htmlspecialchars($user_full_name) ?>!</h1>
        <p class="text-muted">System Management Overview.</p>
        
        <?php if ($success_message): ?><div class="alert alert-success mt-3"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="alert alert-danger mt-3"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?></div><?php endif; ?>

        <?php if ($current_view === 'overview'): ?>
        <div id="overview-content">
            <div class="row mt-4">
                
                <div class="col-md-3">
                    <div class="card bg-primary text-white shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-uppercase font-weight-bold small">Total Students</div>
                                    <div class="h1 mb-0"><?= $total_students ?></div>
                                </div>
                                <i class="fas fa-user-graduate card-icon"></i>
                            </div>
                            <a href="?view=students&page=1" class="text-white small mt-3 d-block font-weight-bold">View Students →</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card bg-info text-white shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-uppercase font-weight-bold small">Total Teachers/Staff</div>
                                    <div class="h1 mb-0"><?= $total_teachers ?></div>
                                </div>
                                <i class="fas fa-chalkboard-teacher card-icon"></i>
                            </div>
                            <a href="?view=teachers&page=1" class="text-white small mt-3 d-block font-weight-bold">View Staff →</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card bg-success text-white shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-uppercase font-weight-bold small">Active Tests</div>
                                    <div class="h1 mb-0"><?= $total_tests ?></div>
                                </div>
                                <i class="fas fa-file-alt card-icon"></i>
                            </div>
                            <a href="manage_tests.php" class="text-white small mt-3 d-block font-weight-bold">Manage Tests →</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-warning text-white shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-uppercase font-weight-bold small">Total Submissions</div>
                                    <div class="h1 mb-0"><?= $total_submissions ?></div>
                                </div>
                                <i class="fas fa-clipboard-check card-icon"></i>
                            </div>
                            <a href="view_all_results.php" class="text-white small mt-3 d-block font-weight-bold">View All Results →</a> 
                        </div>
                    </div>
                </div>

            </div> 
            
            <hr class="mt-5 mb-4">

            <div class="card shadow" id="recent-submissions">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-clock"></i> Recent Test Submissions</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($latest_results)): ?>
                        <p class="alert alert-info mb-0">No test results submitted yet.</p>
                    <?php else: ?>
                        <table class="table table-striped table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Test Name</th>
                                    <th>Score (%)</th>
                                    <th>Submitted On</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($latest_results as $result): ?>
                                <tr>
                                    <td><?= htmlspecialchars($result['full_name']) ?></td>
                                    <td><?= htmlspecialchars($result['test_name']) ?></td>
                                    <?php 
                                    $score_percentage = (float)($result['score'] ?? 0);
                                    
                                    // Determine the color class based on the percentage score
                                    $score_class = 'danger';
                                    if ($score_percentage >= 70) {
                                        $score_class = 'success';
                                    } elseif ($score_percentage >= 50) {
                                        $score_class = 'warning';
                                    }
                                    
                                    $display_score = round($score_percentage, 1) . '%';
                                    ?>
                                    <td class="font-weight-bold text-<?= $score_class ?>">
                                        <?= $display_score ?>
                                    </td>
                                    <td><?= htmlspecialchars((new DateTime($result['submission_time']))->format('M j, Y H:i')) ?></td>
                                    <td>
                                        <a href="view_submission.php?result_id=<?= htmlspecialchars($result['result_id']) ?>" class="btn btn-sm btn-info">View Details</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div> 
        
        <?php else: ?>
        <div id="list-view-content" class="list-view-card">
            
            <a href="admin_dashboard.php?view=overview" class="btn btn-secondary mb-4">← Back to Overview</a>

            <?php if ($current_view === 'teachers'): ?>
                <div class="card shadow list-section">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-chalkboard-teacher"></i> Registered Teachers/Staff (Showing <?= $total_users_in_view ?> Total)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($paginated_users)): ?>
                            <p class="alert alert-light mb-0">No teachers or staff found on this page.</p>
                        <?php else: ?>
                            <table class="table table-striped table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Full Name</th>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paginated_users as $user): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($user['user_id']) ?></td>
                                            <td><?= htmlspecialchars($user['full_name']) ?></td>
                                            <td><?= htmlspecialchars($user['username']) ?></td>
                                            <td><span class="badge badge-<?= $user['user_type'] === 'admin' ? 'danger' : 'info' ?>"><?= ucfirst(htmlspecialchars($user['user_type'])) ?></span></td>
                                            <td><a href="manage_users.php?edit_id=<?= htmlspecialchars($user['user_id']) ?>" class="btn btn-sm btn-outline-info">Manage</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?= renderPagination('teachers', $current_page, $total_pages) ?>
                        <?php endif; ?>
                    </div>
                </div>
            
            <?php elseif ($current_view === 'students'): ?>
                <div class="card shadow list-section">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-user-graduate"></i> Registered Students (Showing <?= $total_users_in_view ?> Total)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($paginated_users)): ?>
                            <p class="alert alert-light mb-0">No student users found on this page.</p>
                        <?php else: ?>
                            <table class="table table-striped table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Full Name</th>
                                        <th>Username</th>
                                        <th>Class</th> <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paginated_users as $user): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($user['user_id']) ?></td>
                                            <td><?= htmlspecialchars($user['full_name']) ?></td>
                                            <td><?= htmlspecialchars($user['username']) ?></td>
                                            <td><span class="badge badge-primary"><?= htmlspecialchars($user['class_year'] ?? 'N/A') ?></span></td> 
                                            <td><a href="manage_users.php?edit_id=<?= htmlspecialchars($user['user_id']) ?>" class="btn btn-sm btn-outline-primary">Manage</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?= renderPagination('students', $current_page, $total_pages) ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>
        <?php endif; ?>

    </div>
</div>
</body>
</html>