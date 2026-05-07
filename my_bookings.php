<?php
/**
 * MY BOOKINGS PAGE
 * 
 * Shows all bookings for the logged-in student with status,
 * payment information, and actions.
 */

session_start();
require_once 'config/database.php';
require_once 'includes/hostel_functions.php';

// Ensure logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit();
}

$db = Database::getInstance()->getConnection();
$success = '';
$error = '';

// Check for success message from booking redirect
if (isset($_SESSION['booking_success']) && $_SESSION['booking_success']) {
    $success = 'Booking created successfully! Reference: ' . htmlspecialchars($_SESSION['booking_reference']);
    unset($_SESSION['booking_success']);
    unset($_SESSION['booking_reference']);
}

// Get student bookings
$bookings = getStudentBookings($db, $_SESSION['user_id']);

// Handle cancel booking request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : null;
    
    if ($bookingId) {
        $cancelResult = cancelBooking($db, $bookingId, $_SESSION['user_id']);
        
        if ($cancelResult['success']) {
            Database::getInstance()->logActivity($_SESSION['user_id'], 'booking_cancelled', 'Booking ID: ' . $bookingId);
            $success = 'Booking cancelled successfully';
            
            // Refresh bookings list
            $bookings = getStudentBookings($db, $_SESSION['user_id']);
        } else {
            $error = $cancelResult['message'];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Hostel Booking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-top: 60px;
        }
        .container {
            max-width: 1200px;
            padding: 40px 15px;
        }
        .page-header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        .page-header h1 {
            color: #667eea;
            margin-bottom: 10px;
        }
        .booking-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .booking-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        .booking-card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .booking-reference {
            font-size: 1.3rem;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }
        .booking-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
        }
        .booking-card-body {
            padding: 20px;
        }
        .booking-detail-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 15px;
        }
        .detail-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .detail-label {
            font-weight: 600;
            color: #667eea;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        .detail-value {
            margin-top: 5px;
            font-size: 1rem;
            color: #333;
        }
        .booking-card-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .btn-sm {
            padding: 6px 15px;
            font-size: 0.85rem;
        }
        .empty-state {
            background: white;
            border-radius: 10px;
            padding: 60px 30px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        .empty-state h3 {
            color: #667eea;
            margin-bottom: 10px;
        }
        .empty-state p {
            color: #999;
            margin-bottom: 30px;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .alert-container {
            margin-bottom: 20px;
        }
        .payment-badge {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .payment-unpaid {
            background: #f8d7da;
            color: #721c24;
        }
        .payment-paid {
            background: #d4edda;
            color: #155724;
        }
        .payment-refunded {
            background: #d1ecf1;
            color: #0c5460;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboards/student.php">
            <i class="fas fa-building"></i> Hostel Booking
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboards/student.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="my_bookings.php">My Bookings</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-bookmark"></i> My Bookings</h1>
        <p class="text-muted mb-0">View and manage all your hostel bookings</p>
    </div>
    
    <?php if ($success): ?>
        <div class="alert-container">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert-container">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (empty($bookings)): ?>
        
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>No Bookings Yet</h3>
            <p>You haven't made any hostel bookings yet.</p>
            <a href="dashboards/student.php" class="btn btn-primary">
                <i class="fas fa-search"></i> Browse Hostels
            </a>
        </div>
        
    <?php else: ?>
        
        <?php foreach ($bookings as $booking): ?>
            <div class="booking-card">
                <div class="booking-card-header">
                    <div>
                        <div class="booking-reference"><?php echo htmlspecialchars($booking['booking_reference']); ?></div>
                        <small><?php echo htmlspecialchars($booking['hostel_name']); ?> - <?php echo htmlspecialchars($booking['room_number']); ?></small>
                    </div>
                    <div>
                        <span class="booking-status status-<?php echo htmlspecialchars(strtolower($booking['status'])); ?>">
                            <?php echo htmlspecialchars(ucfirst($booking['status'])); ?>
                        </span>
                    </div>
                </div>
                
                <div class="booking-card-body">
                    <div class="booking-detail-row">
                        <div class="detail-item">
                            <div class="detail-label">Check-in Date</div>
                            <div class="detail-value">
                                <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Check-out Date</div>
                            <div class="detail-value">
                                <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Room Type</div>
                            <div class="detail-value">
                                <?php 
                                $types = ['single' => '🛏️ Single', 'double' => '🛏️ Double', 'triple' => '🛏️ Triple', 'dorm' => '🛏️ Dorm'];
                                echo $types[$booking['room_type']] ?? ucfirst($booking['room_type']);
                                ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Total Amount</div>
                            <div class="detail-value">
                                <strong class="text-primary">$<?php echo number_format($booking['total_amount'], 2); ?></strong>
                            </div>
                        </div>
                    </div>
                    
                    <div class="booking-detail-row">
                        <div class="detail-item">
                            <div class="detail-label">Payment Status</div>
                            <div class="detail-value">
                                <span class="payment-badge payment-<?php echo htmlspecialchars(strtolower($booking['payment_status'])); ?>">
                                    <?php echo htmlspecialchars(ucfirst($booking['payment_status'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Booking Date</div>
                            <div class="detail-value">
                                <?php echo date('M d, Y H:i', strtotime($booking['booking_date'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="booking-card-footer">
                    <a href="booking_details.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-info">
                        <i class="fas fa-eye"></i> View Details
                    </a>
                    
                    <?php if ($booking['status'] === 'pending'): ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">
                                <i class="fas fa-times-circle"></i> Cancel Booking
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if ($booking['payment_status'] === 'unpaid' && in_array($booking['status'], ['pending', 'confirmed'])): ?>
                        <a href="dashboards/student.php?tab=payments" class="btn btn-sm btn-warning">
                            <i class="fas fa-credit-card"></i> Make Payment
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
    <?php endif; ?>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
