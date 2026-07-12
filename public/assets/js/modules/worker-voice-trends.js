(function () {
    'use strict';

    var page = document.getElementById('workerVoiceTrendsPage');
    if (!page || !window.App) {
        return;
    }

    var orgUuid = page.getAttribute('data-organization-uuid') || '';
    var latestData = null;
    var departmentsById = {};

    function escape(value) {
        return App.utils.escapeHtml(value === null || value === undefined ? '' : String(value));
    }

    function chartPalette() {
        var styles = window.getComputedStyle(document.documentElement);
        return {
            text: styles.getPropertyValue('--bs-body-color').trim() || '#697a8d',
            muted: styles.getPropertyValue('--bs-secondary-color').trim() || '#a1acb8',
            grid: 'rgba(120, 130, 140, 0.16)',
            primary: '#696cff',
            info: '#03c3ec',
            warning: '#ffab00',
            danger: '#ff3e1d',
            success: '#71dd37'
        };
    }

    function titleCase(value) {
        return String(value || '')
            .replace(/_/g, ' ')
            .replace(/\b[a-z]/g, function (match) { return match.toUpperCase(); });
    }

    function formatNumber(value) {
        return Number(value || 0).toFixed(2).replace(/\.00$/, '');
    }

    function formatDateLabel(value) {
        return value ? App.utils.formatDate(value) : '--';
    }

    function scoreBadgeClass(score) {
        if (Number(score) >= 4) {
            return 'bg-label-danger';
        }
        if (Number(score) >= 3) {
            return 'bg-label-warning';
        }
        if (Number(score) >= 2) {
            return 'bg-label-info';
        }
        return 'bg-label-success';
    }

    function tableRows(rows, emptyText, cells) {
        return rows.length ? rows.map(function (row) {
            return '<tr>' + cells.map(function (cell) {
                return '<td>' + escape(cell(row)) + '</td>';
            }).join('') + '</tr>';
        }).join('') : '<tr><td colspan="' + cells.length + '" class="text-muted">' + escape(emptyText) + '</td></tr>';
    }

    function renderRankedList(targetId, rows, emptyText, labelPicker, metricPicker, detailPicker) {
        var target = document.getElementById(targetId);
        if (!target) {
            return;
        }

        if (!rows.length) {
            target.innerHTML = '<div class="list-group-item px-0 text-muted small">' + escape(emptyText) + '</div>';
            return;
        }

        target.innerHTML = rows.slice(0, 5).map(function (row, index) {
            return '<div class="list-group-item px-0">' +
                '<div class="d-flex justify-content-between align-items-start gap-3">' +
                    '<div class="d-flex align-items-start gap-3 min-w-0">' +
                        '<span class="avatar-initial rounded bg-label-primary">' + escape(String(index + 1)) + '</span>' +
                        '<div class="min-w-0">' +
                            '<div class="fw-medium text-truncate">' + escape(labelPicker(row)) + '</div>' +
                            '<div class="text-muted small">' + escape(detailPicker(row)) + '</div>' +
                        '</div>' +
                    '</div>' +
                    '<span class="badge ' + scoreBadgeClass(metricPicker(row)) + '">' + escape(formatNumber(metricPicker(row))) + '</span>' +
                '</div>' +
            '</div>';
        }).join('');
    }

    function renderTimeline(rows) {
        var target = document.getElementById('workerVoiceTimelineList');
        if (!target) {
            return;
        }

        if (!rows.length) {
            target.innerHTML = '<div class="text-muted small">No timeline checkpoints yet.</div>';
            return;
        }

        target.innerHTML = rows.slice().reverse().slice(0, 6).map(function (row) {
            return '<div class="d-flex gap-3 pb-4">' +
                '<span class="avatar-initial rounded bg-label-info"><i class="bi bi-calendar3"></i></span>' +
                '<div class="flex-grow-1">' +
                    '<div class="d-flex justify-content-between align-items-center gap-2">' +
                        '<span class="fw-medium">' + escape(formatDateLabel(row.date)) + '</span>' +
                        '<span class="badge bg-label-primary">' + escape(String(row.responses || 0)) + ' responses</span>' +
                    '</div>' +
                    '<div class="text-muted small mt-1">Average discomfort ' + escape(formatNumber(row.averageDiscomfort || 0)) + '</div>' +
                '</div>' +
            '</div>';
        }).join('');
    }

    function renderTrendChart(rows) {
        var canvas = document.getElementById('workerVoiceTrendChart');
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        var palette = chartPalette();
        if (canvas._chart) {
            canvas._chart.destroy();
        }

        canvas._chart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: rows.map(function (row) { return formatDateLabel(row.date); }),
                datasets: [
                    {
                        type: 'bar',
                        label: 'Responses',
                        data: rows.map(function (row) { return row.responses || 0; }),
                        backgroundColor: 'rgba(105, 108, 255, 0.65)',
                        borderRadius: 6,
                        borderSkipped: false,
                        yAxisID: 'y'
                    },
                    {
                        type: 'line',
                        label: 'Avg discomfort',
                        data: rows.map(function (row) { return row.averageDiscomfort || 0; }),
                        borderColor: palette.warning,
                        backgroundColor: palette.warning,
                        tension: 0.35,
                        pointRadius: 3,
                        pointHoverRadius: 4,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: palette.text,
                            usePointStyle: true,
                            boxWidth: 8
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: palette.muted, precision: 0 },
                        grid: { color: palette.grid }
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: true,
                        suggestedMax: 5,
                        ticks: { color: palette.muted },
                        grid: { drawOnChartArea: false }
                    },
                    x: {
                        ticks: { color: palette.muted, maxRotation: 0, autoSkip: true },
                        grid: { display: false }
                    }
                }
            }
        });
    }

    function renderRegionChart(rows) {
        var canvas = document.getElementById('workerVoiceRegionChart');
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        var palette = chartPalette();
        if (canvas._chart) {
            canvas._chart.destroy();
        }

        if (!rows.length) {
            return;
        }

        canvas._chart = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: rows.slice(0, 6).map(function (row) { return titleCase(row.bodyRegion || 'Unspecified'); }),
                datasets: [{
                    data: rows.slice(0, 6).map(function (row) { return row.responses || 0; }),
                    backgroundColor: [palette.primary, palette.info, palette.warning, palette.danger, palette.success, '#8592a3'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: palette.text,
                            usePointStyle: true,
                            boxWidth: 8,
                            padding: 12
                        }
                    }
                }
            }
        });
    }

    function renderData(data) {
        latestData = data || {};
        var summary = data.summary || {};
        document.getElementById('workerVoiceTrendTotal').textContent = String(summary.totalResponses || 0);
        document.getElementById('workerVoiceTrendAnonymousRate').textContent = String(summary.anonymousRate || 0) + '%';
        document.getElementById('workerVoiceTrendDiscomfort').textContent = formatNumber(summary.averageDiscomfort || 0);
        document.getElementById('workerVoiceTrendPain30').textContent = formatNumber(summary.averagePain30Day || 0);

        var bodyRows = Array.isArray(data.byBodyRegion) ? data.byBodyRegion : [];
        renderRegionChart(bodyRows);
        renderRankedList('workerVoiceRegionHighlights', bodyRows, 'No body-region concentration yet.',
            function (row) { return titleCase(row.bodyRegion || 'Unspecified'); },
            function (row) { return row.averageDiscomfort || 0; },
            function (row) { return String(row.responses || 0) + ' responses'; }
        );
        document.getElementById('workerVoiceBodyRegionTable').innerHTML = tableRows(bodyRows, 'No feedback trends yet.', [
            function (row) { return titleCase(row.bodyRegion || 'Unspecified'); },
            function (row) { return row.responses; },
            function (row) { return formatNumber(row.averageDiscomfort); },
            function (row) { return formatNumber(row.averagePain30Day); }
        ]);

        var taskRows = Array.isArray(data.byTask) ? data.byTask : [];
        renderRankedList('workerVoiceTaskList', taskRows, 'No task trends yet.',
            function (row) { return row.taskName || 'Unlinked feedback'; },
            function (row) { return row.averageDiscomfort || 0; },
            function (row) { return String(row.responses || 0) + ' responses'; }
        );

        var taskTypeRows = Array.isArray(data.byTaskType) ? data.byTaskType : [];
        renderRankedList('workerVoiceTaskTypeList', taskTypeRows, 'No grouped task signals yet.',
            function (row) { return row.label || row.taskType || 'Unlinked feedback'; },
            function (row) { return row.averageDiscomfort || 0; },
            function (row) { return String(row.responses || 0) + ' responses'; }
        );

        var departmentRows = Array.isArray(data.byDepartment) ? data.byDepartment : [];
        renderRankedList('workerVoiceDepartmentList', departmentRows, 'No department signals yet.',
            function (row) {
                if (!row.departmentUuid) {
                    return 'Unassigned';
                }
                return departmentsById[row.departmentUuid] || row.departmentUuid;
            },
            function (row) { return row.averageDiscomfort || 0; },
            function (row) { return String(row.responses || 0) + ' responses'; }
        );

        var timelineRows = Array.isArray(data.timeline) ? data.timeline : [];
        renderTrendChart(timelineRows);
        renderTimeline(timelineRows);
    }

    function loadDepartments() {
        if (!orgUuid) {
            return Promise.resolve();
        }

        return App.api.get('/api/v1/organizations/' + encodeURIComponent(orgUuid) + '/departments', { limit: 200 }).then(function (res) {
            var items = res.ok && Array.isArray(res.data) ? res.data : [];
            items.forEach(function (item) {
                departmentsById[item.id] = item.name || item.id;
            });
        });
    }

    function loadPage() {
        Promise.all([
            App.api.get('/api/v1/worker-feedback/trends'),
            loadDepartments()
        ]).then(function (results) {
            var res = results[0];
            if (!res.ok) {
                App.ui.showAlert('danger', res.message || 'Failed to load worker voice trends.', '#workerVoiceTrendsAlert');
                return;
            }

            renderData(res.data || {});
        }).catch(function () {
            App.ui.showAlert('danger', 'Worker voice trends could not be loaded.', '#workerVoiceTrendsAlert');
        });
    }

    var themeObserver = new MutationObserver(function () {
        if (latestData) {
            renderData(latestData);
        }
    });
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });

    loadPage();
})();
