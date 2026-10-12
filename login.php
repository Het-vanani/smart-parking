<?php
/**
 * login.php — User Login
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: /smart-parking/');
    exit;
}

$errors = [];

$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email    = clean($_POST['email']    ?? '');
    $password = $_POST['password']       ?? '';

    if (empty($email) || empty($password)) {
        $errors[] = 'Email and password are required.';
    } else {
        // Fetch user by email
        $stmt = $conn->prepare(
            "SELECT id, full_name, password_hash, role FROM users WHERE email = ?"
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($id, $full_name, $hash, $role);
        $stmt->fetch();

        if ($stmt->num_rows === 0 || !password_verify($password, $hash)) {
            // Intentionally vague message to prevent user enumeration
            $errors[] = 'Invalid email or password.';
        } else {
            $stmt->close();

            // ── Successful login ───────────────────────────────────
            // Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);

            $_SESSION['user_id']   = $id;
            $_SESSION['full_name'] = $full_name;
            $_SESSION['role']      = $role;

            // Redirect to original destination or role-appropriate dashboard
            $redirect = $_GET['redirect'] ?? '';
            if ($redirect && strpos($redirect, '/smart-parking/') === 0) {
                header('Location: ' . $redirect);
            } elseif ($role === 'admin') {
                header('Location: /smart-parking/admin/');
            } else {
                header('Location: /smart-parking/book.php');
            }
            exit;
        }
        $stmt->close();
    }
}

$page_title = 'Login';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section">
  <div class="auth-card">
    <div class="auth-header">
      <span class="auth-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      </span>
      <h1>Welcome Back</h1>
      <p>Log in to manage your parking bookings</p>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-error" role="alert">
        <?php foreach ($errors as $e): ?>
          <p><?= h($e) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form id="loginForm" action="/smart-parking/login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" method="POST" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email"
               value="<?= h($email) ?>"
               placeholder="you@example.com"
               autocomplete="email" required autofocus>
        <span class="field-error" id="err_email"></span>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="input-eye">
          <input type="password" id="password" name="password"
                 placeholder="Your password"
                 autocomplete="current-password" required>
          <button type="button" class="eye-btn" data-target="password" aria-label="Toggle password visibility">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <span class="field-error" id="err_password"></span>
      </div>

      <button type="submit" class="btn btn-primary btn-full">Sign In</button>
    </form>

    <!-- Demo credentials hint for viva / demo -->
    <div class="demo-creds">
      <p><strong>Demo Logins:</strong></p>
      <p>Admin: <code>admin@parking.com</code> / <code>Admin@123</code></p>
      <p>User: <code>rahul@demo.com</code> / <code>User@123</code></p>
    </div>

    <p class="auth-switch">New here? <a href="/smart-parking/register.php">Create an account</a></p>
  </div>
</section>

<script src="/smart-parking/assets/js/validate.js"></script>
<script>
  document.querySelector('.eye-btn').addEventListener('click', function() {
    var target = document.getElementById(this.dataset.target);
    target.type = target.type === 'password' ? 'text' : 'password';
  });
  attachLoginValidation('loginForm');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
