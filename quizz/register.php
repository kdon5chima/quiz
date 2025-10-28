<?php
// register.php
// Include the database connection file
require_once "db_connect.php";

$username = $email = $password = "";
$message = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){

    // Get and sanitize input data
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    // 1. Prepare an insert statement using a prepared statement for security
    $sql = "INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)";
    
    if($stmt = $conn->prepare($sql)){
        // 2. Bind variables to the prepared statement as parameters
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt->bind_param("sss", $username, $email, $hashed_password); // "sss" for three strings

        // 3. Attempt to execute the prepared statement
        if($stmt->execute()){
            // Registration successful, set success message and redirect to login
            $message = "Registration successful! You can now log in.";
            // Redirect after a slight delay to show the success message (or just go straight to login)
            header("location: login.php?registration=success");
            exit;
        } else{
            // Check for duplicate entry error (e.g., username or email already exists)
            if ($conn->errno == 1062) {
                $message = "Error: Username or Email already taken. Please choose another.";
            } else {
                $message = "Oops! Something went wrong. Please try again later.";
            }
        }

        // 4. Close statement
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register for Quiz Competition</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-light">
    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="card shadow-lg p-4 w-100" style="max-width: 450px;">
            <div class="card-body">
                <h2 class="card-title text-center text-success mb-4">Create Your Account</h2>
                
                <?php 
                    // Display success or error messages using Bootstrap alerts
                    if(!empty($message)){
                        $alert_class = ($conn->errno == 1062) ? 'alert-danger' : 'alert-success';
                        echo '<div class="alert ' . $alert_class . '" role="alert">' . $message . '</div>';
                    }
                ?>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" name="username" id="username" class="form-control" value="<?php echo htmlspecialchars($username); ?>" required>
                    </div>    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <input type="submit" value="Register" class="btn btn-success btn-lg">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
                
                <p class="text-center mt-3">
                    Already have an account? <a href="login.php" class="text-primary fw-bold">Login here</a>.
                </p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>