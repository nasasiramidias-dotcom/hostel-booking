<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'finance') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

try {
    $payment_id = $_POST['payment_id'];
    $booking_id = $_POST['booking_id'];

    $db = Database::getInstance()->getConnection();

    // Start transaction
    $db->beginTransaction();

    try {
        // Verify payment is in 'paid' or 'pending' status before confirming
        $verifyStmt = $db->prepare("SELECT id FROM payments WHERE id = ? AND status IN ('paid', 'pending')");
        $verifyStmt->execute([$payment_id]);
        if (!$verifyStmt->fetch()) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Payment is not eligible for confirmation']);
            exit();
        }

        // Update payment status
        $stmt = $db->prepare("UPDATE payments SET status = 'confirmed', confirmed_by = ?, confirmed_at = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $payment_id]);

        // Update booking status to confirmed
        $stmt2 = $db->prepare("UPDATE bookings SET status = 'confirmed', finance_approved_by = ? WHERE id = ?");
        $stmt2->execute([$_SESSION['user_id'], $booking_id]);

        // Update room status to occupied
        $stmt3 = $db->prepare("UPDATE rooms SET status = 'occupied' WHERE id = (SELECT room_id FROM bookings WHERE id = ?)");
        $stmt3->execute([$booking_id]);

        $db->commit();

        Database::getInstance()->logActivity($_SESSION['user_id'], 'confirm_payment', "Payment $payment_id confirmed, booking $booking_id confirmed");
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

