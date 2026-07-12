<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$assessmentId = (string) (($routeParams ?? [])['assessmentId'] ?? '');
$pageTitle = 'Assessment Detail';
$pagePurpose = 'Assessment workflow';
$pageActions = [
    ['label' => 'Review Queue', 'url' => '/assessments/reviewer-queue', 'class' => 'btn btn-outline-secondary', 'icon' => 'list-check'],
    ['label' => 'Heat Map', 'url' => '/assessments/' . rawurlencode($assessmentId) . '/heatmap', 'class' => 'btn btn-primary', 'icon' => 'activity'],
    ['label' => 'Validation Reviews', 'url' => '/assessments/' . rawurlencode($assessmentId) . '/validation-reviews', 'class' => 'btn btn-outline-secondary', 'icon' => 'people'],
    ['label' => 'Compare', 'url' => '/assessments/comparisons/new?baseline=' . rawurlencode($assessmentId), 'class' => 'btn btn-outline-secondary', 'icon' => 'intersect'],
    ['label' => 'Worker Voice', 'url' => '/worker-voice/new?assessment=' . rawurlencode($assessmentId), 'class' => 'btn btn-outline-secondary', 'icon' => 'chat-square-text'],
];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Assessments', 'url' => '/assessments'],
    ['label' => 'Assessment Detail', 'url' => null],
];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div
    class="container-xxl flex-grow-1 py-4"
    id="assessmentDetailPage"
    data-assessment-uuid="<?= htmlspecialchars($assessmentId, ENT_QUOTES, 'UTF-8') ?>"
    data-organization-uuid="<?= htmlspecialchars((string) ($organizationUuid ?? ''), ENT_QUOTES, 'UTF-8') ?>"
