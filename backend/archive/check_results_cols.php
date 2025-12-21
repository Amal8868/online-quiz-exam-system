<?php
require_once __DIR__ . '/api/config/config.php';
$db = getDBConnection();
$stmt = $db->query("DESCRIBE results");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo $c['Field'] . "\n";
}
