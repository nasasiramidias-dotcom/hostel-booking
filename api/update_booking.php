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
    $booking_id = $_POST['booking_id'] ?? null;
    $status = $_POST['status'] ?? null;

    // Validate booking_id and status are provided
    if (!$booking_id || !$status) {
        echo json_encode(['success' => false, 'message' => 'Booking ID and status are required']);
        exit();
    }

    // Validate status is one of allowed values
    $valid_statuses = ['pending', 'approved', 'rejected', 'confirmed', 'cancelled'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid booking status']);
        exit();
    }

    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("UPDATE bookings SET status = ?, approved_at = NOW(), hostel_approved_by = ? WHERE id = ?");
    $result = $stmt->execute([$status, $_SESSION['user_id'], $booking_id]);

    if ($result) {
        Database::getInstance()->logActivity($_SESSION['user_id'], 'update_booking', "Booking $booking_id updated to $status");

// If approved, create a pending payment record
        if ($status === 'approved') {
            $booking = $db->prepare("SELECT b.*, r.price_per_semester FROM bookings b JOIN rooms r ON b.room_id = r.id WHERE b.id = ?");
            $booking->execute([$booking_id]);
            $b = $booking->fetch();

            if ($b) {
                $pay = $db->prepare("INSERT INTO payments (booking_id, amount, status, payment_method, created_at) VALUES (?, ?, 'pending', 'pending', NOW())");
                $pay->execute([$booking_id, $b['price_per_semester']]);
            }
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

