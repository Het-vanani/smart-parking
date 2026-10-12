<?php
/**
 * logout.php — Destroy session and redirect to login.
 */
require_once __DIR__ . '/includes/auth.php';

// Clear session data and destroy the session
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

header('Location: /smart-parking/login.php');
exit;
