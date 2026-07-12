<?php
declare(strict_types=1);
$v2Root = dirname(__DIR__, 4);
$organizationId = (string)(($routeParams ?? [])['id'] ?? ($organizationUuid ?? ''));
$pageTitle = 'Departments';
$pagePurpose = 'Organise your workforce into departments aligned to worksites.';
$pageActions = [
    ['label' => 'Add Department', 'url' => '#', 'class' => 'btn btn-primary', 'icon' => 'plus-lg', 'id' => 'btnAddDept'],
];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Organization', 'url' => '/organizations/' . $organizationId],
    ['label' => 'Departments'],
];
require $v2Root . '/shared/Views/Partials/page_header.php';
$eOrgId = htmlspecialchars($organizationId, ENT_QUOTES, 'UTF-8');
?>
<div id="departmentsPage"
     data-api-base="/api/v1/organizations/<?= $eOrgId ?>/departments"
     data-worksites-api="/api/v1/organizations/<?= $eOrgId ?>/worksites"
     data-org-id="<?= $eOrgId ?>">

    <div class="row g-4 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div><span class="d-block text-muted small">Total Departments</span><h3 class="mb-0 fw-bold" id="dept-stat-total">—</h3></div>
                        <div class="rounded p-2" style="background:rgba(59,130,246,.1)"><i class="bi bi-diagram-3-fill fs-4" style="color:#3B82F6"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div><span class="d-block text-muted small">Active</span><h3 class="mb-0 fw-bold" id="dept-stat-active">—</h3></div>
                        <div class="rounded p-2" style="background:rgba(34,197,94,.1)"><i class="bi bi-check-circle-fill fs-4 text-success"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card" id="departmentsCard" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
        <div class="card-header d-flex flex-wrap align-items-center gap-3 border-bottom">
            <h5 class="card-title mb-0 me-auto">Departments</h5>
            <input type="search" class="form-control form-control-sm" id="dept-search" placeholder="Search departments…" style="width:200px">
            <select class="form-select form-select-sm" id="dept-worksite-filter" style="width:160px">
                <option value="">All worksites</option>
            </select>
            <select class="form-select form-select-sm" id="dept-status-filter" style="width:130px">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <span class="badge bg-label-primary" id="dept-result-count">0</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Department Name</th>
                        <th>Worksite</th>
                        <th>Parent Department</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="departmentsBody"></tbody>
            </table>
        </div>
        <div class="card-footer d-flex align-items-center gap-3 flex-wrap">
            <span class="text-muted small" id="dept-page-info"></span>
            <div class="ms-auto d-flex gap-2">
                <button class="btn btn-outline-secondary btn-sm" id="dept-prev" disabled><i class="bi bi-chevron-left"></i></button>
                <button class="btn btn-outline-secondary btn-sm" id="dept-next" disabled><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Department Modal -->
<div class="modal fade" id="deptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deptModalTitle"><i class="bi bi-diagram-3 me-2" style="color:var(--we-primary)"></i>Add Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="deptModalAlert" class="mb-3"></div>
                <form id="deptForm" novalidate>
                    <input type="hidden" id="deptId">
                    <div class="mb-3">
                        <label for="deptName" class="form-label fw-medium">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="deptName" name="name" required placeholder="e.g. Assembly Line Team">
                        <div class="form-text">A clear name that reflects the team's function or area of work.</div>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="deptWorksite" class="form-label fw-medium">Worksite</label>
                        <select class="form-select" id="deptWorksite" name="worksiteId">
                            <option value="">No specific worksite</option>
                        </select>
                        <div class="form-text">Which worksite does this department operate from?</div>
                    </div>
                    <div class="mb-3">
                        <label for="deptParent" class="form-label fw-medium">Parent Department</label>
                        <select class="form-select" id="deptParent" name="parentDepartmentId">
                            <option value="">No parent (top-level)</option>
                        </select>
                        <div class="form-text">Assign a parent to nest this department within a larger team.</div>
                    </div>
                    <div class="mb-3" id="deptStatusGroup" style="display:none">
                        <label for="deptStatus" class="form-label fw-medium">Status</label>
                        <select class="form-select" id="deptStatus" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="deptSubmitBtn"><i class="bi bi-check-lg me-1"></i>Save Department</button>
            </div>
        </div>
    </div>
</div>
