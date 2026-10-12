<?php
/**
 * admin/slots.php — Manage Parking Slots (Add / Edit / Delete).
 * Protects against deleting slots that have upcoming confirmed bookings.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$errors  = [];
$success = '';
$edit_slot = null;

/* ── Handle POST actions ──────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $slot_code  = strtoupper(clean($_POST['slot_code']  ?? ''));
        $zone       = clean($_POST['zone']        ?? '');
        $slot_type  = clean($_POST['slot_type']   ?? '');
        $hourly_rate= (float)($_POST['hourly_rate'] ?? 0);
        $status     = clean($_POST['status']      ?? 'active');
        $edit_id    = (int)($_POST['edit_id']     ?? 0);

        // Validate
        if (!$slot_code)                          $errors[] = 'Slot code is required.';
        if (!$zone)                               $errors[] = 'Zone is required.';
        if (!in_array($slot_type, ['car','bike','ev'])) $errors[] = 'Invalid slot type.';
        if ($hourly_rate <= 0)                    $errors[] = 'Hourly rate must be > 0.';
        if (!in_array($status, ['active','maintenance'])) $errors[] = 'Invalid status.';

        if (empty($errors)) {
            if ($action === 'add') {
                $stmt = $conn->prepare(
                    "INSERT INTO parking_slots (slot_code, zone, slot_type, hourly_rate, status)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->bind_param('sssds', $slot_code, $zone, $slot_type, $hourly_rate, $status);
                if ($stmt->execute()) {
                    $success = "Slot {$slot_code} added successfully.";
                } else {
                    $errors[] = 'Slot code already exists.';
                }
                $stmt->close();
            } else {
                $stmt = $conn->prepare(
                    "UPDATE parking_slots SET slot_code=?, zone=?, slot_type=?, hourly_rate=?, status=?
                     WHERE id=?"
                );
                $stmt->bind_param('sssdsi', $slot_code, $zone, $slot_type, $hourly_rate, $status, $edit_id);
                $stmt->execute();
                $stmt->close();
                $success = "Slot {$slot_code} updated.";
            }
        }

    } elseif ($action === 'delete') {
        $del_id = (int)($_POST['delete_id'] ?? 0);

        // Check for upcoming confirmed bookings
        $stmt = $conn->prepare(
            "SELECT COUNT(*) FROM bookings
             WHERE slot_id = ? AND status = 'confirmed' AND start_time > NOW()"
        );
        $stmt->bind_param('i', $del_id);
        $stmt->execute();
        $stmt->bind_result($upcoming_count);
        $stmt->fetch();
        $stmt->close();

        if ($upcoming_count > 0) {
            $errors[] = "Cannot delete slot — it has {$upcoming_count} upcoming booking(s). Cancel them first.";
        } else {
            $stmt = $conn->prepare("DELETE FROM parking_slots WHERE id = ?");
            $stmt->bind_param('i', $del_id);
            $stmt->execute();
            $stmt->close();
            $success = 'Slot deleted.';
        }
    }
}

// Load slot for editing
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM parking_slots WHERE id = ?");
    $stmt->bind_param('i', $edit_id);
    $stmt->execute();
    $edit_slot = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch all slots
$slots = $conn->query(
    "SELECT *, (SELECT COUNT(*) FROM bookings WHERE slot_id = parking_slots.id AND status='confirmed' AND start_time > NOW()) AS upcoming_bookings
     FROM parking_slots ORDER BY zone, slot_code"
);

$page_title = 'Manage Slots';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="admin-section container">
  <div class="page-heading">
    <h1>Manage Parking Slots</h1>
    <p>Add, edit, or remove parking slots.</p>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert alert-error">
      <?php foreach ($errors as $e): ?><p><?= h($e) ?></p><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- ── Add / Edit Form ──────────────────────────────────── -->
  <div class="card form-card">
    <h2><?= $edit_slot ? 'Edit Slot #' . $edit_slot['id'] : 'Add New Slot' ?></h2>
    <form method="POST" action="/smart-parking/admin/slots.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action"     value="<?= $edit_slot ? 'edit' : 'add' ?>">
      <?php if ($edit_slot): ?>
        <input type="hidden" name="edit_id" value="<?= $edit_slot['id'] ?>">
      <?php endif; ?>

      <div class="form-row">
        <div class="form-group">
          <label for="slot_code">Slot Code</label>
          <input type="text" id="slot_code" name="slot_code" required
                 placeholder="A-01" style="text-transform:uppercase"
                 value="<?= h($edit_slot['slot_code'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="zone">Zone</label>
          <input type="text" id="zone" name="zone" required placeholder="Zone A"
                 value="<?= h($edit_slot['zone'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="slot_type">Type</label>
          <select id="slot_type" name="slot_type">
            <?php foreach (['car'=>'Car (4-Wheeler)','bike'=>'Bike (2-Wheeler)','ev'=>'Electric Vehicle (EV)'] as $v => $l): ?>
              <option value="<?= $v ?>" <?= ($edit_slot['slot_type'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="hourly_rate">Rate (₹/hr)</label>
          <input type="number" id="hourly_rate" name="hourly_rate" min="1" step="0.01" required
                 value="<?= h($edit_slot['hourly_rate'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="active"      <?= ($edit_slot['status'] ?? '') === 'active'      ? 'selected' : '' ?>>Active</option>
            <option value="maintenance" <?= ($edit_slot['status'] ?? '') === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
          </select>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">
          <?= $edit_slot ? '💾 Update Slot' : '➕ Add Slot' ?>
        </button>
        <?php if ($edit_slot): ?>
          <a href="/smart-parking/admin/slots.php" class="btn btn-ghost">Cancel Edit</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- ── Slots Table ──────────────────────────────────────── -->
  <div class="card">
    <h2>All Slots</h2>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Code</th><th>Zone</th><th>Type</th><th>Rate</th>
            <th>Status</th><th>Upcoming Bkgs</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $slots->fetch_assoc()): ?>
            <tr>
              <td><strong><?= h($row['slot_code']) ?></strong></td>
              <td><?= h($row['zone']) ?></td>
              <td><?= h(ucfirst($row['slot_type'])) ?></td>
              <td>₹<?= number_format($row['hourly_rate'], 2) ?>/hr</td>
              <td><span class="badge badge-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
              <td><?= $row['upcoming_bookings'] ?></td>
              <td class="action-btns">
                <a href="/smart-parking/admin/slots.php?edit=<?= $row['id'] ?>"
                   class="btn btn-ghost btn-xs">✏️ Edit</a>
                <?php if ($row['upcoming_bookings'] == 0): ?>
                  <form method="POST" style="display:inline"
                        onsubmit="return confirm('Delete slot <?= h($row['slot_code']) ?>?')">
                    <input type="hidden" name="csrf_token"  value="<?= csrf_token() ?>">
                    <input type="hidden" name="action"      value="delete">
                    <input type="hidden" name="delete_id"   value="<?= $row['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-xs">🗑️ Delete</button>
                  </form>
                <?php else: ?>
                  <span class="btn btn-danger btn-xs" style="opacity:.4;cursor:not-allowed"
                        title="Has upcoming bookings">🗑️</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
