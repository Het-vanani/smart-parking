<?php
/**
 * book.php — Parking Slot Booking Page
 * Users pick vehicle type + time range, see the slot grid, select, and confirm.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$page_title = 'Book a Slot';
require_once __DIR__ . '/includes/header.php';
?>

<section class="book-section container">
  <div class="page-heading">
    <h1>Book a Parking Slot</h1>
    <p>Choose your vehicle type and time, then select an available slot from the grid.</p>
  </div>

  <!-- ── Booking Filters ──────────────────────────────────────── -->
  <div class="book-filters card">
    <div class="filters-row">

      <div class="form-group">
        <label for="vehicleType">Vehicle Type</label>
        <select id="vehicleType" name="vehicle_type">
          <option value="car">Car (4-Wheeler)</option>
          <option value="bike">Bike (2-Wheeler)</option>
          <option value="ev">Electric Vehicle (EV)</option>
        </select>
      </div>

      <div class="form-group">
        <label for="vehicleNumber">Vehicle Number</label>
        <input type="text" id="vehicleNumber" name="vehicle_number"
               placeholder="KA01AB1234"
               maxlength="15"
               style="text-transform:uppercase">
        <span class="field-hint">Format: KA01AB1234</span>
      </div>

      <div class="form-group">
        <label for="startTime">Start Date &amp; Time</label>
        <input type="datetime-local" id="startTime" name="start_time" step="900">
      </div>

      <div class="form-group">
        <label for="endTime">End Date &amp; Time</label>
        <input type="datetime-local" id="endTime" name="end_time" step="900">
      </div>

    </div><!-- /.filters-row -->

    <div class="filters-info">
      <span id="gridStatus" class="grid-status-badge">Loading slots…</span>
      <span id="lastUpdated" class="last-updated">Fetching…</span>
    </div>
  </div><!-- /.book-filters -->

  <!-- ── Legend ───────────────────────────────────────────────── -->
  <div class="slot-legend">
    <span class="legend-item"><span class="legend-dot dot-available"></span> Available</span>
    <span class="legend-item"><span class="legend-dot dot-booked"></span> Booked</span>
    <span class="legend-item"><span class="legend-dot dot-selected"></span> Selected</span>
    <span class="legend-item"><span class="legend-dot dot-maintenance"></span> Maintenance</span>
  </div>

  <!-- ── Slot Grid (rendered by book.js) ──────────────────────── -->
  <div id="slotGrid" class="slot-grid-wrapper">
    <p class="grid-empty">Loading slots…</p>
  </div>

  <!-- ── Price Estimate (updated by book.js on slot select) ───── -->
  <div id="priceEstimate" class="price-estimate card">
    <p class="estimate-placeholder">Select a slot and time range to see price.</p>
  </div>

</section>

<!-- ── Confirm Booking Modal ──────────────────────────────────── -->
<div id="modalOverlay" class="modal-overlay" hidden></div>

<div id="confirmModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" hidden>
  <div class="modal-inner">
    <h2 id="modalTitle">✅ Confirm Booking</h2>
    <p class="modal-sub">Please review your booking details before paying.</p>

    <table class="summary-table">
      <tr><th>Slot</th><td id="sumSlot">—</td></tr>
      <tr><th>Vehicle</th><td id="sumVehicle">—</td></tr>
      <tr><th>Start</th><td id="sumStart">—</td></tr>
      <tr><th>End</th><td id="sumEnd">—</td></tr>
      <tr><th>Duration</th><td id="sumDuration">—</td></tr>
      <tr class="row-total"><th>Total Amount</th><td id="sumAmount">—</td></tr>
    </table>

    <p class="modal-payment-note">💳 Payment is simulated — no real charges apply.</p>

    <!-- Hidden CSRF token for the fetch() call -->
    <input type="hidden" id="csrfToken" value="<?= csrf_token() ?>">

    <div class="modal-actions">
      <button id="cancelBookingBtn" class="btn btn-ghost" type="button">Cancel</button>
      <button id="confirmBookingBtn" class="btn btn-primary" type="button">Confirm &amp; Pay ₹</button>
    </div>
  </div>
</div>

<script src="/smart-parking/assets/js/validate.js"></script>
<script src="/smart-parking/assets/js/book.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
