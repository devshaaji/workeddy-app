<?php declare(strict_types=1); ?>
<?php
$pageTitle = 'Before/After Comparison Report';
$baseline = is_array($baseline ?? null) ? $baseline : [];
$followUp = is_array($follow_up ?? null) ? $follow_up : [];
$completedActions = is_array($completed_actions ?? null) ? $completed_actions : [];
$improvedRegions = is_array($body_regions_improved ?? null) ? $body_regions_improved : [];
$worsenedRegions = is_array($body_regions_worsened ?? null) ? $body_regions_worsened : [];
?>

<style>
    .comparison-heatmap svg {
        width: 100%;
        height: auto;
        max-height: 240px;
    }
</style>

<div class="container-xxl flex-grow-1 pb-4">
    <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-4">
                <div>
                    <span class="badge bg-label-warning mb-2">Comparison report</span>
                    <h5 class="mb-2">Baseline versus follow-up comparison showing movement after corrective action.</h5>
                    <p class="text-muted mb-0">Keep the focus on observed score change, changed body regions, and completed actions.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="/reporting/pilot-summary" class="btn btn-outline-secondary">Back to Pilot Summary</a>
                    <a href="/api/v1/reporting/comparison/<?= htmlspecialchars($uuid) ?>/pdf" class="btn btn-primary" target="_blank">Download PDF</a>
                    <a href="/api/v1/reporting/comparison/<?= htmlspecialchars($uuid) ?>/csv" class="btn btn-outline-secondary" target="_blank">Export CSV</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-widget-separator-wrapper">
            <div class="card-body card-widget-separator">
                <div class="row gy-4 gy-sm-1">
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0"><?= number_format((float) ($risk_reduction_pct ?? 0), 1) ?>%</h4>
                                <p class="mb-0">Risk reduction</p>
                            </div>
                            <div class="avatar me-sm-6">
                                <span class="avatar-initial rounded bg-label-success text-heading"><i class="bi bi-graph-up-arrow"></i></span>
                            </div>
                        </div>
                        <hr class="d-none d-sm-block d-lg-none me-6">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0"><?= htmlspecialchars((string) ($baseline['score'] ?? '--')) ?></h4>
                                <p class="mb-0">Baseline score</p>
                            </div>
                            <div class="avatar me-lg-6">
                                <span class="avatar-initial rounded bg-label-danger text-heading"><i class="bi bi-arrow-down-right"></i></span>
                            </div>
                        </div>
                        <hr class="d-none d-sm-block d-lg-none">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0"><?= htmlspecialchars((string) ($followUp['score'] ?? '--')) ?></h4>
                                <p class="mb-0">Follow-up score</p>
                            </div>
                            <div class="avatar me-sm-6">
                                <span class="avatar-initial rounded bg-label-primary text-heading"><i class="bi bi-arrow-up-right"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0"><?= count($completedActions) ?></h4>
                                <p class="mb-0">Completed actions</p>
                            </div>
                            <div class="avatar">
                                <span class="avatar-initial rounded bg-label-info text-heading"><i class="bi bi-check2-square"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Assessment Comparison</h5>
                    <p class="text-muted small mb-0">Direct comparison of published baseline and follow-up assessment positions.</p>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="small text-muted mb-1">Baseline assessment</div>
                                <h5 class="mb-1"><?= htmlspecialchars((string) ($baseline['level'] ?? '--')) ?></h5>
                                <div class="text-muted small mb-3"><?= htmlspecialchars((string) ($baseline['date'] ?? '--')) ?></div>
                                <?php foreach (($baseline['scores'] ?? []) as $part => $score): ?>
                                    <div class="d-flex justify-content-between py-1 border-bottom">
                                        <span class="text-muted"><?= htmlspecialchars(ucfirst((string) $part)) ?></span>
                                        <strong><?= htmlspecialchars((string) $score) ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="small text-muted mb-1">Follow-up assessment</div>
                                <h5 class="mb-1"><?= htmlspecialchars((string) ($followUp['level'] ?? '--')) ?></h5>
                                <div class="text-muted small mb-3"><?= htmlspecialchars((string) ($followUp['date'] ?? '--')) ?></div>
                                <?php foreach (($followUp['scores'] ?? []) as $part => $score): ?>
                                    <div class="d-flex justify-content-between py-1 border-bottom">
                                        <span class="text-muted"><?= htmlspecialchars(ucfirst((string) $part)) ?></span>
                                        <strong><?= htmlspecialchars((string) $score) ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-xl-4">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Completed Actions</h5>
                    <p class="text-muted small mb-0">Actions linked to the improvement claim in this comparison.</p>
                </div>
                <div class="card-body">
                    <?php foreach ($completedActions as $action): ?>
                        <div class="border rounded-3 px-3 py-2 mb-2">
                            <div class="fw-semibold"><?= htmlspecialchars((string) $action) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($completedActions === []): ?>
                        <div class="text-muted">No linked corrective action evidence.</div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Baseline Heat Maps</h5>
                    <p class="text-muted small mb-0">Front and back body-region intensity at baseline.</p>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6"><div class="border rounded-3 p-2 comparison-heatmap"><?= $baseline['heatmap']['frontSvg'] ?? '<p class="text-muted small mb-0">No front heat map.</p>' ?></div></div>
                        <div class="col-6"><div class="border rounded-3 p-2 comparison-heatmap"><?= $baseline['heatmap']['backSvg'] ?? '<p class="text-muted small mb-0">No back heat map.</p>' ?></div></div>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-xl-6">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Follow-up Heat Maps</h5>
                    <p class="text-muted small mb-0">Front and back body-region intensity after intervention.</p>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6"><div class="border rounded-3 p-2 comparison-heatmap"><?= $followUp['heatmap']['frontSvg'] ?? '<p class="text-muted small mb-0">No front heat map.</p>' ?></div></div>
                        <div class="col-6"><div class="border rounded-3 p-2 comparison-heatmap"><?= $followUp['heatmap']['backSvg'] ?? '<p class="text-muted small mb-0">No back heat map.</p>' ?></div></div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Improved Regions</h5>
                    <p class="text-muted small mb-0">Regions showing lower intensity in follow-up reporting.</p>
                </div>
                <div class="card-body">
                    <?php foreach ($improvedRegions as $region): ?>
                        <div class="border rounded-3 p-3 mb-2">
                            <div class="fw-semibold"><?= htmlspecialchars((string) ($region['region'] ?? 'Region')) ?></div>
                            <div class="text-muted small"><?= htmlspecialchars((string) ($region['side'] ?? 'Side')) ?> | Baseline <?= htmlspecialchars((string) ($region['baselineIntensity'] ?? 0)) ?> -> Follow-up <?= htmlspecialchars((string) ($region['followUpIntensity'] ?? 0)) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($improvedRegions === []): ?>
                        <div class="text-muted">No improved regions recorded.</div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
        <div class="col-xl-6">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Worsened Regions</h5>
                    <p class="text-muted small mb-0">Regions that moved unfavorably after follow-up review.</p>
                </div>
                <div class="card-body">
                    <?php foreach ($worsenedRegions as $region): ?>
                        <div class="border rounded-3 p-3 mb-2">
                            <div class="fw-semibold"><?= htmlspecialchars((string) ($region['region'] ?? 'Region')) ?></div>
                            <div class="text-muted small"><?= htmlspecialchars((string) ($region['side'] ?? 'Side')) ?> | Baseline <?= htmlspecialchars((string) ($region['baselineIntensity'] ?? 0)) ?> -> Follow-up <?= htmlspecialchars((string) ($region['followUpIntensity'] ?? 0)) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($worsenedRegions === []): ?>
                        <div class="text-muted">No worsened regions recorded.</div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</div>
