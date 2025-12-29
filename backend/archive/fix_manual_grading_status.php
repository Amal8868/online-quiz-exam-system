<?php
require_once __DIR__ . '/backend/api/config/config.php';

try {
    $db = getDBConnection();
    
    echo "Updating status for manual grading questions...\n";
    
    // Find all answers that correspond to MANUAL_GRADING questions, have 0 points, and is_correct is 0
    // Set is_correct to NULL
    
    $sql = "UPDATE student_answers sa
            JOIN questions q ON sa.question_id = q.id
            SET sa.is_correct = NULL
            WHERE q.correct_answer = 'MANUAL_GRADING' 
            AND sa.points_awarded = 0
            AND sa.is_correct = 0";
            
    $stmt = $db->prepare($sql);
    $stmt->execute();
    
    $count = $stmt->rowCount();
    echo "Updated $count answers to 'Pending Grading' status.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
