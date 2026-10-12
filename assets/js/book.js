/**
 * assets/js/book.js
 * Powers the booking page (book.php):
 *   - Fetches slot data from api/get_slots.php
 *   - Renders the slot grid grouped by zone
 *   - Manages slot selection, live price estimate
 *   - Shows a confirm modal, submits via fetch()
 *   - Auto-refreshes every 10 seconds
 */

/* ── State ─────────────────────────────────────────────────── */
var selectedSlot   = null;   // { id, slot_code, zone, hourly_rate, slot_type }
var currentSlots   = [];     // last fetched slots array
var refreshTimer   = null;   // setInterval handle
var REFRESH_MS     = 10000;  // 10 seconds

/* ── DOM refs (set after DOMContentLoaded) ─────────────────── */
var vehicleTypeEl, vehicleNumberEl, startTimeEl, endTimeEl;
var gridContainer, gridStatus, lastUpdatedEl, priceEstimateEl;
var confirmModal, modalOverlay;

document.addEventListener('DOMContentLoaded', function () {
  vehicleTypeEl   = document.getElementById('vehicleType');
  vehicleNumberEl = document.getElementById('vehicleNumber');
  startTimeEl     = document.getElementById('startTime');
  endTimeEl       = document.getElementById('endTime');
  gridContainer   = document.getElementById('slotGrid');
  gridStatus      = document.getElementById('gridStatus');
  lastUpdatedEl   = document.getElementById('lastUpdated');
  priceEstimateEl = document.getElementById('priceEstimate');
  confirmModal    = document.getElementById('confirmModal');
  modalOverlay    = document.getElementById('modalOverlay');

  // Set min to now (rounded up to next 15 min) for datetime inputs
  setDatetimeDefaults();

  // Listen for filter changes
  vehicleTypeEl.addEventListener('change', onFiltersChange);
  startTimeEl.addEventListener('change',   onFiltersChange);
  endTimeEl.addEventListener('change',     onFiltersChange);

  // Initial load
  fetchSlots();

  // Auto-refresh every 10 seconds
  refreshTimer = setInterval(fetchSlots, REFRESH_MS);

  // Modal buttons
  document.getElementById('cancelBookingBtn').addEventListener('click', closeModal);
  document.getElementById('confirmBookingBtn').addEventListener('click', submitBooking);
  if (modalOverlay) modalOverlay.addEventListener('click', closeModal);

  // Keyboard: close modal on Escape
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
  });
});

/* ── Set sensible default datetimes ─────────────────────────── */
function setDatetimeDefaults() {
  var now    = new Date();
  // Round up to next 15-minute mark
  var mins   = now.getMinutes();
  var rem    = 15 - (mins % 15);
  now.setMinutes(mins + rem, 0, 0);

  var end = new Date(now.getTime() + 2 * 60 * 60 * 1000); // +2 hours

  startTimeEl.value = toDatetimeLocal(now);
  endTimeEl.value   = toDatetimeLocal(end);
  startTimeEl.min   = toDatetimeLocal(new Date()); // can't pick past
}

function toDatetimeLocal(d) {
  // Format: YYYY-MM-DDTHH:MM
  var pad = function(n) { return String(n).padStart(2, '0'); };
  return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) +
    'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
}

/* ── Handle filter changes ──────────────────────────────────── */
function onFiltersChange() {
  selectedSlot = null;     // Clear selection when filters change
  updatePriceEstimate();
  clearInterval(refreshTimer);
  fetchSlots();
  refreshTimer = setInterval(fetchSlots, REFRESH_MS);
}

