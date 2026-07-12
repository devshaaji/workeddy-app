<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$organizationUuid = (string) ($organizationUuid ?? '');
$pageTitle = 'Assessments';
$pagePurpose = 'Create, review, and compare ergonomic risk assessments.';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Assessments', 'url' => null],
];
$pageActions = [
    ['label' => 'New manual', 'url' => '/assessments/new-manual', 'class' => 'btn btn-primary', 'icon' => 'plus-lg'],
    ['label' => 'Video capture', 'url' => '/assessments/video', 'class' => 'btn btn-outline-secondary', 'icon' => 'camera-video'],
    ['label' => 'Reviewer queue', 'url' => '/assessments/reviewer-queue', 'class' => 'btn btn-outline-secondary', 'icon' => 'clipboard-check'],
    ['label' => 'Comparisons', 'url' => '/assessments/comparisons', 'class' => 'btn btn-outline-secondary', 'icon' => 'arrow-left-right'],
];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div
    id="assessmentIndexPage"
    data-api-base="<?= htmlspecialchars($apiBase, ENT_QUOTES, 'UTF-8') ?>">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-widget-separator-wrapper">
                <div class="card-body card-widget-separator">
                    <div class="row gy-4 gy-sm-1">
                        <div class="col-sm-6 col-lg-3">
                            <div class="d-flex justify-content-between align-items-center card-widget-1 border-end pb-4 pb-sm-0">
                                <div>
                                    <h4 class="mb-0" id="assessmentStatTotal">0</h4>
                                    <p class="mb-0">Total assessments</p>
                                </div>
                                <div class="avatar me-sm-6">
                                    <span class="avatar-initial rounded bg-label-primary text-heading">
                                        <i class="bi bi-clipboard2-pulse"></i>
                                    </span>
                                </div>
                            </div>
                            <hr class="d-none d-sm-block d-lg-none me-6">
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="d-flex justify-content-between align-items-center card-widget-2 border-end pb-4 pb-sm-0">
                                <div>
                                    <h4 class="mb-0" id="assessmentStatPending">0</h4>
                                    <p class="mb-0">Pending review</p>
                                </div>
                                <div class="avatar me-lg-6">
                                    <span class="avatar-initial rounded bg-label-info text-heading">
                                        <i class="bi bi-hourglass-split"></i>
                                    </span>
                                </div>
                            </div>
                            <hr class="d-none d-sm-block d-lg-none">
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="d-flex justify-content-between align-items-center border-end pb-4 pb-sm-0 card-widget-3">
                                <div>
                                    <h4 class="mb-0" id="assessmentStatReviewed">0</h4>
                                    <p class="mb-0">Reviewed</p>
                                </div>
                                <div class="avatar me-sm-6">
                                    <span class="avatar-initial rounded bg-label-success text-heading">
                                        <i class="bi bi-check2-circle"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-0" id="assessmentStatLocked">0</h4>
                                    <p class="mb-0">Locked proof</p>
                                </div>
                                <div class="avatar">
                                    <span class="avatar-initial rounded bg-label-warning text-heading">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="card" id="assessmentTableCard" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
        <div class="card-header d-flex flex-column flex-md-row align-items-end justify-content-between gap-3">

            <div class="col-lg-4">
                <label for="assessmentSearch" class="form-label">Search assessments</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input id="assessmentSearch" class="form-control" type="search" placeholder="task, model, reviewer">
                </div>
            </div>
            <div class="col-md-4 col-lg-3">
                <label for="assessmentStatusFilter" class="form-label">Status</label>
                <select id="assessmentStatusFilter" class="form-select">
                    <option value="">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="pending_review">Pending review</option>
                    <option value="reviewed">Reviewed</option>
                    <option value="locked">Locked</option>
                    <option value="flagged">Flagged</option>
                </select>
            </div>
            <div class="col-md-4 col-lg-2">
                <label for="assessmentModelFilter" class="form-label">Model</label>
                <select id="assessmentModelFilter" class="form-select">
                    <option value="">All models</option>
                    <option value="reba">REBA</option>
                    <option value="rula">RULA</option>
                    <option value="niosh">NIOSH</option>
                </select>
            </div>
            <div class="col-md-4 col-lg-2 d-grid">
                <button class="btn btn-outline-secondary cursor-pointer" type="button" id="assessmentClearFilters">
                    <i class="bi bi-x-circle me-1"></i>Clear
                </button>
            </div>


        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Assessment</th>
                        <th>Model</th>
                        <th>Score</th>
                        <th>Risk</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="assessmentTableBody">
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Loading assessments...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>