<?php
require_once __DIR__ . '/config/config.php';

try {
    $db = getDBConnection();
    echo "Starting Waiting Room Migration...\n";

    // 1. Update quizzes table status column
    echo "Updating quiz status enum...\n";
    $db->exec("ALTER TABLE quizzes MODIFY COLUMN status ENUM('draft', 'active', 'started', 'finished') DEFAULT 'draft'");

    // 2. Create invalid_entries table
    echo "Creating invalid_entries table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS invalid_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        quiz_id INT NOT NULL,
        student_id_attempt VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
    )");

    // 3. Ensure results table supports 'waiting' status
    echo "Updating results status enum...\n";
    $db->exec("ALTER TABLE results MODIFY COLUMN status ENUM('waiting', 'in_progress', 'submitted', 'graded') DEFAULT 'waiting'");

    echo "Migration Complete.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
