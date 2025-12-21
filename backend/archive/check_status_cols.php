<?php
require_once __DIR__ . '/api/config/config.php';
$db = getDBConnection();

function checkColumn($db, $table, $column) {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM $table LIKE '$column'");
        $col = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($col) {
            echo "Table $table Column $column: {$col['Type']}\n";
        } else {
            echo "Table $table Column $column: NOT FOUND\n";
        }
    } catch (Exception $e) {
        echo "Table $table Column $column ERROR: " . $e->getMessage() . "\n";
    }
}

checkColumn($db, 'quizzes', 'status');
checkColumn($db, 'results', 'status');
