<?php
session_start();
require_once 'config/database.php';

// If already logged in, redirect to role dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboards/{$_SESSION['role']}.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    // Always create student accounts (no account-type selection anymore)
    $role = 'student';

    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = "Please fill in all required fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $db = Database::getInstance()->getConnection();

        // Check if username or email already exists
        $check_stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $check_stmt->execute([$username, $email]);

        if ($check_stmt->fetch()) {
            $error = "Username or email already exists!";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert user into database
            $insert_stmt = $db->prepare(
                "INSERT INTO users (username, email, password, full_name, phone, role, status)
                 VALUES (?, ?, ?, ?, ?, ?, 'active')"
            );

            if ($insert_stmt->execute([$username, $email, $hashed_password, $full_name, $phone, $role])) {
                $success = "Registration successful! You can now login.";
                Database::getInstance()->logActivity($db->lastInsertId(), 'register', 'New user registered');
                $_POST = array();
            } else {
                $error = "Registration failed: " . ($insert_stmt->errorInfo()[2] ?? 'Unknown error');
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - HostelHub</title>

    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/register.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="register-container">
        <div class="register-wrapper">
            <div class="register-header">
                <h2>Create New Account</h2>
                
            </div>

            <?php if($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm" class="register-form">
                <div class="row">
                    <div class="col-md-6">
                            <div class="form-group">
                                <label for="full_name">Full Name </label>
                                <input type="text" id="full_name" name="full_name" class="form-control"
                                       value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" required>
                            </div>
                        </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="username">Username </label>
                            <input type="text" id="username" name="username" class="form-control"
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email Address </label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>

                <div class="row">
                        <div class="col-md-6">
                        <div class="form-group">
                            <label for="password">Password </label>
                            <input type="password" id="password" name="password" class="form-control" required>
                            <small class="password-hint">Minimum 6 characters</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password </label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control"
                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                </div>

                <!-- Account Type removed: all new registrations are treated as student accounts -->

                <div class="form-group terms">
                    <label class="checkbox-inline">
                        <input type="checkbox" id="terms" required>
                        I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a>
                    </label>
                </div>

                <button type="submit" name="register" class="btn-register">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>

                <div class="login-link">
                    Already have an account? <a href="index.php">Login here</a>
                </div>

                <!-- After successful login, students are redirected to dashboards/student.php by login.php -->
            </form>
        </div>
    </div>

    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms and Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Acceptance of Terms</h6>
                    <p>By registering for this system, you agree to comply with these terms...</p>
                    <h6>2. User Accounts</h6>
                    <p>You are responsible for maintaining the security of your account...</p>
                    <h6>3. Privacy Policy</h6>
                    <p>We respect your privacy and protect your personal information...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        var registerForm = document.getElementById('registerForm');
        if (registerForm) {
            registerForm.addEventListener('submit', function(e) {
                var passwordEl = document.getElementById('password');
                var confirmEl = document.getElementById('confirm_password');
                var termsEl = document.getElementById('terms');

                if (!passwordEl || !confirmEl || !termsEl) return;

                var password = passwordEl.value;
                var confirm = confirmEl.value;
                var terms = termsEl.checked;

                if (password !== confirm) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                } else if (password.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long!');
                } else if (!terms) {
                    e.preventDefault();
                    alert('Please accept the Terms and Conditions!');
                }
            });
        }

        var pwInput = document.getElementById('password');
        if (pwInput) {
            pwInput.addEventListener('input', function() {
                var password = this.value;
                var strength = 0;
                if (password.length >= 8) strength++;
                if (password.match(/[a-z]+/)) strength++;
                if (password.match(/[A-Z]+/)) strength++;
                if (password.match(/[0-9]+/)) strength++;
                if (password.match(/[$@#&!]+/)) strength++;

                var strengthText = '';
                var strengthColor = '';
                switch(strength) {
                    case 0:
                    case 1:
                        strengthText = 'Weak';
                        strengthColor = 'red';
                        break;
                    case 2:
                    case 3:
                        strengthText = 'Medium';
                        strengthColor = 'orange';
                        break;
                    default:
                        strengthText = 'Strong';
                        strengthColor = 'green';
                }

                var hint = document.querySelector('.password-hint');
                if (hint) {
                    if (password.length > 0) {
                        hint.innerHTML = `Password strength: <span style="color: ${strengthColor}">${strengthText}</span>`;
                    } else {
                        hint.innerHTML = 'Minimum 6 characters';
                    }
                }
            });
        }
    </script>
</body>
</html>
