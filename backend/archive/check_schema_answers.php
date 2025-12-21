<?php
require_once __DIR__ . '/api/config/config.php';
$db = getDBConnection();
$stmt = $db->query('DESCRIBE student_answers');
print_r($stmt->fetchAll());
