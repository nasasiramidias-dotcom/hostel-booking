<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'finance') {
    header("Location: ../index.php");
    exit();
}

$db = Database::getInstance()->getConnection();

// Get stats
$totalPayments = $db->query("SELECT COUNT(*) FROM payments")->fetchColumn();
$pendingPayments = $db->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
$confirmedPayments = $db->query("SELECT COUNT(*) FROM payments WHERE status = 'confirmed'")->fetchColumn();
$totalRevenue = $db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'confirmed'")->fetchColumn();

// Get pending payments (paid or pending confirmation)
$stmt = $db->prepare("SELECT p.*, b.id as booking_id, b.status as booking_status, u.full_name as student_name, u.email, h.name as hostel_name, r.room_number, r.price_per_semester 
    FROM payments p 
    JOIN bookings b ON p.booking_id = b.id 
    JOIN users u ON b.student_id = u.id 
    JOIN rooms r ON b.room_id = r.id 
    JOIN hostels h ON r.hostel_id = h.id 
    WHERE p.status IN ('paid', 'pending') ORDER BY p.created_at DESC");
$stmt->execute();
$pending = $stmt->fetchAll();

// Get all payments
$stmt2 = $db->prepare("SELECT p.*, b.id as booking_id, u.full_name as student_name, h.name as hostel_name, r.room_number 
    FROM payments p 
    JOIN bookings b ON p.booking_id = b.id 
    JOIN users u ON b.student_id = u.id 
    JOIN rooms r ON b.room_id = r.id 
    JOIN hostels h ON r.hostel_id = h.id 
    ORDER BY p.created_at DESC LIMIT 50");
$stmt2->execute();
$allPayments = $stmt2->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Dashboard - HostelHub</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="dashboard-body">
    <div class="sidebar">
        <div class="sidebar-header">
            <h2> HostelHub</h2>
            <p>Finance Portal</p>
        </div>
        <nav class="sidebar-nav">
            <a href="#" data-page="dashboard" class="active"> Dashboard</a>
            <a href="#" data-page="payments"> Pending Payments</a>
            <a href="#" data-page="history"> Payment History</a>
            <a href="#" data-page="reports"> Reports</a>
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
                <div class="stat-card"><h3>Total Payments</h3><div class="number"><?php echo $totalPayments; ?></div></div>
                <div class="stat-card"><h3>Pending</h3><div class="number"><?php echo $pendingPayments; ?></div></div>
                <div class="stat-card"><h3>Confirmed</h3><div class="number"><?php echo $confirmedPayments; ?></div></div>
                <div class="stat-card"><h3>Total Revenue</h3><div class="number">UGX <br><?php echo number_format($totalRevenue); ?></div></div>
            </div>
        </div>
        
        <!-- Pending Payments -->
        <div id="payments" class="page-section">
            <div class="card">
                <h3>Pending Payment Confirmations</h3>
                <?php if ($pending): ?>
                <table class="data-table">
                    <thead>
                        <tr><th>ID</th><th>Student</th><th>Hostel</th><th>Room</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending as $p): ?>
                        <tr>
                            <td>#<?php echo $p['id']; ?></td>
                            <td><?php echo htmlspecialchars($p['student_name']); ?><br><small><?php echo htmlspecialchars($p['email']); ?></small></td>
                            <td><?php echo htmlspecialchars($p['hostel_name']); ?></td>
                            <td><?php echo htmlspecialchars($p['room_number']); ?></td>
                            <td>UGX<?php echo number_format($p['amount'], 2); ?></td>
                            <td><span class="badge badge-<?php echo $p['payment_method']; ?>"><?php echo ucfirst(str_replace('_', ' ', $p['payment_method'])); ?></span></td>
                            <td><span class="badge badge-<?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($p['created_at'])); ?></td>
                            <td>
                                <?php if ($p['status'] === 'paid'): ?>
                                <div class="action-buttons">
                                    <button class="btn btn-success" onclick="confirmPayment(<?php echo $p['id']; ?>, <?php echo $p['booking_id']; ?>)">Approve</button>
                                    <button class="btn btn-danger" onclick="rejectPayment(<?php echo $p['id']; ?>)">Reject</button>
                                </div>
                                <?php else: ?>
                                <span style="color: #999999;">Awaiting payment proof</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="no-data">No pending payments.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Payment History -->
        <div id="history" class="page-section">
            <div class="card">
                <h3>Payment History</h3>
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Student</th><th>Hostel</th><th>Room</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($allPayments as $p): ?>
                        <tr>
                            <td>#<?php echo $p['id']; ?></td>
                            <td><?php echo htmlspecialchars($p['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($p['hostel_name']); ?></td>
                            <td><?php echo htmlspecialchars($p['room_number']); ?></td>
                            <td>UGX<?php echo number_format($p['amount'], 2); ?></td>
                            <td><span class="badge badge-<?php echo $p['payment_method']; ?>"><?php echo ucfirst(str_replace('_', ' ', $p['payment_method'])); ?></span></td>
                            <td><span class="badge badge-<?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($p['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Reports -->
        <div id="reports" class="page-section">
            <div class="card">
                <h3>Financial Reports</h3>
                <?php
                $monthly = $db->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count, SUM(amount) as total FROM payments WHERE status = 'confirmed' GROUP BY month ORDER BY month DESC LIMIT 12");
                ?>
                <table class="data-table">
                    <thead><tr><th>Month</th><th>Transactions</th><th>Revenue</th></tr></thead>
                    <tbody>
                        <?php foreach ($monthly as $m): ?>
                        <tr>
                            <td><?php echo $m['month']; ?></td>
                            <td><?php echo $m['count']; ?></td>
                            <td>UGX<?php echo number_format($m['total'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="../assets/script.js"></script>
    <script src="../assets/click-interactions.js"></script>
    <script>
        function confirmPayment(paymentId, bookingId) {
            if (confirm('Approve this payment?')) {
                fetch('../api/confirm_payment.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'payment_id=' + paymentId + '&booking_id=' + bookingId
                })
                .then(res => {
                    if (!res.ok) throw new Error('Server error: ' + res.status);
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        alert('Payment confirmed successfully!');
                        location.reload();
                    } else {
                        alert('Failed: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(err => alert('Error: ' + err.message));
            }
        }

        function rejectPayment(paymentId) {
            const reason = prompt('Enter reason for rejection:');
            if (reason !== null && reason.trim() !== '') {
                fetch('../api/reject_payment.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'payment_id=' + paymentId + '&reason=' + encodeURIComponent(reason)
                })
                .then(res => {
                    if (!res.ok) throw new Error('Server error: ' + res.status);
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        alert('Payment rejected: ' + reason);
                        location.reload();
                    } else {
                        alert('Failed: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(err => alert('Error: ' + err.message));
            } else if (reason === '') {
                alert('Please enter a reason for rejection');
            }
        }
    </script>
</body>
</html>

