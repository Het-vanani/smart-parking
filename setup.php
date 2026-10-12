<?php
/**
 * setup.php — One-time setup script.
 *
 * ⚠️  DELETE THIS FILE after running it once!
 *
 * What it does:
 *   1. Creates 1 admin + 2 demo users with bcrypt-hashed passwords.
 *   2. Inserts sample bookings relative to NOW() so the demo grid
 *      always shows a realistic mix of free and booked slots.
 *
 * Open once in browser: http://localhost/smart-parking/setup.php
 */

require_once __DIR__ . '/includes/db.php';

// ── Safety: run only once ──────────────────────────────────────
$check = $conn->query("SELECT COUNT(*) AS cnt FROM users");
$row   = $check->fetch_assoc();
if ((int)$row['cnt'] > 0) {
    die('<h2 style="font-family:sans-serif;color:#c00">Setup already done. Delete setup.php for security.</h2>');
}

$errors  = [];
$created = [];

// ── 1. Create users ──────────────────────────────────────────
$users_to_create = [
    [
        'full_name' => 'Admin User',
        'email'     => 'admin@parking.com',
        'phone'     => '9000000001',
        'password'  => 'Admin@123',
        'role'      => 'admin',
    ],
    [
        'full_name' => 'Rahul Sharma',
        'email'     => 'rahul@demo.com',
        'phone'     => '9876543210',
        'password'  => 'User@123',
        'role'      => 'user',
    ],
    [
        'full_name' => 'Priya Patel',
        'email'     => 'priya@demo.com',
        'phone'     => '9876543211',
        'password'  => 'User@123',
        'role'      => 'user',
    ],
];

$user_ids = [];

$stmt = $conn->prepare(
    "INSERT INTO users (full_name, email, phone, password_hash, role)
     VALUES (?, ?, ?, ?, ?)"
);

foreach ($users_to_create as $u) {
    $hash = password_hash($u['password'], PASSWORD_BCRYPT);
    $stmt->bind_param('sssss', $u['full_name'], $u['email'], $u['phone'], $hash, $u['role']);
    if ($stmt->execute()) {
        $user_ids[$u['email']] = $conn->insert_id;
        $created[] = "User created: {$u['full_name']} &lt;{$u['email']}&gt; (role: {$u['role']})";
    } else {
        $errors[] = "Failed to create user {$u['email']}: " . $stmt->error;
    }
}
$stmt->close();

// ── 2. Insert sample bookings ──────────────────────────────────
// We need user IDs for Rahul and Priya; skip if user creation failed
$rahul_id = $user_ids['rahul@demo.com'] ?? null;
$priya_id = $user_ids['priya@demo.com'] ?? null;

