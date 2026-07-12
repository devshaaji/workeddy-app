<?php
declare(strict_types=1);
$v2Root = dirname(__DIR__, 4);
$organizationId = (string)(($routeParams ?? [])['id'] ?? ($organizationUuid ?? ''));
$pageTitle = 'Pilot Sites';
$pagePurpose = 'Track worksites enrolled in the ergonomics pilot programme.';
$pageActions = [
    ['label' => 'Enroll Pilot Site', 'url' => '#', 'class' => 'btn btn-primary', 'icon' => 'plus-lg', 'id' => 'btnEnrollPilot'],
];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Organization', 'url' => '/organizations/' . $organizationId],
    ['label' => 'Pilot Sites'],
];
require $v2Root . '/shared/Views/Partials/page_header.php';
$eOrgId = htmlspecialchars($organizationId, ENT_QUOTES, 'UTF-8');
?>
<div id="organizationPilotSitesPage"
     data-api-base="/api/v1/organizations/<?= $eOrgId ?>/pilot-sites"
     data-worksites-api="/api/v1/organizations/<?= $eOrgId ?>/worksites"
     data-org-id="<?= $eOrgId ?>">

    <div class="row g-4 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div><span class="d-block text-muted small">Enrolled Sites</span><h3 class="mb-0 fw-bold" id="ps-stat-total">—</h3></div>
                        <div class="rounded p-2" style="background:rgba(99,102,241,.1)"><i class="bi bi-activity fs-4" style="color:#6366F1"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div><span class="d-block text-muted small">Active</span><h3 class="mb-0 fw-bold" id="ps-stat-active">—</h3></div>
                        <div class="rounded p-2" style="background:rgba(34,197,94,.1)"><i class="bi bi-check-circle-fill fs-4 text-success"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div><span class="d-block text-muted small">Target Workers</span><h3 class="mb-0 fw-bold" id="ps-stat-target">—</h3></div>
                        <div class="rounded p-2" style="background:rgba(245,158,11,.1)"><i class="bi bi-people fs-4" style="color:#F59E0B"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div><span class="d-block text-muted small">Actual Workers</span><h3 class="mb-0 fw-bold" id="ps-stat-actual">—</h3></div>
                        <div class="rounded p-2" style="background:rgba(16,185,129,.1)"><i class="bi bi-person-check-fill fs-4" style="color:#10B981"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card" id="pilotSitesCard" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
        <div class="card-header d-flex flex-wrap align-items-center gap-3 border-bottom">
            <h5 class="card-title mb-0 me-auto">Pilot Site Enrollments</h5>
            <select class="form-select form-select-sm" id="ps-status-filter" style="width:140px">
                <option value="">All statuses</option>
                <option value="enrolled">Enrolled</option>
                <option value="active">Active</option>
                <option value="paused">Paused</option>
                <option value="completed">Completed</option>
            </select>
            <span class="badge bg-label-primary" id="ps-result-count">0</span>
        </div>
        <div id="pilotSiteAlert" class="mx-3 mt-3"></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Worksite</th>
                        <th>Status</th>
                        <th>Enrolled</th>
                        <th>Industry</th>
                        <th>Target</th>
                        <th>Actual</th>
                        <th>Notes</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="pilotSiteTable"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Enroll Pilot Site Modal -->
<div class="modal fade" id="pilotSiteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pilotSiteModalTitle"><i class="bi bi-activity me-2" style="color:var(--we-primary)"></i>Enroll Pilot Site</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="pilotSiteModalAlert" class="mb-3"></div>
                <p class="text-muted small mb-3">A pilot site is a worksite selected to participate in the ergonomics assessment programme. Enrolling a site enables assessment tracking, worker counts, and pilot summary reports for that location.</p>
                <form id="pilotSiteForm" class="row g-3" novalidate>
                    <input type="hidden" id="pilotSiteId">
                    <div class="col-md-6">
                        <label for="pilotSiteWorksite" class="form-label fw-medium">Worksite <span class="text-danger">*</span></label>
                        <select class="form-select" id="pilotSiteWorksite" name="worksiteId" required>
                            <option value="">Select worksite…</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-3">
                        <label for="pilotSiteEnrollmentDate" class="form-label fw-medium">Enrollment Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="pilotSiteEnrollmentDate" name="enrollmentDate" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-md-3">
                        <label for="pilotSiteStatus" class="form-label fw-medium">Programme Status</label>
                        <select class="form-select" id="pilotSiteStatus" name="pilotStatus">
                            <option value="enrolled">Enrolled</option>
                            <option value="active">Active</option>
                            <option value="paused">Paused</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="pilotSiteTargetWorkers" class="form-label fw-medium">Target Workers</label>
                        <input type="number" class="form-control" id="pilotSiteTargetWorkers" name="targetWorkerCount" min="0" value="0">
                        <div class="form-text">Planned number of workers.</div>
                    </div>
                    <div class="col-md-3">
                        <label for="pilotSiteActualWorkers" class="form-label fw-medium">Actual Workers</label>
                        <input type="number" class="form-control" id="pilotSiteActualWorkers" name="actualWorkerCount" min="0" value="0">
                        <div class="form-text">Current enrolled workers.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="pilotSiteIndustry" class="form-label fw-medium">Industry Sector</label>
                        <input type="text" class="form-control" id="pilotSiteIndustry" name="industry" placeholder="e.g. Manufacturing, Logistics">
                    </div>
                    <div class="col-12">
                        <label for="pilotSiteNotes" class="form-label fw-medium">Notes</label>
                        <textarea class="form-control" id="pilotSiteNotes" name="notes" rows="2" placeholder="Pilot scope, staffing conditions, or special notes…"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="pilotSiteSubmitBtn"><i class="bi bi-check-lg me-1"></i>Save Enrollment</button>
            </div>
        </div>
    </div>
</div>
