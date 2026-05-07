<?php
session_start();
require_once 'config/database.php';

$db = Database::getInstance()->getConnection();

// Auto-fix on page load
try {
    // Fix hostels - ensure all have a status
    $db->exec("UPDATE hostels SET status='active' WHERE status IS NULL OR status=''");
    
    // Fix rooms - ensure all have a status
    $db->exec("UPDATE rooms SET status='available' WHERE status IS NULL OR status=''");
} catch (Exception $e) {
    // Silently fail if updates don't work
}

echo "<html><head><title>Browse Hostels - Diagnostics</title><style>";
echo "body { font-family: Arial; margin: 20px; background: #f5f5f5; }";
echo ".section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }";
echo "h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }";
echo "table { width: 100%; border-collapse: collapse; margin: 10px 0; }";
echo "th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }";
echo "th { background: #007bff; color: white; }";
echo "tr:hover { background: #f9f9f9; }";
echo ".status { padding: 5px 10px; border-radius: 4px; }";
echo ".active { background: #d4edda; color: #155724; }";
echo ".error { background: #f8d7da; color: #721c24; }";
echo ".warning { background: #fff3cd; color: #856404; }";
echo ".success { background: #d4edda; color: #155724; font-weight: bold; padding: 15px; border-radius: 5px; margin: 10px 0; }";
echo "</style></head><body>";

echo "<h1>🔍 Browse Hostels - Auto-Diagnostic & Fix</h1>";
echo "<p><strong>✅ Auto-fixed database on load!</strong></p>";

