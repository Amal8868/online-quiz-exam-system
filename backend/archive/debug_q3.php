<?php
require_once __DIR__ . '/backend/api/config/config.php';

try {
    $db = getDBConnection();

    // Get the latest result
    $resultId = $db->query("SELECT id FROM results ORDER BY id DESC LIMIT 1")->fetchColumn();
    
    echo "Result ID: $resultId\n";

    // Get answers
    $stmt = $db->prepare("
        SELECT sa.question_id, sa.student_answer, sa.is_correct, q.question_text, q.options
        FROM student_answers sa
        JOIN questions q ON sa.question_id = q.id
        WHERE sa.result_id = ?
    ");
    $stmt->execute([$resultId]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($answers as $ans) {
        echo "Q: " . $ans['question_text'] . "\n";
        echo "Student Answer (Raw): [" . $ans['student_answer'] . "]\n";
        echo "Is Correct: " . $ans['is_correct'] . "\n";
        echo "Options: " . $ans['options'] . "\n";
        echo "---------------------------------\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
