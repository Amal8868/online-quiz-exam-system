<?php
require_once __DIR__ . '/api/config/config.php';
require_once __DIR__ . '/api/models/Teacher.php';

try {
    $teacherId = 7;
    $teacherModel = new Teacher();
    
    echo "--- Testing getQuizzes for Teacher $teacherId ---\n";
    $quizzes = $teacherModel->getQuizzes($teacherId);
    
    echo "Success! Found " . count($quizzes) . " quizzes.\n";
    print_r($quizzes);
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
