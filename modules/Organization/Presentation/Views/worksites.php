<?php

declare(strict_types=1);
$v2Root = dirname(__DIR__, 4);
$organizationId = (string)(($routeParams ?? [])['id'] ?? ($organizationUuid ?? ''));
$pageTitle = 'Worksites';
$pagePurpose = 'Manage physical sites where work is performed and assessed.';
$pageActions = [
    ['label' => 'Add Worksite', 'url' => '#', 'class' => 'btn btn-primary', 'icon' => 'plus-lg', 'id' => 'btnAddWorksite'],
];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Organization', 'url' => '/organizations/' . $organizationId],
    ['label' => 'Worksites'],
];
require $v2Root . '/shared/Views/Partials/page_header.php';
$eOrgId = htmlspecialchars($organizationId, ENT_QUOTES, 'UTF-8');
?>
<div id="worksitesPage"
    data-api-base="/api/v1/organizations/<?= $eOrgId ?>/worksites"
    data-org-id="<?= $eOrgId ?>">

    <div class="row g-4 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div><span class="d-block text-muted small">Total Worksites</span>
                            <h3 class="mb-0 fw-bold" id="ws-stat-total">—</h3>
                        </div>
                        <div class="rounded p-2" style="background:var(--we-primary-light)"><i class="bi bi-geo-alt-fill fs-4" style="color:var(--we-primary)"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div><span class="d-block text-muted small">Active</span>
                            <h3 class="mb-0 fw-bold" id="ws-stat-active">—</h3>
                        </div>
                        <div class="rounded p-2" style="background:rgba(34,197,94,.1)"><i class="bi bi-check-circle-fill fs-4 text-success"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card" id="worksitesCard" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
        <div class="card-header d-flex flex-wrap align-items-center gap-3 border-bottom">
            <h5 class="card-title mb-0 me-auto">Worksites</h5>
            <input type="search" class="form-control form-control-sm" id="ws-search" placeholder="Search worksites…" style="width:200px">
            <select class="form-select form-select-sm" id="ws-status-filter" style="width:140px">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <span class="badge bg-label-primary" id="ws-result-count">0</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Worksite Name</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="worksitesBody"></tbody>
            </table>
        </div>
        <div class="card-footer d-flex align-items-center gap-3 flex-wrap">
            <span class="text-muted small" id="ws-page-info"></span>
            <div class="ms-auto d-flex gap-2">
                <button class="btn btn-outline-secondary btn-sm" id="ws-prev" disabled><i class="bi bi-chevron-left"></i></button>
                <button class="btn btn-outline-secondary btn-sm" id="ws-next" disabled><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Worksite Modal -->
<div class="modal fade" id="worksiteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="worksiteModalTitle"><i class="bi bi-geo-alt me-2" style="color:var(--we-primary)"></i>Add Worksite</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="worksiteModalAlert" class="mb-3"></div>
                <form id="worksiteForm" novalidate>
                    <input type="hidden" id="worksiteId">
                    <div class="mb-3">
                        <label for="wsName" class="form-label fw-medium">Worksite Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="wsName" name="name" required placeholder="e.g. Factory Floor A">
                        <div class="form-text">The name staff and supervisors will use to identify this work location.</div>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="wsLocation" class="form-label fw-medium">Physical Location</label>
                        <input type="text" class="form-control" id="wsLocation" name="location" placeholder="e.g. Building 3, Level 2">
                        <div class="form-text">Address or internal location reference.</div>
                    </div>
                    <div class="mb-3" id="wsStatusGroup" style="display:none">
                        <label for="wsStatus" class="form-label fw-medium">Status</label>
                        <select class="form-select" id="wsStatus" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="worksiteSubmitBtn"><i class="bi bi-check-lg me-1"></i>Save Worksite</button>
            </div>
        </div>
    </div>
</div>