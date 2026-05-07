<?php
require_once 'config/database.php';

try {
    $db = Database::getInstance()->getConnection();

    // --- Fix bookings table ---
    $stmt = $db->query("SHOW COLUMNS FROM bookings LIKE 'notes'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE bookings ADD COLUMN notes TEXT NULL AFTER check_out");
        echo "✅ Column <strong>notes</strong> added to bookings.<br>";
    } else {
        echo "ℹ️ Column <strong>notes</strong> already exists in bookings.<br>";
    }

    // --- Fix rooms table ---
    $stmt = $db->query("SHOW COLUMNS FROM rooms LIKE 'price'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE rooms ADD COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER room_type");
        echo "✅ Column <strong>price</strong> added to rooms.<br>";
    } else {
        echo "ℹ️ Column <strong>price</strong> already exists in rooms.<br>";
    }

    $stmt = $db->query("SHOW COLUMNS FROM rooms LIKE 'capacity'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE rooms ADD COLUMN capacity INT NOT NULL DEFAULT 1 AFTER price");
        echo "✅ Column <strong>capacity</strong> added to rooms.<br>";
    } else {
        echo "ℹ️ Column <strong>capacity</strong> already exists in rooms.<br>";
    }

    $stmt = $db->query("SHOW COLUMNS FROM rooms LIKE 'status'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE rooms ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'available' AFTER capacity");
        echo "✅ Column <strong>status</strong> added to rooms.<br>";
    } else {
        echo "ℹ️ Column <strong>status</strong> already exists in rooms.<br>";
    }

    // --- Fix payments table ---
    $paymentsColumns = [
        'booking_id' => "INT NOT NULL DEFAULT 0 AFTER id",
        'amount' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER booking_id",
        'status' => "VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER amount",
        'payment_method' => "VARCHAR(50) NOT NULL DEFAULT 'pending' AFTER status",
        'created_at' => "DATETIME DEFAULT CURRENT_TIMESTAMP AFTER payment_method",
        'confirmed_by' => "INT NULL AFTER created_at",
        'confirmed_at' => "DATETIME NULL AFTER confirmed_by"
    ];

    foreach ($paymentsColumns as $col => $def) {
        $stmt = $db->query("SHOW COLUMNS FROM payments LIKE '$col'");
        if ($stmt->rowCount() == 0) {
            $db->exec("ALTER TABLE payments ADD COLUMN $col $def");
            echo "✅ Column <strong>$col</strong> added to payments.<br>";
        } else {
            echo "ℹ️ Column <strong>$col</strong> already exists in payments.<br>";
        }
    }

    echo "<br>🎉 Database fix complete! You can now <a href='dashboards/finance.php'>go to Finance Dashboard</a>.";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>

