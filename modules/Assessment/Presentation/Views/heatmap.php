<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$assessmentId = (string) (($routeParams ?? [])['assessmentId'] ?? '');
$pageTitle = 'Body Region Heat Map';
$pagePurpose = 'Assessment workflow';
$pageActions = [
    ['label' => 'Assessment Detail', 'url' => '/assessments/' . rawurlencode($assessmentId), 'class' => 'btn btn-outline-secondary', 'icon' => 'arrow-left'],
    ['label' => 'Reviewer View', 'url' => '/assessments/' . rawurlencode($assessmentId) . '/review', 'class' => 'btn btn-primary', 'icon' => 'clipboard-check'],
];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Assessments', 'url' => '/assessments'],
    ['label' => 'Body Region Heat Map', 'url' => null],
];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div
    id="assessmentHeatmapPage"
    data-assessment-uuid="<?= htmlspecialchars($assessmentId, ENT_QUOTES, 'UTF-8') ?>"
    data-organization-uuid="<?= htmlspecialchars((string) ($organizationUuid ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="card-title mb-1">Persisted Body Region Evidence</h5>
                        <p class="mb-0 text-muted small">Heat maps render directly from saved region intensity, not inferred overlays.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reviewerSeverityModal">
                            <i class="bi bi-pencil-square me-1"></i>Edit Region Severity
                        </button>
                        <span class="badge bg-label-secondary" id="heatmapStatusBadge">Loading</span>
                    </div>
                </div>
                <div class="card-body">
                    <div id="heatmapAlert"></div>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0 fw-semibold">Front</h6>
                                    <span class="text-muted small">Primary view</span>
                                </div>
                                <div id="heatmapFrontCanvas" class="d-flex align-items-center justify-content-center" style="min-height: 360px;"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0 fw-semibold">Back</h6>
                                    <span class="text-muted small">Posterior load</span>
                                </div>
                                <div id="heatmapBackCanvas" class="d-flex align-items-center justify-content-center" style="min-height: 360px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Assessment Context</h5>
                    <p class="mb-0 text-muted small">Review state stays visible while comparing front and back load concentration.</p>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5 text-muted">Method</dt>
                        <dd class="col-7" id="heatmapMethod">--</dd>
                        <dt class="col-5 text-muted">Final Score</dt>
                        <dd class="col-7" id="heatmapFinalScore">--</dd>
                        <dt class="col-5 text-muted">Risk Level</dt>
                        <dd class="col-7" id="heatmapRiskLevel">--</dd>
                        <dt class="col-5 text-muted">Baseline</dt>
                        <dd class="col-7" id="heatmapBaselineState">--</dd>
                        <dt class="col-5 text-muted">Locked</dt>
                        <dd class="col-7" id="heatmapLockedState">--</dd>
                    </dl>
                </div>
            </div>

            <div class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Intensity Legend</h5>
                    <p class="mb-0 text-muted small">Use the stored region list to explain why a score rose or fell after review.</p>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-4">
                        <li class="d-flex align-items-center gap-2 mb-2"><span class="rounded-circle d-inline-block" style="width: 14px; height: 14px; background: #e8f5e9;"></span><span class="small">0 None recorded</span></li>
                        <li class="d-flex align-items-center gap-2 mb-2"><span class="rounded-circle d-inline-block" style="width: 14px; height: 14px; background: #c8e6c9;"></span><span class="small">1 Low strain</span></li>
                        <li class="d-flex align-items-center gap-2 mb-2"><span class="rounded-circle d-inline-block" style="width: 14px; height: 14px; background: #fff59d;"></span><span class="small">2 Moderate strain</span></li>
                        <li class="d-flex align-items-center gap-2 mb-2"><span class="rounded-circle d-inline-block" style="width: 14px; height: 14px; background: #ffcc80;"></span><span class="small">3 Elevated strain</span></li>
                        <li class="d-flex align-items-center gap-2"><span class="rounded-circle d-inline-block" style="width: 14px; height: 14px; background: #ef9a9a;"></span><span class="small">4 Very high strain</span></li>
                    </ul>
                    <div>
                        <h6 class="fw-semibold mb-2">Stored Regions</h6>
                        <div class="d-flex flex-wrap gap-2" id="heatmapRegionsList"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reviewer Severity Modal -->
    <div class="modal fade" id="reviewerSeverityModal" tabindex="-1" aria-labelledby="reviewerSeverityModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-bold" id="reviewerSeverityModalLabel">Reviewer Severity Editor</h5>
                        <p class="mb-0 text-muted small">Click body regions or select risk levels. Saved values update structured body-region evidence.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="modalHeatmapAlert"></div>
                    <div class="row g-4">
                        <div class="col-lg-5">
                            <div class="border rounded-3 p-3">
                                <h6 class="fw-semibold mb-3">Clickable body map</h6>
                                <div id="heatmapInteractiveCanvas"></div>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Region</th>
                                            <th>View</th>
                                            <th>Risk level</th>
                                            <th>Score</th>
                                        </tr>
                                    </thead>
                                    <tbody id="heatmapRegionEditorRows">
                                        <tr>
                                            <td colspan="4" class="text-muted">Loading region editor...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" id="heatmapSaveRegionsBtn" type="button">Save region severity</button>
                </div>
            </div>
        </div>
    </div>
</div>