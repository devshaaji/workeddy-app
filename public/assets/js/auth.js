(function (window, document) {
  'use strict';

  var nigerStateLgas = [
    'Agaie', 'Agwara', 'Bida', 'Borgu', 'Bosso', 'Chanchaga',
    'Edati', 'Gbako', 'Gurara', 'Katcha', 'Kontagora', 'Lapai',
    'Lavun', 'Magama', 'Mariga', 'Mashegu', 'Mokwa', 'Munya',
    'Paikoro', 'Rafi', 'Rijau', 'Shiroro', 'Suleja', 'Tafa', 'Wushishi'
  ];

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
      return;
    }
    fn();
  }

  function showAlert(type, message, target) {
    if (window.App && App.ui && App.ui.showAlert) {
      App.ui.showAlert(type, message, target);
    }
  }

  function bindValidation() {
    document.querySelectorAll('.needs-validation').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault();
          event.stopPropagation();
        }
        form.classList.add('was-validated');
      }, false);
    });
  }

  function bindPasswordToggles() {
    document.querySelectorAll('[data-password-toggle]').forEach(function (toggle) {
      toggle.addEventListener('click', function () {
        var target = document.querySelector(toggle.getAttribute('data-password-toggle'));
        if (!target) { return; }
        var showing = target.type === 'text';
        target.type = showing ? 'password' : 'text';
        toggle.textContent = showing ? 'Show' : 'Hide';
      });
    });
  }

  function bindAjaxForms() {
    if (!window.App || !App.forms) { return; }

    function bindValidatedAjaxForm(form, opts) {
      opts = opts || {};
      var originalBeforeSend = opts.beforeSend;
      opts.beforeSend = function (candidateForm) {
        if (!candidateForm.checkValidity()) {
          candidateForm.classList.add('was-validated');
          return false;
        }
        if (originalBeforeSend) {
          return originalBeforeSend(candidateForm);
        }
        return true;
      };
      App.forms.bindAjaxForm(form, opts);
    }

    var loginForm = document.getElementById('loginForm');
    if (loginForm) {
      bindValidatedAjaxForm(loginForm, {
        alertTarget: '#login-feedback',
        onSuccess: function (res) {
          if (res.data && res.data.requiresOtp) {
            window.location.href = '/verify-otp';
            return;
          }
          window.location.href = loginForm.getAttribute('data-success-redirect') || '/dashboard';
        }
      });
    }

    var forgotPasswordForm = document.getElementById('forgotPasswordForm');
    if (forgotPasswordForm) {
      bindValidatedAjaxForm(forgotPasswordForm, {
        alertTarget: '#forgot-password-feedback',
        onSuccess: function (res) {
          var msg = (res.data && res.data.message) || res.message || 'If the account is active, reset instructions have been sent.';
          showAlert('success', msg, '#forgot-password-feedback');
        }
      });
    }

    var resetPasswordForm = document.getElementById('resetPasswordForm');
    if (resetPasswordForm) {
      bindValidatedAjaxForm(resetPasswordForm, {
        alertTarget: '#reset-password-feedback',
        beforeSend: function (form) {
          var password = form.querySelector('#new_password');
          var confirm = form.querySelector('#confirm_password');
          if (password && confirm && password.value !== confirm.value) {
            showAlert('danger', 'Passwords do not match.', '#reset-password-feedback');
            confirm.classList.add('is-invalid');
            return false;
          }
          return true;
        },
        onSuccess: function () {
          showAlert('success', 'Password reset successful. Redirecting to sign in...', '#reset-password-feedback');
          window.setTimeout(function () {
            window.location.href = '/login';
          }, 1800);
        }
      });
    }

    var registrationForm = document.getElementById('registrationForm');
    if (registrationForm) {
      bindValidatedAjaxForm(registrationForm, {
        alertTarget: '#registration-feedback',
        beforeSend: function (form) {
          var password = form.querySelector('#register-password');
          var confirm = form.querySelector('#register-confirm-password');
          if (password && confirm && password.value !== confirm.value) {
            showAlert('danger', 'Passwords do not match.', '#registration-feedback');
            confirm.classList.add('is-invalid');
            return false;
          }
          return true;
        },
        onSuccess: function () {
          showAlert('success', 'Registration submitted. Please wait for approval before signing in.', '#registration-feedback');
          window.setTimeout(function () {
            window.location.href = '/login';
          }, 2500);
        }
      });
    }

    var verifyOtpForm = document.getElementById('verifyOtpForm');
    if (verifyOtpForm) {
      bindValidatedAjaxForm(verifyOtpForm, {
        alertTarget: '#verify-otp-feedback',
        beforeSend: function () {
          var hidden = document.getElementById('otp_code');
          if (!hidden || hidden.value.length !== 6) {
            showAlert('danger', 'Enter the complete six-digit code.', '#verify-otp-feedback');
            return false;
          }
          return true;
        },
        onSuccess: function () {
          window.location.href = '/dashboard';
        }
      });
    }
  }

  function populateLgas() {
    var select = document.getElementById('lga');
    if (!select) { return; }

    select.innerHTML = '<option value="">Select LGA</option>';
    nigerStateLgas.forEach(function (lga) {
      var option = document.createElement('option');
      option.value = lga;
      option.textContent = lga;
      select.appendChild(option);
    });
  }

  function bindOtpInputs() {
    var inputs = Array.prototype.slice.call(document.querySelectorAll('[data-otp-input]'));
    var hidden = document.getElementById('otp_code');
    if (!inputs.length || !hidden) { return; }

    function updateCode() {
      hidden.value = inputs.map(function (input) { return input.value; }).join('');
    }

    inputs.forEach(function (input, index) {
      input.addEventListener('input', function () {
        input.value = input.value.replace(/\D/g, '').slice(0, 1);
        if (input.value && index < inputs.length - 1) {
          inputs[index + 1].focus();
        }
        updateCode();
      });

      input.addEventListener('keydown', function (event) {
        if (event.key === 'Backspace' && !input.value && index > 0) {
          inputs[index - 1].focus();
        }
      });

      input.addEventListener('paste', function (event) {
        var pasted = (event.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
        if (pasted.length !== 6) { return; }
        event.preventDefault();
        inputs.forEach(function (otpInput, digitIndex) {
          otpInput.value = pasted[digitIndex] || '';
        });
        updateCode();
      });
    });

    inputs[0].focus();
  }

  function bindOtpTimer() {
    var form = document.getElementById('verifyOtpForm');
    var timer = document.getElementById('otp-timer');
    if (!form || !timer) { return; }

    var expiresAtValue = form.getAttribute('data-expires-at');
    if (!expiresAtValue) {
      timer.textContent = 'Use the latest code you received.';
      return;
    }

    var expiresAt = new Date(expiresAtValue).getTime();
    if (!Number.isFinite(expiresAt)) {
      timer.textContent = 'Use the latest code you received.';
      return;
    }

    window.setInterval(function () {
      var remaining = expiresAt - Date.now();
      if (remaining <= 0) {
        timer.textContent = 'Code expired. Request a new verification code.';
        return;
      }
      var minutes = Math.floor(remaining / 60000);
      var seconds = Math.floor((remaining % 60000) / 1000);
      timer.textContent = 'Code expires in ' + minutes + 'm ' + (seconds < 10 ? '0' : '') + seconds + 's.';
    }, 1000);
  }

  function bindResendOtp() {
    var button = document.getElementById('iam-resend-otp');
    var userId = document.getElementById('otp_user_id');
    if (!button || !userId || !window.App || !App.api) { return; }

    button.addEventListener('click', function () {
      if (!userId.value) {
        showAlert('danger', 'Cannot resend because the pending user is missing.', '#verify-otp-feedback');
        return;
      }

      App.ui.setButtonLoading(button, true, 'Resending...');
      App.api.post('/api/v1/auth/resend-otp', { userId: parseInt(userId.value, 10) })
        .then(function (res) {
          App.ui.setButtonLoading(button, false);
          if (res.ok) {
            showAlert('success', 'Verification code resent.', '#verify-otp-feedback');
            return;
          }
          showAlert('danger', res.message || 'Failed to resend verification code.', '#verify-otp-feedback');
        });
    });
  }

  ready(function () {
    bindValidation();
    bindPasswordToggles();
    populateLgas();
    bindOtpInputs();
    bindOtpTimer();
    bindResendOtp();
    bindAjaxForms();
  });
})(window, document);
