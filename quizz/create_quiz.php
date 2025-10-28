<?php
// create_quiz.php
require_once "db_connect.php";

// ---------------------------
// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'admin') {
    header("location: login.php");
    exit;
}
// ---------------------------

$title = $description = $total_questions = $time_limit = "";
$message = "";
$message_type = ""; // To control Bootstrap alert class (success or danger)

if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Get and validate data
    $title = trim($_POST["title"]);
    $description = trim($_POST["description"]);
    $total_questions = (int) $_POST["total_questions"]; // Cast to integer
    $time_limit = (int) $_POST["time_limit_minutes"];   // Cast to integer

    // Basic validation
    if (empty($title) || $total_questions <= 0 || $time_limit <= 0) {
        $message = "Please ensure the Quiz Title is filled and both numerical fields are greater than zero.";
        $message_type = "danger";
    } else {
        // Prepare an insert statement for the quizzes table
        $sql = "INSERT INTO quizzes (title, description, total_questions, time_limit_minutes) VALUES (?, ?, ?, ?)";
        
        if($stmt = $conn->prepare($sql)){
            $stmt->bind_param("siii", $title, $description, $total_questions, $time_limit); // s=string, i=integer

            if($stmt->execute()){
                $message = "Quiz '{$title}' created successfully! Now you can add questions to it.";
                $message_type = "success";
                
                // Get the ID of the new quiz and provide a helpful link
                $new_quiz_id = $conn->insert_id;
                $follow_up_link = "<p class='mt-3'><a href='add_questions.php?quiz_id=" . $new_quiz_id . "' class='btn btn-info'><i class='fas fa-plus'></i> Start Adding Questions</a></p>";
                
                // Clear form fields after successful submission
                $title = $description = $total_questions = $time_limit = "";
            } else{
                $message = "Error creating quiz: " . $conn->error;
                $message_type = "danger";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Quiz</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="admin_dashboard.php"><i class="fas fa-tools"></i> Admin Control Panel</a>
            <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="card shadow-lg p-4">
            <div class="card-header bg-success text-white">
                <h2 class="mb-0"><i class="fas fa-feather-alt"></i> Define New Quiz Structure</h2>
            </div>
            <div class="card-body">
                
                <?php 
                    // Display success or error messages using dynamic Bootstrap alerts
                    if(!empty($message)){
                        echo '<div class="alert alert-' . $message_type . ' alert-dismissible fade show" role="alert">';
                        echo $message;
                        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                        echo '</div>';
                        
                        // Display the follow-up link only on successful creation
                        if ($message_type === 'success' && isset($follow_up_link)) {
                            echo $follow_up_link;
                        }
                    }
                ?>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label fw-bold">Quiz Title</label>
                        <input type="text" name="title" id="title" class="form-control" value="<?php echo htmlspecialchars($title ?? ''); ?>" required placeholder="e.g., General Knowledge Round 1">
                    </div>    
                    
                    <div class="mb-3">
                        <label for="description" class="form-label fw-bold">Description (Optional)</label>
                        <textarea name="description" id="description" class="form-control" rows="3" placeholder="Briefly describe the theme or rules..."><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="total_questions" class="form-label fw-bold">Total Questions to Display</label>
                            <input type="number" name="total_questions" id="total_questions" class="form-control" value="<?php echo htmlspecialchars($total_questions ?? ''); ?>" required min="1" placeholder="e.g., 20">
                            <div class="form-text">This is how many questions the user will see.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="time_limit_minutes" class="form-label fw-bold">Time Limit (in minutes)</label>
                            <input type="number" name="time_limit_minutes" id="time_limit_minutes" class="form-control" value="<?php echo htmlspecialchars($time_limit ?? ''); ?>" required min="1" placeholder="e.g., 15">
                            <div class="form-text">The maximum time allowed for the attempt.</div>
                        </div>
                    </div>
                    
                    <hr>
                    <div class="d-grid gap-2">
                        <input type="submit" value="Create Quiz Structure" class="btn btn-success btn-lg">
                        <a href="admin_dashboard.php" class="btn btn-outline-secondary">Cancel and Back to Dashboard</a>
                    </div>
                </form>

            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>