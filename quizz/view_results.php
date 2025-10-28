<?php
// view_results.php
require_once "db_connect.php";

// ---------------------------
// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'admin') {
    header("location: login.php");
    exit;
}
// ---------------------------

$selected_quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : null;
$quizzes = [];
$leaderboard = [];
$current_quiz_title = "";

// 1. Fetch all available quizzes for the dropdown (UNCHANGED)
$sql_select_quizzes = "SELECT quiz_id, title FROM quizzes ORDER BY quiz_id DESC";
$result_quizzes = $conn->query($sql_select_quizzes);

if ($result_quizzes && $result_quizzes->num_rows > 0) {
    while($row = $result_quizzes->fetch_assoc()) {
        $quizzes[] = $row;
        if ($row['quiz_id'] == $selected_quiz_id) {
            $current_quiz_title = $row['title'];
        }
    }
}

// 2. Fetch Leaderboard results if a quiz is selected (MODIFIED SQL)
if ($selected_quiz_id) {
    $sql_leaderboard = "
        SELECT 
            a.score, 
            a.total_correct, 
            a.total_questions, 
            a.submission_time, 
            a.contestant_number, -- <<< ADDED THIS COLUMN
            u.username
        FROM attempts a
        JOIN users u ON a.user_id = u.user_id
        WHERE a.quiz_id = ?
        ORDER BY a.score DESC, a.submission_time ASC"; 
        
    if ($stmt = $conn->prepare($sql_leaderboard)) {
        $stmt->bind_param("i", $selected_quiz_id);
        $stmt->execute();
        $result_leaderboard = $stmt->get_result();
        while($row = $result_leaderboard->fetch_assoc()) {
            $leaderboard[] = $row;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Quiz Results</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { padding-top: 56px; background-color: #f8f9fa; }
        #sidebar { position: fixed; top: 56px; bottom: 0; left: 0; z-index: 1000; padding: 1rem; width: 250px; background-color: #343a40; }
        #main-content { margin-left: 250px; padding: 1rem; }
        @media (max-width: 768px) { #sidebar { position: static; width: 100%; height: auto; } #main-content { margin-left: 0; } }
        .nav-link { color: rgba(255, 255, 255, 0.75); }
        .nav-link.active, .nav-link:hover { color: #fff; background-color: #007bff; border-radius: 5px; }
        .score-badge { font-size: 1.1em; }
        .contestant-tag { font-size: 0.9em; margin-left: 5px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="admin_dashboard.php"><i class="fas fa-tools"></i> Admin Control Panel</a>
            <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <nav id="sidebar" class="collapse d-md-block bg-dark">
        <div class="position-sticky">
            <h5 class="text-white mt-2 mb-3 border-bottom pb-2">Navigation</h5>
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="admin_dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard Overview</a></li>
                <li class="nav-item"><a class="nav-link" href="create_quiz.php"><i class="fas fa-feather-alt me-2"></i> Create New Quiz</a></li>
                <li class="nav-item"><a class="nav-link" href="add_questions.php"><i class="fas fa-question-circle me-2"></i> Add Questions</a></li>
                <li class="nav-item"><a class="nav-link active" href="view_results.php"><i class="fas fa-trophy me-2"></i> View Leaderboard</a></li>
                <li class="nav-item"><a class="nav-link" href="create_admin.php"><i class="fas fa-user-plus me-2"></i> Create New Admin</a></li>
                <li class="nav-item mt-3 pt-2 border-top"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <main id="main-content">
        <div class="container-fluid">
            <div class="card shadow-lg p-4">
                <div class="card-header bg-success text-white">
                    <h2 class="mb-0"><i class="fas fa-trophy"></i> Quiz Leaderboards</h2>
                </div>
                <div class="card-body">

                    <h4 class="mb-3 text-secondary">Select a Quiz to View Results</h4>
                    <form action="view_results.php" method="get" class="mb-5">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-6">
                                <select name="quiz_id" class="form-select form-select-lg" required>
                                    <option value="" disabled <?php if(!$selected_quiz_id) echo 'selected'; ?>>-- Choose a Quiz --</option>
                                    <?php foreach ($quizzes as $quiz): ?>
                                        <option value="<?php echo $quiz['quiz_id']; ?>" 
                                            <?php if($quiz['quiz_id'] == $selected_quiz_id) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($quiz['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-search me-1"></i> View Results
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php if ($selected_quiz_id): ?>
                        <hr>
                        <h3 class="mt-4 mb-4">Leaderboard for: <span class="badge bg-success"><?php echo htmlspecialchars($current_quiz_title); ?></span></h3>

                        <?php if (!empty($leaderboard)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th># Rank</th>
                                            <th>Participant</th>
                                            <th>Score</th>
                                            <th>Accuracy</th>
                                            <th>Submission Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $rank = 1; 
                                        foreach ($leaderboard as $attempt): 
                                            $total_questions = $attempt['total_questions'];
                                            $total_correct = $attempt['total_correct'];
                                            $accuracy = ($total_questions > 0) ? round(($total_correct / $total_questions) * 100) : 0;
                                            
                                            // Highlight first 3 ranks
                                            $row_class = '';
                                            if ($rank === 1) $row_class = 'table-warning fw-bold';
                                            elseif ($rank === 2) $row_class = 'table-light';
                                            elseif ($rank === 3) $row_class = 'table-info';

                                            // Determine Contestant Tag styling
                                            $contestant_tag = '';
                                            if ($attempt['contestant_number'] == '1') {
                                                $contestant_tag = '<span class="badge bg-info contestant-tag">Contestant 1</span>';
                                            } elseif ($attempt['contestant_number'] == '2') {
                                                $contestant_tag = '<span class="badge bg-primary contestant-tag">Contestant 2</span>';
                                            } elseif ($attempt['contestant_number'] != 'N/A') {
                                                $contestant_tag = '<span class="badge bg-secondary contestant-tag">' . htmlspecialchars($attempt['contestant_number']) . '</span>';
                                            }
                                        ?>
                                        <tr class="<?php echo $row_class; ?>">
                                            <td>
                                                <?php if ($rank <= 3): ?><i class="fas fa-star text-warning me-1"></i><?php endif; ?>
                                                **<?php echo $rank++; ?>**
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($attempt['username']); ?>
                                                <?php echo $contestant_tag; // <<< ADDED CONTESTANT TAG HERE ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-success score-badge">
                                                    <?php echo $total_correct; ?> / <?php echo $total_questions; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary score-badge">
                                                    <?php echo $accuracy; ?>%
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo date("M j, Y H:i:s", strtotime($attempt['submission_time'])); ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                No attempts have been recorded for the quiz: **<?php echo htmlspecialchars($current_quiz_title); ?>**.
                            </div>
                        <?php endif; ?>
                    
                    <?php else: ?>
                        <div class="alert alert-warning text-center">
                            Please select a quiz from the dropdown above to view the leaderboard.
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>