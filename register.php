<?php
/**
 * register.php — User Registration
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Already logged in → redirect
if (is_logged_in()) {
    header('Location: /smart-parking/');
    exit;
}

$errors = [];
$old    = []; // to re-fill form on error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $full_name = clean($_POST['full_name'] ?? '');
    $email     = clean($_POST['email']     ?? '');
    $phone     = clean($_POST['phone']     ?? '');
    $password  = $_POST['password']        ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    $old = compact('full_name', 'email', 'phone');

    // ── Server-side validation ─────────────────────────────────
    if (strlen($full_name) < 2) {
        $errors[] = 'Full name must be at least 2 characters.';
    }
    if (!valid_email($email)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!valid_phone($phone)) {
        $errors[] = 'Enter a valid 10-digit mobile number (starting with 6–9).';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // ── Check email uniqueness ─────────────────────────────────
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = 'This email is already registered. Please login.';
        }
        $stmt->close();
    }

    // ── Insert user ────────────────────────────────────────────
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $role = 'user';
        $stmt = $conn->prepare(
            "INSERT INTO users (full_name, email, phone, password_hash, role)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('sssss', $full_name, $email, $phone, $hash, $role);
        if ($stmt->execute()) {
            $stmt->close();
            redirect_with_message('/smart-parking/login.php', 'success', 'Account created! Please log in.');
        } else {
            $errors[] = 'Registration failed. Please try again.';
            $stmt->close();
        }
    }
}

$page_title = 'Register';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section">
  <div class="auth-card">
    <div class="auth-header">
      <span class="auth-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </span>
      <h1>Create Account</h1>
      <p>Join SmartPark and book your slot in seconds</p>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-error" role="alert">
        <?php foreach ($errors as $e): ?>
          <p><?= h($e) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form id="registerForm" action="/smart-parking/register.php" method="POST" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <div class="form-group">
        <label for="full_name">Full Name</label>
        <input type="text" id="full_name" name="full_name"
               value="<?= h($old['full_name'] ?? '') ?>"
               placeholder="Rahul Sharma"
               autocomplete="name" required>
        <span class="field-error" id="err_full_name"></span>
      </div>

      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email"
               value="<?= h($old['email'] ?? '') ?>"
               placeholder="rahul@example.com"
               autocomplete="email" required>
        <span class="field-error" id="err_email"></span>
      </div>

      <div class="form-group">
        <label for="phone">Mobile Number</label>
        <input type="tel" id="phone" name="phone"
               value="<?= h($old['phone'] ?? '') ?>"
               placeholder="9876543210"
               maxlength="10" autocomplete="tel" required>
        <span class="field-error" id="err_phone"></span>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="input-eye">
          <input type="password" id="password" name="password"
                 placeholder="Min. 8 characters"
                 autocomplete="new-password" required>
          <button type="button" class="eye-btn" data-target="password" aria-label="Toggle password visibility">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <span class="field-error" id="err_password"></span>
      </div>

      <div class="form-group">
        <label for="confirm_password">Confirm Password</label>
        <div class="input-eye">
          <input type="password" id="confirm_password" name="confirm_password"
                 placeholder="Re-enter password"
                 autocomplete="new-password" required>
          <button type="button" class="eye-btn" data-target="confirm_password" aria-label="Toggle confirm password visibility">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <span class="field-error" id="err_confirm_password"></span>
      </div>

      <button type="submit" class="btn btn-primary btn-full">Create Account →</button>
    </form>

    <p class="auth-switch">Already have an account? <a href="/smart-parking/login.php">Log in</a></p>
  </div>
</section>

<script src="/smart-parking/assets/js/validate.js"></script>
<script>
  // Eye toggle for password fields
  document.querySelectorAll('.eye-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var target = document.getElementById(this.dataset.target);
      target.type = target.type === 'password' ? 'text' : 'password';
    });
  });

  // Attach client-side validation to register form
  attachRegisterValidation('registerForm');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