>
    <div class="row g-4 mb-4" id="assessmentSummaryCards">
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <span class="text-muted small d-block mb-2">Final Score</span>
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <h3 class="mb-1 fw-bold" id="assessmentFinalScore">--</h3>
                            <p class="mb-0 text-muted small" id="assessmentRiskLevel">Loading risk band</p>
                        </div>
                        <span class="badge bg-label-secondary" id="assessmentStatusBadge">Loading</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <span class="text-muted small d-block mb-2">Raw Score</span>
                    <h3 class="mb-1 fw-bold" id="assessmentRawScore">--</h3>
                    <p class="mb-0 text-muted small" id="assessmentMethod">Assessment method</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <span class="text-muted small d-block mb-2">Workflow</span>
                    <h5 class="mb-2 fw-semibold" id="assessmentReviewState">--</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-label-success d-none" id="assessmentBaselineBadge">Baseline</span>
                        <span class="badge bg-label-warning d-none" id="assessmentLockBadge">Locked</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <span class="text-muted small d-block mb-2">Reviewer</span>
                    <h5 class="mb-1 fw-semibold" id="assessmentReviewerName">Pending review</h5>
                    <p class="mb-0 text-muted small" id="assessmentScoreSource">Score source</p>
                </div>
            </div>
        </div>
    </div>

    <div id="assessmentDetailAlert"></div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h5 class="card-title mb-1">Assessment Snapshot</h5>
                        <p class="mb-0 text-muted small">Reviewer-confirmed score remains the report-facing score.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="submitForReviewBtn">
                            <i class="bi bi-send me-1"></i>Submit for Review
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="markBaselineBtn">
                            <i class="bi bi-bookmark-check me-1"></i>Mark Baseline
                        </button>
                        <a href="/assessments/comparisons/new?baseline=<?= rawurlencode($assessmentId) ?>" class="btn btn-outline-secondary btn-sm d-none" id="generateComparisonLink">
                            <i class="bi bi-intersect me-1"></i>Generate Comparison
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row mb-4">
                        <dt class="col-sm-4 text-muted">Assessment UUID</dt>
                        <dd class="col-sm-8 mb-2" id="assessmentUuidValue">--</dd>
                        <dt class="col-sm-4 text-muted">Task UUID</dt>
                        <dd class="col-sm-8 mb-2" id="assessmentTaskUuid">--</dd>
                        <dt class="col-sm-4 text-muted">Created</dt>
                        <dd class="col-sm-8 mb-0" id="assessmentCreatedAt">--</dd>
                    </dl>

                    <h6 class="fw-semibold mb-3">Risk Factors</h6>
                    <div class="d-flex flex-wrap gap-2 mb-4" id="assessmentRiskFactors"></div>

                    <h6 class="fw-semibold mb-3">Reviewer Notes</h6>
                    <p class="mb-0 text-muted" id="assessmentReviewerNotes">No reviewer notes yet.</p>
                </div>
            </div>

            <div class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Stored Ergonomic Metrics</h5>
                    <p class="mb-0 text-muted small">This section shows the saved evidence payload, not a regenerated estimate.</p>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Metric</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody id="assessmentMetricsTable">
                                <tr>
                                    <td colspan="2" class="text-muted">Loading metrics...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mt-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">AI Assistance Snapshot</h5>
                    <p class="mb-0 text-muted small">Advisory only. Reviewer-confirmed scores remain canonical.</p>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning d-flex gap-2 align-items-start mb-4" id="assessmentAiMessage">
                        <i class="bi bi-shield-exclamation"></i>
                        <div>Loading AI assistance status...</div>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-sm-4">
                            <div class="border rounded-3 p-3 h-100">
                                <span class="text-muted small d-block mb-2">AI Score</span>
                                <h4 class="mb-1 fw-bold" id="assessmentAiScore">--</h4>
                                <p class="mb-0 text-muted small" id="assessmentAiRiskLevel">Risk band</p>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="border rounded-3 p-3 h-100">
                                <span class="text-muted small d-block mb-2">Confidence</span>
                                <h4 class="mb-1 fw-bold text-capitalize" id="assessmentAiConfidenceBand">--</h4>
                                <p class="mb-0 text-muted small" id="assessmentAiConfidenceValue">No confidence yet</p>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="border rounded-3 p-3 h-100">
                                <span class="text-muted small d-block mb-2">Model Version</span>
                                <h6 class="mb-1 fw-semibold" id="assessmentAiModelVersion">--</h6>
                                <p class="mb-0 text-muted small" id="assessmentAiWorker">Worker</p>
                            </div>
                        </div>
                    </div>
                    <h6 class="fw-semibold mb-2">Flags</h6>
                    <div class="d-flex flex-wrap gap-2 mb-4" id="assessmentAiFlags">
                        <span class="text-muted small">Loading AI flags...</span>
                    </div>
                    <h6 class="fw-semibold mb-2">Timeline Preview</h6>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Signal</th>
                                </tr>
                            </thead>
                            <tbody id="assessmentAiTimeline">
                                <tr>
                                    <td colspan="2" class="text-muted">Loading AI timeline...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">Body Region Preview</h5>
                    <a class="btn btn-sm btn-outline-secondary" href="/assessments/<?= rawurlencode($assessmentId) ?>/heatmap">
                        <i class="bi bi-arrows-fullscreen me-1"></i>Open
                    </a>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="border rounded-3 p-2 h-100">
                                <span class="text-muted small d-block mb-2">Front</span>
                                <div id="assessmentHeatmapFront" class="d-flex align-items-center justify-content-center" style="min-height: 220px;"></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded-3 p-2 h-100">
                                <span class="text-muted small d-block mb-2">Back</span>
                                <div id="assessmentHeatmapBack" class="d-flex align-items-center justify-content-center" style="min-height: 220px;"></div>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small mb-0 mt-3">Intensity bands are rendered from saved body-region evidence.</p>
                </div>
            </div>

            <div class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Review Trail</h5>
                    <p class="mb-0 text-muted small">Operational actions remain gated by the canonical workflow state.</p>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0" id="assessmentActionState">
                        <li class="d-flex justify-content-between py-2 border-bottom"><span>Can edit</span><span id="canEditState">--</span></li>
                        <li class="d-flex justify-content-between py-2 border-bottom"><span>Can review</span><span id="canReviewState">--</span></li>
                        <li class="d-flex justify-content-between py-2 border-bottom"><span>Can flag</span><span id="canFlagState">--</span></li>
                        <li class="d-flex justify-content-between py-2"><span>Can mark baseline</span><span id="canBaselineState">--</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
