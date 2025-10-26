<?php
// manage_users.php - Universal User Management

// Include configuration and session start (assuming config.php handles session_start() and DB connection)
require_once 'config.php';

// Assuming is_admin() is defined in config.php or elsewhere required
if (!is_admin()) {
    header("location: login.php");
    exit;
}

$success_message = '';
$error_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get the ID of the currently logged-in admin for self-deletion prevention
$logged_in_user_id = $_SESSION['user_id'] ?? null; 

// --- Pagination Setup ---
$limit = 20; // Set pagination limit to 20 users per page
$current_view = $_GET['view'] ?? 'all'; // 'all', 'teachers', or 'students'
$current_page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?? 1);

$total_users_in_view = 0;
$paginated_users = [];
$total_pages = 1;
$view_title = "All Users";


// --- User Deletion Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_user'])) {
    $user_id_to_delete = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
    
    if ($user_id_to_delete) {
        try {
            
            // SECURITY CHECK 1: Prevent admin from deleting their own active account
            if ($user_id_to_delete == $logged_in_user_id) {
                $error_message = "ERROR: You cannot delete your own active administrator account.";
            } else {

                // SECURITY CHECK 2: Check the type of the user being deleted
                $sql_check_admin = "SELECT user_type FROM users WHERE user_id = :user_id";
                $stmt_check_admin = $pdo->prepare($sql_check_admin);
                $stmt_check_admin->bindParam(':user_id', $user_id_to_delete, PDO::PARAM_INT);
                $stmt_check_admin->execute();
                $user_type = $stmt_check_admin->fetchColumn(); 

                if ($user_type === 'admin') { 
                    $error_message = "Cannot delete an administrator account through this panel.";
                } else {
                    // Begin Transaction to ensure all or nothing is deleted
                    $pdo->beginTransaction();
                    
                    // 1. Get ALL result_ids associated with this user
                    $sql_get_results = "SELECT result_id FROM results WHERE user_id = :user_id";
                    $stmt_get_results = $pdo->prepare($sql_get_results);
                    $stmt_get_results->bindParam(':user_id', $user_id_to_delete, PDO::PARAM_INT);
                    $stmt_get_results->execute();
                    $result_ids = $stmt_get_results->fetchAll(PDO::FETCH_COLUMN);

                    if (!empty($result_ids)) {
                        // Prepare placeholders for the IN clause (?, ?, ?)
                        $in_placeholders = implode(',', array_fill(0, count($result_ids), '?'));
                        
                        // 2. Delete student_answers associated with the found results
                        $sql_del_answers = "DELETE FROM student_answers WHERE result_id IN ($in_placeholders)";
                        $stmt_del_answers = $pdo->prepare($sql_del_answers);
                        $stmt_del_answers->execute($result_ids);
                    }
                    
                    // 3. Delete results
                    $sql_del_results = "DELETE FROM results WHERE user_id = :user_id";
                    $stmt_del_results = $pdo->prepare($sql_del_results);
                    $stmt_del_results->bindParam(':user_id', $user_id_to_delete, PDO::PARAM_INT);
                    $stmt_del_results->execute();

                    // 4. Delete the user
                    $sql_del_user = "DELETE FROM users WHERE user_id = :user_id";
                    $stmt_del_user = $pdo->prepare($sql_del_user);
                    $stmt_del_user->bindParam(':user_id', $user_id_to_delete, PDO::PARAM_INT);
                    $stmt_del_user->execute();
                    
                    $pdo->commit();
                    
                    $_SESSION['success_message'] = "User and all associated data deleted successfully.";
                    // Redirect back to the same view the user was on
                    header("location: manage_users.php?view=" . $current_view . "&page=" . $current_page);
                    exit;
                }
            }
        } catch (PDOException $e) {
            // Rollback on any error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("User Deletion Error: " . $e->getMessage());
            $error_message = "Error deleting user. See logs for details.";
        }
    }
}

// --- Dynamic User Fetching (Paginated) ---
// Determines the WHERE clause based on the current view
if ($current_view === 'teachers') {
    $where_clause = "WHERE user_type = 'teacher'";
    $view_title = "Teachers/Staff";
} elseif ($current_view === 'students') {
    $where_clause = "WHERE user_type = 'student'";
    $view_title = "Students";
} else {
    // 'all' view should include all user types including admins (corrected logic)
    $where_clause = "WHERE user_type IN ('student', 'teacher', 'admin')";
    $view_title = "All Users";
}

