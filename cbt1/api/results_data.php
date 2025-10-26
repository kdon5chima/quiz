<?php
// api/results_data.php
require_once '../config.php'; 
header('Content-Type: application/json');

$response = [
    'labels' => [], // Test Titles
    'data' => []    // Average Scores
];

try {
    // SQL to get the average score for each test
    // Calculates percentage score based on number of questions (simplified)
    $sql = "
        SELECT 
            t.title,
            (SUM(CASE WHEN sa.selected_option = q.correct_option THEN 1 ELSE 0 END) / COUNT(q.question_id)) * 100 AS average_score_percent
        FROM 
            tests t
        JOIN 
            questions q ON t.test_id = q.test_id
        JOIN
            results r ON t.test_id = r.test_id
        JOIN
            student_answers sa ON r.result_id = sa.result_id AND q.question_id = sa.question_id
        WHERE
            r.is_submitted = TRUE
        GROUP BY 
            t.title
        ORDER BY 
            average_score_percent DESC;
    ";
    
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll();
    
    foreach ($results as $row) {
        $response['labels'][] = $row['title'] . ' (' . round($row['average_score_percent']) . '%)';
        $response['data'][] = round($row['average_score_percent'], 2); 
    }
    
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error.']);
}

unset($pdo);
?>