<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$assessmentId = (string) (($routeParams ?? [])['assessmentId'] ?? '');
$pageTitle = 'Reviewer Validation';
$pagePurpose = 'Assessment workflow';
$pageActions = [
    ['label' => 'Reviewer Queue', 'url' => '/assessments/reviewer-queue', 'class' => 'btn btn-outline-secondary', 'icon' => 'list-check'],
    ['label' => 'Validation Reviews', 'url' => '/assessments/' . rawurlencode($assessmentId) . '/validation-reviews', 'class' => 'btn btn-outline-secondary', 'icon' => 'people'],
    ['label' => 'Assessment Detail', 'url' => '/assessments/' . rawurlencode($assessmentId), 'class' => 'btn btn-primary', 'icon' => 'clipboard-data'],
];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Assessments', 'url' => '/assessments'],
    ['label' => 'Reviewer Validation', 'url' => null],
];
require $v2Root . '/shared/Views/Partials/page_header.php';

$reviewerName = trim((string) ($_SESSION['username'] ?? $_SESSION['USERNAME'] ?? ''));
if ($reviewerName === '' && ($currentUserContext ?? null) !== null) {
    $reviewerName = 'User #' . $currentUserContext->userId;
}
$reviewerName = $reviewerName !== '' ? $reviewerName : 'Reviewer';
?>


<div class="row g-4 mb-4"
    id="assessmentReviewPage"
    data-assessment-uuid="<?= htmlspecialchars($assessmentId, ENT_QUOTES, 'UTF-8') ?>"
    data-organization-uuid="<?= htmlspecialchars((string) ($organizationUuid ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <div class="col-12">
        <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h5 class="card-title mb-1">Decision Workspace</h5>
                    <p class="mb-0 text-muted small">Review the stored evidence, then approve or flag with an audit-safe explanation.</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-danger btn-de-action" data-bs-toggle="modal" data-bs-target="#flagAssessmentModal">
                        <i class="bi bi-flag me-1"></i>Flag for Rework
                    </button>
                    <button type="button" class="btn btn-primary btn-de-action" data-bs-toggle="modal" data-bs-target="#approveAssessmentModal">
                        <i class="bi bi-check2-circle me-1"></i>Approve Assessment
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="reviewAlert"></div>
                <div class="row g-3 mb-4">
                    <div class="col-sm-4">
                        <div class="border rounded-3 p-3 h-100">
                            <span class="text-muted small d-block mb-2">Final Score</span>
                            <h3 class="mb-1 fw-bold" id="reviewFinalScore">--</h3>
                            <p class="mb-0 text-muted small" id="reviewRiskLevel">Risk band</p>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="border rounded-3 p-3 h-100">
                            <span class="text-muted small d-block mb-2">Raw Score</span>
                            <h3 class="mb-1 fw-bold" id="reviewRawScore">--</h3>
                            <p class="mb-0 text-muted small" id="reviewMethod">Method</p>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="border rounded-3 p-3 h-100">
                            <span class="text-muted small d-block mb-2">State</span>
                            <h5 class="mb-1 fw-semibold" id="reviewStatusText">--</h5>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge bg-label-success d-none" id="reviewBaselineBadge">Baseline</span>
                                <span class="badge bg-label-warning d-none" id="reviewLockBadge">Locked</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="fw-semibold mb-2">Reviewer Notes Context</h6>
                    <p class="mb-0 text-muted" id="reviewExistingNotes">No existing reviewer notes.</p>
                </div>

                <div class="mb-4 d-none" id="reviewAiGuardrailSection">
                    <h6 class="fw-semibold mb-2">AI Assistance Guardrail</h6>
                    <div class="alert alert-warning d-flex gap-2 align-items-start mb-3" id="reviewAiMessage">
                        <i class="bi bi-shield-exclamation"></i>
                        <div>Loading AI assistance status...</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-4">
                            <div class="border rounded-3 p-3 h-100">
                                <span class="text-muted small d-block mb-2">AI Score</span>
                                <h4 class="mb-1 fw-bold" id="reviewAiScore">--</h4>
                                <p class="mb-0 text-muted small" id="reviewAiRiskLevel">Risk band</p>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="border rounded-3 p-3 h-100">
                                <span class="text-muted small d-block mb-2">Confidence</span>
                                <h4 class="mb-1 fw-bold text-capitalize" id="reviewAiConfidenceBand">--</h4>
                                <p class="mb-0 text-muted small" id="reviewAiConfidenceValue">No confidence yet</p>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="border rounded-3 p-3 h-100">
                                <span class="text-muted small d-block mb-2">Model Version</span>
                                <h6 class="mb-1 fw-semibold" id="reviewAiModelVersion">--</h6>
                                <p class="mb-0 text-muted small" id="reviewAiWorker">Worker</p>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3" id="reviewAiFlags">
                        <span class="text-muted small">Loading AI flags...</span>
                    </div>
                </div>

                <div class="mb-0">
                    <h6 class="fw-semibold mb-3">Evidence Summary</h6>
                    <div class="d-flex flex-wrap gap-2 mb-3" id="reviewRiskFactors"></div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Metric</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody id="reviewMetricsTable">
                                <tr>
                                    <td colspan="2" class="text-muted">Loading metrics...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approve Assessment Modal -->
