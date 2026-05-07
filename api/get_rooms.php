<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit();
}

try {
    $hostel_id = $_GET['hostel_id'] ?? 0;
    $db = Database::getInstance()->getConnection();
    
    // Build dynamic query with filters
    $whereParts = ["r.hostel_id = ?"];
    $params = [$hostel_id];

    if (!empty($_GET['check_in']) && !empty($_GET['check_out'])) {
        $checkIn = $_GET['check_in'];
        $checkOut = $_GET['check_out'];

        // Base filter
        $whereParts[] = "r.status = 'available'";

        // Exclude rooms with overlapping bookings
        $whereParts[] = "NOT EXISTS (
            SELECT 1 FROM bookings b
            WHERE b.room_id = r.id
              AND b.status IN ('pending','approved','confirmed')
              AND (b.check_in <= ? AND b.check_out >= ?)
        )";

        $params[] = $checkOut;
        $params[] = $checkIn;
    } else {
        $whereParts[] = "r.status = 'available'";
    }

    if (!empty($_GET['type'])) {
        $whereParts[] = "r.room_type = ?";
        $params[] = $_GET['type'];
    }

    if (!empty($_GET['min_price'])) {
        $whereParts[] = "r.price_per_semester >= ?";
        $params[] = $_GET['min_price'];
    }

    if (!empty($_GET['max_price'])) {
        $whereParts[] = "r.price_per_semester <= ?";
        $params[] = $_GET['max_price'];
    }

    $where = implode(' AND ', $whereParts);
    $query = "SELECT id, room_number, room_type, price_per_semester, capacity, hostel_id FROM rooms WHERE $where ORDER BY room_number ASC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $rooms = $stmt->fetchAll();

    echo json_encode($rooms);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

