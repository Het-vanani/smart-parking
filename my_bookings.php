<?php
/**
 * my_bookings.php — User's booking history with tabs.
 * Tabs: upcoming | active | completed | cancelled
 * Cancel button shown only for upcoming bookings (not yet started).
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$user_id = current_user_id();

// Fetch all bookings for this user, with slot info
$stmt = $conn->prepare(
    "SELECT b.id, b.vehicle_number, b.vehicle_type,
            b.start_time, b.end_time, b.total_amount,
            b.status, b.created_at, b.cancelled_at,
            ps.slot_code, ps.zone, ps.slot_type, ps.hourly_rate
     FROM bookings b
     JOIN parking_slots ps ON ps.id = b.slot_id
     WHERE b.user_id = ?
     ORDER BY b.created_at DESC"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result   = $stmt->get_result();
$bookings = [];
while ($row = $result->fetch_assoc()) {
    $row['derived_status'] = booking_derived_status(
        $row['start_time'], $row['end_time'], $row['status']
    );
    $bookings[] = $row;
}
$stmt->close();

// Group into tabs
$tabs = ['upcoming' => [], 'active' => [], 'completed' => [], 'cancelled' => []];
foreach ($bookings as $b) {
    $ds = $b['derived_status'];
    if (isset($tabs[$ds])) $tabs[$ds][] = $b;
}

$active_tab = $_GET['tab'] ?? 'upcoming';
if (!array_key_exists($active_tab, $tabs)) $active_tab = 'upcoming';

$page_title = 'My Bookings';
require_once __DIR__ . '/includes/header.php';
?>

<section class="my-bookings-section container">
  <div class="page-heading">
    <h1>📋 My Bookings</h1>
    <p>Track your upcoming, active, and past parking reservations.</p>
  </div>

  <!-- ── Tabs ──────────────────────────────────────────────────── -->
  <nav class="tab-nav" role="tablist">
    <?php foreach ($tabs as $tab => $items): ?>
      <a href="/smart-parking/my_bookings.php?tab=<?= $tab ?>"
         class="tab-link <?= $tab === $active_tab ? 'tab-active' : '' ?>"
         role="tab"
         aria-selected="<?= $tab === $active_tab ? 'true' : 'false' ?>">
        <?= ucfirst($tab) ?>
        <span class="tab-count"><?= count($items) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <!-- ── Bookings List ─────────────────────────────────────────── -->
  <div class="bookings-list" role="tabpanel">
    <?php $current = $tabs[$active_tab]; ?>
    <?php if (empty($current)): ?>
      <div class="empty-state">
        <span class="empty-icon">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="4"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/></svg>
        </span>
        <p>No <?= $active_tab ?> bookings found.</p>
        <?php if ($active_tab === 'upcoming' || $active_tab === 'active'): ?>
          <a href="/smart-parking/book.php" class="btn btn-primary">Book a Slot</a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <?php foreach ($current as $b): ?>
        <div class="booking-card card">
          <div class="booking-card-header">
            <span class="booking-id">#<?= $b['id'] ?></span>
            <span class="booking-badge badge-<?= $b['derived_status'] ?>">
              <?= ucfirst($b['derived_status']) ?>
            </span>
          </div>

          <div class="booking-card-body">
            <div class="booking-detail">
              <span>Slot Code</span>
              <strong><?= h($b['slot_code']) ?> — <?= h($b['zone']) ?></strong>
            </div>
            <div class="booking-detail">
              <span>Vehicle</span>
              <strong><?= h(strtoupper($b['vehicle_number'])) ?> (<?= h(ucfirst($b['vehicle_type'])) ?>)</strong>
            </div>
            <div class="booking-detail">
              <span>Start</span>
              <strong><?= fmt_datetime($b['start_time']) ?></strong>
            </div>
            <div class="booking-detail">
              <span>End</span>
              <strong><?= fmt_datetime($b['end_time']) ?></strong>
            </div>
            <div class="booking-detail">
              <span>Duration</span>
              <strong><?= fmt_duration($b['start_time'], $b['end_time']) ?></strong>
            </div>
            <div class="booking-detail">
              <span>Amount</span>
              <strong>₹<?= number_format($b['total_amount'], 2) ?></strong>
            </div>
          </div>

          <div class="booking-card-footer">
            <a href="/smart-parking/receipt.php?id=<?= $b['id'] ?>" class="btn btn-ghost btn-sm">
              View Receipt
            </a>

            <?php if ($b['derived_status'] === 'upcoming'): ?>
              <!-- Cancel button only for bookings that haven't started -->
              <button class="btn btn-danger btn-sm cancel-btn"
                      data-booking-id="<?= $b['id'] ?>"
                      type="button">
                Cancel
              </button>
            <?php endif; ?>

            <?php if ($b['derived_status'] === 'cancelled'): ?>
              <span class="cancelled-on">Cancelled on <?= fmt_datetime($b['cancelled_at']) ?></span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<!-- Hidden CSRF token for cancel fetch() calls -->
<input type="hidden" id="csrfToken" value="<?= csrf_token() ?>">

<script src="/smart-parking/assets/js/toast.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var csrfToken = document.getElementById('csrfToken').value;

  document.querySelectorAll('.cancel-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var bookingId = this.dataset.bookingId;
      if (!confirm('Cancel booking #' + bookingId + '? This cannot be undone.')) return;

      var self = this;
      self.disabled = true;
      self.textContent = 'Cancelling…';

      fetch('/smart-parking/api/cancel_booking.php', {
        method:  'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
        },
        body: JSON.stringify({ booking_id: parseInt(bookingId) }),
      })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.success) {
          showToast('success', data.message);
          // Remove the booking card from DOM and reload after delay
          setTimeout(function () { window.location.reload(); }, 1500);
        } else {
          showToast('error', data.message || 'Cancellation failed.');
          self.disabled = false;
          self.textContent = '✕ Cancel';
        }
      })
      .catch(function () {
        showToast('error', 'Network error. Please try again.');
        self.disabled = false;
        self.textContent = '✕ Cancel';
      });
    });
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
