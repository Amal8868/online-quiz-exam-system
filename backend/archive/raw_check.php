<?php
require_once __DIR__ . '/api/config/config.php';
$db = getDBConnection();
$res = $db->query("SHOW COLUMNS FROM quizzes LIKE 'status'")->fetch();
echo "QUIZZES_STATUS:" . ($res['Type'] ?? 'NONE') . "\n";
$res = $db->query("SHOW COLUMNS FROM results LIKE 'status'")->fetch();
echo "RESULTS_STATUS:" . ($res['Type'] ?? 'NONE') . "\n";
