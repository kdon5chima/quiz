<?php
// delete_question.php
require_once "db_connect.php";

// ---------------------------
// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'admin') {
    header("location: login.php");
    exit;
}
// ---------------------------

$question_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : null;
$redirect_url = "add_questions.php"; // Default redirect

if ($quiz_id) {
    $redirect_url = "add_questions.php?quiz_id=" . $quiz_id;
}

if (!$question_id) {
    // If no question ID is provided, redirect with an error message.
    header("Location: " . $redirect_url . "&delete_status=error_id");
    exit;
}

// --- 1. Find the question and its image path for cleanup ---
$image_path = null;
$sql_fetch_image = "SELECT image_path FROM questions WHERE question_id = ?";
if ($stmt_fetch = $conn->prepare($sql_fetch_image)) {
    $stmt_fetch->bind_param("i", $question_id);
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();
    if ($row = $result_fetch->fetch_assoc()) {
        $image_path = $row['image_path'];
    }
    $stmt_fetch->close();
}

// --- 2. Delete the physical image file (if it exists) ---
if (!empty($image_path) && file_exists($image_path)) {
    unlink($image_path); // Remove the file from the server
}

// --- 3. Delete the question from the database ---
$sql_delete = "DELETE FROM questions WHERE question_id = ?";
if ($stmt_delete = $conn->prepare($sql_delete)) {
    $stmt_delete->bind_param("i", $question_id);
    if ($stmt_delete->execute()) {
        // Success: Redirect back to the question management page
        header("Location: " . $redirect_url . "&delete_status=success");
    } else {
        // Error: Redirect with an error message
        header("Location: " . $redirect_url . "&delete_status=error_db");
    }
    $stmt_delete->close();
} else {
    // Error preparing statement
    header("Location: " . $redirect_url . "&delete_status=error_prep");
}
$conn->close();
exit;
?>