/* ── Fetch slots from API ───────────────────────────────────── */
function fetchSlots() {
  var type  = vehicleTypeEl.value;
  var start = startTimeEl.value;
  var end   = endTimeEl.value;

  // Client-side pre-validation of time range
  if (start && end && new Date(end) <= new Date(start)) {
    showGridError('End time must be after start time.');
    return;
  }

  var url = '/smart-parking/api/get_slots.php?vehicle_type=' + encodeURIComponent(type);
  if (start) url += '&start_time=' + encodeURIComponent(start);
  if (end)   url += '&end_time='   + encodeURIComponent(end);

  gridContainer.classList.add('grid-loading');

  fetch(url)
    .then(function(res) { return res.json(); })
    .then(function(data) {
      gridContainer.classList.remove('grid-loading');
      if (!data.success) {
        showGridError(data.message || 'Failed to load slots.');
        return;
      }
      currentSlots = data.slots;
      renderGrid(data.slots, data.available_count, data.total_count);
      updateLastUpdated();
      // Re-apply selection if slot still available
      if (selectedSlot) {
        var found = data.slots.find(function(s) { return s.id === selectedSlot.id; });
        if (!found || found.availability !== 'available') {
          selectedSlot = null;
          updatePriceEstimate();
        }
      }
    })
    .catch(function() {
      gridContainer.classList.remove('grid-loading');
      showGridError('Network error. Retrying…');
    });
}

/* ── Render the slot grid ───────────────────────────────────── */
function renderGrid(slots, availCount, totalCount) {
  if (!slots || slots.length === 0) {
    gridContainer.innerHTML = '<p class="grid-empty">No slots found for this vehicle type.</p>';
    gridStatus.textContent  = '0 of 0 slots available';
    return;
  }

  gridStatus.textContent = availCount + ' of ' + totalCount + ' slots available';

  // Group slots by zone
  var zones = {};
  slots.forEach(function(slot) {
    if (!zones[slot.zone]) zones[slot.zone] = [];
    zones[slot.zone].push(slot);
  });

  var html = '';
  Object.keys(zones).sort().forEach(function(zone) {
    html += '<div class="zone-group">';
    html += '<h3 class="zone-title">' + escHtml(zone) + '</h3>';
    html += '<div class="slot-tiles">';

    zones[zone].forEach(function(slot) {
      var avail   = slot.availability;            // "available" | "booked" | "maintenance"
      var isSelected = selectedSlot && selectedSlot.id === slot.id;
      var stateClass = isSelected ? 'slot-selected' : 'slot-' + avail;
      var clickable  = avail === 'available' && !isSelected ? '' : (avail !== 'available' ? '' : '');
      var disabled   = (avail !== 'available') ? ' aria-disabled="true"' : '';

      // Clean SVG icons by vehicle type
      var icon = '';
      if (slot.slot_type === 'bike') {
        icon = '<svg class="slot-svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18.5" cy="17.5" r="3.5"/><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="15" cy="5" r="1"/><path d="M12 17.5V14l-3-3 4-3 2 3h2"/></svg>';
      } else if (slot.slot_type === 'ev') {
        icon = '<svg class="slot-svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>';
      } else {
        icon = '<svg class="slot-svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/></svg>';
      }

      html += '<button class="slot-tile ' + stateClass + '"' +
              (avail !== 'available' ? ' disabled' : '') +
              ' data-id="' + slot.id + '"' +
              ' data-code="' + escHtml(slot.slot_code) + '"' +
              ' data-rate="' + slot.hourly_rate + '"' +
              ' data-type="' + slot.slot_type + '"' +
              ' title="' + escHtml(slot.slot_code) + ' — ' + ucfirst(avail) + '"' +
              ' aria-label="Slot ' + escHtml(slot.slot_code) + ', ' + ucfirst(avail) + '">';
      html += '<span class="slot-icon">' + icon + '</span>';
      html += '<span class="slot-code">' + escHtml(slot.slot_code) + '</span>';
      html += '<span class="slot-rate">₹' + slot.hourly_rate + '/hr</span>';
      html += '<span class="slot-status-badge">' + statusLabel(avail) + '</span>';
      html += '</button>';
    });

    html += '</div></div>';
  });

  gridContainer.innerHTML = html;

  // Attach click handlers to available tiles
  gridContainer.querySelectorAll('.slot-tile:not([disabled])').forEach(function(tile) {
    tile.addEventListener('click', function() {
      onSlotClick(tile);
    });
  });
}

