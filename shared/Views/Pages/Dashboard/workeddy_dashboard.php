<?php

declare(strict_types=1);

$pageTitle = 'Dashboard';
$activePage = 'dashboard';
$greeting = $greeting ?? 'Welcome back!';
$warmMessage = $warmMessage ?? '';
$pageScripts = [];

$filters = is_array($filters ?? null) ? $filters : [];
$summary = is_array($summary ?? null) ? $summary : [];
$timeline = is_array($timeline ?? null) ? $timeline : [];

$query = http_build_query(array_filter($filters, static fn($value): bool => $value !== ''));
$pdfUrl = '/api/v1/reporting/pilot-summary/pdf' . ($query !== '' ? '?' . $query : '');
$csvUrl = '/api/v1/reporting/pilot-summary/csv' . ($query !== '' ? '?' . $query : '');

$e = static fn(mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$label = static fn(mixed $value, string $fallback = '--'): string => htmlspecialchars((string) (($value ?? '') !== '' ? $value : $fallback), ENT_QUOTES, 'UTF-8');
$intVal = static fn(string $key): int => (int) ($summary[$key] ?? 0);
$floatVal = static fn(string $key): float => (float) ($summary[$key] ?? 0.0);
$pct = static fn(float $value): float => max(0.0, min(100.0, $value));

$assessments = max(1, $intVal('assessments'));
$reviewedAssessments = $intVal('reviewed_assessments');
$highRiskTasks = $intVal('high_risk_tasks_identified');
$correctiveCompleted = $intVal('corrective_actions_completed');
$reviewBacklog = max(0, $assessments - $reviewedAssessments);
$reviewCoverage = $pct(($reviewedAssessments / $assessments) * 100);
$highRiskRate = $pct(($highRiskTasks / $assessments) * 100);
$averageDiscomfort = $floatVal('average_discomfort');
$workerResponses = $intVal('worker_feedback_total');
$worksitesEnrolled = $intVal('worksites_enrolled');
$workersParticipating = $intVal('workers_participating');
$taskVideosUploaded = $intVal('task_videos_uploaded');
$comparisonReports = $intVal('comparison_reports');
$avgWorkersPerWorksite = $workersParticipating / max(1, $worksitesEnrolled);
$anonymousRate = $pct($floatVal('worker_feedback_anonymous_rate'));
$topMetricCards = [
    [
        'id' => 'dashboardMetricWorksites',
        'label' => 'Worksites',
        'value' => (string) $worksitesEnrolled,
        'deltaClass' => 'text-success',
        'deltaIcon' => 'bi-arrow-up-short',
        'deltaText' => number_format($avgWorkersPerWorksite, 1) . ' workers / site',
        'avatarClass' => 'bg-label-primary',
        'icon' => 'bi-buildings',
        'menuLink' => '/reporting/pilot-summary',
        'menuLabel' => 'Open Summary',
    ],
    [
        'id' => 'dashboardMetricWorkers',
        'label' => 'Active Workers',
        'value' => (string) $workersParticipating,
        'deltaClass' => 'text-success',
        'deltaIcon' => 'bi-arrow-up-short',
        'deltaText' => number_format($anonymousRate, 1) . '% anonymous',
        'avatarClass' => 'bg-label-info',
        'icon' => 'bi-people',
        'menuLink' => '/worker-voice/trends',
        'menuLabel' => 'Open Trends',
    ],
    [
        'id' => 'dashboardMetricAssessments',
        'label' => 'Assessments',
        'value' => (string) $intVal('assessments'),
        'deltaClass' => 'text-success',
        'deltaIcon' => 'bi-arrow-up-short',
        'deltaText' => number_format($reviewCoverage, 1) . '% reviewed',
        'avatarClass' => 'bg-label-warning',
        'icon' => 'bi-clipboard-pulse',
        'menuLink' => '/assessments',
        'menuLabel' => 'Open Assessments',
    ],
    [
        'id' => 'dashboardMetricRisk',
        'label' => 'High-Risk Tasks',
        'value' => (string) $highRiskTasks,
        'deltaClass' => 'text-danger',
        'deltaIcon' => 'bi-arrow-up-short',
        'deltaText' => number_format($highRiskRate, 1) . '% risk rate',
        'avatarClass' => 'bg-label-danger',
        'icon' => 'bi-exclamation-triangle',
        'menuLink' => '/corrective-actions',
        'menuLabel' => 'Open Actions',
    ],
];
?>


<div class="card mb-4 position-relative overflow-hidden">
    <div class="dropdown position-absolute top-0 end-0 mt-4 me-4 z-2">
        <button
            type="button"
            class="btn btn-sm btn-icon btn-text-secondary rounded-pill dashboard-actions-trigger"
            data-bs-toggle="dropdown"
            data-bs-placement="left"
            title="Dashboard actions"
            aria-expanded="false">
            <i class="bi bi-three-dots-vertical"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= $e($pdfUrl) ?>"><i class="bi bi-file-earmark-pdf me-2"></i>Export PDF</a></li>
            <li><a class="dropdown-item" href="<?= $e($csvUrl) ?>"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Export CSV</a></li>
            <li><a class="dropdown-item" href="/reporting/pilot-summary"><i class="bi bi-bar-chart-line me-2"></i>Open Pilot Summary</a></li>
        </ul>
    </div>
    <div class="d-flex align-items-start row">
        <div class="col-sm-7">
            <div class="card-body">
                <h5 class="card-title text-primary mb-3 pe-5"><?= $e($greeting) ?></h5>
                <p class="mb-6 text-muted"><?= $e($warmMessage) ?></p>
                <a href="/reporting/pilot-summary" class="btn btn-sm btn-label-primary">View Pilot Summary</a>
            </div>
        </div>
        <div class="col-sm-5 text-center text-sm-start">
            <div class="card-body pb-0 px-0 px-md-6">
                <img
                    src="/assets/img/illustrations/girl-with-laptop-illustration.png"
                    height="175"
                    class="img-fluid scaleX-n1-rtl"
                    alt="Dashboard overview illustration">
            </div>
        </div>
    </div>
</div>

<div class="row">
    <?php foreach ($topMetricCards as $card): ?>
        <div class="col-lg-6 col-md-12 col-6 mb-6">
            <section class="card h-100">
                <div class="card-body">
                    <div class="card-title d-flex align-items-start justify-content-between mb-4">
                        <div class="avatar flex-shrink-0">
                            <span class="avatar-initial rounded <?= $e($card['avatarClass']) ?> text-heading">
                                <i class="bi <?= $e($card['icon']) ?>"></i>
                            </span>
                        </div>
                        <div class="dropdown">
                            <button class="btn p-0" type="button" id="<?= $e($card['id']) ?>" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="bi bi-three-dots-vertical text-body-secondary"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="<?= $e($card['id']) ?>">
                                <a class="dropdown-item" href="<?= $e($card['menuLink']) ?>"><?= $e($card['menuLabel']) ?></a>
                            </div>
                        </div>
                    </div>
                    <p class="mb-1"><?= $e($card['label']) ?></p>
                    <h4 class="card-title mb-3"><?= $e($card['value']) ?></h4>
                    <small class="<?= $e($card['deltaClass']) ?> fw-medium">
                        <i class="bi <?= $e($card['deltaIcon']) ?>"></i> <?= $e($card['deltaText']) ?>
                    </small>
                </div>
            </section>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-xxl-8">
        <section class="card h-100">
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th>Worker Responses</th>
                                        <th class="text-end">Average Discomfort</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice(array_reverse($timeline), 0, 8) as $item): ?>
                                        <?php $discomfort = (float) ($item['averageDiscomfort'] ?? 0); ?>
                                        <tr>
                                            <td class="fw-semibold"><?= $label($item['date'] ?? null) ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <span><?= (int) ($item['responses'] ?? 0) ?></span>
                                                    <div class="progress w-100" style="height: 8px;">
                                                        <div class="progress-bar" style="width: <?= $pct(((int) ($item['responses'] ?? 0) / max(1, $workerResponses)) * 100) ?>%;"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-end"><?= number_format($discomfort, 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if ($timeline === []): ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-4">No worker trend data available.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card border shadow-none mb-4">
                            <div class="card-body px-xl-6 py-8 d-flex align-items-center flex-column">
                                <div class="text-center mb-4">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-label-primary">Review</button>
                                        <button type="button" class="btn btn-label-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                            <span class="visually-hidden">Toggle Dropdown</span>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="/assessments/reviewer-queue">Reviewer Queue</a></li>
                                            <li><a class="dropdown-item" href="/assessments">Assessments</a></li>
                                            <li><a class="dropdown-item" href="/assessments/comparisons">Comparisons</a></li>
                                        </ul>
                                    </div>
                                </div>

                                <div
                                    class="d-flex align-items-center justify-content-center rounded-circle mb-4"
                                    style="width: 156px; height: 156px; background: conic-gradient(var(--bs-primary) 0deg <?= number_format($reviewCoverage * 3.6, 2, '.', '') ?>deg, var(--bs-border-color) <?= number_format($reviewCoverage * 3.6, 2, '.', '') ?>deg 360deg);">
                                    <div class="d-flex flex-column align-items-center justify-content-center rounded-circle bg-body" style="width: 116px; height: 116px;">
                                        <span class="small text-muted">Review</span>
                                        <h3 class="mb-0"><?= number_format($reviewCoverage, 1) ?>%</h3>
                                    </div>
                                </div>
                                <div class="text-center fw-medium my-2"><?= $reviewedAssessments ?> of <?= $assessments ?> reviewed</div>

                                <div class="d-flex gap-3 justify-content-between w-100 mt-3">
                                    <div class="d-flex">
                                        <div class="avatar me-2">
                                            <span class="avatar-initial rounded-2 bg-label-primary"><i class="bi bi-check2-circle text-primary"></i></span>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <small>Reviewed</small>
                                            <h6 class="mb-0"><?= $reviewedAssessments ?></h6>
                                        </div>
                                    </div>
                                    <div class="d-flex">
                                        <div class="avatar me-2">
                                            <span class="avatar-initial rounded-2 bg-label-warning"><i class="bi bi-hourglass-split text-warning"></i></span>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <small>Pending</small>
                                            <h6 class="mb-0"><?= $reviewBacklog ?></h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
