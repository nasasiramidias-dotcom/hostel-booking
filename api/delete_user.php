<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

try {
    $user_id = $_POST['user_id'];

    // Prevent deleting self
    if ($user_id == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
        exit();
    }

    $db = Database::getInstance()->getConnection();

    // Verify the user exists
    $check = $db->prepare("SELECT id, full_name, role FROM users WHERE id = ?");
    $check->execute([$user_id]);
    $user = $check->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }

    // Prevent deleting other super admins for security
    if ($user['role'] === 'super_admin') {
        echo json_encode(['success' => false, 'message' => 'Cannot delete super admin users']);
        exit();
    }

    // Delete the user
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $result = $stmt->execute([$user_id]);

    if ($result) {
        Database::getInstance()->logActivity($_SESSION['user_id'], 'delete_user', "User '{$user['full_name']}' (ID $user_id, role: {$user['role']}) deleted");
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
