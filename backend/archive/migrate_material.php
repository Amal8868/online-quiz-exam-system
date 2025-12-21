<?php
require_once __DIR__ . '/api/config/config.php';

try {
    $db = getDBConnection();
    
    // Check if column exists
    $stmt = $db->query("SHOW COLUMNS FROM quizzes LIKE 'material_url'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        $sql = "ALTER TABLE quizzes ADD COLUMN material_url VARCHAR(255) NULL AFTER description";
        $db->exec($sql);
        echo "Successfully added 'material_url' column to 'quizzes' table.\n";
    } else {
        echo "'material_url' column already exists.\n";
    }
    
    // Create uploads directory if not exists
    $uploadDir = __DIR__ . '/uploads/materials';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
        echo "Created directory: $uploadDir\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
