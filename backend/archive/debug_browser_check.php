<?php
// Bypass Auth and Config to just dump data
require_once __DIR__ . '/api/config/config.php';
require_once __DIR__ . '/api/models/Quiz.php';
require_once __DIR__ . '/api/models/Question.php';

header('Content-Type: application/json');

try {
    $db = getDBConnection();
    
    // Attempt to find latest quiz
    $stmt = $db->query("SELECT * FROM quizzes ORDER BY id DESC LIMIT 1");
    $quiz = $stmt->fetch();
    
    if (!$quiz) {
        echo json_encode(['error' => 'No quizzes found in DB']);
        exit;
    }
    
    $quizId = $quiz['id'];
    
    // Get questions
    $questionModel = new Question();
    $questions = $questionModel->getByQuizId($quizId);
    $quiz['questions'] = $questions;
    
    echo json_encode([
        'success' => true,
        'message' => 'Debug Data for Latest Quiz (ID: ' . $quizId . ')',
        'data' => $quiz
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
