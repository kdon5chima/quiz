<?php
// view_past_results.php - Displays a history of submitted test results for the currently logged-in student.

session_start(); // CRITICAL: Ensure session_start() is called if it's not in config.php
require_once 'config.php'; 

// --- AUTHENTICATION CHECK ---
// Critical security check: ensures only logged-in students can access this page
if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? '') !== "student") {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$full_name = $_SESSION["full_name"] ?? 'Student'; 

$past_results = [];
$error = null;

try {
    // Fetch all submitted test results for the current user ONLY
    $sql = "
        SELECT
            r.result_id,
            t.title AS test_title,
            r.score,
            (SELECT COUNT(*) FROM questions WHERE test_id = t.test_id) AS total_questions, /* Re-calculating total questions reliably */
            r.submission_time
        FROM results r
        JOIN tests t ON r.test_id = t.test_id
        WHERE r.user_id = :user_id AND r.is_submitted = 1 /* Filters by user_id and submission status */
        ORDER BY r.submission_time DESC
    ";

    // Use Prepared Statement for secure execution
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $past_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Log and display database error
    error_log("Student past results fetch error: " . $e->getMessage());
    $error = "Could not load your results due to a database error.";
}

unset($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Past Results - <?php echo htmlspecialchars($full_name); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #e9ecef;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container { 
            max-width: 900px; 
            margin-top: 40px; 
            margin-bottom: 40px;
        }
        .card-header-custom {
            background-color: #007bff; /* Primary color for header */
            color: white;
            padding: 20px;
            border-bottom: 5px solid #28a745; 
        }
        .result-item {
            border-left: 5px solid #007bff; /* Accent color */
            margin-bottom: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,.05);
            transition: transform 0.2s;
            border-radius: .25rem; /* Match Bootstrap card styling */
        }
        .result-item:hover {
            transform: translateY(-2px);
            border-left: 5px solid #ffc107; /* Highlight on hover */
        }
        .result-score {
            font-size: 1.5rem;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card shadow-lg">
        <div class="card-header-custom rounded-top">
            <h1 class="mb-1"><i class="fas fa-chart-bar"></i> Your Test Results History</h1>
            <p class="lead mb-0">Review of all submitted tests.</p>
        </div>
        <div class="card-body p-4">

            <?php if ($error): ?>
                <div class="alert alert-danger mb-4"><i class="fas fa-exclamation-triangle"></i> **Error:** <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <a href="student_dashboard.php" class="btn btn-outline-secondary mb-4"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

            <?php if (empty($past_results)): ?>
                <div class="alert alert-info text-center mt-4">
                    <i class="fas fa-info-circle"></i> You have not submitted any tests yet.
                </div>
            <?php else: ?>
                
                <ul class="list-group">
                    <?php foreach ($past_results as $result): 
                        // Ensure total_questions and score are handled as integers
                        $total_questions = (int)($result['total_questions'] ?? 0);
                        $score = (int)($result['score'] ?? 0);
                        
                        // Calculate percentage and determine color
                        $percentage = ($total_questions > 0) ? round(($score / $total_questions) * 100) : 0;
                        $score_color = ($percentage >= 70) ? 'success' : (($percentage >= 50) ? 'warning' : 'danger');
                    ?>
                        <li class="list-group-item result-item d-flex justify-content-between align-items-center">
                            
                            <div>
                                <strong class="h5 text-dark"><?php echo htmlspecialchars($result['test_title']); ?></strong>
                                <div class="text-muted small mt-1">
                                    <i class="far fa-calendar-alt"></i> Submitted: **<?php echo date('F j, Y, g:i a', strtotime($result['submission_time'])); ?>**
                                </div>
                            </div>
                            
                            <div class="text-right">
                                <span class="badge badge-<?php echo $score_color; ?> p-2 result-score">
                                    <?php echo htmlspecialchars($score); ?> / <?php echo htmlspecialchars($total_questions); ?>
                                </span>
                                <div class="text-<?php echo $score_color; ?> font-weight-bold mt-1"><?php echo $percentage; ?>%</div>
                                
                                <a href="view_result.php?result_id=<?php echo $result['result_id']; ?>" class="btn btn-sm btn-outline-info mt-2">
                                    <i class="fas fa-search"></i> Review
                                </a>
                            </div>

                        </li>
                    <?php endforeach; ?>
                </ul>

            <?php endif; ?>

        </div>
    </div>
</div>

</body>
</html>