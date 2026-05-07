<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $student_id = $_SESSION['user_id'];

    $room_id = $_POST['room_id'] ?? null; // used by quickBookRoom()
    $hostel_id = $_POST['hostel_id'] ?? null; // used by dashboard New Booking form
    $room_type_filter = $_POST['room_type_filter'] ?? null; // optional
    $check_in = $_POST['check_in'] ?? null;
    $check_out = $_POST['check_out'] ?? null;
    $notes = $_POST['notes'] ?? null;

    // Validate required fields
    if ((!$room_id && !$hostel_id) || !$check_in || !$check_out) {
        echo json_encode([
            'success' => false,
            'message' => 'Room or hostel and check-in/check-out dates are required'
        ]);
        exit();
    }

    // Validate dates
    if (!strtotime((string)$check_in) || !strtotime((string)$check_out)) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        exit();
    }

    $checkInDate = new DateTime((string)$check_in);
    $checkOutDate = new DateTime((string)$check_out);
    $today = new DateTime('today');

    if ($checkInDate < $today) {
        echo json_encode(['success' => false, 'message' => 'Check-in date cannot be in the past']);
        exit();
    }

    if ($checkOutDate <= $checkInDate) {
        echo json_encode(['success' => false, 'message' => 'Check-out date must be after check-in date']);
        exit();
    }

    $db = Database::getInstance()->getConnection();

    $selectedRoom = null;

    // Case 1: room_id provided (quick booking)
    if ($room_id) {
        $roomStmt = $db->prepare("
            SELECT hostel_id, room_number, price_per_semester as price
            FROM rooms
            WHERE id = ? AND status = 'available'
            LIMIT 1
        ");
        $roomStmt->execute([(int)$room_id]);
        $selectedRoom = $roomStmt->fetch();
        if (!$selectedRoom) {
            echo json_encode(['success' => false, 'message' => 'Room is not available']);
            exit();
        }
        $resolved_room_id = (int)$room_id;
    } else {
        // Case 2: dashboard booking form (hostel_id + dates). Pick an available room.
        $params = [(int)$hostel_id];

        $roomTypeSql = '';
        if ($room_type_filter) {
            $roomTypeSql = ' AND room_type = ? ';
            $params[] = $room_type_filter;
        }

        // Find any room that is marked available and has NO overlapping booking for requested dates.
        // Note: this booking logic uses bookings.check_in / bookings.check_out (DATETIME/DATE).
        $roomPickStmt = $db->prepare("
            SELECT
                r.id,
                r.hostel_id,
                r.room_number,
                r.room_type,
                r.price_per_semester as price
            FROM rooms r
            WHERE r.hostel_id = ?
              AND r.status = 'available'
              {$roomTypeSql}
              AND NOT EXISTS (
                  SELECT 1
                  FROM bookings b
                  WHERE b.room_id = r.id
                    AND b.status IN ('pending', 'approved', 'confirmed')
                    AND ((b.check_in <= ? AND b.check_out >= ?) OR (b.check_in <= ? AND b.check_out >= ?))
              )
            ORDER BY r.room_number ASC
            LIMIT 1
        ");

        // for overlap checks:
        $params[] = $check_out;
        $params[] = $check_in;
        $params[] = $check_in;
        $params[] = $check_out;

        $roomPickStmt->execute($params);
        $selectedRoom = $roomPickStmt->fetch();

        if (!$selectedRoom) {
            echo json_encode(['success' => false, 'message' => 'No available rooms found for the selected hostel and dates']);
            exit();
        }

        $resolved_room_id = (int)$selectedRoom['id'];
    }

    // Overlap check (defensive)
    $overlap = $db->prepare("
        SELECT COUNT(*)
        FROM bookings
        WHERE room_id = ?
          AND status IN ('pending', 'approved', 'confirmed')
          AND ((check_in <= ? AND check_out >= ?) OR (check_in <= ? AND check_out >= ?))
    ");
    $overlap->execute([
        $resolved_room_id,
        $check_out, $check_in,
        $check_in, $check_out
    ]);

    if ((int)$overlap->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Room is already booked for these dates']);
        exit();
    }

    // Calculate nights and total amount
    $total_nights = max(1, $checkInDate->diff($checkOutDate)->days);
    $total_amount = ((float)($selectedRoom['price'] ?? 0)) * $total_nights;

    // Generate unique booking reference
    $year = date('Y');
    $refStmt = $db->prepare("
        SELECT booking_reference
        FROM bookings
        WHERE booking_reference LIKE ?
        ORDER BY booking_reference DESC
        LIMIT 1
    ");
    $refStmt->execute(["BK-$year%"]);
    $lastRef = $refStmt->fetchColumn();

    if ($lastRef && preg_match('/BK-' . $year . '(\d+)/', (string)$lastRef, $matches)) {
        $count = (int)$matches[1] + 1;
    } else {
        $count = 1;
    }

    do {
        $booking_reference = sprintf("BK-%s%04d", $year, $count);
        $uniqueCheck = $db->prepare("SELECT id FROM bookings WHERE booking_reference = ?");
        $uniqueCheck->execute([$booking_reference]);
        $exists = $uniqueCheck->fetch();
        $count++;
    } while ($exists);

    $stmt = $db->prepare("
        INSERT INTO bookings (
            booking_reference,
            student_id,
            hostel_id,
            room_id,
            room_number,
            check_in,
            check_out,
            notes,
            total_amount,
            status,
            payment_status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'unpaid', NOW())
    ");

    $result = $stmt->execute([
        $booking_reference,
        $student_id,
        (int)$selectedRoom['hostel_id'],
        (int)$resolved_room_id,
        $selectedRoom['room_number'],
        $check_in,
        $check_out,
        $notes,
        $total_amount
    ]);

    if ($result) {
        $bookingId = $db->lastInsertId();

        // Auto-create payment record for this booking
        $paymentStmt = $db->prepare("
            INSERT INTO payments (
                booking_id,
                amount,
                status,
                payment_method,
                created_at,
                remarks
            ) VALUES (?, ?, 'pending', 'pending', NOW(), ?)
        ");
        $paymentStmt->execute([
            $bookingId,
            $total_amount,
            "Awaiting payment for booking $booking_reference"
        ]);

        Database::getInstance()->logActivity(
            $student_id,
            'booking_request',
            "Booking $booking_reference (#$bookingId) requested for room $resolved_room_id"
        );

        echo json_encode([
            'success' => true,
            'message' => 'Booking request submitted successfully. Please proceed to Payments to complete your payment.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit booking']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
