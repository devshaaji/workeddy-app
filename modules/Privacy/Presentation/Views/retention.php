<?php
declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Retention Policy';
$pagePurpose = 'Manage how video evidence is stored, retained, and disposed.';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Privacy', 'url' => null],
    ['label' => 'Retention', 'url' => null],
];
$pageActions = [
    ['label' => 'Consent', 'url' => '/privacy/consent', 'class' => 'btn btn-outline-secondary', 'icon' => 'file-check'],
    ['label' => 'Access logs', 'url' => '/privacy/video-access-log', 'class' => 'btn btn-outline-secondary', 'icon' => 'journal-text'],
];
$pageScripts = ['js/modules/privacy.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="container-xxl flex-grow-1 py-4" id="privacyRetentionPage" data-requires-organization-scope="true">
    <!-- User context -->
    <div class="d-flex justify-content-end mb-3">
        <span class="badge bg-label-secondary d-inline-flex align-items-center gap-1 px-3 py-2" style="border-radius: var(--we-radius); font-weight: 400;">
            <i class="bi bi-person-circle"></i>
            <?= htmlspecialchars((string) ($userName ?? $userId ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>

    <!-- Privacy Notice Banner -->
    <div class="alert alert-info d-flex align-items-start gap-3 mb-4" role="note" style="border-radius: var(--we-radius-lg); border-left: 4px solid var(--we-primary);">
        <i class="bi bi-shield-check fs-4 mt-1" style="color: var(--we-primary);"></i>
        <div>
            <strong class="d-block mb-1">Your privacy commitment</strong>
            <p class="mb-0 text-secondary">
                WorkEddy is designed for ergonomic risk prevention and safety improvement, not worker discipline or productivity surveillance.
                Retention settings control how long video evidence is stored and whether raw footage is preserved or deleted after processing.
            </p>
        </div>
    </div>

    <!-- Current Policy Display -->
    <section class="row g-4 mb-4" aria-label="Current retention policy">
        <div class="col-sm-6 col-xl-3">
            <article class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="d-block text-muted small">Current policy</span>
                            <h3 class="mb-1 fw-bold fs-5" id="retentionCurrentPolicy">—</h3>
                            <small class="text-muted">Raw video retention rule</small>
                        </div>
                        <span class="rounded p-2" style="background: var(--we-primary-light)">
                            <i class="bi bi-shield-lock fs-4" style="color: var(--we-primary)"></i>
                        </span>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-sm-6 col-xl-3">
            <article class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="d-block text-muted small">Retention period</span>
                            <h3 class="mb-1 fw-bold" id="retentionCurrentDays">—</h3>
                            <small class="text-muted">Before auto-cleanup</small>
                        </div>
                        <span class="rounded p-2 bg-label-info">
                            <i class="bi bi-calendar-clock fs-4"></i>
                        </span>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-sm-6 col-xl-3">
            <article class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="d-block text-muted small">Screenshots only</span>
                            <h3 class="mb-1 fw-bold" id="retentionCurrentScreenshots">—</h3>
                            <small class="text-muted">Raw video excluded</small>
                        </div>
                        <span class="rounded p-2 bg-label-secondary">
                            <i class="bi bi-camera fs-4"></i>
                        </span>
                    </div>
                </div>
            </article>
        </div>
        <div class="col-sm-6 col-xl-3">
            <article class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="d-block text-muted small">Pilot evidence</span>
                            <h3 class="mb-1 fw-bold" id="retentionCurrentEvidence">—</h3>
                            <small class="text-muted">Retained for reporting</small>
                        </div>
                        <span class="rounded p-2 bg-label-warning">
                            <i class="bi bi-bar-chart fs-4"></i>
                        </span>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <!-- Update Policy Form -->
    <section class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-gear me-2" style="color: var(--we-primary)"></i>Configure retention policy
            </h5>
        </div>
        <div class="card-body">
            <form id="retentionForm" novalidate>
                <div class="row g-4">
                    <!-- Raw Video Policy -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">Raw video retention <span class="text-danger">*</span></label>
                        <p class="text-muted small mb-3">Choose what happens to raw video footage after AI processing completes.</p>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="form-check card card-body p-3" style="border-radius: var(--we-radius); border: 1px solid var(--we-border);">
                                    <input class="form-check-input" type="radio" name="rawVideoPolicy" id="policyRetainReview"
                                           value="retain_for_review" checked>
                                    <label class="form-check-label fw-medium" for="policyRetainReview">
                                        Retain for review
                                        <span class="d-block text-muted small fw-normal mt-1">Raw video kept for reviewer access and re-examination.</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check card card-body p-3" style="border-radius: var(--we-radius); border: 1px solid var(--we-border);">
                                    <input class="form-check-input" type="radio" name="rawVideoPolicy" id="policyDeleteAfter"
                                           value="delete_after_processing">
                                    <label class="form-check-label fw-medium" for="policyDeleteAfter">
                                        Delete after processing
                                        <span class="d-block text-muted small fw-normal mt-1">Raw video deleted once scoring completes. Screenshots retained.</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check card card-body p-3" style="border-radius: var(--we-radius); border: 1px solid var(--we-border);">
                                    <input class="form-check-input" type="radio" name="rawVideoPolicy" id="policyDeidentified"
                                           value="retain_deidentified_only">
                                    <label class="form-check-label fw-medium" for="policyDeidentified">
                                        Retain de-identified only
                                        <span class="d-block text-muted small fw-normal mt-1">Faces blurred, metadata stripped. Raw video deleted.</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div id="retentionPolicyDescription" class="alert alert-secondary mt-3 mb-0 small" role="note" style="border-radius: var(--we-radius);">
                            Select a policy above to see its operational impact.
                        </div>
                    </div>

                    <!-- Retention Days -->
                    <div class="col-md-4">
                        <label for="retentionDays" class="form-label">Retention period (days)</label>
                        <div class="input-group">
                            <input type="number" id="retentionDays" name="retentionDays" class="form-control"
                                   value="90" min="0" max="3650" required>
                            <span class="input-group-text">days</span>
                        </div>
                        <div class="form-text">Set to 0 for indefinite retention within policy rules. Maximum 10 years (3650 days).</div>
                        <div class="invalid-feedback"></div>
                    </div>

                    <!-- Toggles -->
                    <div class="col-md-8">
                        <label class="form-label d-block">&nbsp;</label>
                        <div class="d-flex flex-column gap-3">
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="retentionScreenshotsOnly" name="retainScreenshotsOnly" value="1">
                                <label class="form-check-label" for="retentionScreenshotsOnly">
                                    Screenshots only
                                    <span class="d-block text-muted small fw-normal">When enabled, only still frame captures are retained — no video playback.</span>
                                </label>
                            </div>
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="retentionPilotEvidence" name="retainForPilotEvidence" value="1">
                                <label class="form-check-label" for="retentionPilotEvidence">
                                    Retain for pilot evidence
                                    <span class="d-block text-muted small fw-normal">When enabled, evidence is preserved for pilot-site reporting and export even after the retention period.</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary" id="retentionSaveBtn">
                            <i class="bi bi-floppy me-1"></i>Save policy
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- Last Updated Info -->
    <section class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-md-6">
                    <h6 class="mb-1">Policy audit trail</h6>
                    <p class="text-muted small mb-0">
                        Retention policy changes are recorded in the system audit log.
                        Review history in the
                        <a href="/audit/logs" class="text-decoration-none">Audit Logs</a> section.
                    </p>
                </div>
                <div class="col-md-3 text-md-end">
                    <small class="text-muted d-block">Last updated by</small>
                    <span class="fw-medium" id="retentionUpdatedBy">—</span>
                </div>
                <div class="col-md-3 text-md-end">
                    <small class="text-muted d-block">Last updated at</small>
                    <span class="fw-medium" id="retentionUpdatedAt">—</span>
                </div>
            </div>
        </div>
    </section>
</div>
