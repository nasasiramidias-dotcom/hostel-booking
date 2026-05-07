<?php


// Redirect to the correct API endpoint (keep relative paths consistent)
header('Location: ./api/get_available_hostels.php?' . http_build_query($_GET));
exit();
?><?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$db = Database::getInstance()->getConnection();

$room_type = $_GET['room_type'] ?? '';
$check_in = $_GET['check_in'] ?? date('Y-m-d');
$check_out = $_GET['check_out'] ?? date('Y-m-d', strtotime('+1 day'));

$query = "SELECT h.*, 
          COUNT(DISTINCT r.id) as total_rooms,
          h.image,
          h.image_thumbnail
          FROM hostels h
          LEFT JOIN rooms r ON h.id = r.hostel_id
          WHERE h.status = 'active'";

if ($room_type) {
    $query .= " AND r.room_type = :room_type";
}

$query .= " GROUP BY h.id ORDER BY h.name";

$stmt = $db->prepare($query);
if ($room_type) {
    $stmt->bindParam(':room_type', $room_type);
}
$stmt->execute();
$hostels = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get rooms for each hostel
foreach ($hostels as &$hostel) {
    $roomQuery = "SELECT r.*, 
                  CASE WHEN EXISTS (
                      SELECT 1 FROM bookings b 
                      WHERE b.room_id = r.id 
                      AND b.status = 'confirmed'
                      AND b.check_in < :check_out 
                      AND b.check_out > :check_in
                  ) THEN 0 ELSE 1 END as is_available
                  FROM rooms r
                  WHERE r.hostel_id = :hostel_id";
    
    $roomStmt = $db->prepare($roomQuery);
    $roomStmt->execute([
        ':hostel_id' => $hostel['id'],
        ':check_in' => $check_in,
        ':check_out' => $check_out
    ]);
    $rooms = $roomStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add booked ranges for unavailable rooms
    foreach ($rooms as &$room) {
        if (!$room['is_available']) {
            $rangeQuery = "SELECT check_in, check_out, status 
                          FROM bookings 
                          WHERE room_id = :room_id 
                          AND status = 'confirmed'
                          AND check_in < :check_out 
                          AND check_out > :check_in";
            $rangeStmt = $db->prepare($rangeQuery);
            $rangeStmt->execute([
                ':room_id' => $room['id'],
                ':check_out' => $check_out,
                ':check_in' => $check_in
            ]);
            $room['booked_ranges'] = $rangeStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    $hostel['rooms'] = $rooms;
    $hostel['available_rooms'] = count(array_filter($rooms, fn($r) => $r['is_available']));
}

echo json_encode($hostels);
