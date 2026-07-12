(function () {
    'use strict';

    if (!window.App) { return; }
    if (!document.getElementById('contentMediaPage')) { return; }

    var searchInput = document.getElementById('contentMediaSearch');
    var mimeFilter = document.getElementById('contentMediaMimeFilter');

    function escape(value) {
        return App.utils.escapeHtml(value === null || value === undefined ? '' : String(value));
    }

    function statusBadge(status) {
        var tone = status === 'archived' ? 'secondary' : 'success';
        return '<span class="badge bg-label-' + tone + '">' + escape(status || 'active') + '</span>';
    }

    function applyFilters() {
        table.applyFilters({
            q: searchInput ? searchInput.value.trim().toLowerCase() : '',
            mimeType: mimeFilter ? mimeFilter.value : ''
        });
    }

    var table = App.tables.createAdvanced({
        card: '#contentMediaCard',
        tbody: '#contentMediaBody',
        endpoint: '/api/v1/content/media',
        resultCount: '#contentMedia-result-count',
        colspan: 5,
        defaultSort: 'originalName',
        emptyTitle: 'No media found',
        emptySubtitle: 'Uploaded content media assets will appear here.',
        sortValue: function (record, key) {
            return String(record[key] || '').toLowerCase();
        },
        filterRecord: function (record, filters) {
            var matchesMime = !filters.mimeType || record.mimeType === filters.mimeType;
            if (!matchesMime) { return false; }

            var q = String(filters.q || '');
            if (!q) { return true; }

            var haystack = [
                record.originalName || '',
                record.uuid || '',
                record.storageFileUuid || '',
                record.mimeType || ''
            ].join(' ').toLowerCase();

            return haystack.indexOf(q) > -1;
        },
        renderRow: function (record) {
            var width = record.width || 0;
            var height = record.height || 0;

            return '<tr>' +
                '<td>' +
                    '<div class="fw-semibold">' + escape(record.originalName || 'Untitled asset') + '</div>' +
                    '<div class="small text-muted">' + escape(record.uuid || '') + '</div>' +
                '</td>' +
                '<td>' + escape(record.mimeType || '') + '</td>' +
                '<td>' + escape(width + ' x ' + height) + '</td>' +
                '<td><code>' + escape(record.storageFileUuid || '') + '</code></td>' +
                '<td>' + statusBadge(record.status) + '</td>' +
            '</tr>';
        },
        afterLoad: function (records) {
            App.utils.setText('#contentMediaCountBadge', records.length);
        }
    });

    if (searchInput) {
        searchInput.addEventListener('input', App.utils.debounce(applyFilters, 250));
    }
    if (mimeFilter) {
        mimeFilter.addEventListener('change', applyFilters);
    }

    table.load();
})();
