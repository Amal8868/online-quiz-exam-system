<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/config/config.php';

try {
    $db = getDBConnection();
    $stmt = $db->prepare("DESCRIBE quizzes");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Columns:\n";
    foreach ($columns as $col) {
        echo "'" . $col['Field'] . "'\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
