<?php
declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Video Consent';
$pagePurpose = 'Record and manage worker video consent for ergonomic assessments.';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Privacy', 'url' => null],
    ['label' => 'Consent', 'url' => null],
];
$pageActions = [
    ['label' => 'Retention policy', 'url' => '/privacy/retention', 'class' => 'btn btn-outline-secondary', 'icon' => 'clock-history'],
    ['label' => 'Access logs', 'url' => '/privacy/video-access-log', 'class' => 'btn btn-outline-secondary', 'icon' => 'journal-text'],
];
$pageScripts = ['js/modules/privacy.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="container-xxl flex-grow-1 py-4" id="privacyConsentPage" data-requires-organization-scope="true">
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
                Consent must be freely given, informed, and documented before any video capture begins.
            </p>
        </div>
    </div>

    <!-- Stats Row -->
    <section class="row g-4 mb-4" aria-label="Consent summary">
        <div class="col-sm-6 col-xl-3">
            <article class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="d-block text-muted small">Total consents</span>
                            <h3 class="mb-1 fw-bold" id="consentStatTotal">—</h3>
                            <small class="text-muted">All records</small>
                        </div>
                        <span class="rounded p-2" style="background: var(--we-primary-light)">
                            <i class="bi bi-file-check fs-4" style="color: var(--we-primary)"></i>
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
                            <span class="d-block text-muted small">Assessments covered</span>
                            <h3 class="mb-1 fw-bold" id="consentStatAssessments">—</h3>
                            <small class="text-muted">Unique assessments</small>
                        </div>
                        <span class="rounded p-2 bg-label-info">
                            <i class="bi bi-clipboard-pulse fs-4"></i>
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
                            <span class="d-block text-muted small">Accepted</span>
                            <h3 class="mb-1 fw-bold" id="consentStatAccepted">—</h3>
                            <small class="text-muted">Notice accepted</small>
                        </div>
                        <span class="rounded p-2 bg-label-success">
                            <i class="bi bi-check2-circle fs-4"></i>
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
                            <span class="d-block text-muted small">Pending capture</span>
                            <h3 class="mb-1 fw-bold" id="consentStatPending">—</h3>
                            <small class="text-muted">Awaiting recording</small>
                        </div>
                        <span class="rounded p-2 bg-label-warning">
                            <i class="bi bi-hourglass-split fs-4"></i>
                        </span>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <!-- Record Consent Form -->
    <section class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-pencil-square me-2" style="color: var(--we-primary)"></i>Record video consent
            </h5>
        </div>
        <div class="card-body">
            <form id="consentForm" novalidate>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label for="consentAssessmentUuid" class="form-label">Assessment <span class="text-danger">*</span></label>
                        <select id="consentAssessmentUuid" name="assessmentUuid" class="form-select" required>
                            <option value="">Loading assessments...</option>
                        </select>
                        <div class="form-text">Select the assessment this consent applies to.</div>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="col-md-6">
                        <label for="consentStorageFile" class="form-label">Storage file <span class="text-muted">(optional)</span></label>
                        <input type="text" id="consentStorageFile" name="storageFileUuid" class="form-control" placeholder="Linked automatically during video upload">
                        <div class="form-text">Leave blank for manual consent recording. UUID is linked automatically during video capture.</div>
                    </div>

                    <div class="col-12">
                        <label for="consentTextVersion" class="form-label">Consent notice text <span class="text-danger">*</span></label>
                        <textarea id="consentTextVersion" name="textVersion" class="form-control" rows="3" required
                            placeholder="I understand that video recordings of my work activities will be used solely for ergonomic risk assessment and safety improvement purposes...">I understand that video recordings of my work activities will be used solely for ergonomic risk assessment and safety improvement purposes. I understand that these recordings will not be used for discipline, surveillance, or performance evaluation. I give my informed consent freely.</textarea>
                        <div class="form-text">The exact consent language shown to and accepted by the worker.</div>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" id="consentAcceptedNotice" name="acceptedNotice" class="form-check-input" value="1" required>
                            <label for="consentAcceptedNotice" class="form-check-label">
                                I confirm the worker has reviewed and accepted the privacy notice. <span class="text-danger">*</span>
                            </label>
                            <div class="invalid-feedback">You must confirm that the worker accepted the notice.</div>
                        </div>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary" id="consentSubmitBtn">
                            <i class="bi bi-check-lg me-1"></i>Record consent
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- Consent Records Table -->
    <section class="card" id="consentRecordsCard" data-endpoint="/api/v1/privacy/video-consents?organizationUuid=<?= htmlspecialchars((string) ($organizationUuid ?? ''), ENT_QUOTES, 'UTF-8') ?>"
             style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
        <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
            <div>
                <h5 class="card-title mb-1">Consent records</h5>
                <p class="text-muted small mb-0">Audit trail of video consent capture events.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <select id="consentAssessmentFilter" class="form-select form-select-sm" style="max-width: 200px;">
                    <option value="">All assessments</option>
                </select>
                <button class="btn btn-sm btn-outline-secondary" type="button" id="consentClearFilters" title="Clear filters">
                    <i class="bi bi-x-circle"></i>
                </button>
                <span class="badge bg-label-primary" id="consentResultCount">0 records</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th data-sort="acceptedAt">Date</th>
                        <th data-sort="assessmentUuid">Assessment</th>
                        <th data-sort="textVersion">Consent version</th>
                        <th data-sort="userId">Recorded by</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="consentRecordsBody"></tbody>
            </table>
        </div>
    </section>
</div>

<!-- Consent Detail Modal -->
<div class="modal fade" id="consentDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Consent record detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="consentDetailBody">
                <p class="text-muted">Select a record to view details.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
