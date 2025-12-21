<?php
require_once __DIR__ . '/api/config/config.php';

try {
    $db = getDBConnection();
    echo "Starting Robust Migration...\n";

    // 1. Quizzes Table - ensure status column exists and has right enum
    echo "Fixing quizzes.status...\n";
    $stmt = $db->query("SHOW COLUMNS FROM quizzes LIKE 'status'");
    if ($stmt->fetch()) {
        $db->exec("ALTER TABLE quizzes MODIFY COLUMN status ENUM('draft', 'active', 'started', 'finished') DEFAULT 'draft'");
    } else {
        $db->exec("ALTER TABLE quizzes ADD COLUMN status ENUM('draft', 'active', 'started', 'finished') DEFAULT 'draft' AFTER timer_type");
    }

    // 2. Results Table - add status and started_at
    echo "Fixing results.status and started_at...\n";
    $stmt = $db->query("SHOW COLUMNS FROM results LIKE 'status'");
    if ($stmt->fetch()) {
         $db->exec("ALTER TABLE results MODIFY COLUMN status ENUM('waiting', 'in_progress', 'submitted', 'graded') DEFAULT 'waiting'");
    } else {
         $db->exec("ALTER TABLE results ADD COLUMN status ENUM('waiting', 'in_progress', 'submitted', 'graded') DEFAULT 'waiting' AFTER quiz_id");
    }

    $stmt = $db->query("SHOW COLUMNS FROM results LIKE 'started_at'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE results ADD COLUMN started_at TIMESTAMP NULL DEFAULT NULL AFTER status");
    }

    // 3. invalid_entries table
    echo "Creating invalid_entries table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS invalid_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        quiz_id INT NOT NULL,
        student_id_attempt VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
    )");

    echo "Migration Successful!\n";
} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
}
