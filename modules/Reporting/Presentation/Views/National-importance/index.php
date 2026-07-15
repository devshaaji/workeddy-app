<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 5);
$pageTitle = 'National Importance';
$pagePurpose = 'Why WorkEddy matters for workforce health';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Reports', 'url' => null],
    ['label' => 'National Importance', 'url' => null],
];
$pageActions = [
    ['label' => 'Download PDF', 'url' => '/api/v1/reporting/national-importance/pdf', 'class' => 'btn btn-primary', 'icon' => 'file-earmark-pdf'],
    ['label' => 'Manage Statistics', 'url' => '/reporting/national-importance/manage', 'class' => 'btn btn-outline-secondary', 'icon' => 'pencil-square'],
];
require $v2Root . '/shared/Views/Partials/page_header.php';

$dynamic = is_array($dynamic ?? null) ? $dynamic : [];
$categoryLabels = is_array($categoryLabels ?? null) ? $categoryLabels : [];
$statisticsByCategory = is_array($statisticsByCategory ?? null) ? $statisticsByCategory : [];
$context = is_array($context ?? null) ? $context : [];
$notes = is_array($notes ?? null) ? $notes : [];

$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$num = static fn($value, int $decimals = 0): string => number_format((float) $value, $decimals);
?>

<div class="container-xxl flex-grow-1 pb-4" id="nationalImportancePage">

    <!-- Intro / national problem summary -->
    <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
        <div class="card-body p-4">
            <span class="badge bg-label-primary mb-2">National workforce health context</span>
            <h5 class="mb-2">Why WorkEddy Matters for Workforce Health</h5>
            <p class="text-muted mb-2"><?= nl2br($e($context['problemSummary'] ?? '')) ?></p>
            <p class="text-muted small mb-0">
                <i class="bi bi-info-circle me-1"></i>
                National statistics below are public reference data entered by authorized administrators, each with
                its own source citation. WorkEddy platform activity figures are aggregated and de-identified across
                every organization on the platform \u2014 no individual worksite, worker, or organization is identified.
            </p>
        </div>
    </div>

    <!-- Industry risk cards (static, source-cited national context) -->
    <div class="d-flex align-items-center gap-2 mb-3">
        <span class="badge bg-label-secondary">National context</span>
        <div>
            <h6 class="mb-0 fw-bold">Industry Risk Areas</h6>
            <p class="text-muted small mb-0">Sourced national statistics for each required topic area.</p>
        </div>
    </div>
    <div class="row g-4 mb-4">
        <?php foreach ($categoryLabels as $categoryKey => $categoryLabel): ?>
            <?php $stats = $statisticsByCategory[$categoryKey] ?? []; ?>
            <div class="col-md-6 col-xl-4">
                <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><?= $e($categoryLabel) ?></h6>
                        <?php if ($stats === []): ?>
                            <p class="text-muted small mb-0">No sourced statistics entered for this topic area yet.</p>
                        <?php else: ?>
                            <?php foreach ($stats as $stat): ?>
                                <div class="mb-3 pb-2 border-bottom">
                                    <div class="d-flex justify-content-between align-items-baseline">
                                        <span class="small text-muted"><?= $e($stat['title']) ?></span>
                                        <span class="fw-bold"><?= $e($stat['value']) ?><?= $stat['unit'] ? ' ' . $e($stat['unit']) : '' ?></span>
                                    </div>
                                    <div class="small text-muted">
                                        Source: <a href="<?= $e($stat['sourceUrl']) ?>" target="_blank" rel="noopener"><?= $e($stat['sourceName']) ?></a>
                                        (<?= $e($stat['sourceYear']) ?>)
                                    </div>
                                    <div class="small text-muted">
                                        Updated: <?= $e(substr((string) ($stat['updatedAt'] ?? $stat['dateAdded'] ?? ''), 0, 10)) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- WorkEddy platform activity (dynamic, cross-organization) -->
    <div class="d-flex align-items-center gap-2 mb-3">
        <span class="badge bg-label-success">Platform data</span>
        <div>
            <h6 class="mb-0 fw-bold">Observed WorkEddy Platform Activity</h6>
            <p class="text-muted small mb-0">
                Aggregated across every organization.
                <?= $dynamic['generatedAt'] ? 'Last computed: ' . $e($dynamic['generatedAt']) . ' UTC.' : 'Not yet computed \u2014 the nightly refresh job has not run.' ?>
            </p>
        </div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <span class="d-block text-muted small">Industries Represented</span>
                    <h3 class="mb-0 fw-bold"><?= $e($dynamic['industriesRepresented'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <span class="d-block text-muted small">Worksites Assessed</span>
                    <h3 class="mb-0 fw-bold"><?= $e($dynamic['worksitesAssessed'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <span class="d-block text-muted small">High-Risk Tasks Identified</span>
                    <h3 class="mb-0 fw-bold"><?= $e($dynamic['highRiskTasksIdentified'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <span class="d-block text-muted small">Average Risk Reduction After Correction</span>
                    <h3 class="mb-0 fw-bold"><?= $num($dynamic['averageRiskReductionAfterCorrection'] ?? 0, 1) ?>%</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h6 class="card-title mb-1">Common High-Strain Tasks</h6>
                    <p class="text-muted small mb-0">Most frequently flagged high-risk tasks, platform-wide.</p>
                </div>
                <div class="card-body">
                    <div style="height: 260px;"><canvas id="highStrainTasksChart"></canvas></div>
                    <div id="highStrainTasksEmpty" class="text-center text-muted small py-4 d-none">No data yet.</div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h6 class="card-title mb-1">Body Region Burden</h6>
                    <p class="text-muted small mb-0">Most common body regions affected, platform-wide.</p>
                </div>
                <div class="card-body">
                    <div style="height: 260px;"><canvas id="bodyRegionChart"></canvas></div>
                    <div id="bodyRegionEmpty" class="text-center text-muted small py-4 d-none">No data yet.</div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h6 class="card-title mb-1">Corrective Action Outcomes</h6>
                    <p class="text-muted small mb-0">Platform-wide corrective action status distribution across open, active, completed, verified, overdue, and rejected actions.</p>
                </div>
                <div class="card-body">
                    <div style="height: 260px;"><canvas id="correctiveActionsChart"></canvas></div>
                    <div id="correctiveActionsEmpty" class="text-center text-muted small py-4 d-none">No data yet.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Worker discomfort trend -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h6 class="card-title mb-1">Worker Discomfort Trend</h6>
                    <p class="text-muted small mb-0">Monthly average self-reported discomfort, platform-wide.</p>
                </div>
                <div class="card-body">
                    <div style="height: 220px;"><canvas id="discomfortTrendChart"></canvas></div>
                    <div id="discomfortTrendEmpty" class="text-center text-muted small py-4 d-none">No data yet.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Future research -->
    <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-2"><i class="bi bi-compass me-2 text-primary"></i>Future Research Directions</h6>
            <p class="text-muted mb-0"><?= nl2br($e($context['futureResearch'] ?? '')) ?></p>
        </div>
    </div>

    <!-- Managed methodology and limitations -->
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body p-4">
                    <span class="badge bg-label-primary mb-2">Methodology</span>
                    <h6 class="fw-bold mb-2">What WorkEddy Measures</h6>
                    <p class="text-muted mb-0"><?= nl2br($e($notes['methodology'] ?? '')) ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body p-4">
                    <span class="badge bg-label-warning mb-2">Limitations</span>
                    <h6 class="fw-bold mb-2">What WorkEddy Does Not Claim</h6>
                    <p class="text-muted mb-0"><?= nl2br($e($notes['limitations'] ?? '')) ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body p-4">
                    <span class="badge bg-label-info mb-2">Privacy</span>
                    <h6 class="fw-bold mb-2">How Privacy Is Protected</h6>
                    <p class="text-muted mb-0"><?= nl2br($e($notes['privacy'] ?? '')) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart data, consumed by national-importance.js -->
    <script type="application/json" id="nationalImportanceChartData">
        <?= json_encode([
            'commonHighStrainTasks' => $dynamic['commonHighStrainTasks'] ?? [],
            'bodyRegionBurden' => $dynamic['bodyRegionBurden'] ?? [],
            'correctiveActionOutcomes' => $dynamic['commonCorrectiveActions'] ?? [],
            'workerDiscomfortTrend' => $dynamic['workerDiscomfortTrend'] ?? [],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>
    </script>
</div>
