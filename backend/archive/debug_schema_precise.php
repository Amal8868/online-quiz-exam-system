<?php
require_once __DIR__ . '/api/config/config.php';

try {
    $db = getDBConnection();
    
    $stmt = $db->query("SHOW COLUMNS FROM questions");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Columns in questions table:\n";
    foreach ($columns as $col) {
        echo "- $col\n";
    }
    
    if (in_array('time_limit_seconds', $columns)) {
        echo "\nSUCCESS: 'time_limit_seconds' exists.\n";
    } else {
        echo "\nFAILURE: 'time_limit_seconds' MISSING.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
