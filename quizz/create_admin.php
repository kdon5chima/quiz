<?php
// create_admin.php
require_once "db_connect.php";

// ---------------------------
// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'admin') {
    header("location: login.php");
    exit;
}
// ---------------------------

$username = $password = $confirm_password = "";
$username_err = $password_err = $confirm_password_err = $message = "";
$message_type = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){

    // 1. Validate username
    if(empty(trim($_POST["username"]))){
        $username_err = "Please enter a username.";
    } else {
        // Check if username already exists
        $sql = "SELECT user_id FROM users WHERE username = ?";
        
        if($stmt = $conn->prepare($sql)){
            $stmt->bind_param("s", $param_username);
            $param_username = trim($_POST["username"]);
            
            if($stmt->execute()){
                $stmt->store_result();
                
                if($stmt->num_rows == 1){
                    $username_err = "This username is already taken.";
                } else{
                    $username = trim($_POST["username"]);
                }
            } else{
                $message = "Oops! Something went wrong. Please try again later.";
                $message_type = "danger";
            }
            $stmt->close();
        }
    }

    // 2. Validate password
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter a password.";     
    } elseif(strlen(trim($_POST["password"])) < 6){
        $password_err = "Password must have at least 6 characters.";
    } else{
        $password = trim($_POST["password"]);
    }

    // 3. Validate confirm password
    if(empty(trim($_POST["confirm_password"]))){
        $confirm_password_err = "Please confirm password.";
    } else{
        $confirm_password = trim($_POST["confirm_password"]);
        if(empty($password_err) && ($password != $confirm_password)){
            $confirm_password_err = "Passwords did not match.";
        }
    }

    // 4. Check input errors before inserting into database
    if(empty($username_err) && empty($password_err) && empty($confirm_password_err)){
        
        // Use user_type = 'admin' for insertion
        $sql = "INSERT INTO users (username, password, user_type) VALUES (?, ?, 'admin')";
         
        if($stmt = $conn->prepare($sql)){
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt->bind_param("ss", $username, $hashed_password);
            
            if($stmt->execute()){
                $message = "✅ New Admin user **" . htmlspecialchars($username) . "** created successfully!";
                $message_type = "success";
                // Clear form inputs
                $username = $password = $confirm_password = ""; 
            } else{
                $message = "Error creating admin: " . $conn->error;
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
    <title>Create New Admin</title>
    
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
                <li class="nav-item"><a class="nav-link" href="view_results.php"><i class="fas fa-trophy me-2"></i> View Leaderboard</a></li>
                <li class="nav-item"><a class="nav-link active" href="create_admin.php"><i class="fas fa-user-plus me-2"></i> **Create New Admin**</a></li>
                <li class="nav-item mt-3 pt-2 border-top"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <main id="main-content">
        <div class="container-fluid">
            <h1 class="mb-4 text-primary"><i class="fas fa-user-shield"></i> New Administrator Registration</h1>
            
            <div class="card shadow-lg p-4">
                <div class="card-body">
                    <p class="text-muted">Fill out this form to create a new administrator account with full control over the system.</p>

                    <?php 
                        // Display messages
                        if(!empty($message)){
                            echo '<div class="alert alert-' . $message_type . '" role="alert">';
                            echo $message;
                            echo '</div>';
                        }
                    ?>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label fw-bold">Username</label>
                            <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($username); ?>">
                            <div class="invalid-feedback"><?php echo $username_err; ?></div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-bold">Password</label>
                            <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                            <div class="invalid-feedback"><?php echo $password_err; ?></div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label fw-bold">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
                            <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                        </div>

                        <div class="d-grid">
                            <input type="submit" class="btn btn-primary btn-lg" value="Create Admin Account">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>