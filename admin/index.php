<?php
/**
 * admin/index.php — Admin Dashboard
 * Shows stats: total slots, occupied now, today's bookings, revenue.
 * 7-day revenue bar chart drawn with <canvas> + vanilla JS.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

/* ── Stats ─────────────────────────────────────────────────── */

// Total active slots
$r = $conn->query("SELECT COUNT(*) FROM parking_slots WHERE status = 'active'");
$total_slots = (int)$r->fetch_row()[0];

// Slots occupied RIGHT NOW (active confirmed bookings)
$r = $conn->query(
    "SELECT COUNT(DISTINCT slot_id) FROM bookings
     WHERE status = 'confirmed' AND start_time <= NOW() AND end_time > NOW()"
);
$occupied_now = (int)$r->fetch_row()[0];

// Today's bookings count
$r = $conn->query(
    "SELECT COUNT(*) FROM bookings
     WHERE status = 'confirmed' AND DATE(created_at) = CURDATE()"
);
$today_bookings = (int)$r->fetch_row()[0];

// Today's revenue
$r = $conn->query(
    "SELECT COALESCE(SUM(total_amount),0) FROM bookings
     WHERE status = 'confirmed' AND DATE(created_at) = CURDATE()"
);
$today_revenue = (float)$r->fetch_row()[0];

// Total revenue (all time, confirmed)
$r = $conn->query(
    "SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE status = 'confirmed'"
);
$total_revenue = (float)$r->fetch_row()[0];

// Total users (non-admin)
$r = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
$total_users = (int)$r->fetch_row()[0];

/* ── 7-day revenue data for bar chart ──────────────────────── */
$chart_labels  = [];
$chart_revenue = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('d M', strtotime($date));
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(total_amount),0) FROM bookings
         WHERE status = 'confirmed' AND DATE(created_at) = ?"
    );
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $stmt->bind_result($rev);
    $stmt->fetch();
    $stmt->close();

    $chart_labels[]  = $label;
    $chart_revenue[] = (float)$rev;
}

/* ── Recent 5 bookings ──────────────────────────────────────── */
$recent = $conn->query(
    "SELECT b.id, u.full_name, ps.slot_code, b.vehicle_number,
            b.start_time, b.end_time, b.total_amount, b.status
     FROM bookings b
     JOIN users u ON u.id = b.user_id
     JOIN parking_slots ps ON ps.id = b.slot_id
     ORDER BY b.created_at DESC LIMIT 5"
);

$page_title = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="admin-section container">
  <div class="page-heading">
    <h1>⚙️ Admin Dashboard</h1>
    <p>Overview of SmartPark operations.</p>
  </div>

  <!-- ── Stat Cards ─────────────────────────────────────────── -->
  <div class="stat-grid">
    <div class="stat-card">
      <span class="stat-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="4"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/></svg>
      </span>
      <div class="stat-body">
        <p class="stat-label">Active Slots</p>
        <p class="stat-value"><?= $total_slots ?></p>
      </div>
    </div>
    <div class="stat-card stat-accent">
      <span class="stat-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/></svg>
      </span>
      <div class="stat-body">
        <p class="stat-label">Occupied Now</p>
        <p class="stat-value"><?= $occupied_now ?></p>
      </div>
    </div>
    <div class="stat-card">
      <span class="stat-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      </span>
      <div class="stat-body">
        <p class="stat-label">Today's Bookings</p>
        <p class="stat-value"><?= $today_bookings ?></p>
      </div>
    </div>
    <div class="stat-card">
      <span class="stat-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      </span>
      <div class="stat-body">
        <p class="stat-label">Today's Revenue</p>
        <p class="stat-value">₹<?= number_format($today_revenue, 2) ?></p>
      </div>
    </div>
    <div class="stat-card stat-accent">
      <span class="stat-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
      </span>
      <div class="stat-body">
        <p class="stat-label">Total Revenue</p>
        <p class="stat-value">₹<?= number_format($total_revenue, 2) ?></p>
      </div>
    </div>
    <div class="stat-card">
      <span class="stat-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </span>
      <div class="stat-body">
        <p class="stat-label">Registered Users</p>
        <p class="stat-value"><?= $total_users ?></p>
      </div>
    </div>
  </div>

  <!-- ── 7-Day Revenue Chart ────────────────────────────────── -->
  <div class="card chart-card">
    <h2>7-Day Revenue (₹)</h2>
    <canvas id="revenueChart" height="220" role="img" aria-label="7-day revenue bar chart"></canvas>
  </div>

  <!-- ── Recent Bookings ───────────────────────────────────── -->
  <div class="card">
    <div class="card-header-row">
      <h2>Recent Bookings</h2>
      <a href="/smart-parking/admin/bookings.php" class="btn btn-ghost btn-sm">View All &rarr;</a>
    </div>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>#ID</th><th>User</th><th>Slot</th><th>Vehicle</th>
            <th>Start</th><th>Amount</th><th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $recent->fetch_assoc()): ?>
            <tr>
              <td><?= $row['id'] ?></td>
              <td><?= h($row['full_name']) ?></td>
              <td><?= h($row['slot_code']) ?></td>
              <td><?= h(strtoupper($row['vehicle_number'])) ?></td>
              <td><?= h(fmt_datetime($row['start_time'])) ?></td>
              <td>₹<?= number_format($row['total_amount'], 2) ?></td>
              <td><span class="badge badge-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Admin Quick Links -->
  <div class="admin-links">
    <a href="/smart-parking/admin/slots.php" class="btn btn-primary">Manage Slots</a>
    <a href="/smart-parking/admin/bookings.php" class="btn btn-primary">All Bookings</a>
    <a href="/smart-parking/admin/users.php" class="btn btn-primary">Users</a>
  </div>
</section>

<script src="/smart-parking/assets/js/admin.js"></script>
<script>
  // Pass PHP data to admin.js
  window.chartData = {
    labels:  <?= json_encode($chart_labels) ?>,
    revenue: <?= json_encode($chart_revenue) ?>,
  };
  drawRevenueChart('revenueChart', window.chartData.labels, window.chartData.revenue);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
