<?php
require_once __DIR__ . '/api/config/config.php';
$db = getDBConnection();
$resId = 3; // Latest from previous check
$stmt = $db->prepare("SELECT sa.*, q.question_text, q.correct_answer FROM student_answers sa JOIN questions q ON sa.question_id = q.id WHERE sa.result_id = ?");
$stmt->execute([$resId]);
$ans = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$ans) {
    echo "NO_ANSWERS_FOR_RESULT_$resId\n";
    // Check if there are any answers at all
    $total = $db->query("SELECT COUNT(*) FROM student_answers")->fetchColumn();
    echo "Total answers in DB: $total\n";
} else {
    foreach ($ans as $a) {
        echo "Q: {$a['question_text']} | Expected: {$a['correct_answer']} | Got: {$a['student_answer']}\n";
    }
}
