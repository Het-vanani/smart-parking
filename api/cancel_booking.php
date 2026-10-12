<?php
/**
 * api/cancel_booking.php — Cancel a booking.
 *
 * POST: { booking_id, csrf_token (via header) }
 *
 * Rules:
 *   - User must own the booking (admin can cancel any).
 *   - Booking must be 'confirmed'.
 *   - Booking must NOT have started yet (start_time > NOW()).
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

verify_csrf();

// Read input
$raw  = file_get_contents('php://input');
$data = $raw ? (json_decode($raw, true) ?? []) : $_POST;

$booking_id = (int)($data['booking_id'] ?? 0);
if ($booking_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID.']);
    exit;
}

$user_id = current_user_id();

// Fetch the booking
$stmt = $conn->prepare(
    "SELECT id, user_id, status, start_time FROM bookings WHERE id = ?"
);
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$res     = $stmt->get_result();
$booking = $res->fetch_assoc();
$stmt->close();

if (!$booking) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}

// Ownership check: user must own it, OR caller must be admin
if (!is_admin() && (int)$booking['user_id'] !== $user_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

if ($booking['status'] !== 'confirmed') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'This booking is already cancelled.']);
    exit;
}

// Cannot cancel a booking that has already started
if (strtotime($booking['start_time']) <= time()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cannot cancel a booking that has already started.']);
    exit;
}

// ── Update status to cancelled ─────────────────────────────────
$stmt = $conn->prepare(
    "UPDATE bookings
     SET status = 'cancelled', cancelled_at = NOW()
     WHERE id = ?"
);
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$stmt->close();

echo json_encode([
    'success' => true,
    'message' => 'Booking #' . $booking_id . ' has been cancelled.',
]);
