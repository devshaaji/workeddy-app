(function () {
    'use strict';

    if (!window.App) { return; }
    if (!document.getElementById('contentPagesIndex')) { return; }

    function escape(value) {
        return App.utils.escapeHtml(value === null || value === undefined ? '' : String(value));
    }

    function statusBadge(status) {
        var map = {
            draft: 'info',
            published: 'success',
            archived: 'secondary',
            active: 'success'
        };
        var tone = map[status] || 'secondary';
        return '<span class="badge bg-label-' + tone + '">' + escape(status || 'unknown') + '</span>';
    }

    var table = App.tables.createAdvanced({
        card: '#contentPagesCard',
        tbody: '#contentPagesBody',
        endpoint: '/api/v1/content/pages',
        resultCount: '#contentPages-result-count',
        pagination: '#contentPages-pagination',
        colspan: 6,
        defaultSort: 'title',
        emptyTitle: 'No content pages yet',
        emptySubtitle: 'Create the first managed page to start publishing editorial content.',
        filters: {
            'content-pages-search': 'q',
            'content-pages-audience': 'audience',
            'content-pages-status': 'status'
        },
        filterRecord: function (record, values) {
            var q = (values.q || '').toLowerCase().trim();
            var aud = values.audience || '';
            var st = values.status || '';

            var matchQ = !q ||
                (record.title || '').toLowerCase().includes(q) ||
                (record.pageKey || '').toLowerCase().includes(q) ||
                (record.templateKey || '').toLowerCase().includes(q);
            var matchAud = !aud || record.audience === aud;
            var matchSt = !st || record.status === st;

            return matchQ && matchAud && matchSt;
        },
        sortValue: function (record, key) {
            return String(record[key] || '').toLowerCase();
        },
        renderRow: function (record, table, index) {
            var pageUuid = record.pageUuid || '';
            var title = record.title || record.pageKey || 'Untitled page';
            var pageMeta = '<div class="fw-semibold">' + escape(title) + '</div>' +
                '<div class="small text-muted">' + escape(record.pageKey || '') + '</div>';

            var rowNum = '';
            if (table && typeof index === 'number') {
                rowNum = (table.currentPage - 1) * table.pageSize + index + 1;
            }

            return '<tr>' +
                '<td><span class="text-muted small">' + rowNum + '</span></td>' +
                '<td>' + pageMeta + '</td>' +
                '<td>' + escape(record.audience || 'internal') + '</td>' +
                '<td>' + statusBadge(record.status) + '</td>' +
                '<td><span class="text-muted small">' + escape(record.templateKey || 'internal_default') + '</span></td>' +
                '<td class="text-end">' + App.tables.actionDropdown([
                    { label: 'Open', href: '/content/pages/' + encodeURIComponent(pageUuid) },
                    { label: 'Edit', href: '/content/pages/' + encodeURIComponent(pageUuid) + '/edit' },
                    { label: 'History', href: '/content/pages/' + encodeURIComponent(pageUuid) + '/revisions' }
                ]) + '</td>' +
            '</tr>';
        },
        afterLoad: function (records) {
            App.utils.setText('#contentPagesCountBadge', records.length);
        }
    });

    table.load();
})();
