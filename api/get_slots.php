<?php
/**
 * api/get_slots.php — Returns JSON list of parking slots with availability.
 *
 * GET params:
 *   vehicle_type  (car|bike|ev)  — filter by slot type
 *   start_time    (YYYY-MM-DDTHH:MM)  — booking start
 *   end_time      (YYYY-MM-DDTHH:MM)  — booking end
 *
 * Response: { slots: [...], available_count: int, total_count: int }
 * Each slot: { id, slot_code, zone, slot_type, hourly_rate, status, availability }
 *   availability: "available" | "booked" | "maintenance"
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Must be logged in to query slots
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$vehicle_type = $_GET['vehicle_type'] ?? '';
$start_time   = $_GET['start_time']   ?? '';
$end_time     = $_GET['end_time']     ?? '';

// Validate vehicle_type
$allowed_types = ['car', 'bike', 'ev'];
if (!in_array($vehicle_type, $allowed_types, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid vehicle type.']);
    exit;
}

// Validate time range (optional — if not provided, skip overlap check)
$check_availability = false;
if ($start_time && $end_time) {
    $start_ts = strtotime($start_time);
    $end_ts   = strtotime($end_time);
    if (!$start_ts || !$end_ts || $end_ts <= $start_ts) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid time range.']);
        exit;
    }
    // Convert HTML datetime-local format to MySQL DATETIME
    $start_mysql = date('Y-m-d H:i:s', $start_ts);
    $end_mysql   = date('Y-m-d H:i:s', $end_ts);
    $check_availability = true;
}

// ── Fetch all slots of the requested type ──────────────────────
$stmt = $conn->prepare(
    "SELECT id, slot_code, zone, slot_type, hourly_rate, status
     FROM parking_slots
     WHERE slot_type = ?
     ORDER BY zone, slot_code"
);
$stmt->bind_param('s', $vehicle_type);
$stmt->execute();
$result = $stmt->get_result();
$slots  = [];
while ($row = $result->fetch_assoc()) {
    $slots[] = $row;
}
$stmt->close();

// ── If time range given, check which slots have an overlapping booking ──
$booked_ids = [];

if ($check_availability && !empty($slots)) {
    /*
     * Overlap condition: a slot is "booked" for the requested time window if
     * ANY confirmed booking on that slot satisfies:
     *   new_start < existing_end  AND  new_end > existing_start
     *
     * Back-to-back bookings (new_start == existing_end) are allowed.
     */
    $stmt = $conn->prepare(
        "SELECT DISTINCT slot_id
         FROM bookings
         WHERE status = 'confirmed'
           AND start_time < ?
           AND end_time   > ?"
    );
    $stmt->bind_param('ss', $end_mysql, $start_mysql);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $booked_ids[] = (int)$r['slot_id'];
    }
    $stmt->close();
}

// ── Build response ─────────────────────────────────────────────
$available_count = 0;
$formatted_slots = [];

foreach ($slots as $slot) {
    $slot_id = (int)$slot['id'];

    if ($slot['status'] === 'maintenance') {
        $avail = 'maintenance';
    } elseif ($check_availability && in_array($slot_id, $booked_ids)) {
        $avail = 'booked';
    } else {
        $avail = 'available';
        if ($slot['status'] === 'active') $available_count++;
    }

    $formatted_slots[] = [
        'id'          => $slot_id,
        'slot_code'   => $slot['slot_code'],
        'zone'        => $slot['zone'],
        'slot_type'   => $slot['slot_type'],
        'hourly_rate' => (float)$slot['hourly_rate'],
        'status'      => $slot['status'],
        'availability'=> $avail,
    ];
}

echo json_encode([
    'success'         => true,
    'slots'           => $formatted_slots,
    'available_count' => $available_count,
    'total_count'     => count($formatted_slots),
]);
