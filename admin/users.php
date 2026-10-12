<?php
/**
 * admin/users.php — Users list (view only).
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$users = $conn->query(
    "SELECT u.id, u.full_name, u.email, u.phone, u.role, u.created_at,
            COUNT(b.id) AS total_bookings,
            COALESCE(SUM(CASE WHEN b.status='confirmed' THEN b.total_amount ELSE 0 END), 0) AS total_spent
     FROM users u
     LEFT JOIN bookings b ON b.user_id = u.id
     GROUP BY u.id
     ORDER BY u.created_at DESC"
);

$page_title = 'Users';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="admin-section container">
  <div class="page-heading">
    <h1>Registered Users</h1>
    <p>View all registered users and their booking summary.</p>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>#ID</th><th>Name</th><th>Email</th><th>Phone</th>
            <th>Role</th><th>Bookings</th><th>Total Spent</th><th>Joined</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $users->fetch_assoc()): ?>
            <tr>
              <td><?= $row['id'] ?></td>
              <td><?= h($row['full_name']) ?></td>
              <td><?= h($row['email']) ?></td>
              <td><?= h($row['phone']) ?></td>
              <td>
                <span class="badge badge-<?= $row['role'] ?>">
                  <?= ucfirst($row['role']) ?>
                </span>
              </td>
              <td><?= $row['total_bookings'] ?></td>
              <td>₹<?= number_format($row['total_spent'], 2) ?></td>
              <td><?= h(fmt_datetime($row['created_at'])) ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
