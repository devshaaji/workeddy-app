<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Supervisor Feedback Trends';
$pagePurpose = 'Worker voice and discomfort reporting';
$pageActions = [
    ['label' => 'Supervisor Observation', 'url' => '/worker-voice/supervisor/new', 'class' => 'btn btn-outline-secondary', 'icon' => 'clipboard-plus'],
    ['label' => 'Worker Voice', 'url' => '/worker-voice', 'class' => 'btn btn-primary', 'icon' => 'chat-square-text'],
];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Worker Voice', 'url' => '/worker-voice'],
    ['label' => 'Supervisor Trends', 'url' => null],
];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="py-4" id="supervisorFeedbackTrendsPage" data-requires-organization-scope="true" data-organization-uuid="<?= htmlspecialchars((string) ($organizationUuid ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-1">Filter Trends</h5>
            <p class="text-muted small mb-0">Narrow the observation set by task, department, body region, risk, and period.</p>
        </div>
        <div class="card-body">
            <form id="supervisorTrendFilters" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="supervisorTrendTask">Task</label>
                    <select class="form-select" id="supervisorTrendTask" name="taskUuid">
                        <option value="">All tasks</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="supervisorTrendDepartment">Department</label>
                    <select class="form-select" id="supervisorTrendDepartment" name="departmentUuid">
                        <option value="">All departments</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="supervisorTrendBodyRegion">Body Region</label>
                    <select class="form-select" id="supervisorTrendBodyRegion" name="bodyRegion">
                        <option value="">All body regions</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="supervisorTrendRisk">Observed Risk</label>
                    <select class="form-select" id="supervisorTrendRisk" name="observedRiskLevel">
                        <option value="">All risk levels</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="very_high">Very High</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="supervisorTrendDateFrom">From</label>
                    <input class="form-control" type="date" id="supervisorTrendDateFrom" name="dateFrom">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="supervisorTrendDateTo">To</label>
                    <input class="form-control" type="date" id="supervisorTrendDateTo" name="dateTo">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">Apply</button>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" type="button" id="supervisorTrendReset">Reset</button>
                </div>
            </form>
        </div>
    </div>

    <div id="supervisorFeedbackTrendsAlert" class="mb-3"></div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body d-flex justify-content-between align-items-start">
                    <div>
                        <span class="text-muted small d-block mb-2">Responses</span>
                        <h3 class="mb-1 fw-bold" id="supervisorTrendTotal">0</h3>
                        <p class="mb-0 text-muted small">Supervisor observations captured in the current scope.</p>
                    </div>
                    <span class="avatar-initial rounded bg-label-primary"><i class="bi bi-clipboard-check"></i></span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body d-flex justify-content-between align-items-start">
                    <div>
                        <span class="text-muted small d-block mb-2">Avg Severity</span>
                        <h3 class="mb-1 fw-bold" id="supervisorTrendSeverity">0</h3>
                        <p class="mb-0 text-muted small">Average observed severity on the 0-5 scale.</p>
                    </div>
                    <span class="avatar-initial rounded bg-label-danger"><i class="bi bi-exclamation-triangle"></i></span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body d-flex justify-content-between align-items-start">
                    <div>
                        <span class="text-muted small d-block mb-2">Avg Frequency</span>
                        <h3 class="mb-1 fw-bold" id="supervisorTrendFrequency">0</h3>
                        <p class="mb-0 text-muted small">Average repetition or occurrence level in the current scope.</p>
                    </div>
                    <span class="avatar-initial rounded bg-label-warning"><i class="bi bi-arrow-repeat"></i></span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-1">Observation Trend</h5>
                    <p class="text-muted small mb-0">Volume, severity, and frequency pattern across the filtered period.</p>
                </div>
                <div class="card-body">
                    <div style="height: 320px;">
                        <canvas id="supervisorTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-1">Most Observed Regions</h5>
                    <p class="text-muted small mb-0">Where supervisors are seeing the most repeated strain.</p>
                </div>
                <div class="card-body">
                    <div class="mx-auto mb-4" style="max-width: 220px; height: 220px;">
                        <canvas id="supervisorTrendRegionChart"></canvas>
                    </div>
                    <div class="list-group list-group-flush" id="supervisorTrendRegionHighlights">
                        <div class="list-group-item px-0 text-muted small">Loading body-region distribution...</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-1">Department Detail</h5>
                    <p class="text-muted small mb-0">Departments with the highest observation volume and severity.</p>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-borderless align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Responses</th>
                                <th>Severity</th>
                            </tr>
                        </thead>
                        <tbody id="supervisorTrendDepartmentTable">
                            <tr>
                                <td colspan="3" class="text-muted">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-1">Timeline Checkpoints</h5>
                    <p class="text-muted small mb-0">Daily observation checkpoints within the filtered period.</p>
                </div>
                <div class="card-body">
                    <div class="timeline" id="supervisorTrendTimelineList">
                        <div class="text-muted small">Loading timeline...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>