<?php
// index.php
require_once "db_connect.php"; 

if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    if ($_SESSION["user_type"] === 'admin') {
        header("location: admin_dashboard.php");
        exit;
    } else {
        header("location: participant_dashboard.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to the Quiz Competition!</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" 
          rel="stylesheet" 
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" 
          crossorigin="anonymous">
          
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-light">
    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="card shadow-lg p-4 p-md-5 w-100" style="max-width: 500px;">
            <div class="card-body text-center">
                <h1 class="card-title text-primary mb-3">Quiz Masters 🏆</h1>
                <p class="card-text lead mb-4">Challenge your knowledge and climb the global ranks!</p>
                
                <div class="d-grid gap-2 mb-3">
                    <a href="register.php" class="btn btn-success btn-lg shadow-sm">Register New Account</a>
                    <a href="login.php" class="btn btn-outline-primary btn-lg">Login to Compete</a>
                </div>
                
                <p class="mt-4 text-muted" style="font-size: 0.9em;">
                    *Securely log in to manage quizzes or start your attempt.
                </p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" 
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" 
            crossorigin="anonymous"></script>
</body>
</html>