<?php
require_once __DIR__ . '/config/config.php';
$db = getDBConnection();

echo "Fixing students table schema...\n";

try {
    // 1. Make email nullable
    echo "Modifying email column to be nullable...\n";
    $db->exec("ALTER TABLE students MODIFY email VARCHAR(255) NULL");
    
    // 2. Drop unique index on email
    // We need to find the index name first, or just try to drop common names
    // Usually it's 'email'
    echo "Attempting to drop UNIQUE constraint on email...\n";
    try {
        $db->exec("ALTER TABLE students DROP INDEX email");
        echo "Dropped index 'email'.\n";
    } catch (Exception $e) {
        echo "Index 'email' not found or already dropped. Trying to find via SHOW INDEX.\n";
        
        $stmt = $db->query("SHOW INDEX FROM students WHERE Column_name = 'email' AND Non_unique = 0");
        $index = $stmt->fetch();
        if ($index) {
            $indexName = $index['Key_name'];
            echo "Found index: $indexName. Dropping...\n";
            $db->exec("ALTER TABLE students DROP INDEX $indexName");
        } else {
            echo "No unique index found on email.\n";
        }
    }

    echo "Fix complete.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
