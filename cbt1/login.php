<?php
// login.php - Handles user authentication for all user types (admin, student, teacher).
// SECURITY: Uses PDO Prepared Statements and password_verify() for robust protection.

// Load Configuration (which handles session_start() and $pdo)
require_once 'config.php'; 

$username = $password = "";
$username_err = $password_err = $login_err = ""; 

// 1. CHECK IF ALREADY LOGGED IN
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    
    if (isset($_SESSION["user_type"])) {
        $type = $_SESSION["user_type"];
        header("location: {$type}_dashboard.php");
    } else {
        header("location: logout.php"); 
    }
    exit;
}

// 2. PROCESS LOGIN FORM SUBMISSION
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $input_username = trim($_POST["username"] ?? '');
    $input_password = trim($_POST["password"] ?? '');

    if (empty($input_username)) { $username_err = "Please enter username."; } else { $username = $input_username; }
    if (empty($input_password)) { $password_err = "Please enter your password."; } else { $password = $input_password; }
    
    if (empty($username_err) && empty($password_err)) {
        
        $sql = "SELECT user_id, password, user_type, full_name, class_year, username FROM users WHERE username = :username";
        
        if (isset($pdo) && $stmt = $pdo->prepare($sql)) {
            $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);
            $param_username = $username;
            
            if ($stmt->execute()) {
                if ($stmt->rowCount() == 1) {
                    $row = $stmt->fetch();
                    $hashed_password = $row['password']; 
                    
                    if (password_verify($password, $hashed_password)) {
                        
                        // Critical Security: Session regeneration
                        session_regenerate_id(true); 
                        $_SESSION["loggedin"] = true;
                        $_SESSION["user_id"] = $row["user_id"];
                        $_SESSION["username"] = $row["username"];
                        $_SESSION["user_type"] = $row["user_type"]; 
                        $_SESSION["full_name"] = $row["full_name"];
                        $_SESSION["class_id"] = $row["class_year"]; 

                        $type = $row["user_type"];
                        header("location: {$type}_dashboard.php");
                        exit;

                    } else {
                        $login_err = "Invalid username or password."; 
                    }
                } else {
                    $login_err = "Invalid username or password."; 
                }
            } else {
                $login_err = "A database error occurred during login verification.";
            }
        } else {
            $login_err = "System Error: Database connection object missing.";
        }
    }
}

$pdo = null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CBT Login - Professional</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        /* BASE LAYOUT STYLES */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
        }
        
        /* HEADER STYLES */
        .cbt-header {
            background-color: #a80909f3;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: fixed; /* Ensure header stays fixed */
            top: 0;
            width: 100%;
            z-index: 1030;
        }
        .cbt-header .navbar-brand {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
        }
        /* Style for the Logo Image if needed (e.g., max-height) 
        .cbt-header .navbar-brand img {
            max-height: 30px; 
            width: auto;
        } 
        */

        /* MAIN CONTENT AND LAYOUT STYLES */
        .content-main {
            flex-grow: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px 15px;
            padding-top: 80px; /* Space for the fixed header */
        }
        .layout-container {
            max-width: 1000px;
            width: 90%;
            background: #d3c9c9ff;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            padding: 40px;
        }
        .login-form-area {
            padding: 20px;
        }
        
        /* INSTRUCTION PANEL STYLES */
        .instruction-panel {
            background-color: #e9f5ff; 
            border-right: 20px solid #810a0ae1; 
            padding: 20px;
            border-radius: 8px;
            min-height: 100%; 
        }
        .instruction-panel h4 {
            color: #007bff; 
            border-bottom: 1px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 15px;
        }
        .instruction-panel ul {
            list-style: none;
            padding-left: 0;
        }
        .instruction-panel ul li {
            margin-bottom: 10px;
            position: relative;
            padding-left: 20px;
            font-size: 1.20rem; 
        }
        .instruction-panel ul li::before {
            content: '✓';
            color: #28a745;
            font-weight: bold;
            position: absolute;
            left: 0;
            top: 0;
        }

        /* Responsive adjustment: Stack columns on small screens */
        @media (max-width: 968px) {
            .layout-container {
                padding: 20px;
            }
            .instruction-panel {
                margin-bottom: 30px; 
                border-right: none;
                border-bottom: 3px solid #007bff; 
            }
        }
        
        /* FOOTER STYLES */
        .cbt-footer {
            background-color: #343a40; 
            color: #f8f9fa;
            padding: 15px 0;
            text-align: center;
            font-size: 0.85rem;
        }
        .cbt-footer a {
            color: #adb5bd;
            text-decoration: none;
            margin: 0 10px;
        }
        .cbt-footer a:hover {
            color: white;
        }
    </style>
</head>
<body>
    
    <header class="cbt-header">
        <nav class="navbar navbar-expand-lg navbar-dark container">
            <a class="navbar-brand" href="#">
                <img src="logo.png" alt="CBT System Logo" height="80" class="me-2"> 
                Unique Heights Junior and Senior High School <span style="font-weight: 300;"></span>
            </a>
            
            <div class="ms-auto">
                
            </div>
        </nav>
    </header>

    <div class="content-main">
        <div class="layout-container">
            <div class="row g-4">
                
                <div class="col-md-6 d-flex align-items-stretch">
                    <div class="instruction-panel w-100">
                        <h4><span class="me-2">ⓘ</span> CBT Exam Instructions</h4>
                        <p class="text-muted">Review these critical guidelines before logging in to start your test:</p>
                        <ul>
                            <li>**Login Credentials:** Use your assigned **Username** and **Password** carefully. They are case-sensitive.</li>
                            <li>**Time Limit:** A visible **Timer** will be displayed. The test automatically submits when the time expires.</li>
                            <li>**Navigation:** You can move freely between questions. Ensure **all** questions are attempted before final submission.</li>
                            <li>**Do NOT:** Use the browser's **Refresh** or **Back** button during the test. This may terminate your exam.</li>
                            <li>**Materials:** No phones, textbooks, or personal notes are allowed. Only use materials approved by the **Invigilator**.</li>
                            <li>**Submission:** Click **'End Test'** once and confirm to finish. You cannot return after submission.</li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-md-6 login-form-area">
                    <h2 class="text-primary mb-3">Login to CBT System</h2>
                    <p class="text-muted">Securely access your system account using your credentials.</p>
                    
                    <?php if (!empty($login_err)): ?>
                        <div class="alert alert-danger text-center mt-3 mb-4"><?php echo htmlspecialchars($login_err); ?></div>
                    <?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="form-group mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" name="username" id="username" class="form-control <?php echo (!empty($username_err) || !empty($login_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($username); ?>" autofocus>
                            <span class="invalid-feedback"><?php echo htmlspecialchars($username_err); ?></span>
                        </div> 
                        <div class="form-group mb-4">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err) || !empty($login_err)) ? 'is-invalid' : ''; ?>">
                            <span class="invalid-feedback"><?php echo htmlspecialchars($password_err); ?></span>
                        </div>
                        
                        <div class="form-group">
                            <input type="submit" class="btn btn-primary btn-lg w-100" value="Login">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="cbt-footer">
        <div class="container">
            &copy; <?php echo date("Y"); ?> CBT <span style="font-weight: bold;">System</span>. All Rights Reserved.
            <div class="mt-1">
                <a href="#">Privacy Policy</a> |
                <a href="#">Terms of Use</a> |
                <a href="#">Contact Support</a>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>