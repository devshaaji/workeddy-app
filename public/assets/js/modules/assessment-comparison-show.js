(function () {
    'use strict';

    var page = document.getElementById('comparisonDetailPage');
    if (!page || !window.App) {
        return;
    }

    var lockBtn = document.getElementById('lockComparisonReportBtn');
    var lockUrl = page.getAttribute('data-lock-url') || '';
    var statusBadge = page.getAttribute('data-comparison-status') || '';

    if (lockBtn && lockUrl) {
        lockBtn.addEventListener('click', function (event) {
            event.preventDefault();
            App.ui.setButtonLoading(lockBtn, true, 'Locking...');
            App.api.post(lockUrl, {}).then(function (res) {
                App.ui.setButtonLoading(lockBtn, false);
                if (!res.ok) {
                    App.ui.showAlert('danger', res.message || 'Failed to lock comparison report.', '#comparisonDetailAlert');
                    return;
                }

                App.notify.success('Comparison report locked.');
                window.location.reload();
            }).catch(function () {
                App.ui.setButtonLoading(lockBtn, false);
                App.ui.showAlert('danger', 'Failed to lock comparison report.', '#comparisonDetailAlert');
            });
        });
    }

    if (statusBadge === 'locked' && lockBtn) {
        lockBtn.classList.add('d-none');
    }
})();
