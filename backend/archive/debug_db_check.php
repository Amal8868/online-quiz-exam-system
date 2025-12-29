<?php
require_once __DIR__ . '/backend/api/config/config.php';

try {
    $db = getDBConnection();

    echo "Checking tables...\n";
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('quiz_classes', $tables)) {
        echo "[OK] Table 'quiz_classes' exists.\n";
    } else {
        echo "[MISSING] Table 'quiz_classes' does NOT exist.\n";
    }

    echo "\nChecking 'students' columns...\n";
    $columns = $db->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('class_id', $columns)) {
        echo "[OK] Column 'class_id' exists in 'students'.\n";
    } else {
        echo "[MISSING] Column 'class_id' does NOT exist in 'students'.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