if ($rahul_id && $priya_id) {

    // Get slot IDs by code
    $slot_map = [];
    $res = $conn->query("SELECT id, slot_code FROM parking_slots");
    while ($r = $res->fetch_assoc()) {
        $slot_map[$r['slot_code']] = (int)$r['id'];
    }

    /*
     * Sample bookings use DATE_ADD / DATE_SUB relative to NOW()
     * so the demo always shows realistic data:
     *   - Some bookings currently active  (started before now, ends after now)
     *   - Some upcoming (starts in future)
     *   - Some completed (ended in past)
     */
    $bookings = [
        // Active now: A-01 booked by Rahul (started 1hr ago, ends 2hrs from now)
        [
            'user_id'        => $rahul_id,
            'slot_code'      => 'A-01',
            'vehicle_number' => 'KA01AB1234',
            'vehicle_type'   => 'car',
            'start_expr'     => 'DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            'end_expr'       => 'DATE_ADD(NOW(), INTERVAL 2 HOUR)',
            'hours'          => 3,
            'rate'           => 40.00,
        ],
        // Active now: B-01 booked by Priya
        [
            'user_id'        => $priya_id,
            'slot_code'      => 'B-01',
            'vehicle_number' => 'MH02CD5678',
            'vehicle_type'   => 'bike',
            'start_expr'     => 'DATE_SUB(NOW(), INTERVAL 30 MINUTE)',
            'end_expr'       => 'DATE_ADD(NOW(), INTERVAL 90 MINUTE)',
            'hours'          => 2,
            'rate'           => 20.00,
        ],
        // Upcoming: A-02 booked by Priya (1hr from now)
        [
            'user_id'        => $priya_id,
            'slot_code'      => 'A-02',
            'vehicle_number' => 'DL03EF9012',
            'vehicle_type'   => 'car',
            'start_expr'     => 'DATE_ADD(NOW(), INTERVAL 1 HOUR)',
            'end_expr'       => 'DATE_ADD(NOW(), INTERVAL 3 HOUR)',
            'hours'          => 2,
            'rate'           => 40.00,
        ],
        // Upcoming: C-01 booked by Rahul (2hrs from now)
        [
            'user_id'        => $rahul_id,
            'slot_code'      => 'C-01',
            'vehicle_number' => 'TN04GH3456',
            'vehicle_type'   => 'ev',
            'start_expr'     => 'DATE_ADD(NOW(), INTERVAL 2 HOUR)',
            'end_expr'       => 'DATE_ADD(NOW(), INTERVAL 5 HOUR)',
            'hours'          => 3,
            'rate'           => 60.00,
        ],
        // Upcoming: A-03 booked by Rahul (3hrs from now)
        [
            'user_id'        => $rahul_id,
            'slot_code'      => 'A-03',
            'vehicle_number' => 'KA01AB1234',
            'vehicle_type'   => 'car',
            'start_expr'     => 'DATE_ADD(NOW(), INTERVAL 3 HOUR)',
            'end_expr'       => 'DATE_ADD(NOW(), INTERVAL 6 HOUR)',
            'hours'          => 3,
            'rate'           => 40.00,
        ],
        // Completed: B-02 was booked 4hrs ago, ended 2hrs ago
        [
            'user_id'        => $rahul_id,
            'slot_code'      => 'B-02',
            'vehicle_number' => 'KA05IJ7890',
            'vehicle_type'   => 'bike',
            'start_expr'     => 'DATE_SUB(NOW(), INTERVAL 4 HOUR)',
            'end_expr'       => 'DATE_SUB(NOW(), INTERVAL 2 HOUR)',
            'hours'          => 2,
            'rate'           => 20.00,
        ],
        // Cancelled: C-02 was booked by Priya but cancelled
        [
            'user_id'        => $priya_id,
            'slot_code'      => 'C-02',
            'vehicle_number' => 'GJ06KL2345',
            'vehicle_type'   => 'ev',
            'start_expr'     => 'DATE_ADD(NOW(), INTERVAL 4 HOUR)',
            'end_expr'       => 'DATE_ADD(NOW(), INTERVAL 7 HOUR)',
            'hours'          => 3,
            'rate'           => 60.00,
            'status'         => 'cancelled',
            'cancelled_at'   => true,
        ],
    ];

    foreach ($bookings as $b) {
        $slot_id = $slot_map[$b['slot_code']] ?? null;
        if (!$slot_id) {
            $errors[] = "Slot {$b['slot_code']} not found.";
            continue;
        }
        $amount  = ceil($b['hours']) * $b['rate'];
        $status  = $b['status'] ?? 'confirmed';
        $can_at  = isset($b['cancelled_at']) ? 'NOW()' : 'NULL';

        // We use a direct query here (only in setup.php) to use expressions like NOW()
        $sql = "INSERT INTO bookings
                    (user_id, slot_id, vehicle_number, vehicle_type,
                     start_time, end_time, total_amount, status, cancelled_at)
                VALUES
                    ({$b['user_id']}, $slot_id, '{$b['vehicle_number']}', '{$b['vehicle_type']}',
                     {$b['start_expr']}, {$b['end_expr']}, $amount, '$status', $can_at)";

        if ($conn->query($sql)) {
            $created[] = "Booking created: {$b['slot_code']} for user #{$b['user_id']} ({$status})";
        } else {
            $errors[] = "Booking insert failed for {$b['slot_code']}: " . $conn->error;
        }
    }
}

// ── Output ────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>SmartPark Setup</title>
  <style>
    body { font-family: sans-serif; max-width: 700px; margin: 40px auto; padding: 20px; background: #0a1628; color: #e0e6f0; }
    h1 { color: #4ade80; }
    .ok  { color: #4ade80; }
    .err { color: #f87171; }
    pre  { background: #12233a; padding: 12px; border-radius: 6px; font-size: 13px; }
    .warn { background: #7c3b00; color: #fde68a; padding: 12px; border-radius: 6px; margin-top: 20px; }
  </style>
</head>
<body>
  <h1>SmartPark — One-Time Setup</h1>

  <?php if ($errors): ?>
    <h2 class="err">Errors occurred:</h2>
    <pre><?= implode("\n", $errors) ?></pre>
  <?php endif; ?>

  <h2 class="ok">Database Seeded Successfully:</h2>
  <pre><?= implode("\n", $created) ?></pre>

  <div class="warn">
    <strong>IMPORTANT:</strong> Delete <code>setup.php</code> from your project folder now!
    <br>It has done its job. Leaving it accessible is a security risk.
  </div>

  <p style="margin-top:20px;">
    <strong>Demo logins:</strong><br>
    Admin: admin@parking.com / Admin@123<br>
    User 1: rahul@demo.com / User@123<br>
    User 2: priya@demo.com / User@123
  </p>

  <p><a href="/smart-parking/" style="color:#60a5fa;">Go to homepage →</a></p>
</body>
</html>
