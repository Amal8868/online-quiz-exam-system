<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Student.php';
require_once __DIR__ . '/models/Quiz.php';

$roomCode = '34R4YV';
$studentId = '1004';

echo "--- Debugging Entry for Room: $roomCode, Student: $studentId ---\n";

$db = getDBConnection();

// 1. Check Quiz
$stmt = $db->prepare("SELECT * FROM quizzes WHERE room_code = ?");
$stmt->execute([$roomCode]);
$quiz = $stmt->fetch();

if (!$quiz) {
    echo "[FAIL] Quiz not found for room code: $roomCode\n";
} else {
    echo "[INFO] Quiz Found: ID={$quiz['id']}, Status={$quiz['status']}\n";
    if ($quiz['status'] !== 'active') {
        echo "[WARNING] Quiz is NOT active. Student entry will be blocked.\n";
    }
}

// 2. Check Student
$stmt = $db->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    echo "[FAIL] Student not found for ID: $studentId\n";
} else {
    echo "[INFO] Student Found: ID={$student['id']}, Name={$student['name']}\n";
}

// 3. Check Roster
if ($quiz && $student) {
    $stmt = $db->prepare("SELECT * FROM quiz_allowed_students WHERE quiz_id = ? AND student_id = ?");
    $stmt->execute([$quiz['id'], $student['id']]);
    $allowed = $stmt->fetch();
    
    if ($allowed) {
        echo "[PASS] Student is in the roster for this quiz.\n";
    } else {
        echo "[FAIL] Student is NOT in the roster for this quiz.\n";
        
        echo "\nAllowed students for Quiz ID {$quiz['id']}:\n";
        $stmt = $db->prepare("SELECT s.student_id, s.name FROM students s JOIN quiz_allowed_students qas ON s.id = qas.student_id WHERE qas.quiz_id = ?");
        $stmt->execute([$quiz['id']]);
        while ($row = $stmt->fetch()) {
            echo " - {$row['student_id']}: {$row['name']}\n";
        }
    }
}
