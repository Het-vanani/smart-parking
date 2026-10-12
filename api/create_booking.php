<?php
/**
 * api/create_booking.php — Create a new parking booking.
 *
 * POST (JSON body or form-encoded):
 *   slot_id, vehicle_number, vehicle_type, start_time, end_time
 *   csrf_token (via X-CSRF-Token header)
 *
 * Returns JSON: { success, message, booking_id? }
 *
 * Key logic:
 *   1. Validate all inputs server-side.
 *   2. Open a transaction.
 *   3. SELECT ... FOR UPDATE on the slot row (lock it for this transaction).
 *   4. Re-check overlap with confirmed bookings (race-condition safe).
 *   5. Calculate price server-side (never trust client).
 *   6. Insert booking, commit.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// ── Auth check ─────────────────────────────────────────────────
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to book a slot.']);
    exit;
}

// ── CSRF check ─────────────────────────────────────────────────
verify_csrf();

// ── Read input ─────────────────────────────────────────────────
// Accept either JSON body or form-encoded POST
$input = [];
$raw   = file_get_contents('php://input');
if ($raw) {
    $input = json_decode($raw, true) ?? [];
} else {
    $input = $_POST;
}

$slot_id        = (int)($input['slot_id']        ?? 0);
$vehicle_number = strtoupper(trim($input['vehicle_number'] ?? ''));
$vehicle_type   = trim($input['vehicle_type']   ?? '');
$start_time_raw = trim($input['start_time']     ?? '');
$end_time_raw   = trim($input['end_time']       ?? '');

// ── Input validation ───────────────────────────────────────────
$errors = [];

if ($slot_id <= 0) {
    $errors[] = 'No slot selected.';
}

$allowed_types = ['car', 'bike', 'ev'];
if (!in_array($vehicle_type, $allowed_types, true)) {
    $errors[] = 'Invalid vehicle type.';
}

if (!valid_vehicle_number($vehicle_number)) {
    $errors[] = 'Invalid vehicle number. Use format like KA01AB1234.';
}

// Parse datetimes
$start_ts = $start_time_raw ? strtotime($start_time_raw) : false;
$end_ts   = $end_time_raw   ? strtotime($end_time_raw)   : false;

if (!$start_ts) {
    $errors[] = 'Invalid start time.';
}
if (!$end_ts) {
    $errors[] = 'Invalid end time.';
}

if ($start_ts && $end_ts) {
    if ($start_ts < time() - 60) { // 60s grace for clock drift
        $errors[] = 'Start time cannot be in the past.';
    }
    if ($end_ts <= $start_ts) {
        $errors[] = 'End time must be after start time.';
    }
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

$start_mysql = date('Y-m-d H:i:s', $start_ts);
$end_mysql   = date('Y-m-d H:i:s', $end_ts);

// ── Begin transaction ──────────────────────────────────────────
$conn->begin_transaction();

try {
    // ── Step 1: Lock the slot row to prevent race conditions ──────
    // SELECT ... FOR UPDATE prevents another request from booking
    // the same slot at the same time.
    $stmt = $conn->prepare(
        "SELECT id, slot_type, hourly_rate, status
         FROM parking_slots
         WHERE id = ?
         FOR UPDATE"
    );
    $stmt->bind_param('i', $slot_id);
    $stmt->execute();
    $res  = $stmt->get_result();
    $slot = $res->fetch_assoc();
    $stmt->close();

    if (!$slot) {
        throw new RuntimeException('Slot not found.', 404);
    }
    if ($slot['status'] === 'maintenance') {
        throw new RuntimeException('This slot is under maintenance.', 400);
    }
    if ($slot['slot_type'] !== $vehicle_type) {
        throw new RuntimeException('Slot type does not match your vehicle type.', 400);
    }

    // ── Step 2: Overlap check (inside the transaction, after lock) ─
    /*
     * Two bookings overlap if:
     *   new_start < existing_end  AND  new_end > existing_start
     *
     * This means back-to-back is allowed (new_start == existing_end is NOT < existing_end).
     */
    $stmt = $conn->prepare(
        "SELECT id FROM bookings
         WHERE slot_id   = ?
           AND status    = 'confirmed'
           AND start_time < ?
           AND end_time   > ?
         LIMIT 1"
    );
    $stmt->bind_param('iss', $slot_id, $end_mysql, $start_mysql);
    $stmt->execute();
    $stmt->store_result();
    $has_overlap = $stmt->num_rows > 0;
    $stmt->close();

    if ($has_overlap) {
        throw new RuntimeException('Sorry, this slot was just booked by someone else. Please choose another slot.', 409);
    }

    // ── Step 3: Calculate price server-side ────────────────────────
    $price = calculate_price($start_mysql, $end_mysql, (float)$slot['hourly_rate']);
    if ($price['error']) {
        throw new RuntimeException($price['error'], 400);
    }

    // ── Step 4: Insert booking ──────────────────────────────────────
    $user_id = current_user_id();
    $amount  = $price['amount'];
    $status  = 'confirmed';

    $stmt = $conn->prepare(
        "INSERT INTO bookings
            (user_id, slot_id, vehicle_number, vehicle_type,
             start_time, end_time, total_amount, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'iissssds',
        $user_id, $slot_id, $vehicle_number, $vehicle_type,
        $start_mysql, $end_mysql, $amount, $status
    );
    $stmt->execute();
    $booking_id = $conn->insert_id;
    $stmt->close();

    // ── Commit ─────────────────────────────────────────────────────
    $conn->commit();

    echo json_encode([
        'success'    => true,
        'message'    => 'Booking confirmed! Redirecting to your receipt…',
        'booking_id' => $booking_id,
    ]);

} catch (RuntimeException $e) {
    $conn->rollback();
    $code = $e->getCode() ?: 400;
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}
