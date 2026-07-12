(function () {
    'use strict';
    var apiUrl = '/api/v1/dashboard';

    var loadingEl = document.getElementById('loadingState');
    var errorEl = document.getElementById('errorState');
    var chartRow = document.getElementById('chartRow');
    var recentBody = document.getElementById('recentBody');
    var recentWrap = document.getElementById('recentTableWrap');
    var recentEmpty = document.getElementById('recentEmpty');
    var recentFooter = document.getElementById('recentFooter');
    var recentCount = document.getElementById('recentCount');
    var tasksList = document.getElementById('tasksList');
    var tasksEmpty = document.getElementById('tasksEmpty');

    App.api.get(apiUrl).then(function (res) {
        if (loadingEl) loadingEl.classList.add('d-none');

        if (!res || !res.ok) {
            if (errorEl) {
                errorEl.textContent = (res && res.message) || 'Failed to load dashboard data';
                errorEl.classList.remove('d-none');
            }
            return;
        }

        var d = res.data || {};
        var total = d.total_assessments ?? d.totalAssessments ?? 0;
        var high = d.high_risk ?? 0;
        var mod = d.moderate_risk ?? 0;
        var avg = d.avg_score ?? 0;

        App.utils.setText(document.getElementById('kpiTotal'), total);
        App.utils.setText(document.getElementById('kpiHighRisk'), high);
        App.utils.setText(document.getElementById('kpiModerate'), mod);
        App.utils.setText(document.getElementById('kpiAvgScore'), typeof avg === 'number' ? avg.toFixed(1) : avg);

        // Trends chart
        var trends = d.weekly_trends ?? d.weeklyTrends ?? [];
        if (trends.length > 0 && chartRow && typeof Chart !== 'undefined') {
            chartRow.classList.remove('d-none');
            renderTrendsChart(trends, d);
        }

        // Recent assessments
        var scans = d.recent_scans ?? d.recentScans ?? [];
        if (recentBody && recentWrap && recentEmpty && recentFooter && recentCount) {
            if (scans.length > 0) {
                recentBody.innerHTML = scans.map(renderRow).join('');
                recentWrap.classList.remove('d-none');
                recentEmpty.classList.add('d-none');
                recentFooter.classList.remove('d-none');
                recentCount.textContent = scans.length + ' recent';
            } else {
                recentWrap.classList.add('d-none');
                recentEmpty.classList.remove('d-none');
                recentFooter.classList.add('d-none');
            }
        }

        // Tasks by risk
        var tasks = d.top_tasks ?? d.topTasks ?? [];
        if (tasksList && tasksEmpty) {
            if (tasks.length > 0) {
                tasksList.innerHTML = tasks.map(renderTask).join('');
                tasksList.classList.remove('d-none');
                tasksEmpty.classList.add('d-none');
            } else {
                tasksList.classList.add('d-none');
                tasksEmpty.classList.remove('d-none');
            }
        }

    })['catch'](function (err) {
        if (loadingEl) loadingEl.classList.add('d-none');
        if (errorEl) {
            errorEl.textContent = err && err.message ? err.message : 'Connection error';
            errorEl.classList.remove('d-none');
        }
    });

    function renderTrendsChart(trends, d) {
        var canvas = document.getElementById('trendsChart');
        if (!canvas) return;
        if (canvas._chart) canvas._chart.destroy();

        var labels = trends.map(function (w) { return w.week || w.label || ''; });
        var values = trends.map(function (w) { return w.count ?? w.total ?? 0; });

        canvas._chart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Assessments', data: values,
                    backgroundColor: 'rgba(124,58,237,0.7)',
                    borderColor: 'rgba(124,58,237,1)',
                    borderWidth: 1, borderRadius: 4,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(0,0,0,0.04)' } },
                    x: { grid: { display: false } }
                }
            }
        });

        // Risk distribution donut
        var totalScansVal = parseInt(d.total_assessments ?? d.totalAssessments ?? 0);
        var donut = document.getElementById('riskDonut');
        if (donut && typeof Chart !== 'undefined') {
            if (donut._chart) donut._chart.destroy();
            var highCount = parseInt(d.high_risk ?? d.highRisk ?? 0);
            var modCount = parseInt(d.moderate_risk ?? d.moderateRisk ?? 0);
            var lowCount = Math.max(0, totalScansVal - highCount - modCount);

            if (totalScansVal > 0) {
                donut._chart = new Chart(donut, {
                    type: 'doughnut',
                    data: {
                        labels: ['High Risk', 'Moderate', 'Low Risk'],
                        datasets: [{
                            data: [highCount, modCount, lowCount],
                            backgroundColor: ['#dc2626', '#d97706', '#16a34a'],
                            borderWidth: 0,
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        cutout: '70%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { usePointStyle: true, padding: 12, font: { size: 11 } }
                            }
                        }
                    }
                });
            }
        }
    }

    function renderRow(s) {
        var riskClass = 'badge-soft-success';
        if (s.risk_category === 'high') riskClass = 'badge-soft-danger';
        else if (s.risk_category === 'moderate') riskClass = 'badge-soft-warning';
        var score = (s.normalized_score != null) ? Number(s.normalized_score).toFixed(1) : '—';
        var date = s.created_at ? App.utils.formatDate(s.created_at) : '—';
        var name = App.utils.escapeHtml(s.task_name || s.task_id || '—');
        var type = App.utils.escapeHtml(s.scan_type || '—').toUpperCase();
        return '<tr><td class="fw-medium">' + name + '</td>' +
            '<td><span class="badge badge-soft-secondary text-uppercase">' + type + '</span></td>' +
            '<td class="fw-semibold">' + score + '</td>' +
            '<td><span class="badge ' + riskClass + '">' + App.utils.escapeHtml(s.risk_category || '—') + '</span></td>' +
            '<td class="d-none d-md-table-cell text-muted">' + date + '</td>' +
            '<td class="text-end"><div class="dropdown">' +
            '<button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>' +
            '<ul class="dropdown-menu dropdown-menu-end">' +
            '<li><a class="dropdown-item" href="/assessments/' + (s.id || '') + '"><i class="bi bi-eye me-2 text-muted"></i>View</a></li>' +
            '<li><a class="dropdown-item" href="/assessments/' + (s.id || '') + '/review"><i class="bi bi-clipboard-check me-2 text-muted"></i>Review</a></li>' +
            '</ul></div></td></tr>';
    }

    function renderTask(t) {
        var cls = 'badge-soft-success';
        if (t.highest_risk === 'high') cls = 'badge-soft-danger';
        else if (t.highest_risk === 'moderate') cls = 'badge-soft-warning';
        return '<li class="list-group-item d-flex justify-content-between align-items-center px-4 py-3">' +
            '<span class="fw-medium text-truncate me-2">' + App.utils.escapeHtml(t.name || '') + '</span>' +
            '<div class="d-flex align-items-center gap-2 flex-shrink-0">' +
            '<span class="badge ' + cls + '">' + App.utils.escapeHtml(t.highest_risk || '—') + '</span>' +
            '<span class="text-muted text-xs">' + (t.scan_count || 0) + ' scans</span></div></li>';
    }
})();
