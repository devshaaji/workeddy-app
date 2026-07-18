<?php
/**
 * WorkEddy Dashboard – Sneat-style stat cards + tables + charts.
 * Vanilla JS interaction via weDashboardApp.
 *
 * Variables passed from CorePageController::dashboard():
 *   $greeting     string – "Good morning, User!"
 *   $warmMessage  string – actionable message
 */
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
$greeting = $greeting ?? 'Welcome back!';
$warmMessage = $warmMessage ?? '';
?>

<div id="dashboardApp" data-api-url="/api/v1/dashboard">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-main">
            <h1 class="page-title" id="greeting"></h1>
            <p class="page-breadcrumb" id="warmMessage"></p>
        </div>
        <div class="page-header-actions">
            <a href="/tasks" class="btn btn-outline-primary">
                <i class="bi bi-list-task me-1"></i>Tasks
            </a>
            <a href="/assessments/new-manual" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>New Assessment
            </a>
        </div>
    </div>

    <!-- KPI Cards (dynamic data populated by JS) -->
    <div class="row g-4 mb-4" id="kpiRow">
        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon stat-icon-primary"><i class="bi bi-activity"></i></div>
                <div>
                    <div class="stat-value" data-kpi="totalAssessments">—</div>
                    <div class="stat-label">Total Assessments</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon stat-icon-danger"><i class="bi bi-exclamation-triangle"></i></div>
                <div>
                    <div class="stat-value" data-kpi="highRisk">—</div>
                    <div class="stat-label">High Risk</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon stat-icon-warning"><i class="bi bi-exclamation-circle"></i></div>
                <div>
                    <div class="stat-value" data-kpi="moderateRisk">—</div>
                    <div class="stat-label">Moderate Risk</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon stat-icon-info"><i class="bi bi-bar-chart-line"></i></div>
                <div>
                    <div class="stat-value" data-kpi="avgScore">—</div>
                    <div class="stat-label">Avg Risk Score</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading state -->
    <div class="text-center py-5" id="dashboardLoading">
        <div class="spinner-border text-primary"></div>
    </div>

    <!-- Error state (hidden by default) -->
    <div class="alert alert-danger d-none" id="dashboardError"></div>

    <!-- Charts row (hidden by default, shown by JS when data exists) -->
    <div class="row g-4 mb-4 d-none" id="chartRow">
        <div class="col-12 col-xl-8">
            <div class="card">
                <div class="card-header"><h6 class="mb-0 fw-semibold">Weekly Assessment Trends (12 weeks)</h6></div>
                <div class="card-body">
                    <canvas id="weeklyTrendsChart" height="220"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card h-100">
                <div class="card-header"><h6 class="mb-0 fw-semibold">Department Risk</h6></div>
                <div class="card-body p-0" id="deptHeatmapWrap">
                    <!-- Populated by JS -->
                </div>
                <div class="empty-state py-4 d-none" id="deptHeatmapEmpty">
                    <p class="mb-0 text-muted">No department data yet.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- Recent Assessments -->
        <div class="col-12 col-xl-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold">Recent Assessments</h6>
                    <a href="/assessments/new-manual" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-lg me-1"></i>New
                    </a>
                </div>

                <!-- Empty state (hidden by default) -->
                <div class="empty-state d-none" id="recentEmpty">
                    <div class="empty-state-icon"><i class="bi bi-upc-scan"></i></div>
                    <h6>No assessments yet</h6>
                    <p>Run your first ergonomic assessment to see results here.</p>
                    <a href="/assessments/new-manual" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i>New Assessment
                    </a>
                </div>

                <!-- Table (hidden by default) -->
                <div class="table-responsive d-none" id="recentTableWrap">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Type</th>
                                <th>Score</th>
                                <th>Risk</th>
                                <th class="d-none d-md-table-cell">Date</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="recentTableBody">
                        </tbody>
                    </table>
                </div>

                <!-- Footer (hidden by default) -->
                <div class="card-footer d-flex justify-content-between align-items-center py-2 d-none" id="recentFooter">
                    <span class="text-muted text-sm" id="recentCount"></span>
                    <a href="/tasks" class="btn btn-sm btn-link p-0 text-decoration-none text-primary">View all tasks →</a>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-12 col-xl-4">

            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header"><h6 class="mb-0 fw-semibold">Quick Actions</h6></div>
                <div class="card-body d-grid gap-2">
                    <a href="/assessments/new-manual" class="btn btn-outline-primary text-start">
                        <i class="bi bi-upc-scan me-2"></i>Manual Assessment
                    </a>
                    <a href="/assessments/video" class="btn btn-outline-primary text-start">
                        <i class="bi bi-camera-video me-2"></i>Video Assessment
                    </a>
                    <a href="/tasks" class="btn btn-outline-secondary text-start">
                        <i class="bi bi-list-task me-2"></i>Manage Tasks
                    </a>
                    <a href="/assessments/reviewer-queue" class="btn btn-outline-secondary text-start">
                        <i class="bi bi-person-check me-2"></i>Reviewer Queue
                    </a>
                </div>
            </div>

            <!-- Tasks by Risk -->
            <div class="card">
                <div class="card-header"><h6 class="mb-0 fw-semibold">Tasks by Risk</h6></div>

                <div class="text-center py-4 d-none" id="tasksLoading">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                </div>

                <div class="empty-state py-4 d-none" id="tasksEmpty">
                    <div class="empty-state-icon" style="width:48px;height:48px;font-size:1.25rem;">
                        <i class="bi bi-list-task"></i>
                    </div>
                    <p class="mb-0">No tasks recorded yet.</p>
                </div>

                <ul class="list-group list-group-flush d-none" id="tasksList">
                </ul>
            </div>

        </div>
    </div>

</div>
