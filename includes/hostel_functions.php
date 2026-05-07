<?php
/**
 * Hostel Booking System Helper Functions
 * Centralized helpers for:
 * - hostel/room retrieval
 * - availability checks (overlapping bookings)
 * - booking creation with bed decrement + room full status
 * - booking cancellation with bed restore (beds_booked stored in notes JSON)
 */

declare(strict_types=1);

/**
 * Escape for HTML output.
 */
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Get active hostels ordered by name.
 * @param PDO $db
 * @return array<int, array<string, mixed>>
 */
function getActiveHostels(PDO $db): array {
    $stmt = $db->prepare("SELECT * FROM hostels WHERE status = 'active' ORDER BY name ASC");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Search active hostels by name/address/address-like fields.
 * @param PDO $db
 * @param string $term
 * @return array<int, array<string, mixed>>
 */
function searchActiveHostels(PDO $db, string $term): array {
    $like = '%' . $term . '%';
    $stmt = $db->prepare("
        SELECT * FROM hostels
        WHERE status = 'active'
          AND (name LIKE ? OR address LIKE ? OR description LIKE ?)
        ORDER BY name ASC
    ");
    $stmt->execute([$like, $like, $like]);
    return $stmt->fetchAll();
}

/**
 * Get rooms for a hostel filtered as per spec:
 * - status='available'
 * - available_beds > 0
 * Optionally filter by room_type.
 *
 * @param PDO $db
 * @param int $hostelId
 * @param string|null $roomType
 * @return array<int, array<string, mixed>>
 */
function getAvailableRoomsForHostel(PDO $db, int $hostelId, ?string $roomType = null): array {
    $query = "
        SELECT id, room_number, room_type, capacity, price_per_month, available_beds, total_beds, amenities, status, hostel_id
        FROM rooms
        WHERE hostel_id = ?
          AND status = 'available'
          AND available_beds > 0
    ";
    $params = [$hostelId];

    if ($roomType !== null && $roomType !== '') {
        $query .= " AND room_type = ?";
        $params[] = $roomType;
    }

    $query .= " ORDER BY room_number ASC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Check if a room is available for the date range.
 * Overlap logic uses:
 *   check_in_date < requested_check_out AND check_out_date > requested_check_in
 * For status overlapping with: pending, confirmed
 *
 * @param PDO $db
 * @param int $roomId
 * @param string $checkIn  YYYY-MM-DD
 * @param string $checkOut YYYY-MM-DD
 * @return array{available: bool, available_beds: int}
 */
function checkRoomAvailability(PDO $db, int $roomId, string $checkIn, string $checkOut): array {
    $roomStmt = $db->prepare("SELECT available_beds FROM rooms WHERE id = ? LIMIT 1");
    $roomStmt->execute([$roomId]);
    $room = $roomStmt->fetch();

    if (!$room) {
        return ['available' => false, 'available_beds' => 0];
    }

    $overlapStmt = $db->prepare("
        SELECT COUNT(*) AS cnt
        FROM bookings
        WHERE room_id = ?
          AND status IN ('confirmed', 'pending')
          AND check_in_date < ?
          AND check_out_date > ?
    ");
    $overlapStmt->execute([$roomId, $checkOut, $checkIn]);
    $cnt = (int)$overlapStmt->fetchColumn();

    if ($cnt > 0) {
        return ['available' => false, 'available_beds' => 0];
    }

    return ['available' => true, 'available_beds' => (int)$room['available_beds']];
}

/**
 * Calculate total amount using spec:
 *   total = (days_diff / 30) * price_per_month
 *
 * @param float $pricePerMonth
 * @param string $checkIn YYYY-MM-DD
 * @param string $checkOut YYYY-MM-DD
 * @return float
 */
function calculateBookingAmount(float $pricePerMonth, string $checkIn, string $checkOut): float {
    $in = new DateTime($checkIn);
    $out = new DateTime($checkOut);
    $daysDiff = (int)$in->diff($out)->days;

    // Spec requires each month = 30 days.
    $total = ($daysDiff / 30) * $pricePerMonth;
    return round($total, 2);
}

/**
 * Generate unique booking reference: BK-YYYY-XXXXX
 * @param PDO $db
 * @return string
 */
function generateBookingReference(PDO $db): string {
    $year = date('Y');

    // Use count of this year's bookings + 1 for sequence.
    $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE booking_reference LIKE ?");
    $stmt->execute(["BK-$year-%"]);
    $count = (int)$stmt->fetchColumn();

    return sprintf('BK-%s-%05d', $year, $count + 1);
}

/**
 * Create booking:
 * - Insert into bookings with status='pending' and payment_status='unpaid'
 * - Update rooms.available_beds -= bedsBooked
 * - If available_beds becomes 0, rooms.status='full'
 *
 * Notes JSON stores beds_booked for later restore.
 *
 * @return array{success: bool, booking_reference?: string, booking_id?: int, message?: string}
 */
function createBooking(
    PDO $db,
    int $studentId,
    int $roomId,
    int $hostelId,
    string $checkIn,
    string $checkOut,
    int $bedsBooked,
    float $totalAmount,
    ?string $notes
): array {
    // Basic date integrity
    $in = new DateTime($checkIn);
    $out = new DateTime($checkOut);
    $today = new DateTime('today');

    if ($in < $today) {
        return ['success' => false, 'message' => 'Check-in date cannot be in the past'];
    }
    if ($out <= $in) {
        return ['success' => false, 'message' => 'Check-out date must be after check-in date'];
    }

    if ($bedsBooked < 1) {
        return ['success' => false, 'message' => 'Beds to book must be at least 1'];
    }

    // Room details + ensure hostel_id matches what client passed (defense-in-depth)
    $roomStmt = $db->prepare("
        SELECT hostel_id, room_number, price_per_month, available_beds, status
        FROM rooms WHERE id = ? LIMIT 1
    ");
    $roomStmt->execute([$roomId]);
    $room = $roomStmt->fetch();

    if (!$room) {
        return ['success' => false, 'message' => 'Room not found'];
    }

    if ((int)$room['hostel_id'] !== $hostelId) {
        return ['success' => false, 'message' => 'Invalid hostel for this room'];
    }

    if ($room['available_beds'] < $bedsBooked) {
        return ['success' => false, 'message' => 'Not enough beds available'];
    }

    try {
        $db->beginTransaction();

        // Double-booking prevention: overlapping bookings with status pending/confirmed
        $overlapStmt = $db->prepare("
            SELECT COUNT(*) AS cnt
            FROM bookings
            WHERE room_id = ?
              AND status IN ('confirmed', 'pending')
              AND check_in_date < ?
              AND check_out_date > ?
        ");
        $overlapStmt->execute([$roomId, $checkOut, $checkIn]);
        $cnt = (int)$overlapStmt->fetchColumn();

        if ($cnt > 0) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Room is already booked for these dates'];
        }

        // Generate booking reference
        $bookingReference = generateBookingReference($db);

        // Ensure unique (rare race condition): if exists, bump until free
        $checkRef = $db->prepare("SELECT 1 FROM bookings WHERE booking_reference = ? LIMIT 1");
        $tries = 0;
        while (true) {
            $checkRef->execute([$bookingReference]);
            if (!$checkRef->fetch()) {
                break;
            }
            $tries++;
            if ($tries > 5) {
                $db->rollBack();
                return ['success' => false, 'message' => 'Could not generate unique booking reference'];
            }
            // Bump last 5 digits by 1
            $bookingReference = $bookingReference . ''; // keep format safety
            $bookingReference = substr($bookingReference, 0, -5) . sprintf('%05d', ((int)substr($bookingReference, -5)) + 1);
        }

        $notesPayload = [
            'freeform' => $notes ?? '',
            'beds_booked' => $bedsBooked,
        ];
        $notesJson = json_encode($notesPayload, JSON_UNESCAPED_UNICODE);

        $ins = $db->prepare("
            INSERT INTO bookings
            (student_id, room_id, hostel_id, booking_reference, check_in_date, check_out_date,
             status, total_amount, payment_status, notes)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, 'unpaid', ?)
        ");
        $ins->execute([
            $studentId,
            $roomId,
            $hostelId,
            $bookingReference,
            $checkIn,
            $checkOut,
            $totalAmount,
            $notesJson
        ]);

        $bookingId = (int)$db->lastInsertId();

        // Update rooms available beds and status
        $upd = $db->prepare("
            UPDATE rooms
            SET available_beds = available_beds - ?,
                status = CASE
                    WHEN available_beds - ? <= 0 THEN 'full'
                    ELSE status
                END
            WHERE id = ?
        ");
        $upd->execute([$bedsBooked, $bedsBooked, $roomId]);

        $db->commit();
        return ['success' => true, 'booking_reference' => $bookingReference, 'booking_id' => $bookingId];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['success' => false, 'message' => 'Error creating booking: ' . $e->getMessage()];
    }
}

/**
 * Cancel a pending booking and restore beds to rooms.
 * Requires that booking.notes contains JSON with beds_booked.
 *
 * @return array{success: bool, message: string}
 */
function cancelBooking(PDO $db, int $bookingId, int $studentId): array {
    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            SELECT id, student_id, room_id, status, notes
            FROM bookings
            WHERE id = ? AND student_id = ? AND status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([$bookingId, $studentId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Booking not found or cannot be cancelled'];
        }

        $bedsBooked = 1;
        if (!empty($booking['notes'])) {
            $decoded = json_decode((string)$booking['notes'], true);
            if (is_array($decoded) && isset($decoded['beds_booked']) && (int)$decoded['beds_booked'] > 0) {
                $bedsBooked = (int)$decoded['beds_booked'];
            }
        }

        // Mark cancelled
        $updBooking = $db->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
        $updBooking->execute([$bookingId]);

        // Restore beds and room status back to 'available' if it was full
        $updRoom = $db->prepare("
            UPDATE rooms
            SET available_beds = LEAST(total_beds, available_beds + ?),
                status = CASE
                    WHEN available_beds + ? > 0 THEN 'available'
                    ELSE status
                END
            WHERE id = ?
        ");
        $updRoom->execute([$bedsBooked, $bedsBooked, (int)$booking['room_id']]);

        $db->commit();
        return ['success' => true, 'message' => 'Booking cancelled successfully'];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['success' => false, 'message' => 'Error cancelling booking: ' . $e->getMessage()];
    }
}

/**
 * Get booking details with hostel+room.
 * @return array<string, mixed>
 */
function getBookingDetails(PDO $db, int $bookingId, int $studentId): array {
    $stmt = $db->prepare("
        SELECT
            b.*,
            h.name AS hostel_name,
            h.address AS hostel_address,
            h.contact_phone,
            h.contact_email,
            r.room_number,
            r.room_type,
            r.capacity,
            r.price_per_month
        FROM bookings b
        JOIN hostels h ON b.hostel_id = h.id
        JOIN rooms r ON b.room_id = r.id
        WHERE b.id = ? AND b.student_id = ?
        LIMIT 1
    ");
    $stmt->execute([$bookingId, $studentId]);
    $row = $stmt->fetch();
    return $row ?: [];
}

/**
 * Get all bookings for the logged-in student.
 * @return array<int, array<string, mixed>>
 */
function getStudentBookings(PDO $db, int $studentId): array {
    $stmt = $db->prepare("
        SELECT
            b.id,
            b.booking_reference,
            b.check_in_date,
            b.check_out_date,
            b.total_amount,
            b.status,
            b.payment_status,
            b.booking_date,
            h.name AS hostel_name,
            r.room_number
        FROM bookings b
        JOIN hostels h ON b.hostel_id = h.id
        JOIN rooms r ON b.room_id = r.id
        WHERE b.student_id = ?
        ORDER BY b.booking_date DESC
    ");
    $stmt->execute([$studentId]);
    return $stmt->fetchAll();
}

/**
 * Build safe amenities string for display from JSON/CSV stored in amenities.
 */
function renderAmenities(mixed $amenities): string {
    if ($amenities === null || $amenities === '') return '';
    if (is_string($amenities)) {
        $trim = trim($amenities);
        // Try JSON
        if (($trim[0] ?? '') === '[' || ($trim[0] ?? '') === '{') {
            $decoded = json_decode($amenities, true);
            if (is_array($decoded)) {
                return implode(', ', array_map('strval', $decoded));
            }
        }
        // Fallback CSV
        return $amenities;
    }
    if (is_array($amenities)) {
        return implode(', ', array_map('strval', $amenities));
    }
    return (string)$amenities;
}
?>
