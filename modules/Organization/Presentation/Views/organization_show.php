<?php
declare(strict_types=1);
$v2Root = dirname(__DIR__, 4);
$organizationId = (string)(($routeParams ?? [])['id'] ?? ($organizationUuid ?? ''));
$pageTitle = 'Organization';
$pagePurpose = 'Organization profile, settings, and operational structure.';
$pageActions = [
    ['label' => 'Edit Profile', 'url' => '#', 'class' => 'btn btn-primary', 'icon' => 'pencil', 'id' => 'btnEditOrg'],
];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Organizations', 'url' => '/organizations'],
    ['label' => 'Profile'],
];
require $v2Root . '/shared/Views/Partials/page_header.php';
$eOrgId = htmlspecialchars($organizationId, ENT_QUOTES, 'UTF-8');
?>
<div id="orgShowPage" data-org-id="<?= $eOrgId ?>">
    <div class="card mb-4" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
        <div class="card-body">
            <div class="d-flex align-items-center gap-4 flex-wrap">
                <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:72px;height:72px;background:var(--we-primary-light)">
                    <i class="bi bi-building-fill fs-2" style="color:var(--we-primary)"></i>
                </div>
                <div class="flex-grow-1">
                    <h4 class="mb-1 fw-bold" id="org-name-display">Loading…</h4>
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <span class="text-muted small" id="org-slug-display"></span>
                        <span id="org-status-badge"></span>
                    </div>
                </div>
                <div class="d-flex flex-column gap-1 text-end">
                    <span class="text-muted small"><i class="bi bi-envelope me-1"></i><span id="org-email-display">—</span></span>
                    <span class="text-muted small"><i class="bi bi-telephone me-1"></i><span id="org-phone-display">—</span></span>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-4 mb-4">
        <?php foreach ([
            ['url' => 'worksites',   'icon' => 'geo-alt-fill',     'color' => '#7C3AED', 'bg' => 'rgba(124,58,237,.1)',  'id' => 'nav-worksites-count',   'label' => 'Worksites'],
            ['url' => 'departments', 'icon' => 'diagram-3-fill',   'color' => '#3B82F6', 'bg' => 'rgba(59,130,246,.1)',  'id' => 'nav-departments-count', 'label' => 'Departments'],
            ['url' => 'job-roles',   'icon' => 'person-badge-fill','color' => '#10B981', 'bg' => 'rgba(16,185,129,.1)',  'id' => 'nav-jobroles-count',    'label' => 'Job Roles'],
            ['url' => 'members',     'icon' => 'people-fill',      'color' => '#F59E0B', 'bg' => 'rgba(245,158,11,.1)',  'id' => 'nav-members-count',     'label' => 'Members'],
            ['url' => 'pilot-sites', 'icon' => 'activity',         'color' => '#6366F1', 'bg' => 'rgba(99,102,241,.1)', 'id' => 'nav-pilotsites-count',  'label' => 'Pilot Sites'],
        ] as $nav): ?>
        <div class="col-6 col-lg-4 col-xl-2">
            <a href="/organizations/<?= $eOrgId ?>/<?= $nav['url'] ?>" class="card text-decoration-none h-100"
               style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm);transition:box-shadow .2s,transform .2s;cursor:pointer"
               onmouseenter="this.style.boxShadow='var(--we-shadow)';this.style.transform='translateY(-2px)'"
               onmouseleave="this.style.boxShadow='var(--we-shadow-sm)';this.style.transform='none'">
                <div class="card-body text-center py-4">
                    <div class="rounded-3 mx-auto mb-2 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:<?= $nav['bg'] ?>">
                        <i class="bi bi-<?= $nav['icon'] ?>" style="color:<?= $nav['color'] ?>;font-size:1.25rem"></i>
                    </div>
                    <h5 class="mb-0 fw-bold" id="<?= $nav['id'] ?>">—</h5>
                    <span class="text-muted small"><?= $nav['label'] ?></span>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if (!empty($can['manageOrganization'])): ?>
    <div class="card" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
        <div class="card-header"><h5 class="card-title mb-0"><i class="bi bi-toggle-on me-2"></i>Status Management</h5></div>
        <div class="card-body">
            <div id="statusAlert" class="mb-3"></div>
            <p class="text-muted mb-3">Change the operational status of this organization. Suspended organizations lose platform access.</p>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-success btn-sm" id="btnActivate"><i class="bi bi-check-circle me-1"></i>Activate</button>
                <button class="btn btn-warning btn-sm" id="btnSuspend"><i class="bi bi-pause-circle me-1"></i>Suspend</button>
                <button class="btn btn-secondary btn-sm" id="btnDeactivate"><i class="bi bi-slash-circle me-1"></i>Deactivate</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<div class="modal fade" id="editOrgModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2" style="color:var(--we-primary)"></i>Edit Organization</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="editOrgAlert" class="mb-3"></div>
                <form id="editOrgForm" novalidate>
                    <div class="mb-3">
                        <label for="editOrgName" class="form-label fw-medium">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editOrgName" name="name" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label for="editOrgEmail" class="form-label fw-medium">Contact Email</label>
                        <input type="email" class="form-control" id="editOrgEmail" name="contactEmail">
                    </div>
                    <div class="mb-3">
                        <label for="editOrgPhone" class="form-label fw-medium">Phone</label>
                        <input type="tel" class="form-control" id="editOrgPhone" name="phone">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="editOrgSubmitBtn"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
            </div>
        </div>
    </div>
</div>
