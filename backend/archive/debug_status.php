<?php
require_once __DIR__ . '/api/config/config.php';
$db = getDBConnection();
$res = $db->query("SHOW COLUMNS FROM quizzes LIKE 'status'")->fetch();
file_put_contents('quiz_status_type.txt', $res['Type']);
echo "OK\n";
