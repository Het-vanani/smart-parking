/**
 * assets/js/admin.js
 * Admin dashboard: 7-day revenue bar chart drawn with vanilla <canvas>.
 * Also handles admin slot form enhancements.
 */

var _lastChartArgs = null;

/**
 * Draw a bar chart on a <canvas> element.
 *
 * @param {string} canvasId  - ID of the <canvas> element
 * @param {string[]} labels  - X-axis labels (e.g. ["02 Oct", "03 Oct", ...])
 * @param {number[]} values  - Revenue values (₹)
 */
function drawRevenueChart(canvasId, labels, values) {
  _lastChartArgs = { canvasId: canvasId, labels: labels, values: values };

  var canvas  = document.getElementById(canvasId);
  if (!canvas) return;
  var ctx     = canvas.getContext('2d');

  var isLight = document.documentElement.getAttribute('data-theme') === 'light';

  // Responsive canvas width
  canvas.width  = canvas.parentElement.clientWidth - 40;

  var W    = canvas.width;
  var H    = canvas.height || 220;
  var pad  = { top: 30, right: 20, bottom: 50, left: 60 };
  var plotW = W - pad.left - pad.right;
  var plotH = H - pad.top  - pad.bottom;

  var maxVal = Math.max.apply(null, values.concat([1])); // at least 1 to avoid /0
  var barW   = plotW / labels.length * 0.55;
  var gap    = plotW / labels.length;

  // ── Background ──────────────────────────────────────────────
  ctx.fillStyle = isLight ? '#ffffff' : '#141c2b';
  ctx.fillRect(0, 0, W, H);

  // ── Grid lines ──────────────────────────────────────────────
  var gridLines = 5;
  ctx.strokeStyle = isLight ? '#e2e8f0' : '#233247';
  ctx.lineWidth   = 1;
  ctx.font        = '11px system-ui, sans-serif';
  ctx.fillStyle   = isLight ? '#64748b' : '#8ca0b8';
  ctx.textAlign   = 'right';

  for (var g = 0; g <= gridLines; g++) {
    var yVal = (maxVal / gridLines) * g;
    var y    = pad.top + plotH - (plotH * g / gridLines);
    ctx.beginPath();
    ctx.moveTo(pad.left, y);
    ctx.lineTo(pad.left + plotW, y);
    ctx.stroke();
    ctx.fillText('₹' + Math.round(yVal), pad.left - 6, y + 4);
  }

  // ── Bars ────────────────────────────────────────────────────
  for (var i = 0; i < labels.length; i++) {
    var x    = pad.left + i * gap + (gap - barW) / 2;
    var bH   = (values[i] / maxVal) * plotH;
    var y    = pad.top + plotH - bH;

    // Gradient fill: Vibrant Royal Blue
    var grad = ctx.createLinearGradient(x, y, x, pad.top + plotH);
    grad.addColorStop(0, '#3b82f6');
    grad.addColorStop(1, '#1d4ed8');

    ctx.fillStyle = grad;
    ctx.beginPath();
    // Rounded top corners (r = 4)
    var r = 4;
    if (bH > r) {
      ctx.moveTo(x + r, y);
      ctx.lineTo(x + barW - r, y);
      ctx.quadraticCurveTo(x + barW, y, x + barW, y + r);
      ctx.lineTo(x + barW, pad.top + plotH);
      ctx.lineTo(x, pad.top + plotH);
      ctx.lineTo(x, y + r);
      ctx.quadraticCurveTo(x, y, x + r, y);
    } else {
      ctx.rect(x, pad.top + plotH - 2, barW, 2);
    }
    ctx.fill();

    // Value label on top of bar
    if (values[i] > 0) {
      ctx.fillStyle   = isLight ? '#0f172a' : '#f8fafc';
      ctx.font        = 'bold 11px system-ui, sans-serif';
      ctx.textAlign   = 'center';
      ctx.fillText('₹' + values[i].toFixed(0), x + barW / 2, y - 6);
    }

    // X-axis label
    ctx.fillStyle   = isLight ? '#64748b' : '#8ca0b8';
    ctx.font        = '11px system-ui, sans-serif';
    ctx.textAlign   = 'center';
    ctx.fillText(labels[i], x + barW / 2, pad.top + plotH + 20);
  }

  // ── Axes ─────────────────────────────────────────────────────
  ctx.strokeStyle = isLight ? '#cbd5e1' : '#233247';
  ctx.lineWidth   = 1;
  ctx.beginPath();
  ctx.moveTo(pad.left, pad.top);
  ctx.lineTo(pad.left, pad.top + plotH);
  ctx.lineTo(pad.left + plotW, pad.top + plotH);
  ctx.stroke();

  // ── Y-axis title ─────────────────────────────────────────────
  ctx.save();
  ctx.translate(14, pad.top + plotH / 2);
  ctx.rotate(-Math.PI / 2);
  ctx.fillStyle   = isLight ? '#64748b' : '#8ca0b8';
  ctx.font        = '11px system-ui, sans-serif';
  ctx.textAlign   = 'center';
  ctx.fillText('Revenue (₹)', 0, 0);
  ctx.restore();
}

// Re-render chart on theme change
window.addEventListener('themechange', function() {
  if (_lastChartArgs) {
    drawRevenueChart(_lastChartArgs.canvasId, _lastChartArgs.labels, _lastChartArgs.values);
  }
});
