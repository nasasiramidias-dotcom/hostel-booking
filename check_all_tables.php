<?php
require_once 'config/database.php';
$db = Database::getInstance()->getConnection();
$tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    echo "=== $table ===\n";
    $stmt = $db->query("SHOW CREATE TABLE $table");
    $row = $stmt->fetch();
    echo $row['Create Table'] . "\n\n";
}

