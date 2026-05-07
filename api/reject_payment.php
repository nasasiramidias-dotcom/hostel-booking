<?php
session_start();
require_once '../config/database.php';

// Verify user is finance role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'finance') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Validate input
if (!isset($_POST['payment_id']) || !isset($_POST['reason'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$paymentId = intval($_POST['payment_id']);
$reason = trim($_POST['reason']);

if (empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Rejection reason cannot be empty']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get payment details
    $stmt = $db->prepare("SELECT * FROM payments WHERE id = ?");
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
        exit();
    }
    
    // Check if payment is in a rejectable state
    if ($payment['status'] !== 'paid' && $payment['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Cannot reject payment with status: ' . $payment['status']]);
        exit();
    }
    
    // Update payment status to 'rejected' with reason
    $updateStmt = $db->prepare("UPDATE payments SET status = 'rejected', rejection_reason = ?, rejected_at = NOW(), rejected_by = ? WHERE id = ?");
    $result = $updateStmt->execute([$reason, $_SESSION['user_id'], $paymentId]);
    
    if ($result) {
        // Get student email for notification
        $bookingStmt = $db->prepare("SELECT b.student_id FROM bookings b WHERE b.id = ?");
        $bookingStmt->execute([$payment['booking_id']]);
        $booking = $bookingStmt->fetch();
        
        if ($booking) {
            $userStmt = $db->prepare("SELECT email FROM users WHERE id = ?");
            $userStmt->execute([$booking['student_id']]);
            $user = $userStmt->fetch();
            
            // Log activity
            Database::getInstance()->logActivity($_SESSION['user_id'], 'payment_rejected', 'Payment #' . $paymentId . ' rejected. Reason: ' . $reason);
        }
        
        echo json_encode(['success' => true, 'message' => 'Payment rejected successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update payment status']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
