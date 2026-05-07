<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hostel_admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

try {
    $room_id = $_POST['room_id'];

    $db = Database::getInstance()->getConnection();

    // Verify the room exists
    $check = $db->prepare("SELECT id, room_number, hostel_id FROM rooms WHERE id = ?");
    $check->execute([$room_id]);
    $room = $check->fetch();

    if (!$room) {
        echo json_encode(['success' => false, 'message' => 'Room not found']);
        exit();
    }

    // Check if room has active bookings
    $bookingCheck = $db->prepare("SELECT COUNT(*) FROM bookings WHERE room_id = ? AND status IN ('pending', 'approved')");
    $bookingCheck->execute([$room_id]);
    $activeBookings = $bookingCheck->fetchColumn();

    if ($activeBookings > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete room with active or pending bookings']);
        exit();
    }

    // Delete the room
    $stmt = $db->prepare("DELETE FROM rooms WHERE id = ?");
    $result = $stmt->execute([$room_id]);

    if ($result) {
        Database::getInstance()->logActivity($_SESSION['user_id'], 'delete_room', "Room '{$room['room_number']}' (ID $room_id) deleted");
        echo json_encode(['success' => true, 'message' => 'Room deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete room']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

