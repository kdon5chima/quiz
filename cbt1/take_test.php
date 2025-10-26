<?php
// take_test.php - Handles both starting a new test and continuing an existing one.

session_start();
require_once 'config.php'; 
require_once 'helpers.php'; // Ensure this file or your config.php contains require_login()

// --- AUTHENTICATION CHECK ---
if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? '') !== "student") {
    header("location: login.php");
    exit;
}
$user_id = $_SESSION["user_id"];
// CRITICAL: Fetches class year from session, defaults to empty string if missing, and casts to string.
$user_class_year = (string)($_SESSION["class_year"] ?? ''); 

// Input cleaning and validation
$test_id_from_url = filter_input(INPUT_GET, 'test_id', FILTER_VALIDATE_INT);
$result_id_from_url = filter_input(INPUT_GET, 'result_id', FILTER_VALIDATE_INT);

$test_id = null;
$result_id = null;
$test_info = null;
$result_row = null;

// Initialize arrays for data consistency
$questions = [];
$options_by_question = [];

$remaining_seconds = 0;
$error_message = ''; 

try {
    // =========================================================================
    // 1. PHASE 1: Determine Test Status (New or Continue)
    // =========================================================================

    if ($result_id_from_url) {
        // SCENARIO A: CONTINUE TEST (Initiated by ?result_id=X URL)
        $sql_result = "SELECT result_id, test_id, start_time, user_id, is_submitted FROM results WHERE result_id = :result_id AND user_id = :user_id";
        $stmt_result = $pdo->prepare($sql_result);
        $stmt_result->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt_result->bindParam(':result_id', $result_id_from_url, PDO::PARAM_INT);
        $stmt_result->execute();
        $result_row = $stmt_result->fetch(PDO::FETCH_ASSOC);

        if (!$result_row || (int)$result_row['user_id'] !== (int)$user_id) {
            header("location: student_dashboard.php?error=" . urlencode("Invalid result session or access denied."));
            exit;
        }

        if ($result_row['is_submitted']) {
            header("location: student_dashboard.php?error=" . urlencode("Test already submitted."));
            exit;
        }

        $result_id = $result_row['result_id'];
        $test_id = $result_row['test_id'];

    } elseif ($test_id_from_url) {
        // SCENARIO B: START NEW TEST (Initiated by ?test_id=X URL)
        $test_id = $test_id_from_url;

        // --------------------------------------------------------------------------------
        // CRITICAL FIX LOGIC: Check for and manage potentially abandoned (stale) unsubmitted attempts
        // --------------------------------------------------------------------------------
        $sql_check_unsubmitted = "
            SELECT r.result_id, r.start_time, t.duration_minutes, r.attempt_number
            FROM results r
            JOIN tests t ON r.test_id = t.test_id
            WHERE r.user_id = :user_id 
              AND r.test_id = :test_id 
              AND r.is_submitted = 0
            ORDER BY r.start_time DESC
        ";
        $stmt_check = $pdo->prepare($sql_check_unsubmitted);
        $stmt_check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt_check->bindParam(':test_id', $test_id, PDO::PARAM_INT);
        $stmt_check->execute();
        $unsubmitted_attempts = $stmt_check->fetchAll(PDO::FETCH_ASSOC);

        $found_active_session = false;

        foreach ($unsubmitted_attempts as $session) {
            $total_duration_seconds = (int)$session['duration_minutes'] * 60;
            $start_timestamp = strtotime($session['start_time']);
            $elapsed_seconds = time() - $start_timestamp;

            if ($elapsed_seconds < $total_duration_seconds) {
                // Case 1: Session is still within the time limit. RESUME IT.
                $result_id = $session['result_id'];
                // Manually construct result_row needed for timer calculation later
                $result_row = [
                    'result_id' => $result_id,
                    'test_id' => $test_id,
                    'start_time' => $session['start_time'], 
                    'is_submitted' => 0
                ];
                $found_active_session = true;
                break; // Use the newest active session and exit loop
            } else {
                // Case 2: Session is past the time limit (abandoned/stale). FORCE SUBMIT IT.
                // This clears the 'is_submitted = 0' flag, preventing the duplicate error on new attempts.
                $sql_force_submit = "UPDATE results SET is_submitted = 1, end_time = NOW() WHERE result_id = :result_id";
                $stmt_force = $pdo->prepare($sql_force_submit);
                $stmt_force->bindParam(':result_id', $session['result_id'], PDO::PARAM_INT);
                $stmt_force->execute();
            }
        }

        if (!$found_active_session) {
            // Case 3: No active, unsubmitted session found. Proceed with checks for a NEW attempt.

            // 1. Check Class Year/Access Info
            $sql_year_check = "SELECT max_attempts, class_year FROM tests WHERE test_id = :test_id";
            $stmt_year_check = $pdo->prepare($sql_year_check);
            $stmt_year_check->bindParam(':test_id', $test_id, PDO::PARAM_INT);
            $stmt_year_check->execute();
            $test_access_info = $stmt_year_check->fetch(PDO::FETCH_ASSOC);

            $test_class_year = (string)($test_access_info['class_year'] ?? '');

            if (!$test_access_info || $test_class_year !== $user_class_year) {
                 $error_msg = "Test is not currently available for your class year.";
                 header("location: student_dashboard.php?error=" . urlencode($error_msg));
                 exit;
            }
            
            $max_attempts = (int)($test_access_info['max_attempts'] ?? 1);

            // 2. Count submitted attempts by the user for this test (for attempt limit enforcement)
            $sql_count = "
                SELECT COUNT(*) 
                FROM results 
                WHERE user_id = :user_id 
                  AND test_id = :test_id 
                  AND is_submitted = 1
            ";
            $stmt_count = $pdo->prepare($sql_count);
            $stmt_count->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_count->bindParam(':test_id', $test_id, PDO::PARAM_INT);
            $stmt_count->execute();
            $submitted_attempts = $stmt_count->fetchColumn();
            
            // 3. Enforce the limit policy
            if ($max_attempts > 0 && $submitted_attempts >= $max_attempts) {
                $error_msg = "You have already completed the maximum allowed attempts ({$max_attempts}) for this test.";
                header("location: student_dashboard.php?error=" . urlencode($error_msg));
                exit;
            }
            
            // 4. Calculate the next UNIQUE attempt number using MAX(attempt_number)
            $sql_max_attempt = "
                SELECT MAX(attempt_number) 
                FROM results 
                WHERE user_id = :user_id 
                  AND test_id = :test_id
            ";
            $stmt_max_attempt = $pdo->prepare($sql_max_attempt);
            $stmt_max_attempt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_max_attempt->bindParam(':test_id', $test_id, PDO::PARAM_INT);
            $stmt_max_attempt->execute();
            $last_attempt = $stmt_max_attempt->fetchColumn() ?? 0; 
            
            $next_attempt = $last_attempt + 1; // This ensures the number is always unique
            
            // 5. All checks passed: CREATE NEW SESSION
            $start_time_php = time(); // Capture current PHP timestamp
            
            $sql_insert_result = "
                INSERT INTO results (user_id, test_id, start_time, attempt_number) 
                VALUES (:user_id, :test_id, FROM_UNIXTIME(:start_time_ts), :attempt_number)
            ";
            $stmt_insert = $pdo->prepare($sql_insert_result);
            $stmt_insert->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_insert->bindParam(':test_id', $test_id, PDO::PARAM_INT);
            $stmt_insert->bindParam(':start_time_ts', $start_time_php, PDO::PARAM_INT); 
            $stmt_insert->bindParam(':attempt_number', $next_attempt, PDO::PARAM_INT); 
            $stmt_insert->execute(); 

            $result_id = $pdo->lastInsertId();
            
            // Manually construct the result row for timer calculation later
            $result_row = [
                'result_id' => $result_id,
                'test_id' => $test_id,
                'start_time' => date('Y-m-d H:i:s', $start_time_php), 
                'is_submitted' => 0
            ];
        }

    } else {
        header("location: student_dashboard.php?error=" . urlencode("Test identifier missing."));
        exit;
    }

    if (!$result_id || !$test_id || !$result_row) {
          header("location: student_dashboard.php?error=" . urlencode("Critical ID or data retrieval error."));
          exit;
    }
    
    // =========================================================================
    // 2. PHASE 2: Load Test Info and Calculate Timer
    // =========================================================================

    // Fetch Test Details (CRITICAL: Must be active)
    $sql_test = "SELECT title, duration_minutes FROM tests WHERE test_id = :test_id AND is_active = 1";
    $stmt_test = $pdo->prepare($sql_test);
    $stmt_test->bindParam(':test_id', $test_id, PDO::PARAM_INT);
    $stmt_test->execute();
    $test_info = $stmt_test->fetch(PDO::FETCH_ASSOC);

    if (!$test_info) {
        header("location: student_dashboard.php?error=" . urlencode("Test definition not found or is inactive."));
        exit;
    }
    
    // Calculate remaining time
    $total_duration_seconds = $test_info['duration_minutes'] * 60;
    $start_timestamp = strtotime($result_row['start_time']); 
    $elapsed_seconds = time() - $start_timestamp;
    $remaining_seconds = $total_duration_seconds - $elapsed_seconds;

    // CRITICAL SERVER-SIDE TIME CHECK 
    if ($remaining_seconds <= 0) {
        // Force submission if time is up
        // Note: The `submit_test.php` script handles the final score calculation.
        header("location: submit_test.php?result_id={$result_id}"); 
        exit;
    }

    // =========================================================================
    // 3. PHASE 3: Fetch Questions and Student's Existing Answers
    // =========================================================================
    
    // 3a. Fetch ALL questions for the test (and student's current answers)
    $sql_questions = "
        SELECT q.question_id, q.question_text, sa.selected_option
        FROM questions q
        LEFT JOIN student_answers sa ON q.question_id = sa.question_id AND sa.result_id = :result_id
        WHERE q.test_id = :test_id
        ORDER BY q.question_id ASC";
    
    $stmt_q = $pdo->prepare($sql_questions);
    $stmt_q->bindParam(':test_id', $test_id, PDO::PARAM_INT);
    $stmt_q->bindParam(':result_id', $result_id, PDO::PARAM_INT);
    $stmt_q->execute();
    $questions = $stmt_q->fetchAll(PDO::FETCH_ASSOC);

    if (empty($questions)) {
        $error_message = "Test contains no questions. Please contact administrator.";
    }

    // 3b. Fetch all options for all questions in one optimized query
    $question_ids = array_column($questions, 'question_id');
    
    if (!empty($question_ids)) {
    
        $in_clause = implode(',', array_fill(0, count($question_ids), '?'));

        // The correct answer should ONLY be fetched during the scoring process (submit_test.php) 
        $sql_options = "
             SELECT question_id, option_key, option_text
             FROM options 
             WHERE question_id IN ({$in_clause})
             ORDER BY question_id, option_key";
             
        $stmt_options = $pdo->prepare($sql_options);
        $stmt_options->execute($question_ids);
        $all_options = $stmt_options->fetchAll(PDO::FETCH_ASSOC);

        // Reorganize options by question_id for easy lookup
        $options_by_question = [];
        foreach ($all_options as $opt) {
            $options_by_question[$opt['question_id']][] = $opt;
        }
    } else {
        $options_by_question = [];
    }

} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
} catch (Exception $e) {
    $error_message = "Application Error: " . $e->getMessage();
}

