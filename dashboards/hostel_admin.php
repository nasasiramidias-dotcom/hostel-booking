<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hostel_admin') {
    header("Location: ../index.php");
    exit();
}

$db = Database::getInstance()->getConnection();

// Get stats
$totalBookings = $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$pendingBookings = $db->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
$approvedBookings = $db->query("SELECT COUNT(*) FROM bookings WHERE status = 'approved'")->fetchColumn();
$totalHostels = $db->query("SELECT COUNT(*) FROM hostels WHERE status = 'active'")->fetchColumn();
$totalRooms = $db->query("SELECT COUNT(*) FROM rooms WHERE status = 'available'")->fetchColumn();

// Get pending bookings for approval
$stmt = $db->prepare("SELECT b.*, u.full_name as student_name, u.email, h.name as hostel_name, r.room_number, r.room_type, r.price_per_semester as price 
    FROM bookings b 
    JOIN users u ON b.student_id = u.id 
    JOIN rooms r ON b.room_id = r.id 
    JOIN hostels h ON r.hostel_id = h.id 
    WHERE b.status = 'pending' ORDER BY b.created_at DESC");
$stmt->execute();
$pending = $stmt->fetchAll();

// Get all bookings
$stmt2 = $db->prepare("SELECT b.*, u.full_name as student_name, h.name as hostel_name, r.room_number, r.room_type 
    FROM bookings b 
    JOIN users u ON b.student_id = u.id 
    JOIN rooms r ON b.room_id = r.id 
    JOIN hostels h ON r.hostel_id = h.id 
    ORDER BY b.created_at DESC LIMIT 50");
$stmt2->execute();
$allBookings = $stmt2->fetchAll();

// Get hostels
$hostels = $db->query("SELECT * FROM hostels ORDER BY name")->fetchAll();

// Get rooms with hostel info
$stmt3 = $db->prepare("SELECT r.id, r.hostel_id, r.room_number, r.room_type, r.price_per_semester as price, r.capacity, r.status, h.name as hostel_name FROM rooms r JOIN hostels h ON r.hostel_id = h.id ORDER BY h.name, r.room_number");
$stmt3->execute();
$rooms = $stmt3->fetchAll();

// Determine active page from URL parameter
$currentPage = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$allowedPages = ['dashboard', 'pending', 'manage-hostels', 'manage-rooms', 'all-bookings'];
if (!in_array($currentPage, $allowedPages)) {
    $currentPage = 'dashboard';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostel Admin - HostelHub</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="dashboard-body" data-page="<?php echo htmlspecialchars($currentPage); ?>">
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>HostelHub</h2>
            <p>Hostel Admin Portal</p>
        </div>
        <nav class="sidebar-nav">
            <a href="#" data-page="dashboard" class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
            <a href="#" data-page="pending" class="<?php echo $currentPage === 'pending' ? 'active' : ''; ?>">Pending Approvals</a>
            <a href="#" data-page="manage-hostels" class="<?php echo $currentPage === 'manage-hostels' ? 'active' : ''; ?>"> Manage Hostels</a>
            <a href="#" data-page="manage-rooms" class="<?php echo $currentPage === 'manage-rooms' ? 'active' : ''; ?>"> Manage Rooms</a>
            <a href="#" data-page="all-bookings" class="<?php echo $currentPage === 'all-bookings' ? 'active' : ''; ?>"> All Bookings</a>
            <a href="../logout.php"> Logout</a>
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
                <div class="stat-card primary">
                    <div class="stat-icon"></div>
                    <h3>Total Bookings</h3>
                    <div class="number"><?php echo $totalBookings; ?></div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon"></div>
                    <h3>Pending</h3>
                    <div class="number"><?php echo $pendingBookings; ?></div>
                </div>
                <div class="stat-card success">
                    <div class="stat-icon"></div>
                    <h3>Approved</h3>
                    <div class="number"><?php echo $approvedBookings; ?></div>
                </div>
                <div class="stat-card info">
                    <div class="stat-icon"></div>
                    <h3>Hostels</h3>
                    <div class="number"><?php echo $totalHostels; ?></div>
                </div>
                <div class="stat-card secondary">
                    <div class="stat-icon"></div>
                    <h3>Available Rooms</h3>
                    <div class="number"><?php echo $totalRooms; ?></div>
                </div>
            </div>
        </div>
        
        <!-- Pending Approvals -->
        <div id="pending" class="page-section">
            <div class="card">
                <h3>Pending Booking Approvals</h3>
                <?php if ($pending): ?>
                <table class="data-table">
                    <thead>
                        <tr><th>ID</th><th>Student</th><th>Hostel</th><th>Room</th><th>Type</th><th>Price</th><th>Check-in</th><th>Check-out</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending as $b): ?>
                        <tr>
                            <td>#<?php echo $b['id']; ?></td>
                            <td><?php echo htmlspecialchars($b['student_name']); ?><br><small><?php echo htmlspecialchars($b['email']); ?></small></td>
                            <td><?php echo htmlspecialchars($b['hostel_name']); ?></td>
                            <td><?php echo htmlspecialchars($b['room_number']); ?></td>
                            <td><?php echo htmlspecialchars($b['room_type']); ?></td>
                            <td>$<?php echo number_format($b['price'], 2); ?></td>
                            <td><?php echo $b['check_in']; ?></td>
                            <td><?php echo $b['check_out']; ?></td>
                            <td>
                                <button class="btn btn-success" onclick="updateStatus(<?php echo $b['id']; ?>, 'approved')">Approve</button>
                                <button class="btn btn-danger" onclick="updateStatus(<?php echo $b['id']; ?>, 'rejected')">Reject</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="no-data">No pending approvals.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Manage Hostels -->
        <div id="manage-hostels" class="page-section">
            <div class="card">
                <h3>Add New Hostel</h3>
                <form method="POST" action="../api/add_hostel.php">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Hostel Name</label>
                            <input type="text" name="name" required>
                        </div>
                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" name="location" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Hostel</button>
                </form>
            </div>
            
            <div class="card">
                <h3>All Hostels</h3>
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Name</th><th>Location</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($hostels as $h): ?>
                        <tr>
                            <td>#<?php echo $h['id']; ?></td>
                            <td><?php echo htmlspecialchars($h['name']); ?></td>
                            <td><?php echo htmlspecialchars($h['location']); ?></td>
                            <td><span class="badge badge-<?php echo $h['status']; ?>"><?php echo ucfirst($h['status']); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($h['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-danger" onclick="deleteHostel(<?php echo $h['id']; ?>)">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Manage Rooms -->
        <div id="manage-rooms" class="page-section">
            <div class="card">
                <h3>Add New Room</h3>
                <form method="POST" action="../api/add_room.php">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Hostel</label>
                            <select name="hostel_id" required>
                                <?php foreach ($hostels as $h): ?>
                                <option value="<?php echo $h['id']; ?>"><?php echo htmlspecialchars($h['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Room Number</label>
                            <input type="text" name="room_number" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Room Type</label>
                            <select name="room_type" required>
                                <option value="single">Single</option>
                                <option value="double">Double</option>
                                <option value="triple">Triple</option>
                                <option value="suite">Suite</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Price per semester</label>
                            <input type="number" name="price_per_semester" step="0.01" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Capacity</label>
                        <input type="number" name="capacity" value="1" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Room</button>
                </form>
            </div>
            
            <div class="card">
                <h3>All Rooms</h3>
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Hostel</th><th>Room</th><th>Type</th><th>Price</th><th>Capacity</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($rooms as $r): ?>
                        <tr>
                            <td>#<?php echo $r['id']; ?></td>
                            <td><?php echo htmlspecialchars($r['hostel_name']); ?></td>
                            <td><?php echo htmlspecialchars($r['room_number']); ?></td>
                            <td><?php echo ucfirst($r['room_type']); ?></td>
                            <td>$<?php echo number_format($r['price'], 2); ?></td>
                            <td><?php echo $r['capacity']; ?></td>
                            <td><span class="badge badge-<?php echo $r['status']; ?>"><?php echo ucfirst($r['status']); ?></span></td>
                            <td>
                                <button class="btn btn-danger" onclick="deleteRoom(<?php echo $r['id']; ?>)">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- All Bookings -->
        <div id="all-bookings" class="page-section">
            <div class="card">
                <h3>All Bookings</h3>
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Student</th><th>Hostel</th><th>Room</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($allBookings as $b): ?>
                        <tr>
                            <td>#<?php echo $b['id']; ?></td>
                            <td><?php echo htmlspecialchars($b['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($b['hostel_name']); ?></td>
                            <td><?php echo htmlspecialchars($b['room_number']); ?></td>
                            <td><span class="badge badge-<?php echo $b['status']; ?>"><?php echo ucfirst($b['status']); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($b['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="../assets/script.js"></script>
    <script>
        function updateStatus(bookingId, status) {
            const msg = status === 'approved' ? 'Approve this booking?' : 'Reject this booking?';
            if (confirm(msg)) {
                fetch('../api/update_booking.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'booking_id=' + bookingId + '&status=' + status
                })
                .then(res => {
                    if (!res.ok) throw new Error('Server error: ' + res.status);
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        alert('Booking ' + status + ' successfully!');
                        location.reload();
                    } else {
                        alert('Failed to update booking: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(err => alert('Error: ' + err.message));
            }
        }

        function deleteHostel(hostelId) {
            if (confirm('Are you sure you want to delete this hostel? This will also delete all associated rooms.')) {
                fetch('../api/delete_hostel.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'hostel_id=' + hostelId
                })
                .then(res => {
                    if (!res.ok) throw new Error('Server error: ' + res.status);
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        alert('Hostel deleted successfully!');
                        location.reload();
                    } else {
                        alert('Failed to delete hostel: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(err => alert('Error: ' + err.message));
            }
        }

        function deleteRoom(roomId) {
            if (confirm('Are you sure you want to delete this room?')) {
                fetch('../api/delete_room.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'room_id=' + roomId
                })
                .then(res => {
                    if (!res.ok) throw new Error('Server error: ' + res.status);
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        alert('Room deleted successfully!');
                        location.reload();
                    } else {
                        alert('Failed to delete room: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(err => alert('Error: ' + err.message));
            }
        }
    </script>
</body>
</html>

