/**
 * assets/js/validate.js
 * Client-side form validation helpers.
 * Attaches real-time validation to register and login forms.
 */

/* ── Validation rules ────────────────────────────────────────── */

function isValidEmail(v) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim());
}

function isValidPhone(v) {
  return /^[6-9][0-9]{9}$/.test(v.trim());
}

function isValidVehicleNumber(v) {
  // Indian: 2 letters + 2 digits + 1-3 letters + 4 digits, e.g. KA01AB1234
  return /^[A-Z]{2}[0-9]{2}[A-Z]{1,3}[0-9]{4}$/.test(v.toUpperCase().trim());
}

/* ── Inline error helper ─────────────────────────────────────── */

function setFieldError(fieldId, msg) {
  var el = document.getElementById('err_' + fieldId);
  if (el) {
    el.textContent = msg;
    el.style.display = msg ? 'block' : 'none';
  }
  var input = document.getElementById(fieldId);
  if (input) {
    if (msg) {
      input.classList.add('input-error');
    } else {
      input.classList.remove('input-error');
    }
  }
  return !msg; // true = valid
}

/* ── Register form validation ────────────────────────────────── */

function attachRegisterValidation(formId) {
  var form = document.getElementById(formId);
  if (!form) return;

  var fields = {
    full_name:        { el: form.querySelector('#full_name'),        validate: function(v) { return v.trim().length >= 2 ? '' : 'Name must be at least 2 characters.'; } },
    email:            { el: form.querySelector('#email'),            validate: function(v) { return isValidEmail(v) ? '' : 'Enter a valid email address.'; } },
    phone:            { el: form.querySelector('#phone'),            validate: function(v) { return isValidPhone(v) ? '' : '10-digit mobile number required (starts with 6–9).'; } },
    password:         { el: form.querySelector('#password'),         validate: function(v) { return v.length >= 8 ? '' : 'Password must be at least 8 characters.'; } },
    confirm_password: { el: form.querySelector('#confirm_password'), validate: function(v) { return v === form.querySelector('#password').value ? '' : 'Passwords do not match.'; } },
  };

  Object.keys(fields).forEach(function(key) {
    var f = fields[key];
    if (!f.el) return;
    f.el.addEventListener('blur', function() {
      setFieldError(key, f.validate(f.el.value));
    });
    f.el.addEventListener('input', function() {
      if (f.el.classList.contains('input-error')) {
        setFieldError(key, f.validate(f.el.value));
      }
    });
  });

  form.addEventListener('submit', function(e) {
    var valid = true;
    Object.keys(fields).forEach(function(key) {
      var f   = fields[key];
      if (!f.el) return;
      var err = f.validate(f.el.value);
      if (!setFieldError(key, err)) valid = false;
    });
    if (!valid) e.preventDefault();
  });
}

/* ── Login form validation ───────────────────────────────────── */

function attachLoginValidation(formId) {
  var form = document.getElementById(formId);
  if (!form) return;

  form.addEventListener('submit', function(e) {
    var valid  = true;
    var email  = form.querySelector('#email');
    var pass   = form.querySelector('#password');

    if (!isValidEmail(email.value)) {
      setFieldError('email', 'Enter a valid email address.');
      valid = false;
    } else {
      setFieldError('email', '');
    }

    if (!pass.value.trim()) {
      setFieldError('password', 'Password is required.');
      valid = false;
    } else {
      setFieldError('password', '');
    }

    if (!valid) e.preventDefault();
  });
}
