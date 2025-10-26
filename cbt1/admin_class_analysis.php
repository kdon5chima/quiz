<?php
// admin_class_analysis.php - Allows admin to select a class and displays ranked student performance.

session_start();
require_once 'config.php';
require_once 'helpers.php'; 

// Check for Admin login
if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] !== 'admin') {
    header("location: login.php");
    exit;
}

$selected_class = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
$analysis_data = [];
$class_name = "All Classes";
$error_message = '';

try {
    // 1. Fetch all available class names (grades)
    $sql_classes = "SELECT DISTINCT grade FROM users WHERE user_type = 'student' ORDER BY grade ASC";
    $stmt_classes = $pdo->query($sql_classes);
    $classes = $stmt_classes->fetchAll(PDO::FETCH_COLUMN);

    if ($selected_class) {
        
        // 2. Fetch Class Name
        $class_name = "Grade " . htmlspecialchars($selected_class);
        
        // 3. Fetch Student Performance Data for the Selected Class
        // This query calculates the average score and total tests taken per student.
        $sql_analysis = "
            SELECT 
                u.user_id,
                u.username,
                COUNT(r.result_id) AS total_tests_taken,
                SUM(r.score) AS total_score,
                SUM(r.total_questions) AS grand_total_questions,
                (SUM(r.score) / SUM(r.total_questions)) * 100 AS average_percentage
            FROM users u
            LEFT JOIN results r ON u.user_id = r.user_id AND r.is_submitted = 1
            WHERE u.user_type = 'student' AND u.grade = :class_id
            GROUP BY u.user_id, u.username
            HAVING total_tests_taken > 0
            ORDER BY average_percentage DESC, u.username ASC
        ";

        $stmt_analysis = $pdo->prepare($sql_analysis);
        $stmt_analysis->bindParam(':class_id', $selected_class, PDO::PARAM_INT);
        $stmt_analysis->execute();
        $raw_data = $stmt_analysis->fetchAll(PDO::FETCH_ASSOC);

        // 4. Calculate Position (Rank)
        $rank = 0;
        $prev_percentage = null;
        $rank_offset = 0;

        foreach ($raw_data as $i => $row) {
            $current_percentage = round($row['average_percentage'], 2);

            // Determine rank based on score percentage
            if ($current_percentage !== $prev_percentage) {
                $rank = $i + 1;
                $rank_offset = 0;
            } else {
                $rank_offset++; // Students with the same score share the same rank
            }
            
            $analysis_data[] = [
                'serial_number' => $i + 1,
                'student_name' => htmlspecialchars($row['username']),
                'actual_score' => (int)$row['total_score'],
                'max_score' => (int)$row['grand_total_questions'],
                'percentage_score' => $current_percentage,
                'position' => $rank,
                'total_tests_taken' => (int)$row['total_tests_taken']
            ];

            $prev_percentage = $current_percentage;
        }

    }

} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
} catch (Exception $e) {
    $error_message = "Application Error: " . $e->getMessage();
}

unset($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Class Performance Analysis - Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .page-header { background-color: #f8f9fa; padding: 20px; border-bottom: 1px solid #dee2e6; }
        .table-striped tbody tr:nth-of-type(odd) { background-color: #f3f3f3; }
    </style>
</head>
<body>

<?php include 'admin_header.php'; // Include your standard admin navigation/header ?>

<div class="container mt-5">
    <div class="page-header mb-4">
        <h2>Class Performance Analysis</h2>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form action="admin_class_analysis.php" method="GET" class="form-inline">
                <label for="class_id" class="mr-3 font-weight-bold">Select Class (Grade):</label>
                <select name="class_id" id="class_id" class="form-control mr-3" required>
                    <option value="">-- Choose Grade --</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo htmlspecialchars($class); ?>" 
                                <?php echo $selected_class == $class ? 'selected' : ''; ?>>
                            Grade <?php echo htmlspecialchars($class); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">View Analysis</button>
            </form>
        </div>
    </div>

    <?php if ($selected_class): ?>
        <div class="alert alert-info">
            Displaying analysis for **<?php echo $class_name; ?>**. Ranks are based on average percentage score across all submitted tests.
        </div>

        <?php if (!empty($analysis_data)): ?>
            <h3>Ranked Students in <?php echo $class_name; ?> (Total: <?php echo count($analysis_data); ?>)</h3>
            <div class="table-responsive">
                <table class="table table-striped table-bordered mt-3">
                    <thead class="thead-dark">
                        <tr>
                            <th>Position</th>
                            <th>S/N</th>
                            <th>Student Name</th>
                            <th>Total Score</th>
                            <th>Percentage Score</th>
                            <th>Tests Taken</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analysis_data as $data): ?>
                        <tr>
                            <td>
                                <span class="badge badge-<?php echo ($data['position'] <= 3 ? 'success' : 'secondary'); ?> badge-lg">
                                    <?php echo $data['position']; ?>
                                </span>
                            </td>
                            <td><?php echo $data['serial_number']; ?></td>
                            <td><?php echo $data['student_name']; ?></td>
                            <td><?php echo $data['actual_score']; ?> / <?php echo $data['max_score']; ?></td>
                            <td><?php echo number_format($data['percentage_score'], 2); ?>%</td>
                            <td><?php echo $data['total_tests_taken']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">No students in **<?php echo $class_name; ?>** have completed any tests yet.</div>
        <?php endif; ?>

    <?php elseif (empty($classes)): ?>
        <div class="alert alert-danger">No student accounts found in the system to analyze.</div>
    <?php endif; ?>

</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>
</html>