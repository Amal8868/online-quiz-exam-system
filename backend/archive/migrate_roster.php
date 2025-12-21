<?php
require_once __DIR__ . '/api/config/config.php';

try {
    $db = getDBConnection();
    
    // 4. Quiz Allowed Students (Roster)
    $sql1 = "CREATE TABLE IF NOT EXISTS quiz_allowed_students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        quiz_id INT NOT NULL,
        student_id INT NOT NULL,
        FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        UNIQUE KEY unique_roster (quiz_id, student_id)
    )";
    $db->exec($sql1);
    echo "Checked/Created 'quiz_allowed_students'.\n";

    // 5. Kicked Students
    $sql2 = "CREATE TABLE IF NOT EXISTS kicked_students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        quiz_id INT NOT NULL,
        student_id INT NOT NULL,
        reason TEXT,
        kicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    )";
    $db->exec($sql2);
    echo "Checked/Created 'kicked_students'.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
