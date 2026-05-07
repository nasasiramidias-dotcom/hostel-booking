<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: ../index.php");
    exit();
}

$db = Database::getInstance()->getConnection();

// Stats
$totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalBookings = $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$totalHostels = $db->query("SELECT COUNT(*) FROM hostels")->fetchColumn();
$totalRevenue = $db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'confirmed'")->fetchColumn();

// Users
$users = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 50");

// All bookings
$stmt = $db->prepare("SELECT b.*, u.full_name as student_name, h.name as hostel_name, r.room_number 
    FROM bookings b 
    JOIN users u ON b.student_id = u.id 
    JOIN rooms r ON b.room_id = r.id 
    JOIN hostels h ON r.hostel_id = h.id 
    ORDER BY b.created_at DESC LIMIT 50");
$stmt->execute();
$allBookings = $stmt->fetchAll();

// Activity logs
$logs = $db->query("SELECT al.*, u.full_name, u.role FROM activity_logs al JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 50");
?>
<?php
$page = $_GET['page'] ?? 'dashboard';
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin - HostelHub</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="dashboard-body" data-page="<?php echo htmlspecialchars($page); ?>">
    <?php if ($success): ?>
    <div class="alert alert-success" id="flash-message"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-error" id="flash-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>HostelHub</h2>
            <p>Super Admin Portal</p>
        </div>
        <nav class="sidebar-nav">
            <a href="#" data-page="dashboard" class="active"> Dashboard</a>
            <a href="#" data-page="users"> User Management</a>
            <a href="#" data-page="bookings"> All Bookings</a>
            <a href="#" data-page="reports">System Reports</a>

            <a href="../logout.php">Logout</a>

        </nav>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></h1>
            <div class="user-info">
                <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>
        </div>
        
        <!-- Dashboard -->
        <div id="dashboard" class="page-section active">
            <div class="stats-grid">
                <div class="stat-card"><h3>Total Users</h3><div class="number"><?php echo $totalUsers; ?></div></div>
                <div class="stat-card"><h3>Total Bookings</h3><div class="number"><?php echo $totalBookings; ?></div></div>
                <div class="stat-card"><h3>Hostels</h3><div class="number"><?php echo $totalHostels; ?></div></div>
                <div class="stat-card"><h3>Total Revenue</h3><div class="number">UGX <br><?php echo number_format($totalRevenue); ?></div></div>
            </div>
        </div>
        
        <!-- Users -->
        <div id="users" class="page-section">
            <div class="card">
                <h3>Add New User</h3>
                <form method="POST" action="../api/add_user.php">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" required>
                        </div>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <select name="role" required>
                                <option value="student">Student</option>
                                <option value="hostel_admin">Hostel Admin</option>
                                <option value="finance">Finance</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </form>
            </div>
            
            <div class="card">
<h3>All Users</h3>
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td>#<?php echo $u['id']; ?></td>
                            <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($u['username']); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $u['role'])); ?></td>
                            <td><span class="badge badge-<?php echo $u['status']; ?>"><?php echo ucfirst($u['status']); ?></span></td>
                            <td><?php echo $u['last_login'] ? date('M d, Y H:i', strtotime($u['last_login'])) : 'Never'; ?></td>
                            <td>
                                <?php if ($u['role'] !== 'super_admin'): ?>
                                <button class="btn btn-danger btn-sm" onclick="deleteUser(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['full_name']); ?>')">Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Bookings -->
        <div id="bookings" class="page-section">
            <div class="card">
                <h3>All Bookings</h3>
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Student</th><th>Hostel</th><th>Room</th><th>Status</th><th>Check-in</th><th>Check-out</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($allBookings as $b): ?>
                        <tr>
                            <td>#<?php echo $b['id']; ?></td>
                            <td><?php echo htmlspecialchars($b['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($b['hostel_name']); ?></td>
                            <td><?php echo htmlspecialchars($b['room_number']); ?></td>
                            <td><span class="badge badge-<?php echo $b['status']; ?>"><?php echo ucfirst($b['status']); ?></span></td>
                            <td><?php echo $b['check_in']; ?></td>
                            <td><?php echo $b['check_out']; ?></td>
                            <td><?php echo date('M d, Y', strtotime($b['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Reports -->
        <div id="reports" class="page-section">
            <div class="card">
                <h3>Activity Logs</h3>
                <table class="data-table">
                    <thead><tr><th>ID</th><th>User</th><th>Role</th><th>Action</th><th>Details</th><th>IP</th><th>Time</th></tr></thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>#<?php echo $log['id']; ?></td>
                            <td><?php echo htmlspecialchars($log['full_name']); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $log['role'])); ?></td>
                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                            <td><?php echo htmlspecialchars($log['details'] ?? ''); ?></td>
                            <td><?php echo $log['ip_address']; ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card">
                <h3>Booking Statistics</h3>
                <?php
                $statusStats = $db->query("SELECT status, COUNT(*) as count FROM bookings GROUP BY status");
                ?>
                <table class="data-table">
                    <thead><tr><th>Status</th><th>Count</th></tr></thead>
                    <tbody>
                        <?php foreach ($statusStats as $s): ?>
                        <tr>
                            <td><?php echo ucfirst($s['status']); ?></td>
                            <td><?php echo $s['count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
<script src="../assets/script.js"></script>
    <script>
        function deleteUser(userId, userName) {
            if (confirm('Are you sure you want to delete user "' + userName + '"? This action cannot be undone.')) {
                fetch('../api/delete_user.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'user_id=' + userId
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'User deleted successfully');
                        location.reload();
                    } else {
                        alert('Failed: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(err => alert('Error: ' + err.message));
            }
        }
    </script>
</body>
</html>

