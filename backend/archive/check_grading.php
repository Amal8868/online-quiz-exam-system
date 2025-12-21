<?php
require_once __DIR__ . '/api/config/config.php';
$db = getDBConnection();

$stmt = $db->query("
    SELECT r.id as res_id, q.id as q_id, q.question_text, q.correct_answer, sa.student_answer
    FROM results r
    JOIN student_answers sa ON r.id = sa.result_id
    JOIN questions q ON sa.question_id = q.id
    ORDER BY r.id DESC LIMIT 10
");

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($data as $row) {
    echo "ResID: {$row['res_id']} | QID: {$row['q_id']} | Text: {$row['question_text']}\n";
    echo "  Expected: [{$row['correct_answer']}] | Got: [{$row['student_answer']}]\n";
}
