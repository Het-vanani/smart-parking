<?php
/**
 * includes/footer.php
 * Shared HTML footer included at the bottom of every page.
 */
?>
</main><!-- /.main-content -->

<footer class="site-footer">
  <div class="container footer-inner">
    <p><strong>SmartPark</strong> &mdash; Smart Parking Slot Booking System</p>
    <p class="footer-sub">College Web Development Project &copy; <?= date('Y') ?></p>
  </div>
</footer>

<script src="/smart-parking/assets/js/toast.js"></script>
<script>
  // Show flash message via toast on page load
  if (window.__flash) {
    document.addEventListener('DOMContentLoaded', function () {
      showToast(window.__flash.type, window.__flash.msg);
    });
  }

  // Mobile nav toggle
  document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('navToggle');
    var nav    = document.getElementById('siteNav');
    if (toggle && nav) {
      toggle.addEventListener('click', function () {
        var open = nav.classList.toggle('open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }
  });
</script>
<script src="/smart-parking/assets/js/theme.js"></script>
</body>
</html>
