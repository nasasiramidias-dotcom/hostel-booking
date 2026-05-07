<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

$db = Database::getInstance()->getConnection();

// Get student bookings
$stmt = $db->prepare("SELECT b.*, h.name as hostel_name, r.room_number, r.room_type, r.price_per_semester as price 
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id 
    JOIN hostels h ON r.hostel_id = h.id 
    WHERE b.student_id = ? ORDER BY b.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll();

// Get available hostels for booking form
$hostels = $db->query("SELECT * FROM hostels WHERE status = 'active' ORDER BY name");

// Get student payments
$paymentStmt = $db->prepare("SELECT p.*, b.id as booking_id, b.status as booking_status, h.name as hostel_name, r.room_number, r.room_type, r.price_per_semester as price
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    JOIN rooms r ON b.room_id = r.id
    JOIN hostels h ON r.hostel_id = h.id
    WHERE b.student_id = ? ORDER BY p.created_at DESC");
$paymentStmt->execute([$_SESSION['user_id']]);
$payments = $paymentStmt->fetchAll();

// Separate payments by status
$pendingPayments = array_filter($payments, fn($p) => $p['status'] === 'pending');
$awaitingPayments = array_filter($payments, fn($p) => $p['status'] === 'paid');
$paymentHistory = array_filter($payments, fn($p) => $p['status'] === 'confirmed');

// Totals used only on the dashboard overview (NOT for the payment modal)
$dashboardCompletePaymentAmount = 0;
if ($paymentHistory) {
    $dashboardCompletePaymentAmount = array_sum(array_map(fn($p) => (float)($p['amount'] ?? 0), $paymentHistory));
}

// Get stats
$totalBookings = count($bookings);
$pendingBookings = count(array_filter($bookings, fn($b) => $b['status'] === 'pending'));
$confirmedBookings = count(array_filter($bookings, fn($b) => $b['status'] === 'confirmed'));
$rejectedBookings = count(array_filter($bookings, fn($b) => $b['status'] === 'rejected'));

// ---- Fetch approved (confirmed) bookings with payment details ----
$approvedStmt = $db->prepare("
    SELECT 
        b.id AS booking_id,
        b.status AS booking_status,
        b.created_at AS booking_date,
        h.name AS hostel_name,
        r.room_number,
        r.room_type,
        r.price_per_semester AS amount,
        p.id AS payment_id,
        p.status AS payment_status,
        p.payment_method,
        p.transaction_id,
        p.created_at AS payment_created_at
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    JOIN hostels h ON r.hostel_id = h.id
    LEFT JOIN payments p ON b.id = p.booking_id
    WHERE b.student_id = ? AND b.status = 'confirmed'
    ORDER BY b.created_at DESC
");
$approvedStmt->execute([$_SESSION['user_id']]);
$approvedBookings = $approvedStmt->fetchAll(PDO::FETCH_ASSOC);

// Group by booking ID (there could be multiple payment attempts, but we take the latest)
$approvedMap = [];
foreach ($approvedBookings as $row) {
    $bid = $row['booking_id'];
    if (!isset($approvedMap[$bid])) {
        $approvedMap[$bid] = [
            'booking_id'    => $bid,
            'hostel_name'   => $row['hostel_name'],
            'room_number'   => $row['room_number'],
            'room_type'     => $row['room_type'],
            'amount'        => $row['amount'],
            'booking_date'  => $row['booking_date'],
            'payments'      => []
        ];
    }
    // only if there is a real payment record (p.id not null)
    if ($row['payment_id']) {
        $approvedMap[$bid]['payments'][] = [
            'payment_id'     => $row['payment_id'],
            'status'         => $row['payment_status'],
            'method'         => $row['payment_method'],
            'transaction_id' => $row['transaction_id'],
            'date'           => $row['payment_created_at']
        ];
    }
}
// Determine final payment status for each booking
$finalApprovedList = [];
foreach ($approvedMap as $bid => $data) {
    $payments = $data['payments'];
    $paymentStatus = 'none';   // none, pending, paid, confirmed
    $existingPaymentId = null;
    $existingPaymentMethod = null;
    
    if (!empty($payments)) {
        // Check most recent payment status: priority confirmed > paid > pending
        usort($payments, function($a, $b) {
            return strtotime($b['date'] ?? '2000-01-01') - strtotime($a['date'] ?? '2000-01-01');
        });
        $latest = $payments[0];
        $paymentStatus = $latest['status'];
        $existingPaymentId = $latest['payment_id'];
        $existingPaymentMethod = $latest['method'];
    }
    
    $finalApprovedList[] = [
        'booking_id'         => $bid,
        'hostel_name'        => $data['hostel_name'],
        'room_number'        => $data['room_number'],
        'room_type'          => $data['room_type'],
        'amount'             => $data['amount'],
        'booking_date'       => $data['booking_date'],
        'payment_status'     => $paymentStatus,
        'existing_payment_id'=> $existingPaymentId,
        'payment_method'     => $existingPaymentMethod
    ];
}

// Count approved bookings that need payment (not confirmed payment)
$approvedNeedPayment = count(array_filter($finalApprovedList, fn($a) => $a['payment_status'] !== 'confirmed'));
$approvedTotalAmount = array_sum(array_map(fn($a) => (float)($a['amount'] ?? 0), $finalApprovedList));
$approvedPaidAmount = array_sum(array_map(fn($a) => $a['payment_status'] === 'confirmed' ? (float)($a['amount'] ?? 0) : 0, $finalApprovedList));
$approvedPendingAmount = $approvedTotalAmount - $approvedPaidAmount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - HostelHub</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../assets/css/browse-hostels.css">
    <style>
        /* Additional styles for browse hostels feature */
        .hostels-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .hostel-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .hostel-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .hostel-image {
            height: 180px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .hostel-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .hostel-image .image-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: white;
            font-size: 14px;
            text-align: center;
        }
        
        .hostel-image .placeholder-icon {
            font-size: 48px;
            font-weight: normal;
        }
        
        .hostel-info {
            padding: 20px;
        }
        
        .hostel-info h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 1.1em;
        }
        
        .hostel-location {
            color: #666;
            margin-bottom: 10px;
            font-size: 0.8em;
        }
        
        .hostel-stats {
            display: flex;
            gap: 15px;
            margin: 15px 0;
            padding: 10px 0;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
        }
        
        .stat {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.8em;
            color: #555;
        }
        
        .rooms-list {
            margin-top: 15px;
        }
        
        .room-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin: 8px 0;
            background: #f8f9fa;
            border-radius: 8px;
            transition: background 0.3s;
        }
        
        .room-item.available {
            border-left: 4px solid #28a745;
            background-color: #fff;
            transition: all 0.3s ease;
        }
        
        .room-item.available:hover {
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2);
        }
        
        .room-item.booked {
            border-left: 4px solid #dc3545;
            opacity: 0.75;
            background-color: #f8f9fa;
            position: relative;
        }
        
        .room-item.booked::before {
            content: 'LOCKED';
            position: absolute;
            right: 10px;
            top: 10px;
            font-size: 0.7em;
            background: #dc3545;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .room-details {
            flex: 1;
        }
        
        .room-number {
            font-weight: 600;
            color: #333;
            font-size: 0.9em;
        }
        
        .room-type {
            font-size: 0.75em;
            color: #666;
            margin-left: 10px;
        }
        
        .room-price {
            font-weight: 600;
            color: #28a745;
        }
        
        .room-status {
            font-size: 0.8em;
            padding: 3px 8px;
            border-radius: 12px;
            margin-left: 10px;
        }
        
        .status-available {
            background: #d4edda;
            color: #155724;
        }
        
        .status-booked {
            background: #f8d7da;
            color: #721c24;
        }
        
        .book-btn {
            padding: 5px 12px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.75em;
            transition: background 0.3s;
        }
        
        .book-btn:hover:not(:disabled) {
            background: #0056b3;
        }
        
        .book-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .filter-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .filter-group .form-group {
            flex: 1;
            min-width: 150px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.85em;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }

        /* Stats grid - 5 cards layout */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.12);
        }

        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 0.9em;
            color: #666;
            font-weight: 500;
        }

        .stat-card .number {
            font-size: 2em;
            font-weight: bold;
            color: #333;
        }

        .stat-card.approved-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card.approved-card h3 {
            color: black;
        }

        .stat-card.approved-card .number {
            color: black;
        }

        .stat-card.approved-card .sub-text {
            font-size: 0.75em;
            margin-top: 8px;
            color: black;
        }

        /* Badge styles without emojis */
        .badge-success::before {
            content: "✓ ";
        }
        
        .badge-warning::before {
            content: "⏳ ";
        }
        
        .badge-pending::before {
            content: "⏰ ";
        }
        
        .badge-danger::before {
            content: "⚠️ ";
        }
        
        .info-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            background: #6c757d;
            color: white;
            border-radius: 50%;
            font-size: 11px;
            font-weight: bold;
            cursor: help;
        }
    </style>
