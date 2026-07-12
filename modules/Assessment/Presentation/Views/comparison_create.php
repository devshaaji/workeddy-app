<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Generate Comparison Report';
$pagePurpose = 'Link locked baseline to reviewed follow-up';
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

<div id="comparisonCreatePage" data-prefill-baseline="<?= htmlspecialchars($prefillBaseline, ENT_QUOTES, 'UTF-8') ?>" data-prefill-follow-up="<?= htmlspecialchars($prefillFollowUp, ENT_QUOTES, 'UTF-8') ?>" data-prefill-action="<?= htmlspecialchars($prefillAction, ENT_QUOTES, 'UTF-8') ?>">
    <div id="comparisonCreateAlert"></div>
    <div class="row g-4">
        <div class="col-xl-5">
            <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Comparison inputs</h5>
                    <p class="mb-0 text-muted small">Baseline must be locked and marked. Follow-up must already be reviewed.</p>
                </div>
                <div class="card-body">
                    <form id="comparisonCreateForm" novalidate>
                        <div class="mb-3"><label for="baselineAssessmentUuid" class="form-label">Baseline assessment UUID</label><input type="text" class="form-control" id="baselineAssessmentUuid" name="baselineAssessmentUuid" value="<?= htmlspecialchars($prefillBaseline, ENT_QUOTES, 'UTF-8') ?>" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="mb-3"><label for="followUpAssessmentUuid" class="form-label">Follow-up assessment UUID</label><input type="text" class="form-control" id="followUpAssessmentUuid" name="followUpAssessmentUuid" value="<?= htmlspecialchars($prefillFollowUp, ENT_QUOTES, 'UTF-8') ?>" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="mb-4"><label for="correctiveActionUuid" class="form-label">Corrective action UUID</label><input type="text" class="form-control" id="correctiveActionUuid" name="correctiveActionUuid" value="<?= htmlspecialchars($prefillAction, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="form-text">Optional. Include action workflow evidence when relevant.</div>
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
                    <h5 class="card-title mb-1">Proof rules</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="d-flex gap-2 mb-3"><i class="bi bi-check-circle text-success"></i><span>Same org and same scoring model only.</span></li>
                        <li class="d-flex gap-2 mb-3"><i class="bi bi-check-circle text-success"></i><span>Uses reviewed final scores, not raw draft input.</span></li>
                        <li class="d-flex gap-2 mb-3"><i class="bi bi-check-circle text-success"></i><span>Body-region delta shows improvement, worsening, unchanged.</span></li>
                        <li class="d-flex gap-2"><i class="bi bi-check-circle text-success"></i><span>Risk reduction is estimated evidence, not guaranteed outcome.</span></li>
                    </ul>
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