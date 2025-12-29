<?php
require_once __DIR__ . '/backend/api/config/config.php';

try {
    $db = getDBConnection();

    // Get the latest result
    $resultId = $db->query("SELECT id FROM results ORDER BY id DESC LIMIT 1")->fetchColumn();
    
    if (!$resultId) die("No results found.");
    
    echo "Checking result: $resultId\n";

    $stmt = $db->prepare("
        SELECT sa.student_answer, q.question_type, q.options
        FROM student_answers sa
        JOIN questions q ON sa.question_id = q.id
        WHERE sa.result_id = ?
    ");
    $stmt->execute([$resultId]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($answers as $i => $ans) {
        echo "Q" . ($i+1) . " Type: " . $ans['question_type'] . "\n";
        echo "Options (Raw): " . $ans['options'] . "\n";
        echo "Student Answer: " . $ans['student_answer'] . "\n";
        echo "---------------------------------\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
