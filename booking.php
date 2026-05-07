<?php
/**
 * BOOKING PAGE
 * 
 * Allows students to book a room with selected dates and bed count.
 * Pre-fills room details and validates availability.
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
$roomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : null;
$room = null;
$hostel = null;

// Get room details
if ($roomId) {
    $stmt = $db->prepare("
        SELECT r.*, h.name as hostel_name, h.address, h.contact_phone, h.contact_email
        FROM rooms r
        JOIN hostels h ON r.hostel_id = h.id
        WHERE r.id = ?
        LIMIT 1
    ");
    $stmt->execute([$roomId]);
    $row = $stmt->fetch();
    
    if ($row) {
        $room = $row;
        $hostel = [
            'name' => $row['hostel_name'],
            'address' => $row['address'],
            'contact_phone' => $row['contact_phone'],
            'contact_email' => $row['contact_email']
        ];
    } else {
        $error = 'Room not found';
    }
} else {
    $error = 'No room specified';
}

// Handle booking form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $room) {
    $checkInDate = isset($_POST['check_in_date']) ? trim($_POST['check_in_date']) : '';
    $checkOutDate = isset($_POST['check_out_date']) ? trim($_POST['check_out_date']) : '';
    $bedsToBook = isset($_POST['beds_to_book']) ? (int)$_POST['beds_to_book'] : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;
    
    // Validate dates
    $dateValidation = validateBookingDates($checkInDate, $checkOutDate);
    if (!$dateValidation['valid']) {
        $error = $dateValidation['error'];
    }
    
    // Validate beds
    if (!$error && ($bedsToBook < 1 || $bedsToBook > $room['available_beds'])) {
        $error = 'Invalid number of beds to book';
    }
    
    // Check availability
    if (!$error && !isRoomAvailable($roomId, $checkInDate, $checkOutDate)) {
        $error = 'Room is not available for these dates';
    }
    
    // Create booking
    if (!$error) {
        $totalAmount = calculateBookingAmount($room['price_per_month'], $checkInDate, $checkOutDate);
        $bookingResult = createBooking(
            $db,
            $_SESSION['user_id'],
            $roomId,
            $room['hostel_id'],
            $checkInDate,
            $checkOutDate,
            $bedsToBook,
            $totalAmount,
            $notes
        );
        
        if ($bookingResult['success']) {
            // Log activity
            Database::getInstance()->logActivity($_SESSION['user_id'], 'booking_created', 'Booking: ' . $bookingResult['booking_reference']);
            
            $_SESSION['booking_success'] = true;
            $_SESSION['booking_reference'] = $bookingResult['booking_reference'];
            header("Location: my_bookings.php");
            exit();
        } else {
            $error = $bookingResult['message'] ?? 'Error creating booking';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Room - Hostel Booking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-top: 60px;
        }
        .booking-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
            max-width: 800px;
            margin: 40px auto;
        }
        .room-card {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 5px;
        }
        .room-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 15px;
        }
        .detail-item {
            padding: 10px;
            background: white;
            border-radius: 5px;
        }
        .detail-label {
            font-weight: 600;
            color: #667eea;
            font-size: 0.9rem;
        }
        .detail-value {
            margin-top: 5px;
            font-size: 1.1rem;
        }
        .form-section {
            margin-bottom: 30px;
        }
        .form-section h5 {
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: #333;
        }
        .price-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-top: 30px;
        }
        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }
        .price-row.total {
            border-top: 2px solid #667eea;
            padding-top: 15px;
            font-size: 1.2rem;
            font-weight: 700;
            color: #667eea;
        }
        .btn-book {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 40px;
            font-weight: 600;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn-book:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            color: white;
        }
        .alert-container {
            margin-bottom: 30px;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

<div class="booking-container">
    <h1 class="mb-4">
        <i class="fas fa-door-open"></i> Book a Room
    </h1>
    
    <?php if ($error): ?>
        <div class="alert-container">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($room): ?>
        
        <!-- Room Information -->
        <div class="room-card">
            <h5><i class="fas fa-info-circle"></i> Room Information</h5>
            <div class="room-details-grid">
                <div class="detail-item">
                    <div class="detail-label">Room Number</div>
                    <div class="detail-value"><?php echo htmlspecialchars($room['room_number']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Room Type</div>
                    <div class="detail-value text-capitalize"><?php echo htmlspecialchars($room['room_type']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Capacity</div>
                    <div class="detail-value"><?php echo htmlspecialchars($room['capacity']); ?> persons</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Available Beds</div>
                    <div class="detail-value"><?php echo htmlspecialchars($room['available_beds']); ?> bed(s)</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Price per Month</div>
                    <div class="detail-value"><strong>$<?php echo number_format($room['price_per_month'], 2); ?></strong></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Hostel</div>
                    <div class="detail-value"><?php echo htmlspecialchars($hostel['name']); ?></div>
                </div>
            </div>
            <?php if ($room['amenities']): ?>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                    <strong>Amenities:</strong>
                    <div style="margin-top: 8px;">
                        <?php 
                        $amenities = is_string($room['amenities']) ? 
                            (str_starts_with(trim($room['amenities']), '[') || str_starts_with(trim($room['amenities']), '{') ?
                            json_decode($room['amenities'], true) : 
                            explode(',', $room['amenities'])) : 
                            [];
                        if (is_array($amenities)):
                            foreach ($amenities as $amenity): ?>
                                <span class="badge bg-secondary me-2 mb-2"><?php echo htmlspecialchars(trim($amenity)); ?></span>
                            <?php endforeach;
                        endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Booking Form -->
        <form method="POST" id="bookingForm">
            <div class="form-section">
                <h5><i class="fas fa-calendar"></i> Check-in & Check-out</h5>
                
                <div class="row">
                    <div class="col-md-6">
                        <label for="check_in_date" class="form-label">Check-in Date</label>
                        <input type="date" class="form-control" id="check_in_date" name="check_in_date" required>
                        <small class="text-muted">Select your arrival date</small>
                    </div>
                    <div class="col-md-6">
                        <label for="check_out_date" class="form-label">Check-out Date</label>
                        <input type="date" class="form-control" id="check_out_date" name="check_out_date" required>
                        <small class="text-muted">Select your departure date</small>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h5><i class="fas fa-bed"></i> Number of Beds</h5>
                <div class="col-md-6">
                    <label for="beds_to_book" class="form-label">Beds to Book</label>
                    <select class="form-select" id="beds_to_book" name="beds_to_book" required>
                        <option value="">-- Select --</option>
                        <?php 
                        for ($i = 1; $i <= $room['available_beds']; $i++) {
                            echo "<option value=\"$i\">$i bed(s)</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            
            <div class="form-section">
                <h5><i class="fas fa-sticky-note"></i> Additional Notes (Optional)</h5>
                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Any special requests or notes..."></textarea>
            </div>
            
            <!-- Price Summary -->
            <div class="price-summary">
                <h6 class="mb-3">Price Calculation</h6>
                <div class="price-row">
                    <span>Price per Month:</span>
                    <span>$<?php echo number_format($room['price_per_month'], 2); ?></span>
                </div>
                <div class="price-row">
                    <span>Duration (days):</span>
                    <span id="duration">-</span>
                </div>
                <div class="price-row">
                    <span>Calculated Duration (months):</span>
                    <span id="duration_months">-</span>
                </div>
                <div class="price-row total">
                    <span>Total Amount:</span>
                    <span id="total_amount">$0.00</span>
                </div>
                <small class="text-muted d-block mt-2">Formula: (days ÷ 30) × price per month</small>
            </div>
            
            <button type="submit" class="btn btn-primary btn-book w-100" id="submitBtn">
                <i class="fas fa-check-circle"></i> Confirm Booking
            </button>
        </form>
        
    <?php else: ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        <a href="dashboards/student.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    <?php endif; ?>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkInInput = document.getElementById('check_in_date');
    const checkOutInput = document.getElementById('check_out_date');
    const durationSpan = document.getElementById('duration');
    const durationMonthsSpan = document.getElementById('duration_months');
    const totalAmountSpan = document.getElementById('total_amount');
    const pricePerMonth = <?php echo $room['price_per_month'] ?? 0; ?>;
    
    function calculateTotal() {
        const checkIn = checkInInput.value;
        const checkOut = checkOutInput.value;
        
        if (!checkIn || !checkOut) {
            durationSpan.textContent = '-';
            durationMonthsSpan.textContent = '-';
            totalAmountSpan.textContent = '$0.00';
            return;
        }
        
        const checkInDate = new Date(checkIn);
        const checkOutDate = new Date(checkOut);
        
        if (checkOutDate <= checkInDate) {
            durationSpan.textContent = 'Invalid';
            durationMonthsSpan.textContent = '-';
            totalAmountSpan.textContent = '$0.00';
            return;
        }
        
        const days = Math.ceil((checkOutDate - checkInDate) / (1000 * 60 * 60 * 24));
        const months = (days / 30).toFixed(2);
        const total = (months * pricePerMonth).toFixed(2);
        
        durationSpan.textContent = days + ' day(s)';
        durationMonthsSpan.textContent = months + ' month(s)';
        totalAmountSpan.textContent = '$' + parseFloat(total).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    
    // Set minimum check-in date to today
    const today = new Date().toISOString().split('T')[0];
    checkInInput.setAttribute('min', today);
    checkOutInput.setAttribute('min', today);
    
    // Recalculate when dates change
    checkInInput.addEventListener('change', function() {
        // Update checkout minimum to be > checkin
        if (checkInInput.value) {
            const nextDay = new Date(checkInInput.value);
            nextDay.setDate(nextDay.getDate() + 1);
            checkOutInput.setAttribute('min', nextDay.toISOString().split('T')[0]);
        }
        calculateTotal();
    });
    
    checkOutInput.addEventListener('change', calculateTotal);
});
</script>

</body>
</html>