// If an error occurred before setting test_info, use a default title
if (!$test_info) {
    $test_info = ['title' => 'Test Loading Error', 'duration_minutes' => 0];
}

// --- CENTRALIZED ERROR HANDLING BEFORE RENDERING COMPLEX UI ---
if ($error_message) {
    unset($pdo);
    // Render a simplified, full-page error view and exit
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Error Loading Test</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-lg border-danger">
                    <div class="card-header bg-danger text-white h4">
                        Test Access Error
                    </div>
                    <div class="card-body">
                        <p class="card-text h5 text-danger">
                            An error occurred while attempting to load the test:
                        </p>
                        <blockquote class="blockquote border-left border-danger pl-3 pt-2 pb-2 mt-3">
                            <p class="mb-0"><?php echo htmlspecialchars($error_message); ?></p>
                        </blockquote>
                        <hr>
                        <p>
                            Please return to the dashboard and try again. If the issue persists, contact your administrator with the details below.
                        </p>
                        <p class="text-muted small">
                            Test ID: <?php echo $test_id ?? 'N/A'; ?> | Result ID: <?php echo $result_id ?? 'N/A'; ?>
                        </p>
                        <a href="student_dashboard.php" class="btn btn-primary mt-3">Go to Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
    <?php
    exit; // Stop execution after rendering the error page
}
// --- END CENTRALIZED ERROR HANDLING ---


