<?php
// student_dashboard.php - Displays available tests and ongoing sessions, allowing up to max_attempts per test.

session_start();
require_once 'config.php'; 

// --- AUTHENTICATION CHECK ---
if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? '') !== "student") {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$full_name = $_SESSION["full_name"] ?? 'Student';
$student_class_year = null;

$tests = [];
$ongoing_tests = [];
$error = null;

// Check for and display messages from redirects
$message = filter_input(INPUT_GET, 'message', FILTER_SANITIZE_STRING);
$redirect_error = filter_input(INPUT_GET, 'error', FILTER_SANITIZE_STRING);


try {
    // 1. Fetch Student's Class Year (Prioritizing Session for efficiency)
    if (isset($_SESSION["class_year"]) && !empty($_SESSION["class_year"])) {
        $student_class_year = $_SESSION["class_year"];
    } else {
        // Fallback: Fetch from database if session variable is missing
        $sql_user_info = "SELECT class_year, full_name FROM users WHERE user_id = :user_id";
        $stmt_user = $pdo->prepare($sql_user_info);
        $stmt_user->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt_user->execute();
        $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($user_info && !empty($user_info['class_year'])) {
            $student_class_year = (string)$user_info['class_year'];
            // Set the session variable for future requests (optional, but good practice)
            $_SESSION["class_year"] = $student_class_year; 
            $full_name = $user_info['full_name'] ?? $full_name; // Update name too if needed
        } else {
            $error = "Your class year is not set. Please contact the administrator.";
        }
    }
    
    // --- Continue processing only if the class year is set ---
    if (!$student_class_year) {
        throw new Exception("Class year not determined, cannot load tests.");
    }

    // 2. Fetch Ongoing/Unsubmitted Tests
    $sql_ongoing = "
        SELECT r.result_id, t.title 
        FROM results r
        JOIN tests t ON r.test_id = t.test_id
        WHERE r.user_id = :user_id AND r.is_submitted = 0
    ";
    $stmt_ongoing = $pdo->prepare($sql_ongoing);
    $stmt_ongoing->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_ongoing->execute();
    $ongoing_tests = $stmt_ongoing->fetchAll(PDO::FETCH_ASSOC);


    // 3. CORE LOGIC: Fetch Available Active Tests, calculating attempts used and filtering
    $sql_available = "
        SELECT 
            t.test_id, 
            t.title, 
            t.duration_minutes, 
            t.max_attempts, 
            COALESCE(r_count.submitted_attempts, 0) AS attempts_used
        FROM tests t
        LEFT JOIN (
            /* Subquery to count the student's submitted attempts for each test */
            SELECT test_id, COUNT(result_id) AS submitted_attempts
            FROM results
            WHERE user_id = :user_id_for_attempts AND is_submitted = 1
            GROUP BY test_id
        ) r_count ON t.test_id = r_count.test_id
        WHERE t.is_active = 1 
          AND t.class_year = :class_year
          /* CRITICAL FILTER: Only show tests where attempts used < max_attempts */
          AND COALESCE(r_count.submitted_attempts, 0) < t.max_attempts
        ORDER BY t.creation_date DESC
    ";
    
    $stmt_available = $pdo->prepare($sql_available);
    $stmt_available->bindParam(':user_id_for_attempts', $user_id, PDO::PARAM_INT);
    // Use the determined class year (either from session or DB)
    $stmt_available->bindParam(':class_year', $student_class_year, PDO::PARAM_STR); 
    $stmt_available->execute();
    $tests = $stmt_available->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Handle database error
    error_log("Dashboard test fetch error: " . $e->getMessage());
    $error = "Could not load tests due to a database error.";
} catch (Exception $e) {
    $error = $e->getMessage();
}

$pdo = null;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .container { max-width: 900px; margin-top: 30px; }
        .list-group-item:hover {
            background-color: #f8f9fa;
        }
        .attempts-badge {
            font-size: 0.8em;
            padding: 0.3em 0.6em;
            font-weight: 500;
        }
    </style>
</head>
<body>

<div class="container">
    <h1 class="mb-4 text-primary"><i class="fas fa-graduation-cap"></i> Welcome, <?php echo htmlspecialchars($full_name); ?>!</h1>

    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
        <p class="h5 text-muted mb-0">
            <i class="fas fa-chalkboard-teacher"></i> Class: <span class="badge badge-secondary"><?php echo htmlspecialchars($student_class_year ?? 'N/A'); ?></span>
        </p>
        <a href="view_past_results.php" class="btn btn-outline-info btn-sm">
            <i class="fas fa-list-alt"></i> View Past Results
        </a>
    </div>

    <?php if ($error || $redirect_error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error ?? $redirect_error); ?></div>
    <?php endif; ?>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if (!empty($ongoing_tests)): ?>
        <h3 class="mt-4 text-warning"><i class="fas fa-hourglass-half"></i> Continue In-Progress Tests</h3>
        <ul class="list-group mb-4 shadow">
            <?php foreach ($ongoing_tests as $ongoing): ?>
                <li class="list-group-item list-group-item-warning d-flex justify-content-between align-items-center">
                    <div>
                        <strong class="h5 text-dark"><?php echo htmlspecialchars($ongoing['title']); ?></strong>
                        <span class="badge badge-dark ml-2">IN PROGRESS</span>
                    </div>
                    <a href="take_test.php?result_id=<?php echo $ongoing['result_id']; ?>" class="btn btn-warning btn-sm font-weight-bold">
                        <i class="fas fa-play-circle"></i> Continue Attempt
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h3 class="mt-4 text-success"><i class="fas fa-book-open"></i> Available Tests</h3>
    
    <?php if (empty($tests)): ?>
        <p class="alert alert-info">There are no new active tests available for your class at this time, or you have **reached the maximum number of attempts** for all available tests.</p>
    <?php else: ?>
        <ul class="list-group shadow">
            <?php foreach ($tests as $test): ?>
                <?php 
                    $attempts_remaining = $test['max_attempts'] - $test['attempts_used'];
                    $badge_class = $attempts_remaining <= 1 ? 'badge-danger' : 'badge-info';
                ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong class="h5"><?php echo htmlspecialchars($test['title']); ?></strong>
                        <div class="text-muted small mt-1">
                            <i class="far fa-clock"></i> Duration: <?php echo $test['duration_minutes']; ?> minutes |
                            <i class="fas fa-chart-line"></i> Attempts Used: 
                            <span class="badge badge-secondary attempts-badge"><?php echo $test['attempts_used']; ?> / <?php echo $test['max_attempts']; ?></span>
                            | **Remaining:** <span class="badge <?php echo $badge_class; ?> attempts-badge"><?php echo $attempts_remaining; ?></span>
                        </div>
                    </div>
                    <a href="take_test.php?test_id=<?php echo $test['test_id']; ?>" class="btn btn-success">
                        <i class="fas fa-play"></i> Start New Test
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <a href="logout.php" class="btn btn-danger btn-block mt-5"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

</body>
</html>