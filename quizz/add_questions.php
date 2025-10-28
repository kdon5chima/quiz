
<?php
// add_questions.php - Updated for Bulky Import
require_once "db_connect.php";

// ---------------------------
// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'admin') {
    header("location: login.php");
    exit;
}
// ---------------------------

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : null;
$message = "";
$message_type = "";
$current_quiz_title = "";
$file_upload_error = false;

// Initialize form fields for sticky form
$question_text = $option_a = $option_b = $option_c = $option_d = $correct_answer = "";

// Define the upload directory
$target_dir = "images/questions/";
$image_path = null;

// Logic to get all quizzes for the dropdown
$quizzes = [];
$sql_select_quizzes = "SELECT quiz_id, title FROM quizzes ORDER BY quiz_id DESC";
$result_quizzes = $conn->query($sql_select_quizzes);

if ($result_quizzes && $result_quizzes->num_rows > 0) {
    while($row = $result_quizzes->fetch_assoc()) {
        $quizzes[] = $row;
        if ($row['quiz_id'] == $quiz_id) {
            $current_quiz_title = $row['title'];
        }
    }
} else {
    $message = "No quizzes found. Please create a quiz first.";
    $message_type = "warning";
}

// --- List Existing Questions (Runs only if a quiz is selected) ---
$existing_questions = [];
if ($quiz_id) {
    $sql_list_questions = "SELECT question_id, question_text FROM questions WHERE quiz_id = ? ORDER BY question_id DESC";
    if ($stmt_list = $conn->prepare($sql_list_questions)) {
        $stmt_list->bind_param("i", $quiz_id);
        $stmt_list->execute();
        $result_list = $stmt_list->get_result();
        while($row = $result_list->fetch_assoc()) {
            $existing_questions[] = $row;
        }
        $stmt_list->close();
    }
}
// --- End List Existing Questions ---


// ----------------------------------------------------
// --- NEW CSV IMPORT PROCESSING LOGIC ---
// ----------------------------------------------------
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_csv']) && $quiz_id){
    if (isset($_FILES["csv_file"]) && $_FILES["csv_file"]["error"] == 0) {
        $file_path = $_FILES["csv_file"]["tmp_name"];
        $file_info = pathinfo($_FILES["csv_file"]["name"]);

        if (strtolower($file_info['extension']) != 'csv') {
            $message = "Error: Invalid file format. Only CSV files are allowed.";
            $message_type = "danger";
        } elseif (($handle = fopen($file_path, "r")) !== FALSE) {
            
            // Skip header row if it exists (first row)
            fgetcsv($handle); 

            $import_count = 0;
            $failed_count = 0;
            $error_lines = [];
            $line_number = 2; // Start counting from the first data row (usually line 2)

            // Prepare the insert statement once outside the loop for efficiency
            $sql = "INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_answer, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    // Expecting 7 or 8 columns (7 basic + 1 optional image path)
                    if (count($data) >= 7) { 
                        $q_text = trim($data[0]);
                        $opt_a = trim($data[1]);
                        $opt_b = trim($data[2]);
                        $opt_c = trim($data[3]);
                        $opt_d = trim($data[4]);
                        $correct_ans = strtoupper(trim($data[5]));
                        // Use the 8th column for image path, defaulting to null if not present
                        $img_path = (count($data) >= 8 && !empty(trim($data[7]))) ? trim($data[7]) : null; 

                        if (empty($q_text) || empty($opt_a) || !in_array($correct_ans, ['A', 'B', 'C', 'D'])) {
                             $error_lines[] = "Line {$line_number}: Missing required data or invalid correct answer.";
                             $failed_count++;
                        } else {
                            $stmt->bind_param("isssssss", $quiz_id, $q_text, $opt_a, $opt_b, $opt_c, $opt_d, $correct_ans, $img_path);
                            if ($stmt->execute()) {
                                $import_count++;
                            } else {
                                $error_lines[] = "Line {$line_number}: DB Error - " . $conn->error;
                                $failed_count++;
                            }
                        }
                    } else {
                        $error_lines[] = "Line {$line_number}: Incorrect number of columns (" . count($data) . "). Expected 7 or 8.";
                        $failed_count++;
                    }
                    $line_number++;
                }
                $stmt->close();
            } else {
                 $message = "Database error: Could not prepare insert statement.";
                 $message_type = "danger";
            }
            
            fclose($handle);

            // Set final status message
            if ($import_count > 0) {
                $message = "✅ Successfully imported **{$import_count}** questions!";
                $message_type = "success";
            }
            if ($failed_count > 0) {
                $message .= "<br>❌ Failed to import {$failed_count} questions. Errors: <ul><li>" . implode("</li><li>", $error_lines) . "</li></ul>";
                $message_type = ($import_count > 0) ? "warning" : "danger";
            }
            if ($import_count == 0 && $failed_count == 0) {
                 $message = "No questions found in the CSV file (it might have been empty).";
                 $message_type = "info";
            }

            // Redirect to refresh list and show message
            header("Location: add_questions.php?quiz_id=" . $quiz_id . "&status=" . $message_type . "&msg=" . urlencode($message));
            exit;

        } else {
            $message = "Error: Could not open the uploaded file.";
            $message_type = "danger";
        }
    } else {
        $message = "Error: Please select a CSV file to upload.";
        $message_type = "danger";
    }
}
// ----------------------------------------------------
// --- END CSV IMPORT PROCESSING LOGIC ---
// ----------------------------------------------------