try {
    // 1. Get Total Count
    $sql_count = "SELECT COUNT(*) FROM users " . $where_clause;
    $stmt_count = $pdo->query($sql_count);
    $total_users_in_view = $stmt_count->fetchColumn();
    $total_pages = ceil($total_users_in_view / $limit);

    // Recalculate page and offset based on total pages
    $current_page = max(1, min($current_page, $total_pages > 0 ? $total_pages : 1));
    $offset = ($current_page - 1) * $limit;
    
    // 2. Fetch Paginated Users
    $sql_paginated = "SELECT user_id, username, full_name, class_year, user_type 
                      FROM users 
                      " . $where_clause . " 
                      ORDER BY full_name ASC 
                      LIMIT :limit OFFSET :offset";
            
    $stmt_paginated = $pdo->prepare($sql_paginated);
    $stmt_paginated->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt_paginated->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt_paginated->execute();
    $paginated_users = $stmt_paginated->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = (isset($error_message) && $error_message) ? $error_message : "Could not load user list: " . $e->getMessage();
}

// Unset the PDO connection after all database operations are complete
$pdo = null;


/**
 * Helper function to render Bootstrap pagination links.
 * @param string $view The current view ('all', 'teachers', or 'students').
 * @param int $currentPage The current page number.
 * @param int $totalPages The total number of pages.
 * @return string HTML for pagination.
 */
