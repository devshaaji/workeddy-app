<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Video Access Log';
$pagePurpose = 'Accountability records for sensitive video evidence access.';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Privacy', 'url' => null],
    ['label' => 'Access Log', 'url' => null],
];
$pageActions = [
    ['label' => 'Consent', 'url' => '/privacy/consent', 'class' => 'btn btn-outline-secondary', 'icon' => 'file-check'],
    ['label' => 'Retention policy', 'url' => '/privacy/retention', 'class' => 'btn btn-outline-secondary', 'icon' => 'clock-history'],
];
$pageScripts = ['js/modules/privacy.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="container-xxl flex-grow-1 py-4" id="privacyAccessLogPage">
    <!-- User context -->
    <div class="d-flex justify-content-end mb-3">
        <span class="badge bg-label-secondary d-inline-flex align-items-center gap-1 px-3 py-2" style="border-radius: var(--we-radius); font-weight: 400;">
            <i class="bi bi-person-circle"></i>
            <?= htmlspecialchars((string) ($userName ?? $userId ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>
    <!-- Stats Row -->
    <section class="row g-4 mb-4" aria-label="Access log summary">
        <div class="col-sm-6 col-xl-3">
            <article class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="d-block text-muted small">Total events</span>
                            <h3 class="mb-1 fw-bold" id="accessLogStatTotal">0</h3>
                        </div>
                        <span class="rounded p-2">
                            <i class="bi bi-journal-text fs-4 text-primary"></i>
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
                            <span class="d-block text-muted small">Unique users</span>
                            <h3 class="mb-1 fw-bold" id="accessLogStatUsers">0</h3>
                        </div>
                        <span class="rounded p-2">
                            <i class="bi bi-people fs-4 text-info"></i>
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
                            <span class="d-block text-muted small">Assessments</span>
                            <h3 class="mb-1 fw-bold" id="accessLogStatAssessments">0</h3>

                        </div>
                        <span class="rounded p-2">
                            <i class="bi bi-clipboard-pulse fs-4 text-success"></i>
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
                            <span class="d-block text-muted small">Recent period</span>
                            <h3 class="mb-1 fw-bold" id="accessLogStatRecent">0</h3>
                        </div>
                        <span class="rounded p-2 ">
                            <i class="bi bi-clock-history fs-4 text-warning"></i>
                        </span>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <!-- Access Log Table -->
    <section class="card" id="accessLogCard"
        data-endpoint="/api/v1/privacy/video-access-logs?organizationUuid=<?= htmlspecialchars((string) ($organizationUuid ?? ''), ENT_QUOTES, 'UTF-8') ?>"
        style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
        <div class="card-header d-flex flex-column flex-md-row align-items-end justify-content-between gap-2">
            <div class="col-md-4">
                <label for="accessLogPurposeFilter" class="form-label">Purpose</label>
                <select id="accessLogPurposeFilter" class="form-select">
                    <option value="">All purposes</option>
                    <option value="review">Review</option>
                    <option value="audit">Audit</option>
                    <option value="evidence">Evidence</option>
                    <option value="export">Export</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="accessLogSearch" class="form-label">Search</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input id="accessLogSearch" class="form-control" type="search" placeholder="User, assessment, IP...">
                </div>
            </div>
            <div class="col-md-3 d-grid">
                <button class="btn btn-outline-secondary cursor-pointer" type="button" id="accessLogClearFilters">
                    <i class="bi bi-x-circle me-1"></i>Clear filters
                </button>
            </div>

        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th data-sort="accessedAt">Timestamp</th>
                        <th data-sort="userId">User</th>
                        <th data-sort="purpose">Purpose</th>
                        <th data-sort="assessmentUuid">Assessment</th>
                        <th data-sort="ipAddress">IP address</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="accessLogBody"></tbody>
            </table>
        </div>
    </section>
</div>

<!-- Access Log Detail Modal -->
<div class="modal fade" id="accessLogDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Access event detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="accessLogDetailBody">
                <p class="text-muted">Select an event to view full details.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>