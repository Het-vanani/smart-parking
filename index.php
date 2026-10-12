<?php
/**
 * index.php — Landing Page
 * Shows live availability summary and a CTA to book.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Live stats (no auth required for public view)
$total_active = (int)$conn->query(
    "SELECT COUNT(*) FROM parking_slots WHERE status = 'active'"
)->fetch_row()[0];

$occupied_now = (int)$conn->query(
    "SELECT COUNT(DISTINCT slot_id) FROM bookings
     WHERE status = 'confirmed' AND start_time <= NOW() AND end_time > NOW()"
)->fetch_row()[0];

$available_now = $total_active - $occupied_now;

// Per-type availability
$type_stats = [];
foreach (['car','bike','ev'] as $type) {
    $total = (int)$conn->query(
        "SELECT COUNT(*) FROM parking_slots WHERE status='active' AND slot_type='$type'"
    )->fetch_row()[0];
    $busy = (int)$conn->query(
        "SELECT COUNT(DISTINCT b.slot_id) FROM bookings b
         JOIN parking_slots ps ON ps.id = b.slot_id
         WHERE b.status='confirmed' AND b.start_time<=NOW() AND b.end_time>NOW()
           AND ps.slot_type='$type'"
    )->fetch_row()[0];
    $type_stats[$type] = ['total' => $total, 'available' => $total - $busy];
}

$page_title = 'Home';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Hero Section ──────────────────────────────────────────── -->
<section class="hero">
  <div class="container hero-inner">
    <div class="hero-text">
      <h1 class="hero-title">
        Smart Parking,<br>
        <span class="hero-accent">Simplified.</span>
      </h1>
      <p class="hero-sub">
        Find, book, and manage your parking slot in seconds.
        Real-time availability, instant confirmation, no hassle.
      </p>
      <div class="hero-cta">
        <?php if (is_logged_in()): ?>
          <a href="/smart-parking/book.php" class="btn btn-primary btn-lg">Book a Slot Now</a>
          <a href="/smart-parking/my_bookings.php" class="btn btn-ghost btn-lg">My Bookings</a>
        <?php else: ?>
          <a href="/smart-parking/register.php" class="btn btn-primary btn-lg">Get Started</a>
          <a href="/smart-parking/login.php" class="btn btn-ghost btn-lg">Login</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Live availability badge -->
    <div class="hero-badge">
      <div class="live-dot"></div>
      <span>Live Availability</span>
      <p class="live-count"><?= $available_now ?> <small>of <?= $total_active ?></small></p>
      <p class="live-sub">slots available right now</p>
    </div>
  </div>
</section>

<!-- ── Live Stats Strip ──────────────────────────────────────── -->
<section class="stats-strip">
  <div class="container stats-inner">
    <div class="stat-pill">
      <span>Cars</span>
      <strong><?= $type_stats['car']['available'] ?> / <?= $type_stats['car']['total'] ?> free</strong>
    </div>
    <div class="stat-pill">
      <span>Bikes</span>
      <strong><?= $type_stats['bike']['available'] ?> / <?= $type_stats['bike']['total'] ?> free</strong>
    </div>
    <div class="stat-pill">
      <span>EV Bays</span>
      <strong><?= $type_stats['ev']['available'] ?> / <?= $type_stats['ev']['total'] ?> free</strong>
    </div>
    <div class="stat-pill stat-pill-cta">
      <a href="/smart-parking/book.php">Book Now</a>
    </div>
  </div>
</section>

<!-- ── Features Section ──────────────────────────────────────── -->
<section class="features-section container">
  <h2 class="section-title">Key Features</h2>
  <div class="features-grid">
    <div class="feature-card">
      <span class="feature-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </span>
      <h3>Real-Time Availability</h3>
      <p>See which slots are free for your exact time window. Grid auto-refreshes every 10 seconds.</p>
    </div>
    <div class="feature-card">
      <span class="feature-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
      </span>
      <h3>Instant Booking</h3>
      <p>Select a slot, review the price, and confirm in one click. Concurrency handled with row locking.</p>
    </div>
    <div class="feature-card">
      <span class="feature-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      </span>
      <h3>Transparent Pricing</h3>
      <p>Pay per hour, billed in whole hours. Review the exact calculated total before confirming.</p>
    </div>
    <div class="feature-card">
      <span class="feature-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      </span>
      <h3>Digital Receipts</h3>
      <p>Every booking generates a printable digital pass with slot details and verify token.</p>
    </div>
    <div class="feature-card">
      <span class="feature-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/></svg>
      </span>
      <h3>Multi-Vehicle Support</h3>
      <p>Dedicated parking zones for Cars, Bikes, and EVs with customized rates per zone.</p>
    </div>
    <div class="feature-card">
      <span class="feature-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 3v18"/><path d="m14 9 3 3-3 3"/></svg>
      </span>
      <h3>Admin Control</h3>
      <p>Admins can manage slot status, view all bookings, analyze revenue, and cancel reservations.</p>
    </div>
  </div>
</section>

<!-- ── Pricing Section ───────────────────────────────────────── -->
<section class="pricing-section">
  <div class="container">
    <h2 class="section-title">Hourly Rates</h2>
    <div class="pricing-grid">
      <div class="pricing-card">
        <span class="pricing-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/></svg>
        </span>
        <h3>Four Wheeler (Car)</h3>
        <p class="pricing-rate">₹40<small>/hour</small></p>
        <p>Zone A · Standard car bays</p>
      </div>
      <div class="pricing-card pricing-featured">
        <span class="pricing-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
        </span>
        <h3>Electric Vehicle (EV)</h3>
        <p class="pricing-rate">₹60<small>/hour</small></p>
        <p>Zone C · Charging dock bays</p>
      </div>
      <div class="pricing-card">
        <span class="pricing-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18.5" cy="17.5" r="3.5"/><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="15" cy="5" r="1"/><path d="M12 17.5V14l-3-3 4-3 2 3h2"/></svg>
        </span>
        <h3>Two Wheeler (Bike)</h3>
        <p class="pricing-rate">₹20<small>/hour</small></p>
        <p>Zone B · Compact two-wheeler bays</p>
      </div>
    </div>
    <p class="pricing-note">Billed in whole hours · Minimum 1 hour duration</p>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