<div class="modal fade" id="approveAssessmentModal" tabindex="-1" aria-labelledby="approveAssessmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approveAssessmentModalLabel">Approve Assessment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="approveAssessmentForm" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="reviewerName" value="<?= htmlspecialchars($reviewerName, ENT_QUOTES, 'UTF-8') ?>">
                    
                    <div class="mb-3">
                        <label class="form-label" for="reviewerCredentialsInput">
                            Credentials
                            <i class="bi bi-question-circle text-muted ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Professional certifications or titles (e.g. CPE, PT, MD)"></i>
                        </label>
                        <input type="text" class="form-control" id="reviewerCredentialsInput" name="reviewerCredentials">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="adjustedScoreInput">Adjusted Final Score</label>
                        <input type="number" class="form-control" step="0.1" min="0" id="adjustedScoreInput" name="adjustedScore">
                        <div class="form-text">Leave blank to keep the current reviewed score.</div>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="adjustmentReasonInput">Adjustment Reason</label>
                        <textarea class="form-control" id="adjustmentReasonInput" name="adjustmentReason" rows="3"></textarea>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="reviewerNotesInput">Reviewer Notes</label>
                        <textarea class="form-control" id="reviewerNotesInput" name="reviewerNotes" rows="3"></textarea>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="lockAssessmentInput" name="lock" checked>
                        <label class="form-check-label" for="lockAssessmentInput">Lock assessment after approval</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="approveAssessmentBtn">
                        <i class="bi bi-check2-circle me-1"></i>Approve Assessment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Flag Assessment Modal -->
<div class="modal fade" id="flagAssessmentModal" tabindex="-1" aria-labelledby="flagAssessmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="flagAssessmentModalLabel">Flag Assessment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="flagAssessmentForm" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="reviewerName" value="<?= htmlspecialchars($reviewerName, ENT_QUOTES, 'UTF-8') ?>">
                    
                    <div class="mb-3">
                        <label class="form-label" for="flagReviewerCredentialsInput">
                            Credentials
                            <i class="bi bi-question-circle text-muted ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Professional certifications or titles (e.g. CPE, PT, MD)"></i>
                        </label>
                        <input type="text" class="form-control" id="flagReviewerCredentialsInput" name="reviewerCredentials">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="flagReviewerNotesInput">Flag Notes</label>
                        <textarea class="form-control" id="flagReviewerNotesInput" name="reviewerNotes" rows="4" required></textarea>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-outline-danger" id="flagAssessmentBtn">
                        <i class="bi bi-flag me-1"></i>Flag for Rework
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>