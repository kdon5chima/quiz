<?php
// manage_tests.php

// ====================================================
// CONFIG & INITIALIZATION
// ====================================================

// Conditionally start the session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
} 
require_once 'config.php';
require_once 'helpers.php'; // Includes functions like is_admin() and require_login()

// Access Control: Only admins and teachers can manage tests
require_login(['admin', 'teacher']); 
$is_admin = is_admin(); 
$user_id = (int)$_SESSION['user_id']; // Cast to int for security

// Generate and store CSRF token for secure POST actions like delete
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Retrieve and clear session messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// ====================================================
// PAGINATION LOGIC
// ====================================================

$records_per_page = 20;
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
if ($current_page < 1) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $records_per_page;

$total_records = 0;
try {
    $count_sql = "SELECT COUNT(test_id) FROM tests";
    $where_clause = (!$is_admin) ? " WHERE created_by = :user_id" : "";
    $count_sql .= $where_clause;
    
    $stmt_count = $pdo->prepare($count_sql);
    
    if (!$is_admin) {
        $stmt_count->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    }
    
    $stmt_count->execute();
    $total_records = $stmt_count->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Test Pagination Count Error: " . $e->getMessage());
    $error_message = "A database error occurred while calculating total tests.";
}

$total_pages = ceil($total_records / $records_per_page);
if ($total_records > 0 && $offset >= $total_records) {
    $current_page = max(1, $total_pages);
    $offset = ($current_page - 1) * $records_per_page;
}


// ====================================================
// --- Test Deletion Logic ---
// ====================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_test'])) {
    
    // CRITICAL SECURITY FIX: CSRF Token Check
    $posted_token = filter_input(INPUT_POST, 'csrf_token', FILTER_UNSAFE_RAW);
    if (!isset($_SESSION['csrf_token']) || $posted_token !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Security error: Invalid request (CSRF check failed).";
        header("location: manage_tests.php");
        exit;
    }
    
    $test_id_to_delete = filter_input(INPUT_POST, 'test_id', FILTER_VALIDATE_INT);
    
    if ($test_id_to_delete) {
        try {
            // Check ownership/existence first for permission and security
            $sql_check = "SELECT created_by FROM tests WHERE test_id = :test_id";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->bindParam(':test_id', $test_id_to_delete, PDO::PARAM_INT);
            $stmt_check->execute();
            $test_creator_id = $stmt_check->fetchColumn();

            if ($test_creator_id === false) {
                 $_SESSION['error_message'] = "Test not found.";
                 header("location: manage_tests.php");
                 exit;
            }
            
            // Only non-admins (teachers) are restricted to their own tests
            if (!$is_admin && $test_creator_id != $user_id) {
                $_SESSION['error_message'] = "Permission denied: You can only delete tests you created.";
                header("location: manage_tests.php");
                exit;
            }
            
            // --- DELETION TRANSACTION ---
            $pdo->beginTransaction();
            
            // 1. Delete student answers (student_answers -> results -> test)
            // Using a multi-table DELETE syntax. Requires the proper database user permissions.
            $sql_del_sa = "
                DELETE sa FROM student_answers sa
                JOIN results r ON sa.result_id = r.result_id
                WHERE r.test_id = :test_id
            ";
            $stmt_del_sa = $pdo->prepare($sql_del_sa);
            $stmt_del_sa->bindParam(':test_id', $test_id_to_delete, PDO::PARAM_INT);
            $stmt_del_sa->execute();

            // 2. Delete results
            $sql_del_results = "DELETE FROM results WHERE test_id = :test_id";
            $stmt_del_results = $pdo->prepare($sql_del_results);
            $stmt_del_results->bindParam(':test_id', $test_id_to_delete, PDO::PARAM_INT);
            $stmt_del_results->execute();

            // 3. Delete options (options -> questions -> test)
            $sql_del_options = "
                DELETE o FROM options o
                JOIN questions q ON o.question_id = q.question_id
                WHERE q.test_id = :test_id
            ";
            $stmt_del_options = $pdo->prepare($sql_del_options);
            $stmt_del_options->bindParam(':test_id', $test_id_to_delete, PDO::PARAM_INT);
            $stmt_del_options->execute();

            // 4. Delete questions
            $sql_del_questions = "DELETE FROM questions WHERE test_id = :test_id";
            $stmt_del_questions = $pdo->prepare($sql_del_questions);
            $stmt_del_questions->bindParam(':test_id', $test_id_to_delete, PDO::PARAM_INT);
            $stmt_del_questions->execute();
            
            // 5. Finally, delete the test itself
            $sql_del_test = "DELETE FROM tests WHERE test_id = :test_id";
            $stmt_del_test = $pdo->prepare($sql_del_test);
            $stmt_del_test->bindParam(':test_id', $test_id_to_delete, PDO::PARAM_INT);
            $stmt_del_test->execute();
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "Test and all associated data deleted successfully.";
            // Redirect back to the same page/page number
            header("location: manage_tests.php?page=" . $current_page); 
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Test Deletion Error: " . $e->getMessage());
            $_SESSION['error_message'] = "Error deleting test. Please check database logs.";
            header("location: manage_tests.php");
            exit;
        }
    } else {
        $_SESSION['error_message'] = "Invalid Test ID provided for deletion.";
        header("location: manage_tests.php");
        exit;
    }
}


