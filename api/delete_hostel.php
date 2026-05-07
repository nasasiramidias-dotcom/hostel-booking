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
    $hostel_id = $_POST['hostel_id'];

    $db = Database::getInstance()->getConnection();

    // Verify the hostel exists
    $check = $db->prepare("SELECT id, name FROM hostels WHERE id = ?");
    $check->execute([$hostel_id]);
    $hostel = $check->fetch();

    if (!$hostel) {
        echo json_encode(['success' => false, 'message' => 'Hostel not found']);
        exit();
    }

    // Delete related rooms first (cascade)
    $deleteRooms = $db->prepare("DELETE FROM rooms WHERE hostel_id = ?");
    $deleteRooms->execute([$hostel_id]);

    // Delete the hostel
    $stmt = $db->prepare("DELETE FROM hostels WHERE id = ?");
    $result = $stmt->execute([$hostel_id]);

    if ($result) {
        Database::getInstance()->logActivity($_SESSION['user_id'], 'delete_hostel', "Hostel '{$hostel['name']}' (ID $hostel_id) deleted");
        echo json_encode(['success' => true, 'message' => 'Hostel deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete hostel']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