// Logic to process the single question form submission (kept for completeness)
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_question'])){
    // ... (Your existing logic for single question submission remains here)
    // Get and sanitize data
    $quiz_id = (int)$_POST["quiz_id"];
    $question_text = trim($_POST["question_text"]);
    $option_a = trim($_POST["option_a"]);
    $option_b = trim($_POST["option_b"]);
    $option_c = trim($_POST["option_c"]);
    $option_d = trim($_POST["option_d"]);
    $correct_answer = $_POST["correct_answer"];

    // --- FILE UPLOAD LOGIC ---
    if (isset($_FILES["question_image"]) && $_FILES["question_image"]["error"] == 0) {
        $imageFileType = strtolower(pathinfo($_FILES["question_image"]["name"], PATHINFO_EXTENSION));
        // Create a unique filename
        $unique_filename = uniqid('img_', true) . '.' . $imageFileType;
        $target_path = $target_dir . $unique_filename;

        // Validation Checks
        $check = getimagesize($_FILES["question_image"]["tmp_name"]);
        if ($check === false) {
            $message = "Error: File is not a valid image.";
            $message_type = "danger";
            $file_upload_error = true;
        } elseif ($_FILES["question_image"]["size"] > 5000000) { // 5MB limit
            $message = "Error: File size exceeds the 5MB limit.";
            $message_type = "danger";
            $file_upload_error = true;
        } elseif ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
            $message = "Error: Only JPG, JPEG, PNG & GIF files are allowed.";
            $message_type = "danger";
            $file_upload_error = true;
        } else {
            // Attempt to upload file
            if (move_uploaded_file($_FILES["question_image"]["tmp_name"], $target_path)) {
                $image_path = $target_path; // Success: Path to be saved in DB
            } else {
                $message = "Error: There was an unknown error uploading your file.";
                $message_type = "danger";
                $file_upload_error = true;
            }
        }
    }
    // --- END FILE UPLOAD LOGIC ---

    // Basic form validation
    if (empty($question_text) || empty($option_a) || empty($correct_answer)) {
        $message = "Please fill in the question text, Option A (minimum), and select the correct answer.";
        $message_type = "danger";
    } elseif (!$file_upload_error) { // Only proceed if no file upload errors occurred
        
        // Prepare insert statement for the questions table (UPDATED TO INCLUDE image_path)
        $sql = "INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_answer, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        if($stmt = $conn->prepare($sql)){
            // Bind 8 parameters: 1 int (quiz_id) and 7 strings (including image_path which can be NULL)
            $stmt->bind_param("isssssss", $quiz_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $image_path);

            if($stmt->execute()){
                // Refresh the list of questions and clear form fields for next question entry
                header("Location: add_questions.php?quiz_id=" . $quiz_id . "&status=success");
                exit;

            } else{
                $message = "Error adding question to database: " . $conn->error;
                $message_type = "danger";
            }
            $stmt->close();
        }
    }
}