<div class="row g-4">
    <div class="col-12">
        <section class="card">
            <div class="card-header d-flex justify-content-between align-items-start">
                <div>
                    <h5 class="card-title mb-1">Command Center</h5>
                    <p class="text-muted small mb-0">Move from signal detection into review, remediation, and evidence workflows.</p>
                </div>
                <a href="/reporting/pilot-summary" class="btn btn-sm btn-outline-secondary">Open reporting hub</a>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6 col-xl-3">
                        <a href="/assessments/reviewer-queue" class="card border shadow-none h-100 text-decoration-none">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <span class="avatar avatar-sm"><span class="avatar-initial rounded bg-label-warning"><i class="bi bi-person-check"></i></span></span>
                                    <span class="badge bg-label-warning"><?= $reviewBacklog ?></span>
                                </div>
                                <h6 class="mb-1 text-dark">Review Backlog</h6>
                                <p class="text-muted small mb-0">Open assessments waiting for reviewer confirmation before final use.</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <a href="/corrective-actions" class="card border shadow-none h-100 text-decoration-none">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <span class="avatar avatar-sm"><span class="avatar-initial rounded bg-label-success"><i class="bi bi-check2-square"></i></span></span>
                                    <span class="badge bg-label-success"><?= $correctiveCompleted ?></span>
                                </div>
                                <h6 class="mb-1 text-dark">Corrective Flow</h6>
                                <p class="text-muted small mb-0">Inspect assignment, progress, completion, and follow-up evidence.</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <a href="/worker-voice/trends" class="card border shadow-none h-100 text-decoration-none">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <span class="avatar avatar-sm"><span class="avatar-initial rounded bg-label-danger"><i class="bi bi-person-hearts"></i></span></span>
                                    <span class="badge bg-label-danger"><?= number_format($averageDiscomfort, 2) ?></span>
                                </div>
                                <h6 class="mb-1 text-dark">Discomfort Trends</h6>
                                <p class="text-muted small mb-0">Follow worker-reported strain patterns before they become repeat exposure.</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <a href="/assessments/comparisons" class="card border shadow-none h-100 text-decoration-none">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <span class="avatar avatar-sm"><span class="avatar-initial rounded bg-label-primary"><i class="bi bi-sliders2"></i></span></span>
                                    <span class="badge bg-label-primary"><?= $intVal('comparison_reports') ?></span>
                                </div>
                                <h6 class="mb-1 text-dark">Change Evidence</h6>
                                <p class="text-muted small mb-0">Use before-and-after comparisons to prove intervention effect instead of assumptions.</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
            return;
        }

        document.querySelectorAll('.dashboard-actions-trigger').forEach(function(el) {
            if (!bootstrap.Tooltip.getInstance(el)) {
                new bootstrap.Tooltip(el);
            }
        });
    });
</script>