function statusLabel(avail) {
  var labels = { available: '✔ Free', booked: '✖ Booked', maintenance: '🔧 Maint.' };
  return labels[avail] || avail;
}

function ucfirst(s) {
  return s.charAt(0).toUpperCase() + s.slice(1);
}

/* ── Slot tile click ────────────────────────────────────────── */
function onSlotClick(tile) {
  var id   = parseInt(tile.dataset.id);
  var code = tile.dataset.code;
  var rate = parseFloat(tile.dataset.rate);
  var type = tile.dataset.type;

  // Toggle off if already selected
  if (selectedSlot && selectedSlot.id === id) {
    selectedSlot = null;
    fetchSlots();  // re-render to remove blue highlight
    updatePriceEstimate();
    return;
  }

  selectedSlot = { id: id, slot_code: code, hourly_rate: rate, slot_type: type };

  // Highlight selected tile
  gridContainer.querySelectorAll('.slot-tile').forEach(function(t) {
    t.classList.remove('slot-selected');
  });
  tile.classList.remove('slot-available');
  tile.classList.add('slot-selected');

  updatePriceEstimate();
  showToast('info', 'Slot ' + code + ' selected. Review the price below and click Confirm.');
}

/* ── Live price estimate ─────────────────────────────────────── */
function updatePriceEstimate() {
  if (!selectedSlot) {
    priceEstimateEl.innerHTML = '<p class="estimate-placeholder">Select a slot and time range to see price.</p>';
    return;
  }

  var start = startTimeEl.value;
  var end   = endTimeEl.value;

  if (!start || !end) {
    priceEstimateEl.innerHTML = '<p class="estimate-placeholder">Set start and end times to see price.</p>';
    return;
  }

  var startMs = new Date(start).getTime();
  var endMs   = new Date(end).getTime();

  if (endMs <= startMs) {
    priceEstimateEl.innerHTML = '<p class="estimate-error">⚠️ End time must be after start time.</p>';
    return;
  }

  var minutes = (endMs - startMs) / 60000;
  var hours   = Math.ceil(minutes / 60);

  if (hours < 1) {
    priceEstimateEl.innerHTML = '<p class="estimate-error">⚠️ Minimum 1 hour required.</p>';
    return;
  }
  if (hours > 24) {
    priceEstimateEl.innerHTML = '<p class="estimate-error">⚠️ Maximum 24 hours allowed.</p>';
    return;
  }

  var amount = hours * selectedSlot.hourly_rate;
  var durText = hours === 1 ? '1 hour' : hours + ' hours';

  priceEstimateEl.innerHTML =
    '<div class="estimate-card">' +
    '<div class="estimate-row"><span>Slot</span><strong>' + escHtml(selectedSlot.slot_code) + '</strong></div>' +
    '<div class="estimate-row"><span>Duration</span><strong>' + durText + ' (billed whole hrs)</strong></div>' +
    '<div class="estimate-row"><span>Rate</span><strong>₹' + selectedSlot.hourly_rate + '/hr</strong></div>' +
    '<div class="estimate-row estimate-total"><span>Estimated Total</span><strong>₹' + amount.toFixed(2) + '</strong></div>' +
    '<button id="openConfirmBtn" class="btn btn-primary btn-confirm-open" type="button">Confirm Booking →</button>' +
    '</div>';

  document.getElementById('openConfirmBtn').addEventListener('click', openConfirmModal);
}

