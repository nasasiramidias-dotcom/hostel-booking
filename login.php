<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    // Support bcrypt (password_hash) and legacy hashes
    $storedHash = $user['password'] ?? '';
    $isBcrypt = is_string($storedHash) && str_starts_with($storedHash, '$2y$');
    $isBcrypt2 = is_string($storedHash) && str_starts_with($storedHash, '$2a$');
    $isBcrypt3 = is_string($storedHash) && str_starts_with($storedHash, '$2b$');
    $isBcrypt = $isBcrypt || $isBcrypt2 || $isBcrypt3;

    $passwordValid = false;
    if ($user) {
        if ($isBcrypt) {
            $passwordValid = password_verify($password, $storedHash);
        } else {
            // legacy fallbacks (md5/plain/sha1)
            $passwordValid = false;
            if (is_string($storedHash) && preg_match('/^[a-f0-9]{32}$/i', $storedHash)) {
                $passwordValid = (md5($password) === $storedHash);
            } elseif (is_string($storedHash) && preg_match('/^[a-f0-9]{40}$/i', $storedHash)) {
                $passwordValid = (sha1($password) === $storedHash);
            } else {
                // plain text legacy (only if it matches exactly)
                $passwordValid = hash_equals((string)$storedHash, (string)$password);
            }
        }
    }

    if ($user && $passwordValid) {
        // If legacy password matched, upgrade to bcrypt
        if (!$isBcrypt) {
            $newHash = password_hash($password, PASSWORD_BCRYPT);
            $up = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $up->execute([$newHash, $user['id']]);
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];

        // Normalize roles so student portal always loads
        $role = $user['role'];
        $_SESSION['role'] = ($role === 'user' || $role === 'premium') ? 'student' : $role;

        $update = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $update->execute([$user['id']]);
        
        Database::getInstance()->logActivity($user['id'], 'login', 'User logged in successfully');
        
        // Map roles to correct dashboard files
        $map = [
            'student' => 'dashboards/student.php',
            'hostel_admin' => 'dashboards/hostel_admin.php',
            'finance' => 'dashboards/finance.php',
            'super_admin' => 'dashboards/super_admin.php',
            'user' => 'dashboards/student.php',  
            'premium' => 'dashboards/student.php',  
        ];
        $target = $map[$role] ?? ('dashboards/' . $role . '.php');
        header("Location: {$target}");
    } else {
        header("Location: login.php?error=1");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelHub - Professional Login</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/register.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="login-body">
    <div class="login-wrapper">
        <div class="login-container">
            <!-- Left Side - Branding -->
            <div class="login-left">
                <div class="logo-header">
                    <img src="assets/logo.svg" alt="HostelHub Logo" class="logo-svg">
                    <h1>HostelHub</h1>
                </div>
                <h2>Smart Hostel Booking</h2>
                <p>Your trusted platform for finding and booking premium accommodation</p>
                <div class="features">
                    <div><i class="fas fa-check-circle"></i> Instant Room Availability</div>
                    <div><i class="fas fa-check-circle"></i> Secure Payment Gateway</div>
                    <div><i class="fas fa-check-circle"></i> Professional Management</div>
                    <div><i class="fas fa-check-circle"></i> 24/7 Customer Support</div>
                </div>
            </div>

            <!-- Right Side - Login Form -->
            <div class="login-right">
                <div class="login-card">
                    <div class="login-card-header">
                        <h3>Welcome Back</h3>
                        <p>Sign in to your account</p>
                    </div>

                    <?php if(isset($_GET['error'])): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> Invalid username or password
                        </div>
                    <?php endif; ?>

                    <form action="login.php" method="POST" class="login-form">
                        <div class="form-group">
                            <label for="username"><i class="fas fa-user"></i> Username</label>
                            <input type="text" name="username" id="username" placeholder="Enter your username" required class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="password"><i class="fas fa-lock"></i> Password</label>
                            <input type="password" name="password" id="password" placeholder="Enter your password" required class="form-control">
                        </div>

                        <div class="form-options">
                            <label class="remember-me">
                                <input type="checkbox"> Remember me
                            </label>
                            <a href="#" class="forgot-password">Forgot password?</a>
                        </div>

                        <button type="submit" class="btn-login">
                            <span>Sign In</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </form>

                    <div class="login-footer">
                        <p>Don't have an account? <a href="create_account.php" class="signup-link">Create one now</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/script.js"></script>
</body>
</html>
