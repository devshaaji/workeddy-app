<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Corrective Actions';
$pagePurpose = 'Track controls from assignment through verification.';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Corrective Actions', 'url' => null],
];
$pageActions = [
    ['label' => 'Review recommendations', 'url' => '/corrective-actions/recommendations', 'class' => 'btn btn-primary', 'icon' => 'clipboard-check'],
    ['label' => 'Refresh', 'url' => '#', 'class' => 'btn btn-outline-secondary', 'icon' => 'arrow-clockwise', 'id' => 'caRefreshActions'],
];
$pageScripts = ['js/modules/corrective-action.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="" data-ca-page="actions">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-widget-separator-wrapper">
                <div class="card-body card-widget-separator">
                    <div class="row gy-4 gy-sm-1">
                        <div class="col-sm-6 col-lg-3">
                            <div class="d-flex justify-content-between align-items-center card-widget-1 border-end pb-4 pb-sm-0">
                                <div>
                                    <h4 class="mb-0" id="caStatOpen">0</h4>
                                    <p class="mb-0">Open workload</p>
                                </div>
                                <div class="avatar me-sm-6">
                                    <span class="avatar-initial rounded bg-label-primary text-heading">
                                        <i class="bi bi-kanban"></i>
                                    </span>
                                </div>
                            </div>
                            <hr class="d-none d-sm-block d-lg-none me-6">
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="d-flex justify-content-between align-items-center card-widget-2 border-end pb-4 pb-sm-0">
                                <div>
                                    <h4 class="mb-0" id="caStatOverdue">0</h4>
                                    <p class="mb-0">Overdue</p>
                                </div>
                                <div class="avatar me-lg-6">
                                    <span class="avatar-initial rounded bg-label-danger text-heading">
                                        <i class="bi bi-exclamation-triangle"></i>
                                    </span>
                                </div>
                            </div>
                            <hr class="d-none d-sm-block d-lg-none">
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="d-flex justify-content-between align-items-center border-end pb-4 pb-sm-0 card-widget-3">
                                <div>
                                    <h4 class="mb-0" id="caStatCompleted">0</h4>
                                    <p class="mb-0">Completed</p>
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
                                    <h4 class="mb-0" id="caStatVerified">0</h4>
                                    <p class="mb-0">Verified</p>
                                </div>
                                <div class="avatar">
                                    <span class="avatar-initial rounded bg-label-info text-heading">
                                        <i class="bi bi-shield-check"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="caSearch" class="form-label">Search actions</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input id="caSearch" class="form-control" type="search" placeholder="Title, assessment, assignee">
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="caStatusFilter" class="form-label">Status</label>
                    <select id="caStatusFilter" class="form-select">
                        <option value="">All statuses</option>
                        <option value="assigned">Assigned</option>
                        <option value="in_progress">In progress</option>
                        <option value="completed">Completed</option>
                        <option value="verified">Verified</option>
                        <option value="overdue">Overdue</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="caPriorityFilter" class="form-label">Priority</label>
                    <select id="caPriorityFilter" class="form-select">
                        <option value="">All priorities</option>
                        <option value="critical">Critical</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-outline-secondary cursor-pointer" type="button" id="caClearFilters">
                        <i class="bi bi-x-circle me-1"></i>Clear
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="card" id="caActionsCard" data-endpoint="/api/v1/corrective-actions" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm)">
        <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
            <div>
                <h5 class="card-title mb-1">Action register</h5>
                <p class="text-muted small mb-0">One operational queue for owners, verifiers, and safety managers.</p>
            </div>
            <span class="badge bg-label-primary" id="caActionCount">0 actions</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Control</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Due</th>
                        <th>Follow-up</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="caActionsBody"></tbody>
            </table>
        </div>
    </section>
</div>