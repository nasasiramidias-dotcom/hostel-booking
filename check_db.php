<?php
require_once 'config/database.php';
$db = Database::getInstance()->getConnection();

echo "=== BOOKINGS CREATE TABLE ===\n";
$stmt = $db->query("SHOW CREATE TABLE bookings");
$row = $stmt->fetch();
echo $row['Create Table'] . "\n";

echo "\n=== PAYMENTS CREATE TABLE ===\n";
$stmt = $db->query("SHOW CREATE TABLE payments");
$row = $stmt->fetch();
echo $row['Create Table'] . "\n";
?>
