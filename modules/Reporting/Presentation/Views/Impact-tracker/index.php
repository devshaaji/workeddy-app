<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Impact Tracker';
$pagePurpose = 'Public health impact tracker';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Reports', 'url' => null],
    ['label' => 'Impact Tracker', 'url' => null],
];
$filters = is_array($filters ?? null) ? $filters : [];
$query = http_build_query(array_filter($filters, static fn($value): bool => $value !== ''));
$pdfUrl = '/api/v1/reporting/impact-tracker/pdf' . ($query !== '' ? '?' . $query : '');
$pageActions = [
    ['label' => 'Download PDF', 'url' => $pdfUrl, 'class' => 'btn btn-primary', 'icon' => 'file-earmark-pdf'],
];
require $v2Root . '/shared/Views/Partials/page_header.php';

$observed = is_array($observed ?? null) ? $observed : [];
$estimated = is_array($estimated ?? null) ? $estimated : [];
$assumptions = is_array($assumptions ?? null) ? $assumptions : [];
$disclaimer = (string) ($disclaimer ?? '');

$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$num = static fn($value, int $decimals = 0): string => number_format((float) $value, $decimals);

$bodyRegionFallback = ['Neck', 'Shoulders', 'Upper Back', 'Lower Back', 'Elbows', 'Wrists / Hands', 'Hips', 'Knees', 'Ankles / Feet'];
?>

