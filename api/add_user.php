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
    // Validate all required fields are present
    if (!isset($_POST['full_name'], $_POST['username'], $_POST['email'], $_POST['role'], $_POST['password'])) {
        header("Location: ../dashboards/super_admin.php?page=users&error=" . urlencode('All fields are required'));
        exit();
    }

    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $password = $_POST['password'];

    // Validate inputs
    $error = '';
    if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif (!in_array($role, ['student', 'hostel_admin', 'finance', 'super_admin'])) {
        $error = 'Invalid role selected';
    }

    if (!empty($error)) {
        header("Location: ../dashboards/super_admin.php?page=users&error=" . urlencode($error));
        exit();
    }

    // Use secure password hashing instead of md5
    $password = password_hash($password, PASSWORD_BCRYPT);

    $db = Database::getInstance()->getConnection();

    // Check username and email separately for clearer error messages
    $checkUser = $db->prepare("SELECT id, username FROM users WHERE username = ?");
    $checkUser->execute([$username]);
    $userExists = $checkUser->fetch();

    $checkEmail = $db->prepare("SELECT id, email FROM users WHERE email = ?");
    $checkEmail->execute([$email]);
    $emailExists = $checkEmail->fetch();

    if ($userExists && $emailExists) {
        $error = "Both username ('" . htmlspecialchars($userExists['username']) . "') and email ('" . htmlspecialchars($emailExists['email']) . "') already exist";
    } elseif ($userExists) {
        $error = "Username '" . htmlspecialchars($userExists['username']) . "' already exists (ID: " . $userExists['id'] . ")";
    } elseif ($emailExists) {
        $error = "Email '" . htmlspecialchars($emailExists['email']) . "' already exists (ID: " . $emailExists['id'] . ")";
    }

    if (!empty($error)) {
        header("Location: ../dashboards/super_admin.php?page=users&error=" . urlencode($error));
        exit();
    }

    $stmt = $db->prepare("INSERT INTO users (full_name, username, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
    $result = $stmt->execute([$full_name, $username, $email, $password, $role]);

    if ($result) {
        $userId = $db->lastInsertId();
        Database::getInstance()->logActivity($_SESSION['user_id'], 'add_user', "User '$username' ($role) added with ID $userId");
        header("Location: ../dashboards/super_admin.php?page=users&success=User+added+successfully");
        exit();
    } else {
        header("Location: ../dashboards/super_admin.php?page=users&error=Failed+to+add+user");
        exit();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

