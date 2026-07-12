<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Generate Comparison Report';
$pagePurpose = 'Select before and after evidence for improvement proof';
$pageActions = [
    ['label' => 'Comparison register', 'url' => '/assessments/comparisons', 'class' => 'btn btn-outline-secondary', 'icon' => 'list-ul'],
];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Assessments', 'url' => '/assessments'],
    ['label' => 'Comparisons', 'url' => '/assessments/comparisons'],
    ['label' => 'Generate', 'url' => null],
];
$prefillBaseline = (string) (($query['baseline'] ?? $query['baselineAssessmentUuid'] ?? '') ?: '');
$prefillFollowUp = (string) (($query['followUp'] ?? $query['followUpAssessmentUuid'] ?? '') ?: '');
$prefillAction = (string) (($query['correctiveAction'] ?? $query['correctiveActionUuid'] ?? '') ?: '');
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div id="comparisonCreatePage"
     data-organization-uuid="<?= htmlspecialchars((string) ($organizationUuid ?? ''), ENT_QUOTES, 'UTF-8') ?>"
     data-prefill-baseline="<?= htmlspecialchars($prefillBaseline, ENT_QUOTES, 'UTF-8') ?>"
     data-prefill-follow-up="<?= htmlspecialchars($prefillFollowUp, ENT_QUOTES, 'UTF-8') ?>"
     data-prefill-action="<?= htmlspecialchars($prefillAction, ENT_QUOTES, 'UTF-8') ?>">
    <div id="comparisonCreateAlert"></div>
    <div class="row g-4">
        <div class="col-xl-5">
            <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Comparison inputs</h5>
                    <p class="mb-0 text-muted small">Choose the locked baseline, the matching reviewed follow-up, and the linked corrective action when evidence exists.</p>
                </div>
                <div class="card-body">
                    <form id="comparisonCreateForm" novalidate>
                        <div class="mb-4">
                            <label for="baselineAssessmentUuid" class="form-label d-inline-flex align-items-center gap-2">
                                <span>Locked baseline assessment</span>
                                <button type="button" class="btn btn-sm btn-icon p-0 border-0 bg-transparent text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Pick the assessment that was approved, marked as baseline, and locked before the improvement work began." aria-label="Before assessment help">
                                    <i class="bi bi-info-circle"></i>
                                </button>
                            </label>
                            <select class="form-select" id="baselineAssessmentUuid" name="baselineAssessmentUuid" required>
                                <option value="">Loading baseline candidates...</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="mb-4">
                            <label for="followUpAssessmentUuid" class="form-label d-inline-flex align-items-center gap-2">
                                <span>Reviewed follow-up assessment</span>
                                <button type="button" class="btn btn-sm btn-icon p-0 border-0 bg-transparent text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Choose the reviewed or locked follow-up assessment that shows the result after the change and uses the same scoring model." aria-label="After assessment help">
                                    <i class="bi bi-info-circle"></i>
                                </button>
                            </label>
                            <select class="form-select" id="followUpAssessmentUuid" name="followUpAssessmentUuid" required>
                                <option value="">Loading follow-up candidates...</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="mb-4">
                            <label for="correctiveActionUuid" class="form-label d-inline-flex align-items-center gap-2">
                                <span>Linked corrective action</span>
                                <button type="button" class="btn btn-sm btn-icon p-0 border-0 bg-transparent text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="Optional. Link the action record when you want the report to carry improvement workflow evidence." aria-label="Linked corrective action help">
                                    <i class="bi bi-info-circle"></i>
                                </button>
                            </label>
                            <select class="form-select" id="correctiveActionUuid" name="correctiveActionUuid">
                                <option value="">No linked corrective action</option>
                            </select>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary" id="generateComparisonBtn"><i class="bi bi-intersect me-1"></i>Generate Report</button>
                            <button type="button" class="btn btn-outline-secondary" id="previewAssessmentsBtn"><i class="bi bi-eye me-1"></i>Preview Inputs</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Readiness checklist</h5>
                </div>
                <div class="card-body">
                    <div id="comparisonEligibilityPanel" class="small text-muted">
                        Select a before and after assessment to validate comparison readiness.
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="card-title mb-1">Assessment preview</h5>
                        <p class="mb-0 text-muted small">Side-by-side validation before report generation.</p>
                    </div>
                    <span class="badge bg-label-info" id="comparisonPreviewModel">Model --</span>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100">
                                <h6 class="fw-semibold">Baseline</h6>
                                <div id="baselinePreviewPanel" class="text-muted small">No preview yet.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100">
                                <h6 class="fw-semibold">Follow-up</h6>
                                <div id="followUpPreviewPanel" class="text-muted small">No preview yet.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