// ====================================================
// --- Fetch Tests ---
// ====================================================
$tests = [];
try {
    $sql = "
        SELECT 
            t.test_id, t.title, t.duration_minutes, t.class_year, t.is_active, 
            u.full_name AS creator_name, 
            COUNT(q.question_id) AS total_questions
        FROM tests t
        LEFT JOIN questions q ON t.test_id = q.test_id
        LEFT JOIN users u ON t.created_by = u.user_id
    ";
    
    if (!$is_admin) {
        $sql .= " WHERE t.created_by = :user_id ";
    }
    
    $sql .= "
        GROUP BY 
            t.test_id, t.title, t.duration_minutes, t.class_year, t.is_active, u.full_name
        ORDER BY t.class_year, t.test_id DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($sql);
    
    if (!$is_admin) {
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    }
    
    $stmt->bindParam(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Test Load Error: " . $e->getMessage());
    $error_message = "Could not load test list due to a database error.";
}
// -----------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Tests | <?php echo $is_admin ? 'Admin' : 'Teacher'; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style> .sidebar { min-height: 100vh; background-color: #343a40; } .sidebar a { color: #f8f9fa; padding: 10px 15px; display: block; } .sidebar a:hover { background-color: #495057; text-decoration: none; } </style>
</head>
<body>


<div class="d-flex">
    <div class="sidebar text-white p-3">
        <h4 class="mb-4"><?php echo $is_admin ? 'Admin Panel' : 'Teacher Panel'; ?></h4>
        
        <?php if ($is_admin): ?>
            <a href="admin_dashboard.php"><i class="fas fa-chart-line mr-2"></i> Dashboard</a>
            <a href="register.php"><i class="fas fa-user-plus mr-2"></i> Register Student</a>
            <a href="manage_tests.php" class="font-weight-bold"><i class="fas fa-edit mr-2"></i> Manage Tests</a>
            <a href="grade_submissions.php"><i class="fas fa-check-circle mr-2"></i> Grade Submissions</a>
            <a href="view_reports.php"><i class="fas fa-chart-bar mr-2"></i> View Reports</a>
            <a href="view_results.php"><i class="fas fa-poll mr-2"></i> View All Results</a>
            <a href="manage_users.php"><i class="fas fa-users-cog mr-2"></i> Manage Users</a>
            <a href="admin_profile.php"><i class="fas fa-cog mr-2"></i> My Profile</a>
        <?php else: ?>
            <a href="teacher_dashboard.php"><i class="fas fa-chart-line mr-2"></i> Dashboard</a>
            <a href="manage_tests.php" class="font-weight-bold"><i class="fas fa-edit mr-2"></i> Manage Tests</a>
            <a href="grade_submissions.php"><i class="fas fa-check-circle mr-2"></i> Grade Submissions</a>
            <a href="view_reports.php"><i class="fas fa-chart-bar mr-2"></i> View Reports</a>
            <a href="teacher_profile.php"><i class="fas fa-cog mr-2"></i> My Profile</a>
        <?php endif; ?>
        
        <a href="logout.php" class="btn btn-danger btn-sm mt-3"><i class="fas fa-sign-out-alt mr-1"></i> Logout</a>
    </div>



    <div class="container-fluid p-4">
        <h2>Manage All Tests</h2>
        <p><a href="create_test.php" class="btn btn-primary">➕ Create New Test</a></p>
        
        <?php if ($success_message): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

        <div class="card shadow mb-4">
            <div class="card-header bg-secondary text-white">
                All Tests (<?php echo $total_records; ?> Total) </div>
            <div class="card-body">
                <?php if (empty($tests)): ?>
                    <div class="alert alert-info">No tests have been created yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered">
                        <thead class="thead-dark">
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Year</th>
                                <th>Duration (min)</th>
                                <th>Questions</th>
                                <?php if ($is_admin): ?><th>Creator</th><?php endif; ?>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tests as $test): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($test['test_id']); ?></td>
                                <td><?php echo htmlspecialchars($test['title']); ?></td>
                                <td><?php echo htmlspecialchars($test['class_year']); ?></td>
                                <td><?php echo htmlspecialchars($test['duration_minutes']); ?></td>
                                <td><?php echo htmlspecialchars($test['total_questions']); ?></td>
                                <?php if ($is_admin): ?><td><?php echo htmlspecialchars($test['creator_name'] ?? 'N/A'); ?></td><?php endif; ?>
                                <td>
                                    <?php if ($test['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <a href="add_questions.php?test_id=<?php echo htmlspecialchars($test['test_id']); ?>" class="btn btn-sm btn-primary mr-1">Questions (<?php echo htmlspecialchars($test['total_questions']); ?>)</a>
                                    
                                    <button type="button" 
                                        data-id="<?php echo htmlspecialchars($test['test_id']); ?>" 
                                        data-status="<?php echo htmlspecialchars($test['is_active']); ?>"
                                        class="btn btn-sm toggle-status-btn <?php echo $test['is_active'] ? 'btn-warning' : 'btn-success'; ?> mr-1">
                                        <?php echo $test['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                    
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('WARNING! Deleting the test \'<?php echo htmlspecialchars($test['title']); ?>\' is permanent and will remove all student results and questions for it. Proceed?');">
                                        <input type="hidden" name="test_id" value="<?php echo htmlspecialchars($test['test_id']); ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <button type="submit" name="delete_test" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Test list navigation">
                            <ul class="pagination justify-content-center mt-4">
                                <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>">Previous</a>
                                </li>

                                <?php 
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++): 
                                ?>
                                    <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>">Next</a>
                                </li>
                            </ul>
                            <p class="text-center text-muted">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></p>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
// Toggle Test Status Logic (AJAX)
$(document).ready(function() {
    // Hide any previous PHP-generated success messages after 5 seconds
    setTimeout(function() {
        $('.alert-success, .alert-danger').fadeOut(1000);
    }, 5000);

    $('.toggle-status-btn').on('click', function() {
        var button = $(this);
        var testId = button.data('id');
        var currentStatus = button.data('status');
        var action = currentStatus == 1 ? 'DEACTIVATE' : 'ACTIVATE';

        if (!confirm('Are you sure you want to ' + action + ' this test?')) {
            return;
        }

        $.ajax({
            url: 'toggle_test.php',
            type: 'POST',
            data: { 
                test_id: testId,
                // Send the current status so the server knows which way to flip it
                current_status: currentStatus 
            },
            dataType: 'json',
            beforeSend: function() {
                button.prop('disabled', true).text('Updating...');
            },
            success: function(response) {
                if (response.success) {
                    var newStatus = response.new_status;
                    
                    var newStatusText = newStatus == 1 ? 'Active' : 'Inactive';
                    var newButtonText = newStatus == 1 ? 'Deactivate' : 'Activate';
                    var newBadgeClass = newStatus == 1 ? 'badge-success' : 'badge-danger';
                    var newButtonClass = newStatus == 1 ? 'btn-warning' : 'btn-success';
                    
                    // The column index depends on whether the 'Creator' column is present
                    var statusColumnIndex = <?php echo $is_admin ? 6 : 5; ?>;
                    var $badge = button.closest('tr').find('td:eq('+ statusColumnIndex +') .badge'); 
                    $badge.removeClass('badge-success badge-danger').addClass(newBadgeClass).text(newStatusText);
                    
                    // Update the Button Text/Class and the data-status for the NEXT click
                    button.data('status', newStatus) // CRITICAL: Update the data attribute
                          .removeClass('btn-warning btn-success')
                          .addClass(newButtonClass)
                          .text(newButtonText);

                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                var errorMessage = 'An unknown server error occurred.';
                try {
                    // Try to parse JSON error response from the server
                    var response = JSON.parse(xhr.responseText);
                    errorMessage = response.message || errorMessage;
                } catch(e) {
                    // Fallback to plain text or generic error
                    errorMessage = xhr.responseText || errorMessage;
                }
                alert('Error: ' + errorMessage);
            },
            complete: function() {
                // IMPORTANT: Re-enable the button, using the *updated* data-status value
                var finalStatus = button.data('status'); 
                var finalText = finalStatus == 1 ? 'Deactivate' : 'Activate';
                var finalCssClass = finalStatus == 1 ? 'btn-warning' : 'btn-success';

                button.prop('disabled', false)
                      .text(finalText)
                      .removeClass('btn-warning btn-success')
                      .addClass(finalCssClass);
            }
        });
    });
});
</script>
</body>
</html>