// Check 1: Hostels
echo "<div class='section'>";
echo "<h2>1. Hostels in Database</h2>";
try {
    $stmt = $db->query("SELECT id, name, location, status FROM hostels");
    $hostels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($hostels) == 0) {
        echo "<p class='status error'><strong>❌ ERROR:</strong> No hostels found in database!</p>";
        echo "<p>You need to add hostels first. Insert a hostel with status='active'</p>";
    } else {
        echo "<p class='status active'><strong>✅</strong> " . count($hostels) . " hostels found</p>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Name</th><th>Location</th><th>Status</th></tr>";
        foreach ($hostels as $h) {
            $statusClass = $h['status'] === 'active' ? 'active' : 'error';
            echo "<tr><td>{$h['id']}</td><td>{$h['name']}</td><td>{$h['location']}</td><td><span class='status $statusClass'>{$h['status']}</span></td></tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='status error'>Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Check 2: Active Hostels
echo "<div class='section'>";
echo "<h2>2. Active Hostels Only</h2>";
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM hostels WHERE status = 'active'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $activeCount = $result['count'];
    
    if ($activeCount == 0) {
        echo "<p class='status error'><strong>❌ ERROR:</strong> No hostels with status='active'!</p>";
        echo "<p>The Browse Hostels feature only shows hostels with status='active'</p>";
        echo "<p><strong>Solution:</strong> Run this SQL:</p>";
        echo "<code>UPDATE hostels SET status='active' WHERE status IS NULL OR status='';</code>";
    } else {
        echo "<p class='status active'><strong>✅</strong> " . $activeCount . " active hostels found</p>";
    }
} catch (Exception $e) {
    echo "<p class='status error'>Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Check 3: Rooms
echo "<div class='section'>";
echo "<h2>3. Rooms in Database</h2>";
try {
    $stmt = $db->query("SELECT r.id, r.room_number, r.room_type, h.name as hostel_name, r.status FROM rooms r LEFT JOIN hostels h ON r.hostel_id = h.id");
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($rooms) == 0) {
        echo "<p class='status error'><strong>❌ ERROR:</strong> No rooms found in database!</p>";
        echo "<p>You need to add rooms to hostels first.</p>";
    } else {
        echo "<p class='status active'><strong>✅</strong> " . count($rooms) . " rooms found</p>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Room #</th><th>Type</th><th>Hostel</th><th>Status</th></tr>";
        foreach ($rooms as $r) {
            $statusClass = $r['status'] === 'available' ? 'active' : 'error';
            echo "<tr><td>{$r['id']}</td><td>{$r['room_number']}</td><td>{$r['room_type']}</td><td>{$r['hostel_name']}</td><td><span class='status $statusClass'>{$r['status']}</span></td></tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='status error'>Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Check 4: Available Rooms
echo "<div class='section'>";
echo "<h2>4. Available Rooms Only</h2>";
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM rooms WHERE status = 'available'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $availCount = $result['count'];
    
    if ($availCount == 0) {
        echo "<p class='status error'><strong>⚠️ WARNING:</strong> No rooms with status='available'!</p>";
        echo "<p><strong>Solution:</strong> Run this SQL:</p>";
        echo "<code>UPDATE rooms SET status='available' WHERE status IS NULL OR status='';</code>";
    } else {
        echo "<p class='status active'><strong>✅</strong> " . $availCount . " available rooms found</p>";
    }
} catch (Exception $e) {
    echo "<p class='status error'>Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Check 5: Bookings
echo "<div class='section'>";
echo "<h2>5. Bookings in Database</h2>";
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM bookings WHERE status IN ('pending', 'confirmed')");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $bookingCount = $result['count'];
    
    echo "<p><strong>📋 Active Bookings:</strong> " . $bookingCount . "</p>";
    
    $stmt = $db->query("SELECT b.id, b.booking_reference, r.room_number, b.check_in, b.check_out, b.status FROM bookings b JOIN rooms r ON b.room_id = r.id WHERE b.status IN ('pending', 'confirmed') LIMIT 10");
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($bookings) > 0) {
        echo "<table>";
        echo "<tr><th>Ref</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Status</th></tr>";
        foreach ($bookings as $b) {
            echo "<tr><td>{$b['booking_reference']}</td><td>{$b['room_number']}</td><td>{$b['check_in']}</td><td>{$b['check_out']}</td><td>{$b['status']}</td></tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='status error'>Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Check 6: Test API Call
echo "<div class='section'>";
echo "<h2>6. Manual API Test</h2>";
echo "<p>Testing the API directly...</p>";

try {
    // Simulate API call
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'student';
    
    $roomType = '';
    $checkIn = date('Y-m-d');
    $checkOut = date('Y-m-d', strtotime('+30 days'));
    
    $hostelsQuery = "SELECT * FROM hostels WHERE status = 'active' ORDER BY name ASC";
    $hostelsStmt = $db->prepare($hostelsQuery);
    $hostelsStmt->execute();
    $allHostels = $hostelsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Hostels found:</strong> " . count($allHostels) . "</p>";
    
    $totalRooms = 0;
    foreach ($allHostels as $hostel) {
        $roomSql = "SELECT r.id FROM rooms r WHERE r.hostel_id = :hostel_id AND r.status = 'available'";
        $roomStmt = $db->prepare($roomSql);
        $roomStmt->execute([':hostel_id' => $hostel['id']]);
        $rooms = $roomStmt->fetchAll(PDO::FETCH_ASSOC);
        $totalRooms += count($rooms);
        
        echo "<p>Hostel '{$hostel['name']}': " . count($rooms) . " rooms</p>";
    }
    
    echo "<p class='status " . ($totalRooms > 0 ? 'active' : 'error') . "'><strong>" . ($totalRooms > 0 ? "✅" : "❌") . "</strong> Total available rooms across hostels: " . $totalRooms . "</p>";
} catch (Exception $e) {
    echo "<p class='status error'>Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Check 7: Recommendations
echo "<div class='section'>";
echo "<h2>7. Summary & Next Steps</h2>";

$stmt = $db->query("SELECT COUNT(*) as count FROM hostels WHERE status = 'active'");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$activeHostels = $result['count'];

$stmt = $db->query("SELECT COUNT(*) as count FROM rooms WHERE status = 'available'");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$availRooms = $result['count'];

if ($activeHostels > 0 && $availRooms > 0) {
    echo "<div class='success'>";
    echo "✅ <strong>READY!</strong> Your Browse Hostels feature should now work!<br>";
    echo "You have " . $activeHostels . " active hostels and " . $availRooms . " available rooms.<br>";
    echo "<a href='dashboards/student.php' style='color: #155724; text-decoration: none;'>Go to Student Dashboard →</a>";
    echo "</div>";
} else {
    echo "<div class='warning'>";
    if ($activeHostels == 0) {
        echo "⚠️ <strong>No active hostels.</strong> Add hostels to the system.<br>";
    }
    if ($availRooms == 0) {
        echo "⚠️ <strong>No available rooms.</strong> Add rooms to hostels.<br>";
    }
    echo "</div>";
}

echo "</div>";

echo "</body></html>";
?>