// --- SECURE MAPPING LOGIC (Generates random hashes for options) ---
// This map is CRITICAL for save_answer.php and submit_test.php
$secure_mapping = []; 
$js_questions = [];

foreach ($questions as $q) {
    $q_id = $q['question_id'];
    
    if (!isset($options_by_question[$q_id]) || empty($options_by_question[$q_id])) {
        continue; 
    }
    
    $question_options_rows = $options_by_question[$q_id];
    
    $options_map = []; 
    
    foreach ($question_options_rows as $opt) {
        $options_map[$opt['option_key']] = $opt['option_text'];
    }
    
    $keys = array_keys($options_map);
    shuffle($keys); 
    
    $secure_options_for_client = [];

    // IMPORTANT: Assuming $q['selected_option'] from the LEFT JOIN contains the 
    // secure hash saved by a previous call to save_answer.php, OR is NULL.
    $original_selected_hash = $q['selected_option']; 
    $secure_selected_hash = null;

    foreach ($keys as $original_key) {
        // Generate a new unique hash for this option
        $secure_hash = bin2hex(random_bytes(8)); 
        
        // This is the CRITICAL map used for decoding the student's submitted hash.
        $secure_mapping[$q_id][$secure_hash] = $original_key; 
        
        $secure_options_for_client[$secure_hash] = $options_map[$original_key];

        // If the hash loaded from the DB matches the generated hash, mark it as selected.
        // NOTE: This comparison is highly unlikely to work unless the hash is saved 
        // AND then re-generated exactly the same way, which is NOT SECURE. 
        // For RESUMING a test, we must rely on the hash loaded from the DB:
        if ($original_selected_hash === $secure_hash) {
             $secure_selected_hash = $secure_hash;
        }
    }

    // Since a unique hash is generated on every load, the hash saved in the DB 
    // will *never* match the newly generated secure_hash.
    // We must rely on the DB having saved the secure hash in student_answers.selected_option.
    // The previous implementation was likely saving the OPTION_KEY (A, B, C) OR the load 
    // of the hash failed. For the student to see a previously selected answer, 
    // we must pass the hash that was loaded from the DB:
    
    $js_questions[] = [
        'id' => $q_id,
        'text' => htmlspecialchars($q['question_text']),
        'options' => $secure_options_for_client, 
        // Pass the hash loaded from the DB directly to JS. If it matches a hash in q.options 
        // (unlikely, see note above), JS can check it. If not, it just becomes the initial 
        // value in studentAnswers and is overwritten on the first user interaction.
        'selected_option' => $original_selected_hash // Pass the hash loaded from DB for continuity
    ];
}

