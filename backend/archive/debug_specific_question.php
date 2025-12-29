<?php
require_once __DIR__ . '/backend/api/config/config.php';

try {
    $db = getDBConnection();
    
    echo "Searching for question 'my name is Amal'...\n";
    $stmt = $db->prepare("SELECT id, question_text, question_type, options, correct_answer FROM questions WHERE question_text LIKE ?");
    $stmt->execute(['%my name is Amal%']);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($question) {
        echo "ID: " . $question['id'] . "\n";
        echo "Type: " . $question['question_type'] . "\n";
        echo "Options (Raw): " . $question['options'] . "\n";
        echo "Correct Answer: " . $question['correct_answer'] . "\n";
    } else {
        echo "Question not found.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
