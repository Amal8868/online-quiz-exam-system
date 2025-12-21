<?php
require_once __DIR__ . '/config/config.php';

try {
    $db = getDBConnection();
    // Get foreign keys for quizzes table
    $sql = "SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_NAME = 'quizzes' 
            AND COLUMN_NAME = 'class_id' 
            AND TABLE_SCHEMA = '" . DB_NAME . "'";
            
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $fks = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Foreign Keys on class_id:\n";
    print_r($fks);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
