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
    $transaction_id = $_POST['transaction_id'] ?? null;

    $valid_methods = ['cash', 'bank_transfer', 'mobile_money', 'credit_card'];

    if (!$payment_id || !$payment_method) {
        echo json_encode(['success' => false, 'message' => 'Payment ID and method are required']);
        exit();
    }

    if (!in_array($payment_method, $valid_methods)) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment method']);
        exit();
    }

    // For non-cash methods, transaction reference is required
    if ($payment_method !== 'cash' && empty($transaction_id)) {
        echo json_encode(['success' => false, 'message' => 'Transaction ID is required for this payment method']);
        exit();
    }

    $db = Database::getInstance()->getConnection();

    // Verify the payment belongs to this student and is pending
    $check = $db->prepare("SELECT p.id, p.status FROM payments p JOIN bookings b ON p.booking_id = b.id WHERE p.id = ? AND b.student_id = ? AND p.status = 'pending'");
    $check->execute([$payment_id, $student_id]);
    $payment = $check->fetch();

    if (!$payment) {
        echo json_encode(['success' => false, 'message' => 'Payment not found or not eligible for payment']);
        exit();
    }

    // Handle file upload for bank transfer
    $payment_proof = null;
    if ($payment_method === 'bank_transfer' && isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/payment_proofs/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_name = 'payment_' . $payment_id . '_' . time() . '_' . basename($_FILES['payment_proof']['name']);
        $file_path = $upload_dir . $file_name;

        // Use proper MIME type detection instead of relying on $_FILES['type']
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $file_type = finfo_file($finfo, $_FILES['payment_proof']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($file_type, $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and PDF are allowed.']);
            exit();
        }

        if ($_FILES['payment_proof']['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File size must be less than 5MB.']);
            exit();
        }

        if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $file_path)) {
            $payment_proof = 'uploads/payment_proofs/' . $file_name;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload payment proof.']);
            exit();
        }
    }

    // For credit card, simulate a successful payment
    // For cash, just record the intention to pay cash
    // For mobile money, record the transaction ID

    // Update payment record
    $stmt = $db->prepare("UPDATE payments SET payment_method = ?, transaction_id = ?, status = 'paid', payment_date = NOW() WHERE id = ?");
    $result = $stmt->execute([$payment_method, $transaction_id, $payment_id]);

    if ($result) {
        Database::getInstance()->logActivity($student_id, 'make_payment', "Payment #$payment_id made using $payment_method");
        echo json_encode(['success' => true, 'message' => 'Payment submitted successfully! It will be confirmed by the finance team shortly.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to process payment']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

