<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

try {
    $booking_id = $_POST['booking_id'];
    $student_id = $_SESSION['user_id'];

    $db = Database::getInstance()->getConnection();

    // Verify the booking belongs to this student and is pending
    $check = $db->prepare("SELECT id FROM bookings WHERE id = ? AND student_id = ? AND status = 'pending'");
    $check->execute([$booking_id, $student_id]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Booking not found or cannot be cancelled']);
        exit();
    }

    $stmt = $db->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
    $result = $stmt->execute([$booking_id]);

    if ($result) {
        Database::getInstance()->logActivity($student_id, 'cancel_booking', "Booking $booking_id cancelled");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel booking']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

