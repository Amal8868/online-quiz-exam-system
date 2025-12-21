<?php
require_once __DIR__ . '/api/config/config.php';

try {
    $db = getDBConnection();
    
    echo "--- Checking 'questions' Table Schema ---\n";
    $stmt = $db->query("DESCRIBE questions");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    print_r($columns);
    
    echo "\n--- Checking 'questions' Table Content ---\n";
    $stmt = $db->query("SELECT * FROM questions ORDER BY id DESC LIMIT 5");
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($questions);
    
    echo "\n--- Checking 'quizzes' Table Content ---\n";
    $stmt = $db->query("SELECT * FROM quizzes ORDER BY id DESC LIMIT 5");
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($quizzes);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
