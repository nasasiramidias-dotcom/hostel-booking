<?php
/**
 * AJAX Endpoint: Check room availability for a date range
 * 
 * Returns availability status and current available beds for a given room
 * and date range.
 * 
 * Required Parameters:
 *   - room_id: INT
 *   - check_in: DATE (YYYY-MM-DD)
 *   - check_out: DATE (YYYY-MM-DD)
 * 
 * Response: JSON object with availability status and available beds
 */

session_start();
header('Content-Type: application/json');

require_once '../config/database.php';

// Ensure logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get and validate parameters
    $roomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : null;
    $checkInDate = isset($_GET['check_in']) ? $_GET['check_in'] : null;
    $checkOutDate = isset($_GET['check_out']) ? $_GET['check_out'] : null;
    
    // Validate required parameters
    if (!$roomId || !$checkInDate || !$checkOutDate) {
        http_response_code(400);
        echo json_encode(['error' => 'room_id, check_in, and check_out are required']);
        exit();
    }
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkInDate) || 
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkOutDate)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD']);
        exit();
    }
    
    // Validate dates
    $today = date('Y-m-d');
    if ($checkInDate < $today) {
        http_response_code(400);
        echo json_encode(['error' => 'Check-in date cannot be in the past']);
        exit();
    }
    
    if ($checkOutDate <= $checkInDate) {
        http_response_code(400);
        echo json_encode(['error' => 'Check-out date must be after check-in date']);
        exit();
    }
    
    // Get room details
    $roomStmt = $db->prepare("
        SELECT id, room_number, available_beds, status 
        FROM rooms 
        WHERE id = ? 
        LIMIT 1
    ");
    $roomStmt->execute([$roomId]);
    $room = $roomStmt->fetch();
    
    if (!$room) {
        http_response_code(404);
        echo json_encode(['error' => 'Room not found']);
        exit();
    }
    
    // Check for overlapping bookings
    $overlapStmt = $db->prepare("
        SELECT COUNT(*) as overlap_count 
        FROM bookings 
        WHERE room_id = ? 
        AND status IN ('pending', 'confirmed') 
        AND check_in_date < ? 
        AND check_out_date > ?
    ");
    $overlapStmt->execute([$roomId, $checkOutDate, $checkInDate]);
    $result = $overlapStmt->fetch();
    
    $isAvailable = $result['overlap_count'] == 0 && $room['available_beds'] > 0;
    
    // Return availability info
    echo json_encode([
        'available' => $isAvailable,
        'available_beds' => (int)$room['available_beds'],
        'room_status' => $room['status'],
        'overlap_count' => (int)$result['overlap_count']
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in check_availability.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit();
} catch (Exception $e) {
    error_log("Error in check_availability.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
    exit();
}
?>
