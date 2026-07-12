/**
 * ============================================================================
 * App.js V2 Shared JavaScript Foundation
 * ============================================================================
 *
 * Single global namespace: window.App
 *
 * Provides:  config, api, notify, ui, forms, tables, modals, utils
 *
 * Rules:
 *   - No ES modules / import-export
 *   - IIFE style, browser-compatible
 *   - Uses fetch, NOT $.ajax
 *   - Preserves Sneat / Bootstrap compatibility
 *   - All page scripts MUST use App instead of duplicating logic
 *
 * Load order:
 *   1. vendor scripts (jQuery, Bootstrap, Sneat helpers)
 *   2. config.js
 *   3. main.js
 *   4. app.js          â† this file
 *   5. page-specific script
 * ============================================================================
 */
(function (window, document) {
  'use strict';

  /* -----------------------------------------------------------------------
   * Prevent double-init
   * --------------------------------------------------------------------- */
  if (window.App) { return; }

  /* =======================================================================
   * CONFIG
   * ===================================================================== */
  var config = {
    /** Base URL for API calls auto-detected from <html data-assets-path>. */
    baseUrl: (function () {
      var el = document.documentElement;
      var ap = el.getAttribute('data-assets-path') || '/assets/';
      // assets path is e.g. "/invoice/v1/public/assets/" walk up to the app root
      return ap.replace(/\/assets\/?$/, '') || '/';
    })(),

    /** Default fetch timeout in milliseconds. */
    timeout: 30000,

    /** Debug mode logs request/response to console when true. */
    debug: false
  };

  var html2PdfConfig = {
    url: 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js',
    integrity: 'sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==',
    fallbackUrl: 'https://cdn.jsdelivr.net/npm/html2pdf.js@0.10.1/dist/html2pdf.bundle.min.js',
    promise: null
  };

  /**
   * Look up the current CSRF token from a <meta name="csrf-token"> tag
   * or a hidden input named _token, whichever exists.
   */
  function _getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) { return meta.getAttribute('content') || ''; }
    var input = document.querySelector('input[name="_token"]');
    if (input) { return input.value || ''; }
    return '';
  }


  /* =======================================================================
   * INTERNAL HELPERS
   * ===================================================================== */

  /**
   * Normalise every API response into a predictable envelope.
   *
   * @param {Response} res  - Raw fetch Response
   * @returns {Promise<Object>}  Normalised { ok, status, message, data, errors, raw }
   */
  function _normalise(res) {
    var status = res.status;

    // Handle 204 No Content
    if (status === 204) {
      return Promise.resolve({
        ok: true, status: 204, code: null, message: 'No Content',
        data: null, errors: null, raw: null
      });
    }

    return res.text().then(function (text) {
      var json = null;
      try { json = JSON.parse(text); } catch (_) { /* not JSON */ }

      var ok = status >= 200 && status < 300;
      var message = '';
      var data = null;
      var errors = null;
      var code = null;

      if (json) {
        if (json.success === false) {
          ok = false;
        }
        code = json.code || null;
        message = json.message || json.error || json.msg || '';
        if (String(json.status || '').toLowerCase() === 'error') {
          ok = false;
        }
        data = json.data !== undefined ? json.data : (ok ? json : null);
        errors = json.errors || {};
      }

      // Fallback messages per status code
      if (!message) {
        var fallbacks = {
          400: 'Bad request',
          401: 'Authentication required',
          403: 'You do not have permission for this action',
          404: 'Resource not found',
          419: 'Invalid CSRF token',
          422: 'Validation failed',
          429: 'Too many requests, please wait',
          500: 'Internal server error'
        };
        message = fallbacks[status] || (ok ? 'Success' : 'Request failed');
      }

      return {
        ok: ok,
        status: status,
        code: code,
        message: message,
        data: data,
        errors: errors,
        raw: json
      };
    });
  }

  /**
   * Build a fetch-compatible AbortController timeout.
   */
  function _makeAbort(ms) {
    if (typeof AbortController === 'undefined') { return {}; }
    var ctrl = new AbortController();
    var id = setTimeout(function () { ctrl.abort(); }, ms || config.timeout);
    return { signal: ctrl.signal, timerId: id };
  }


  /* =======================================================================
   * APP.API
   * ===================================================================== */
  var api = {};

  /**
   * Core request method.
   *
   * @param {string} method   - HTTP method (GET, POST, PUT, PATCH, DELETE)
   * @param {string} url      - Relative or absolute URL
   * @param {*}      [data]   - Body payload (object, FormData, or null)
   * @param {Object} [opts]   - Extra options
   * @param {Object} [opts.headers]  - Additional headers
   * @param {number} [opts.timeout]  - Override default timeout
   * @param {boolean}[opts.raw]      - Return raw fetch Response instead
   * @returns {Promise<Object>} Normalised response envelope
   */
  api.request = function (method, url, data, opts) {
    opts = opts || {};

    // Resolve relative URLs against baseUrl
    if (url.charAt(0) === '/') {
      url = config.baseUrl.replace(/\/$/, '') + url;
    } else if (!/^https?:\/\//.test(url)) {
      url = config.baseUrl.replace(/\/$/, '') + '/' + url;
    }

    var headers = Object.assign({}, opts.headers || {});
    var fetchOpts = {
      method: method.toUpperCase(),
      credentials: 'same-origin',
      headers: headers
    };

    // CSRF
    var token = _getCsrfToken();
    if (token && method !== 'GET') {
      headers['X-CSRF-TOKEN'] = token;
    }

    // Accept JSON
    if (!headers['Accept']) {
      headers['Accept'] = 'application/json';
    }

    // Body
    if (data !== undefined && data !== null && method !== 'GET') {
      if (data instanceof FormData) {
        fetchOpts.body = data;
        // Let browser set multipart content-type with boundary
      } else {
        headers['Content-Type'] = 'application/json';
        fetchOpts.body = JSON.stringify(data);
      }
    }

    // Query string for GET
    if (method === 'GET' && data && typeof data === 'object') {
      var qs = _buildQuery(data);
      if (qs) { url += (url.indexOf('?') > -1 ? '&' : '?') + qs; }
    }

    fetchOpts.headers = headers;

    // Timeout / abort
    var ab = _makeAbort(opts.timeout);
    if (ab.signal) { fetchOpts.signal = ab.signal; }

    if (config.debug) {
      console.log('[App.api]', method, url, data || '');
    }

    return fetch(url, fetchOpts)
      .then(function (res) {
        if (ab.timerId) { clearTimeout(ab.timerId); }

        // 401 â†’ Redirect to login
        if (res.status === 401 && !opts.skipAuthRedirect) {
          var loginUrl = config.baseUrl.replace(/\/$/, '') + '/login';
          window.location.href = loginUrl;
          // Return a never-resolving promise to stop downstream handlers.
          return new Promise(function () { });
        }

        if (opts.raw) { return res; }
        return _normalise(res);
      })
      .then(function (result) {
        if (config.debug && result && result.status) {
          console.log('[App.api] Response', result.status, result);
        }
        return result;
      })
      .catch(function (err) {
        if (ab.timerId) { clearTimeout(ab.timerId); }
        if (err && err.name === 'AbortError') {
          return {
            ok: false, status: 0, code: null, message: 'Request timed out',
            data: null, errors: null, raw: null
          };
        }
        return {
          ok: false, status: 0, code: null, message: err ? err.message : 'Network error',
          data: null, errors: null, raw: null
        };
      });
  };

  /** Shorthand methods */
  api.get = function (url, params, opts) { return api.request('GET', url, params, opts); };
  api.post = function (url, data, opts) { return api.request('POST', url, data, opts); };
  api.put = function (url, data, opts) { return api.request('PUT', url, data, opts); };
  api.patch = function (url, data, opts) { return api.request('PATCH', url, data, opts); };
  api.delete = function (url, data, opts) { return api.request('DELETE', url, data, opts); };


  /* =======================================================================
   * APP.NOTIFY
   * ===================================================================== */
  var notify = {};

  function _getIconSvg(type, extraClass) {
    var svgStart = '<svg xmlns="http://www.w3.org/2000/svg" class="icon ' + (extraClass || '') + '" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/>';
    var path = '';
    if (type === 'success') {
      path = '<path d="M5 12l5 5l10 -10" />';
    } else if (type === 'error' || type === 'danger') {
      path = '<circle cx="12" cy="12" r="9" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />';
    } else if (type === 'warning') {
      path = '<path d="M12 9v2m0 4v.01" /><path d="M5 19h14a2 2 0 0 0 1.84 -2.75l-7.1 -12.25a2 2 0 0 0 -3.5 0l-7.1 12.25a2 2 0 0 0 1.75 2.75" />';
    } else if (type === 'folder') {
      path = '<path d="M5 4h4l3 3h7a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-11a2 2 0 0 1 2 -2" />';
    } else {
      path = '<circle cx="12" cy="12" r="9" /><line x1="12" y1="8" x2="12.01" y2="8" /><polyline points="11 12 12 12 12 16 13 16" />';
    }
    return svgStart + path + '</svg>';
  }

  /**
   * Internal show a notification via Notyf (Sneat toasts) or a fallback
   * Bootstrap alert appended to .content-wrapper.
   */
  function _toast(type, message) {
    // Try Notyf first (used by Sneat for toasts)
    if (typeof Notyf !== 'undefined') {
      var notyf = window._appNotyf;
      if (!notyf) {
        var col = window.config && window.config.colors ? window.config.colors : {};
        notyf = new Notyf({
          duration: 4000,
          ripple: true,
          dismissible: true,
          position: { x: 'right', y: 'top' },
          types: [
            {
              type: 'info',
              background: col.info || '#03c3ec',
              icon: { className: 'icon-base bx bxs-info-circle icon-md text-white', tagName: 'i' }
            },
            {
              type: 'warning',
              background: col.warning || '#ffab00',
              icon: { className: 'icon-base bx bxs-error icon-md text-white', tagName: 'i' }
            },
            {
              type: 'success',
              background: col.success || '#71dd37',
              icon: { className: 'icon-base bx bxs-check-circle icon-md text-white', tagName: 'i' }
            },
            {
              type: 'error',
              background: col.danger || '#ff3e1d',
              icon: { className: 'icon-base bx bxs-x-circle icon-md text-white', tagName: 'i' }
            }
          ]
        });
        window._appNotyf = notyf;
      }
      notyf.open({ type: type === 'danger' ? 'error' : type, message: message });
      return;
    }

    // Try Bootstrap Toast
    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
      var id = 'app-toast-' + Date.now();
      var colorClass = type === 'error' ? 'danger' : type;
      var iconSvg = _getIconSvg(type, 'text-' + colorClass + ' me-2');

      var html = '' +
        '<div id="' + id + '" class="toast bs-toast bg-' + colorClass + ' text-white shadow" role="alert" ' +
        'aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="4000">' +
        '<div class="toast-header border-0 bg-transparent text-white">' +
        iconSvg +
        '<strong class="me-auto">' + _escHtml(_ucFirst(type)) + '</strong>' +
        '<button type="button" class="ms-2 btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>' +
        '</div>' +
        '<div class="toast-body">' + _escHtml(message) + '</div>' +
        '</div>';
      var toastContainer = document.getElementById('app-toast-container') || document.body;
      toastContainer.insertAdjacentHTML('beforeend', html);
      var toastEl = document.getElementById(id);
      var t = new bootstrap.Toast(toastEl, { delay: 4000 });
      t.show();
      toastEl.addEventListener('hidden.bs.toast', function () { toastEl.remove(); });
      return;
    }

    // Fallback simple alert div in .content-wrapper or body
    var wrapper = document.querySelector('.container-xxl.flex-grow-1.container-p-y') || document.querySelector('.content-wrapper') || document.body;
    var alertClass = type === 'error' ? 'danger' : type;
    var alertHtml = '' +
      '<div class="alert alert-' + alertClass + ' alert-dismissible fade show shadow-sm" role="alert">' +
      '<div>' + _escHtml(message) + '</div>' +
      '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
      '</div>';
    wrapper.insertAdjacentHTML('afterbegin', alertHtml);

    // Auto-dismiss after 5s
    var alertNode = wrapper.querySelector('.alert');
    if (alertNode) {
      setTimeout(function () {
        if (alertNode.parentNode) { alertNode.remove(); }
      }, 5000);
    }
  }

  notify.success = function (msg) { _toast('success', msg); };
  notify.error = function (msg) { _toast('error', msg); };
  notify.warning = function (msg) { _toast('warning', msg); };
  notify.info = function (msg) { _toast('info', msg); };


  /* =======================================================================
   * APP.UI
   * ===================================================================== */
  var ui = {};

  /**
   * Set a button to loading state (disabled + spinner) or restore it.
   *
   * @param {HTMLElement|string} btn     - Button element or CSS selector
   * @param {boolean}            loading - true = show spinner, false = restore
   * @param {string}            [text]   - Override button text while loading
   */
  ui.setButtonLoading = function (btn, loading, text) {
    btn = _el(btn);
    if (!btn) { return; }

    if (loading) {
      btn.dataset.appOrigText = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="d-inline-flex align-items-center justify-content-center">' +
        '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' +
        (text || 'Processing¦') + '</span>';
    } else {
      btn.disabled = false;
      btn.innerHTML = btn.dataset.appOrigText || btn.innerHTML;
      delete btn.dataset.appOrigText;
    }
  };

  /**
   * Show a Bootstrap alert inside a target container.
   *
   * @param {string} type                - success | danger | warning | info
   * @param {string} message             - Alert text
   * @param {HTMLElement|string} target   - Container element or selector
   */
  ui.showAlert = function (type, message, target) {
    target = _el(target);
    if (!target) { return; }
    var autoHide = target.classList.contains('d-none') || target.dataset.appAlertAutoHide === '1';
    ui.clearAlert(target);
    if (autoHide) { target.dataset.appAlertAutoHide = '1'; }
    target.classList.remove('d-none');
    target.setAttribute('aria-live', 'polite');
    var html = '' +
      '<div class="alert alert-' + type + ' alert-dismissible fade show shadow-sm" role="alert">' +
      '<div>' + _escHtml(message) + '</div>' +
      '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
      '</div>';
    target.insertAdjacentHTML('afterbegin', html);
  };

  /**
   * Clear alerts from a container.
   */
  ui.clearAlert = function (target) {
    target = _el(target);
    if (!target) { return; }
    var alerts = target.querySelectorAll('.alert.alert-dismissible');
    alerts.forEach(function (a) { a.remove(); });
    if (target.dataset.appAlertAutoHide === '1' && !target.querySelector('.alert.alert-dismissible')) {
      target.classList.add('d-none');
    }
  };

  /**
   * Show an empty state message inside a container.
   *
   * @param {HTMLElement|string} target  - Container
   * @param {string}             message - Message to display
   */
  ui.showEmptyState = function (target, message) {
    target = _el(target);
    if (!target) { return; }
    target.innerHTML = '' +
      '<div class="text-center py-5">' +
      '<div class="text-muted mb-3">' +
      _getIconSvg('folder', '') +
      '</div>' +
      '<p class="mb-0 text-secondary fw-medium">' + _escHtml(message || 'No records found') + '</p>' +
      '</div>';
  };

  /**
   * Update a status badge element.
   *
   * @param {HTMLElement|string} target - Badge element or selector
   * @param {string}             status - Status string (mapped to Bootstrap color)
   */
  ui.updateStatusBadge = function (target, status) {
    target = _el(target);
    if (!target) { return; }

    var map = {
      active: 'success', approved: 'success', paid: 'success',
      pending: 'warning', draft: 'secondary', inactive: 'secondary',
      rejected: 'danger', overdue: 'danger', failed: 'danger',
      cancelled: 'danger', suspended: 'danger',
      processing: 'info', partial: 'info', sent: 'info'
    };
    var color = map[status.toLowerCase()] || 'secondary';

    // Strip old bg-label-* classes
    target.className = target.className.replace(/bg-label-\w+/g, '').replace(/bg-\w+-lt/g, '').trim();
    target.classList.add('badge', 'bg-label-' + color);
    target.textContent = _ucFirst(status);
  };


  
  /**
   * Return an HTML badge string for a given status — use in innerHTML rendering.
   * @param {string} status
   * @returns {string} HTML string
   */
  ui.statusBadge = function (status) {
    var map = {
      active: 'success', approved: 'success', paid: 'success', enrolled: 'info',
      pending: 'warning', draft: 'secondary', inactive: 'secondary',
      rejected: 'danger', overdue: 'danger', failed: 'danger',
      cancelled: 'danger', suspended: 'danger',
      processing: 'info', partial: 'info', sent: 'info', paused: 'warning',
      completed: 'secondary'
    };
    var st = (status || 'unknown').toLowerCase();
    var color = map[st] || 'secondary';
    return '<span class="badge bg-label-' + color + '">' + _escHtml(_ucFirst(st)) + '</span>';
  };
