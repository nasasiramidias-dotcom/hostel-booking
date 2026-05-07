<?php
session_start();
require_once 'config/database.php';

$db = Database::getInstance()->getConnection();

echo "<html><head><title>Browse Hostels - Data Seeder</title><style>";
echo "body { font-family: Arial; margin: 20px; background: #f5f5f5; }";
echo ".section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }";
echo "h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }";
echo ".success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }";
echo ".info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }";
echo "button { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }";
echo "button:hover { background: #0056b3; }";
echo "</style></head><body>";

echo "<h1>🏨 Browse Hostels - Add Test Data</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_test_data'])) {
    try {
        // Check if test data already exists
        $stmt = $db->query("SELECT COUNT(*) as count FROM hostels WHERE name LIKE 'Test%' OR name LIKE 'Sample%'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            echo "<div class='info'>ℹ️ Test data already exists in database. Skipping to avoid duplicates.</div>";
        } else {
            // Add 3 test hostels
            $hostels = [
                ['name' => 'Test Hostel Downtown', 'location' => 'City Center', 'description' => 'Modern hostel in the heart of the city'],
                ['name' => 'Test Hostel Campus', 'location' => 'University Area', 'description' => 'Close to campus, student-friendly'],
                ['name' => 'Test Hostel Riverside', 'location' => 'Riverside District', 'description' => 'Peaceful location with nice views'],
            ];
            
            $hostelIds = [];
            foreach ($hostels as $hostel) {
                $stmt = $db->prepare("INSERT INTO hostels (name, location, description, status, created_at) VALUES (?, ?, ?, 'active', NOW())");
                $stmt->execute([$hostel['name'], $hostel['location'], $hostel['description']]);
                $hostelIds[] = $db->lastInsertId();
            }
            
            echo "<div class='success'>✅ Added " . count($hostels) . " test hostels</div>";
            
            // Add rooms to each hostel
            $roomCount = 0;
            $roomTypes = ['Single', 'Double', 'Triple'];
            
            foreach ($hostelIds as $hostelId) {
                for ($i = 1; $i <= 5; $i++) {
                    $roomType = $roomTypes[($i - 1) % count($roomTypes)];
                    $roomNumber = '10' . str_pad($i, 2, '0', STR_PAD_LEFT);
                    $price = 500000 + ($i * 50000);
                    
                    $stmt = $db->prepare("INSERT INTO rooms (hostel_id, room_number, room_type, price_per_semester, capacity, status, created_at) VALUES (?, ?, ?, ?, ?, 'available', NOW())");
                    $stmt->execute([$hostelId, $roomNumber, $roomType, $price, $i % 3 + 1]);
                    $roomCount++;
                }
            }
            
            echo "<div class='success'>✅ Added " . $roomCount . " test rooms</div>";
            
            // Verify the data
            $stmt = $db->query("SELECT COUNT(*) as count FROM hostels WHERE status='active'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $activeHostels = $result['count'];
            
            $stmt = $db->query("SELECT COUNT(*) as count FROM rooms WHERE status='available'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $availRooms = $result['count'];
            
            echo "<div class='success'>";
            echo "<strong>🎉 Success!</strong><br>";
            echo "✅ " . $activeHostels . " active hostels<br>";
            echo "✅ " . $availRooms . " available rooms<br><br>";
            echo "The Browse Hostels feature should now work! Go to the student dashboard and click 'Browse Hostels'.<br>";
            echo "<a href='dashboards/student.php' style='color: #155724; text-decoration: underline;'>Open Student Dashboard →</a>";
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
        echo "❌ Error: " . $e->getMessage();
        echo "</div>";
    }
} else {
    // Show initial form
    echo "<div class='section'>";
    echo "<h2>Add Test Data</h2>";
    echo "<p>This will add test hostels and rooms to your database so you can see the Browse Hostels feature working.</p>";
    echo "<p><strong>What will be added:</strong></p>";
    echo "<ul>";
    echo "<li>3 test hostels (Downtown, Campus, Riverside)</li>";
    echo "<li>5 rooms per hostel (15 total)</li>";
    echo "<li>Mix of Single, Double, Triple rooms</li>";
    echo "<li>Realistic pricing from UGX 500,000 - 750,000 per semester</li>";
    echo "</ul>";
    
    echo "<form method='POST'>";
    echo "<button type='submit' name='add_test_data'>➕ Add Test Data Now</button>";
    echo "</form>";
    echo "</div>";
    
    // Show current status
    echo "<div class='section'>";
    echo "<h2>Current Database Status</h2>";
    
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM hostels");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p><strong>Hostels:</strong> " . $result['count'] . "</p>";
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM rooms");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p><strong>Rooms:</strong> " . $result['count'] . "</p>";
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM bookings");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p><strong>Bookings:</strong> " . $result['count'] . "</p>";
    } catch (Exception $e) {
        echo "<p>Error checking database: " . $e->getMessage() . "</p>";
    }
    
    echo "</div>";
}

echo "</body></html>";
?>
