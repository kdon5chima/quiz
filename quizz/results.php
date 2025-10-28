<?php
// Start the session to access stored quiz data
session_start();

// NOTE: db_connect.php is typically included here if you need to fetch extra details
// require_once "db_connect.php";

// Check if the user is a logged-in participant and if the results data exists
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'participant') {
    // If not logged in as a participant, redirect to login
    header("location: login.php");
    exit;
}

if (!isset($_SESSION['latest_results'])) {
    // If results are missing (maybe the page was refreshed), send them to the dashboard
    header("location: participant_dashboard.php");
    exit;
}

// Extract the results from the session
$results = $_SESSION['latest_results'];
$quiz_id = $results['quiz_id'];
$score = $results['score'];
$total = $results['total'];
$percentage = $results['percentage'];
$contestant_number = $results['contestant_number'];

// Optionally unset the session data after displaying it to prevent a refresh loop
// unset($_SESSION['latest_results']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results</title>
    <!-- Load Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">

    <div class="max-w-xl w-full mx-4 bg-white shadow-2xl rounded-xl p-8 sm:p-12 border-4 border-indigo-600 transform transition-all hover:scale-[1.01]">
        
        <div class="text-center mb-10">
            <i class="fas fa-medal text-6xl text-indigo-600 mb-4 animate-bounce-slow"></i>
            <h1 class="text-4xl font-extrabold text-gray-900 mb-2">Quiz Completed!</h1>
            <p class="text-gray-500 font-medium">Your submission has been successfully processed.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-10 text-center">
            <!-- Total Score Card -->
            <div class="bg-blue-50 p-6 rounded-lg shadow-md border border-blue-200">
                <p class="text-sm font-semibold text-blue-600 uppercase mb-2">Total Score</p>
                <p class="text-5xl font-bold text-blue-800">
                    <?php echo htmlspecialchars($score); ?>
                </p>
                <p class="text-gray-500">out of <?php echo htmlspecialchars($total); ?></p>
            </div>

            <!-- Percentage Score Card -->
            <div class="bg-green-50 p-6 rounded-lg shadow-md border border-green-200">
                <p class="text-sm font-semibold text-green-600 uppercase mb-2">Accuracy</p>
                <p class="text-5xl font-bold text-green-800">
                    <?php echo htmlspecialchars($percentage); ?><span class="text-3xl">%</span>
                </p>
                <p class="text-gray-500">Correct Answers</p>
            </div>
        </div>
        
        <div class="mb-8 p-4 bg-gray-100 rounded-lg text-center shadow-inner">
            <p class="text-lg font-semibold text-gray-700">
                Contestant Number: 
                <span class="font-extrabold text-indigo-700 ml-2 text-xl">
                    <?php echo htmlspecialchars($contestant_number); ?>
                </span>
            </p>
        </div>

        <div class="text-center">
            <a href="participant_dashboard.php" class="inline-flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-full shadow-lg text-white bg-indigo-600 hover:bg-indigo-700 transition duration-300 ease-in-out transform hover:scale-105">
                <i class="fas fa-home mr-2"></i> Return to Dashboard
            </a>
        </div>
    </div>
    
    <script>
        // Optional: Simple animation for the completion message
        document.addEventListener('DOMContentLoaded', () => {
            const medalIcon = document.querySelector('.fa-medal');
            if(medalIcon) {
                // Remove initial slow bounce and add a quick celebration effect
                setTimeout(() => {
                    medalIcon.classList.remove('animate-bounce-slow');
                    medalIcon.classList.add('animate-spin-once', 'text-yellow-500');
                }, 500);
            }
        });
    </script>
</body>
</html>
