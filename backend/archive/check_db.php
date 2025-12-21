<?php
require_once __DIR__ . '/backend/api/config/config.php';
$db = getDBConnection();

$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables:\n";
print_r($tables);

foreach ($tables as $table) {
    echo "\nTable: $table\n";
    $columns = $db->query("DESCRIBE $table")->fetchAll();
    print_r($columns);
}
