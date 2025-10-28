<?php
// ==========================================================
// 1. Force Error Reporting
error_reporting(E_ALL); 
ini_set('display_errors', 1);
// ==========================================================

// NOTE: db_connect.php MUST include session_start();
require_once "db_connect.php"; 

// --- DEBUG CHECKPOINT 1 ---
if (!isset($conn)) {
    die("FATAL ERROR: db_connect.php did not successfully create the \$conn database object.");
}

// ---------------------------
// Security Check: Ensure user is logged in as a participant.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'participant') {
    header("location: login.php");
    exit;
}
if ($_SESSION["user_type"] === 'admin') {
    header("location: admin_dashboard.php"); 
    exit;
}
// ---------------------------

$user_id = $_SESSION["user_id"];
$quiz_id_initial = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : null;

// --- Helper Function for Clean HTML Generation ---
/**
 * Renders the question options HTML for the current question.
 * @param array $question The question data array.
 * @param string $selected_option The currently selected option ('A', 'B', etc.).
 * @return string HTML for options.
 */
function render_question_options($question, $selected_option) {
    $options = [
        'A' => $question['option_a'],
        'B' => $question['option_b'],
        'C' => $question['option_c'],
        'D' => $question['option_d']
    ];
    
    $html = '';
    foreach ($options as $key => $value) {
        // Only display options with content
        if (!empty($value)) {
            $is_checked = ($selected_option === $key);
            $checked_attr = $is_checked ? 'checked' : '';

            // Using the label wrapper and the adjacent sibling div (.option-label) for styling
            $html .= '<label class="d-block">';
            $html .= '<input type="radio" name="current_answer" value="' . $key . '" ' . $checked_attr . '>';
            $html .= '<div class="option-label">';
            $html .= '<span class="fw-medium">';
            $html .= '<span class="badge bg-light text-primary me-3 border border-primary">' . $key . '</span>';
            $html .= htmlspecialchars($value);
            $html .= '</span>';
            $html .= '</div>';
            $html .= '</label>';
        }
    }
    return $html;
}


// --- STEP 1: INITIALIZE OR RESTORE QUIZ SESSION STATE ---

// If no current quiz is set, or a different quiz ID is requested, initialize a new state.
$is_new_quiz_requested = (isset($_SESSION['current_quiz']['quiz_id']) && $quiz_id_initial && $_SESSION['current_quiz']['quiz_id'] != $quiz_id_initial);
$is_session_empty = !isset($_SESSION['current_quiz']);

if ($is_session_empty || $is_new_quiz_requested) {
    
    $quiz_id = $quiz_id_initial;

    if (!$quiz_id) {
        // No quiz ID provided, redirect to dashboard.
        header("location: participant_dashboard.php");
        exit;
    }

    // Capture Contestant Number on initial load from GET parameter
    $contestant_number_from_get = isset($_GET['contestant_number']) ? trim($_GET['contestant_number']) : 'N/A';
    
    // Attempt to resume or create a new attempt record in the database
    $attempt_id = null;
    $sql_check_attempts = "SELECT attempt_id FROM attempts WHERE user_id = ? AND quiz_id = ? AND end_time IS NULL";
    
    if ($stmt = $conn->prepare($sql_check_attempts)) {
        $stmt->bind_param("ii", $user_id, $quiz_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Resume existing attempt
            $row = $result->fetch_assoc();
            $attempt_id = $row['attempt_id'];
        } else {
            // Create a new attempt record
            $sql_insert_attempt = "INSERT INTO attempts (user_id, quiz_id, start_time, contestant_number) VALUES (?, ?, NOW(), ?)";
            if ($stmt_insert = $conn->prepare($sql_insert_attempt)) {
                $stmt_insert->bind_param("iis", $user_id, $quiz_id, $contestant_number_from_get);
                $stmt_insert->execute();
                $attempt_id = $conn->insert_id;
                $stmt_insert->close();
            }
        }
        $stmt->close();
    }

    // Fetch all questions and quiz title
    $questions = [];
    $quiz_title = "Quiz";
    $sql_questions = "
        SELECT 
            q.question_id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, z.title
        FROM questions q
        JOIN quizzes z ON q.quiz_id = z.quiz_id
        WHERE q.quiz_id = ? ORDER BY q.question_id ASC"; // Added ORDER BY for consistent question order
    
    if ($stmt = $conn->prepare($sql_questions)) {
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $questions[] = $row;
            // Title will be overwritten multiple times, but that's okay.
            $quiz_title = $row['title']; 
        }
        $stmt->close();
    }

    if (empty($questions) || $attempt_id === null) {
        die("Quiz not found, no questions, or attempt record failed to create.");
    }

    // Initialize session state
    $_SESSION['current_quiz'] = [
        'quiz_id' => $quiz_id,
        'attempt_id' => $attempt_id,
        'title' => $quiz_title,
        'questions' => $questions,
        // Initialize user answers array, setting default to null/unanswered
        'user_answers' => array_fill_keys(array_column($questions, 'question_id'), null), 
        'current_question_index' => 0,
        'total_questions' => count($questions),
        'contestant_number' => htmlspecialchars($contestant_number_from_get) 
    ];

} 

