/**
 * national-importance.js — renders the three required charts (common
 * high-strain tasks, body region burden, corrective action outcomes) plus
 * the worker discomfort trend, from data the server already embedded in the
 * page (#nationalImportanceChartData). No API call needed — this dashboard
 * is fully server-rendered from the platform_aggregate_metrics cache.
 */
(function () {
    'use strict';

    var page = document.getElementById('nationalImportancePage');
    if (!page || typeof Chart === 'undefined') { return; }

    var dataEl = document.getElementById('nationalImportanceChartData');
    var data = {};
    try {
        data = dataEl ? JSON.parse(dataEl.textContent || '{}') : {};
    } catch (_) {
        data = {};
    }

    var palette = ['#7c3aed', '#0ea5e9', '#f59e0b', '#16a34a', '#dc2626', '#0891b2', '#db2777', '#65a30d', '#9333ea', '#f97316'];

    function renderRankedBarChart(canvasId, emptyId, rows, labelKey, valueKey, color) {
        var canvas = document.getElementById(canvasId);
        var empty = document.getElementById(emptyId);
        if (!canvas) { return; }

        if (!rows || rows.length === 0) {
            canvas.classList.add('d-none');
            if (empty) { empty.classList.remove('d-none'); }
            return;
        }

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: rows.map(function (r) { return r[labelKey] || ''; }),
                datasets: [{
                    label: 'Count',
                    data: rows.map(function (r) { return r.count || 0; }),
                    backgroundColor: color,
                    borderRadius: 4,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(0,0,0,0.04)' } },
                    y: { grid: { display: false } },
                },
            },
        });
    }

    renderRankedBarChart('highStrainTasksChart', 'highStrainTasksEmpty', data.commonHighStrainTasks, 'task', 'count', 'rgba(124,58,237,0.75)');
    renderRankedBarChart('bodyRegionChart', 'bodyRegionEmpty', data.bodyRegionBurden, 'region', 'count', 'rgba(14,165,233,0.75)');
    renderRankedBarChart('correctiveActionsChart', 'correctiveActionsEmpty', data.correctiveActionOutcomes, 'label', 'count', 'rgba(22,163,74,0.75)');

    // Worker discomfort trend — a line chart over months
    var trend = data.workerDiscomfortTrend || [];
    var trendCanvas = document.getElementById('discomfortTrendChart');
    var trendEmpty = document.getElementById('discomfortTrendEmpty');
    if (trendCanvas) {
        if (trend.length === 0) {
            trendCanvas.classList.add('d-none');
            if (trendEmpty) { trendEmpty.classList.remove('d-none'); }
        } else {
            new Chart(trendCanvas, {
                type: 'line',
                data: {
                    labels: trend.map(function (r) { return r.month || ''; }),
                    datasets: [{
                        label: 'Average Discomfort',
                        data: trend.map(function (r) { return r.averageDiscomfort || 0; }),
                        borderColor: 'rgba(220,38,38,0.9)',
                        backgroundColor: 'rgba(220,38,38,0.12)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } },
                        x: { grid: { display: false } },
                    },
                },
            });
        }
    }
})();
