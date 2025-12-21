<?php
require_once __DIR__ . '/api/config/config.php';
require_once __DIR__ . '/api/models/Quiz.php';
require_once __DIR__ . '/api/models/Question.php';

// Simulate fetching Quiz ID 21 (from logs)
$quizId = 21; 

try {
    $db = getDBConnection();
    
    echo "--- Fetching Quiz $quizId ---\n";
    $quizModel = new Quiz();
    $quiz = $quizModel->find($quizId);
    print_r($quiz);
    
    echo "\n--- Fetching Questions for Quiz $quizId ---\n";
    $questionModel = new Question();
    $questions = $questionModel->getByQuizId($quizId);
    
    echo "Count: " . count($questions) . "\n";
    print_r($questions);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
