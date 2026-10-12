/**
 * assets/js/theme.js
 * Theme switcher: toggles between dark and light themes with localStorage persistence.
 */
(function () {
  function getTheme() {
    return localStorage.getItem('smartpark_theme') || 'dark';
  }

  function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('smartpark_theme', theme);

    var btn = document.getElementById('themeToggle');
    if (btn) {
      var next = theme === 'dark' ? 'light' : 'dark';
      btn.setAttribute('title', 'Switch to ' + next + ' theme');
      btn.setAttribute('aria-label', 'Switch to ' + next + ' theme');
    }

    // Broadcast event for listeners (like the admin revenue chart)
    window.dispatchEvent(new CustomEvent('themechange', { detail: { theme: theme } }));
  }

  document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('themeToggle');
    if (!btn) return;

    var current = getTheme();
    setTheme(current);

    btn.addEventListener('click', function () {
      var active = document.documentElement.getAttribute('data-theme') || 'dark';
      var target = active === 'dark' ? 'light' : 'dark';
      setTheme(target);
    });
  });
})();
