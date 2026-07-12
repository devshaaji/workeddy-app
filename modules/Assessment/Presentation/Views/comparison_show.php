<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$comparison = is_array($comparison ?? null) ? $comparison : [];
$baseline = is_array($baseline ?? null) ? $baseline : [];
$followUp = is_array($followUp ?? null) ? $followUp : [];
$correctiveAction = is_array($correctiveAction ?? null) ? $correctiveAction : [];
$comparisonId = (string) ($comparison['uuid'] ?? ($routeParams['comparisonId'] ?? ''));
$comparisonPdfUrl = (string) ($comparisonPdfUrl ?? '/api/v1/reporting/comparison/' . rawurlencode($comparisonId) . '/pdf');
$can = is_array($can ?? null) ? $can : [];
$e = static fn(mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$display = static fn(mixed $value, string $fallback = '--'): string => htmlspecialchars((string) (($value ?? '') !== '' ? $value : $fallback), ENT_QUOTES, 'UTF-8');
$formatScore = static fn(mixed $value): string => $value === null || $value === '' ? '--' : number_format((float) $value, 0);
$formatDate = static fn(mixed $value): string => $value ? date('F j, Y', strtotime((string) $value)) : '--';
$bodyRegions = static function (array $regions) use ($e): string {
    if ($regions === []) {
        return '<p class="text-muted small mb-0">No body regions recorded.</p>';
    }

    return implode('', array_map(static function (array $region) use ($e): string {
        $delta = (int) ($region['delta'] ?? 0);
        $deltaClass = $delta < 0 ? 'text-success' : ($delta > 0 ? 'text-danger' : 'text-muted');

        return '<div class="border rounded-3 p-3 mb-2">'
            . '<div class="fw-semibold">' . $e($region['region'] ?? 'Region') . ' <span class="text-muted">(' . $e($region['side'] ?? 'side') . ')</span></div>'
            . '<div class="small text-muted">Baseline ' . $e($region['baselineIntensity'] ?? 0) . ' -> Follow-up ' . $e($region['followUpIntensity'] ?? 0) . '</div>'
            . '<div class="small ' . $deltaClass . '">Delta: ' . $e($delta) . '</div>'
            . '</div>';
    }, $regions));
};
$screenshot = static function (array $assessment) use ($e): string {
    $src = (string) ($assessment['screenshotUrl'] ?? '');
    if ($src === '') {
        return '<div class="border rounded-3 p-4 text-center text-muted small">No screenshot available.</div>';
    }

    return '<img src="' . $e($src) . '" alt="' . $e($assessment['screenshotAlt'] ?? 'Assessment screenshot') . '" class="img-fluid rounded-3 border">';
};
$heatmap = static function (array $assessment): string {
    $heatmap = is_array($assessment['bodyRegionHeatmap'] ?? null) ? $assessment['bodyRegionHeatmap'] : [];
    $front = (string) ($heatmap['frontSvg'] ?? '');
    $back = (string) ($heatmap['backSvg'] ?? '');

    if ($front === '' && $back === '') {
        return '<p class="text-muted small mb-0">No heat map evidence recorded.</p>';
    }

    return '<div class="row g-3">'
        . '<div class="col-6"><div class="border rounded-3 p-2 h-100"><div class="small text-muted mb-2">Front</div>' . $front . '</div></div>'
        . '<div class="col-6"><div class="border rounded-3 p-2 h-100"><div class="small text-muted mb-2">Back</div>' . $back . '</div></div>'
        . '</div>';
};
$pageTitle = 'Comparison Report Detail';
$pagePurpose = 'Before and after improvement proof';
$pageActions = [
    ['label' => 'Comparison register', 'url' => '/assessments/comparisons', 'class' => 'btn btn-outline-secondary', 'icon' => 'list-ul'],
    ['label' => 'Export to PDF', 'url' => $comparisonPdfUrl, 'class' => 'btn btn-primary', 'icon' => 'filetype-pdf'],
];
if (!empty($can['lockComparison']) && ($comparison['status'] ?? '') !== 'locked') {
    $pageActions[] = ['label' => 'Lock report', 'url' => '#', 'class' => 'btn btn-outline-secondary', 'icon' => 'lock', 'id' => 'lockComparisonReportBtn'];
}
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Assessments', 'url' => '/assessments'],
    ['label' => 'Comparisons', 'url' => '/assessments/comparisons'],
    ['label' => 'Report Detail', 'url' => null],
];
$pageScripts = ['js/modules/assessment-comparison-show.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div
    id="comparisonDetailPage"
    data-comparison-uuid="<?= $e($comparisonId) ?>"
    data-lock-url="<?= $e('/api/v1/comparison-reports/' . rawurlencode($comparisonId) . '/lock') ?>"
    data-comparison-status="<?= $e($comparison['status'] ?? '') ?>"
>
    <div id="comparisonDetailAlert"></div>

    <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-4">
                <div>
                    <span class="badge bg-label-primary mb-3">Comparison evidence</span>
                    <h4 class="mb-2 fw-bold">Baseline and follow-up results side by side</h4>
                    <p class="text-muted mb-0">This report shows the observed change after corrective action completion, using the same scoring method on both assessments.</p>
                </div>
                <div class="text-lg-end">
                    <div class="mb-2">
                        <span class="badge bg-label-secondary"><?= $e(str_replace('_', ' ', (string) ($comparison['status'] ?? 'generated'))) ?></span>
                    </div>
                    <div class="text-muted small">Generated <?= $display($formatDate($comparison['generatedAt'] ?? null)) ?></div>
                    <div class="text-muted small">Locked <?= $display($formatDate($comparison['lockedAt'] ?? null)) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <span class="text-muted small d-block mb-2">Estimated risk reduction</span>
                    <h3 class="mb-1 fw-bold"><?= $display(number_format((float) ($comparison['riskReductionPercent'] ?? 0), 1) . '%') ?></h3>
                    <p class="mb-0 text-muted small">Estimated using the baseline and follow-up scores.</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <span class="text-muted small d-block mb-2">Original task score</span>
                    <h3 class="mb-1 fw-bold"><?= $display($formatScore($comparison['originalTaskScore'] ?? null)) ?></h3>
                    <p class="mb-0 text-muted small">Risk level before: <?= $display($comparison['riskLevelBefore'] ?? null) ?></p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <span class="text-muted small d-block mb-2">Corrected task score</span>
                    <h3 class="mb-1 fw-bold"><?= $display($formatScore($comparison['correctedTaskScore'] ?? null)) ?></h3>
                    <p class="mb-0 text-muted small">Risk level after: <?= $display($comparison['riskLevelAfter'] ?? null) ?></p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <span class="text-muted small d-block mb-2">Corrective action</span>
                    <h5 class="mb-1 fw-semibold"><?= $display($correctiveAction['title'] ?? 'Not linked') ?></h5>
                    <p class="mb-0 text-muted small"><?= $display($correctiveAction['status'] ?? 'No linked action') ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Side-by-side results</h5>
                    <p class="mb-0 text-muted small">The first reviewed assessment is treated as the locked baseline.</p>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                    <div>
                                        <span class="badge bg-label-secondary mb-2">Before</span>
                                        <h6 class="mb-1 fw-semibold">Baseline assessment</h6>
                                        <div class="small text-muted">Date: <?= $display($formatDate($baseline['createdAt'] ?? null)) ?></div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-semibold"><?= $display($formatScore($baseline['score']['raw'] ?? null)) ?></div>
                                        <div class="small text-muted"><?= $display($baseline['riskLevel'] ?? null) ?></div>
                                    </div>
                                </div>
                                <div class="mb-3"><?= $screenshot($baseline) ?></div>
                                <div class="mb-3"><?= $heatmap($baseline) ?></div>
                                <div>
                                    <div class="small text-muted mb-1">Reviewer notes</div>
                                    <div class="bg-lighter rounded-3 p-3"><?= $display($baseline['reviewerNotes'] ?? 'No reviewer notes captured.') ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                    <div>
                                        <span class="badge bg-label-info mb-2">After</span>
                                        <h6 class="mb-1 fw-semibold">Follow-up assessment</h6>
                                        <div class="small text-muted">Date: <?= $display($formatDate($followUp['createdAt'] ?? null)) ?></div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-semibold"><?= $display($formatScore($followUp['score']['raw'] ?? null)) ?></div>
                                        <div class="small text-muted"><?= $display($followUp['riskLevel'] ?? null) ?></div>
                                    </div>
                                </div>
                                <div class="mb-3"><?= $screenshot($followUp) ?></div>
                                <div class="mb-3"><?= $heatmap($followUp) ?></div>
                                <div>
                                    <div class="small text-muted mb-1">Reviewer notes</div>
                                    <div class="bg-lighter rounded-3 p-3"><?= $display($followUp['reviewerNotes'] ?? 'No reviewer notes captured.') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Body regions improved</h5>
                    <p class="mb-0 text-muted small">Lower intensity in follow-up compared with the baseline is shown as improved.</p>
                </div>
                <div class="card-body">
                    <?= $bodyRegions(is_array($comparison['bodyRegionsImproved'] ?? null) ? $comparison['bodyRegionsImproved'] : []) ?>
                </div>
            </div>

            <div class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Comparison evidence</h5>
                    <p class="mb-0 text-muted small">Stored report fields and workflow evidence used to explain the change.</p>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-md-4 text-muted">Baseline assessment UUID</dt>
                        <dd class="col-md-8 text-break"><?= $display($baseline['uuid'] ?? null) ?></dd>
                        <dt class="col-md-4 text-muted">Follow-up assessment UUID</dt>
                        <dd class="col-md-8 text-break"><?= $display($followUp['uuid'] ?? null) ?></dd>
                        <dt class="col-md-4 text-muted">Scoring method</dt>
                        <dd class="col-md-8 text-break"><?= $display($comparison['model'] ?? null) ?></dd>
                        <dt class="col-md-4 text-muted">Direction</dt>
                        <dd class="col-md-8 text-break"><?= $display($comparison['direction'] ?? null) ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Corrective action summary</h5>
                    <p class="mb-0 text-muted small">The comparison is tied to the verified corrective action that triggered the follow-up.</p>
                </div>
                <div class="card-body">
                    <?php if ($correctiveAction !== []): ?>
                        <dl class="row mb-0">
                            <dt class="col-5 text-muted">Title</dt><dd class="col-7 text-break"><?= $display($correctiveAction['title'] ?? null) ?></dd>
                            <dt class="col-5 text-muted">Status</dt><dd class="col-7"><?= $display($correctiveAction['status'] ?? null) ?></dd>
                            <dt class="col-5 text-muted">Completed</dt><dd class="col-7"><?= $display($formatDate($correctiveAction['completedAt'] ?? null)) ?></dd>
                            <dt class="col-5 text-muted">Verified</dt><dd class="col-7"><?= $display($formatDate($correctiveAction['verifiedAt'] ?? null)) ?></dd>
                            <dt class="col-5 text-muted">Due date</dt><dd class="col-7"><?= $display($formatDate($correctiveAction['followUpAssessmentDueDate'] ?? null)) ?></dd>
                        </dl>
                    <?php else: ?>
                        <div class="text-muted">No corrective action was linked to this report.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Follow-up notes</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="small text-muted mb-1">Baseline reviewer notes</div>
                        <div class="bg-lighter rounded-3 p-3"><?= $display($baseline['reviewerNotes'] ?? 'No reviewer notes captured.') ?></div>
                    </div>
                    <div>
                        <div class="small text-muted mb-1">Follow-up reviewer notes</div>
                        <div class="bg-lighter rounded-3 p-3"><?= $display($followUp['reviewerNotes'] ?? 'No reviewer notes captured.') ?></div>
                    </div>
                </div>
            </div>

            <div class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Export</h5>
                </div>
                <div class="card-body d-grid gap-2">
                    <a class="btn btn-primary" href="<?= $e($comparisonPdfUrl) ?>" target="_blank" rel="noopener">
                        <i class="bi bi-filetype-pdf me-1"></i>Export to PDF
                    </a>
                    <a class="btn btn-outline-secondary" href="/assessments/comparisons">
                        <i class="bi bi-list-ul me-1"></i>Back to register
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