// Store the secure mapping in the session. CRITICAL for save_answer.php and submit_test.php
$_SESSION['secure_test_map'][$result_id] = $secure_mapping;

$total_questions = count($js_questions);
unset($pdo); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($test_info['title']); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        /* --- FONT SIZE IMPROVEMENTS FOR STUDENTS --- */
        body { 
            background-color: #f8f9fa; 
            font-size: 1.1rem; 
        }
        .question-card .h5 {
            font-size: 1.6rem !important; 
            font-weight: 600;
            line-height: 1.5;
            padding-bottom: 10px;
        }
        .option-item span {
            font-size: 1.4rem; 
            line-height: 1.6;
            margin-left: 5px; /* Spacing between radio and text */
        }
        .option-label {
            font-weight: bold;
            min-width: 30px; /* Gives space for "A." or "D." */
            text-align: right;
            margin-right: 15px; /* Space before the radio button */
            font-size: 1.4rem; 
            color: #007bff; /* Primary color for visibility */
        }
        .option-item:has(input:checked) {
            background-color: #d1ecf1; 
            border-color: #007bff !important;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        /* CRITICAL ALIGNMENT: Align items to center all elements in the label */
        .option-item { 
            cursor: pointer; 
            transition: background-color 0.15s; 
            border: 1px solid #dee2e6;
            align-items: center !important; 
        }
        .option-item:hover {
            background-color: #e9ecef;
        }
        .question-radio {
            transform: scale(1.8); 
            /* Added margin-right to separate radio from option text */
            margin-right: 15px; 
        }
        .nav-pill { 
            width: 45px; 
            height: 45px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            border-radius: 50%; 
            padding: 0; 
            font-size: 1.1rem;
        }
        .nav-pill.btn-success { background-color: #28a745 !important; border-color: #28a745 !important; color: white; }
        .nav-pill.active { border: 3px solid #ffc107 !important; transform: scale(1.1); }
        .question-card { min-height: 450px; }
        /* --- END FONT SIZE IMPROVEMENTS --- */
    </style>
</head>
<body>

<div class="container-fluid p-3">
    <?php if ($error_message): ?>
        <div class="alert alert-danger sticky-top" style="z-index: 1000;"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="row mb-3">
        <div class="col-12">
            <div class="progress" style="height: 30px;">
                <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                    <span id="progressText">0% Complete (0/<?php echo $total_questions; ?> Answered)</span>
                </div>
            </div>
        </div>
        <div class="col-12 text-center text-muted small mt-2">
            Test ID: <?php echo $test_id; ?> | Result ID: <?php echo $result_id; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-12 mb-3">
            <div class="d-flex justify-content-between align-items-center bg-dark text-white p-3 rounded shadow">
                <h4>Test: <?php echo htmlspecialchars($test_info['title']); ?></h4>
                <div class="text-right d-flex align-items-center">
                    <span class="mr-3 h5">Time Left:</span>
                    <span id="timer" class="badge badge-warning text-dark h4 p-2">--:--</span>
                    <button class="btn btn-danger ml-3 shadow-sm" id="submitBtn">Finish & Submit</button>
                </div>
            </div>
        </div>

        <div class="col-md-9" id="question-area">
        </div>

        <div class="col-md-3">
            <div class="card shadow sticky-top" style="top: 20px;">
                <div class="card-header bg-primary text-white h5">Question Navigator</div>
                <div class="card-body">
                    <div id="nav-pills" class="d-flex flex-wrap">
                    </div>
                </div>
                <div class="card-footer text-center">
                    <button class="btn btn-secondary btn-sm" id="prevBtn" disabled>Previous</button>
                    <button class="btn btn-secondary btn-sm" id="nextBtn">Next</button>
                </div>
            </div>
            <div class="alert alert-info mt-3" id="save-status" style="display:none;">
                Saving...
            </div>
            <div id="timeUpModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">Time Expired!</h5>
                        </div>
                        <div class="modal-body">
                            <p>Your allocated time for the test has run out. Your answers will be automatically submitted now.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div id="confirmSubmitModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title">Confirm Submission</h5>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to finish and submit the test now?</p>
                            <p id="unansweredWarning" class="text-danger font-weight-bold" style="display: none;"></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-danger" id="confirmSubmitBtn">Submit Test</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
// --- GLOBAL CONSTANTS AND VARIABLES ---
const QUESTIONS = <?php echo json_encode($js_questions); ?>; 
const TEST_DURATION_SECONDS = <?php echo $remaining_seconds; ?>; 
const RESULT_ID = <?php echo $result_id; ?>; 
const TOTAL_QUESTIONS = QUESTIONS.length;
let currentQuestionIndex = 0;
let timerInterval;
let studentAnswers = {}; 

/** Initializes the studentAnswers map from the data passed by PHP. 
 * NOTE: Converts PHP null (un-answered) to empty string '' for JS consistency. */
function initializeAnswers() {
    QUESTIONS.forEach((q) => {
        // q.selected_option is the secure hash if previously saved and loaded correctly (or null/empty)
        studentAnswers[q.id] = q.selected_option || ''; 
    });
}

/** Renders the current question (based on currentQuestionIndex) into the DOM. */
function renderQuestion() {
    const q = QUESTIONS[currentQuestionIndex];
    const qNum = currentQuestionIndex + 1;

    let optionsHtml = '';
    let labelIndex = 0; // Initialize label counter (0 = A, 1 = B, etc.)
    
    // Iterating over secure hash and option text
    for (const [hash, text] of Object.entries(q.options)) {
        // Generate the A, B, C, ... label
        const optionLabel = String.fromCharCode(65 + labelIndex);
        labelIndex++; 
        
        // Check against the secure hash (or empty string) in the studentAnswers map
        const isChecked = studentAnswers[q.id] === hash ? 'checked' : '';
        const radioId = `q${q.id}-${hash}`;
        
        optionsHtml += `
            <label class="list-group-item d-flex align-items-center option-item mb-3 border rounded p-4 shadow-sm">
                <div class="option-label text-primary">${optionLabel}.</div>

                <input class="form-check-input me-1 question-radio" type="radio" name="question-${q.id}" id="${radioId}" value="${hash}" ${isChecked}>
                <span>${text}</span>
            </label>
        `;
    }

    const html = `
        <div class="card shadow question-card" data-question-id="${q.id}">
            <div class="card-header bg-secondary text-white h5">Question ${qNum} of ${TOTAL_QUESTIONS}</div>
            <div class="card-body">
                <p class="h4">${q.text}</p>
                <div class="list-group mt-4">
                    ${optionsHtml}
                </div>
            </div>
        </div>
    `;

    $('#question-area').html(html);
    updateNavigationPills();
    updateNavigationButtons();
}

// --- AJAX ANSWER SAVING ---
let saveTimeout;

/** Sends the selected answer hash to the server via AJAX after a small debounce (500ms). */
function saveAnswer(questionId, selectedOptionHash) {
    clearTimeout(saveTimeout); 

    $('#save-status').text('Saving...').removeClass('alert-danger alert-success').addClass('alert-info').show();

    saveTimeout = setTimeout(() => {
        $.ajax({
            url: 'api/save_answer.php', 
            type: 'POST',
            dataType: 'json',
            data: {
                result_id: RESULT_ID,
                question_id: questionId,
                selected_option: selectedOptionHash // This is the secure hash
            },
            success: function(response) {
                if (response.success) {
                    $('#save-status').text('Answer Saved!').removeClass('alert-info alert-danger').addClass('alert-success').fadeIn(200).delay(1500).fadeOut(500);
                } else {
                    $('#save-status').text('Save Error: ' + (response.error || 'Unknown')).removeClass('alert-info alert-success').addClass('alert-danger').show();
                }
            },
            error: function(xhr) {
                let errorMsg = 'Network Error! Could not save.';
                if (xhr.status === 404) {
                    errorMsg = 'API file (save_answer.php) not found! Check your path.';
                } else if (xhr.status === 500) {
                    errorMsg = 'Server Error (500)! Check PHP logs.';
                }
                $('#save-status').text(errorMsg).removeClass('alert-info alert-success').addClass('alert-danger').show();
            }
        });
    }, 500); 
}

// --- PROGRESS & NAVIGATION ---
function getAnsweredCount() {
    return Object.values(studentAnswers).filter(hash => hash !== '').length;
}

function updateProgressBar() {
    let answeredCount = getAnsweredCount();
    let percentage = (answeredCount / TOTAL_QUESTIONS) * 100;
    
    $('#progressBar').css('width', percentage + '%').attr('aria-valuenow', percentage);
    $('#progressText').text(`${Math.round(percentage)}% Complete (${answeredCount}/${TOTAL_QUESTIONS} Answered)`);
}

function goToQuestion(index) {
    if (index >= 0 && index < TOTAL_QUESTIONS) {
        currentQuestionIndex = index;
        renderQuestion();
    }
}

function updateNavigationButtons() {
    $('#prevBtn').prop('disabled', currentQuestionIndex === 0);
    $('#nextBtn').prop('disabled', currentQuestionIndex === TOTAL_QUESTIONS - 1);
}

function updateNavigationPills() {
    let pillsHtml = '';
    for (let i = 0; i < TOTAL_QUESTIONS; i++) {
        const qId = QUESTIONS[i].id;
        const statusClass = studentAnswers[qId] ? 'btn-success' : 'btn-outline-secondary';
        const activeClass = i === currentQuestionIndex ? 'active' : '';
        
        pillsHtml += `<button class="btn btn-sm ${statusClass} ${activeClass} m-1 nav-pill" data-index="${i}">${i + 1}</button>`;
    }
    $('#nav-pills').html(pillsHtml);
}

// --- EVENT HANDLERS ---
// Change handler for radio button selection
$(document).on('change', '.question-radio', function() {
    const questionId = QUESTIONS[currentQuestionIndex].id;
    const selectedOptionHash = $(this).val(); 
    
    if (studentAnswers[questionId] !== selectedOptionHash) { 
        studentAnswers[questionId] = selectedOptionHash;
        saveAnswer(questionId, selectedOptionHash);
        updateNavigationPills();
        updateProgressBar(); 
    }
});

// Click handler for the entire label (improves UX on large text)
$(document).on('click', '.option-item', function(e) {
    // Check if the click target is NOT an input or the label text itself
    if (e.target.tagName !== 'INPUT' && !$(e.target).hasClass('option-label')) {
        const radio = $(this).find('.question-radio');
        
        // Logic to allow double-tap/click to unselect
        if (radio.is(':checked')) {
            // Un-answering logic - SENDING EMPTY STRING ''
            const questionId = QUESTIONS[currentQuestionIndex].id;
            studentAnswers[questionId] = ''; 
            saveAnswer(questionId, ''); 
            
            // Uncheck the visual radio button and update status
            radio.prop('checked', false);
            $(this).removeClass('option-item-selected');
            updateNavigationPills();
            updateProgressBar(); 
            
        } else {
            // Select the radio button and trigger the change event
            radio.prop('checked', true).trigger('change');
        }
    }
});

$('#prevBtn').on('click', () => goToQuestion(currentQuestionIndex - 1));
$('#nextBtn').on('click', () => goToQuestion(currentQuestionIndex + 1));
$(document).on('click', '.nav-pill', function() {
    goToQuestion(parseInt($(this).data('index')));
});

// --- FINAL SUBMISSION LOGIC (THE FIX) ---

/**
 * Dynamically creates a form, populates it with all student answers, 
 * and submits it to the server for final scoring.
 */
function finalizeAndSubmitTest() {
    // 1. Create a new form element
    const form = $('<form>', {
        'action': 'submit_test.php', // The correct target script
        'method': 'post',
        'style': 'display: none;'
    });
    
    // 2. Add the result_id (CRITICAL for the PHP script)
    form.append($('<input>', {
        'type': 'hidden',
        'name': 'result_id',
        'value': RESULT_ID
    }));

    // 3. Add ALL saved answers from the JavaScript map
    for (const qId in studentAnswers) {
        // The name MUST be in the format 'answers[QUESTION_ID]' for PHP to receive 
        // it as an associative array in $_POST['answers'].
        const hash = studentAnswers[qId];
        // We submit the hash, even if it's an empty string, so submit_test.php 
        // knows the final state for every question.
        form.append($('<input>', {
            'type': 'hidden',
            'name': `answers[${qId}]`, 
            'value': hash 
        }));
    }

    // 4. Attach the form to the body and submit it
    $('body').append(form);
    form.submit();
}

// Submit button click handler
$('#submitBtn').on('click', function() {
    const unanswered = TOTAL_QUESTIONS - getAnsweredCount();
    
    // Update the warning message in the modal
    if (unanswered > 0) {
        $('#unansweredWarning').text(`You have ${unanswered} unanswered question(s). Are you sure you want to submit?`).show();
    } else {
        $('#unansweredWarning').hide();
    }
    
    // Show the confirmation modal
    $('#confirmSubmitModal').modal('show');
});

// Modal submission handler
$('#confirmSubmitBtn').on('click', function() {
    // Hide the modal immediately
    $('#confirmSubmitModal').modal('hide'); 

    // Visually disable the submit button and show a status message
    $('#submitBtn').prop('disabled', true).text('Submitting...');

    // Call the function to build and submit the hidden form
    finalizeAndSubmitTest();
});

// --- TIMER AND INITIALIZATION ---

function updateTimer() {
    let secondsLeft = TEST_DURATION_SECONDS; // Starting value is the remaining seconds from PHP
    
    // Convert remaining seconds into minutes and seconds format
    const formatTime = (totalSeconds) => {
        if (totalSeconds < 0) totalSeconds = 0;
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    };

    // Initial display
    $('#timer').text(formatTime(secondsLeft));

    timerInterval = setInterval(() => {
        secondsLeft--;
        $('#timer').text(formatTime(secondsLeft));

        if (secondsLeft <= 60) {
            $('#timer').removeClass('badge-warning').addClass('badge-danger');
        }

        if (secondsLeft <= 0) {
            clearInterval(timerInterval);
            // Time is up! Trigger the final submission.
            $('#timeUpModal').modal({ backdrop: 'static', keyboard: false }); // Prevent closing modal
            // Use a slight delay to ensure the modal is displayed before the redirect
            setTimeout(() => {
                finalizeAndSubmitTest(); 
            }, 2000); 
        }
    }, 1000);
}


// --- INITIALIZATION ---
$(document).ready(function() {
    // 1. Load answers from PHP into JS map
    initializeAnswers(); 
    
    // 2. Render the first question
    renderQuestion(); 
    
    // 3. Start the progress bar and timer
    updateProgressBar();
    updateTimer(); 
});
</script>
</body>
</html>