<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Worker Feedback Trends';
$pagePurpose = 'Worker voice and discomfort reporting';
$pageActions = [
    ['label' => 'Register', 'url' => '/worker-voice', 'class' => 'btn btn-outline-secondary', 'icon' => 'list-ul'],
    ['label' => 'Submit feedback', 'url' => '/worker-voice/new', 'class' => 'btn btn-primary', 'icon' => 'chat-square-text'],
];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Worker Voice', 'url' => '/worker-voice'],
    ['label' => 'Trends', 'url' => null],
];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="container-xxl flex-grow-1 py-4" id="workerVoiceTrendsPage" data-organization-uuid="<?= htmlspecialchars((string) ($organizationUuid ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <div id="workerVoiceTrendsAlert"></div>
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body d-flex justify-content-between align-items-start">
                    <div>
                        <span class="text-muted small d-block mb-2">Responses</span>
                        <h3 class="mb-1 fw-bold" id="workerVoiceTrendTotal">0</h3>
                        <p class="mb-0 text-muted small">Total worker feedback submissions.</p>
                    </div>
                    <span class="avatar-initial rounded bg-label-primary"><i class="bi bi-chat-left-text"></i></span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body d-flex justify-content-between align-items-start">
                    <div>
                        <span class="text-muted small d-block mb-2">Anonymous Rate</span>
                        <h3 class="mb-1 fw-bold" id="workerVoiceTrendAnonymousRate">0%</h3>
                        <p class="mb-0 text-muted small">Protected identity share across responses.</p>
                    </div>
                    <span class="avatar-initial rounded bg-label-info"><i class="bi bi-shield-lock"></i></span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body d-flex justify-content-between align-items-start">
                    <div>
                        <span class="text-muted small d-block mb-2">Avg Discomfort</span>
                        <h3 class="mb-1 fw-bold" id="workerVoiceTrendDiscomfort">0</h3>
                        <p class="mb-0 text-muted small">Current discomfort intensity on the 0-5 scale.</p>
                    </div>
                    <span class="avatar-initial rounded bg-label-warning"><i class="bi bi-activity"></i></span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body d-flex justify-content-between align-items-start">
                    <div>
                        <span class="text-muted small d-block mb-2">Avg 30-Day Pain</span>
                        <h3 class="mb-1 fw-bold" id="workerVoiceTrendPain30">0</h3>
                        <p class="mb-0 text-muted small">Longer-horizon strain signal across submissions.</p>
                    </div>
                    <span class="avatar-initial rounded bg-label-danger"><i class="bi bi-thermometer-half"></i></span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-1">Response Trend</h5>
                        <p class="text-muted small mb-0">Submission volume and discomfort pattern over time.</p>
                    </div>
                </div>
                <div class="card-body">
                    <div style="height: 320px;">
                        <canvas id="workerVoiceTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-1">Most Reported Regions</h5>
                    <p class="text-muted small mb-0">Distribution of reported discomfort by body region.</p>
                </div>
                <div class="card-body">
                    <div class="mx-auto mb-4" style="max-width: 220px; height: 220px;">
                        <canvas id="workerVoiceRegionChart"></canvas>
                    </div>
                    <div class="list-group list-group-flush" id="workerVoiceRegionHighlights">
                        <div class="list-group-item px-0 text-muted small">Loading region distribution...</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-1">Top Tasks</h5>
                    <p class="text-muted small mb-0">Tasks generating the highest feedback volume.</p>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush" id="workerVoiceTaskList">
                        <div class="list-group-item px-0 text-muted small">Loading task signals...</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-1">Department Signals</h5>
                    <p class="text-muted small mb-0">Where feedback volume and discomfort are concentrating.</p>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush" id="workerVoiceDepartmentList">
                        <div class="list-group-item px-0 text-muted small">Loading department signals...</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-1">Task Grouping</h5>
                    <p class="text-muted small mb-0">Grouped workload signals from linked tasks.</p>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush" id="workerVoiceTaskTypeList">
                        <div class="list-group-item px-0 text-muted small">Loading grouped signals...</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-1">Body Region Detail</h5>
                    <p class="text-muted small mb-0">Response count with discomfort and pain averages.</p>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-borderless align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Region</th>
                                <th>Responses</th>
                                <th>Discomfort</th>
                                <th>30-Day Pain</th>
                            </tr>
                        </thead>
                        <tbody id="workerVoiceBodyRegionTable">
                            <tr><td colspan="4" class="text-muted">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-1">Timeline Checkpoints</h5>
                    <p class="text-muted small mb-0">Daily volume and discomfort movement across the period.</p>
                </div>
                <div class="card-body">
                    <div class="timeline" id="workerVoiceTimelineList">
                        <div class="text-muted small">Loading timeline...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
