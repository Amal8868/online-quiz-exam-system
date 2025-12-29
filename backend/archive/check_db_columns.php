<?php
require_once __DIR__ . '/backend/api/config/config.php';

try {
    $db = getDBConnection();
    
    // Check columns in results table
    $stmt = $db->query("DESCRIBE results");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required = ['is_paused', 'is_blocked'];
    foreach ($required as $col) {
        if (!in_array($col, $columns)) {
            echo "Adding column $col to results table...\n";
            $db->exec("ALTER TABLE results ADD COLUMN $col TINYINT(1) DEFAULT 0");
        } else {
            echo "Column $col already exists.\n";
        }
    }
    
    echo "Database check completed successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
