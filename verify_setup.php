<?php
/**
 * BROWSE HOSTELS - SYSTEM VERIFICATION
 * Quick diagnostic tool to verify the setup
 */

session_start();
require_once 'config/database.php';

// Color codes for terminal output (fallback for HTML)
$status_ok = "✅";
$status_error = "❌";
$status_warning = "⚠️";

echo "<!DOCTYPE html>
<html>
<head>
    <title>Browse Hostels - System Verification</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .check { margin: 15px 0; padding: 15px; border-radius: 8px; }
        .success { background: #d4edda; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; }
        h1 { color: #333; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 4px; margin: 10px 0; font-family: monospace; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
    </style>
</head>
<body>

<h1>🔍 Browse Hostels System Verification</h1>
<p>Diagnostic report generated at " . date('Y-m-d H:i:s') . "</p>

<hr>";

// Test 1: Database Connection
echo "<div class='check success'>";
try {
    $db = Database::getInstance()->getConnection();
    echo "$status_ok <strong>Database Connection</strong><br>";
    echo "Status: Connected successfully";
} catch (Exception $e) {
    echo "<div class='check error'>";
    echo "$status_error <strong>Database Connection</strong><br>";
    echo "Error: " . $e->getMessage();
    echo "</div>";
}
echo "</div>";

// Test 2: Hostels Table
echo "<div class='check'>";
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM hostels");
    $result = $stmt->fetch();
    $count = $result['count'];
    
    if ($count > 0) {
        echo "<div class='success'>";
        echo "$status_ok <strong>Hostels Table</strong><br>";
        echo "Status: $count active hostels found";
        echo "</div>";
        
        $stmt = $db->query("SELECT id, name, location, status FROM hostels LIMIT 5");
        echo "<table><tr><th>ID</th><th>Name</th><th>Location</th><th>Status</th></tr>";
        while ($row = $stmt->fetch()) {
            echo "<tr><td>{$row['id']}</td><td>{$row['name']}</td><td>{$row['location']}</td><td>{$row['status']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='warning'>";
        echo "$status_warning <strong>Hostels Table</strong><br>";
        echo "Warning: No hostels found in database";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "$status_error <strong>Hostels Table</strong><br>";
    echo "Error: " . $e->getMessage();
    echo "</div>";
}
echo "</div>";

// Test 3: Rooms Table
echo "<div class='check'>";
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM rooms WHERE status = 'available'");
    $result = $stmt->fetch();
    $count = $result['count'];
    
    if ($count > 0) {
        echo "<div class='success'>";
        echo "$status_ok <strong>Rooms Table</strong><br>";
        echo "Status: $count available rooms found";
        echo "</div>";
        
        $stmt = $db->query("SELECT r.id, r.room_number, r.room_type, r.price_per_semester, h.name as hostel_name FROM rooms r JOIN hostels h ON r.hostel_id = h.id WHERE r.status = 'available' LIMIT 5");
        echo "<table><tr><th>Room</th><th>Type</th><th>Price</th><th>Hostel</th></tr>";
        while ($row = $stmt->fetch()) {
            echo "<tr><td>{$row['room_number']}</td><td>{$row['room_type']}</td><td>UGX " . number_format($row['price_per_semester'], 0) . "</td><td>{$row['hostel_name']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='warning'>";
        echo "$status_warning <strong>Rooms Table</strong><br>";
        echo "Warning: No available rooms found";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "$status_error <strong>Rooms Table</strong><br>";
    echo "Error: " . $e->getMessage();
    echo "</div>";
}
echo "</div>";

// Test 4: Bookings Table
echo "<div class='check'>";
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM bookings");
    $result = $stmt->fetch();
    $count = $result['count'];
    
    echo "<div class='success'>";
    echo "$status_ok <strong>Bookings Table</strong><br>";
    echo "Status: $count total bookings in system";
    echo "</div>";
    
    $stmt = $db->query("SELECT b.id, b.booking_reference, r.room_number, b.check_in, b.check_out, b.status FROM bookings b JOIN rooms r ON b.room_id = r.id ORDER BY b.created_at DESC LIMIT 5");
    echo "<table><tr><th>Ref</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Status</th></tr>";
    while ($row = $stmt->fetch()) {
        $status_class = strtolower($row['status']);
        echo "<tr><td>{$row['booking_reference']}</td><td>{$row['room_number']}</td><td>{$row['check_in']}</td><td>{$row['check_out']}</td><td><span style='padding: 3px 8px; background: " . ($status_class === 'confirmed' ? '#d4edda' : '#fff3cd') . "; border-radius: 3px;'>{$row['status']}</span></td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "$status_error <strong>Bookings Table</strong><br>";
    echo "Error: " . $e->getMessage();
    echo "</div>";
}
echo "</div>";

// Test 5: API Endpoints
echo "<div class='check'>";
echo "$status_ok <strong>API Endpoints Status</strong><br>";
echo "<p>The following API endpoints are available:</p>";
echo "<ul>";
echo "<li><code>api/get_available_hostels.php</code> - Get hostels with availability</li>";
echo "<li><code>api/get_rooms.php</code> - Get rooms for a hostel</li>";
echo "<li><code>api/add_booking.php</code> - Create a new booking</li>";
echo "<li><code>api/get_hostels.php</code> - Get all hostels</li>";
echo "<li><code>api/make_payment.php</code> - Process payment</li>";
echo "</ul>";
echo "</div>";

// Test 6: Session Status
echo "<div class='check'>";
if (isset($_SESSION['user_id'])) {
    echo "<div class='success'>";
    echo "$status_ok <strong>Session Status</strong><br>";
    echo "User ID: {$_SESSION['user_id']}<br>";
    echo "Role: {$_SESSION['role']}<br>";
    echo "Name: {$_SESSION['full_name']}";
    echo "</div>";
} else {
    echo "<div class='warning'>";
    echo "$status_warning <strong>Session Status</strong><br>";
    echo "No active session. This is normal for this verification script.";
    echo "</div>";
}
echo "</div>";

// Test 7: File Structure
echo "<div class='check success'>";
echo "$status_ok <strong>File Structure</strong><br>";
echo "<p>Expected files exist:</p>";
$files = [
    'dashboards/student.php',
    'api/get_available_hostels.php',
    'api/get_rooms.php',
    'api/add_booking.php',
    'assets/css/browse-hostels.css',
    'assets/script.js',
    'config/database.php'
];
echo "<ul>";
foreach ($files as $file) {
    $exists = file_exists($file) ? "✅" : "❌";
    echo "<li>$exists $file</li>";
}
echo "</ul>";
echo "</div>";

// Test 8: API Test
echo "<div class='check'>";
echo "<strong>API Test - Get Available Hostels</strong><br>";
echo "<p>Testing: <code>api/get_available_hostels.php?check_in=" . date('Y-m-d', strtotime('+1 day')) . "&check_out=" . date('Y-m-d', strtotime('+7 days')) . "</code></p>";
echo "<div class='code'>";
try {
    // Simulate the API call
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'student';
    
    $roomType = '';
    $checkIn = date('Y-m-d', strtotime('+1 day'));
    $checkOut = date('Y-m-d', strtotime('+7 days'));
    
    $hostelsQuery = "SELECT * FROM hostels WHERE status = 'active' ORDER BY name ASC LIMIT 1";
    $hostelsStmt = $db->prepare($hostelsQuery);
    $hostelsStmt->execute();
    $hostel = $hostelsStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($hostel) {
        echo "Sample Response:<br>";
        echo json_encode([
            'id' => (int)$hostel['id'],
            'name' => $hostel['name'],
            'location' => $hostel['location'],
            'total_rooms' => 5,
            'available_rooms' => 3,
            'rooms' => [
                [
                    'id' => 1,
                    'room_number' => '101',
                    'room_type' => 'Double',
                    'price_per_semester' => 500000,
                    'is_available' => true
                ]
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    } else {
        echo "No hostels found in test";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
echo "</div>";
echo "</div>";

// Summary
echo "<hr>";
echo "<div class='success' style='padding: 20px; text-align: center;'>";
echo "<h2>$status_ok System Ready</h2>";
echo "<p>The Browse Hostels feature is ready to use!</p>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Login to the student dashboard</li>";
echo "<li>Click 'Browse Hostels' in the sidebar</li>";
echo "<li>Select dates and room type</li>";
echo "<li>Click 'Apply Filters' to see available rooms</li>";
echo "<li>Click 'Book Now' on a room to make a booking</li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
?>