function renderPagination($view, $currentPage, $totalPages) {
    if ($totalPages <= 1) return ''; 

    $html = '<nav><ul class="pagination justify-content-center mt-3">';
    $url = 'manage_users.php?view=' . $view;

    // Previous Button
    $disabled = ($currentPage <= 1) ? 'disabled' : '';
    $prevPage = $currentPage - 1;
    $html .= '<li class="page-item ' . $disabled . '"><a class="page-link" href="' . $url . '&page=' . $prevPage . '">Previous</a></li>';

    // Page Numbers (simplified to show 5 pages max)
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '&page=1">1</a></li>';
        if ($start > 2) $html .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $currentPage) ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $url . '&page=' . $i . '">' . $i . '</a></li>';
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) $html .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '&page=' . $totalPages . '">' . $totalPages . '</a></li>';
    }

    // Next Button
    $disabled = ($currentPage >= $totalPages) ? 'disabled' : '';
    $nextPage = $currentPage + 1;
    $html .= '<li class="page-item ' . $disabled . '"><a class="page-link" href="' . $url . '&page=' . $nextPage . '">Next</a></li>';

    $html .= '</ul></nav>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users | Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style> 
        .sidebar { min-height: 100vh; background-color: #343a40; } 
        .sidebar a { color: #f8f9fa; padding: 10px 15px; display: block; } 
        .sidebar a:hover { background-color: #495057; text-decoration: none; } 
        .table-container { max-height: 70vh; overflow-y: auto; }
        .sticky-top { top: 0; background-color: #343a40; z-index: 10; } /* Ensure table header stays visible */
    </style>
</head>
<body>
<div class="d-flex">
    <div class="sidebar text-white p-3">
    <h4 class="mb-4">Admin Panel</h4>
    <a href="admin_dashboard.php">📊 Dashboard</a>
    <a href="register.php">👤 Register User</a>
    <a href="manage_tests.php">📝 Manage Tests</a>
    <a href="view_result.php">📈 View Results</a>
    <a href="admin_class_analysis.php">📈 View Results Analysis</a>
    <a href="manage_users.php" class="font-weight-bold">🛠️ Manage Users</a>
    <a href="admin_profile.php">⚙️ My Profile</a>
    <a href="logout.php" class="btn btn-danger btn-sm mt-3">Logout</a>
</div>

    <div class="container-fluid p-4">
        <h2>Manage User Accounts</h2>
        <p><a href="register.php" class="btn btn-primary">➕ Register New User</a></p>
        
        <?php if ($success_message): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
        
        <!-- View Selection Tabs -->
        <div class="mb-3">
            <a href="?view=all" class="btn <?php echo $current_view === 'all' ? 'btn-dark' : 'btn-outline-secondary'; ?>">All Users</a>
            <a href="?view=students" class="btn <?php echo $current_view === 'students' ? 'btn-primary' : 'btn-outline-primary'; ?>">Students</a>
            <a href="?view=teachers" class="btn <?php echo $current_view === 'teachers' ? 'btn-info' : 'btn-outline-info'; ?>">Teachers/Staff</a>
        </div>


        <div class="card shadow mb-4">
            <div class="card-header <?php echo $current_view === 'students' ? 'bg-primary' : ($current_view === 'teachers' ? 'bg-info' : 'bg-secondary'); ?> text-white">
                <?php echo htmlspecialchars($view_title); ?> (Page <?php echo $current_page; ?> of <?php echo $total_pages; ?> | <?php echo $total_users_in_view; ?> Total)
            </div>
            <div class="card-body">
                <?php if (empty($paginated_users)): ?>
                    <div class="alert alert-info">No user accounts found for this view on this page.</div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="table table-striped table-hover table-bordered table-sm">
                            <thead class="thead-dark sticky-top">
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Role / Class Year</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paginated_users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td>
                                        <?php 
                                        if ($user['user_type'] === 'student') {
                                            echo '<span class="badge badge-primary">Student</span>' . (empty($user['class_year']) ? '' : ' (Year ' . htmlspecialchars($user['class_year']) . ')');
                                        } else {
                                            $badge_color = ($user['user_type'] === 'admin') ? 'badge-danger' : 'badge-info';
                                            echo '<span class="badge ' . $badge_color . '">' . ucfirst(htmlspecialchars($user['user_type'])) . '</span>'; // Teacher or Admin
                                        }
                                        ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <a href="register.php?edit_id=<?php echo htmlspecialchars($user['user_id']); ?>" class="btn btn-sm btn-warning mr-1">Edit</a>
                                        
                                        <!-- Delete Button triggers the custom modal -->
                                        <?php 
                                        // Disable delete button for the current logged-in admin or any user whose type is 'admin'
                                        $is_current_admin = ($user['user_id'] == $logged_in_user_id);
                                        $is_any_admin = ($user['user_type'] == 'admin');
                                        
                                        if ($is_current_admin || $is_any_admin): 
                                        ?>
                                            <button type="button" class="btn btn-sm btn-secondary disabled" title="Cannot delete Admin accounts or your active account">
                                                Delete
                                            </button>
                                        <?php else: ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger delete-btn" 
                                                    data-toggle="modal" 
                                                    data-target="#deleteConfirmModal"
                                                    data-userid="<?php echo htmlspecialchars($user['user_id']); ?>"
                                                    data-username="<?php echo htmlspecialchars($user['full_name']); ?>">
                                                Delete
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination Controls -->
                    <?php echo renderPagination($current_view, $current_page, $total_pages); ?>

                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Custom Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" role="dialog" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteConfirmModalLabel">Confirm Permanent Deletion</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p>You are about to **permanently delete** the user account for **<span id="modal-username" class="font-weight-bold"></span>**.</p>
        <div class="alert alert-warning">
            WARNING: Deleting this user will **permanently remove ALL** their associated test results and answers from the system. This action cannot be undone.
        </div>
        <p>Are you sure you want to proceed?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <form method="POST" id="modal-delete-form">
            <input type="hidden" name="user_id" id="modal-user-id" value="">
            <!-- IMPORTANT: Add back the current view/page information to ensure smooth redirect -->
            <input type="hidden" name="current_view" id="modal-current-view" value="<?php echo htmlspecialchars($current_view); ?>">
            <input type="hidden" name="current_page" id="modal-current-page" value="<?php echo htmlspecialchars($current_page); ?>">
            <button type="submit" name="delete_user" class="btn btn-danger">Yes, Delete Permanently</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
    // Handle modal population when a delete button is clicked
    $('.delete-btn').on('click', function() {
        // Get data attributes from the clicked button
        const userId = $(this).data('userid');
        const username = $(this).data('username');

        // Populate the modal fields
        $('#modal-username').text(username);
        $('#modal-user-id').val(userId);
    });
</script>
</body>
</html>
