<?php
/**
 * admin/bookings.php — All bookings management.
 * Features: search by user/slot/vehicle, filter by status and date,
 * pagination (10 per page), cancel action.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

/* ── Handle cancel POST ───────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    verify_csrf();
    $cancel_id = (int)($_POST['booking_id'] ?? 0);

    // Check it's not started yet
    $stmt = $conn->prepare("SELECT start_time, status FROM bookings WHERE id = ?");
    $stmt->bind_param('i', $cancel_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $msg_type = 'error';
    $msg      = 'Booking not found.';

    if ($res) {
        if ($res['status'] === 'cancelled') {
            $msg = 'Already cancelled.';
        } elseif (strtotime($res['start_time']) <= time()) {
            $msg = 'Cannot cancel a booking that has already started.';
        } else {
            $stmt = $conn->prepare(
                "UPDATE bookings SET status='cancelled', cancelled_at=NOW() WHERE id=?"
            );
            $stmt->bind_param('i', $cancel_id);
            $stmt->execute();
            $stmt->close();
            $msg_type = 'success';
            $msg = "Booking #{$cancel_id} cancelled.";
        }
    }
    redirect_with_message('/smart-parking/admin/bookings.php', $msg_type, $msg);
}

/* ── Filters & Pagination ─────────────────────────────────── */
$search      = clean($_GET['search']      ?? '');
$filter_status = clean($_GET['status']   ?? '');
$filter_date = clean($_GET['date']        ?? '');
$filter_slot = clean($_GET['slot']        ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$per_page    = 10;
$offset      = ($page - 1) * $per_page;

// Build WHERE clause
$where    = ['1=1'];
$params   = [];
$types    = '';

if ($search) {
    $like = '%' . $search . '%';
    $where[] = "(u.full_name LIKE ? OR b.vehicle_number LIKE ? OR ps.slot_code LIKE ?)";
    $params[] = &$like; $params[] = &$like; $params[] = &$like;
    $types   .= 'sss';
}
if ($filter_status && in_array($filter_status, ['confirmed','cancelled'])) {
    $where[] = "b.status = ?";
    $params[] = &$filter_status;
    $types   .= 's';
}
if ($filter_date) {
    $where[] = "DATE(b.created_at) = ?";
    $params[] = &$filter_date;
    $types   .= 's';
}
if ($filter_slot) {
    $where[] = "ps.slot_code = ?";
    $params[] = &$filter_slot;
    $types   .= 's';
}

$where_sql = implode(' AND ', $where);

// Count total
$count_sql = "SELECT COUNT(*) FROM bookings b
              JOIN users u ON u.id = b.user_id
              JOIN parking_slots ps ON ps.id = b.slot_id
              WHERE $where_sql";
$stmt = $conn->prepare($count_sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$stmt->bind_result($total_rows);
$stmt->fetch();
$stmt->close();
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// Fetch rows for this page
$data_sql = "SELECT b.id, u.full_name, u.email, ps.slot_code, ps.zone,
                    b.vehicle_number, b.vehicle_type, b.start_time, b.end_time,
                    b.total_amount, b.status, b.created_at
             FROM bookings b
             JOIN users u ON u.id = b.user_id
             JOIN parking_slots ps ON ps.id = b.slot_id
             WHERE $where_sql
             ORDER BY b.created_at DESC
             LIMIT $per_page OFFSET $offset";
$stmt = $conn->prepare($data_sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result();

// All slot codes for filter dropdown
$slot_codes = $conn->query("SELECT DISTINCT slot_code FROM parking_slots ORDER BY slot_code");

$page_title = 'All Bookings';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="admin-section container">
  <div class="page-heading">
    <h1>All Bookings</h1>
    <p>Search, filter, and manage every booking.</p>
  </div>

  <!-- ── Filters ──────────────────────────────────────────── -->
  <div class="card filter-bar">
    <form method="GET" action="/smart-parking/admin/bookings.php" class="filter-form">
      <div class="form-row">
        <div class="form-group">
          <label for="search">Search</label>
          <input type="text" id="search" name="search" placeholder="Name / vehicle / slot"
                 value="<?= h($search) ?>">
        </div>
        <div class="form-group">
          <label for="status_filter">Status</label>
          <select id="status_filter" name="status">
            <option value="">All</option>
            <option value="confirmed"  <?= $filter_status === 'confirmed'  ? 'selected' : '' ?>>Confirmed</option>
            <option value="cancelled"  <?= $filter_status === 'cancelled'  ? 'selected' : '' ?>>Cancelled</option>
          </select>
        </div>
        <div class="form-group">
          <label for="date_filter">Date</label>
          <input type="date" id="date_filter" name="date" value="<?= h($filter_date) ?>">
        </div>
        <div class="form-group">
          <label for="slot_filter">Slot</label>
          <select id="slot_filter" name="slot">
            <option value="">All Slots</option>
            <?php while ($sc = $slot_codes->fetch_assoc()): ?>
              <option value="<?= h($sc['slot_code']) ?>"
                <?= $filter_slot === $sc['slot_code'] ? 'selected' : '' ?>>
                <?= h($sc['slot_code']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Filter Results</button>
        <a href="/smart-parking/admin/bookings.php" class="btn btn-ghost">Reset</a>
      </div>
    </form>
  </div>

  <p class="result-count">Showing <?= min($total_rows, $per_page) ?> of <?= $total_rows ?> bookings</p>

  <!-- ── Bookings Table ───────────────────────────────────── -->
  <div class="card">
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>#ID</th><th>User</th><th>Slot</th><th>Vehicle</th>
            <th>Start</th><th>End</th><th>Amount</th><th>Status</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows->num_rows === 0): ?>
            <tr><td colspan="9" class="text-center">No bookings found.</td></tr>
          <?php else: ?>
            <?php while ($row = $rows->fetch_assoc()): ?>
              <?php $derived = booking_derived_status($row['start_time'], $row['end_time'], $row['status']); ?>
              <tr>
                <td><?= $row['id'] ?></td>
                <td>
                  <?= h($row['full_name']) ?><br>
                  <small><?= h($row['email']) ?></small>
                </td>
                <td><?= h($row['slot_code']) ?><br><small><?= h($row['zone']) ?></small></td>
                <td><?= h(strtoupper($row['vehicle_number'])) ?><br><small><?= h(ucfirst($row['vehicle_type'])) ?></small></td>
                <td><?= h(fmt_datetime($row['start_time'])) ?></td>
                <td><?= h(fmt_datetime($row['end_time'])) ?></td>
                <td>₹<?= number_format($row['total_amount'], 2) ?></td>
                <td>
                  <span class="badge badge-<?= $derived ?>"><?= ucfirst($derived) ?></span>
                </td>
                <td class="action-btns">
                  <a href="/smart-parking/receipt.php?id=<?= $row['id'] ?>"
                     class="btn btn-ghost btn-xs" target="_blank">🧾</a>
                  <?php if ($derived === 'upcoming'): ?>
                    <form method="POST" style="display:inline"
                          onsubmit="return confirm('Cancel booking #<?= $row['id'] ?>?')">
                      <input type="hidden" name="csrf_token"  value="<?= csrf_token() ?>">
                      <input type="hidden" name="action"      value="cancel">
                      <input type="hidden" name="booking_id"  value="<?= $row['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-xs">✕</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <?php
        $base_url = '/smart-parking/admin/bookings.php?' . http_build_query(array_filter([
            'search' => $search,
            'status' => $filter_status,
            'date'   => $filter_date,
            'slot'   => $filter_slot,
        ]));
        for ($p = 1; $p <= $total_pages; $p++):
        ?>
          <a href="<?= $base_url ?>&amp;page=<?= $p ?>"
             class="page-link <?= $p === $page ? 'page-active' : '' ?>">
            <?= $p ?>
          </a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
