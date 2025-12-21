<?php
require_once __DIR__ . '/api/config/config.php';
$db = getDBConnection();
$stmt = $db->query("SELECT * FROM results ORDER BY id DESC LIMIT 5");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$res) {
    echo "NO_RESULTS_FOUND\n";
} else {
    foreach ($res as $row) {
        print_r($row);
    }
}
echo "Total count: " . $db->query("SELECT COUNT(*) FROM results")->fetchColumn() . "\n";
