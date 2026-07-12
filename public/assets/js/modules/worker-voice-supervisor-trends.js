(function () {
    'use strict';

    var page = document.getElementById('supervisorFeedbackTrendsPage');
    if (!page || !window.App) {
        return;
    }

    var form = document.getElementById('supervisorTrendFilters');
    var resetButton = document.getElementById('supervisorTrendReset');
    var orgUuid = page.getAttribute('data-organization-uuid') || '';
    var departmentsById = {};
    var latestData = null;

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

    function setOptions(select, items, emptyLabel, valuePicker, labelPicker) {
        select.innerHTML = '<option value="">' + escape(emptyLabel) + '</option>' + items.map(function (item) {
            return '<option value="' + escape(valuePicker(item)) + '">' + escape(labelPicker(item)) + '</option>';
        }).join('');
    }

    function tableRows(rows, emptyText, cells) {
        return rows.length ? rows.map(function (row) {
            return '<tr>' + cells.map(function (cell) {
                return '<td>' + escape(cell(row)) + '</td>';
            }).join('') + '</tr>';
        }).join('') : '<tr><td colspan="' + cells.length + '" class="text-muted">' + escape(emptyText) + '</td></tr>';
    }

    function renderRankedList(targetId, rows, emptyText) {
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
                            '<div class="fw-medium text-truncate">' + escape(titleCase(row.bodyRegion || 'Unspecified')) + '</div>' +
                            '<div class="text-muted small">' + escape(String(row.responses || 0)) + ' responses</div>' +
                        '</div>' +
                    '</div>' +
                    '<span class="badge ' + scoreBadgeClass(row.averageSeverity || 0) + '">' + escape(formatNumber(row.averageSeverity || 0)) + '</span>' +
                '</div>' +
            '</div>';
        }).join('');
    }

    function renderTimeline(rows) {
        var target = document.getElementById('supervisorTrendTimelineList');
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
                    '<div class="text-muted small mt-1">Average severity ' + escape(formatNumber(row.averageSeverity || 0)) + '</div>' +
                '</div>' +
            '</div>';
        }).join('');
    }

    function renderTrendChart(rows) {
        var canvas = document.getElementById('supervisorTrendChart');
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
                        label: 'Avg severity',
                        data: rows.map(function (row) { return row.averageSeverity || 0; }),
                        borderColor: palette.danger,
                        backgroundColor: palette.danger,
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
        var canvas = document.getElementById('supervisorTrendRegionChart');
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
                    backgroundColor: [palette.danger, palette.warning, palette.info, palette.primary, palette.success, '#8592a3'],
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

    function loadFilters() {
        return Promise.all([
            App.api.get('/api/v1/organizations/' + encodeURIComponent(orgUuid) + '/tasks', { limit: 200 }),
            App.api.get('/api/v1/organizations/' + encodeURIComponent(orgUuid) + '/departments', { limit: 200 }),
            App.api.get('/api/v1/worker-feedback/questions')
        ]).then(function (responses) {
            var tasks = responses[0].ok && Array.isArray(responses[0].data) ? responses[0].data : [];
            var departments = responses[1].ok && Array.isArray(responses[1].data) ? responses[1].data : [];
            var bodyRegions = responses[2].ok && responses[2].data && Array.isArray(responses[2].data.bodyRegions) ? responses[2].data.bodyRegions : [];

            departments.forEach(function (item) {
                departmentsById[item.id] = item.name || item.id;
            });

            setOptions(document.getElementById('supervisorTrendTask'), tasks, 'All tasks', function (item) { return item.id; }, function (item) { return item.name || item.id; });
            setOptions(document.getElementById('supervisorTrendDepartment'), departments, 'All departments', function (item) { return item.id; }, function (item) { return item.name || item.id; });
            setOptions(document.getElementById('supervisorTrendBodyRegion'), bodyRegions, 'All body regions', function (item) { return item.key || item; }, function (item) { return item.label || item.key || item; });
        });
    }

    function renderData(data) {
        latestData = data || {};
        var summary = data.summary || {};
        document.getElementById('supervisorTrendTotal').textContent = String(summary.totalResponses || 0);
        document.getElementById('supervisorTrendSeverity').textContent = formatNumber(summary.averageSeverity || 0);
        document.getElementById('supervisorTrendFrequency').textContent = formatNumber(summary.averageFrequency || 0);

        var regionRows = Array.isArray(data.byBodyRegion) ? data.byBodyRegion : [];
        renderRegionChart(regionRows);
        renderRankedList('supervisorTrendRegionHighlights', regionRows, 'No body-region concentration yet.');

        document.getElementById('supervisorTrendDepartmentTable').innerHTML = tableRows(Array.isArray(data.byDepartment) ? data.byDepartment : [], 'No department trends yet.', [
            function (row) { return row.departmentUuid ? (departmentsById[row.departmentUuid] || 'Unknown department') : 'Unassigned'; },
            function (row) { return row.responses; },
            function (row) { return formatNumber(row.averageSeverity); }
        ]);

        var timelineRows = Array.isArray(data.timeline) ? data.timeline : [];
        renderTrendChart(timelineRows);
        renderTimeline(timelineRows);
    }

    function loadTrends() {
        var params = {
            taskUuid: form.elements.taskUuid.value || null,
            departmentUuid: form.elements.departmentUuid.value || null,
            bodyRegion: form.elements.bodyRegion.value || null,
            observedRiskLevel: form.elements.observedRiskLevel.value || null,
            dateFrom: form.elements.dateFrom.value || null,
            dateTo: form.elements.dateTo.value || null
        };

        App.api.get('/api/v1/supervisor-feedback/trends', params).then(function (res) {
            if (!res.ok) {
                App.ui.showAlert('danger', res.message || 'Failed to load supervisor trends.', '#supervisorFeedbackTrendsAlert');
                return;
            }

            renderData(res.data || {});
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        loadTrends();
    });

    if (resetButton) {
        resetButton.addEventListener('click', function () {
            form.reset();
            loadTrends();
        });
    }

    var themeObserver = new MutationObserver(function () {
        if (latestData) {
            renderData(latestData);
        }
    });
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });

    loadFilters().then(function () {
        loadTrends();
    }).catch(function () {
        App.ui.showAlert('danger', 'Trend filters could not be loaded.', '#supervisorFeedbackTrendsAlert');
    });
})();
