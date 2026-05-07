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
    $hostel_id = $_POST['hostel_id'] ?? null;
    $room_number = trim($_POST['room_number'] ?? '');
    $room_type = trim($_POST['room_type'] ?? '');
    $price_per_semester = $_POST['price_per_semester'] ?? $_POST['price'] ?? null;
    $capacity = $_POST['capacity'] ?? 1;

    // Validate required fields
    if (!$hostel_id || empty($room_number) || empty($room_type) || $price_per_semester === null) {
        echo json_encode(['success' => false, 'message' => 'Hostel ID, room number, room type, and price are required']);
        exit();
    }

    // Validate price is a valid number
    if (!is_numeric($price_per_semester) || (float)$price_per_semester < 0) {
        echo json_encode(['success' => false, 'message' => 'Price must be a valid positive number']);
        exit();
    }

    // Validate capacity is a valid number
    if (!is_numeric($capacity) || (int)$capacity < 1) {
        echo json_encode(['success' => false, 'message' => 'Capacity must be at least 1']);
        exit();
    }

    // Validate hostel exists
    $db = Database::getInstance()->getConnection();
    $checkHostel = $db->prepare("SELECT id FROM hostels WHERE id = ?");
    $checkHostel->execute([$hostel_id]);
    if (!$checkHostel->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Invalid hostel ID']);
        exit();
    }

    $stmt = $db->prepare("INSERT INTO rooms (hostel_id, room_number, room_type, price_per_semester, capacity, status, created_at) VALUES (?, ?, ?, ?, ?, 'available', NOW())");
    $result = $stmt->execute([$hostel_id, $room_number, $room_type, (float)$price_per_semester, (int)$capacity]);

    if ($result) {
        $roomId = $db->lastInsertId();
        Database::getInstance()->logActivity($_SESSION['user_id'], 'add_room', "Room '$room_number' added to hostel $hostel_id with ID $roomId");
        header("Location: ../dashboards/hostel_admin.php?page=manage-rooms");
        exit();
    } else {
    echo json_encode(['success' => false, 'message' => 'Room already exists for this hostel (unique constraint). Try different room number.']); // Line 21
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

