<?php
/**
 * includes/header.php
 * Shared HTML header + navigation bar included at the top of every page.
 * Requires $page_title to be set before including this file.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Smart Parking Slot Booking System - Book your parking slot online quickly and easily.">
  <title><?= h($page_title ?? 'Smart Parking') ?> | SmartPark</title>
  <link rel="stylesheet" href="/smart-parking/assets/css/style.css">
  <script>
    (function() {
      var t = localStorage.getItem('smartpark_theme') || 'dark';
      document.documentElement.setAttribute('data-theme', t);
    })();
  </script>
</head>
<body>

<!-- ── Toast container (populated by toast.js) ── -->
<div id="toast-container" aria-live="polite"></div>

<?php if ($flash): ?>
<!-- Flash message passed from redirect — picked up by toast.js on DOMContentLoaded -->
<script>
  window.__flash = { type: <?= json_encode($flash['type']) ?>, msg: <?= json_encode($flash['msg']) ?> };
</script>
<?php endif; ?>

<!-- ── Navigation ── -->
<header class="site-header">
  <div class="container header-inner">
    <a href="/smart-parking/" class="logo" aria-label="SmartPark Home">
      <span class="logo-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <rect width="18" height="18" x="3" y="3" rx="4"/>
          <path d="M9 17V7h4a3 3 0 0 1 0 6H9"/>
        </svg>
      </span>
      <span class="logo-text">Smart<strong>Park</strong></span>
    </a>

    <div class="header-actions">
      <button class="theme-toggle" id="themeToggle" type="button" aria-label="Toggle theme" title="Switch Theme">
        <span class="theme-icon-sun" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="5"/>
            <line x1="12" y1="1" x2="12" y2="3"/>
            <line x1="12" y1="21" x2="12" y2="23"/>
            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
            <line x1="1" y1="12" x2="3" y2="12"/>
            <line x1="21" y1="12" x2="23" y2="12"/>
            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
          </svg>
        </span>
        <span class="theme-icon-moon" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
          </svg>
        </span>
      </button>

      <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>

    <nav class="site-nav" id="siteNav">
      <ul>
        <li><a href="/smart-parking/" <?= basename($_SERVER['PHP_SELF']) === 'index.php' && strpos($_SERVER['REQUEST_URI'], '/smart-parking/admin') !== 0 ? 'class="active"' : '' ?>>Home</a></li>

        <?php if (is_logged_in()): ?>
          <li><a href="/smart-parking/book.php" <?= basename($_SERVER['PHP_SELF']) === 'book.php' ? 'class="active"' : '' ?>>Book Slot</a></li>
          <li><a href="/smart-parking/my_bookings.php" <?= basename($_SERVER['PHP_SELF']) === 'my_bookings.php' ? 'class="active"' : '' ?>>My Bookings</a></li>
          <?php if (is_admin()): ?>
            <li><a href="/smart-parking/admin/" <?= strpos($_SERVER['REQUEST_URI'], '/smart-parking/admin') === 0 ? 'class="active"' : '' ?>>Admin</a></li>
          <?php endif; ?>
          <li class="nav-user">Hi, <?= current_user_name() ?></li>
          <li><a href="/smart-parking/logout.php" class="btn-nav-logout">Logout</a></li>
        <?php else: ?>
          <li><a href="/smart-parking/login.php" <?= basename($_SERVER['PHP_SELF']) === 'login.php' ? 'class="active"' : '' ?>>Login</a></li>
          <li><a href="/smart-parking/register.php" class="btn-nav-register">Register</a></li>
        <?php endif; ?>
      </ul>
    </nav>
  </div>
</header>

<main class="main-content">
