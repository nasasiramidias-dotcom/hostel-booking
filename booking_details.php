<?php
/**
 * BOOKING DETAILS PAGE
 * 
 * Shows complete booking information including all details,
 * hostel and room information, and cancellation option for pending bookings.
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
$error = '';
$success = '';
$bookingId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$booking = null;

// Get booking details
if ($bookingId) {
    $booking = getBookingDetails($db, $bookingId, $_SESSION['user_id']);
    
    if (!$booking) {
        $error = 'Booking not found or access denied';
    }
} else {
    $error = 'No booking specified';
}

// Handle cancel booking request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel' && $booking) {
    $cancelResult = cancelBooking($db, $bookingId, $_SESSION['user_id']);
    
    if ($cancelResult['success']) {
        Database::getInstance()->logActivity($_SESSION['user_id'], 'booking_cancelled', 'Booking ID: ' . $bookingId);
        $success = 'Booking cancelled successfully';
        
        // Refresh booking details
        $booking = getBookingDetails($db, $bookingId, $_SESSION['user_id']);
    } else {
        $error = $cancelResult['message'];
    }
}

// Calculate duration
$durationDays = 0;
if ($booking) {
    $checkIn = new DateTime($booking['check_in_date']);
    $checkOut = new DateTime($booking['check_out_date']);
    $durationDays = $checkOut->diff($checkIn)->days;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details - Hostel Booking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-top: 60px;
        }
        .details-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
            max-width: 900px;
            margin: 40px auto;
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        .booking-ref {
            font-family: 'Courier New', monospace;
            font-size: 1.4rem;
            font-weight: 700;
            color: #667eea;
        }
        .status-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.95rem;
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
        .section {
            margin-bottom: 40px;
        }
        .section-title {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
            font-weight: 600;
            color: #333;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 20px;
        }
        .info-box {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .info-label {
            font-weight: 600;
            color: #667eea;
            font-size: 0.85rem;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .info-value {
            font-size: 1.1rem;
            color: #333;
            margin-bottom: 0;
        }
        .large-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
        }
        .amenities-box {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .amenity-tag {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            margin-right: 8px;
            margin-bottom: 8px;
            font-size: 0.85rem;
        }
        .payment-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
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
        .action-buttons {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .alert-container {
            margin-bottom: 30px;
        }
        .summary-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }
        .summary-row:last-child {
            border-bottom: none;
        }
        .summary-total {
            font-size: 1.4rem;
            font-weight: 700;
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
                    <a class="nav-link" href="my_bookings.php">My Bookings</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="details-container">
    
    <?php if ($error): ?>
        <div class="alert-container">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
        <a href="my_bookings.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to My Bookings
        </a>
    <?php elseif ($booking): ?>
        
        <?php if ($success): ?>
            <div class="alert-container">
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Header -->
        <div class="header-section">
            <div>
                <h1><i class="fas fa-receipt"></i> Booking Details</h1>
                <div class="booking-ref"><?php echo htmlspecialchars($booking['booking_reference']); ?></div>
            </div>
            <div>
                <span class="status-badge status-<?php echo htmlspecialchars(strtolower($booking['status'])); ?>">
                    <?php echo htmlspecialchars(ucfirst($booking['status'])); ?>
                </span>
            </div>
        </div>
        
        <!-- Hostel Information -->
        <div class="section">
            <div class="section-title"><i class="fas fa-hotel"></i> Hostel Information</div>
            <div class="info-grid">
                <div class="info-box">
                    <div class="info-label">Hostel Name</div>
                    <div class="info-value"><?php echo htmlspecialchars($booking['hostel_name']); ?></div>
                </div>
                <div class="info-box">
                    <div class="info-label">Address</div>
                    <div class="info-value"><?php echo htmlspecialchars($booking['hostel_address']); ?></div>
                </div>
                <div class="info-box">
                    <div class="info-label">Contact Phone</div>
                    <div class="info-value"><a href="tel:<?php echo htmlspecialchars($booking['contact_phone']); ?>"><?php echo htmlspecialchars($booking['contact_phone']); ?></a></div>
                </div>
                <div class="info-box">
                    <div class="info-label">Contact Email</div>
                    <div class="info-value"><a href="mailto:<?php echo htmlspecialchars($booking['contact_email']); ?>"><?php echo htmlspecialchars($booking['contact_email']); ?></a></div>
                </div>
            </div>
        </div>
        
        <!-- Room Information -->
        <div class="section">
            <div class="section-title"><i class="fas fa-door-open"></i> Room Information</div>
            <div class="info-grid">
                <div class="info-box">
                    <div class="info-label">Room Number</div>
                    <div class="info-value"><?php echo htmlspecialchars($booking['room_number']); ?></div>
                </div>
                <div class="info-box">
                    <div class="info-label">Room Type</div>
                    <div class="info-value">
                        <?php 
                        $types = ['single' => '🛏️ Single', 'double' => '🛏️ Double', 'triple' => '🛏️ Triple', 'dorm' => '🛏️ Dorm'];
                        echo $types[$booking['room_type']] ?? ucfirst($booking['room_type']);
                        ?>
                    </div>
                </div>
                <div class="info-box">
                    <div class="info-label">Capacity</div>
                    <div class="info-value"><?php echo htmlspecialchars($booking['capacity']); ?> persons</div>
                </div>
                <div class="info-box">
                    <div class="info-label">Price per Month</div>
                    <div class="info-value">$<?php echo number_format($booking['price_per_month'], 2); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Booking Dates -->
        <div class="section">
            <div class="section-title"><i class="fas fa-calendar-alt"></i> Booking Dates</div>
            <div class="info-grid">
                <div class="info-box">
                    <div class="info-label">Check-in Date</div>
                    <div class="info-value"><?php echo date('l, F d, Y', strtotime($booking['check_in_date'])); ?></div>
                </div>
                <div class="info-box">
                    <div class="info-label">Check-out Date</div>
                    <div class="info-value"><?php echo date('l, F d, Y', strtotime($booking['check_out_date'])); ?></div>
                </div>
                <div class="info-box">
                    <div class="info-label">Duration</div>
                    <div class="info-value"><?php echo $durationDays; ?> night(s)</div>
                </div>
                <div class="info-box">
                    <div class="info-label">Booking Date</div>
                    <div class="info-value"><?php echo date('F d, Y H:i', strtotime($booking['booking_date'])); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Payment Information -->
        <div class="section">
            <div class="section-title"><i class="fas fa-credit-card"></i> Payment Information</div>
            <div class="info-grid">
                <div class="info-box">
                    <div class="info-label">Total Amount</div>
                    <div class="large-value">$<?php echo number_format($booking['total_amount'], 2); ?></div>
                </div>
                <div class="info-box">
                    <div class="info-label">Payment Status</div>
                    <div style="margin-top: 10px;">
                        <span class="payment-badge payment-<?php echo htmlspecialchars(strtolower($booking['payment_status'])); ?>">
                            <?php echo htmlspecialchars(ucfirst($booking['payment_status'])); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="summary-box">
                <div class="summary-row">
                    <span>Price per Month:</span>
                    <span>$<?php echo number_format($booking['price_per_month'], 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Duration (days):</span>
                    <span><?php echo $durationDays; ?></span>
                </div>
                <div class="summary-row">
                    <span>Duration (months):</span>
                    <span><?php echo round($durationDays / 30, 2); ?></span>
                </div>
                <div class="summary-row summary-total">
                    <span>Total Amount Due:</span>
                    <span>$<?php echo number_format($booking['total_amount'], 2); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Notes -->
        <?php 
        $notes = null;
        if ($booking['notes']) {
            $decoded = json_decode($booking['notes'], true);
            $notes = is_array($decoded) && isset($decoded['freeform']) ? $decoded['freeform'] : $booking['notes'];
        }
        ?>
        <?php if ($notes): ?>
            <div class="section">
                <div class="section-title"><i class="fas fa-sticky-note"></i> Notes</div>
                <div class="info-box">
                    <p class="mb-0"><?php echo htmlspecialchars($notes); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="my_bookings.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to My Bookings
            </a>
            
            <?php if ($booking['status'] === 'pending'): ?>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this booking? This action cannot be undone.');">
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times-circle"></i> Cancel Booking
                    </button>
                </form>
            <?php endif; ?>
            
            <?php if ($booking['payment_status'] === 'unpaid' && in_array($booking['status'], ['pending', 'confirmed'])): ?>
                <a href="dashboards/student.php?tab=payments" class="btn btn-warning">
                    <i class="fas fa-credit-card"></i> Make Payment
                </a>
            <?php endif; ?>
        </div>
        
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Booking not found
        </div>
        <a href="my_bookings.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to My Bookings
        </a>
    <?php endif; ?>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
