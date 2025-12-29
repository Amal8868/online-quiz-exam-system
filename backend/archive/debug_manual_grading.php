<?php
require_once __DIR__ . '/backend/api/config/config.php';

try {
    $db = getDBConnection();

    // 1. Get result 28
    $stmt = $db->query("SELECT * FROM results WHERE id = 28");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        die("No results found for ID 28.");
    }

    echo "Latest Result ID: " . $result['id'] . "\n";
    echo "Student ID: " . $result['student_id'] . "\n";
    echo "Quiz ID: " . $result['quiz_id'] . "\n";

    // 2. Get all answers for this result
    $stmt = $db->prepare("
        SELECT sa.id, sa.question_id, sa.student_answer, q.question_type, q.question_text 
        FROM student_answers sa
        JOIN questions q ON sa.question_id = q.id
        WHERE sa.result_id = ?
    ");
    $stmt->execute([$result['id']]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Run the exact count query used in StudentController
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM student_answers sa 
        JOIN questions q ON sa.question_id = q.id 
        WHERE sa.result_id = ? AND LOWER(q.question_type) = 'short_answer'
    ");
    $stmt->execute([$result['id']]);
    $manualCount = $stmt->fetchColumn();

    // Output results as JSON
    $output = [
        'result_id' => $result['id'],
        'answers' => $answers,
        'manual_count_check' => $manualCount
    ];
    echo json_encode($output, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
