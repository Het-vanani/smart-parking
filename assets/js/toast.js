/**
 * assets/js/toast.js
 * Lightweight toast notification system.
 * showToast(type, message, duration)
 *   type: 'success' | 'error' | 'info' | 'warning'
 */

function showToast(type, message, duration) {
  duration = duration || 4000;
  var container = document.getElementById('toast-container');
  if (!container) return;

  var toast = document.createElement('div');
  toast.className = 'toast toast-' + type;
  toast.setAttribute('role', 'alert');

  var icons = { success: '✅', error: '❌', info: 'ℹ️', warning: '⚠️' };
  var icon  = icons[type] || 'ℹ️';

  toast.innerHTML =
    '<span class="toast-icon">' + icon + '</span>' +
    '<span class="toast-msg">' + message + '</span>' +
    '<button class="toast-close" aria-label="Close">✕</button>';

  toast.querySelector('.toast-close').addEventListener('click', function () {
    removeToast(toast);
  });

  container.appendChild(toast);

  // Trigger animation
  requestAnimationFrame(function () {
    toast.classList.add('toast-show');
  });

  // Auto-remove
  var timer = setTimeout(function () { removeToast(toast); }, duration);

  // Pause timer on hover
  toast.addEventListener('mouseenter', function () { clearTimeout(timer); });
  toast.addEventListener('mouseleave', function () {
    timer = setTimeout(function () { removeToast(toast); }, 2000);
  });
}

function removeToast(toast) {
  toast.classList.remove('toast-show');
  toast.classList.add('toast-hide');
  setTimeout(function () {
    if (toast.parentNode) toast.parentNode.removeChild(toast);
  }, 350);
}
