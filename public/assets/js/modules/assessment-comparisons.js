(function () {
    'use strict';

    var page = document.getElementById('assessmentComparisonsPage');
    if (!page || !window.App) {
        return;
    }

    var state = {
        reports: [],
    };

    function asText(value, fallback) {
        if (value === null || value === undefined || value === '') {
            return fallback || '--';
        }

        return String(value);
    }

    function escape(value) {
        return App.utils.escapeHtml(asText(value, ''));
    }

    function formatPercent(value) {
        if (value === null || value === undefined || value === '') {
            return '--';
        }

        return Number(value).toFixed(2) + '%';
    }

    function statusBadge(status) {
        var map = {
            generated: 'bg-label-primary',
            locked: 'bg-label-success',
            draft: 'bg-label-secondary',
        };

        return '<span class="badge ' + (map[status] || 'bg-label-secondary') + '">' + escape(status) + '</span>';
    }

    function directionBadge(direction) {
        var map = {
            improved: 'bg-label-success',
            worsened: 'bg-label-danger',
            unchanged: 'bg-label-secondary',
        };

        return '<span class="badge ' + (map[direction] || 'bg-label-secondary') + '">' + escape(direction) + '</span>';
    }

    function setText(id, value) {
        var node = document.getElementById(id);
        if (node) {
            node.textContent = String(value);
        }
    }

    function setHtml(id, value) {
        var node = document.getElementById(id);
        if (node) {
            node.innerHTML = value;
        }
    }

    function filters() {
        var status = document.getElementById('comparisonStatusFilter');
        var direction = document.getElementById('comparisonDirectionFilter');

        return {
            status: status ? status.value : '',
            direction: direction ? direction.value : '',
        };
    }

    function filteredReports() {
        var active = filters();

        return state.reports.filter(function (report) {
            if (active.status && report.status !== active.status) {
                return false;
            }
            if (active.direction && report.direction !== active.direction) {
                return false;
            }
            return true;
        });
    }

    function render() {
        var rows = filteredReports();
        setText('comparisonCount', state.reports.length);
        setText('comparisonImprovedCount', state.reports.filter(function (report) { return report.direction === 'improved'; }).length);
        setText('comparisonLockedCount', state.reports.filter(function (report) { return report.status === 'locked'; }).length);
        setText('comparisonResultCount', rows.length);

        if (rows.length === 0) {
            setHtml('comparisonTableBody', '<tr><td colspan="7" class="text-muted">No comparison reports match the current filters.</td></tr>');
            return;
        }

        setHtml('comparisonTableBody', rows.map(function (report) {
            return '<tr>' +
                '<td><div class="fw-semibold text-break">' + escape(report.baselineAssessmentUuid) + '</div><small class="text-muted text-break">Follow-up: ' + escape(report.followUpAssessmentUuid) + '</small></td>' +
                '<td><span class="text-uppercase">' + escape(report.model) + '</span></td>' +
                '<td>' + escape(formatPercent(report.riskReductionPercent)) + '</td>' +
                '<td>' + directionBadge(report.direction) + '</td>' +
                '<td>' + statusBadge(report.status) + '</td>' +
                '<td>' + escape(report.generatedAt ? App.utils.formatDate(report.generatedAt) : '--') + '</td>' +
                '<td class="text-end"><a href="/assessments/comparisons/' + encodeURIComponent(report.uuid) + '" class="btn btn-sm btn-outline-primary">Open</a></td>' +
            '</tr>';
        }).join(''));
    }

    function load() {
        App.api.get('/api/v1/comparison-reports').then(function (res) {
            if (!res.ok) {
                App.ui.showAlert('danger', res.message || 'Failed to load comparison reports.', '#assessmentComparisonsPage');
                return;
            }

            state.reports = Array.isArray(res.data) ? res.data : [];
            render();
        });
    }

    ['comparisonStatusFilter', 'comparisonDirectionFilter'].forEach(function (id) {
        var control = document.getElementById(id);
        if (control) {
            control.addEventListener('change', render);
        }
    });

    var refresh = document.getElementById('comparisonRefreshBtn');
    if (refresh) {
        refresh.addEventListener('click', load);
    }

    load();
})();
