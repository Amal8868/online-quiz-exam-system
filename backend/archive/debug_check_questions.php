<?php
require_once __DIR__ . '/api/config/config.php';

try {
    $db = getDBConnection();
    $stmt = $db->query("SELECT * FROM questions ORDER BY id DESC LIMIT 5");
    $questions = $stmt->fetchAll();
    
    echo "Total Questions Found: " . count($questions) . "\n\n";
    
    foreach ($questions as $q) {
        echo "ID: " . $q['id'] . "\n";
        echo "Quiz ID: " . $q['quiz_id'] . "\n";
        echo "Text: " . $q['question_text'] . "\n";
        echo "Type: " . $q['question_type'] . "\n";
        echo "Options: " . $q['options'] . "\n";
        echo "---------------------------\n";
    }
    
    if (count($questions) === 0) {
        echo "No questions found in the database table 'questions'.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