/* ── Confirm modal ───────────────────────────────────────────── */
function openConfirmModal() {
  if (!selectedSlot) return;

  // Validate vehicle number client-side first
  var vn = vehicleNumberEl.value.trim().toUpperCase();
  if (!/^[A-Z]{2}[0-9]{2}[A-Z]{1,3}[0-9]{4}$/.test(vn)) {
    showToast('error', 'Enter a valid vehicle number (e.g. KA01AB1234) before confirming.');
    vehicleNumberEl.focus();
    return;
  }

  var start     = startTimeEl.value;
  var end       = endTimeEl.value;
  var startMs   = new Date(start).getTime();
  var endMs     = new Date(end).getTime();
  var minutes   = (endMs - startMs) / 60000;
  var hours     = Math.ceil(minutes / 60);
  var amount    = hours * selectedSlot.hourly_rate;

  // Populate modal summary
  document.getElementById('sumSlot').textContent    = selectedSlot.slot_code;
  document.getElementById('sumVehicle').textContent = vn;
  document.getElementById('sumStart').textContent   = formatDTLocal(start);
  document.getElementById('sumEnd').textContent     = formatDTLocal(end);
  document.getElementById('sumDuration').textContent = hours + ' hour(s)';
  document.getElementById('sumAmount').textContent   = '₹' + amount.toFixed(2);

  confirmModal.hidden  = false;
  if (modalOverlay) modalOverlay.hidden = false;
  document.getElementById('confirmBookingBtn').focus();
}

function closeModal() {
  if (confirmModal)  confirmModal.hidden  = true;
  if (modalOverlay)  modalOverlay.hidden  = true;
}

/* ── Submit booking via fetch() ─────────────────────────────── */
function submitBooking() {
  var btn = document.getElementById('confirmBookingBtn');
  btn.disabled = true;
  btn.textContent = 'Booking…';

  var csrfToken = document.getElementById('csrfToken').value;

  var payload = {
    slot_id:        selectedSlot.id,
    vehicle_number: vehicleNumberEl.value.trim().toUpperCase(),
    vehicle_type:   vehicleTypeEl.value,
    start_time:     startTimeEl.value,
    end_time:       endTimeEl.value,
  };

  fetch('/smart-parking/api/create_booking.php', {
    method:  'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken,
    },
    body: JSON.stringify(payload),
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    closeModal();
    if (data.success) {
      showToast('success', data.message, 5000);
      // Redirect to receipt page after short delay
      setTimeout(function() {
        window.location.href = '/smart-parking/receipt.php?id=' + data.booking_id;
      }, 1500);
    } else {
      showToast('error', data.message || 'Booking failed. Please try again.');
      // Refresh grid in case the slot was taken
      selectedSlot = null;
      fetchSlots();
      updatePriceEstimate();
      btn.disabled = false;
      btn.textContent = 'Confirm & Pay';
    }
  })
  .catch(function() {
    closeModal();
    showToast('error', 'Network error. Please try again.');
    btn.disabled = false;
    btn.textContent = 'Confirm & Pay';
  });
}

/* ── Utilities ──────────────────────────────────────────────── */
function showGridError(msg) {
  gridContainer.innerHTML = '<p class="grid-empty grid-error">⚠️ ' + escHtml(msg) + '</p>';
}

function updateLastUpdated() {
  if (lastUpdatedEl) {
    var now = new Date();
    var h   = String(now.getHours()).padStart(2, '0');
    var m   = String(now.getMinutes()).padStart(2, '0');
    var s   = String(now.getSeconds()).padStart(2, '0');
    lastUpdatedEl.textContent = 'Last updated: ' + h + ':' + m + ':' + s;
  }
}

function formatDTLocal(s) {
  if (!s) return '-';
  var d = new Date(s);
  return d.toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric' }) +
    ' ' + d.toLocaleTimeString('en-IN', { hour:'2-digit', minute:'2-digit', hour12: true });
}

function escHtml(s) {
  var d = document.createElement('div');
  d.appendChild(document.createTextNode(String(s)));
  return d.innerHTML;
}
