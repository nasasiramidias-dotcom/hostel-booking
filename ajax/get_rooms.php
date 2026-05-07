<?php
/**
 * AJAX Endpoint: Get available rooms with filtering
 * 
 * This endpoint returns a JSON list of available rooms for a given hostel,
 * with optional filtering by room type, price range, and date availability.
 * 
 * Required Parameters:
 *   - hostel_id: INT (required)
 * 
 * Optional Parameters:
 *   - room_type: STRING (single, double, triple, dorm)
 *   - min_price: FLOAT
 *   - max_price: FLOAT
 *   - check_in: DATE (YYYY-MM-DD) - for availability check
 *   - check_out: DATE (YYYY-MM-DD) - for availability check
 *   - sort_by: STRING (price_asc, price_desc)
 * 
 * Response: JSON array of room objects
 */

session_start();
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/hostel_functions.php';

// Ensure logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Validate required parameters
    $hostelId = isset($_GET['hostel_id']) ? (int)$_GET['hostel_id'] : null;
    
    if (!$hostelId) {
        http_response_code(400);
        echo json_encode(['error' => 'hostel_id is required']);
        exit();
    }
    
    // Verify hostel exists
    $hostelStmt = $db->prepare("SELECT id FROM hostels WHERE id = ? LIMIT 1");
    $hostelStmt->execute([$hostelId]);
    if (!$hostelStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Hostel not found']);
        exit();
    }
    
    // Get optional filters
    $roomType = isset($_GET['room_type']) && $_GET['room_type'] !== '' ? $_GET['room_type'] : null;
    $minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : null;
    $maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : null;
    $checkInDate = isset($_GET['check_in']) ? $_GET['check_in'] : null;
    $checkOutDate = isset($_GET['check_out']) ? $_GET['check_out'] : null;
    $sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'price_asc';
    
    // Build query
    $query = "
        SELECT 
            r.id,
            r.room_number,
            r.room_type,
            r.capacity,
            r.price_per_month,
            r.available_beds,
            r.total_beds,
            r.amenities,
            r.status
        FROM rooms r
        WHERE r.hostel_id = ? AND r.status IN ('available', 'full')
    ";
    
    $params = [$hostelId];
    
    // Add room type filter
    if ($roomType && in_array($roomType, ['single', 'double', 'triple', 'dorm'])) {
        $query .= " AND r.room_type = ?";
        $params[] = $roomType;
    }
    
    // Add price filters
    if ($minPrice !== null && $minPrice >= 0) {
        $query .= " AND r.price_per_month >= ?";
        $params[] = $minPrice;
    }
    
    if ($maxPrice !== null && $maxPrice >= 0) {
        $query .= " AND r.price_per_month <= ?";
        $params[] = $maxPrice;
    }
    
    // Add availability filter if dates provided
    if ($checkInDate && $checkOutDate) {
        // Validate date format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkInDate) && 
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkOutDate)) {
            
            $query .= "
                AND r.available_beds > 0
                AND NOT EXISTS (
                    SELECT 1 FROM bookings b 
                    WHERE b.room_id = r.id 
                    AND b.status IN ('pending', 'confirmed')
                    AND b.check_in_date < ? 
                    AND b.check_out_date > ?
                )
            ";
            $params[] = $checkOutDate;
            $params[] = $checkInDate;
        }
    } else {
        // Without date filter, still check for availability beds
        $query .= " AND r.available_beds > 0";
    }
    
    // Add sorting
    if ($sortBy === 'price_desc') {
        $query .= " ORDER BY r.price_per_month DESC";
    } else {
        $query .= " ORDER BY r.price_per_month ASC";
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $rooms = $stmt->fetchAll();
    
    // Format response
    $response = [];
    foreach ($rooms as $room) {
        $response[] = [
            'id' => (int)$room['id'],
            'room_number' => $room['room_number'],
            'room_type' => $room['room_type'],
            'capacity' => (int)$room['capacity'],
            'price_per_month' => (float)$room['price_per_month'],
            'available_beds' => (int)$room['available_beds'],
            'total_beds' => (int)$room['total_beds'],
            'amenities' => renderAmenities($room['amenities']),
            'status' => $room['status']
        ];
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Database error in get_rooms.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit();
} catch (Exception $e) {
    error_log("Error in get_rooms.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
    exit();
}
?>