</head>
<body class="dashboard-body">
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>HostelHub</h2>
            <p>Student Portal</p>
        </div>
        <nav class="sidebar-nav">
            <a href="#" data-page="dashboard" class="active">Dashboard</a>
            <a href="#" data-page="browse-hostels">Browse Hostels</a>
            <a href="#" data-page="new-booking">New Booking</a>
            <a href="#" data-page="payments">Payments</a>
            <a href="#" data-page="bookings">My Bookings</a>
            <a href="#" data-page="profile">Profile</a>
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

        <!-- Dashboard Overview -->
        <div id="dashboard" class="page-section active">
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Bookings</h3>
                    <div class="number"><?php echo $totalBookings; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending</h3>
                    <div class="number"><?php echo $pendingBookings; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Confirmed</h3>
                    <div class="number"><?php echo $confirmedBookings; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Rejected</h3>
                    <div class="number"><?php echo $rejectedBookings; ?></div>
                </div>
                <div class="stat-card approved-card">
                    <h3>Approved</h3>
                    <div class="number"><?php echo $approvedNeedPayment; ?></div>
                    <div class="sub-text"></div>
                </div>
            </div>
            
            <!-- Detailed Approved Bookings Container -->
            <?php if (!empty($finalApprovedList)): ?>
            <div class="card" id="approved-bookings-card" style="margin-top: 0; margin-bottom: 25px;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 12px; margin-bottom: 20px;">
                    <h3 style="margin: 0;">Approved Bookings – Payment Required</h3>
                    <span class="badge badge-confirmed" style="background: #28a745; font-size: 0.85rem;">
                        <?php echo count($finalApprovedList); ?> booking(s) confirmed
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="data-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Hostel</th>
                                <th>Room</th>
                                <th>Room Type</th>
                                <th>Amount (UGX)</th>
                                <th>Payment Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($finalApprovedList as $approved): 
                                $paymentStatus = $approved['payment_status'];
                                $statusBadge = '';
                                $actionDisabled = false;
                                $actionText = '';
                                
                                if ($paymentStatus === 'confirmed') {
                                    $statusBadge = '<span class="badge badge-success" style="background:#28a745;">Paid & Confirmed</span>';
                                    $actionText = 'Paid';
                                    $actionDisabled = true;
                                } elseif ($paymentStatus === 'paid') {
                                    $statusBadge = '<span class="badge badge-warning" style="background:#ffc107; color:#333;">Awaiting Confirmation</span>';
                                    $actionText = 'Processing';
                                    $actionDisabled = true;
                                } elseif ($paymentStatus === 'pending') {
                                    $statusBadge = '<span class="badge badge-pending" style="background:#ff9800;">Pending Payment</span>';
                                    $actionText = 'Resume Payment';
                                    $actionDisabled = false;
                                } else {
                                    $statusBadge = '<span class="badge badge-danger" style="background:#dc3545;">Not Paid</span>';
                                    $actionText = 'Pay Now';
                                    $actionDisabled = false;
                                }
                            ?>
                                <tr data-booking-id="<?php echo $approved['booking_id']; ?>">
                                    <td><strong><?php echo htmlspecialchars($approved['hostel_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($approved['room_number']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($approved['room_type'])); ?></td>
                                    <td>UGX <?php echo number_format($approved['amount'], 2); ?></td>
                                    <td><?php echo $statusBadge; ?></span></td>
                                    <td>
                                        <?php if (!$actionDisabled): ?>
                                            <button class="btn btn-primary pay-approved-btn" 
                                                    data-booking-id="<?php echo $approved['booking_id']; ?>"
                                                    data-amount="<?php echo $approved['amount']; ?>"
                                                    data-hostel="<?php echo htmlspecialchars($approved['hostel_name']); ?>"
                                                    data-room="<?php echo htmlspecialchars($approved['room_number']); ?>"
                                                    data-room-type="<?php echo htmlspecialchars($approved['room_type']); ?>"
                                                    data-existing-payment-id="<?php echo $approved['existing_payment_id']; ?>"
                                                    style="padding: 6px 12px; font-size: 0.85rem;">
                                                <?php echo $actionText; ?>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-secondary" disabled style="opacity:0.7; cursor:not-allowed;">
                                                <?php echo $actionText; ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top: 15px; font-size: 0.85rem; background: #f8f9fa; padding: 10px; border-radius: 8px;">
                    <strong>Note:</strong> Once you complete payment, the hostel admin will confirm your payment and your booking becomes fully active.
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <h3>Recent Bookings</h3>
                <?php if ($bookings): ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>Hostel</th><th>Room</th><th>Type</th><th>Status</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($bookings, 0, 5) as $b): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($b['hostel_name']); ?></td>
                                    <td><?php echo htmlspecialchars($b['room_number']); ?></span></td>
                                    <td><?php echo htmlspecialchars($b['room_type']); ?></span></td>
                                    <td><span class="badge badge-<?php echo $b['status']; ?>"><?php echo ucfirst($b['status']); ?></span></span></span></td>
                                    <td><?php echo date('M d, Y', strtotime($b['created_at'])); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-data">No bookings yet. <a href="#" onclick="showPage('new-booking')">Request a booking</a></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Browse Hostels Page -->
        <div id="browse-hostels" class="page-section">
            <div class="card">
                <h3>Browse Available Hostels</h3>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form id="hostelFilterForm">
                        <div class="filter-group">
                            <div class="form-group">
                                <label>Filter by Room Type</label>
                                <select id="filterRoomType" name="room_type">
                                    <option value="">All Room Types</option>
                                    <?php
                                    $roomTypesStmt = $db->query("SELECT DISTINCT room_type FROM rooms WHERE room_type IS NOT NULL AND room_type <> '' ORDER BY room_type");
                                    while ($rt = $roomTypesStmt->fetch()) {
                                        echo '<option value="' . htmlspecialchars($rt['room_type']) . '">' . htmlspecialchars(ucfirst($rt['room_type'])) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Check-in Date</label>
                                <input type="date" id="filterCheckIn" name="check_in" min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Check-out Date</label>
                                <input type="date" id="filterCheckOut" name="check_out" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                            </div>
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="button" class="btn-secondary" onclick="applyFilters()">Apply Filters</button>
                                <button type="button" class="btn-secondary" onclick="resetFilters()" style="background: #dc3545;">Reset</button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Hostels Grid -->
                <div id="hostelsGrid" class="hostels-grid">
                    <div style="text-align: center; padding: 50px;">Loading hostels...</div>
                </div>
            </div>
        </div>

        <!-- My Bookings -->
        <div id="bookings" class="page-section">
            <div class="card">
                <h3>All My Bookings</h3>
                <?php if ($bookings): ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>ID</th><th>Hostel</th><th>Room</th><th>Type</th><th>Price</th><th>Status</th><th>Requested</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $b): ?>
                                <tr>
                                    <td>#<?php echo $b['id']; ?></span></td>
                                    <td><?php echo htmlspecialchars($b['hostel_name']); ?></span></td>
                                    <td><?php echo htmlspecialchars($b['room_number']); ?></span></span></td>
                                    <td><?php echo htmlspecialchars($b['room_type']); ?></span></span></td>
                                    <td>UGX <?php echo number_format($b['price'], 2); ?></span></span></td>
                                    <td><span class="badge badge-<?php echo $b['status']; ?>"><?php echo ucfirst($b['status']); ?></span></span></span></span></span></span></span></span></span></span></span></span></span></td>
                                    <td><?php echo date('M d, Y', strtotime($b['created_at'])); ?></span></span></span></span></span></span></span></span></span></span></span></span></span></td>
                                    <td>
                                        <?php if ($b['status'] === 'pending'): ?>
                                            <button class="btn btn-danger" onclick="cancelBooking(<?php echo $b['id']; ?>)">Cancel</button>
                                        <?php endif; ?>
                                    </span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-data">No bookings found.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payments -->
        <div id="payments" class="page-section">
            <div class="card">
                <h3>Available Payment Methods</h3>
                <div class="payment-methods-legend">
                    <div class="payment-method-indicator" onclick="showPaymentDetails('cash')">
                        
                        <div class="payment-method">
                        <span class="badge badge-cash">Cash</span>
                        <div class="method-desc">Pay at Finance Office</div>
                          </div>
                    </div>
                    <div class="payment-method-indicator" onclick="showPaymentDetails('bank_transfer')">
                        <span class="badge badge-bank_transfer">Bank Transfer</span>
                        <div class="method-desc">Upload proof required</div>
                        
                    </div>
                    <div class="payment-method-indicator" onclick="showPaymentDetails('mobile_money')">
                        <span class="badge badge-mobile_money">Mobile Money</span>
                        <div class="method-desc">MPesa,Airtel Money, MTN Uganda</div>
                        
                    </div>
                    <div class="payment-method-indicator" onclick="showPaymentDetails('credit_card')">
                        <span class="badge badge-credit_card">Credit Card</span>
                        <div class="method-desc">Visa,Mastercard, etc </div>
    
                    </div>
                </div>

                <div class="payment-details-panel" id="paymentDetailsPanel">
                    <h4 id="detailsTitle">Payment Details</h4>
                    <div id="detailsContent"></div>
                </div>
            </div>

            <div class="card">
                <h3>Pending Payments</h3>
                <?php if ($pendingPayments): ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>ID</th><th>Hostel</th><th>Room</th><th>Amount</th><th>Method</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingPayments as $p): ?>
                                <tr>
                                    <td>#<?php echo $p['id']; ?></span></td>
                                    <td><?php echo htmlspecialchars($p['hostel_name']); ?></span></span></span></span></span></span></span></span></span></td>
                                    <td><?php echo htmlspecialchars($p['room_number']); ?> (<?php echo htmlspecialchars($p['room_type']); ?>)</span></span></span></span></span></td>
                                    <td>UGX <?php echo number_format($p['amount'], 2); ?></span></span></td>
                                    <td>
                                        <?php if ($p['payment_method'] === 'pending'): ?>
                                            <span class="badge badge-pending">Not Selected</span>
                                        <?php else: ?>
                                            <span class="badge badge-<?php echo $p['payment_method']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $p['payment_method'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </span></td>
                                    <td>
                                        <button class="btn btn-primary" onclick="openPaymentModal(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['hostel_name']); ?>', '<?php echo htmlspecialchars($p['room_number']); ?>', <?php echo $p['amount']; ?>, '<?php echo htmlspecialchars($p['room_type']); ?>')">Pay Now</button>
                                    </span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-data">No pending payments. Your approved bookings will appear here for payment.</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3>Awaiting Confirmation</h3>
                <?php if ($awaitingPayments): ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>ID</th><th>Hostel</th><th>Room</th><th>Amount</th><th>Method</th><th>Reference</th><th>Date Paid</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($awaitingPayments as $p): ?>
                                <tr>
                                    <td>#<?php echo $p['id']; ?></span></td>
                                    <td><?php echo htmlspecialchars($p['hostel_name']); ?></span></span></span></span></span>\n                                    <td><?php echo htmlspecialchars($p['room_number']); ?></span></span></span></span></span></td>
                                    <td>UGX <?php echo number_format($p['amount'], 2); ?></span></span></td>
                                    <td>
                                        <span class="badge badge-<?php echo $p['payment_method']; ?>"><?php echo ucfirst(str_replace('_', ' ', $p['payment_method'])); ?></span>
                                    </span></td>
                                    <td><?php echo htmlspecialchars($p['transaction_id'] ?? 'N/A'); ?></span></span></td>
                                    <td><?php echo $p['payment_date'] ? date('M d, Y', strtotime($p['payment_date'])) : 'N/A'; ?></span></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-data">No payments awaiting confirmation.</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3>Payment History</h3>
                <?php if ($paymentHistory): ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>ID</th><th>Hostel</th><th>Room</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paymentHistory as $p): ?>
                                <tr>
                                    <td>#<?php echo $p['id']; ?></span></td>
                                    <td><?php echo htmlspecialchars($p['hostel_name']); ?></span></span></span></span></span></span></span></span></span></span></td>
                                    <td><?php echo htmlspecialchars($p['room_number']); ?></span></span></span></span></span></span></span></span></span></span></td>
                                    <td>UGX <?php echo number_format($p['amount'], 2); ?></span></span></td>
                                    <td>
                                        <span class="badge badge-<?php echo $p['payment_method']; ?>"><?php echo ucfirst(str_replace('_', ' ', $p['payment_method'])); ?></span>
                                    </span></td>
                                    <td><span class="badge badge-<?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span></span></span></span></span></span></span></span></span></span></td>
                                    <td><?php echo date('M d, Y', strtotime($p['created_at'])); ?></span></span></span></span></span></span></span></span></span></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-data">No payment history yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- New Booking -->
        <div id="new-booking" class="page-section">
            <div class="card">
                <h3>Request New Booking</h3>
                <div id="booking-message" class="booking-message" style="display:none;"></div>
                <form id="bookingForm" method="POST" action="../api/add_booking.php">
                    <div class="form-group">
                        <label>Select Hostel</label>
                        <select name="hostel_id" id="hostelSelect" required>
                            <option value="">Choose a hostel...</option>
                            <?php foreach ($hostels as $h): ?>
                                <option value="<?php echo $h['id']; ?>"><?php echo htmlspecialchars($h['name']); ?> - <?php echo htmlspecialchars($h['location']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Select Room Type</label>
                        <select id="roomTypeSelect" name="room_type_filter">
                            <option value="">All room types</option>
                            <?php
                            $roomTypesStmt = $db->query("SELECT DISTINCT room_type FROM rooms WHERE room_type IS NOT NULL AND room_type <> '' ORDER BY room_type");
                            while ($rt = $roomTypesStmt->fetch()) {
                                $rtVal = $rt['room_type'];
                                echo '<option value="' . htmlspecialchars($rtVal) . '">' . htmlspecialchars(ucfirst($rtVal)) . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Check-in Date</label>
                        <input type="date" name="check_in" id="checkInInput" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Check-out Date</label>
                        <input type="date" name="check_out" id="checkOutInput" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                    </div>

                    <button type="submit" class="btn btn-primary">Submit Booking Request</button>
                </form>
            </div>
        </div>

        <!-- Profile -->
        <div id="profile" class="page-section">
            <div class="card">
                <h3>My Profile</h3>
                <table class="data-table profile-table">
                    <tbody>
                        <tr>
                            <th>Full Name</th>
                            <td><?php echo htmlspecialchars($_SESSION['full_name']); ?></span></td>
                        </tr>
                        <tr>
                            <th>Username</th>
                            <td><?php echo htmlspecialchars($_SESSION['username']); ?></span></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><?php echo htmlspecialchars($_SESSION['username']); ?>@student.university.edu</span></td>
                        </tr>
                        <tr>
                            <th>Role</th>
                            <td><span class="badge badge-approved">Student</span></span></td>
                        </tr>
                        <tr>
                            <th>Member Since</th>
                            <td><?php echo date('M Y', strtotime($_SESSION['created_at'] ?? '2024-01-01')); ?></span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // -------------------------
        // Sidebar / page navigation
        // -------------------------
        function showPage(pageId) {
            document.querySelectorAll('.page-section').forEach(section => {
                section.classList.remove('active');
            });
            const el = document.getElementById(pageId);
            if (el) el.classList.add('active');

            document.querySelectorAll('.sidebar-nav a[data-page]').forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('data-page') === pageId) {
                    link.classList.add('active');
                }
            });

            if (pageId === 'browse-hostels') {
                loadHostels();
            }
        }

        document.querySelectorAll('.sidebar-nav a[data-page]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                showPage(link.getAttribute('data-page'));
            });
        });

        // -------------------------
        // Browse Hostels
        // -------------------------
        function loadHostels() {
            const roomType = document.getElementById('filterRoomType') ? document.getElementById('filterRoomType').value : '';
            const checkIn = document.getElementById('filterCheckIn') ? document.getElementById('filterCheckIn').value : '';
            const checkOut = document.getElementById('filterCheckOut') ? document.getElementById('filterCheckOut').value : '';

            const params = new URLSearchParams();
            if (roomType) params.append('room_type', roomType);
            if (checkIn) params.append('check_in', checkIn);
            if (checkOut) params.append('check_out', checkOut);

            const grid = document.getElementById('hostelsGrid');
            if (!grid) return;
            grid.innerHTML = '<div style="text-align: center; padding: 50px;">Loading hostels...</div>';

            const apiUrl = '../api/get_available_hostels.php' + (params.toString() ? '?' + params.toString() : '');

            fetch(apiUrl, { credentials: 'same-origin' })
                .then(res => {
                    if (!res.ok) {
                        return res.text().then(t => {
                            throw new Error('Server error: ' + res.status + ' - ' + t);
                        });
                    }
                    return res.json();
                })
                .then(data => {
                    if (data && data.error) {
                        grid.innerHTML = `<div style="text-align: center; padding: 50px; color: red;">Error: ${data.error}</div>`;
                        return;
                    }
                    if (!data || data.length === 0) {
                        grid.innerHTML = '<div style="text-align: center; padding: 50px;">No hostels found. Please check back later.</div>';
                        return;
                    }
                    displayHostels(data);
                })
                .catch(err => {
                    console.error('Error loading hostels:', err);
                    grid.innerHTML = `<div style="text-align: center; padding: 50px; color: red;">Error loading hostels: ${err.message}</div>`;
                });
        }

        function displayHostels(hostels) {
            const grid = document.getElementById('hostelsGrid');
            if (!grid) return;

            if (!hostels || hostels.length === 0) {
                grid.innerHTML = '<div style="text-align: center; padding: 50px;">No hostels found matching your criteria.</div>';
                return;
            }

            let html = '';
            hostels.forEach(hostel => {
                const rooms = hostel.rooms || [];
                // Get hostel image URL from database or use placeholder
                const hostelImage = hostel.image_url || null;
                
                html += `
                    <div class="hostel-card">
                        <div class="hostel-image">
                            ${hostelImage ? 
                                `<img src="${escapeHtml(hostelImage)}" alt="${escapeHtml(hostel.name)}" onerror="this.onerror=null; this.parentElement.innerHTML=this.parentElement.innerHTML;">` : 
                                `<div class="image-placeholder">
                                    <div class="placeholder-icon">🏢</div>
                                    <div>No Image Available</div>
                                </div>`
                            }
                        </div>
                        <div class="hostel-info">
                            <h3>${escapeHtml(hostel.name)}</h3>
                            <div class="hostel-location">${escapeHtml(hostel.location)}</div>
                            <div class="hostel-stats">
                                <div class="stat">Rooms: ${hostel.total_rooms ?? 0} Total</div>
                                <div class="stat">Available: ${hostel.available_rooms ?? 0}</div>
                                <div class="stat">Booked: ${(hostel.total_rooms ?? 0) - (hostel.available_rooms ?? 0)}</div>
                            </div>
                            <div style="margin-top: 10px; padding: 8px 0;">
                                <div style="font-size: 0.8em; color: #555; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                                    <span>Availability: <strong>${(() => {
                                        const total = parseInt(hostel.total_rooms) || 0;
                                        const available = parseInt(hostel.available_rooms) || 0;
                                        if (total === 0) return 'No Rooms';
                                        const percent = Math.round((available / total) * 100);
                                        return Number.isNaN(percent) ? '0%' : percent + '%';
                                    })()}</strong></span>
                                    <span class="info-icon" title="Percentage of rooms available for your selected dates (no overlapping bookings)">i</span>
                                </div>
                                <div style="width: 100%; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
                                    <div style="width: ${(() => {
                                        const total = parseInt(hostel.total_rooms) || 0;
                                        const available = parseInt(hostel.available_rooms) || 0;
                                        if (total === 0) return '0';
                                        const percent = (available / total) * 100;
                                        return Number.isNaN(percent) ? 0 : percent;
                                    })()}%; height: 100%; background: linear-gradient(90deg, #28a745, #20c997); transition: width 0.3s ease;"></div>
                                </div>
                            </div>
                            <div class="rooms-list">
                                <strong>Rooms (${rooms.filter(r => r.is_available).length} Available / ${rooms.length} Total):</strong>
                                ${rooms.length ? rooms.map(room => `
                                    <div class="room-item ${room.is_available ? 'available' : 'booked'}" style="${!room.is_available ? 'opacity: 0.7; background: #f5f5f5;' : ''}">
                                        <div class="room-details">
                                            <span class="room-number">Room ${escapeHtml(room.room_number)}</span>
                                            <span class="room-type">${escapeHtml(room.room_type || '')}</span>
                                            ${!room.is_available ? '<span style="display: inline-block; background: #dc3545; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.7em; font-weight: 700; margin-left: 5px;">BOOKED</span>' : '<span style="display: inline-block; background: #28a745; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.7em; font-weight: 700; margin-left: 5px;">AVAILABLE</span>'}
                                        </div>
                                        <div class="room-price">UGX ${formatNumber(room.price_per_semester)}</div>
                                        <button class="book-btn" type="button"
                                            onclick="quickBookRoom(${room.id}, '${escapeHtml(hostel.name)}', '${escapeHtml(room.room_number)}')"
                                            style="${!room.is_available ? 'background: #ccc; cursor: not-allowed;' : ''}"
                                            ${room.is_available ? '' : 'disabled'}>
                                            ${room.is_available ? 'Book Now' : 'Not Available'}
                                        </button>

                                        ${!room.is_available && room.booked_ranges && room.booked_ranges.length ? `
                                            <div style="margin-top:10px; width:100%; padding: 10px; background: #fff3cd; border-left: 4px solid #ff6b6b; border-radius: 4px;">
                                                <div style="font-size:0.85em; font-weight:700; color:#721c24; margin-bottom: 8px;">
                                                    BOOKED FOR:
                                                </div>
                                                <div style="margin-top:6px;">
                                                    ${room.booked_ranges.map(br => {
                                                        const checkInDate = new Date(br.check_in);
                                                        const checkOutDate = new Date(br.check_out);
                                                        const formattedCheckIn = checkInDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                                                        const formattedCheckOut = checkOutDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                                                        return `
                                                            <div style="font-size:0.8em; color:#333; padding:8px; background:#fff; border:2px solid #ff9999; border-radius:6px; margin:6px 0;">
                                                                <div style="font-weight:700; color: #dc3545;">
                                                                    ${formattedCheckIn} → ${formattedCheckOut}
                                                                </div>
                                                                <div style="margin-top:4px; font-size:0.75em; color:#555;">
                                                                    Booking Status: <strong>${escapeHtml(String(br.status || 'pending')).toUpperCase()}</strong>
                                                                </div>
                                                            </div>
                                                        `;
                                                    }).join('')}
                                                </div>
                                            </div>
                                        ` : ''}
                                    </div>
                                `).join('') : '<p style="color: #999; padding: 10px;">No rooms at this hostel</p>'}
                            </div>
                        </div>
                    </div>
                `;
            });

            grid.innerHTML = html;
        }

        function quickBookRoom(roomId, hostelName, roomNumber) {
            const checkInEl = document.getElementById('filterCheckIn');
            const checkOutEl = document.getElementById('filterCheckOut');
            const checkIn = checkInEl ? checkInEl.value : '';
            const checkOut = checkOutEl ? checkOutEl.value : '';

            if (!checkIn || !checkOut) {
                alert('Select check-in and check-out dates in Browse Hostels first.');
                showPage('new-booking');
                return;
            }

            if (confirm(`Book Room ${roomNumber} at ${hostelName}?\nCheck-in: ${checkIn}\nCheck-out: ${checkOut}`)) {
                const formData = new FormData();
                formData.append('room_id', roomId);
                formData.append('check_in', checkIn);
                formData.append('check_out', checkOut);
                formData.append('notes', '');

                fetch('../api/add_booking.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('Booking request submitted successfully!');
                        location.reload();
                    } else {
                        alert('Failed to book: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(err => {
                    alert('Error: ' + err.message);
                });
            }
        }

        function applyFilters() {
            loadHostels();
        }

        function resetFilters() {
            const rt = document.getElementById('filterRoomType');
            const ci = document.getElementById('filterCheckIn');
            const co = document.getElementById('filterCheckOut');
            if (rt) rt.value = '';
            if (ci) ci.value = '';
            if (co) co.value = '';
            loadHostels();
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        function formatNumber(num) {
            if (num === null || num === undefined || num === '') return '0';
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        document.addEventListener('DOMContentLoaded', function () {
            const browse = document.getElementById('browse-hostels');
            if (browse && browse.classList.contains('active')) {
                loadHostels();
            }
        });

        // -------------------------
        // New Booking handler
        // -------------------------
        const bookingForm = document.getElementById('bookingForm');
        if (bookingForm) {
            bookingForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const formData = new FormData(this);
                const messageDiv = document.getElementById('booking-message');

                fetch(this.getAttribute('action'), {
                    method: 'POST',
                    body: formData
                })
                .then(res => {
                    if (!res.ok) throw new Error('Server error: ' + res.status);
                    return res.json();
                })
                .then(data => {
                    messageDiv.style.display = 'block';
                    if (data.success) {
                        messageDiv.className = 'booking-message success';
                        messageDiv.textContent = data.message || 'Booking request submitted successfully!';
                        this.reset();
                    } else {
                        messageDiv.className = 'booking-message error';
                        messageDiv.textContent = data.message || 'Failed to submit booking request.';
                    }
                })
                .catch(err => {
                    console.error('Booking error:', err);
                    messageDiv.style.display = 'block';
                    messageDiv.className = 'booking-message error';
                    messageDiv.textContent = 'Error submitting booking request: ' + err.message;
                });
            });
        }

        // -------------------------
        // Payments
        // -------------------------
        function showPaymentDetails(method) {
            const message =
                method === 'cash'
                    ? 'Cash: Pay at the Finance Office.'
                    : method === 'bank_transfer'
                        ? 'Bank Transfer: You will be asked for a transaction reference.'
                        : method === 'mobile_money'
                            ? 'Mobile Money: You will be asked for a transaction reference.'
                            : method === 'credit_card'
                                ? 'Credit Card: You will be asked for a transaction reference.'
                                : 'Unknown payment method.';
            alert(message);
        }

        function openPaymentModal(paymentId, hostelName, roomNumber, amount, roomType) {
            const method = prompt(
                'Choose payment method (type one):\n' +
                'cash\n' +
                'bank_transfer\n' +
                'mobile_money\n' +
                'credit_card\n\n' +
                `Payment for: ${hostelName} • Room ${roomNumber} (${roomType}) • UGX ${amount}`
            );
            if (!method) return;
            const validMethods = ['cash', 'bank_transfer', 'mobile_money', 'credit_card'];
            const selectedMethod = method.trim().toLowerCase();
            if (!validMethods.includes(selectedMethod)) {
                alert('Invalid payment method.');
                return;
            }

            let transactionId = '';
            if (selectedMethod !== 'cash') {
                transactionId = prompt('Enter transaction reference / Transaction ID:');
                if (!transactionId || transactionId.trim() === '') {
                    alert('Transaction ID / reference is required for this method.');
                    return;
                }
            }

            const formData = new FormData();
            formData.append('payment_id', paymentId);
            formData.append('payment_method', selectedMethod);
            if (selectedMethod !== 'cash') formData.append('transaction_id', transactionId);

            fetch('../api/make_payment.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(async res => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    throw new Error(data.message || ('Payment failed (HTTP ' + res.status + ')'));
                }
                return data;
            })
            .then(data => {
                alert(data.message || 'Payment submitted successfully!');
                location.reload();
            })
            .catch(err => {
                console.error(err);
                alert(err.message || 'Payment failed');
            });
        }

        // Approved Bookings Payment Helper
        async function createPaymentForBooking(bookingId) {
            const response = await fetch('../api/create_payment_for_booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: 'booking_id=' + encodeURIComponent(bookingId)
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Could not create payment record');
            }
            return data.payment_id;
        }

        document.querySelectorAll('.pay-approved-btn').forEach(btn => {
            btn.addEventListener('click', async function(e) {
                e.preventDefault();
                const bookingId = this.dataset.bookingId;
                const amount = this.dataset.amount;
                const hostel = this.dataset.hostel;
                const room = this.dataset.room;
                const roomType = this.dataset.roomType;
                let existingPaymentId = this.dataset.existingPaymentId;
                
                if (!existingPaymentId || existingPaymentId === '0' || existingPaymentId === '') {
                    try {
                        const result = await createPaymentForBooking(bookingId);
                        existingPaymentId = result;
                    } catch (err) {
                        alert('Unable to prepare payment: ' + err.message);
                        return;
                    }
                }
                
                if (window.openPaymentModal) {
                    window.openPaymentModal(existingPaymentId, hostel, room, amount, roomType);
                } else {
                    console.error('openPaymentModal function not defined');
                    alert('Payment method selection temporarily unavailable. Please refresh the page.');
                }
            });
        });

        function displayHostels(hostels) {
    const grid = document.getElementById('hostelsGrid');
    if (!grid) return;

    if (!hostels || hostels.length === 0) {
        grid.innerHTML = '<div style="text-align: center; padding: 50px;">No hostels found matching your criteria.</div>';
        return;
    }

    let html = '';
    hostels.forEach(hostel => {
        const rooms = hostel.rooms || [];
        const imagePath = hostel.image ? `../uploads/hostels/${hostel.image}` : '';
        const imageThumbPath = hostel.image_thumbnail ? `../uploads/hostels/${hostel.image_thumbnail}` : imagePath;
        
        html += `
            <div class="hostel-card">
                <div class="hostel-image" style="${!hostel.image ? 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);' : ''}">
                    ${hostel.image ? `<img src="${imageThumbPath}" alt="${escapeHtml(hostel.name)}" style="width: 100%; height: 100%; object-fit: cover;">` : '🏨'}
                </div>
                <div class="hostel-info">
                    <h3>${escapeHtml(hostel.name)}</h3>
                    <div class="hostel-location">${escapeHtml(hostel.location)}</div>
                    <div class="hostel-stats">
                        <div class="stat">Total Rooms: ${hostel.total_rooms || 0}</div>
                        <div class="stat">Available: ${hostel.available_rooms || 0}</div>
                        <div class="stat">Booked: ${(hostel.total_rooms || 0) - (hostel.available_rooms || 0)}</div>
                    </div>
                    <div style="margin-top: 10px; padding: 8px 0;">
                        <div style="font-size: 0.8em; color: #555; margin-bottom: 4px;">
                            Availability: <strong>${(() => {
                                const total = parseInt(hostel.total_rooms) || 0;
                                const available = parseInt(hostel.available_rooms) || 0;
                                if (total === 0) return 'No Rooms';
                                const percent = Math.round((available / total) * 100);
                                return Number.isNaN(percent) ? '0%' : percent + '%';
                            })()}</strong>
                        </div>
                        <div style="width: 100%; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
                            <div style="width: ${(() => {
                                const total = parseInt(hostel.total_rooms) || 0;
                                const available = parseInt(hostel.available_rooms) || 0;
                                if (total === 0) return '0';
                                const percent = (available / total) * 100;
                                return Number.isNaN(percent) ? 0 : percent;
                            })()}%; height: 100%; background: linear-gradient(90deg, #28a745, #20c997);"></div>
                        </div>
                    </div>
                    <div class="rooms-list">
                        <strong>Rooms (${rooms.filter(r => r.is_available).length} Available / ${rooms.length} Total):</strong>
                        ${rooms.length ? rooms.map(room => `
                            <div class="room-item ${room.is_available ? 'available' : 'booked'}" style="${!room.is_available ? 'opacity: 0.7; background: #f5f5f5;' : ''}">
                                <div class="room-details">
                                    <span class="room-number">Room ${escapeHtml(room.room_number)}</span>
                                    <span class="room-type">${escapeHtml(room.room_type || '')}</span>
                                    ${!room.is_available ? '<span class="room-status status-booked">BOOKED</span>' : '<span class="room-status status-available">AVAILABLE</span>'}
                                </div>
                                <div class="room-price">UGX ${formatNumber(room.price_per_semester)}</div>
                                <button class="book-btn" type="button"
                                    onclick="quickBookRoom(${room.id}, '${escapeHtml(hostel.name)}', '${escapeHtml(room.room_number)}')"
                                    ${room.is_available ? '' : 'disabled'}>
                                    ${room.is_available ? 'Book Now' : 'Not Available'}
                                </button>
                            </div>
                        `).join('') : '<p style="color: #999; padding: 10px;">No rooms at this hostel</p>'}
                    </div>
                </div>
            </div>
        `;
    });

    grid.innerHTML = html;
}
    </script>
</body>
</html>