<?php
/**
 * includes/functions.php
 * Shared helper functions used across multiple pages.
 */

/**
 * Calculate booking price.
 *
 * Rule: bill in whole hours, rounded UP. Minimum 1 hour, max 24 hours.
 *   total = ceil(duration_in_minutes / 60) × hourly_rate
 *
 * @param string $start_time  MySQL DATETIME or PHP-parsable string
 * @param string $end_time    MySQL DATETIME or PHP-parsable string
 * @param float  $hourly_rate Slot's hourly rate in ₹
 * @return array ['hours' => int, 'amount' => float, 'error' => string|null]
 */
function calculate_price(string $start_time, string $end_time, float $hourly_rate): array {
    $start = strtotime($start_time);
    $end   = strtotime($end_time);

    if ($end <= $start) {
        return ['hours' => 0, 'amount' => 0, 'error' => 'End time must be after start time.'];
    }

    $minutes = (int)(($end - $start) / 60); // integer minutes
    $hours   = (int)ceil($minutes / 60);    // round up to whole hours

    // Enforce minimum 1 hour and maximum 24 hours
    if ($hours < 1) {
        return ['hours' => 0, 'amount' => 0, 'error' => 'Minimum booking duration is 1 hour.'];
    }
    if ($hours > 24) {
        return ['hours' => 0, 'amount' => 0, 'error' => 'Maximum booking duration is 24 hours.'];
    }

    $amount = round($hours * $hourly_rate, 2);
    return ['hours' => $hours, 'amount' => $amount, 'error' => null];
}

/**
 * Format a DATETIME string to a human-readable form (IST).
 * e.g. "08 Oct 2026, 05:30 PM"
 */
function fmt_datetime(string $dt): string {
    if (!$dt) return '-';
    return date('d M Y, h:i A', strtotime($dt));
}

/**
 * Return duration string like "2 hrs 30 min".
 */
function fmt_duration(string $start, string $end): string {
    $secs = strtotime($end) - strtotime($start);
    $h    = (int)($secs / 3600);
    $m    = (int)(($secs % 3600) / 60);
    return $h . ' hr' . ($h !== 1 ? 's' : '') . ($m ? ' ' . $m . ' min' : '');
}

/**
 * Derive booking status label based on current time.
 * DB stores only 'confirmed' or 'cancelled'.
 * Upcoming: confirmed + starts in future
 * Active  : confirmed + already started, not ended
 * Completed: confirmed + ended
 */
function booking_derived_status(string $start_time, string $end_time, string $db_status): string {
    if ($db_status === 'cancelled') return 'cancelled';
    $now = time();
    $s   = strtotime($start_time);
    $e   = strtotime($end_time);
    if ($now < $s) return 'upcoming';
    if ($now >= $s && $now < $e) return 'active';
    return 'completed';
}

/**
 * Validate an Indian vehicle registration number.
 * Pattern: 2-letter state code, 2-digit district, 1-3 letter series, 4 digits
 * e.g. KA01AB1234
 */
function valid_vehicle_number(string $vn): bool {
    return (bool)preg_match('/^[A-Z]{2}[0-9]{2}[A-Z]{1,3}[0-9]{4}$/', strtoupper(trim($vn)));
}

/**
 * Validate a 10-digit Indian phone number.
 */
function valid_phone(string $p): bool {
    return (bool)preg_match('/^[6-9][0-9]{9}$/', $p);
}

/**
 * Validate email format.
 */
function valid_email(string $e): bool {
    return filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Sanitize and trim a string (not for HTML output — use h() for that).
 */
function clean(string $s): string {
    return trim(strip_tags($s));
}

/**
 * Redirect with a one-time flash message stored in session.
 */
function redirect_with_message(string $url, string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
    header('Location: ' . $url);
    exit;
}

/**
 * Retrieve and clear any flash message from the session.
 */
function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
