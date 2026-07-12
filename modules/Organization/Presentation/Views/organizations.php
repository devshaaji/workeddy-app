<?php
declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Organizations';
$pagePurpose = 'Manage all client organizations on the platform.';
$pageActions = [
    ['label' => 'New Organization', 'url' => '#', 'class' => 'btn btn-primary', 'icon' => 'plus-lg', 'id' => 'btnCreateOrg'],
];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Organizations'],
];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<!-- Stats Row -->
<div class="row g-4 mb-4" id="org-stats-row">
    <div class="col-6 col-xl-3">
        <div class="card" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="d-block text-muted small">Total</span>
                        <h3 class="mb-0 fw-bold" id="stat-total">—</h3>
                    </div>
                    <div class="rounded p-2" style="background:var(--we-primary-light)">
                        <i class="bi bi-building-fill fs-4" style="color:var(--we-primary)"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="d-block text-muted small">Active</span>
                        <h3 class="mb-0 fw-bold" id="stat-active">—</h3>
                    </div>
                    <div class="rounded p-2" style="background:rgba(34,197,94,.1)">
                        <i class="bi bi-check-circle-fill fs-4 text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="d-block text-muted small">Suspended</span>
                        <h3 class="mb-0 fw-bold" id="stat-suspended">—</h3>
                    </div>
                    <div class="rounded p-2" style="background:rgba(239,68,68,.1)">
                        <i class="bi bi-slash-circle-fill fs-4 text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="d-block text-muted small">Pending</span>
                        <h3 class="mb-0 fw-bold" id="stat-pending">—</h3>
                    </div>
                    <div class="rounded p-2" style="background:rgba(234,179,8,.1)">
                        <i class="bi bi-hourglass-split fs-4 text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Organizations Table Card -->
<div class="card" id="orgsCard" data-endpoint="/api/v1/organizations"
     style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
    <div class="card-header d-flex flex-wrap align-items-center gap-3 border-bottom">
        <div class="me-auto">
            <h5 class="card-title mb-0">All Organizations</h5>
        </div>
        <!-- Filters -->
        <input type="search" class="form-control form-control-sm" id="org-search"
               placeholder="Search name or email..." style="width:200px">
        <select class="form-select form-select-sm" id="org-status-filter" style="width:140px">
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="pending">Pending</option>
            <option value="suspended">Suspended</option>
            <option value="inactive">Inactive</option>
        </select>
        <span class="badge bg-label-primary" id="org-result-count">0</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th data-sort="name" style="cursor:pointer">Organization <i class="bi bi-arrow-down-up text-muted small ms-1"></i></th>
                    <th>Contact</th>
                    <th data-sort="status" style="cursor:pointer">Status</th>
                    <th>Structure</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody id="orgsBody"></tbody>
        </table>
    </div>
    <div class="card-footer d-flex align-items-center gap-3 flex-wrap">
        <span class="text-muted small" id="org-page-info"></span>
        <div class="ms-auto d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm" id="org-prev" disabled>
                <i class="bi bi-chevron-left"></i>
            </button>
            <button class="btn btn-outline-secondary btn-sm" id="org-next" disabled>
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    </div>
</div>

<!-- Create Organization Modal -->
<div class="modal fade" id="createOrgModal" tabindex="-1" aria-labelledby="createOrgModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createOrgModalLabel">
                    <i class="bi bi-building-add me-2" style="color:var(--we-primary)"></i>New Organization
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="createOrgAlert" class="mb-3"></div>
                <form id="createOrgForm" novalidate>
                    <div class="mb-3">
                        <label for="orgName" class="form-label fw-medium">Organization Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="orgName" name="name" required
                               placeholder="e.g. Acme Manufacturing Ltd.">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="orgEmail" class="form-label fw-medium">Contact Email</label>
                        <input type="email" class="form-control" id="orgEmail" name="contactEmail"
                               placeholder="billing@example.com">
                        <div class="form-text">Primary billing and communication email.</div>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="orgPhone" class="form-label fw-medium">Phone</label>
                        <input type="tel" class="form-control" id="orgPhone" name="phone"
                               placeholder="+44 7700 900000">
                        <div class="invalid-feedback"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="createOrgSubmitBtn">
                    <i class="bi bi-check-lg me-1"></i>Create Organization
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Organization Modal -->
<div class="modal fade" id="editOrgModal" tabindex="-1" aria-labelledby="editOrgModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editOrgModalLabel">
                    <i class="bi bi-pencil me-2" style="color:var(--we-primary)"></i>Edit Organization
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="editOrgAlert" class="mb-3"></div>
                <form id="editOrgForm" novalidate>
                    <input type="hidden" id="editOrgId" name="_id">
                    <div class="mb-3">
                        <label for="editOrgName" class="form-label fw-medium">Organization Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editOrgName" name="name" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="editOrgEmail" class="form-label fw-medium">Contact Email</label>
                        <input type="email" class="form-control" id="editOrgEmail" name="contactEmail">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="editOrgPhone" class="form-label fw-medium">Phone</label>
                        <input type="tel" class="form-control" id="editOrgPhone" name="phone">
                        <div class="invalid-feedback"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="editOrgSubmitBtn">
                    <i class="bi bi-check-lg me-1"></i>Save Changes
                </button>
            </div>
        </div>
    </div>
</div>
