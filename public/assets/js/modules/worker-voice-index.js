(function () {
    'use strict';

    if (!window.App) { return; }

    var page = document.getElementById('workerVoiceIndexPage');
    if (!page) { return; }

    function escape(value) {
        return App.utils.escapeHtml(value === null || value === undefined ? '' : String(value));
    }

    function badge(label, tone) {
        return '<span class="badge bg-label-' + tone + '">' + escape(label) + '</span>';
    }

    function formatPainWindow(record) {
        var sevenDay = Number(record.pain7DayLevel || 0);
        var thirtyDay = Number(record.pain30DayLevel || 0);

        return '<div class="small">' +
            '<div><span class="text-muted">7-day:</span> <span class="fw-semibold">' + escape(sevenDay) + '/5</span></div>' +
            '<div><span class="text-muted">30-day:</span> <span class="fw-semibold">' + escape(thirtyDay) + '/5</span></div>' +
        '</div>';
    }

    function formatTaskCell(record) {
        var taskUuid = record.taskUuid || '';
        var assessmentUuid = record.assessmentUuid || '';
        var taskHtml = taskUuid
            ? '<a href="/tasks/' + encodeURIComponent(taskUuid) + '" class="fw-semibold text-decoration-none">' + escape(taskUuid) + '</a>'
            : '<span class="text-muted fst-italic">Unlinked</span>';
        var assessmentHtml = assessmentUuid
            ? '<a href="/assessments/' + encodeURIComponent(assessmentUuid) + '" class="small text-muted text-decoration-none">Assessment ' + escape(assessmentUuid) + '</a>'
            : '<span class="small text-muted">No assessment link</span>';

        return taskHtml + '<div>' + assessmentHtml + '</div>';
    }

    function loadCatalog() {
        return App.api.get('/api/v1/worker-feedback/questions').then(function (res) {
            if (!res.ok) { return; }

            var filter = document.getElementById('workerVoiceBodyRegionFilter');
            if (!filter) { return; }

            filter.innerHTML = '<option value="">All body regions</option>' + (res.data && Array.isArray(res.data.bodyRegions)
                ? res.data.bodyRegions.map(function (region) {
                    return '<option value="' + escape(region.key) + '">' + escape(region.label) + '</option>';
                }).join('')
                : '');
        });
    }

    var table = App.tables.createAdvanced({
        card: '#workerVoiceTableCard',
        tbody: '#workerVoiceIndexTable',
        endpoint: '/api/v1/worker-feedback?limit=200',
        resultCount: '#workerVoiceResultCount',
        pagination: '#workerVoicePagination',
        colspan: 8,
        pageSize: 15,
        defaultSort: 'createdAt',
        sortDir: 'desc',
        loadingText: 'Loading feedback...',
        emptyTitle: 'No feedback records found',
        emptySubtitle: 'Adjust the filters or wait for new worker voice submissions.',
        filters: {
            'workerVoiceSearchFilter': 'q',
            'workerVoiceBodyRegionFilter': 'bodyRegion',
            'workerVoiceAnonymousFilter': 'anonymousStatus',
            'workerVoiceDateFrom': 'dateFrom',
            'workerVoiceDateTo': 'dateTo',
            'workerVoicePainWindowFilter': 'painWindow'
        },
        filterRecord: function (record, values) {
            var q = (values.q || '').toLowerCase().trim();
            var bodyRegion = values.bodyRegion || '';
            var anonymousStatus = values.anonymousStatus || '';
            var dateFrom = values.dateFrom || '';
            var dateTo = values.dateTo || '';
            var painWindow = values.painWindow || '';

            var createdAt = record.createdAt ? new Date(record.createdAt) : null;
            var searchBlob = [
                record.bodyRegion,
                record.taskUuid,
                record.assessmentUuid,
                record.suggestedChange
            ].join(' ').toLowerCase();

            var matchQ = !q || searchBlob.indexOf(q) !== -1;
            var matchBodyRegion = !bodyRegion || record.bodyRegion === bodyRegion;
            var matchAnonymous = anonymousStatus === '' || String(Number(!!record.anonymousStatus)) === anonymousStatus;
            var matchDateFrom = !dateFrom || (createdAt instanceof Date && !isNaN(createdAt.getTime()) && createdAt >= new Date(dateFrom + 'T00:00:00'));
            var matchDateTo = !dateTo || (createdAt instanceof Date && !isNaN(createdAt.getTime()) && createdAt <= new Date(dateTo + 'T23:59:59'));
            var matchPainWindow = !painWindow
                || (painWindow === '7' && Number(record.pain7DayLevel || 0) > 0)
                || (painWindow === '30' && Number(record.pain30DayLevel || 0) > 0);

            return matchQ && matchBodyRegion && matchAnonymous && matchDateFrom && matchDateTo && matchPainWindow;
        },
        sortValue: function (record, key) {
            if (key === 'createdAt') { return record.createdAt || ''; }
            return String(record[key] || '').toLowerCase();
        },
        renderRow: function (record, tableRef, index) {
            var rowNumber = '';
            if (tableRef && typeof index === 'number') {
                rowNumber = (tableRef.currentPage - 1) * tableRef.pageSize + index + 1;
            }

            var discomfortTone = Number(record.discomfortLevel || 0) >= 4 ? 'danger'
                : Number(record.discomfortLevel || 0) >= 2 ? 'warning'
                : 'success';

            return '<tr>' +
                '<td><span class="text-muted small">' + rowNumber + '</span></td>' +
                '<td><div class="fw-semibold">' + escape(record.bodyRegion || '--') + '</div></td>' +
                '<td>' + formatTaskCell(record) + '</td>' +
                '<td>' + badge((record.hasDiscomfort ? 'Discomfort ' : 'No discomfort ') + escape(String(record.discomfortLevel || 0) + '/5'), discomfortTone) + '</td>' +
                '<td>' + formatPainWindow(record) + '</td>' +
                '<td>' + (record.anonymousStatus ? badge('Anonymous', 'info') : badge('Identified', 'secondary')) + '</td>' +
                '<td><span class="small text-muted">' + escape(record.createdAt ? App.utils.formatDate(record.createdAt) : '--') + '</span></td>' +
                '<td class="text-end">' + App.tables.actionDropdown([
                    { label: 'Open detail', href: '/worker-voice/' + encodeURIComponent(record.uuid || '') },
                    record.taskUuid ? { label: 'Open task', href: '/tasks/' + encodeURIComponent(record.taskUuid) } : '',
                    record.assessmentUuid ? { label: 'Open assessment', href: '/assessments/' + encodeURIComponent(record.assessmentUuid) } : ''
                ].filter(Boolean)) + '</td>' +
            '</tr>';
        }
    });

    loadCatalog();

    var refreshButton = document.getElementById('workerVoiceRefreshBtn');
    if (refreshButton) {
        refreshButton.addEventListener('click', function () {
            table.load();
        });
    }
})();
