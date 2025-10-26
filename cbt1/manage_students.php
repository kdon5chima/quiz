<?php
// manage_students.php - Allows a teacher to view all registered students with pagination.

// ====================================================
// CONFIG & INITIALIZATION
// ====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
} 
require_once 'config.php';
require_once 'helpers.php'; 

// Access Control: Only teachers are allowed
require_login('teacher'); 
$user_id = (int)$_SESSION["user_id"];

// ====================================================
// PAGINATION LOGIC
// ====================================================

$records_per_page = 10; // Set to 10 as requested
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
if ($current_page < 1) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $records_per_page;

$total_records = 0;
$error_message = '';
$students = [];

try {
    // 1. Count Total Student Records
    // NOTE: Teachers typically manage ALL students in their school level, not just the ones they registered.
    $count_sql = "SELECT COUNT(user_id) FROM users WHERE user_type = 'student'";
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute();
    $total_records = $stmt_count->fetchColumn();
    
    $total_pages = ceil($total_records / $records_per_page);

    // Re-adjust page if offset is past the last record (only happens on deletion)
    if ($total_records > 0 && $offset >= $total_records) {
        $current_page = max(1, $total_pages);
        $offset = ($current_page - 1) * $records_per_page;
    }

    // 2. Fetch Paginated Student Records
    $sql_students = "
        SELECT user_id, username, full_name, class_year, reg_date AS date_registered 
        FROM users 
        WHERE user_type = 'student'
        ORDER BY class_year, full_name
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt_students = $pdo->prepare($sql_students);
    $stmt_students->bindParam(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt_students->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt_students->execute();
    $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Student Management DB Error: " . $e->getMessage());
    $error_message = "A database error occurred while fetching the student list.";
}

$pdo = null; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students | Teacher Panel</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="teacher_dashboard.php"><i class="fas fa-chalkboard-teacher mr-2"></i>Teacher Panel</a>
            <a href="logout.php" class="btn btn-danger ml-auto"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-users mr-2"></i> Manage Students</h2>
            <a href="register_student.php" class="btn btn-success"><i class="fas fa-user-plus mr-1"></i> Register New Student</a>
        </div>

        <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                Student List (<?php echo $total_records; ?> Total)
            </div>
            <div class="card-body p-0">
                <?php if (empty($students)): ?>
                    <div class="alert alert-info m-3">No students have been registered yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Name</th>
                                <th>Class/Year</th>
                                <th>Username</th>
                                <th>Date Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td><span class="badge badge-info"><?php echo htmlspecialchars($student['class_year']); ?></span></td>
                                <td><?php echo htmlspecialchars($student['username']); ?></td>
                                <td><?php echo htmlspecialchars(date("Y-m-d", strtotime($student['date_registered']))); ?></td>
                                <td>
                                    <a href="view_student_results.php?user_id=<?php echo $student['user_id']; ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-poll"></i> View Results
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Student list navigation" class="p-3">
                            <ul class="pagination justify-content-center">
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
                            <p class="text-center text-muted small">Showing <?php echo count($students); ?> of <?php echo $total_records; ?> students (Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>)</p>
                        </nav>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>
        <p class="text-center mt-3"><a href="teacher_dashboard.php">← Back to Dashboard</a></p>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
