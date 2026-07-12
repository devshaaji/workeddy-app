<?php
declare(strict_types=1);
$v2Root = dirname(__DIR__, 4);
$organizationId = (string)(($routeParams ?? [])['id'] ?? ($organizationUuid ?? ''));
$pageTitle = 'Team Members';
$pagePurpose = 'Manage who has access to this organization and their operational roles.';
$pageActions = [
    ['label' => 'Invite Member', 'url' => '#', 'class' => 'btn btn-primary', 'icon' => 'person-plus', 'id' => 'btnInviteMember'],
];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Organization', 'url' => '/organizations/' . $organizationId],
    ['label' => 'Members'],
];
require $v2Root . '/shared/Views/Partials/page_header.php';
$eOrgId = htmlspecialchars($organizationId, ENT_QUOTES, 'UTF-8');
?>
<div id="membersPage"
     data-api-base="/api/v1/organizations/<?= $eOrgId ?>/members"
     data-roles-api="/api/v1/organizations/<?= $eOrgId ?>/assignable-roles"
     data-invite-api="/api/v1/organizations/<?= $eOrgId ?>/members"
     data-org-id="<?= $eOrgId ?>">

    <div class="row g-4 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div><span class="d-block text-muted small">Total Members</span><h3 class="mb-0 fw-bold" id="mem-stat-total">—</h3></div>
                        <div class="rounded p-2" style="background:rgba(245,158,11,.1)"><i class="bi bi-people-fill fs-4" style="color:#F59E0B"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div><span class="d-block text-muted small">Active</span><h3 class="mb-0 fw-bold" id="mem-stat-active">—</h3></div>
                        <div class="rounded p-2" style="background:rgba(34,197,94,.1)"><i class="bi bi-check-circle-fill fs-4 text-success"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card" id="membersCard" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
        <div class="card-header d-flex flex-wrap align-items-center gap-3 border-bottom">
            <h5 class="card-title mb-0 me-auto">Members</h5>
            <input type="search" class="form-control form-control-sm" id="mem-search" placeholder="Search by name or email…" style="width:220px">
            <select class="form-select form-select-sm" id="mem-role-filter" style="width:160px">
                <option value="">All roles</option>
            </select>
            <select class="form-select form-select-sm" id="mem-status-filter" style="width:130px">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="pending">Pending</option>
                <option value="suspended">Suspended</option>
                <option value="inactive">Inactive</option>
            </select>
            <span class="badge bg-label-primary" id="mem-result-count">0</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Member</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Primary</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="membersBody"></tbody>
            </table>
        </div>
        <div class="card-footer d-flex align-items-center gap-3 flex-wrap">
            <span class="text-muted small" id="mem-page-info"></span>
            <div class="ms-auto d-flex gap-2">
                <button class="btn btn-outline-secondary btn-sm" id="mem-prev" disabled><i class="bi bi-chevron-left"></i></button>
                <button class="btn btn-outline-secondary btn-sm" id="mem-next" disabled><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>
</div>

<!-- Invite Member Modal -->
<div class="modal fade" id="inviteMemberModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2" style="color:var(--we-primary)"></i>Invite Team Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="inviteMemberAlert" class="mb-3"></div>
                <p class="text-muted small mb-3">The invited person will receive an email to set up their account and gain access to this organization.</p>
                <form id="inviteMemberForm" novalidate>
                    <div class="mb-3">
                        <label for="inviteFullName" class="form-label fw-medium">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="inviteFullName" name="fullName" required placeholder="e.g. Jane Smith">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="inviteEmail" class="form-label fw-medium">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="inviteEmail" name="email" required placeholder="jane.smith@example.com">
                        <div class="form-text">They will receive an invitation at this address.</div>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="invitePhone" class="form-label fw-medium">Phone (optional)</label>
                        <input type="tel" class="form-control" id="invitePhone" name="phone" placeholder="+44 7700 900000">
                    </div>
                    <div class="mb-3">
                        <label for="inviteRole" class="form-label fw-medium">Access Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="inviteRole" name="roleSlug" required>
                            <option value="">Select a role…</option>
                        </select>
                        <div class="form-text">Determines what this person can view and do within the organization.</div>
                        <div class="invalid-feedback"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="inviteMemberSubmitBtn"><i class="bi bi-send me-1"></i>Send Invitation</button>
            </div>
        </div>
    </div>
</div>

<!-- Change Role Modal -->
<div class="modal fade" id="changeMemberRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-shield me-2" style="color:var(--we-primary)"></i>Change Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="changeRoleAlert" class="mb-3"></div>
                <p class="text-muted small mb-3">Select the new access role for <strong id="changeRoleMemberName">this member</strong>.</p>
                <input type="hidden" id="changeRoleMemberId">
                <select class="form-select" id="changeRoleSelect">
                    <option value="">Select role…</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="changeRoleSubmitBtn"><i class="bi bi-check-lg me-1"></i>Update Role</button>
            </div>
        </div>
    </div>
</div>
