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
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $student_id = $_SESSION['user_id'];
    $payment_id = $_POST['payment_id'] ?? null;
    $payment_method = $_POST['payment_method'] ?? null;

    $valid_methods = ['cash', 'bank_transfer', 'mobile_money', 'credit_card'];

    if (!$payment_id || !$payment_method) {
        echo json_encode(['success' => false, 'message' => 'Payment ID and method are required']);
        exit();
    }

    if (!in_array($payment_method, $valid_methods)) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment method']);
        exit();
    }

    $db = Database::getInstance()->getConnection();

    // Verify the payment belongs to this student
    $check = $db->prepare("SELECT p.id FROM payments p JOIN bookings b ON p.booking_id = b.id WHERE p.id = ? AND b.student_id = ? AND p.status = 'pending'");
    $check->execute([$payment_id, $student_id]);

    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Payment not found or not eligible for update']);
        exit();
    }

    // Update payment method
    $stmt = $db->prepare("UPDATE payments SET payment_method = ? WHERE id = ?");
    $result = $stmt->execute([$payment_method, $payment_id]);

    if ($result) {
        Database::getInstance()->logActivity($student_id, 'update_payment_method', "Payment method updated to $payment_method for payment #$payment_id");
        echo json_encode(['success' => true, 'message' => 'Payment method updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update payment method']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

