<?php
/**
 * includes/db.php
 * Opens a mysqli connection to smart_parking_db.
 * Sets PHP + MySQL session timezone to Asia/Kolkata (+05:30).
 */

date_default_timezone_set('Asia/Kolkata');

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'smart_parking_db';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]));
}

// Set character set
$conn->set_charset('utf8mb4');

// Sync MySQL session timezone with PHP timezone
$conn->query("SET time_zone = '+05:30'");
