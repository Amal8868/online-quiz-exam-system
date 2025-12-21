<?php
require_once __DIR__ . '/api/config/config.php';
$db = getDBConnection();
$stmt = $db->query("SELECT * FROM student_answers ORDER BY id DESC LIMIT 5");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$res) {
    echo "NO_ANSWERS_FOUND\n";
} else {
    foreach ($res as $row) {
        print_r($row);
    }
}
