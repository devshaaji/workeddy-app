<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$assessmentId = (string) (($routeParams ?? [])['assessmentId'] ?? '');
$pageTitle = 'Validation Reviews';
$pagePurpose = 'Assessment workflow';
$pageActions = [
    ['label' => 'Review Workspace', 'url' => '/assessments/' . rawurlencode($assessmentId) . '/review', 'class' => 'btn btn-outline-secondary', 'icon' => 'clipboard-check'],
    ['label' => 'Assessment Detail', 'url' => '/assessments/' . rawurlencode($assessmentId), 'class' => 'btn btn-primary', 'icon' => 'clipboard-data'],
];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Assessments', 'url' => '/assessments'],
    ['label' => 'Validation Reviews', 'url' => null],
];
$assessmentApi = '/api/v1/assessments/' . rawurlencode($assessmentId);
$reviewsApi = $assessmentApi . '/validation-reviews';
require $v2Root . '/shared/Views/Partials/page_header.php';

$reviewerName = trim((string) ($_SESSION['username'] ?? $_SESSION['USERNAME'] ?? ''));
if ($reviewerName === '' && ($currentUserContext ?? null) !== null) {
    $reviewerName = 'User #' . $currentUserContext->userId;
}
$reviewerName = $reviewerName !== '' ? $reviewerName : 'Reviewer';
?>

<div
    class="container-xxl flex-grow-1 py-4"
    id="assessmentValidationReviewsPage"
    data-assessment-id="<?= htmlspecialchars($assessmentId, ENT_QUOTES, 'UTF-8') ?>"
    data-assessment-api="<?= htmlspecialchars($assessmentApi, ENT_QUOTES, 'UTF-8') ?>"
    data-reviews-api="<?= htmlspecialchars($reviewsApi, ENT_QUOTES, 'UTF-8') ?>">
    <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm); overflow: hidden;">
        <div class="d-flex align-items-end row">
            <div class="col-sm-8">
                <div class="card-body">
                    <div class="text-muted text-uppercase fs-tiny fw-semibold mb-1">Assessment Context</div>
                    <h4 class="card-title text-primary mb-2 fw-bold d-flex align-items-center gap-2">
                        <span id="validationAssessmentTitle">Loading assessment...</span>
                        <span class="" id="validationAssessmentStatus"></span>
                    </h4>
                    <p class="mb-4 text-muted small" id="validationAssessmentMeta">
                        Assessment details will appear here.
                    </p>

                    <div class="row g-3">
                        <div class="col-6 col-md-5">
                            <span class="text-muted small d-block mb-1 text-uppercase fw-semibold">Current Risk</span>
                            <div class="d-flex align-items-center gap-2">

                                <span class="text-dark" id="validationAssessmentRisk">--</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-7">
                            <span class="text-muted small d-block mb-1 text-uppercase fw-semibold">Body Regions</span>
                            <div class="d-flex flex-wrap gap-1 align-items-center mt-1" id="validationAssessmentRegions">
                                <span class="text-muted small">None recorded</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-4 text-center text-sm-end">
                <div class="card-body pb-0 px-0 px-md-4">
                    <img src="/assets/img/illustrations/boy-with-laptop-light.png" height="140" alt="Assessment review illustration">
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small mb-2">Agreement</div>
                    <h4 class="mb-1" id="validationAgreementOverall">--</h4>
                    <div class="text-muted small">Overall reviewer agreement</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small mb-2">Risk Match</div>
                    <h4 class="mb-1" id="validationAgreementRisk">--</h4>
                    <div class="text-muted small">Risk-level agreement</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small mb-2">Score Match</div>
                    <h4 class="mb-1" id="validationAgreementScore">--</h4>
                    <div class="text-muted small">Score agreement</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small mb-2">Review Pairs</div>
                    <h4 class="mb-1" id="validationAgreementPairs">--</h4>
                    <div class="text-muted small">Independent comparison pairs</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0">Submitted Validation Reviews</h5>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#submitValidationReviewModal">
                <i class="bi bi-plus-lg me-1"></i>Add Validation Review
            </button>
        </div>
        <div class="card-body">
            <div id="validationReviewAlert" class="mb-3"></div>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Reviewer</th>
                            <th>Round</th>
                            <th>Score</th>
                            <th>Risk</th>
                            <th>Primary</th>
                            <th>Final</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody id="validationReviewTable">
                        <tr>
                            <td colspan="7" class="text-muted">Loading review history...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Submit Validation Review Modal -->
<div class="modal fade" id="submitValidationReviewModal" tabindex="-1" aria-labelledby="submitValidationReviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="submitValidationReviewModalLabel">Submit Independent Validation Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="validationReviewForm" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="reviewerName" value="<?= htmlspecialchars($reviewerName, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="validationReviewerCredentialsInput">
                                Credentials
                                <i class="bi bi-question-circle text-muted ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Professional certifications or titles (e.g. CPE, PT, MD)"></i>
                            </label>
                            <input class="form-control" id="validationReviewerCredentialsInput" name="reviewerCredentials">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="validationReviewRound">Review Round</label>
                            <input class="form-control" type="number" id="validationReviewRound" name="reviewRound" min="1" value="1" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="validationRiskLevel">Risk Level</label>
                            <select class="form-select" id="validationRiskLevel" name="riskLevel" required>
                                <option value="">Select risk level</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="very_high">Very High</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="validationScoreRaw">Overall Score</label>
                            <input class="form-control" id="validationScoreRaw" type="number" min="0" step="0.1" name="scoreRaw" value="0" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-8 d-flex align-items-end gap-3 pb-2">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="isPrimary" id="validationPrimary">
                                <label class="form-check-label" for="validationPrimary">Primary reviewer</label>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="isFinal" id="validationFinal" checked>
                                <label class="form-check-label" for="validationFinal">Final submission</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Body Regions</label>
                            <div class="border rounded p-3" id="validationBodyRegions">Loading options...</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Risk Factors</label>
                            <div class="border rounded p-3" id="validationRiskFactors">Loading options...</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="validationNotes">Notes</label>
                            <textarea class="form-control" id="validationNotes" name="notes" rows="3"></textarea>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" type="submit">Submit Validation Review</button>
                </div>
            </form>
        </div>
    </div>
</div>