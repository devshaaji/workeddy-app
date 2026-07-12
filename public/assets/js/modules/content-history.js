(function () {
    'use strict';

    if (!window.App) { return; }

    var root = document.getElementById('contentHistoryPage');
    if (!root) { return; }

    var pageUuid = root.getAttribute('data-page-uuid') || '';
    if (!pageUuid) { return; }

    function escape(value) {
        return App.utils.escapeHtml(value === null || value === undefined ? '' : String(value));
    }

    function statusBadge(status) {
        var tone = status === 'published' ? 'success' : 'info';
        return '<span class="badge bg-label-' + tone + '">' + escape(status || 'draft') + '</span>';
    }

    function bindRestoreActions() {
        root.querySelectorAll('[data-revision-uuid]').forEach(function (button) {
            button.addEventListener('click', function () {
                var revisionUuid = button.getAttribute('data-revision-uuid') || '';
                if (!revisionUuid) { return; }

                App.modals.confirm({
                    title: 'Restore revision',
                    message: 'Restore this revision into a new draft for further editing?',
                    confirmText: 'Restore',
                    confirmClass: 'btn-primary',
                    onConfirm: function () {
                        App.api.post('/api/v1/content/pages/' + encodeURIComponent(pageUuid) + '/revisions/' + encodeURIComponent(revisionUuid) + '/restore', {
                            changeSummary: 'Restored from history page'
                        }).then(function (res) {
                            if (!res.ok) {
                                App.notify.error(res.message || 'Failed to restore revision.');
                                return;
                            }
                            App.notify.success(res.message || 'Revision restored.');
                            window.location.href = '/content/pages/' + encodeURIComponent(pageUuid) + '/edit';
                        });
                    }
                });
            });
        });
    }

    var table = App.tables.createAdvanced({
        card: '#contentHistoryCard',
        tbody: '#contentHistoryBody',
        endpoint: '/api/v1/content/pages/' + encodeURIComponent(pageUuid) + '/revisions',
        resultCount: '#contentHistory-result-count',
        colspan: 5,
        defaultSort: 'versionNumber',
        sortDir: 'desc',
        emptyTitle: 'No revision history',
        emptySubtitle: 'Draft and published revisions will appear here as the page changes.',
        sortValue: function (record, key) {
            if (key === 'versionNumber') {
                return Number(record.versionNumber || 0);
            }
            return String(record[key] || '').toLowerCase();
        },
        renderRow: function (record) {
            var revisionUuid = record.revisionUuid || '';
            var previewHref = '/content/pages/' + encodeURIComponent(pageUuid) + '/revisions/' + encodeURIComponent(revisionUuid);

            return '<tr>' +
                '<td class="fw-semibold">v' + escape(record.versionNumber || 0) + '</td>' +
                '<td>' + statusBadge(record.revisionStatus) + '</td>' +
                '<td class="text-muted small">' + escape(record.publishedAt || record.updatedAt || '') + '</td>' +
                '<td>' + escape(record.changeSummary || 'No summary provided') + '</td>' +
                '<td class="text-end">' +
                    '<div class="d-inline-flex gap-2">' +
                        '<a class="btn btn-sm btn-outline-secondary" href="' + previewHref + '">' +
                            '<i class="bi bi-eye me-1"></i>Preview' +
                        '</a>' +
                        '<button class="btn btn-sm btn-outline-primary" type="button" data-revision-uuid="' + escape(revisionUuid) + '">' +
                            '<i class="bi bi-arrow-counterclockwise me-1"></i>Restore' +
                        '</button>' +
                    '</div>' +
                '</td>' +
            '</tr>';
        },
        afterLoad: function (records) {
            App.utils.setText('#contentHistoryCountBadge', records.length);
        },
        afterRender: function () {
            bindRestoreActions();
        }
    });

    table.load();
})();
