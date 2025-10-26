<?php
// admin_view_all_results.php - Allows the administrator to view all submitted test results across all students.

session_start();
require_once 'config.php'; 

// --- AUTHENTICATION CHECK ---
// Ensure session safety and correct user role (admin)
if (!isset($_SESSION["user_id"]) || ($_SESSION["user_role"] ?? '') !== "admin") {
    header("location: login.php");
    exit;
}

$all_results = [];
$error = null;

try {
    // 1. Fetch ALL submitted test results, joining student and test information
    $sql = "
        SELECT
            r.result_id,
            t.title AS test_title,
            u.full_name AS student_name,
            u.class_year,
            r.score,
            r.total_questions,
            r.submission_time
        FROM results r
        JOIN tests t ON r.test_id = t.test_id
        JOIN users u ON r.user_id = u.user_id
        WHERE r.is_submitted = 1 /* Only display submitted (completed) tests */
        ORDER BY r.submission_time DESC
    ";

    // Use Prepared Statement (no user input, but good practice)
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $all_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Log and display database error
    error_log("Admin results fetch error: " . $e->getMessage());
    $error = "Could not load results due to a database error.";
}

unset($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin: All Student Results</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.22/css/jquery.dataTables.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container { 
            max-width: 1200px; 
            margin-top: 40px; 
            margin-bottom: 40px;
        }
        .card-header-custom {
            background-color: #007bff;
            color: white;
            padding: 20px;
            border-bottom: 5px solid #28a745; 
        }
        /* Custom styling for DataTables */
        table.dataTable thead th {
            background-color: #e9ecef;
            border-bottom: 2px solid #ced4da;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card shadow-lg">
        <div class="card-header-custom rounded-top">
            <h1 class="mb-1"><i class="fas fa-database"></i> All Student Test Results</h1>
            <p class="lead mb-0">Comprehensive overview of all submitted tests across the school.</p>
        </div>
        <div class="card-body p-4">

            <?php if ($error): ?>
                <div class="alert alert-danger mb-4"><i class="fas fa-exclamation-triangle"></i> **Error:** <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <a href="admin_dashboard.php" class="btn btn-outline-secondary mb-4"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

            <?php if (empty($all_results)): ?>
                <div class="alert alert-info text-center mt-4">
                    <i class="fas fa-info-circle"></i> No tests have been submitted by any student yet.
                </div>
            <?php else: ?>
                
                <div class="table-responsive">
                    <table id="resultsTable" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr>
                                <th>Test Title</th>
                                <th>Student Name</th>
                                <th>Class Year</th>
                                <th>Score</th>
                                <th>Submitted On</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_results as $result): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($result['test_title']); ?></td>
                                    <td><?php echo htmlspecialchars($result['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($result['class_year']); ?></td>
                                    <td>
                                        <span class="font-weight-bold text-success">
                                            <?php echo htmlspecialchars($result['score']); ?> / <?php echo htmlspecialchars($result['total_questions']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($result['submission_time'])); ?></td>
                                    <td>
                                        <a href="admin_view_result_details.php?result_id=<?php echo $result['result_id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-search"></i> Details
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

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.js"></script>
<script>
    $(document).ready( function () {
        // Initialize DataTables for powerful filtering and sorting
        $('#resultsTable').DataTable({
            "order": [[ 4, "desc" ]] // Default sort by Submitted On (column 4) descending
        });
    } );
</script>

</body>
</html>