// Extract quiz data (use references for answer and index tracking)
$current_quiz = &$_SESSION['current_quiz'];
$quiz_id = $current_quiz['quiz_id'];
$attempt_id = $current_quiz['attempt_id'];
$questions = $current_quiz['questions'];
$quiz_title = $current_quiz['title'];
$total_questions = $current_quiz['total_questions'];
$user_answers = &$current_quiz['user_answers'];
$current_index = &$current_quiz['current_question_index'];


// --- STEP 2: HANDLE USER SUBMISSION (NAVIGATION/ANSWER SAVE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Get the question ID of the question the user was just viewing (before navigation)
    // NOTE: This logic assumes the POST is always saving the answer for the question at the $current_index
    // before the index potentially changes.
    $current_question_id = $questions[$current_index]['question_id'];
    
    // Save the answer for the current question. Use empty string if no radio is selected.
    $submitted_answer = isset($_POST['current_answer']) ? $_POST['current_answer'] : ''; 
    $user_answers[$current_question_id] = $submitted_answer;

    // Update contestant number from hidden input (in case it was modified in the URL initially)
    if (isset($_POST['contestant_number'])) {
           $current_quiz['contestant_number'] = htmlspecialchars(trim($_POST['contestant_number']));
    }

    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'next' && $current_index < $total_questions - 1) {
            $current_index++;
        } elseif ($action === 'prev' && $current_index > 0) {
            $current_index--;
        } elseif ($action === 'jump' && isset($_POST['jump_index'])) {
            // Jump navigation only changes the index, as the current answer is already saved above.
            $jump_index = (int)$_POST['jump_index'];
            if ($jump_index >= 0 && $jump_index < $total_questions) {
                $current_index = $jump_index;
            }
        } elseif ($action === 'finish' || $action === 'timeout') {
            // Final submission action
            $_SESSION['final_submission'] = [
                'attempt_id' => $attempt_id,
                'quiz_id' => $quiz_id,
                'contestant_number' => $current_quiz['contestant_number'],
                'answers' => $user_answers // Contains all collected answers
            ];
            
            unset($_SESSION['current_quiz']); // Clear the ongoing quiz session data
            
            header("location: process_submission.php");
            exit;
        }
    }
    // Redirect to prevent form resubmission on refresh (POST/Redirect/GET pattern)
    header("Location: start_quiz.php?quiz_id=" . $quiz_id);
    exit;
}

// --- STEP 3: PREPARE DATA FOR CURRENT QUESTION DISPLAY ---
$current_question = $questions[$current_index];
$question_num = $current_index + 1;
// Get the previously selected option for pre-filling the radio button
$selected_option = $user_answers[$current_question['question_id']];
$contestant_number_display = $current_quiz['contestant_number'];

// Timer Fetch Logic: Get the time limit and start time from the database
$quiz_duration_minutes = 0; 
$attempt_start_time_unix = 0;

$sql_time_data = "
    SELECT 
        q.time_limit_minutes, 
        UNIX_TIMESTAMP(a.start_time) AS start_time_unix
    FROM attempts a
    JOIN quizzes q ON a.quiz_id = q.quiz_id
    WHERE a.attempt_id = ? AND a.user_id = ?";

if ($stmt = $conn->prepare($sql_time_data)) {
    $stmt->bind_param("ii", $attempt_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $quiz_duration_minutes = (int)$row['time_limit_minutes'];
        $attempt_start_time_unix = (int)$row['start_time_unix']; 
    }
    $stmt->close();
}

// --- CRITICAL TIMER CALCULATION: Server-side sync ---
$quiz_duration_seconds = $quiz_duration_minutes * 60;
$time_remaining_on_load = 0;

if ($attempt_start_time_unix > 0 && $quiz_duration_seconds > 0) {
    // Calculate the expected quiz end time (start + duration)
    $quiz_end_time_unix = $attempt_start_time_unix + $quiz_duration_seconds;
    
    // Calculate time remaining based on server's current time
    $time_remaining_on_load = $quiz_end_time_unix - time();
} else {
    // Fallback or error handling for invalid/missing time data (Shouldn't happen with good data)
    $time_remaining_on_load = 3600; // Default to 1 hour if data is bad
}