// Check for status messages
if (isset($_GET['delete_status'])) {
    if ($_GET['delete_status'] == 'success') {
        $message = "🗑️ Question deleted successfully!";
        $message_type = "success";
    } elseif ($_GET['delete_status'] == 'error_id') {
        $message = "Error: Question ID missing for deletion.";
        $message_type = "danger";
    } elseif ($_GET['delete_status'] == 'error_db' || $_GET['delete_status'] == 'error_prep') {
        $message = "Error: Failed to delete question from the database.";
        $message_type = "danger";
    }
} elseif (isset($_GET['status']) && $_GET['status'] == 'success') {
    $message = "Question added successfully! Ready for the next question.";
    $message_type = "success";
} elseif (isset($_GET['status']) && isset($_GET['msg'])) {
    // For CSV import status
    $message = urldecode($_GET['msg']);
    $message_type = $_GET['status'];
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Questions</title>
    
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
        .question-list-item { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 75%; }
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
                <li class="nav-item"><a class="nav-link active" href="add_questions.php"><i class="fas fa-question-circle me-2"></i> Add Questions</a></li>
                <li class="nav-item"><a class="nav-link" href="view_results.php"><i class="fas fa-trophy me-2"></i> View Leaderboard</a></li>
                <li class="nav-item"><a class="nav-link" href="create_admin.php"><i class="fas fa-user-plus me-2"></i> Create New Admin</a></li>
                <li class="nav-item mt-3 pt-2 border-top"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <main id="main-content">
        <div class="container-fluid">
            <div class="card shadow-lg p-4">
                <div class="card-header bg-warning text-white">
                    <h2 class="mb-0"><i class="fas fa-question-circle"></i> Question Bank Management</h2>
                </div>
                <div class="card-body">
                    
                    <?php 
                        // Display messages
                        if(!empty($message)){
                            // Use html_entity_decode to handle the HTML within the URL-encoded message
                            echo '<div class="alert alert-' . $message_type . ' alert-dismissible fade show" role="alert">';
                            echo html_entity_decode($message);
                            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                            echo '</div>';
                        }
                    ?>

                    <?php if (!$quiz_id && !empty($quizzes)): ?>
                        <div class="alert alert-info text-center">
                            <p class="mb-2 lead">Select the quiz you want to manage questions for:</p>
                            <form action="add_questions.php" method="get" class="d-flex justify-content-center">
                                <select name="quiz_id" class="form-select w-50 me-2" required>
                                    <option value="">-- Select Quiz --</option>
                                    <?php foreach ($quizzes as $quiz): ?>
                                        <option value="<?php echo $quiz['quiz_id']; ?>">
                                            <?php echo htmlspecialchars($quiz['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="submit" value="Select Quiz" class="btn btn-primary">
                            </form>
                        </div>
                    
                    <?php elseif ($quiz_id && $current_quiz_title): ?>
                        
                        <h3 class="text-secondary border-bottom pb-2 mb-4">Managing: <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($current_quiz_title); ?></span></h3>

                        <?php if (!empty($existing_questions)): ?>
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Existing Questions (<span class="badge bg-secondary"><?php echo count($existing_questions); ?></span>) - <small>Use **Edit/Delete** for corrections.</small></h5>
                            </div>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($existing_questions as $q): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span class="question-list-item">
                                            Q<?php echo $q['question_id']; ?>: <?php echo htmlspecialchars($q['question_text']); ?>
                                        </span>
                                        <div class="btn-group flex-shrink-0" role="group">
                                            <a href="edit_question.php?id=<?php echo $q['question_id']; ?>" class="btn btn-sm btn-info text-white">
                                                <i class="fas fa-pencil-alt"></i> Edit
                                            </a>
                                            <a href="delete_question.php?id=<?php echo $q['question_id']; ?>&quiz_id=<?php echo $quiz_id; ?>" 
                                            onclick="return confirm('WARNING: Are you sure you want to delete Question ID <?php echo $q['question_id']; ?>? This action cannot be undone and will delete any associated image file.');" 
                                            class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <hr>
                        
                        <h4 class="mt-4"><i class="fas fa-file-csv"></i> Bulk Import Questions (CSV)</h4>
                        <div class="alert alert-primary p-3">
                            <form action="add_questions.php?quiz_id=<?php echo $quiz_id; ?>" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
                                
                                <label for="csv_file" class="form-label fw-bold">Upload CSV File</label>
                                <input type="file" name="csv_file" id="csv_file" class="form-control mb-2" accept=".csv" required>
                                <div class="form-text mb-3">
                                    **Format:** `question_text, option_a, option_b, option_c, option_d, correct_answer (A/B/C/D), [optional image_path]`
                                </div>
                                <button type="submit" name="submit_csv" class="btn btn-primary w-100">
                                    <i class="fas fa-upload me-1"></i> Import Questions from CSV
                                </button>
                            </form>
                        </div>
                        
                        <hr>
                        
                        <h4><i class="fas fa-plus-circle"></i> Add Single Question Manually</h4>

                        <form action="add_questions.php" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
                            
                            <div class="mb-4">
                                <label for="question_text" class="form-label fw-bold">Question Text (Use LaTeX for symbols/formulas)</label>
                                <textarea name="question_text" id="question_text" class="form-control" rows="3" required><?php echo htmlspecialchars($question_text); ?></textarea>
                            </div>
                            
                            <div class="mb-4 alert alert-info p-3">
                                <label for="question_image" class="form-label fw-bold"><i class="fas fa-image"></i> Optional: Upload Supporting Image/Diagram</label>
                                <input type="file" name="question_image" id="question_image" class="form-control" accept="image/*">
                                <div class="form-text">Max 5MB. Accepts JPG, PNG, GIF. Ensure the **images/questions/** folder exists.</div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Options</label>
                                    <input type="text" name="option_a" class="form-control mb-2" value="<?php echo htmlspecialchars($option_a); ?>" required placeholder="A: Option Text (Use LaTeX)">
                                    <input type="text" name="option_b" class="form-control mb-2" value="<?php echo htmlspecialchars($option_b); ?>" required placeholder="B: Option Text (Use LaTeX)">
                                    <input type="text" name="option_c" class="form-control mb-2" value="<?php echo htmlspecialchars($option_c); ?>" required placeholder="C: Option Text (Use LaTeX)">
                                    <input type="text" name="option_d" class="form-control mb-2" value="<?php echo htmlspecialchars($option_d); ?>" required placeholder="D: Option Text (Use LaTeX)">
                                </div>

                                <div class="col-md-6">
                                    <label for="correct_answer" class="form-label fw-bold">Select Correct Answer</label>
                                    <select name="correct_answer" id="correct_answer" class="form-select form-select-lg" required>
                                        <option value="" disabled <?php if(empty($correct_answer)) echo 'selected'; ?>>-- Select --</option>
                                        <option value="A" <?php if($correct_answer == 'A') echo 'selected'; ?>>A</option>
                                        <option value="B" <?php if($correct_answer == 'B') echo 'selected'; ?>>B</option>
                                        <option value="C" <?php if($correct_answer == 'C') echo 'selected'; ?>>C</option>
                                        <option value="D" <?php if($correct_answer == 'D') echo 'selected'; ?>>D</option>
                                    </select>
                                    <div class="alert alert-info mt-3">
                                        **Tip:** Use LaTeX syntax like `$\\text{H}_2\text{O}$` or `$\\alpha + \\beta$` for symbols.
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            <div class="d-grid gap-2">
                                <input type="submit" name="submit_question" value="Add Single Question" class="btn btn-warning btn-lg text-white">
                                <a href="admin_dashboard.php" class="btn btn-outline-secondary mt-2">Finished - Go to Dashboard</a>
                            </div>
                        </form>
                        
                    <?php else: ?>
                        <div class="alert alert-warning text-center">
                            <p class="mb-0">There are no quizzes available to add questions to.</p>
                            <a href='create_quiz.php' class='btn btn-success btn-sm mt-2'>Create a Quiz First</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>