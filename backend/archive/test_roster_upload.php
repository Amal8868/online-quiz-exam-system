<?php
// Test script for Roster Upload
require_once __DIR__ . '/controllers/QuizController.php';

// Mock File Upload
$quizId = 25; // Use the one we found earlier (ID 25 for 34R4YV)
$teacherId = 123; // Assuming this teacher owns it. BUT wait, we need to know the teacher ID.
// Let's check who owns quiz 25 first.

$db = getDBConnection();
$stmt = $db->prepare("SELECT * FROM quizzes WHERE id = ?");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch();

if (!$quiz) {
    die("Quiz 25 not found. Please update script with valid quiz ID.\n");
}

$ownerId = $quiz['teacher_id'];
echo "Testing upload for Quiz {$quiz['id']} (Owner: $ownerId)...\n";

// Create a temp CSV file
$tmpFile = tempnam(sys_get_temp_dir(), 'csv');
$handle = fopen($tmpFile, 'w');
fputcsv($handle, ['student_id', 'name']);
fputcsv($handle, ['9991', 'Backend Test Student 1']);
fputcsv($handle, ['9992', 'Backend Test Student 2']);
fclose($handle);

// Mock $_FILES
$files = [
    'file' => [
        'name' => 'test_roster.csv',
        'type' => 'text/csv',
        'tmp_name' => $tmpFile,
        'error' => 0,
        'size' => filesize($tmpFile)
    ]
];

$controller = new QuizController();
$response = $controller->uploadRoster($ownerId, $quizId, $files);

echo "Response:\n";
print_r($response);

// Cleanup
unlink($tmpFile);