// Ensure the JS timer doesn't start negative
$js_time_remaining = max(0, $time_remaining_on_load);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz: <?php echo htmlspecialchars($quiz_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* Custom Styles for Quiz UI */
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f6; }
        .quiz-container { max-width: 800px; }
        .question-card { border: none; border-radius: 16px; transition: transform 0.3s ease-in-out, box-shadow 0.3s; }
        .question-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        
        .option-label { 
            cursor: pointer; 
            border-radius: 12px; 
            transition: all 0.2s; 
            padding: 15px; 
            margin-bottom: 10px; 
            border: 2px solid #e0e0e0; 
            display: flex;
            align-items: center;
            background-color: #fff;
        }
        
        .option-label:hover { 
            background-color: #f8f9fa; 
            border-color: #0d6efd; 
        }
        
        /* Selector targets the adjacent .option-label div when the hidden input is checked. */
        input[type="radio"]:checked + .option-label { 
            background-color: #e6f0ff; 
            border-color: #0d6efd; 
            font-weight: 600; 
            box-shadow: 0 0 5px rgba(13, 110, 253, 0.5);
        }
        
        input[type="radio"] { display: none; }
        
        .nav-btn { width: 100%; border-radius: 10px; padding: 12px 0; font-size: 1.1rem; }
        
        .question-nav-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(50px, 1fr)); gap: 8px; }
        .q-btn { 
            width: 100%; 
            border-radius: 8px; 
            padding: 8px 0; 
            font-size: 1rem;
            transition: background-color 0.2s, transform 0.1s;
        }
        .q-btn:active { transform: scale(0.95); }
        .q-answered { background-color: #28a745; color: white; border: 1px solid #28a745; }
        .q-current { background-color: #0d6efd; color: white; border: 3px solid #ffc107; font-weight: bold; }
        .q-unanswered { background-color: #ffffff; color: #6c757d; border: 1px solid #6c757d; }
        
        .contestant-info { border-bottom: 3px solid #0d6efd; padding-bottom: 15px; margin-bottom: 30px; }
        
        /* Animation for low time */
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.05); }
        }
        .animate-pulse {
            animation: pulse 1s infinite;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navbar with Quiz Title -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-lg">
        <div class="container quiz-container">
            <a class="navbar-brand fw-bold" href="participant_dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <span class="navbar-text text-light fs-5">
                <i class="fas fa-scroll"></i> <?php echo htmlspecialchars($quiz_title); ?>
            </span>
        </div>
    </nav>

    <div class="container mt-5 quiz-container">
        
        <!-- Timer Box -->
        <div class="d-flex justify-content-between align-items-center mb-5 p-3 bg-white rounded-3 shadow">
            <h5 class="mb-0 text-secondary fw-bold">
                <i class="far fa-clock"></i> Time Remaining
            </h5>
            <!-- Timer text class will be updated by JS -->
            <div id="quiz-timer" class="fw-bolder fs-3 text-primary">--:--</div>
        </div>

        <!-- Contestant Info & Progress -->
        <div class="contestant-info d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0 text-dark">
                Participant: <span class="text-primary fw-bolder"><?php echo htmlspecialchars($contestant_number_display); ?></span>
            </h2>
            <div class="fw-bolder fs-5 text-primary">
                Question <span id="currentQNumber"><?php echo $question_num; ?></span> / <?php echo $total_questions; ?>
            </div>
        </div>

        <!-- Main Quiz Form for Next/Prev/Finish -->
        <form id="main-quiz-form" method="POST" action="start_quiz.php?quiz_id=<?php echo $quiz_id; ?>" class="mb-5">
            <input type="hidden" name="contestant_number" value="<?php echo htmlspecialchars($contestant_number_display); ?>">
            
            <div class="card shadow-lg question-card p-4">
                <div class="card-body">
                    <!-- Question Text -->
                    <h3 class="card-title mb-4 border-bottom pb-3">
                        <span class="badge bg-secondary me-2"><?php echo $question_num; ?></span> 
                        <?php echo htmlspecialchars($current_question['question_text']); ?>
                    </h3>
                    
                    <div class="options-container" id="options-container">
                        <?php echo render_question_options($current_question, $selected_option); ?>
                    </div>
                </div>
            </div>

            <!-- Main Navigation Buttons -->
            <div class="d-flex justify-content-between mt-4">
                <button type="submit" name="action" value="prev" class="btn btn-outline-secondary btn-lg me-2 nav-btn w-50" <?php echo ($current_index == 0) ? 'disabled' : ''; ?>>
                    <i class="fas fa-arrow-left"></i> Previous
                </button>
                
                <?php if ($current_index < $total_questions - 1): ?>
                    <button type="submit" name="action" value="next" class="btn btn-primary btn-lg nav-btn w-50">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                <?php else: ?>
                    <button type="submit" name="action" value="finish" class="btn btn-danger btn-lg nav-btn w-50">
                        <i class="fas fa-check-double"></i> Submit Quiz
                    </button>
                <?php endif; ?>
            </div>
        </form>

        <hr class="my-5">

        <!-- Question Navigation Grid -->
        <h4 class="mb-3 text-secondary"><i class="fas fa-list-ol"></i> Question Navigation</h4>
        <div class="question-nav-grid mb-5">
            <?php for ($i = 0; $i < $total_questions; $i++): ?>
                <?php
                    $q_id = $questions[$i]['question_id'];
                    $q_class = 'btn-light'; // Base class for non-current
                    
                    if ($i == $current_index) {
                        $q_class = 'q-current'; // Primary + Yellow border
                    } elseif ($user_answers[$q_id] !== null && $user_answers[$q_id] !== '') {
                        $q_class = 'q-answered'; // Green
                    } else {
                        $q_class = 'q-unanswered'; // White/Gray
                    }
                ?>
                <!-- Separate form for jumping to ensure current answer is saved first -->
                <form method="POST" action="start_quiz.php?quiz_id=<?php echo $quiz_id; ?>" class="d-inline jump-form">
                    <!-- This hidden input is crucial and will be filled by JS on submission -->
                    <input type="hidden" name="current_answer" value=""> 
                    <input type="hidden" name="contestant_number" value="<?php echo htmlspecialchars($contestant_number_display); ?>">
                    <input type="hidden" name="action" value="jump">
                    <input type="hidden" name="jump_index" value="<?php echo $i; ?>">
                    <button type="submit" class="btn <?php echo $q_class; ?> q-btn">
                        <?php echo $i + 1; ?>
                    </button>
                </form>
            <?php endfor; ?>
        </div>
        
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    
    <script>
        // Server-calculated time remaining is passed to the client for accuracy.
        let timeLeftSeconds = <?php echo $js_time_remaining; ?>;
        
        const timerDisplay = document.getElementById('quiz-timer');
        const mainForm = document.getElementById('main-quiz-form'); 
        
        /**
         * Reads the value of the currently selected radio button.
         * @returns {string} The selected option value (A, B, C, D) or empty string if none selected.
         */
        function getCurrentAnswer() {
            const checkedRadio = document.querySelector('input[name="current_answer"]:checked');
            return checkedRadio ? checkedRadio.value : '';
        }

        /**
         * Timer update function: decrements time and handles timeout.
         */
        function updateTimer() {
            
            if (timeLeftSeconds <= 0) {
                timeLeftSeconds = 0;
                clearInterval(timerInterval);
                timerDisplay.textContent = "00:00";
                
                // --- Automatic Submission on Timeout ---
                console.log("Time is up. Submitting quiz automatically.");
                
                // 1. Save the last viewed question's answer
                const lastAnswerInput = document.createElement('input');
                lastAnswerInput.type = 'hidden';
                lastAnswerInput.name = 'current_answer';
                lastAnswerInput.value = getCurrentAnswer();
                mainForm.appendChild(lastAnswerInput);

                // 2. Set action to 'timeout'
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'timeout';
                mainForm.appendChild(actionInput);
                
                // 3. Submit the form
                mainForm.submit();
                return;
            }
            
            // Decrement timer
            timeLeftSeconds--;

            const minutes = Math.floor(timeLeftSeconds / 60);
            const seconds = timeLeftSeconds % 60;

            const minutesStr = String(minutes).padStart(2, '0');
            const secondsStr = String(seconds).padStart(2, '0');

            timerDisplay.textContent = `${minutesStr}:${secondsStr}`;
            
            // Visual feedback for low time
            if (timeLeftSeconds < 60) {
                // Less than 1 minute: Red and pulsing
                timerDisplay.classList.add('text-danger', 'animate-pulse');
                timerDisplay.classList.remove('text-warning', 'text-primary');
            } else if (timeLeftSeconds < 300) { 
                // Less than 5 minutes: Orange warning
                timerDisplay.classList.add('text-warning');
                timerDisplay.classList.remove('text-danger', 'animate-pulse', 'text-primary');
            } else {
                 // Normal time: Blue/Primary color
                 timerDisplay.classList.remove('text-danger', 'animate-pulse', 'text-warning');
                 timerDisplay.classList.add('text-primary'); 
            }
        }
        
        // --- JUMP FORM SUBMISSION HANDLER ---
        // Ensures the answer for the current question is saved before jumping to another.
        document.querySelectorAll('.jump-form').forEach(form => {
            form.addEventListener('submit', function(event) {
                // Get the current answer before the jump
                const answer = getCurrentAnswer();
                
                // Update the hidden input in this specific jump form
                this.querySelector('input[name="current_answer"]').value = answer;
                
                // Allow the form to submit to PHP
            });
        });

        // Start the timer
        updateTimer(); // Initial call to display time immediately
        const timerInterval = setInterval(updateTimer, 1000);
        
        // Prevent form re-submission on page refresh (Post/Redirect/Get pattern)
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>
