<?php
// edit_question.php
require_once "db_connect.php";

// ---------------------------
// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'admin') {
    header("location: login.php");
    exit;
}
// ---------------------------

$question_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$message = "";
$message_type = "";
$question = null;

// Define the upload directory (Must match add_questions.php)
$target_dir = "images/questions/";

if (!$question_id) {
    // Redirect if no ID is given
    header("location: add_questions.php"); 
    exit;
}

// --- 1. Fetch current question data ---
$sql_fetch = "SELECT * FROM questions WHERE question_id = ?";
if ($stmt_fetch = $conn->prepare($sql_fetch)) {
    $stmt_fetch->bind_param("i", $question_id);
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();
    if ($result_fetch->num_rows == 1) {
        $question = $result_fetch->fetch_assoc();
    } else {
        $message = "Question not found.";
        $message_type = "danger";
        $question_id = null; // Prevent update if fetch failed
    }
    $stmt_fetch->close();
}

// --- 2. Process form submission for update ---
if($_SERVER["REQUEST_METHOD"] == "POST" && $question_id){
    // Get and sanitize data
    $question_text = trim($_POST["question_text"]);
    $option_a = trim($_POST["option_a"]);
    $option_b = trim($_POST["option_b"]);
    $option_c = trim($_POST["option_c"]);
    $option_d = trim($_POST["option_d"]);
    $correct_answer = $_POST["correct_answer"];
    
    // Start with the existing image path
    $current_image_path = $question['image_path']; 
    $new_image_path = $current_image_path; 

    $file_upload_error = false;
    
    // Check if the admin chose to remove the image
    if (isset($_POST['remove_image']) && $_POST['remove_image'] == 'yes') {
        if (!empty($current_image_path) && file_exists($current_image_path)) {
            unlink($current_image_path); // Delete the physical file
        }
        $new_image_path = null; // Set DB path to NULL
    } 
    
    // Check for a new image upload
    if (isset($_FILES["question_image"]) && $_FILES["question_image"]["error"] == 0) {
        
        // Validation and upload logic (Same as add_questions.php)
        $imageFileType = strtolower(pathinfo($_FILES["question_image"]["name"], PATHINFO_EXTENSION));
        $unique_filename = uniqid('img_', true) . '.' . $imageFileType;
        $target_path = $target_dir . $unique_filename;

        // Validation Checks
        $check = getimagesize($_FILES["question_image"]["tmp_name"]);
        if ($check === false) {
            $message = "Error: File is not a valid image."; $message_type = "danger"; $file_upload_error = true;
        } elseif ($_FILES["question_image"]["size"] > 5000000) { // 5MB limit
            $message = "Error: File size exceeds the 5MB limit."; $message_type = "danger"; $file_upload_error = true;
        } elseif ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
            $message = "Error: Only JPG, JPEG, PNG & GIF files are allowed."; $message_type = "danger"; $file_upload_error = true;
        } else {
            // Success in validation: If a new file is uploaded, delete the old one (if it wasn't already deleted by the checkbox)
            if (!empty($current_image_path) && file_exists($current_image_path) && $new_image_path == $current_image_path) {
                unlink($current_image_path);
            }
            // Move the new file
            if (move_uploaded_file($_FILES["question_image"]["tmp_name"], $target_path)) {
                $new_image_path = $target_path; // New path to be saved in DB
            } else {
                $message = "Error: There was an unknown error uploading your new file."; $message_type = "danger"; $file_upload_error = true;
            }
        }
    }
    // End Image Upload/Delete Logic

    // Proceed with database update if no errors
    if (!$file_upload_error) { 
        $sql_update = "UPDATE questions SET question_text=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_answer=?, image_path=? WHERE question_id=?";
        
        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("sssssssi", $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $new_image_path, $question_id);

            if($stmt_update->execute()){
                $message = "✅ Question updated successfully!";
                $message_type = "success";
                
                // Re-fetch/update form fields with latest changes
                $question['question_text'] = $question_text;
                $question['option_a'] = $option_a;
                $question['option_b'] = $option_b;
                $question['option_c'] = $option_c;
                $question['option_d'] = $option_d;
                $question['correct_answer'] = $correct_answer;
                $question['image_path'] = $new_image_path;

            } else {
                $message = "Error updating question: " . $conn->error;
                $message_type = "danger";
            }
            $stmt_update->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Question <?php echo $question_id; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* Include the sidebar styles from admin_dashboard.php */
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
                <li class="nav-item"><a class="nav-link active" href="add_questions.php"><i class="fas fa-question-circle me-2"></i> Add/Edit Questions</a></li>
                <li class="nav-item"><a class="nav-link" href="view_results.php"><i class="fas fa-trophy me-2"></i> View Leaderboard</a></li>
                <li class="nav-item"><a class="nav-link" href="create_admin.php"><i class="fas fa-user-plus me-2"></i> Create New Admin</a></li>
                <li class="nav-item mt-3 pt-2 border-top"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <main id="main-content">
        <div class="container-fluid">
            <div class="card shadow-lg p-4">
                <div class="card-header bg-info text-white">
                    <h2 class="mb-0"><i class="fas fa-edit"></i> Edit Question (ID: <?php echo $question_id; ?>)</h2>
                </div>
                <div class="card-body">
                    
                    <?php 
                        if(!empty($message)){
                            echo '<div class="alert alert-' . $message_type . ' alert-dismissible fade show" role="alert">';
                            echo $message;
                            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                            echo '</div>';
                        }
                    ?>

                    <?php if ($question): ?>
                        <p class="text-muted">You are editing a question for Quiz ID: **<?php echo $question['quiz_id']; ?>**.</p>
                        
                        <form action="edit_question.php?id=<?php echo $question_id; ?>" method="post" enctype="multipart/form-data">
                            
                            <div class="mb-4">
                                <label for="question_text" class="form-label fw-bold">Question Text</label>
                                <textarea name="question_text" id="question_text" class="form-control" rows="3" required><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                            </div>
                            
                            <div class="mb-4 alert alert-secondary p-3">
                                <h5 class="fw-bold"><i class="fas fa-image"></i> Image Management</h5>
                                
                                <?php if (!empty($question['image_path']) && file_exists($question['image_path'])): ?>
                                    <p class="mb-2">Current Image:</p>
                                    <div class="mb-3 border p-2 text-center bg-white rounded">
                                        <img src="<?php echo htmlspecialchars($question['image_path']); ?>" class="img-fluid rounded" alt="Current Question Diagram" style="max-height: 150px;">
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="remove_image" value="yes" id="remove_image">
                                        <label class="form-check-label text-danger fw-bold" for="remove_image">
                                            Check to **DELETE** Current Image
                                        </label>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No image currently associated with this question.</p>
                                <?php endif; ?>

                                <label for="question_image" class="form-label">Upload New Image (Replaces current, or adds new)</label>
                                <input type="file" name="question_image" id="question_image" class="form-control" accept="image/*">
                                <div class="form-text">Max 5MB. Leave blank to keep the current image or to make no changes.</div>
                            </div>
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Options</label>
                                    <input type="text" name="option_a" class="form-control mb-2" value="<?php echo htmlspecialchars($question['option_a']); ?>" required placeholder="A: Option Text">
                                    <input type="text" name="option_b" class="form-control mb-2" value="<?php echo htmlspecialchars($question['option_b']); ?>" required placeholder="B: Option Text">
                                    <input type="text" name="option_c" class="form-control mb-2" value="<?php echo htmlspecialchars($question['option_c']); ?>" required placeholder="C: Option Text">
                                    <input type="text" name="option_d" class="form-control mb-2" value="<?php echo htmlspecialchars($question['option_d']); ?>" required placeholder="D: Option Text">
                                </div>

                                <div class="col-md-6">
                                    <label for="correct_answer" class="form-label fw-bold">Select Correct Answer</label>
                                    <select name="correct_answer" id="correct_answer" class="form-select form-select-lg" required>
                                        <?php 
                                            $options = ['A', 'B', 'C', 'D'];
                                            foreach($options as $opt) {
                                                $selected = ($question['correct_answer'] == $opt) ? 'selected' : '';
                                                echo "<option value=\"$opt\" $selected>$opt</option>";
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            
                            <hr>
                            <div class="d-grid gap-2">
                                <input type="submit" value="Update Question" class="btn btn-info btn-lg text-white">
                                <a href="add_questions.php?quiz_id=<?php echo $question['quiz_id']; ?>" class="btn btn-outline-secondary mt-2">
                                    <i class="fas fa-chevron-left"></i> Go Back to Question Management
                                </a>
                            </div>
                        </form>
                        
                    <?php else: ?>
                        <div class="alert alert-danger text-center">
                            <p class="mb-0">Question could not be loaded or does not exist.</p>
                            <a href='add_questions.php' class='btn btn-secondary btn-sm mt-2'>Return to Question Management</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>