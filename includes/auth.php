<?php
/**
 * includes/auth.php
 * Session helpers, login/admin guards, CSRF utilities.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if current user is logged in.
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Check if current user is an admin.
 */
function is_admin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Redirect to login if not logged in.
 */
function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /smart-parking/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/**
 * Redirect to home if not admin.
 */
function require_admin(): void {
    if (!is_logged_in() || !is_admin()) {
        header('Location: /smart-parking/login.php');
        exit;
    }
}

/**
 * Generate a CSRF token and store in session (once per session).
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate the CSRF token from a POST request.
 * Accepts token via POST field or X-CSRF-Token header (for fetch() calls).
 */
function verify_csrf(): void {
    $token = $_POST['csrf_token']
        ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Invalid CSRF token.']));
    }
}

/**
 * Return current logged-in user's ID.
 */
function current_user_id(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

/**
 * Return current logged-in user's name.
 */
function current_user_name(): string {
    return h($_SESSION['full_name'] ?? '');
}

/**
 * HTML-escape a string (short alias).
 */
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
