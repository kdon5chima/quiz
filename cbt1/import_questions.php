<?php
// import_questions.php
session_start();
require_once 'config.php'; 

// Access Control: Must be an admin
if (!isset($_SESSION["user_type"]) || $_SESSION["user_type"] !== "admin") {
    header("location: login.php");
    exit;
}

// Get Test ID
$test_id = filter_input(INPUT_GET, 'test_id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'test_id', FILTER_VALIDATE_INT);

if (!$test_id) {
    $_SESSION['error_message'] = "Test ID missing. Cannot import questions.";
    header("location: manage_tests.php");
    exit;
}

$test_title = '';
$error_message = $_SESSION['error_message'] ?? '';
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['error_message'], $_SESSION['success_message']);

// Fetch Test Title
try {
    $sql_test = "SELECT title FROM tests WHERE test_id = :test_id";
    $stmt_test = $pdo->prepare($sql_test);
    $stmt_test->bindParam(':test_id', $test_id, PDO::PARAM_INT);
    $stmt_test->execute();
    $test = $stmt_test->fetch(PDO::FETCH_ASSOC);
    $test_title = $test ? htmlspecialchars($test['title']) : 'Unknown Test';
    if (!$test) {
        $_SESSION['error_message'] = "Test ID {$test_id} not found.";
        header("location: manage_tests.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching test title: " . $e->getMessage());
    $error_message = "Error fetching test details.";
}

// --- Question Parsing and Insertion Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['question_file'])) {
    
    $file = $_FILES['question_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_message = "File upload failed with error code: " . $file['error'];
    } elseif ($file['type'] !== 'text/plain') {
        $error_message = "Invalid file type. Please upload a plain text (.txt) file.";
    } else {
        $file_content = file_get_contents($file['tmp_name']);
        // Use '---' as the block separator
        $blocks = explode('---', $file_content); 
        $imported_count = 0;
        $all_questions = [];

        foreach ($blocks as $block_index => $block) {
            $block = trim($block);
            if (empty($block)) continue;

            // Clean up lines and filter out empty ones
            $lines = array_filter(array_map('trim', explode("\n", $block)));
            $question = ['text' => null, 'options' => [], 'correct_key' => null];
            
            $expected_keys = ['Q:', 'A:', 'B:', 'C:', 'D:', 'Answer:'];
            $line_map = [];
            
            // Map the required prefixes to their content
            foreach ($lines as $line) {
                foreach ($expected_keys as $key) {
                    if (str_starts_with($line, $key)) {
                        $content = trim(substr($line, strlen($key)));
                        $line_map[$key] = $content;
                        break;
                    }
                }
            }

            // Basic validation for the block structure
            if (empty($line_map['Q:']) || empty($line_map['Answer:']) || count($line_map) < 6) {
                $error_message = "Parsing error in block " . ($block_index + 1) . ". Ensure all 6 required lines (Q:, A:, B:, C:, D:, Answer:) are present and correctly formatted.";
                break;
            }
            
            $question['text'] = $line_map['Q:'];
            $question['options'] = [
                'A' => $line_map['A:'], 
                'B' => $line_map['B:'], 
                'C' => $line_map['C:'], 
                'D' => $line_map['D:']
            ];
            $question['correct_key'] = strtoupper(trim($line_map['Answer:']));

            // Validate the correct key is one of A, B, C, or D
            if (!in_array($question['correct_key'], ['A', 'B', 'C', 'D'])) {
                $error_message = "Validation error in block " . ($block_index + 1) . ": Invalid Answer key ('{$question['correct_key']}'). Must be A, B, C, or D.";
                break;
            }
            
            // Final check that all options were extracted
            if (count(array_filter($question['options'])) !== 4) {
                 $error_message = "Parsing error in block " . ($block_index + 1) . ": One or more options (A, B, C, D) are missing content.";
                break;
            }

            $all_questions[] = $question;
        }

        // If no parsing errors, proceed with database insertion
        if (empty($error_message) && !empty($all_questions)) {
            try {
                $pdo->beginTransaction();
                
                // 1. Prepare Question Insertion
                $sql_insert_q = "INSERT INTO questions (test_id, question_text) VALUES (:test_id, :question_text)";
                $stmt_q = $pdo->prepare($sql_insert_q);
                
                // 2. Prepare Option Insertion
                $sql_insert_opt = "INSERT INTO options (question_id, option_key, option_text, is_correct) VALUES (:question_id, :option_key, :option_text, :is_correct)";
                $stmt_opt = $pdo->prepare($sql_insert_opt);

                foreach ($all_questions as $q_data) {
                    // Insert Question
                    $stmt_q->bindParam(':test_id', $test_id, PDO::PARAM_INT);
                    $stmt_q->bindParam(':question_text', $q_data['text'], PDO::PARAM_STR);
                    $stmt_q->execute();
                    $question_id = $pdo->lastInsertId();
                    
                    // Insert Options
                    foreach ($q_data['options'] as $key => $text) {
                        $is_correct = ($key === $q_data['correct_key']) ? 1 : 0;
                        
                        $stmt_opt->bindValue(':question_id', $question_id, PDO::PARAM_INT);
                        $stmt_opt->bindValue(':option_key', $key, PDO::PARAM_STR);
                        $stmt_opt->bindValue(':option_text', $text, PDO::PARAM_STR);
                        $stmt_opt->bindValue(':is_correct', $is_correct, PDO::PARAM_INT);
                        $stmt_opt->execute();
                    }
                    $imported_count++;
                }

                $pdo->commit();
                
                $_SESSION['success_message'] = "Successfully imported {$imported_count} questions!";
                // Redirect back to the question list
                header("location: add_questions.php?test_id={$test_id}");
                exit;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Database Error on Question Import: " . $e->getMessage());
                $error_message = "Database Error: Failed to save questions. Please check server logs for details.";
            }
        } elseif (empty($error_message)) {
            $error_message = "The file was empty or no valid questions were found.";
        }
    }
}

