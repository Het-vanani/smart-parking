<?php
/**
 * receipt.php — Print-friendly booking receipt.
 * Accessible via ?id=<booking_id>.
 * User can only see their own receipt; admin can see any.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$booking_id = (int)($_GET['id'] ?? 0);
if ($booking_id <= 0) {
    redirect_with_message('/smart-parking/my_bookings.php', 'error', 'Invalid receipt ID.');
}

$user_id = current_user_id();

// Fetch booking + slot + user info (include b.user_id for ownership check)
$stmt = $conn->prepare(
    "SELECT b.id, b.user_id, b.vehicle_number, b.vehicle_type,
            b.start_time, b.end_time, b.total_amount,
            b.status, b.created_at, b.cancelled_at,
            ps.slot_code, ps.zone, ps.slot_type, ps.hourly_rate,
            u.full_name, u.email, u.phone
     FROM bookings b
     JOIN parking_slots ps ON ps.id = b.slot_id
     JOIN users u           ON u.id  = b.user_id
     WHERE b.id = ?"
);
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$res  = $stmt->get_result();
$bk   = $res->fetch_assoc();
$stmt->close();

if (!$bk) {
    redirect_with_message('/smart-parking/my_bookings.php', 'error', 'Booking not found.');
}

// Security: non-admin can only view their own receipt
if (!is_admin() && (int)$bk['user_id'] !== $user_id) {
    redirect_with_message('/smart-parking/my_bookings.php', 'error', 'Access denied.');
}

$duration = fmt_duration($bk['start_time'], $bk['end_time']);
$derived  = booking_derived_status($bk['start_time'], $bk['end_time'], $bk['status']);

// Calculate hours for display
$mins  = (strtotime($bk['end_time']) - strtotime($bk['start_time'])) / 60;
$hours = (int)ceil($mins / 60);

$page_title = 'Receipt #' . $booking_id;
require_once __DIR__ . '/includes/header.php';
?>

<section class="receipt-section container">
  <div class="receipt-actions no-print">
    <a href="/smart-parking/my_bookings.php" class="btn btn-ghost">&larr; Back to Bookings</a>
    <button onclick="window.print()" class="btn btn-primary">Print Receipt</button>
  </div>

  <!-- ── Receipt Card ─────────────────────────────────────────── -->
  <div class="receipt-card">
    <!-- Header -->
    <div class="receipt-header">
      <div class="receipt-logo">
        <span class="receipt-logo-icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <rect width="18" height="18" x="3" y="3" rx="4"/>
            <path d="M9 17V7h4a3 3 0 0 1 0 6H9"/>
          </svg>
        </span>
        <div>
          <strong>SmartPark</strong>
          <small>Smart Parking Slot Booking System</small>
        </div>
      </div>
      <div class="receipt-id-block">
        <p class="receipt-label">RECEIPT</p>
        <p class="receipt-bid">#<?= $booking_id ?></p>
        <span class="receipt-status badge-<?= $derived ?>">
          <?= ucfirst($derived) ?>
        </span>
      </div>
    </div>

    <hr class="receipt-divider">

    <!-- User Info -->
    <div class="receipt-section-title">Customer Details</div>
    <table class="receipt-table">
      <tr><th>Name</th><td><?= h($bk['full_name']) ?></td></tr>
      <tr><th>Email</th><td><?= h($bk['email']) ?></td></tr>
      <tr><th>Phone</th><td><?= h($bk['phone']) ?></td></tr>
    </table>

    <hr class="receipt-divider">

    <!-- Booking Info -->
    <div class="receipt-section-title">Booking Details</div>
    <table class="receipt-table">
      <tr><th>Slot</th><td><?= h($bk['slot_code']) ?> — <?= h($bk['zone']) ?></td></tr>
      <tr><th>Slot Type</th><td><?= h(ucfirst($bk['slot_type'])) ?></td></tr>
      <tr><th>Vehicle Number</th><td><?= h(strtoupper($bk['vehicle_number'])) ?></td></tr>
      <tr><th>Vehicle Type</th><td><?= h(ucfirst($bk['vehicle_type'])) ?></td></tr>
      <tr><th>Start Time</th><td><?= h(fmt_datetime($bk['start_time'])) ?></td></tr>
      <tr><th>End Time</th><td><?= h(fmt_datetime($bk['end_time'])) ?></td></tr>
      <tr><th>Duration</th><td><?= h($duration) ?> (billed <?= $hours ?> hr<?= $hours !== 1 ? 's' : '' ?>)</td></tr>
    </table>

    <hr class="receipt-divider">

    <!-- Payment -->
    <div class="receipt-section-title">Payment</div>
    <table class="receipt-table">
      <tr><th>Hourly Rate</th><td>₹<?= number_format($bk['hourly_rate'], 2) ?>/hr</td></tr>
      <tr><th>Hours Billed</th><td><?= $hours ?></td></tr>
      <tr class="receipt-total-row">
        <th>Total Paid</th>
        <td class="receipt-total">₹<?= number_format($bk['total_amount'], 2) ?></td>
      </tr>
    </table>

    <p class="receipt-payment-note">✅ Payment completed (simulated). No real charges were made.</p>

    <hr class="receipt-divider">

    <!-- Footer -->
    <div class="receipt-footer">
      <p>Booked on: <?= h(fmt_datetime($bk['created_at'])) ?></p>
      <?php if ($bk['status'] === 'cancelled'): ?>
        <p>Cancelled on: <?= h(fmt_datetime($bk['cancelled_at'])) ?></p>
      <?php endif; ?>
      <p class="receipt-thankyou">Thank you for using SmartPark! 🙏</p>
    </div>
  </div><!-- /.receipt-card -->
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
