<?php
// participant_dashboard.php
require_once "db_connect.php";

// ---------------------------
// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'participant') {
    header("location: login.php");
    exit;
}
// ---------------------------

$user_id = $_SESSION["user_id"];
$username = htmlspecialchars($_SESSION["username"]);

// 1. Fetch all available quizzes
$sql_quizzes = "SELECT quiz_id, title, total_questions, time_limit_minutes FROM quizzes ORDER BY quiz_id DESC";
$result_quizzes = $conn->query($sql_quizzes);
$all_quizzes = [];

if ($result_quizzes && $result_quizzes->num_rows > 0) {
    while($row = $result_quizzes->fetch_assoc()) {
        $all_quizzes[] = $row;
    }
}

// 2. Fetch completed quiz attempts for the current user
// NOTE: We are intentionally NOT filtering by contestant_number here, as a completed quiz
// is blocked for the whole account, regardless of who submitted it first.
$sql_attempts = "SELECT quiz_id FROM attempts WHERE user_id = ?";
$completed_quiz_ids = [];

if ($stmt = $conn->prepare($sql_attempts)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result_attempts = $stmt->get_result();
    
    while($row = $result_attempts->fetch_assoc()) {
        // Store quiz IDs that the user has already attempted
        $completed_quiz_ids[$row['quiz_id']] = true; 
    }
    $stmt->close();
}

// 3. Separate Quizzes into 'Available' and 'Completed' lists for display
$available_quizzes = [];
$completed_quizzes = [];

foreach ($all_quizzes as $quiz) {
    if (isset($completed_quiz_ids[$quiz['quiz_id']])) {
        $completed_quizzes[] = $quiz;
    } else {
        $available_quizzes[] = $quiz;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Participant Dashboard</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        body { background-color: #e9ecef; }
        .welcome-header { background-color: #007bff; color: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .quiz-card { border: none; transition: transform 0.2s, box-shadow 0.2s; }
        .quiz-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1); }
        .completed-card { opacity: 0.8; }
        .list-group-item i { width: 20px; text-align: center; }
    </style>
</head>
<body>
    
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container-fluid container">
            <a class="navbar-brand fw-bold" href="participant_dashboard.php"><i class="fas fa-home me-1"></i> Quiz Platform</a>
            <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <main class="container mt-5">

        <div class="welcome-header shadow-lg">
            <h1 class="display-5"><i class="fas fa-user-circle me-2"></i> Welcome, <?php echo $username; ?>!</h1>
            <p class="lead">Ready to test your knowledge? Choose a quiz below to start.</p>
        </div>

        <h2 class="mb-4 text-primary"><i class="fas fa-list-ul me-2"></i> Available Quizzes (<?php echo count($available_quizzes); ?>)</h2>
        <?php if (!empty($available_quizzes)): ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-5">
                <?php foreach ($available_quizzes as $quiz): ?>
                    <div class="col">
                        <div class="card h-100 quiz-card shadow">
                            <div class="card-header bg-success text-white fw-bold">
                                NEW QUIZ 🚀
                            </div>
                            <div class="card-body">
                                <h5 class="card-title text-success"><?php echo htmlspecialchars($quiz['title']); ?></h5>
                                <p class="card-text text-muted">Test your skills with this challenging quiz.</p>
                                <ul class="list-group list-group-flush mb-3">
                                    <li class="list-group-item"><i class="fas fa-question-circle text-primary"></i> **Questions:** <?php echo $quiz['total_questions']; ?></li>
                                    <li class="list-group-item"><i class="fas fa-clock text-danger"></i> **Time Limit:** <?php echo $quiz['time_limit_minutes']; ?> minutes</li>
                                </ul>
                            </div>
                            <div class="card-footer d-grid">
                                <form action="start_quiz.php" method="get">
                                    <input type="hidden" name="quiz_id" value="<?php echo $quiz['quiz_id']; ?>">
                                    
                                    <div class="input-group mb-3">
                                        <span class="input-group-text bg-light fw-bold"><i class="fas fa-user-tag me-1"></i> Contestant:</span>
                                        <select name="contestant_number" class="form-select" required>
                                            <option value="" disabled selected>-- Select Player --</option>
                                            <option value="1">Contestant 1</option>
                                            <option value="2">Contestant 2</option>
                                            </select>
                                    </div>

                                    <button type="submit" class="btn btn-lg btn-success w-100">
                                        <i class="fas fa-play me-1"></i> Start Quiz
                                    </button>
                                </form>
                                </div>
                            </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center mb-5" role="alert">
                <i class="fas fa-check-circle me-2"></i> Congratulations! You have completed all available quizzes.
            </div>
        <?php endif; ?>

        <hr>
        <h2 class="mt-5 mb-4 text-secondary"><i class="fas fa-history me-2"></i> Completed Quizzes (<?php echo count($completed_quizzes); ?>)</h2>
        <?php if (!empty($completed_quizzes)): ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-5">
                <?php foreach ($completed_quizzes as $quiz): ?>
                    <div class="col">
                        <div class="card h-100 quiz-card completed-card border-secondary shadow-sm">
                            <div class="card-header bg-secondary text-white fw-bold">
                                COMPLETED <i class="fas fa-award"></i>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title text-secondary"><?php echo htmlspecialchars($quiz['title']); ?></h5>
                                <p class="card-text text-muted">You have already submitted your answers for this quiz.</p>
                                <ul class="list-group list-group-flush mb-3">
                                    <li class="list-group-item"><i class="fas fa-question-circle text-primary"></i> **Questions:** <?php echo $quiz['total_questions']; ?></li>
                                    <li class="list-group-item"><i class="fas fa-clock text-danger"></i> **Time Limit:** <?php echo $quiz['time_limit_minutes']; ?> minutes</li>
                                </ul>
                            </div>
                            <div class="card-footer d-grid">
                                <a href="view_score.php?quiz_id=<?php echo $quiz['quiz_id']; ?>" class="btn btn-lg btn-outline-secondary">
                                    <i class="fas fa-poll me-1"></i> View Score
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-warning text-center" role="alert">
                You haven't completed any quizzes yet. Start one above!
            </div>
        <?php endif; ?>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>