unset($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Questions | Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style> 
        body { background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background-color: #343a40; } 
        .sidebar a { color: #f8f9fa; padding: 12px 15px; display: block; border-left: 3px solid transparent; } 
        .sidebar a:hover { background-color: #495057; text-decoration: none; border-left: 3px solid #ffc107; } 
        .sidebar .active { border-left: 3px solid #ffc107; background-color: #495057; }
        .card-header { font-weight: bold; background-color: #007bff !important; color: white !important; }
        .btn-primary {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
<div class="d-flex">
    <div class="sidebar text-white p-3">
        <h4 class="mb-4">Admin Panel</h4>
        <a href="admin_dashboard.php">📊 Dashboard</a>
        <a href="register.php">👤 Register Student</a>
        <a href="manage_tests.php" class="active">📝 Manage Tests</a>
        <a href="view_results.php">📈 View Results</a>
        <a href="manage_users.php">🛠️ Manage Users</a>
        <a href="admin_profile.php">⚙️ My Profile</a>
        <a href="logout.php" class="btn btn-danger btn-sm mt-3 w-100">Logout</a>
    </div>

    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Import Questions via Text File</h2>
            <a href="add_questions.php?test_id=<?php echo htmlspecialchars($test_id); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Questions List
            </a>
        </div>
        
        <p class="lead">Importing for Test: <strong><?php echo $test_title; ?></strong></p>

        <?php if ($success_message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

        <div class="card shadow mb-4">
            <div class="card-header">
                Upload Questions File
            </div>
            <div class="card-body">
                <form action="import_questions.php?test_id=<?php echo htmlspecialchars($test_id); ?>" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="test_id" value="<?php echo htmlspecialchars($test_id); ?>">
                    
                    <div class="form-group">
                        <label for="question_file">Select Text File (.txt)</label>
                        <input type="file" name="question_file" id="question_file" class="form-control-file" accept=".txt" required>
                        <small class="form-text text-muted">Please ensure your file follows the required format below.</small>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-upload"></i> Import Questions
                    </button>
                </form>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header bg-secondary text-white">
                Required Text File Format
            </div>
            <div class="card-body">
                <p>Each question block must contain exactly 6 lines in the specified order, separated by a line containing only <code>---</code>.</p>
                <pre class="bg-light p-3 border rounded">
Q: [Your Question Text Here]
A: [Option A Text]
B: [Option B Text]
C: [Option C Text]
D: [Option D Text]
Answer: [A|B|C|D]
---
Q: [Next Question Text Here]
A: [Next Option A Text]
... (and so on)</pre>
                <p class="mt-3 text-danger"><i class="fas fa-exclamation-circle"></i> **Important:** The prefixes (Q:, A:, B:, C:, D:, Answer:) and the separator (---) are mandatory.</p>
            </div>
        </div>
        
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
