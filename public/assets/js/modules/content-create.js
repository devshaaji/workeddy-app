(function () {
    'use strict';

    if (!window.App) { return; }

    var form = document.getElementById('contentCreateForm');
    if (!form) { return; }

    App.forms.bindAjaxForm(form, {
        url: '/api/v1/content/pages',
        method: 'POST',
        submitBtn: '#contentCreateSubmitBtn',
        transformData: function (data) {
            data.snapshot = { sections: [], references: [] };
            return data;
        },
        onSuccess: function (res) {
            var pageUuid = res && res.data ? res.data.pageUuid : null;
            App.notify.success(res.message || 'Content page created.');
            if (pageUuid) {
                window.location.href = '/content/pages/' + encodeURIComponent(pageUuid) + '/edit';
            }
        },
        onError: function (res) {
            App.notify.error(res.message || 'Failed to create content page.');
        }
    });
})();
