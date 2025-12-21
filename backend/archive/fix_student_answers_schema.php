<?php
require_once __DIR__ . '/api/config/config.php';

try {
    $db = getDBConnection();
    
    // 1. Check for answered_at column
    $stmt = $db->query("SHOW COLUMNS FROM student_answers LIKE 'answered_at'");
    if (!$stmt->fetch()) {
        echo "Adding answered_at column...\n";
        $db->exec("ALTER TABLE student_answers ADD COLUMN answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    } else {
        echo "answered_at column already exists.\n";
    }
    
    // 2. Check for unique key (result_id, question_id)
    $stmt = $db->query("SHOW INDEX FROM student_answers WHERE Column_name = 'result_id' AND Seq_in_index = 1");
    $hasIndex = false;
    $indexes = $stmt->fetchAll();
    foreach ($indexes as $idx) {
        // Need to check if it's the right one.
        $idxName = $idx['Key_name'];
        $stmt2 = $db->prepare("SHOW INDEX FROM student_answers WHERE Key_name = ?");
        $stmt2->execute([$idxName]);
        $cols = $stmt2->fetchAll(PDO::FETCH_COLUMN, 4); // Column_name
        if (count($cols) == 2 && in_array('result_id', $cols) && in_array('question_id', $cols)) {
            $hasIndex = true;
            break;
        }
    }
    
    if (!$hasIndex) {
        echo "Adding unique index on (result_id, question_id)...\n";
        try {
            $db->exec("ALTER TABLE student_answers ADD UNIQUE KEY unique_answer (result_id, question_id)");
        } catch (Exception $e) {
            echo "Index creation error (maybe exists with diff name): " . $e->getMessage() . "\n";
        }
    } else {
        echo "Unique index already exists.\n";
    }
    
    echo "Migration Complete.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
