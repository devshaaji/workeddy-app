<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Manage National Statistics';
$pagePurpose = 'Source-cited national context for the National Importance dashboard';
$pageActions = [
    ['label' => 'View Dashboard', 'url' => '/reporting/national-importance', 'class' => 'btn btn-outline-secondary', 'icon' => 'bar-chart'],
    ['label' => 'Add Statistic', 'url' => '#', 'class' => 'btn btn-primary', 'icon' => 'plus-lg', 'id' => 'btnAddStatistic'],
];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Reports', 'url' => null],
    ['label' => 'National Importance', 'url' => '/reporting/national-importance'],
    ['label' => 'Manage Statistics', 'url' => null],
];
require $v2Root . '/shared/Views/Partials/page_header.php';

$categoryLabels = is_array($categoryLabels ?? null) ? $categoryLabels : [];
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>

<div id="nationalStatisticsAdminPage" data-api-base="/api/v1/reporting/national-statistics">

    <p class="text-muted mb-4">
        Every statistic here appears on the public-facing National Importance dashboard and in its PDF export.
        <strong>Source name, source year, and a source link are required for every entry</strong> \u2014 statistics
        without a citation cannot be saved. Unpublished statistics are saved but hidden from the dashboard.
    </p>

    <div class="card" id="statisticsCard" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
        <div class="card-header d-flex flex-wrap align-items-center gap-3 border-bottom">
            <h5 class="card-title mb-0 me-auto">National Statistics</h5>
            <input type="search" class="form-control form-control-sm" id="ns-search" placeholder="Search statistics…" style="width:220px">
            <select class="form-select form-select-sm" id="ns-category-filter" style="width:220px">
                <option value="">All topic areas</option>
                <?php foreach ($categoryLabels as $key => $label): ?>
                    <option value="<?= $e($key) ?>"><?= $e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select form-select-sm" id="ns-status-filter" style="width:160px">
                <option value="">All statuses</option>
                <option value="published">Published</option>
                <option value="draft">Unpublished</option>
            </select>
            <span class="badge bg-label-primary" id="ns-result-count">0</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Statistic</th>
                        <th>Topic Area</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="statisticsBody"></tbody>
            </table>
        </div>
        <div class="card-footer d-flex align-items-center gap-3 flex-wrap">
            <span class="text-muted small" id="ns-page-info"></span>
            <div class="ms-auto d-flex gap-2">
                <button class="btn btn-outline-secondary btn-sm" id="ns-prev" disabled><i class="bi bi-chevron-left"></i></button>
                <button class="btn btn-outline-secondary btn-sm" id="ns-next" disabled><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card mt-4" style="border-radius:var(--we-radius-lg);box-shadow:var(--we-shadow-sm)">
        <div class="card-body">
            <h6 class="fw-bold mb-2"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Problem summary &amp; future research text</h6>
            <p class="text-muted small mb-2">
                The dashboard's "National problem summary" and "Future research" prose sections are edited as
                managed content pages, alongside the rest of WorkEddy's published content.
            </p>
            <a href="/content/national-importance-context" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-pencil me-1"></i> Edit dashboard text content
            </a>
        </div>
    </div>
</div>

<!-- Add/Edit Statistic Modal -->
<div class="modal fade" id="statisticModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="statisticModalTitle"><i class="bi bi-bar-chart-line me-2" style="color:var(--we-primary)"></i>Add Statistic</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="statisticModalAlert" class="mb-3"></div>
                <form id="statisticForm" novalidate>
                    <input type="hidden" id="statisticUuid">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="nsTitle" class="form-label fw-medium">Statistic Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nsTitle" name="title" required placeholder="e.g. Warehouse worker MSD incidence rate">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="nsValue" class="form-label fw-medium">Value <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nsValue" name="value" required placeholder="e.g. 8.1">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="nsUnit" class="form-label fw-medium">Unit</label>
                            <input type="text" class="form-control" id="nsUnit" name="unit" placeholder="e.g. per 10,000 FTE">
                        </div>
                        <div class="col-md-6">
                            <label for="nsCategory" class="form-label fw-medium">Topic Area <span class="text-danger">*</span></label>
                            <select class="form-select" id="nsCategory" name="category" required>
                                <option value="">Select a topic area…</option>
                                <?php foreach ($categoryLabels as $key => $label): ?>
                                    <option value="<?= $e($key) ?>"><?= $e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="nsIndustryRelevance" class="form-label fw-medium">Industry Relevance</label>
                            <input type="text" class="form-control" id="nsIndustryRelevance" name="industryRelevance" placeholder="e.g. Warehousing, 3PL, e-commerce fulfillment">
                        </div>
                        <div class="col-12"><hr class="my-1"></div>
                        <div class="col-12">
                            <div class="small text-muted fw-medium mb-1"><i class="bi bi-link-45deg me-1"></i>Source citation (required)</div>
                        </div>
                        <div class="col-md-6">
                            <label for="nsSourceName" class="form-label fw-medium">Source Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nsSourceName" name="sourceName" required placeholder="e.g. Bureau of Labor Statistics">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-3">
                            <label for="nsSourceYear" class="form-label fw-medium">Source Year <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="nsSourceYear" name="sourceYear" required min="1990" max="2100" placeholder="2025">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-3">
                            <label for="nsPublished" class="form-label fw-medium">Visibility</label>
                            <select class="form-select" id="nsPublished" name="isPublished">
                                <option value="1">Published</option>
                                <option value="0">Unpublished (draft)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="nsSourceUrl" class="form-label fw-medium">Source Link <span class="text-danger">*</span></label>
                            <input type="url" class="form-control" id="nsSourceUrl" name="sourceUrl" required placeholder="https://…">
                            <div class="form-text">Must be a working URL to the original source \u2014 shown as a link on the public dashboard.</div>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="statisticSubmitBtn"><i class="bi bi-check-lg me-1"></i>Save Statistic</button>
            </div>
        </div>
    </div>
</div>
