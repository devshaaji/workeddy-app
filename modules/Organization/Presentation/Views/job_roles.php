<?php
declare(strict_types=1);
$v2Root = dirname(__DIR__, 4);
$organizationId = (string)(($routeParams ?? [])['id'] ?? ($organizationUuid ?? ''));
$pageTitle = 'Job Roles';
$pagePurpose = 'Define the job roles within your departments for task tracking and assessments.';
$pageActions = [
    ['label' => 'Add Job Role', 'url' => '#', 'class' => 'btn btn-primary', 'icon' => 'plus-lg', 'id' => 'btnAddJobRole'],
];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Organization', 'url' => '/organizations/' . $organizationId],
    ['label' => 'Job Roles'],
];
require $v2Root . '/shared/Views/Partials/page_header.php';
$eOrgId = htmlspecialchars($organizationId, ENT_QUOTES, 'UTF-8');
?>
<div id="jobRolesPage"
     data-api-base="/api/v1/organizations/<?= $eOrgId ?>/job-roles"
     data-departments-api="/api/v1/organizations/<?= $eOrgId ?>/departments"
     data-org-id="<?= $eOrgId ?>">

    <div class="row g-4 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div><span class="d-block text-muted small">Total Job Roles</span><h3 class="mb-0 fw-bold" id="jr-stat-total">—</h3></div>
                        <div class="rounded p-2" style="background:rgba(16,185,129,.1)"><i class="bi bi-person-badge-fill fs-4" style="color:#10B981"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div><span class="d-block text-muted small">Active</span><h3 class="mb-0 fw-bold" id="jr-stat-active">—</h3></div>
                        <div class="rounded p-2" style="background:rgba(34,197,94,.1)"><i class="bi bi-check-circle-fill fs-4 text-success"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card" id="jobRolesCard" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
        <div class="card-header d-flex flex-wrap align-items-center gap-3 border-bottom">
            <h5 class="card-title mb-0 me-auto">Job Roles</h5>
            <input type="search" class="form-control form-control-sm" id="jr-search" placeholder="Search job roles…" style="width:200px">
            <select class="form-select form-select-sm" id="jr-dept-filter" style="width:170px">
                <option value="">All departments</option>
            </select>
            <select class="form-select form-select-sm" id="jr-status-filter" style="width:130px">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <span class="badge bg-label-primary" id="jr-result-count">0</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Job Role</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="jobRolesBody"></tbody>
            </table>
        </div>
        <div class="card-footer d-flex align-items-center gap-3 flex-wrap">
            <span class="text-muted small" id="jr-page-info"></span>
            <div class="ms-auto d-flex gap-2">
                <button class="btn btn-outline-secondary btn-sm" id="jr-prev" disabled><i class="bi bi-chevron-left"></i></button>
                <button class="btn btn-outline-secondary btn-sm" id="jr-next" disabled><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Job Role Modal -->
<div class="modal fade" id="jobRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="jobRoleModalTitle"><i class="bi bi-person-badge me-2" style="color:var(--we-primary)"></i>Add Job Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="jobRoleModalAlert" class="mb-3"></div>
                <form id="jobRoleForm" novalidate>
                    <input type="hidden" id="jobRoleId">
                    <div class="mb-3">
                        <label for="jrName" class="form-label fw-medium">Job Role Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="jrName" name="name" required placeholder="e.g. Machine Operator">
                        <div class="form-text">The job title used when assigning workers to tasks and assessments.</div>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="jrDept" class="form-label fw-medium">Department</label>
                        <select class="form-select" id="jrDept" name="departmentId">
                            <option value="">No specific department</option>
                        </select>
                        <div class="form-text">Which department does this role belong to?</div>
                    </div>
                    <div class="mb-3" id="jrStatusGroup" style="display:none">
                        <label for="jrStatus" class="form-label fw-medium">Status</label>
                        <select class="form-select" id="jrStatus" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="jobRoleSubmitBtn"><i class="bi bi-check-lg me-1"></i>Save Job Role</button>
            </div>
        </div>
    </div>
</div>