/* =======================================================================
   * APP.FORMS
   * ===================================================================== */
  var forms = {};

  function _findFormSubmitButton(form, selector, submitter) {
    if (!form) { return null; }
    if (selector) { return _el(selector); }
    if (submitter && submitter.type === 'submit' && (submitter.form === form || submitter.getAttribute('form') === form.id)) {
      return submitter;
    }

    var internal = form.querySelector('[type="submit"]');
    if (internal) { return internal; }
    if (!form.id) { return null; }

    var external = document.querySelectorAll('button[type="submit"][form], input[type="submit"][form]');
    for (var i = 0; i < external.length; i += 1) {
      if (external[i].getAttribute('form') === form.id) {
        return external[i];
      }
    }

    return null;
  }

  function _findFormAlertTarget(form, opts) {
    if (!form) { return form; }
    if (opts && opts.alertTarget) {
      return _el(opts.alertTarget) || form;
    }

    var target = form.querySelector('[data-form-feedback], [id$="-feedback"]');
    if (target) { return target; }

    var formId = form.getAttribute('id');
    if (formId) {
      var derived = formId.replace(/-form$/, '-feedback');
      if (derived !== formId) {
        target = document.getElementById(derived);
        if (target) { return target; }
      }

      target = document.getElementById(formId + '-feedback');
      if (target) { return target; }
    }

    return form;
  }

  /**
   * Bind an AJAX form” intercept submit, post via App.api, handle response.
   *
   * @param {HTMLFormElement|string} form  - Form element or selector
   * @param {Object} [opts]
   * @param {string|Function} [opts.method] - Override form method
   * @param {string|Function} [opts.url]    - Override form action
   * @param {boolean}  [opts.useFormData]  - Send as FormData instead of JSON
   * @param {string}   [opts.submitBtn]    - Selector for submit button (auto-detected)
   * @param {string|HTMLElement} [opts.alertTarget] - Container for success/error alerts
   * @param {Object}   [opts.apiOptions]    - Extra options passed to App.api
   * @param {Function} [opts.beforeSend]   - Called before request; return false to cancel
   * @param {Function} [opts.transformData] - fn(data, form) to adjust serialized payload
   * @param {Function} [opts.onSuccess]    - fn(response) on success
   * @param {Function} [opts.onError]      - fn(response) on error
   * @param {Function} [opts.onComplete]   - fn(response) always called
   * @param {boolean}  [opts.resetOnSuccess] - Reset form after success (default false)
   * @param {string}   [opts.successMsg]   - Auto-show success notification
   */
  forms.bindAjaxForm = function (form, opts) {
    form = _el(form);
    if (!form) { return; }
    opts = opts || {};

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      e.stopPropagation();

      if (opts.beforeSend && opts.beforeSend(form) === false) { return; }

      var methodValue = typeof opts.method === 'function' ? opts.method(form) : opts.method;
      var urlValue = typeof opts.url === 'function' ? opts.url(form) : opts.url;
      var method = (methodValue || form.method || 'POST').toUpperCase();
      var url = urlValue || form.getAttribute('action') || '';
      var btn = _findFormSubmitButton(form, opts.submitBtn, e.submitter);
      var alertTarget = _findFormAlertTarget(form, opts);
      var data;

      if (opts.useFormData || _hasFileInput(form)) {
        data = new FormData(form);
      } else {
        data = forms.serialize(form);
      }
      if (opts.transformData) {
        data = opts.transformData(data, form);
      }

      // Clear previous errors
      forms.clearValidationErrors(form);
      ui.clearAlert(form);
      if (alertTarget !== form) { ui.clearAlert(alertTarget); }

      // Loading state
      ui.setButtonLoading(btn, true);

      api.request(method, url, data, opts.apiOptions || {})
        .then(function (res) {
          ui.setButtonLoading(btn, false);

          if (res.ok) {
            if (opts.successMsg) { ui.showAlert('success', opts.successMsg, alertTarget); }
            if (res.message && !opts.successMsg) { ui.showAlert('success', res.message, alertTarget); }
            if (opts.resetOnSuccess) { forms.reset(form); }
            if (opts.onSuccess) { opts.onSuccess(res); }
          } else {
            var validation = _isValidationResponse(res);
            var rendered = null;

            if (validation) {
              rendered = forms.showValidationErrors(form, res.errors || {});
              if (!opts.onError) {
                if (rendered.formErrors.length) {
                  ui.showAlert('danger', rendered.formErrors.join(' '), alertTarget);
                } else if (!Object.keys(rendered.fieldErrors).length && res.message) {
                  ui.showAlert('danger', res.message, alertTarget);
                }
              }
            }

            if (!validation && res.message) { ui.showAlert('danger', res.message, alertTarget); }
            if (opts.onError) { opts.onError(res); }
          }

          if (opts.onComplete) { opts.onComplete(res); }
        });
    });
  };

  /**
   * Serialise a form into a plain object (name â†’ value).
   * Handles checkboxes, radios, selects, textareas.
   *
   * @param {HTMLFormElement|string} form
   * @returns {Object}
   */
  forms.serialize = function (form) {
    form = _el(form);
    if (!form) { return {}; }

    var fd = new FormData(form);
    var obj = {};
    fd.forEach(function (value, key) {
      // Support array fields: name="items[]"
      if (key.endsWith('[]')) {
        var k = key.slice(0, -2);
        if (!Array.isArray(obj[k])) { obj[k] = []; }
        obj[k].push(value);
      } else if (obj[key] !== undefined) {
        // Duplicate key â†’ make array
        if (!Array.isArray(obj[key])) { obj[key] = [obj[key]]; }
        obj[key].push(value);
      } else {
        obj[key] = value;
      }
    });
    return obj;
  };

  /**
   * Show server-side validation errors on form fields.
   * Expects { fieldName: "message" } or { fieldName: ["msg1","msg2"] }.
   * Renders Bootstrap 5 invalid-feedback under each matching input and
   * returns unmatched errors for form-level display.
   *
   * @param {HTMLFormElement|string} form
   * @param {Object} errors
   * @returns {{fieldErrors: Object, formErrors: string[]}}
   */
  forms.showValidationErrors = function (form, errors) {
    form = _el(form);
    var rendered = { fieldErrors: {}, formErrors: [] };
    if (!form || !errors) { return rendered; }

    Object.keys(errors).forEach(function (field) {
      var msgs = _normaliseErrorMessages(errors[field]);
      var input = _findFormField(form, field);
      if (!input || input.type === 'hidden') {
        rendered.formErrors = rendered.formErrors.concat(msgs);
        return;
      }

      input.classList.add('is-invalid');
      rendered.fieldErrors[field] = msgs;

      // Remove any existing feedback for this field
      var existing = input.parentNode.querySelector('.invalid-feedback[data-field="' + _escAttr(field) + '"]');
      if (existing) { existing.remove(); }

      var fb = document.createElement('div');
      fb.className = 'invalid-feedback d-block';
      fb.setAttribute('data-field', field);
      fb.textContent = msgs.join('. ');

      // Insert after the input (or after input-group wrapper)
      var wrapper = input.closest('.input-group') || input;
      wrapper.parentNode.insertBefore(fb, wrapper.nextSibling);
    });

    return rendered;
  };

  /**
   * Show a form-level validation error above the form fields.
   *
   * @param {HTMLFormElement|string} form
   * @param {string} message
   */
  forms.showFormError = function (form, message) {
    form = _el(form);
    if (!form || !message) { return; }

    var holder = document.createElement('div');
    holder.className = form.classList.contains('row') ? 'app-form-alert col-12' : 'app-form-alert';
    holder.innerHTML = '' +
      '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
      _escHtml(message) +
      '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
      '</div>';

    form.insertBefore(holder, form.firstChild);
  };

  /**
   * Clear all validation error states from a form.
   */
  forms.clearValidationErrors = function (form) {
    form = _el(form);
    if (!form) { return; }

    form.querySelectorAll('.is-invalid').forEach(function (el) {
      el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.invalid-feedback').forEach(function (el) {
      el.remove();
    });
    form.querySelectorAll('.app-form-alert').forEach(function (el) {
      el.remove();
    });
    form.classList.remove('was-validated');
  };

  /**
   * Reset form to its default state and clear errors.
   */
  forms.reset = function (form) {
    form = _el(form);
    if (!form) { return; }
    form.reset();
    forms.clearValidationErrors(form);
  };

  forms.setSelectLoading = function (select, message) {
    select = _el(select);
    if (!select) { return; }
    if (select.dataset.appSelectWasDisabled === undefined) {
      select.dataset.appSelectWasDisabled = select.disabled ? '1' : '0';
    }
    if (select._appSelectPlaceholderHtml === undefined) {
      var firstOption = select.querySelector('option');
      select._appSelectPlaceholderHtml = firstOption ? firstOption.outerHTML : '<option value="">Select</option>';
    }
    select.dataset.appSelectLoading = '1';
    select.disabled = true;
    select.innerHTML = '<option value="">' + _escHtml(message || 'Loading...') + '</option>';
    forms.refreshSelect(select);
  };

  forms.clearSelectLoading = function (select) {
    select = _el(select);
    if (!select) { return; }
    if (select.dataset.appSelectLoading === '1') {
      select.innerHTML = select._appSelectPlaceholderHtml || '<option value="">Select</option>';
      delete select.dataset.appSelectLoading;
      delete select._appSelectPlaceholderHtml;
    }
    if (select.dataset.appSelectWasDisabled !== undefined) {
      var wasDisabled = select.dataset.appSelectWasDisabled === '1';
      delete select.dataset.appSelectWasDisabled;
      select.disabled = wasDisabled;
    }
    forms.refreshSelect(select);
  };

  forms.enhanceSelects = function (container) {
    container = _el(container) || document;
    if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) { return; }

    var selects = [];
    if (container.matches && container.matches('select.form-select:not([data-app-select2="off"])')) {
      selects.push(container);
    }
    container.querySelectorAll('select.form-select:not([data-app-select2="off"])').forEach(function (select) {
      selects.push(select);
    });

    selects.forEach(function (select) {
      if (select.dataset.appSelect2Initialized === '1') {
        window.jQuery(select).trigger('change.select2');
        return;
      }

      var emptyOption = Array.prototype.find.call(select.options, function (option) {
        return option.value === '';
      });
      var modal = select.closest('.modal');
      var options = {
        width: '100%',
        dropdownParent: modal ? window.jQuery(modal) : window.jQuery(document.body)
      };

      if (emptyOption) {
        options.placeholder = emptyOption.textContent || 'Select';
        options.allowClear = !select.required;
      }

      window.jQuery(select).select2(options);
      select.dataset.appSelect2Initialized = '1';

      if (select.dataset.appSelect2ChangeBridge !== '1') {
        window.jQuery(select).on('change.appSelect2Bridge', function (event) {
          if (event.originalEvent) { return; }
          select.dispatchEvent(new Event('change', { bubbles: true }));
        });
        select.dataset.appSelect2ChangeBridge = '1';
      }
    });
  };

  forms.refreshSelect = function (select) {
    select = _el(select);
    if (!select || !window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) { return; }
    if (select.dataset.appSelect2Initialized === '1') {
      window.jQuery(select).trigger('change.select2');
      return;
    }
    forms.enhanceSelects(select.parentNode || document);
  };


  /* =======================================================================
   * APP.TABLES
   * ===================================================================== */
  var tables = {};

  /**
   * Reusable WorkEddy advanced table.
   * Handles API loading, client filters, sorting, pagination, row selection,
   * bulk-action bars, empty/error rows, and page-specific row rendering.
   */
  function AdvancedTable(opts) {
    opts = opts || {};
    this.card = _el(opts.card);
    this.tbody = _el(opts.tbody);
    this.endpoint = opts.endpoint || (this.card ? this.card.getAttribute('data-endpoint') : '');
    this.records = [];
    this.filteredRecords = [];
    this.currentPage = 1;
    this.pageSize = parseInt(opts.pageSize || 10, 10);
    this.pageSizeOptions = Array.isArray(opts.pageSizeOptions) && opts.pageSizeOptions.length
      ? opts.pageSizeOptions.map(function (size) { return parseInt(size, 10); }).filter(function (size) { return size > 0; })
      : [10, 25, 50, 100];
    this.sortBy = opts.defaultSort || opts.sortBy || '';
    this.sortDir = opts.sortDir || 'asc';
    this.colspan = parseInt(opts.colspan || 1, 10);
    this.filters = opts.filters || {};
    this.renderRow = opts.renderRow || function () { return ''; };
    this.filterRecord = opts.filterRecord || opts.filterLogic || null;
    this.sortValue = opts.sortValue || null;
    this.extractRecords = opts.extractRecords || _advancedTableExtractRecords;
    this.afterLoad = opts.afterLoad || null;
    this.afterRender = opts.afterRender || opts.onRenderComplete || null;
    this.emptyTitle = opts.emptyTitle || 'No records found';
    this.emptySubtitle = opts.emptySubtitle || 'No active records match the current filters.';
    this.loadingText = opts.loadingText || 'Loading...';
    this.resultCountEl = _el(opts.resultCount || opts.resultCountEl) || (this.card ? this.card.querySelector('[data-table-result-count], [id$="-result-count"]') : null);
    this.paginationEl = _el(opts.pagination || opts.paginationEl) || (this.card ? this.card.querySelector('[data-table-pagination], [id$="-pagination"]') : null);
    this.selectAllEl = _el(opts.selectAll) || (this.card ? this.card.querySelector('.table-select-all') : null);
    this.bulkBarEl = _el(opts.bulkBar || opts.bulkBarEl) || (this.card ? this.card.querySelector('[data-table-bulk-bar], [id$="-bulk-bar"]') : null);
    this.selectedCountEl = _el(opts.selectedCount || opts.selectedCountEl) || (this.card ? this.card.querySelector('[data-table-selected-count], [id$="-selected-count"]') : null);
    this.pageSizeEl = _el(opts.pageSizeEl) || (this.card ? this.card.querySelector('[data-table-page-size], [id$="-page-size"]') : null);
    this.getRecordKey = typeof opts.getRecordKey === 'function' ? opts.getRecordKey : null;
    this.selectedKeys = {};
    this.lastRenderedRecords = [];

    if (this.card && this.tbody) {
      this.ensureControls();
      this.init();
    }
  }

  AdvancedTable.prototype.ensureControls = function () {
    if (!this.card) { return; }

    if (!this.paginationEl) {
      var footer = this.card.querySelector('.card-footer');
      if (footer) {
        this.paginationEl = document.createElement('ul');
        this.paginationEl.className = 'pagination m-0 ms-auto';
        this.paginationEl.setAttribute('data-table-pagination', 'auto');
        footer.appendChild(this.paginationEl);
      }
    }

    if (!this.pageSizeEl && this.paginationEl && this.paginationEl.parentNode) {
      this.pageSizeEl = document.createElement('select');
      this.pageSizeEl.className = 'form-select form-select-sm w-auto me-3';
      this.pageSizeEl.setAttribute('data-table-page-size', 'auto');
      this.paginationEl.parentNode.insertBefore(this.pageSizeEl, this.paginationEl);
    }

    if (this.pageSizeEl && !this.pageSizeEl.options.length) {
      var self = this;
      this.pageSizeOptions.forEach(function (size) {
        var option = document.createElement('option');
        option.value = String(size);
        option.textContent = String(size) + ' / page';
        if (size === self.pageSize) {
          option.selected = true;
        }
        self.pageSizeEl.appendChild(option);
      });
    }
  };

  AdvancedTable.prototype.init = function () {
    var self = this;

    if (self.selectAllEl) {
      self.selectAllEl.addEventListener('change', function () {
        self.filteredRecords.forEach(function (record) {
          var key = self.recordKey(record);
          if (!key) { return; }
          self.selectedKeys[key] = self.selectAllEl.checked;
        });
        self.syncRenderedSelection();
        self.updateBulkBar();
      });
    }

    self.tbody.addEventListener('change', function (event) {
      if (!event.target || !event.target.classList.contains('table-selectable-check')) { return; }
      self.setRowSelected(event.target, event.target.checked);
      self.updateSelectAll();
      self.updateBulkBar();
    });

    self.tbody.addEventListener('click', function (event) {
      var row = event.target && event.target.closest ? event.target.closest('tr') : null;
      if (!row || event.target.closest('a, button, input, select, textarea, label, .dropdown-menu')) { return; }
      var checkbox = row.querySelector('.table-selectable-check');
      if (!checkbox) { return; }
      checkbox.checked = !checkbox.checked;
      checkbox.dispatchEvent(new Event('change', { bubbles: true }));
    });

    self.card.querySelectorAll('.table-sort').forEach(function (button) {
      button.addEventListener('click', function () {
        var field = button.getAttribute('data-sort') || '';
        if (self.sortBy === field) {
          self.sortDir = self.sortDir === 'asc' ? 'desc' : 'asc';
        } else {
          self.sortBy = field;
          self.sortDir = 'asc';
        }
        self.sort();
        self.render();
      });
    });

    Object.keys(self.filters).forEach(function (id) {
      var filterEl = _el('#' + id) || _el(id);
      if (!filterEl) { return; }
      var handler = _debounce(function () { self.applyFilters(); }, filterEl.type === 'search' || filterEl.type === 'text' ? 200 : 0);
      filterEl.addEventListener('input', handler);
      filterEl.addEventListener('change', handler);
    });

    if (self.pageSizeEl) {
      self.pageSizeEl.addEventListener('change', function () {
        var nextSize = parseInt(self.pageSizeEl.value, 10);
        if (!nextSize || nextSize === self.pageSize) { return; }
        self.pageSize = nextSize;
        self.currentPage = 1;
        self.render();
      });
    }

    self.load();
  };

  AdvancedTable.prototype.load = function (params) {
    var self = this;
    if (!self.endpoint) { return Promise.resolve([]); }
    self.renderLoading();

    return api.get(self.endpoint, params || {})
      .then(function (res) {
        if (!res.ok && String(res.status || '').toLowerCase() !== 'ok') {
          throw new Error(res.message || 'Failed loading records.');
        }
        self.records = self.extractRecords(res);
        if (self.afterLoad) { self.afterLoad(self.records, res, self); }
        self.applyFilters();
        return self.records;
      })
      .catch(function (err) {
        self.renderError(err.message || 'Failed loading records.');
        return [];
      });
  };

  AdvancedTable.prototype.reload = function () {
    return this.load();
  };

  AdvancedTable.prototype.applyFilters = function () {
    var self = this;
    var values = {};
    Object.keys(self.filters).forEach(function (id) {
      var filterEl = _el('#' + id) || _el(id);
      values[self.filters[id] || id] = filterEl ? filterEl.value : '';
      values[id] = filterEl ? filterEl.value : '';
    });

    self.currentPage = 1;
    self.filteredRecords = self.filterRecord
      ? self.records.filter(function (record) { return self.filterRecord(record, values, self); })
      : self.records.slice();
    self.sort();
    self.render();
  };

  AdvancedTable.prototype.sort = function () {
    var self = this;
    if (!self.sortBy) { return; }

    self.filteredRecords.sort(function (a, b) {
      var aValue = self.sortValue ? self.sortValue(a, self.sortBy) : a[self.sortBy];
      var bValue = self.sortValue ? self.sortValue(b, self.sortBy) : b[self.sortBy];

      if (aValue === null || aValue === undefined) { aValue = ''; }
      if (bValue === null || bValue === undefined) { bValue = ''; }
      if (typeof aValue === 'string') { aValue = aValue.toLowerCase(); }
      if (typeof bValue === 'string') { bValue = bValue.toLowerCase(); }
      if (aValue === bValue) { return 0; }
      var result = aValue > bValue ? 1 : -1;
      return self.sortDir === 'asc' ? result : -result;
    });
  };

  AdvancedTable.prototype.render = function () {
    var self = this;

    if (self.filteredRecords.length === 0) {
      if (self.selectAllEl) {
        self.selectAllEl.checked = false;
        self.selectAllEl.indeterminate = false;
      }
      self.lastRenderedRecords = [];
      self.updateBulkBar();
      self.renderEmpty();
      return;
    }

    var total = self.filteredRecords.length;
    var pages = Math.max(1, Math.ceil(total / self.pageSize));
    if (self.currentPage > pages) { self.currentPage = pages; }
    var start = (self.currentPage - 1) * self.pageSize;
    var end = Math.min(start + self.pageSize, total);
    var pageRecords = self.filteredRecords.slice(start, end);
    self.lastRenderedRecords = pageRecords;

    self.tbody.innerHTML = pageRecords.map(function (record, index) {
      return self.renderRow(record, self, index);
    }).join('');

    if (self.resultCountEl) {
      self.resultCountEl.textContent = 'Showing ' + (start + 1) + ' to ' + end + ' of ' + total + ' records';
    }
    self.syncRenderedSelection();
    self.renderPagination(pages);
    _initTooltips(self.tbody);
    if (self.afterRender) { self.afterRender(self); }
    self.updateSelectAll();
    self.updateBulkBar();
  };

  AdvancedTable.prototype.renderLoading = function () {
    this.tbody.innerHTML = '<tr><td colspan="' + this.colspan + '" class="text-center text-secondary py-5">' +
      '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' +
      _escHtml(this.loadingText) + '</td></tr>';
  };

  AdvancedTable.prototype.renderEmpty = function () {
    this.tbody.innerHTML = '<tr><td colspan="' + this.colspan + '" class="text-center text-secondary py-5">' +
      '<div class="my-0"><p class="mb-1 fw-medium text-body">' + _escHtml(this.emptyTitle) + '</p>' +
      '<p class="mb-0 text-secondary small">' + _escHtml(this.emptySubtitle) + '</p></div></td></tr>';
    if (this.resultCountEl) { this.resultCountEl.textContent = '0 total records.'; }
    if (this.paginationEl) { this.paginationEl.innerHTML = ''; }
  };

  AdvancedTable.prototype.renderError = function (message) {
    this.tbody.innerHTML = '<tr><td colspan="' + this.colspan + '" class="text-center py-5">' +
      '<div class="alert alert-danger mb-0 d-inline-block">' + _escHtml(message) + '</div></td></tr>';
    if (this.resultCountEl) { this.resultCountEl.textContent = 'Unable to load records.'; }
    if (this.paginationEl) { this.paginationEl.innerHTML = ''; }
  };

  AdvancedTable.prototype.renderPagination = function (pages) {
    var self = this;
    if (!self.paginationEl) { return; }
    var html = '';
    var prevDisabled = self.currentPage === 1 ? ' disabled' : '';
    html += '<li class="page-item' + prevDisabled + '"><a class="page-link" href="#" data-page="' + (self.currentPage - 1) + '" aria-label="Previous page">&lsaquo;</a></li>';

    var startPage = Math.max(1, self.currentPage - 2);
    var endPage = Math.min(pages, self.currentPage + 2);

    if (startPage > 1) {
      html += '<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>';
      if (startPage > 2) {
        html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
      }
    }

    for (var page = startPage; page <= endPage; page++) {
      html += '<li class="page-item' + (page === self.currentPage ? ' active' : '') + '"><a class="page-link" href="#" data-page="' + page + '">' + page + '</a></li>';
    }

    if (endPage < pages) {
      if (endPage < pages - 1) {
        html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
      }
      html += '<li class="page-item"><a class="page-link" href="#" data-page="' + pages + '">' + pages + '</a></li>';
    }

    var nextDisabled = self.currentPage === pages ? ' disabled' : '';
    html += '<li class="page-item' + nextDisabled + '"><a class="page-link" href="#" data-page="' + (self.currentPage + 1) + '" aria-label="Next page">&rsaquo;</a></li>';
    self.paginationEl.innerHTML = html;
    self.paginationEl.querySelectorAll('.page-link').forEach(function (link) {
      link.addEventListener('click', function (event) {
        event.preventDefault();
        var page = parseInt(link.getAttribute('data-page'), 10);
        if (page >= 1 && page <= pages && page !== self.currentPage) {
          self.currentPage = page;
          self.render();
        }
      });
    });
  };

  AdvancedTable.prototype.setRowSelected = function (check, selected) {
    var row = check.closest('tr');
    var key = check.getAttribute('value') || check.dataset.recordKey || check.getAttribute('data-uuid') || check.getAttribute('data-id') || '';
    if (key) {
      this.selectedKeys[key] = selected;
    }
    if (!row) { return; }
    row.classList.toggle('selected', selected);
    row.classList.toggle('active', selected);
  };

  AdvancedTable.prototype.updateSelectAll = function () {
    if (!this.selectAllEl) { return; }
    var keys = this.filteredRecords.map(this.recordKey.bind(this)).filter(Boolean);
    var selected = keys.filter(function (key) { return !!this.selectedKeys[key]; }, this);
    this.selectAllEl.checked = keys.length > 0 && selected.length === keys.length;
    this.selectAllEl.indeterminate = selected.length > 0 && selected.length < keys.length;
  };

  AdvancedTable.prototype.updateBulkBar = function () {
    var count = Object.keys(this.selectedKeys).filter(function (key) { return !!this.selectedKeys[key]; }, this).length;
    if (this.bulkBarEl) { this.bulkBarEl.classList.toggle('d-none', count === 0); }
    if (this.selectedCountEl) { this.selectedCountEl.textContent = count + ' selected'; }
  };

  AdvancedTable.prototype.getSelectedKeys = function () {
    return Object.keys(this.selectedKeys).filter(function (key) { return !!this.selectedKeys[key]; }, this);
  };

  AdvancedTable.prototype.getSelectedRecords = function () {
    var self = this;
    return this.records.filter(function (record) {
      var key = self.recordKey(record);
      return key && !!self.selectedKeys[key];
    });
  };

  AdvancedTable.prototype.clearSelection = function () {
    if (this.selectAllEl) { this.selectAllEl.checked = false; }
    this.selectedKeys = {};
    this.tbody.querySelectorAll('.table-selectable-check:checked').forEach(function (check) {
      check.checked = false;
      var row = check.closest('tr');
      if (row) {
        row.classList.remove('selected');
        row.classList.remove('active');
      }
    });
    this.updateBulkBar();
  };

  AdvancedTable.prototype.recordKey = function (record) {
    if (!record || typeof record !== 'object') { return ''; }
    if (this.getRecordKey) { return String(this.getRecordKey(record) || ''); }
    return String(record.uuid || record.id || record.key || record.reference_number || '');
  };

  AdvancedTable.prototype.syncRenderedSelection = function () {
    var self = this;
    this.tbody.querySelectorAll('.table-selectable-check').forEach(function (check) {
      var row = check.closest('tr');
      var key = check.getAttribute('value') || check.dataset.recordKey || check.getAttribute('data-uuid') || check.getAttribute('data-id') || '';
      if (!key && row && row.dataset) {
        key = row.dataset.recordKey || row.dataset.uuid || row.dataset.id || '';
      }
      if (!key && row) {
        var index = Array.prototype.indexOf.call(self.tbody.querySelectorAll('tr'), row);
        var record = self.lastRenderedRecords[index];
        key = self.recordKey(record);
      }
      if (!key) { return; }
      check.checked = !!self.selectedKeys[key];
      self.setRowSelected(check, check.checked);
    });
  };

  tables.AdvancedTable = AdvancedTable;

  tables.createAdvanced = function (opts) {
    return new AdvancedTable(opts);
  };

  tables.extractRecords = _advancedTableExtractRecords;

  tables.actionDropdown = function (items) {
    var itemsHtml = '';
    if (Array.isArray(items)) {
      itemsHtml = items.map(function (item) {
        if (typeof item === 'string') { return item; }
        if (!item || typeof item !== 'object') { return ''; }
        var cls = 'dropdown-item' + (item.class ? ' ' + item.class : '');
        var href = item.href ? _escHtml(item.href) : '#';
        var onclick = '';
        if (item.onclick) {
          var escVal = String(item.onclick)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
          onclick = ' onclick="' + escVal + '"';
        }
        return '<a class="' + cls + '" href="' + href + '"' + onclick + '>' + _escHtml(item.label || '') + '</a>';
      }).join('');
    } else {
      itemsHtml = items || '';
    }
    return '<div class="d-flex justify-content-end"><div class="dropdown">' +
      '<a href="#" class="btn btn-sm btn-icon rounded-pill" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-label="Actions">' +
      '<i class="bi bi-three-dots-vertical"></i>' +
      '</a><div class="dropdown-menu dropdown-menu-end">' + itemsHtml + '</div></div></div>';
  };

  function _advancedTableExtractRecords(res) {
    var raw = res ? res.data : [];
    if (Array.isArray(raw)) { return raw; }
    if (raw && typeof raw === 'object') {
      var key = Object.keys(raw).find(function (name) { return Array.isArray(raw[name]); });
      return key ? raw[key] : [];
    }
    return [];
  }


  /* =======================================================================
   * APP.MODALS
   * ===================================================================== */
  var modals = {};
  var confirmModalState = {
    opts: null,
    action: null
  };

  /**
   * Open a Bootstrap modal.
   * @param {HTMLElement|string} selector
   */
  modals.open = function (selector) {
    var el = _el(selector);
    if (!el) { return; }
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) { return; }
    var modal = bootstrap.Modal.getOrCreateInstance(el);
    modal.show();
  };

  /**
   * Close / hide a Bootstrap modal.
   * @param {HTMLElement|string} selector
   */
  modals.close = function (selector) {
    var el = _el(selector);
    if (!el) { return; }
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) { return; }
    var modal = bootstrap.Modal.getInstance(el);
    if (modal) { modal.hide(); }
  };

  /**
   * Reset a modal's inner form(s) and clear validation errors.
   * @param {HTMLElement|string} selector
   */
  modals.reset = function (selector) {
    var el = _el(selector);
    if (!el) { return; }
    el.querySelectorAll('form').forEach(function (f) { forms.reset(f); });
    // Clear any alerts inside the modal
    ui.clearAlert(el);
  };

  function _confirmIcon(icon) {
    var icons = {
      warning: { type: 'warning', color: 'text-warning' },
      error: { type: 'error', color: 'text-danger' },
      success: { type: 'success', color: 'text-success' },
      info: { type: 'info', color: 'text-info' },
      question: { type: 'info', color: 'text-primary' }
    };
    return icons[icon] || icons.warning;
  }

  function _ensureConfirmModal() {
    var existing = document.getElementById('app-confirm-modal');
    if (existing) { return existing; }

    var html = '' +
      '<div class="modal fade" id="app-confirm-modal" tabindex="-1" role="dialog" aria-hidden="true">' +
      '<div class="modal-dialog modal-sm modal-dialog-centered" role="document">' +
      '<div class="modal-content">' +
      '<div class="modal-header border-0 pb-0">' +
      '<h5 id="app-confirm-modal-title" class="modal-title">Are you sure?</h5>' +
      '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
      '</div>' +
      '<div class="modal-body text-center pt-2 pb-4" id="app-confirm-icon-container">' +
      '<div class="text-secondary" data-app-confirm-text>This action cannot be undone.</div>' +
      '</div>' +
      '<div class="modal-footer">' +
      '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" data-app-confirm-cancel>Cancel</button>' +
      '<button type="button" class="btn btn-danger" data-bs-dismiss="modal" data-app-confirm-action>Confirm</button>' +
      '</div>' +
      '</div>' +
      '</div>' +
      '</div>';

    document.body.insertAdjacentHTML('beforeend', html);
    var modalEl = document.getElementById('app-confirm-modal');

    modalEl.querySelector('[data-app-confirm-action]').addEventListener('click', function () {
      if (confirmModalState.action) { return; }
      confirmModalState.action = 'confirm';
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
      var opts = confirmModalState.opts || {};
      var action = confirmModalState.action || 'cancel';
      confirmModalState.opts = null;
      confirmModalState.action = null;

      if (action === 'confirm') {
        if (opts.onConfirm) { opts.onConfirm(); }
        return;
      }

      if (opts.onCancel) {
        opts.onCancel();
      }
    });

    return modalEl;
  }

  /**
   * Show a confirmation dialog.
   * Uses a shared Sneat / Bootstrap modal when available, otherwise a plain confirm().
   *
   * @param {Object} opts
   * @param {string} opts.title
   * @param {string} opts.text
   * @param {string} [opts.confirmText] - Default "Confirm"
   * @param {string} [opts.cancelText]  - Default "Cancel"
   * @param {string} [opts.icon]        - warning, error, success, info, question
   * @param {Function} opts.onConfirm   - Called if user confirms
   * @param {Function} [opts.onCancel]  - Called if user cancels
   */
  modals.confirm = function (opts) {
    opts = opts || {};

    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
      var modalEl = _ensureConfirmModal();
      var icon = _confirmIcon(opts.icon || 'warning');
      var iconContainer = modalEl.querySelector('#app-confirm-icon-container');
      var titleEl = modalEl.querySelector('#app-confirm-modal-title');
      var textEl = modalEl.querySelector('[data-app-confirm-text]');
      var confirmBtn = modalEl.querySelector('[data-app-confirm-action]');
      var cancelBtn = modalEl.querySelector('[data-app-confirm-cancel]');

      confirmModalState.opts = opts;
      confirmModalState.action = null;

      titleEl.textContent = opts.title || 'Are you sure?';
      textEl.textContent = opts.text || '';
      confirmBtn.textContent = opts.confirmText || 'Confirm';
      cancelBtn.textContent = opts.cancelText || 'Cancel';

      var colorClass = icon.color.replace('text-', '');
      var existingSvg = iconContainer.querySelector('svg');
      if (existingSvg) existingSvg.remove();
      iconContainer.insertAdjacentHTML('afterbegin', _getIconSvg(icon.type, icon.color + ' icon-lg mb-2'));
      confirmBtn.className = 'btn btn-' + colorClass;

      bootstrap.Modal.getOrCreateInstance(modalEl).show();
      return;
    }

    // Fallback: native confirm
    var confirmed = window.confirm((opts.title || 'Are you sure?') + '\n' + (opts.text || ''));
    if (confirmed) {
      if (opts.onConfirm) { opts.onConfirm(); }
    } else {
      if (opts.onCancel) { opts.onCancel(); }
    }
  };


  /* =======================================================================
   * APP.UTILS
   * ===================================================================== */
  var utils = {};

  /**
   * Escape text before inserting it into HTML strings.
   *
   * @param {*} value
   * @returns {string}
   */
  utils.escapeHtml = function (value) {
    return _escHtml(value === null || value === undefined ? '' : String(value));
  };

  /**
   * Format a number as currency.
   *
   * @param {number|string} value
   * @param {string}       [currency] - Currency code, default 'NGN'
   * @returns {string}
   */
  utils.formatCurrency = function (value, currency) {
    var num = parseFloat(value);
    if (isNaN(num)) { return ''; }
    currency = currency || 'NGN';

    // Use Intl if available
    if (typeof Intl !== 'undefined' && Intl.NumberFormat) {
      try {
        return new Intl.NumberFormat('en-NG', {
          style: 'currency', currency: currency,
          minimumFractionDigits: 2, maximumFractionDigits: 2
        }).format(num);
      } catch (_) { /* fall through */ }
    }

    // Fallback
    var symbol = currency === 'NGN' ? 'â‚¦' : currency + ' ';
    return symbol + num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  };

  /**
   * Format a date value for display.
   *
   * @param {string|Date|number} value
   * @returns {string}  e.g. "24 Apr 2026"
   */
  utils.formatDate = function (value) {
    if (!value) { return ''; }
    var d = value instanceof Date ? value : new Date(value);
    if (isNaN(d.getTime())) { return String(value); }

    return d.toLocaleDateString('en-GB', {
      day: '2-digit', month: 'short', year: 'numeric'
    });
  };

  /**
   * Debounce a function call.
   *
   * @param {Function} fn
   * @param {number}   wait - Milliseconds
   * @returns {Function}
   */
  utils.debounce = function (fn, wait) {
    return _debounce(fn, wait);
  };

  /**
   * Format a byte count for display.
   *
   * @param {number|string} bytes
   * @returns {string}
   */
  utils.formatBytes = function (bytes) {
    bytes = parseInt(bytes, 10) || 0;
    if (bytes <= 0) { return '0 B'; }
    var units = ['B', 'KB', 'MB', 'GB', 'TB'];
    var i = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));
    return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
  };

  /**
   * Format a duration in seconds for compact display.
   *
   * @param {number|string} seconds
   * @returns {string}
   */
  utils.formatDuration = function (seconds) {
    seconds = parseInt(seconds, 10) || 0;
    if (!seconds) { return ''; }
    var h = Math.floor(seconds / 3600);
    var m = Math.floor((seconds % 3600) / 60);
    var s = seconds % 60;
    if (h > 0) { return h + 'h ' + m + 'm'; }
    if (m > 0) { return m + 'm ' + s + 's'; }
    return s + 's';
  };

  /**
   * Set text content when the element exists.
   *
   * @param {HTMLElement|string} target
   * @param {*} value
   */
  utils.setText = function (target, value) {
    target = _el(target);
    if (target) {
      target.textContent = value !== undefined && value !== null ? value : '';
    }
  };

  /**
   * Build a URL query string from an object.
   * Skips empty/null/undefined values.
   *
   * @param {Object} params
   * @returns {string}
   */
  utils.buildQuery = function (params) {
    return _buildQuery(params);
  };

  /**
   * Get the current CSRF token.
   * @returns {string}
   */
  utils.getCsrfToken = function () {
    return _getCsrfToken();
  };

  /**
   * Load html2pdf once and reuse it for invoice/status PDF exports.
   *
   * @returns {Promise<Function>}
   */
  utils.loadHtml2Pdf = function () {
    return _loadHtml2Pdf();
  };

  /**
   * Export a page element to PDF using the legacy invoice_final html2pdf defaults.
   *
   * @param {HTMLElement|string} target - Element, CSS selector, or element id.
   * @param {Object} [opts]
   * @returns {Promise<void>}
   */
  utils.downloadElementPdf = function (target, opts) {
    return _exportElementPdf(target, opts || {}, 'download');
  };

  /**
   * Generate a PDF from a page element and open the browser print flow.
   *
   * @param {HTMLElement|string} target - Element, CSS selector, or element id.
   * @param {Object} [opts]
   * @returns {Promise<void>}
   */
  utils.printElementPdf = function (target, opts) {
    return _exportElementPdf(target, opts || {}, 'print');
  };

  /**
   * Backward-compatible invoice PDF helper used by invoice pages.
   *
   * @param {HTMLElement|string} target
   * @param {string} reference
   * @param {Object} [opts]
   * @returns {Promise<void>}
   */
  utils.downloadInvoicePdf = function (target, reference, opts) {
    opts = opts || {};
    opts.filename = opts.filename || ('invoice_' + _pdfSafeName(reference || 'document') + '.pdf');
    opts.successMessage = opts.successMessage || 'Invoice PDF downloaded.';
    return _exportElementPdf(target, opts, 'download');
  };

  /**
   * Alias for places that describe the action as print-to-PDF.
   */
  utils.printToPdf = function (target, opts) {
    return _exportElementPdf(target, opts || {}, 'print');
  };

  /**
   * Bind buttons/links with data-pdf-target so pages do not duplicate html2pdf code.
   */
  utils.bindPdfDownloadButtons = function (root) {
    root = root || document;
    var buttons = root.querySelectorAll('[data-pdf-target]');
    buttons.forEach(function (button) {
      if (button.dataset.pdfBound === '1') { return; }
      button.dataset.pdfBound = '1';
      button.addEventListener('click', function (event) {
        event.preventDefault();
        var opts = _pdfOptionsFromButton(button);
        var action = button.getAttribute('data-pdf-action') || 'download';
        _exportElementPdf(button.getAttribute('data-pdf-target'), opts, action).catch(function () { });
      });
    });
  };

  utils.pageSpinner = function (root) {
    root = root || document;
    const MINIMUM_LOADING_TIME = 200;
    const loadingStart = Date.now();

    function hide() {
      const remaining = Math.max(0, MINIMUM_LOADING_TIME - (Date.now() - loadingStart));
      setTimeout(() => {
        const el = root.querySelector('.loading-container');
        if (el) el.style.display = 'none';
      }, remaining);
    }

    if (document.readyState === 'complete') {
      hide();
    } else {
      window.addEventListener('load', hide, { once: true });
    }
  };



  /* =======================================================================
   * PRIVATE HELPERS
   * ===================================================================== */

  /** Detect form-validation style responses. */
  function _isValidationResponse(res) {
    if (!res) { return false; }
    return res.status === 400 || res.status === 422 || !!res.errors;
  }

  /** Convert a validation error value into displayable messages. */
  function _normaliseErrorMessages(error) {
    if (Array.isArray(error)) {
      return error.map(function (msg) { return String(msg || '').trim(); }).filter(Boolean);
    }
    if (error && typeof error === 'object') {
      return Object.keys(error).map(function (key) { return String(error[key] || '').trim(); }).filter(Boolean);
    }
    var message = String(error || '').trim();
    return message ? [message] : [];
  }

  /** Find a form control by server-side validation key without relying on CSS escaping. */
  function _findFormField(form, field) {
    var controls = form.querySelectorAll('input, select, textarea');
    for (var i = 0; i < controls.length; i++) {
      var control = controls[i];
      if (
        control.name === field ||
        control.name === field + '[]' ||
        control.id === field ||
        control.getAttribute('data-field') === field
      ) {
        return control;
      }
    }
    return null;
  }

  /** Resolve a selector or element to an element */
  function _el(selectorOrEl) {
    if (!selectorOrEl) { return null; }
    if (typeof selectorOrEl === 'string') {
      return document.querySelector(selectorOrEl);
    }
    return selectorOrEl;
  }

  /** Resolve a PDF target from an element, selector, or plain id. */
  function _pdfTarget(target) {
    if (!target) { return null; }
    if (typeof target !== 'string') { return target; }
    var byId = document.getElementById(target);
    if (byId) { return byId; }
    try {
      return document.querySelector(target);
    } catch (_) {
      return null;
    }
  }

  /** Build options from a declarative data-pdf-* trigger. */
  function _pdfOptionsFromButton(button) {
    var opts = {
      button: button,
      filename: button.getAttribute('data-pdf-filename') || undefined,
      loadingText: button.getAttribute('data-pdf-loading-text') || 'Downloading...',
      successMessage: button.getAttribute('data-pdf-success-message') || undefined,
      errorMessage: button.getAttribute('data-pdf-error-message') || undefined
    };
    var margin = _parsePdfMargin(button.getAttribute('data-pdf-margin'));
    if (margin !== undefined) { opts.margin = margin; }
    var background = button.getAttribute('data-pdf-background');
    if (background) { opts.temporaryStyles = { backgroundColor: background }; }
    var hideSelectors = button.getAttribute('data-pdf-hide');
    if (hideSelectors) { opts.hideSelectors = hideSelectors.split(',').map(function (item) { return item.trim(); }).filter(Boolean); }
    return opts;
  }

  /** Keep generated filenames filesystem-safe. */
  function _pdfSafeName(value) {
    return String(value || 'document')
      .trim()
      .replace(/\.pdf$/i, '')
      .replace(/[\\/:*?"<>|]+/g, '_')
      .replace(/\s+/g, '_')
      .replace(/[^A-Za-z0-9._-]+/g, '_')
      .replace(/_+/g, '_') || 'document';
  }

  function _pdfFilename(value) {
    var filename = _pdfSafeName(value || 'document');
    return /\.pdf$/i.test(filename) ? filename : filename + '.pdf';
  }

  function _parsePdfMargin(value) {
    if (value === null || value === undefined || value === '') { return undefined; }
    var raw = String(value).trim();
    if (raw.charAt(0) === '[') {
      try { return JSON.parse(raw); } catch (_) { return undefined; }
    }
    if (raw.indexOf(',') !== -1) {
      return raw.split(',').map(function (part) { return parseFloat(part.trim()); });
    }
    var num = parseFloat(raw);
    return isNaN(num) ? undefined : num;
  }

  function _defaultPdfOptions(filename) {
    return {
      margin: 0,
      filename: _pdfFilename(filename || 'document.pdf'),
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
        scrollY: 0,
        scrollX: 0,
        logging: false,
        letterRendering: true,
        onclone: function (clonedDocument) {
          _preparePdfClone(clonedDocument);
        }
      },
      jsPDF: {
        unit: 'mm',
        format: 'a4',
        orientation: 'portrait',
        compress: true
      }
    };
  }

  function _mergePdfOptions(base, next) {
    next = next || {};
    Object.keys(next).forEach(function (key) {
      var value = next[key];
      if (
        value &&
        typeof value === 'object' &&
        !Array.isArray(value) &&
        base[key] &&
        typeof base[key] === 'object' &&
        !Array.isArray(base[key])
      ) {
        _mergePdfOptions(base[key], value);
      } else if (value !== undefined) {
        base[key] = value;
      }
    });
    return base;
  }

  function _html2PdfOptions(opts) {
    var pdfOptions = _defaultPdfOptions(opts.filename);
    ['margin', 'filename', 'image', 'html2canvas', 'jsPDF', 'pagebreak'].forEach(function (key) {
      if (opts[key] !== undefined) {
        var next = {};
        next[key] = key === 'filename' ? _pdfFilename(opts[key]) : opts[key];
        _mergePdfOptions(pdfOptions, next);
      }
    });
    if (opts.options) {
      _mergePdfOptions(pdfOptions, opts.options);
    }
    pdfOptions.filename = _pdfFilename(pdfOptions.filename);
    return pdfOptions;
  }

  function _applyTemporaryStyles(element, styles) {
    if (styles === false) {
      return function () { };
    }
    styles = Object.assign({ backgroundColor: '#fff' }, styles || {});
    var original = {};
    Object.keys(styles).forEach(function (key) {
      original[key] = element.style[key];
      element.style[key] = styles[key];
    });
    return function () {
      Object.keys(original).forEach(function (key) {
        element.style[key] = original[key];
      });
    };
  }

  function _hideForPdf(selectors) {
    var hidden = [];
    (selectors || []).forEach(function (selector) {
      document.querySelectorAll(selector).forEach(function (node) {
        hidden.push({ node: node, display: node.style.display });
        node.style.display = 'none';
      });
    });
    return function () {
      hidden.forEach(function (entry) {
        entry.node.style.display = entry.display;
      });
    };
  }

  function _loadHtml2Pdf() {
    if (typeof window.html2pdf !== 'undefined') {
      return Promise.resolve(window.html2pdf);
    }
    if (html2PdfConfig.promise) {
      return html2PdfConfig.promise;
    }
    html2PdfConfig.promise = new Promise(function (resolve, reject) {
      _appendHtml2PdfScript(html2PdfConfig.url, html2PdfConfig.integrity, resolve, function () {
        _appendHtml2PdfScript(html2PdfConfig.fallbackUrl, '', resolve, function () {
          html2PdfConfig.promise = null;
          reject(new Error('Unable to load html2pdf.'));
        });
      });
    });
    return html2PdfConfig.promise;
  }

  function _appendHtml2PdfScript(url, integrity, resolve, reject) {
    var script = document.createElement('script');
    script.src = url;
    script.async = true;
    script.crossOrigin = 'anonymous';
    script.referrerPolicy = 'no-referrer';
    if (integrity) { script.integrity = integrity; }
    script.onload = function () {
      if (typeof window.html2pdf !== 'undefined') {
        resolve(window.html2pdf);
      } else {
        reject();
      }
    };
    script.onerror = reject;
    document.head.appendChild(script);
  }

  function _preparePdfClone(clonedDocument) {
    if (!clonedDocument || !clonedDocument.body) { return; }
    var style = clonedDocument.createElement('style');
    style.textContent = [
      '*{box-shadow:none!important;text-shadow:none!important;animation:none!important;transition:none!important;}',
      'body{background:#fff!important;}',
      '.card,.invoice-preview-card{background:#fff!important;border-color:#e4e6e8!important;}',
      '.bg-primary{background:#008000!important;color:#fff!important;}',
      '.bg-lighter{background:#f8f9fa!important;}',
      '.text-primary{color:#008000!important;}',
      '.text-danger{color:#dc3545!important;}',
      '.text-success{color:#198754!important;}',
      '.text-warning{color:#b7791f!important;}',
      '.text-muted{color:#6c757d!important;}',
      '.text-dark,.text-heading{color:#212529!important;}',
      '.bg-label-success,.text-bg-success{background:#dff7d5!important;color:#1f7a1f!important;}',
      '.bg-label-warning,.text-bg-warning{background:#fff2d6!important;color:#8a5a00!important;}',
      '.bg-label-danger,.text-bg-danger{background:#ffe0db!important;color:#b02a37!important;}',
      '.bg-label-secondary,.text-bg-secondary{background:#ebeef0!important;color:#4e5965!important;}',
      '.timeline-point-primary,.timeline-point-success{background:#008000!important;}',
      '.timeline-point-warning{background:#ffab00!important;}',
      '.timeline-point-danger{background:#dc3545!important;}'
    ].join('\n');
    clonedDocument.head.appendChild(style);
  }

  function _openPdfPrint(pdfObj, filename) {
    var pdfBlob = pdfObj.output('blob');
    var pdfUrl = URL.createObjectURL(pdfBlob);
    var printWindow = window.open(pdfUrl, '_blank');

    if (printWindow) {
      printWindow.onload = function () {
        printWindow.focus();
        printWindow.print();
        printWindow.onafterprint = function () {
          printWindow.close();
          URL.revokeObjectURL(pdfUrl);
        };
        setTimeout(function () {
          URL.revokeObjectURL(pdfUrl);
        }, 60000);
      };
      return;
    }

    var link = document.createElement('a');
    link.href = pdfUrl;
    link.download = _pdfFilename(filename || 'document.pdf');
    link.click();
    setTimeout(function () {
      URL.revokeObjectURL(pdfUrl);
    }, 1000);
  }

  function _exportElementPdf(target, opts, action) {
    opts = opts || {};
    var element = _pdfTarget(target);
    if (!element) {
      notify.error(opts.errorMessage || 'PDF content is not available.');
      return Promise.reject(new Error('PDF target not found.'));
    }

    var button = opts.button ? _el(opts.button) : null;
    var restoreStyles = _applyTemporaryStyles(element, opts.temporaryStyles);
    var restoreHidden = _hideForPdf(opts.hideSelectors);
    if (button) {
      ui.setButtonLoading(button, true, opts.loadingText || (action === 'print' ? 'Preparing...' : 'Downloading...'));
    }

    return _loadHtml2Pdf()
      .then(function (html2pdf) {
        var pdfOptions = _html2PdfOptions(opts);
        if (action === 'print') {
          return html2pdf().set(pdfOptions).from(element).toPdf().get('pdf').then(function (pdfObj) {
            _openPdfPrint(pdfObj, pdfOptions.filename);
          });
        }
        return html2pdf().set(pdfOptions).from(element).save();
      })
      .then(function () {
        restoreHidden();
        restoreStyles();
        if (button) { ui.setButtonLoading(button, false); }
        if (opts.successMessage) { notify.success(opts.successMessage); }
      })
      .catch(function (err) {
        restoreHidden();
        restoreStyles();
        if (button) { ui.setButtonLoading(button, false); }
        if (window.console && console.error) {
          console.error('PDF generation failed:', err);
        }
        notify.error(opts.errorMessage || 'PDF generation failed.');
        throw err;
      });
  }

  /** Escape a value for a quoted CSS attribute selector */
  function _escAttr(str) {
    return String(str || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"');
  }

  /** Escape HTML for safe insertion */
  function _escHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str || ''));
    return div.innerHTML;
  }

  /** Capitalise first letter */
  function _ucFirst(str) {
    if (!str) { return ''; }
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  /** Internal debounce */
  function _debounce(fn, wait) {
    var timer;
    return function () {
      var ctx = this, args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function () { fn.apply(ctx, args); }, wait || 300);
    };
  }

  /** Build query string from params object */
  function _buildQuery(params) {
    if (!params || typeof params !== 'object') { return ''; }
    var parts = [];
    Object.keys(params).forEach(function (key) {
      var val = params[key];
      if (val === null || val === undefined || val === '') { return; }
      if (Array.isArray(val)) {
        val.forEach(function (v) {
          parts.push(encodeURIComponent(key + '[]') + '=' + encodeURIComponent(v));
        });
      } else {
        parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(val));
      }
    });
    return parts.join('&');
  }

  /** Check if a form has any file inputs with files selected */
  function _hasFileInput(form) {
    var inputs = form.querySelectorAll('input[type="file"]');
    for (var i = 0; i < inputs.length; i++) {
      if (inputs[i].files && inputs[i].files.length > 0) { return true; }
    }
    return false;
  }

  /** Get a URL parameter by name */
  function _getUrlParam(url, name) {
    try {
      var u = new URL(url, window.location.origin);
      return u.searchParams.get(name);
    } catch (_) {
      return null;
    }
  }

  /** Re-initialise Bootstrap tooltips inside a container */
  function _initTooltips(container) {
    if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) { return; }
    if (container.nodeType === 1 && container.getAttribute('data-bs-toggle') === 'tooltip') {
      if (!bootstrap.Tooltip.getInstance(container)) {
        new bootstrap.Tooltip(container);
      }
    }
    var tips = container.querySelectorAll('[data-bs-toggle="tooltip"]');
    tips.forEach(function (el) {
      if (!bootstrap.Tooltip.getInstance(el)) {
        new bootstrap.Tooltip(el);
      }
    });
  }

  /* =======================================================================
   * EXPOSE GLOBAL NAMESPACE
   * ===================================================================== */
  utils.initTooltips = function (container) {
    _initTooltips(container || document);
  };

  window.App = {
    config: config,
    api: api,
    notify: notify,
    ui: ui,
    forms: forms,
    tables: tables,
    modals: modals,
    utils: utils
  };
  window.App.utils.pageSpinner();
  document.addEventListener('DOMContentLoaded', function () {
    _initTooltips(document);
    window.App.forms.enhanceSelects(document);
    window.App.utils.bindPdfDownloadButtons(document);
    initTenantSwitcher();



    if (typeof MutationObserver !== 'undefined') {
      var selectObserver = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
          if (mutation.type !== 'childList') { return; }
          if (mutation.target && mutation.target.matches && mutation.target.matches('select.form-select')) {
            window.App.forms.refreshSelect(mutation.target);
            return;
          }
          mutation.addedNodes.forEach(function (node) {
            if (node.nodeType === 1) {
              window.App.forms.enhanceSelects(node);
              _initTooltips(node);
            }
          });
        });
      });
      selectObserver.observe(document.body, { childList: true, subtree: true });
    }
  });

  function initTenantSwitcher() {
    var wrap = document.getElementById('tenantSwitcherWrap');
    var select = document.getElementById('tenantSwitcherSelect');
    if (!wrap || !select || !window.App || !window.App.api) { return; }

    window.App.api.get('/api/v1/iam/profile').then(function (result) {
      if (!result || !result.ok || !result.data || !Array.isArray(result.data.tenants)) { return; }
      if (result.data.tenants.length <= 1) { return; }

      select.innerHTML = '';
      result.data.tenants.forEach(function (tenant) {
        var option = document.createElement('option');
        option.value = tenant.id || '';
        option.textContent = tenant.organizationUuid
          ? ((tenant.organizationName || 'Organization') + ' (' + (tenant.roleName || tenant.roleSlug || 'Member') + ')')
          : ((tenant.roleName || tenant.roleSlug || 'Platform') + ' - Platform');
        if ((result.data.activeTenant && result.data.activeTenant.id) === tenant.id) {
          option.selected = true;
        }
        select.appendChild(option);
      });

      wrap.classList.remove('d-none');
      select.addEventListener('change', function () {
        var nextTenantId = select.value;
        select.disabled = true;
        window.App.api.post('/api/v1/iam/profile/tenant', { tenantId: nextTenantId }).then(function (response) {
          select.disabled = false;
          if (!response || !response.ok) {
            window.App.notify.error(response && response.message ? response.message : 'Unable to switch organization.');
            return;
          }
          window.location.reload();
        });
      });
    });
  }

  var _systemDialog = null;

  function _ensureBootstrap(callback) {
    if (typeof bootstrap !== 'undefined') {
      callback(bootstrap);
      return;
    }
    if (window._bootstrapLoadingPromise) {
      window._bootstrapLoadingPromise.then(function (bs) {
        callback(bs);
      })['catch'](function () {
        callback(null);
      });
      return;
    }
    window._bootstrapLoadingPromise = new Promise(function (resolve, reject) {
      var script = document.createElement('script');
      script.src = '/assets/vendor/bootstrap/js/bootstrap.bundle.min.js';
      script.async = true;
      script.onload = function () {
        resolve(window.bootstrap);
      };
      script.onerror = function () {
        reject(new Error('Failed to load Bootstrap'));
      };
      document.head.appendChild(script);
    });
    window._bootstrapLoadingPromise.then(function (bs) {
      callback(bs);
    })['catch'](function () {
      callback(null);
    });
  }

  function _initSystemDialog(bs) {
    if (_systemDialog) return _systemDialog;

    var modalEl = document.getElementById('systemUxDialogModal');
    if (!modalEl) return null;

    var titleEl  = modalEl.querySelector('[data-dialog-title]');
    var bodyEl   = modalEl.querySelector('[data-dialog-body]');
    var iconEl   = modalEl.querySelector('[data-dialog-icon]');
    var cancelEl = modalEl.querySelector('[data-dialog-cancel]');
    var okEl     = modalEl.querySelector('[data-dialog-ok]');

    var bsModal = new bs.Modal(modalEl, {
      backdrop: 'static',
      keyboard: false,
    });

    _systemDialog = {
      modalEl: modalEl,
      bsModal: bsModal,
      titleEl: titleEl,
      bodyEl: bodyEl,
      iconEl: iconEl,
      cancelEl: cancelEl,
      okEl: okEl,
      active: null,
    };

    if (cancelEl) {
      cancelEl.addEventListener('click', function () {
        if (!_systemDialog || !_systemDialog.active) return;
        var resolve = _systemDialog.active.resolve;
        _systemDialog.active = null;
        resolve(false);
        _systemDialog.bsModal.hide();
      });
    }

    if (okEl) {
      okEl.addEventListener('click', function () {
        if (!_systemDialog || !_systemDialog.active) return;
        var resolve = _systemDialog.active.resolve;
        _systemDialog.active = null;
        resolve(true);
        _systemDialog.bsModal.hide();
      });
    }

    modalEl.addEventListener('hidden.bs.modal', function () {
      if (_systemDialog && _systemDialog.active) {
        var resolve = _systemDialog.active.resolve;
        _systemDialog.active = null;
        resolve(false);
      }
    });

    return _systemDialog;
  }

  function _showSystemDialog(options) {
    options = options || {};
    var mode = options.mode || 'alert';
    var title = options.title || 'Notice';
    var message = options.message || '';
    var okText = options.okText || 'OK';
    var cancelText = options.cancelText || 'Cancel';
    var variant = options.variant || 'primary';

    return new Promise(function (resolve) {
      _ensureBootstrap(function (bs) {
        if (!bs) {
          console.warn('Bootstrap is not available; falling back to browser dialogs.');
          if (mode === 'confirm') {
            resolve(confirm(message));
          } else {
            alert(message);
            resolve(true);
          }
          return;
        }

        var dlg = _initSystemDialog(bs);

        if (!dlg) {
          console.warn('System dialog modal not found; falling back to browser dialogs.');
          if (mode === 'confirm') {
            resolve(confirm(message));
          } else {
            alert(message);
            resolve(true);
          }
          return;
        }

        dlg.active = { resolve: resolve, mode: mode };

        if (dlg.titleEl) dlg.titleEl.textContent = title;
        if (dlg.bodyEl) dlg.bodyEl.textContent = message;
        if (dlg.okEl) {
          dlg.okEl.textContent = okText;
          dlg.okEl.className = 'btn btn-' + (variant || 'primary');
        }
        if (dlg.cancelEl) {
          dlg.cancelEl.textContent = cancelText;
          dlg.cancelEl.classList.toggle('d-none', mode !== 'confirm');
        }
        if (dlg.iconEl) {
          var iconClass = mode === 'confirm' ? 'bi bi-question-circle text-warning' : 'bi bi-info-circle text-primary';
          dlg.iconEl.className = iconClass;
        }

        dlg.bsModal.show();
      });
    });
  }

  window.appAlert = function appAlert(message, options) {
    options = options || {};
    return _showSystemDialog({
      mode: 'alert',
      title: options.title || 'Notice',
      message: String(message ?? ''),
      okText: options.okText || 'OK',
      variant: options.variant || 'primary',
    });
  };

  window.appConfirm = function appConfirm(message, options) {
    options = options || {};
    return _showSystemDialog({
      mode: 'confirm',
      title: options.title || 'Please Confirm',
      message: String(message ?? ''),
      okText: options.okText || 'Confirm',
      cancelText: options.cancelText || 'Cancel',
      variant: options.variant || 'danger',
    });
  };

})(window, document);