<div
    class="container-xxl flex-grow-1 pb-4"
    id="impactTrackerPage"
    data-organization-uuid="<?= $e($organizationUuid ?? '') ?>">

    <!-- Intro banner: sets the observed vs. estimated framing before any numbers appear -->
    <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3">
                <div class="flex-grow-1">
                    <span class="badge bg-label-secondary mb-2">Preliminary platform findings</span>
                    <h5 class="mb-2">Public Health Impact Tracker</h5>
                    <p class="text-muted mb-2">
                        This dashboard is split into two parts. <strong>Observed Platform Activity</strong> is counted
                        directly from your organization's assessments, corrective actions, comparison reports, worker
                        feedback, tasks, and worksites. <strong>Estimated Impact</strong> applies disclosed, editable
                        assumption rates to those observed figures to produce planning approximations only.
                    </p>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Estimated figures are not guarantees of injuries prevented, confirmed cost savings, eliminated
                        risk, or OSHA/regulatory compliance.
                    </p>
                </div>
                <div class="text-lg-end flex-shrink-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#impactFilterModal">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 1: Observed Platform Activity -->
    <div class="d-flex align-items-center gap-2 mb-3">
        <span class="badge bg-label-success">Observed</span>
        <div>
            <h6 class="mb-0 fw-bold">Observed Platform Activity</h6>
            <p class="text-muted small mb-0">Counted directly from platform records &mdash; no projections applied.</p>
        </div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <span class="d-block text-muted small">High-Risk Tasks Identified</span>
                    <h3 class="mb-0 fw-bold"><?= $e($observed['high_risk_tasks_identified'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <span class="d-block text-muted small">High-Risk Tasks Reduced</span>
                    <h3 class="mb-0 fw-bold"><?= $e($observed['high_risk_tasks_reduced'] ?? 0) ?></h3>
                    <span class="small text-success"><i class="bi bi-arrow-down-circle me-1"></i>Observed improvement after corrective action</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <span class="d-block text-muted small">Corrective Actions Completed</span>
                    <h3 class="mb-0 fw-bold"><?= $e($observed['corrective_actions_completed'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <span class="d-block text-muted small">Average Risk Reduction</span>
                    <h3 class="mb-0 fw-bold"><?= $num($observed['average_risk_reduction_pct'] ?? 0, 1) ?>%</h3>
                    <span class="small text-muted">Risk reduction estimate across comparison reports</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <span class="d-block text-muted small">Departments Improved</span>
                    <h3 class="mb-0 fw-bold"><?= $e($observed['departments_improved'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <span class="d-block text-muted small">Workers Reached</span>
                    <h3 class="mb-0 fw-bold"><?= $e($observed['workers_reached'] ?? 0) ?></h3>
                    <span class="small text-muted">Aggregate count, not individually identified</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <span class="d-block text-muted small">Repeat High-Risk Tasks</span>
                    <h3 class="mb-0 fw-bold text-warning"><?= $e($observed['repeat_high_risk_tasks'] ?? 0) ?></h3>
                    <span class="small text-muted">Caution signal &mdash; risk recurring despite prior activity</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 2: Estimated Impact -->
    <div class="d-flex align-items-center gap-2 mb-3">
        <span class="badge bg-label-warning">Estimate</span>
        <div>
            <h6 class="mb-0 fw-bold">Estimated Impact &mdash; Not a Guarantee</h6>
            <p class="text-muted small mb-0">Planning approximations derived from the observed figures above and the disclosed assumption rates below.</p>
        </div>
    </div>
    <div class="row g-4 mb-3">
        <div class="col-md-4">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm); background: #fffbeb; border: 1px solid #fde68a;">
                <div class="card-body">
                    <span class="badge bg-label-warning mb-2">Estimate</span>
                    <span class="d-block text-muted small">Potential Injuries Prevented</span>
                    <h3 class="mb-0 fw-bold text-warning-emphasis"><?= $num($estimated['potential_injuries_prevented'] ?? 0, 2) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm); background: #fffbeb; border: 1px solid #fde68a;">
                <div class="card-body">
                    <span class="badge bg-label-warning mb-2">Estimate</span>
                    <span class="d-block text-muted small">Potential Lost Workdays Avoided</span>
                    <h3 class="mb-0 fw-bold text-warning-emphasis"><?= $num($estimated['potential_lost_workdays_avoided'] ?? 0, 1) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm); background: #fffbeb; border: 1px solid #fde68a;">
                <div class="card-body">
                    <span class="badge bg-label-warning mb-2">Estimate</span>
                    <span class="d-block text-muted small">Potential Cost Savings</span>
                    <h3 class="mb-0 fw-bold text-warning-emphasis">$<?= $num($estimated['potential_cost_savings'] ?? 0, 2) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Methodology disclosure -->
    <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm); border-left: 4px solid #d97706;">
        <div class="card-body">
            <h6 class="fw-bold mb-2"><i class="bi bi-calculator me-2 text-warning"></i>How these estimates are calculated</h6>
            <p class="text-muted small mb-3"><?= $e($disclaimer) ?></p>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                        <tr>
                            <td class="text-muted">Assumed injury-prevention rate per resolved high-risk task</td>
                            <td class="text-end fw-semibold"><?= $num(((float) ($assumptions['injuryPreventionRate'] ?? 0)) * 100, 1) ?>%</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Assumed lost workdays per potential injury</td>
                            <td class="text-end fw-semibold"><?= $num($assumptions['lostWorkdaysPerInjury'] ?? 0, 1) ?> days</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Assumed fully-burdened cost per lost workday</td>
                            <td class="text-end fw-semibold">$<?= $num($assumptions['costPerLostWorkday'] ?? 0, 2) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-muted small mt-3 mb-0">
                These rates are configurable in Reporting settings and reflect a conservative planning methodology,
                not a scientific measurement of this pilot's actual health outcomes.
            </p>
        </div>
    </div>

    <!-- Filter Modal -->
    <div class="modal fade" id="impactFilterModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-funnel me-2" style="color:var(--we-primary)"></i>Filter Impact Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="get" action="/reporting/impact-tracker">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-medium" for="impactWorksite">Worksite</label>
                                <select id="impactWorksite" name="worksiteUuid" class="form-select" data-selected="<?= $e($filters['worksiteUuid'] ?? '') ?>">
                                    <option value="">All worksites</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium" for="impactDepartment">Department</label>
                                <select id="impactDepartment" name="departmentUuid" class="form-select" data-selected="<?= $e($filters['departmentUuid'] ?? '') ?>">
                                    <option value="">All departments</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium" for="impactJobRole">Job Role</label>
                                <select id="impactJobRole" name="jobRoleUuid" class="form-select" data-selected="<?= $e($filters['jobRoleUuid'] ?? '') ?>">
                                    <option value="">All job roles</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium" for="impactIndustry">Industry</label>
                                <input id="impactIndustry" type="text" name="industry" class="form-control" value="<?= $e($filters['industry'] ?? '') ?>" placeholder="e.g. Manufacturing">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium" for="impactBodyRegion">Body Region</label>
                                <select id="impactBodyRegion" name="bodyRegion" class="form-select" data-selected="<?= $e($filters['bodyRegion'] ?? '') ?>">
                                    <option value="">All body regions</option>
                                    <?php foreach ($bodyRegionFallback as $region): ?>
                                        <option value="<?= $e($region) ?>"><?= $e($region) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium" for="impactRiskLevel">Risk Level</label>
                                <select id="impactRiskLevel" name="riskLevel" class="form-select">
                                    <option value="">All risk levels</option>
                                    <option value="Low Risk" <?= ($filters['riskLevel'] ?? '') === 'Low Risk' ? 'selected' : '' ?>>Low Risk</option>
                                    <option value="Medium Risk" <?= ($filters['riskLevel'] ?? '') === 'Medium Risk' ? 'selected' : '' ?>>Medium Risk</option>
                                    <option value="High Risk" <?= ($filters['riskLevel'] ?? '') === 'High Risk' ? 'selected' : '' ?>>High Risk</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium" for="impactFromDate">From Date</label>
                                <input id="impactFromDate" type="date" name="fromDate" class="form-control" value="<?= $e($filters['fromDate'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium" for="impactToDate">To Date</label>
                                <input id="impactToDate" type="date" name="toDate" class="form-control" value="<?= $e($filters['toDate'] ?? '') ?>">
                            </div>
                        </div>
                        <p class="text-muted small mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Data stays scoped to your current organization. Applying filters reloads the dashboard and updates the PDF export link above.</p>
                    </div>
                    <div class="modal-footer">
                        <a href="/reporting/impact-tracker" class="btn btn-outline-secondary">Reset</a>
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
