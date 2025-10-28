<?php
// admin_dashboard.php
require_once "db_connect.php";

// ---------------------------
// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'admin') {
    header("location: login.php");
    exit;
}
// ---------------------------

// --- 1. Fetch Summary Data for Dashboard Cards ---
$total_quizzes = 0;
$total_questions = 0;
$total_users = 0;

// Total Quizzes
$result = $conn->query("SELECT COUNT(quiz_id) AS total FROM quizzes");
if ($result) {
    $total_quizzes = $result->fetch_assoc()['total'];
}

// Total Questions
$result = $conn->query("SELECT COUNT(question_id) AS total FROM questions");
if ($result) {
    $total_questions = $result->fetch_assoc()['total'];
}

// Total Participants (assuming user_type != 'admin')
$result = $conn->query("SELECT COUNT(user_id) AS total FROM users WHERE user_type = 'participant'");
if ($result) {
    $total_users = $result->fetch_assoc()['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        body {
            /* Adjust body padding for fixed navbar */
            padding-top: 56px; 
            background-color: #f8f9fa; 
        }
        #sidebar {
            position: fixed;
            top: 56px; /* Below the fixed navbar */
            bottom: 0;
            left: 0;
            z-index: 1000;
            padding: 1rem;
            width: 250px;
            background-color: #343a40; /* Dark background */
        }
        #main-content {
            margin-left: 250px;
            padding: 1rem;
        }
        @media (max-width: 768px) {
            #sidebar {
                position: static;
                width: 100%;
                height: auto;
            }
            #main-content {
                margin-left: 0;
            }
        }
        .nav-link {
            color: rgba(255, 255, 255, 0.75);
        }
        .nav-link.active, .nav-link:hover {
            color: #fff;
            background-color: #007bff; /* Primary color highlight */
            border-radius: 5px;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="admin_dashboard.php">
                <i class="fas fa-tools"></i> Admin Control Panel
            </a>
            <button class="btn btn-outline-light btn-sm ms-auto me-3 d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse" aria-controls="sidebarCollapse" aria-expanded="false" aria-label="Toggle navigation">
                Menu
            </button>
            <span class="navbar-text text-white-50 d-none d-md-block me-3">
                Logged in as: <?php echo htmlspecialchars($_SESSION["username"]); ?>
            </span>
            <a href="logout.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <nav id="sidebar" class="collapse d-md-block bg-dark">
        <div class="position-sticky">
            <h5 class="text-white mt-2 mb-3 border-bottom pb-2">Navigation</h5>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="admin_dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard Overview
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="create_quiz.php">
                        <i class="fas fa-feather-alt me-2"></i> Create New Quiz
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="add_questions.php">
                        <i class="fas fa-question-circle me-2"></i> Add Questions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="view_results.php">
                        <i class="fas fa-trophy me-2"></i> View Leaderboard
                    </a>
                </li>
                <li class="nav-item">
    <a class="nav-link" href="create_admin.php">
        <i class="fas fa-user-plus me-2"></i> Create New Admin
    </a>
</li>
                <li class="nav-item mt-3 pt-2 border-top">
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <main id="main-content">
        <div class="container-fluid">
            <h1 class="mb-4 text-secondary">Dashboard Overview</h1>
            
            <div class="row g-4 mb-5">
                
                <div class="col-lg-4 col-md-6">
                    <div class="card text-white bg-info shadow h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-0"><?php echo $total_quizzes; ?></h4>
                                    <p class="card-text">Total Quizzes Created</p>
                                </div>
                                <i class="fas fa-book fa-3x"></i>
                            </div>
                        </div>
                        <a href="view_results.php" class="card-footer text-white clearfix small-box-footer text-decoration-none">
                            More Info <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="card text-white bg-warning shadow h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-0"><?php echo $total_questions; ?></h4>
                                    <p class="card-text">Total Questions in Bank</p>
                                </div>
                                <i class="fas fa-database fa-3x"></i>
                            </div>
                        </div>
                        <a href="add_questions.php" class="card-footer text-white clearfix small-box-footer text-decoration-none">
                            Manage Questions <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-4 col-md-12">
                    <div class="card text-white bg-success shadow h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-0"><?php echo $total_users; ?></h4>
                                    <p class="card-text">Total Registered Participants</p>
                                </div>
                                <i class="fas fa-users fa-3x"></i>
                            </div>
                        </div>
                        <a href="#" class="card-footer text-white clearfix small-box-footer text-decoration-none">
                            View Participants <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

            </div> <hr>

            <h2 class="mb-4 text-primary">Admin Actions</h2>
            <div class="row g-4">
                
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm text-center border-success">
                        <div class="card-body d-flex flex-column">
                            <i class="fas fa-feather-alt fa-3x text-success mb-3"></i>
                            <h5 class="card-title">Create New Quiz</h5>
                            <p class="card-text text-muted flex-grow-1">Set up the structure, title, and rules for a new competition round.</p>
                            <a href="create_quiz.php" class="btn btn-success mt-auto w-100">
                                <i class="fas fa-plus-circle"></i> Start Quiz Setup
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card h-100 shadow-sm text-center border-warning">
                        <div class="card-body d-flex flex-column">
                            <i class="fas fa-question-circle fa-3x text-warning mb-3"></i>
                            <h5 class="card-title">Add Questions</h5>
                            <p class="card-text text-muted flex-grow-1">Populate existing quizzes with questions, options, and correct answers.</p>
                            <a href="add_questions.php" class="btn btn-warning mt-auto w-100 text-white">
                                <i class="fas fa-edit"></i> Manage Questions
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card h-100 shadow-sm text-center border-info">
                        <div class="card-body d-flex flex-column">
                            <i class="fas fa-trophy fa-3x text-info mb-3"></i>
                            <h5 class="card-title">Global Leaderboard</h5>
                            <p class="card-text text-muted flex-grow-1">View participant scores and rank competitors based on time and score.</p>
                            <a href="view_results.php" class="btn btn-info mt-auto w-100 text-white">
                                <i class="fas fa-list-ol"></i> View Results
                            </a>
                        </div>
                    </div>
                </div>

            </div> </div> </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>