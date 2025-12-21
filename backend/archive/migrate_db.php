<?php
require_once __DIR__ . '/config/config.php';

try {
    $db = getDBConnection();
    
    $columns = $db->query("DESCRIBE quizzes")->fetchAll(PDO::FETCH_COLUMN);
    
    // Drop Foreign Key first
    // We found it is likely 'quizzes_ibfk_2' but let's be safe and try to catch error if not exists
    try {
        echo "Dropping FK quizzes_ibfk_2...\n";
        $db->exec("ALTER TABLE quizzes DROP FOREIGN KEY quizzes_ibfk_2");
    } catch (Exception $e) {
        echo "FK drop failed (maybe didn't exist): " . $e->getMessage() . "\n";
    }

    // Drop class_id if exists
    if (in_array('class_id', $columns)) {
        echo "Dropping class_id...\n";
        $db->exec("ALTER TABLE quizzes DROP COLUMN class_id");
    }
    
    // Check is_active vs status
    if (in_array('is_active', $columns) && in_array('status', $columns)) {
        echo "Dropping is_active (using status instead)...\n";
        $db->exec("ALTER TABLE quizzes DROP COLUMN is_active");
    }
    
     // Add timer_type if missing
    if (!in_array('timer_type', $columns)) {
        echo "Adding timer_type...\n";
        $db->exec("ALTER TABLE quizzes ADD COLUMN timer_type ENUM('exam', 'question') DEFAULT 'exam'");
    }

    echo "Migration Complete.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
