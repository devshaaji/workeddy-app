<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$assessmentId = (string) (($routeParams ?? [])['assessmentId'] ?? '');
$pageTitle = 'Assessment Video Evidence';
$pagePurpose = 'Review-only evidence workspace';
$pageActions = [
    ['label' => 'Assessment Detail', 'url' => '/assessments/' . rawurlencode($assessmentId), 'class' => 'btn btn-outline-secondary', 'icon' => 'clipboard-data'],
    ['label' => 'Reviewer Validation', 'url' => '/assessments/' . rawurlencode($assessmentId) . '/review', 'class' => 'btn btn-primary', 'icon' => 'shield-check'],
];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Assessments', 'url' => '/assessments'],
    ['label' => 'Video Evidence', 'url' => null],
];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div
    class="container-xxl flex-grow-1 py-4"
    id="assessmentVideoEvidencePage"
    data-assessment-uuid="<?= htmlspecialchars($assessmentId, ENT_QUOTES, 'UTF-8') ?>"
    data-organization-uuid="<?= htmlspecialchars((string) ($organizationUuid ?? ''), ENT_QUOTES, 'UTF-8') ?>"
>
    <div id="videoEvidenceAlert"></div>

    <section class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                <div>
                    <h5 class="mb-1">Status Rail</h5>
                    <p class="mb-0 text-muted small">Consent, processing, blur, retention, and review readiness from the canonical assessment payload.</p>
                </div>
            </div>
            <div class="row g-3" id="videoEvidenceStatusRail">
                <div class="col-sm-6 col-xl"><div class="border rounded-3 p-3 h-100"><span class="text-muted small d-block mb-1">Consent</span><strong id="statusRailConsent">Loading</strong></div></div>
                <div class="col-sm-6 col-xl"><div class="border rounded-3 p-3 h-100"><span class="text-muted small d-block mb-1">Processing</span><strong id="statusRailProcessing">Loading</strong></div></div>
                <div class="col-sm-6 col-xl"><div class="border rounded-3 p-3 h-100"><span class="text-muted small d-block mb-1">Blur</span><strong id="statusRailBlur">Loading</strong></div></div>
                <div class="col-sm-6 col-xl"><div class="border rounded-3 p-3 h-100"><span class="text-muted small d-block mb-1">Retention</span><strong id="statusRailRetention">Loading</strong></div></div>
                <div class="col-sm-6 col-xl"><div class="border rounded-3 p-3 h-100"><span class="text-muted small d-block mb-1">Review / Reports</span><strong id="statusRailReadiness">Loading</strong></div></div>
            </div>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-xl-4">
            <section class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Evidence</h5>
                    <p class="mb-0 text-muted small">Source evidence only: original and blurred video when available.</p>
                </div>
                <div class="list-group list-group-flush" id="videoEvidenceList">
                    <div class="list-group-item text-muted small">Loading evidence...</div>
                </div>
            </section>

            <section class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Processing Outputs</h5>
                    <p class="mb-0 text-muted small">Generated artifacts only: thumbnail, pose overlay, and any surfaced derived outputs.</p>
                </div>
                <div class="list-group list-group-flush" id="videoOutputList">
                    <div class="list-group-item text-muted small">Loading outputs...</div>
                </div>
            </section>

            <section class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Reports</h5>
                    <p class="mb-0 text-muted small">Assessment, comparison, and corrective-action reports only. Evidence files stay separate.</p>
                </div>
                <div class="card-body" id="videoReportsList">
                    <p class="text-muted small mb-0">Loading reports...</p>
                </div>
            </section>
        </div>

        <div class="col-xl-8">
            <section class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div>
                        <h5 class="card-title mb-1">Selected Asset Preview + Playback Controls</h5>
                        <p class="mb-0 text-muted small">Request signed access only when needed. Raw storage paths are never rendered.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="requestSignedAccessBtn" disabled>
                            <i class="bi bi-shield-lock me-1"></i>Request access
                        </button>
                        <a href="#" class="btn btn-primary btn-sm disabled" id="openSignedAssetBtn" target="_blank" rel="noopener">
                            <i class="bi bi-play-circle me-1"></i>Open asset
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="border rounded-3 bg-body-tertiary d-flex align-items-center justify-content-center p-3 mb-3" id="selectedAssetPreview" style="min-height: 320px;">
                        <p class="text-muted mb-0">Select an evidence asset to begin review.</p>
                    </div>
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div>
                            <span class="text-muted small d-block">Access state</span>
                            <strong id="selectedAssetAccessState">No asset selected</strong>
                        </div>
                        <div class="text-end">
                            <span class="text-muted small d-block">Signed link expiry</span>
                            <strong id="selectedAssetExpiry">--</strong>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Asset Metadata</h5>
                    <p class="mb-0 text-muted small">Artifact identity, processing state, consent version, blur state, confidence, and retention markers.</p>
                </div>
                <div class="card-body">
                    <dl class="row mb-0" id="selectedAssetMetadata">
                        <dt class="col-sm-4 text-muted">Artifact Type</dt>
                        <dd class="col-sm-8">--</dd>
                    </dl>
                </div>
            </section>

            <section class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Audit Trail</h5>
                    <p class="mb-0 text-muted small">Recent signed-link issue and playback access events for the selected asset.</p>
                </div>
                <div class="card-body" id="selectedAssetAudit">
                    <p class="text-muted small mb-0">Select an asset to load recent audit activity.</p>
                </div>
            </section>
        </div>
    </div>
</div>
