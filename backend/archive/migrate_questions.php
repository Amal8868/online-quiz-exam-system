<?php
require_once __DIR__ . '/api/config/config.php';

try {
    $db = getDBConnection();
    
    // Check if column exists
    $stmt = $db->query("SHOW COLUMNS FROM questions LIKE 'time_limit_seconds'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        $sql = "ALTER TABLE questions ADD COLUMN time_limit_seconds INT DEFAULT NULL AFTER points";
        $db->exec($sql);
        echo "Successfully added 'time_limit_seconds' column to 'questions' table.\n";
    } else {
        echo "'time_limit_seconds' column already exists.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
