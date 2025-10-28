<?php
// login.php
// Include the database connection file (which also starts the session)
require_once "db_connect.php";

$username = $password = "";
$login_err = "";

// Check if the user is already logged in, if so, redirect them
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    // Redirect based on user type (Admin or Participant)
    if ($_SESSION["user_type"] === 'admin') {
        header("location: admin_dashboard.php");
    } else {
        header("location: participant_dashboard.php");
    }
    exit;
}

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){

    // Get and sanitize input data
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    // Use a prepared statement to prevent SQL injection
    $sql = "SELECT user_id, username, password_hash, user_type FROM users WHERE username = ?";
    
    if($stmt = $conn->prepare($sql)){
        $stmt->bind_param("s", $username);
        
        if($stmt->execute()){
            $stmt->store_result();
            
            // Check if username exists
            if($stmt->num_rows == 1){                    
                $stmt->bind_result($id, $username, $hashed_password, $user_type);
                if($stmt->fetch()){
                    // Verify password
                    if(password_verify($password, $hashed_password)){
                        // Password is correct, start a new session
                        $_SESSION["loggedin"] = true;
                        $_SESSION["user_id"] = $id;
                        $_SESSION["username"] = $username;
                        $_SESSION["user_type"] = $user_type;
                        
                        // Redirect user based on their user type
                        if ($user_type === 'admin') {
                            header("location: admin_dashboard.php");
                        } else {
                            header("location: participant_dashboard.php");
                        }
                        exit;
                    } else{
                        // Password is not valid
                        $login_err = "Invalid username or password.";
                    }
                }
            } else{
                // Username doesn't exist
                $login_err = "Invalid username or password.";
            }
        } else{
            echo "Oops! Something went wrong. Please try again later.";
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
    <title>Login to Quiz Competition</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-light">
    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="card shadow-lg p-4 w-100" style="max-width: 400px;">
            <div class="card-body">
                <h2 class="card-title text-center text-primary mb-4">Login to Compete</h2>
                
                <?php 
                    // Display the error message above the form
                    if(!empty($login_err)){
                        echo '<div class="alert alert-danger" role="alert">' . $login_err . '</div>';
                    }
                ?>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" name="username" id="username" class="form-control" required>
                    </div>    
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    <div>
                        <input type="submit" value="Login" class="btn btn-primary btn-lg w-100 mb-3">
                    </div>
                </form>
                
                <p class="text-center mt-3">
                    Don't have an account? <a href="register.php" class="text-success fw-bold">Register here</a>.
                </p>
                <p class="text-center mt-2">
                    <a href="index.php" class="text-muted" style="font-size: 0.9em;">← Back to Home</a>
                </p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>