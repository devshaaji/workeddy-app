<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Comparison Reports';
$pagePurpose = 'Before and after improvement proof';
$pageActions = [
    ['label' => 'Generate report', 'url' => '/assessments/comparisons/new', 'class' => 'btn btn-primary', 'icon' => 'plus-lg'],
    ['label' => 'Assessments', 'url' => '/assessments', 'class' => 'btn btn-outline-secondary', 'icon' => 'clipboard-pulse'],
];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Assessments', 'url' => '/assessments'],
    ['label' => 'Comparisons', 'url' => null],
];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div id="assessmentComparisonsPage">
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body"><span class="text-muted small d-block mb-2">Generated Reports</span>
                    <h3 class="mb-1 fw-bold" id="comparisonCount">0</h3>
                    <p class="mb-0 text-muted small">Stored before and after evidence packs.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body"><span class="text-muted small d-block mb-2">Improved Cases</span>
                    <h3 class="mb-1 fw-bold" id="comparisonImprovedCount">0</h3>
                    <p class="mb-0 text-muted small">Estimated risk reduction below baseline.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body"><span class="text-muted small d-block mb-2">Locked Reports</span>
                    <h3 class="mb-1 fw-bold" id="comparisonLockedCount">0</h3>
                    <p class="mb-0 text-muted small">Finalized proof ready for export.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="comparisonStatusFilter" class="form-label">Status</label>
                    <select id="comparisonStatusFilter" class="form-select">
                        <option value="">All statuses</option>
                        <option value="generated">Generated</option>
                        <option value="locked">Locked</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="comparisonDirectionFilter" class="form-label">Direction</label>
                    <select id="comparisonDirectionFilter" class="form-select">
                        <option value="">All directions</option>
                        <option value="improved">Improved</option>
                        <option value="unchanged">Unchanged</option>
                        <option value="worsened">Worsened</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-secondary w-100" id="comparisonRefreshBtn"><i class="bi bi-arrow-repeat me-1"></i>Refresh Register</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div>
                <h5 class="card-title mb-1">Comparison register</h5>
                <p class="mb-0 text-muted small">Each report links baseline, follow-up, and optional corrective action evidence.</p>
            </div>
            <span class="badge bg-label-primary" id="comparisonResultCount">0</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Pair</th>
                        <th>Model</th>
                        <th>Reduction</th>
                        <th>Direction</th>
                        <th>Status</th>
                        <th>Generated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="comparisonTableBody">
                    <tr>
                        <td colspan="7" class="text-muted">Loading comparison reports...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>