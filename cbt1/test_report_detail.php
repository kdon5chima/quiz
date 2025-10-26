<?php
// test_report_detail.php - FINAL CORRECTED VERSION

require_once 'config.php';
require_once 'helpers.php'; // Ensure helpers are included for require_login and is_admin

// Access Control & Setup
require_login(['teacher', 'admin']);
$user_id = $_SESSION['user_id'];
$is_admin = is_admin();

$test_id = filter_input(INPUT_GET, 'test_id', FILTER_VALIDATE_INT);
$error_message = '';
$test_info = null;
$report_stats = [];
$student_results = [];

if (!$test_id) {
    $_SESSION['error_message'] = "Invalid or missing Test ID.";
    header("location: view_reports.php");
    exit;
}

try {
    // 1. Fetch Test Information and Check Ownership
    $sql_test_info = "
        SELECT test_id, title, created_by 
        FROM tests 
        WHERE test_id = :test_id
    ";
    $stmt_test_info = $pdo->prepare($sql_test_info);
    $stmt_test_info->bindParam(':test_id', $test_id, PDO::PARAM_INT);
    $stmt_test_info->execute();
    $test_info = $stmt_test_info->fetch(PDO::FETCH_ASSOC);

    if (!$test_info) {
        throw new Exception("Test not found.");
    }

    // ENFORCE OWNERSHIP (If not admin, must be the creator)
    if (!$is_admin && $test_info['created_by'] != $user_id) {
        throw new Exception("Permission denied. You can only view detailed reports for tests you created.");
    }
    
    $test_title = htmlspecialchars($test_info['title']);


    // 2. Fetch General Report Statistics (Uses correct_answers and total_questions from results)
    $sql_stats = "
        SELECT 
            COUNT(r.result_id) AS total_submissions,
            AVG(r.correct_answers) AS average_raw_score,
            MAX(r.correct_answers) AS highest_raw_score,
            MIN(r.correct_answers) AS lowest_raw_score,
            MAX(r.total_questions) AS overall_total_questions
        FROM results r
        WHERE r.test_id = :test_id AND r.is_submitted = 1
    ";
    $stmt_stats = $pdo->prepare($sql_stats);
    $stmt_stats->bindParam(':test_id', $test_id, PDO::PARAM_INT);
    $stmt_stats->execute();
    $report_stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    
    $total_submissions = $report_stats['total_submissions'] ?? 0;
    $total_questions = $report_stats['overall_total_questions'] ?? 0; // Use MAX total_questions from results
    
    // Format stats and calculate percentages
    if ($total_submissions > 0 && $total_questions > 0) {
        $report_stats['average_score'] = round(($report_stats['average_raw_score'] / $total_questions) * 100, 1);
        $report_stats['highest_score'] = round(($report_stats['highest_raw_score'] / $total_questions) * 100);
        $report_stats['lowest_score'] = round(($report_stats['lowest_raw_score'] / $total_questions) * 100);
    } else {
        $report_stats['average_score'] = $report_stats['highest_score'] = $report_stats['lowest_score'] = 'N/A';
    }


    // 3. Fetch Detailed Student Results
    // Fetching correct_answers and total_questions for in-row percentage calculation
    $sql_students = "
        SELECT 
            u.full_name, r.correct_answers, r.total_questions, r.submission_time 
        FROM results r
        JOIN users u ON r.user_id = u.user_id
        WHERE r.test_id = :test_id AND r.is_submitted = 1
        ORDER BY r.correct_answers DESC, r.submission_time ASC 
    ";
    $stmt_students = $pdo->prepare($sql_students);
    $stmt_students->bindParam(':test_id', $test_id, PDO::PARAM_INT);
    $stmt_students->execute();
    $student_results = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = $e->getMessage();
    $test_info = null; // Clear info on error
} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
    error_log("Report Detail DB Error: " . $e->getMessage());
    $test_info = null;
}

unset($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Detailed Report | <?php echo $test_title ?? 'Error'; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>📊 Detailed Report: <?php echo $test_title ?? 'Test Data Error'; ?></h2>
        <a href="view_reports.php" class="btn btn-secondary">← Back to Test List</a>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php elseif ($test_info): ?>
        
        <div class="row mb-5">
            <div class="col-md-3">
                <div class="card bg-info text-white text-center p-3 shadow-sm">
                    <h5 class="card-title"><i class="fas fa-list-alt mr-1"></i> Total Submissions</h5>
                    <p class="card-text display-4 font-weight-bold"><?php echo $total_submissions; ?></p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-primary text-white text-center p-3 shadow-sm">
                    <h5 class="card-title"><i class="fas fa-chart-line mr-1"></i> Average Score (%)</h5>
                    <p class="card-text display-4 font-weight-bold"><?php echo $report_stats['average_score']; ?></p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white text-center p-3 shadow-sm">
                    <h5 class="card-title"><i class="fas fa-trophy mr-1"></i> Highest Score (%)</h5>
                    <p class="card-text display-4 font-weight-bold"><?php echo $report_stats['highest_score']; ?></p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white text-center p-3 shadow-sm">
                    <h5 class="card-title"><i class="fas fa-frown-open mr-1"></i> Lowest Score (%)</h5>
                    <p class="card-text display-4 font-weight-bold"><?php echo $report_stats['lowest_score']; ?></p>
                </div>
            </div>
        </div>
        
        <h3 class="mt-4">Individual Student Results</h3>
        <div class="table-responsive">
            <table class="table table-striped table-hover shadow-sm">
                <thead class="thead-dark">
                    <tr>
                        <th>Student Name</th>
                        <th>Score (%)</th>
                        <th>Raw Score</th>
                        <th>Submitted On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($student_results)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No student results found for this test.</td></tr>
                    <?php else: ?>
                        <?php foreach ($student_results as $result): 
                            // Calculate percentage for the individual student
                            $student_score = $result['correct_answers'] ?? 0;
                            $student_total_q = $result['total_questions'] ?? 1; // Prevent division by zero
                            $percentage = ($student_total_q > 0) ? round(($student_score / $student_total_q) * 100) : 0;
                            $score_class = ($percentage >= 70) ? 'text-success' : (($percentage >= 50) ? 'text-warning' : 'text-danger');
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($result['full_name']); ?></td>
                            <td class="font-weight-bold <?php echo $score_class; ?>">
                                <?php echo $percentage; ?>%
                            </td>
                            <td>
                                <?php echo $student_score; ?> / <?php echo $student_total_q; ?>
                            </td>
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($result['submission_time']))); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>