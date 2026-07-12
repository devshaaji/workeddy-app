(function (window, document) {
  'use strict';

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
      return;
    }
    fn();
  }

  function bindContactForm() {
    if (!window.App || !App.forms) { return; }

    var form = document.getElementById('contact-form');
    if (!form) { return; }

    App.forms.bindAjaxForm(form, {
      alertTarget: '#contact-feedback',
      resetOnSuccess: true,
      beforeSend: function (candidateForm) {
        if (!candidateForm.checkValidity()) {
          candidateForm.classList.add('was-validated');
          return false;
        }
        return true;
      },
      onSuccess: function (res) {
        form.classList.remove('was-validated');
      }
    });
  }

  ready(function () {
    bindContactForm();
  });
})(window, document);
