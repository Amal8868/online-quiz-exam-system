<?php
require_once __DIR__ . '/config/config.php';
$db = getDBConnection();
$stmt = $db->query("SHOW COLUMNS FROM students");
print_r($stmt->fetchAll());
$stmt = $db->query("SHOW INDEX FROM students");
print_r($stmt->fetchAll());
