(function (window, document) {
  'use strict';

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
      return;
    }
    fn();
  }

  ready(function () {
    if (!window.App) { return; }

    var form = document.getElementById('platform-settings-form');
    if (!form) { return; }

    var feedback = '#platform-settings-feedback';
    var moduleKey = form.getAttribute('data-settings-module') || '';

    App.forms.bindAjaxForm(form, {
      method: 'PUT',
      url: form.getAttribute('action'),
      alertTarget: feedback,
      transformData: function (data) {
        var transformed = {};
        var jsonError = null;

        form.querySelectorAll('[name]').forEach(function (field) {
          if (!field.name || field.disabled) { return; }

          var type = field.getAttribute('data-setting-type') || '';
          if (type === 'boolean') {
            transformed[field.name] = !!field.checked;
            return;
          }

          if (type === 'integer') {
            transformed[field.name] = field.value === '' ? null : parseInt(field.value, 10);
            return;
          }

          if (type === 'float') {
            transformed[field.name] = field.value === '' ? null : parseFloat(field.value);
            return;
          }

          if (type === 'json') {
            if (field.value.trim() === '') {
              transformed[field.name] = null;
              return;
            }
            try {
              transformed[field.name] = JSON.parse(field.value);
            } catch (err) {
              jsonError = 'Invalid JSON in ' + field.name + '.';
            }
            return;
          }

          transformed[field.name] = field.value;
        });

        if (jsonError) {
          App.ui.showAlert('danger', jsonError, feedback);
          throw new Error(jsonError);
        }

        return transformed;
      },
      onSuccess: function (res) {
        App.ui.showAlert('success', (res && res.message) || 'Settings saved successfully.', feedback);
      }
    });

    var resetBtn = document.getElementById('platform-settings-reset');
    if (!resetBtn || !moduleKey) { return; }

    resetBtn.addEventListener('click', function () {
      var keys = [];
      form.querySelectorAll('[name]').forEach(function (field) {
        if (!field.disabled && field.name) {
          keys.push(field.name);
        }
      });

      if (!keys.length) { return; }

      App.modals.confirm({
        title: 'Reset Settings?',
        text: 'This will reset this module settings page back to its registered defaults.',
        confirmText: 'Reset Defaults',
        onConfirm: function () {
          App.api.request('DELETE', '/api/v1/settings/' + moduleKey, { keys: keys })
            .then(function (res) {
              if (!res.ok) {
                App.ui.showAlert('danger', res.message || 'Failed to reset settings.', feedback);
                return;
              }
              window.location.reload();
            })['catch'](function (err) {
              App.ui.showAlert('danger', (err && err.message) || 'Failed to reset settings.', feedback);
            });
        }
      });
    });
  });
})(window, document);
