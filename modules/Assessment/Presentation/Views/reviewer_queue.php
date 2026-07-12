<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$organizationUuid = (string) ($organizationUuid ?? '');
$pageTitle = 'Reviewer Queue';
$pagePurpose = 'Validate pending assessments before corrective controls are generated.';
$breadcrumbs = [
    ['label' => 'Assessments', 'url' => '/assessments'],
    ['label' => 'Reviewer Queue', 'url' => null],
];
$pageActions = [
    ['label' => 'All assessments', 'url' => '/assessments', 'class' => 'btn btn-outline-secondary', 'icon' => 'list-ul'],
    ['label' => 'Refresh', 'url' => '#', 'class' => 'btn btn-primary', 'icon' => 'arrow-clockwise', 'id' => 'reviewerQueueRefresh'],
];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div
    id="assessmentReviewerQueuePage"
    data-api-base="<?= htmlspecialchars($apiBase, ENT_QUOTES, 'UTF-8') ?>/reviewer-queue">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-widget-separator-wrapper">
                <div class="card-body card-widget-separator">
                    <div class="row gy-4 gy-sm-1">
                        <div class="col-sm-6 col-lg-3">
                            <div class="d-flex justify-content-between align-items-center card-widget-1 border-end pb-4 pb-sm-0">
                                <div>
                                    <h4 class="mb-0" id="queueStatPending">0</h4>
                                    <p class="mb-0">Pending review</p>
                                </div>
                                <div class="avatar me-sm-6"><span class="avatar-initial rounded bg-label-info text-heading"><i class="bi bi-hourglass-split"></i></span></div>
                            </div>
                            <hr class="d-none d-sm-block d-lg-none me-6">
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="d-flex justify-content-between align-items-center card-widget-2 border-end pb-4 pb-sm-0">
                                <div>
                                    <h4 class="mb-0" id="queueStatHighRisk">0</h4>
                                    <p class="mb-0">High risk</p>
                                </div>
                                <div class="avatar me-lg-6"><span class="avatar-initial rounded bg-label-danger text-heading"><i class="bi bi-exclamation-triangle"></i></span></div>
                            </div>
                            <hr class="d-none d-sm-block d-lg-none">
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="d-flex justify-content-between align-items-center border-end pb-4 pb-sm-0 card-widget-3">
                                <div>
                                    <h4 class="mb-0" id="queueStatVideo">0</h4>
                                    <p class="mb-0">Video-assisted</p>
                                </div>
                                <div class="avatar me-sm-6"><span class="avatar-initial rounded bg-label-primary text-heading"><i class="bi bi-camera-video"></i></span></div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-0" id="queueStatManual">0</h4>
                                    <p class="mb-0">Manual entries</p>
                                </div>
                                <div class="avatar"><span class="avatar-initial rounded bg-label-secondary text-heading"><i class="bi bi-pencil-square"></i></span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
        <div class="card-header d-flex flex-column flex-md-row align-items-end justify-content-between gap-3">

            <div class="col-lg-6">
                <label for="queueSearch" class="form-label">Search queue</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input id="queueSearch" class="form-control" type="search" placeholder="task, model, risk">
                </div>
            </div>
            <div class="col-md-4 col-lg-3">
                <label for="queueModelFilter" class="form-label">Model</label>
                <select id="queueModelFilter" class="form-select">
                    <option value="">All models</option>
                    <option value="reba">REBA</option>
                    <option value="rula">RULA</option>
                    <option value="niosh">NIOSH</option>
                </select>
            </div>
            <div class="col-md-4 col-lg-2 d-grid">
                <button class="btn btn-outline-secondary cursor-pointer" type="button" id="queueClearFilters">
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
                        <th>Source</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="queueTableBody">
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Loading reviewer queue...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>