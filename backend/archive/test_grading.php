<?php
require_once __DIR__ . '/api/config/config.php';
require_once __DIR__ . '/api/models/Result.php';

$resId = 3; // Use the existing one
$resultModel = new Result();
$summary = $resultModel->calculateSummary($resId);

echo "Summary for Result $resId:\n";
print_r($summary);

// List raw answers for this result
$db = getDBConnection();
$stmt = $db->prepare("SELECT * FROM student_answers WHERE result_id = ?");
$stmt->execute([$resId]);
$rawAns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Raw Answers:\n";
print_r($rawAns);

// List raw questions
$res = $db->prepare("SELECT quiz_id FROM results WHERE id = ?");
$res->execute([$resId]);
$quizId = $res->fetchColumn();
$stmt = $db->prepare("SELECT id, question_text, correct_answer FROM questions WHERE quiz_id = ?");
$stmt->execute([$quizId]);
$rawQs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Raw Questions:\n";
foreach ($rawQs as $q) {
    echo "ID: {$q['id']} | Expected: [{$q['correct_answer']}]\n";
}
