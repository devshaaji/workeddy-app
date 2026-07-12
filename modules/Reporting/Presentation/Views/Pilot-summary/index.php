<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Pilot Summary';
$pagePurpose = 'Organization reporting';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Reports', 'url' => null],
    ['label' => 'Pilot Summary', 'url' => null],
];
$filters = is_array($filters ?? null) ? $filters : [];
$query = http_build_query(array_filter($filters, static fn($value): bool => $value !== ''));
$pdfUrl = '/api/v1/reporting/pilot-summary/pdf' . ($query !== '' ? '?' . $query : '');
$csvUrl = '/api/v1/reporting/pilot-summary/csv' . ($query !== '' ? '?' . $query : '');
$pageActions = [
    ['label' => 'Download PDF', 'url' => $pdfUrl, 'class' => 'btn btn-primary', 'icon' => 'file-earmark-pdf'],
    ['label' => 'Export CSV (Excel)', 'url' => $csvUrl, 'class' => 'btn btn-outline-secondary', 'icon' => 'file-earmark-spreadsheet'],
];
require $v2Root . '/shared/Views/Partials/page_header.php';

$summary = is_array($summary ?? null) ? $summary : [];
$topBodyRegions = is_array($top_body_regions ?? null) ? $top_body_regions : [];
$topTasks = is_array($top_tasks ?? null) ? $top_tasks : [];
$timeline = is_array($timeline ?? null) ? $timeline : [];
$supervisorTopBodyRegions = is_array($supervisor_top_body_regions ?? null) ? $supervisor_top_body_regions : [];
$validationAgreement = is_array($validation_agreement ?? null) ? $validation_agreement : [];
$supervisorTimeline = is_array($supervisor_timeline ?? null) ? $supervisor_timeline : [];

// Discomfort trend direction, derived from the worker-feedback timeline, for the
// "Self-Reported Discomfort" outcome card (rising / falling / steady / not enough data).
$discomfortTrend = null;
if (count($timeline) >= 2) {
    $firstHalf = array_slice($timeline, 0, (int) ceil(count($timeline) / 2));
    $secondHalf = array_slice($timeline, (int) ceil(count($timeline) / 2));
    $avg = static function (array $rows): float {
        $values = array_map(static fn(array $row): float => (float) ($row['averageDiscomfort'] ?? 0), $rows);
        return $values === [] ? 0.0 : array_sum($values) / count($values);
    };
    $delta = $avg($secondHalf) - $avg($firstHalf);
    $discomfortTrend = match (true) {
        $delta <= -0.3 => ['label' => 'Improving', 'icon' => 'arrow-down-circle', 'class' => 'success'],
        $delta >= 0.3 => ['label' => 'Worsening', 'icon' => 'arrow-up-circle', 'class' => 'danger'],
        default => ['label' => 'Steady', 'icon' => 'dash-circle', 'class' => 'secondary'],
    };
}

$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$num = static fn($value, int $decimals = 0): string => number_format((float) $value, $decimals);

// Body regions common in ergonomic assessments — used as a static fallback for
// the filter select if the worker-voice question catalog cannot be loaded client-side.
$bodyRegionFallback = ['Neck', 'Shoulders', 'Upper Back', 'Lower Back', 'Elbows', 'Wrists / Hands', 'Hips', 'Knees', 'Ankles / Feet'];
?>

<div
    class="container-xxl flex-grow-1 pb-4"
    id="pilotSummaryPage"
    data-organization-uuid="<?= $e($organizationUuid ?? '') ?>">

    <!-- Hero summary card -->
    <div class="card bg-transparent shadow-none my-6 border-0">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-4">
                <div class="flex-grow-1">
                    <span class="badge bg-label-primary mb-2">Pilot dashboard</span>

                    <p class="text-muted mb-4">Track implementation progress, discomfort trends, and reviewer agreement in one organization summary.</p>

                    <div class="d-flex justify-content-start flex-wrap gap-4 me-12">
                        <!-- Stat 1: Task Videos -->
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar avatar-lg">
                                <div class="avatar-initial">
                                    <div class="text-primary">
                                        <svg width="34" height="34" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <g id="Laptop">
                                                <path id="Vector" opacity="0.2" d="M5.9375 26.125V10.6875C5.9375 10.0576 6.18772 9.45352 6.63312 9.00812C7.07852 8.56272 7.68261 8.3125 8.3125 8.3125H29.6875C30.3174 8.3125 30.9215 8.56272 31.3669 9.00812C31.8123 9.45352 32.0625 10.0576 32.0625 10.6875V26.125H5.9375Z" fill="currentColor"></path>
                                                <path id="Vector_2" d="M5.9375 26.125V10.6875C5.9375 10.0576 6.18772 9.45352 6.63312 9.00812C7.07852 8.56272 7.68261 8.3125 8.3125 8.3125H29.6875C30.3174 8.3125 30.9215 8.56272 31.3669 9.00812C31.8123 9.45352 32.0625 10.6875V26.125M21.375 13.0625H16.625M3.5625 26.125H34.4375V28.5C34.4375 29.1299 34.1873 29.734 33.7419 30.1794C33.2965 30.6248 32.6924 30.875 32.0625 30.875H5.9375C5.30761 30.875 4.70352 30.6248 4.25812 30.1794C3.81272 29.734 3.5625 29.1299 3.5625 28.5V26.125Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                            </g>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <div class="content-right">
                                <p class="mb-0 fw-medium">Videos Uploaded</p>
                                <h4 class="text-primary mb-0"><?= $e($summary['task_videos_uploaded'] ?? 0) ?></h4>
                            </div>
                        </div>

                        <!-- Stat 2: Reviewer Agreement -->
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar avatar-lg">
                                <div class="avatar-initial">
                                    <div class="text-info">
                                        <svg width="34" height="34" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <g id="Lightbulb">
                                                <path id="Vector" opacity="0.2" d="M11.6822 24.7891C10.2684 23.6898 9.12342 22.2832 8.33388 20.6759C7.54435 19.0685 7.13099 17.3025 7.12513 15.5117C7.09544 9.06954 12.2759 3.71095 18.7181 3.56251C21.2113 3.50341 23.6599 4.23078 25.7166 5.64147C27.7732 7.05217 29.3335 9.07457 30.1761 11.4219C31.0188 13.7691 31.101 16.3221 30.4112 18.7188C29.7214 21.1154 28.2945 23.2341 26.3329 24.7742C25.8996 25.1092 25.5486 25.5388 25.3068 26.0301C25.065 26.5215 24.9387 27.0617 24.9376 27.6094V28.5C24.9376 28.815 24.8125 29.117 24.5898 29.3397C24.3671 29.5624 24.0651 29.6875 23.7501 29.6875H14.2501C13.9352 29.6875 13.6331 29.5624 13.4104 29.3397C13.1877 29.117 13.0626 28.815 13.0626 28.5V27.6094C13.0589 27.0658 12.9329 26.5301 12.6939 26.0418C12.4549 25.5536 12.1091 25.1255 11.6822 24.7891Z" fill="currentColor"></path>
                                                <path id="Union" fill-rule="evenodd" clip-rule="evenodd" d="M25.1509 6.46609C23.2675 5.17419 21.0251 4.50807 18.7418 4.5622L18.7411 4.56221C18.4983 4.56781 18.2574 4.58151 18.0187 4.60305L18.6951 2.56275C21.398 2.49881 24.0526 3.28743 26.2822 4.8168C28.512 6.34629 30.2037 8.53899 31.1173 11.0839C32.031 13.6289 32.1201 16.3969 31.3722 18.9954C30.6243 21.5938 29.0772 23.8909 26.9505 25.5607L26.9445 25.5654L26.9445 25.5654C26.6318 25.8071 26.3785 26.1171 26.204 26.4717C26.0295 26.8263 25.9384 27.2161 25.9376 27.6113V28.5C25.9376 29.0801 25.7072 29.6365 25.2969 30.0468C24.8867 30.457 24.3303 30.6875 23.7501 30.6875H14.2501C13.67 30.6875 13.1136 30.457 12.7033 30.0468C12.2931 29.6365 12.0626 29.0801 12.0626 28.5V27.6131C12.0595 27.2206 11.9683 26.8339 11.7957 26.4815C11.6232 26.1289 11.3737 25.8196 11.0656 25.5764L11.7414 23.5378C11.9208 23.6976 12.1057 23.8517 12.296 23.9996L11.6821 24.7891L12.301 24.0035C12.8459 24.4328 13.2871 24.9792 13.5921 25.6022C13.8971 26.2252 14.0579 26.9089 14.0626 27.6025L14.0627 27.6094L14.0626 28.5C14.0626 28.5497 14.0824 28.5974 14.1175 28.6326C14.1527 28.6677 14.2004 28.6875 14.2501 28.6875H23.7501C23.7999 28.6875 23.8475 28.6677 23.8827 28.6326C23.9179 28.5974 23.9376 28.5497 23.9376 28.5V27.6094L23.9376 27.6074C23.939 26.9073 24.1004 26.2167 24.4096 25.5885C24.7181 24.9616 25.1657 24.4133 25.7181 23.9855C27.5131 22.5752 28.8188 20.6359 29.4502 18.4422C30.082 16.2473 30.0067 13.9093 29.235 11.7597C28.4633 9.61009 27.0344 7.75799 25.1509 6.46609ZM11.7414 23.5378L11.7414 23.5378L18.0187 4.60305L18.018 4.6031L18.6944 2.56276C11.7043 2.72418 6.09331 8.53234 6.12513 15.5156C6.13159 17.458 6.57998 19.3733 7.43632 21.1167C8.29225 22.8593 9.53332 24.3843 11.0656 25.5764L11.7414 23.5378ZM11.7414 23.5378C10.7009 22.6109 9.84781 21.4898 9.23145 20.235C8.50882 18.7638 8.13049 17.1475 8.12512 15.5084L8.12512 15.5071C8.09905 9.84987 12.4637 5.10456 18.018 4.6031L11.7414 23.5378ZM12.0627 34.4375C12.0627 33.8852 12.5104 33.4375 13.0627 33.4375H24.9377C25.49 33.4375 25.9377 33.8852 25.9377 34.4375C25.9377 34.9898 25.49 35.4375 24.9377 35.4375H13.0627C12.5104 35.4375 12.0627 34.9898 12.0627 34.4375ZM20.3697 7.44532C19.8252 7.35302 19.3089 7.71961 19.2166 8.26412C19.1243 8.80864 19.4909 9.32489 20.0354 9.41719C21.2827 9.62862 22.4336 10.222 23.3292 11.1154C24.2249 12.0087 24.8212 13.1581 25.0358 14.4048C25.1295 14.9491 25.6467 15.3144 26.191 15.2207C26.7353 15.127 27.1005 14.6098 27.0068 14.0655C26.722 12.4107 25.9305 10.8851 24.7417 9.69934C23.5528 8.51353 22.0252 7.72596 20.3697 7.44532Z" fill="currentColor"></path>
                                            </g>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <div class="content-right">
                                <p class="mb-0 fw-medium">Reviewer Agreement</p>
                                <h4 class="text-info mb-0"><?= $num($summary['reviewer_agreement_rate'] ?? 0.0, 1) ?>%</h4>
                            </div>
                        </div>

                        <!-- Stat 3: Assessments Completed -->
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar avatar-lg">
                                <div class="avatar-initial">
                                    <div class="text-warning">
                                        <svg width="34" height="34" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <g id="Check">
                                                <path id="Vector" opacity="0.2" d="M8.08984 29.9102C6.72422 28.5445 7.62969 25.6797 6.93203 24.0023C6.23438 22.325 3.5625 20.8555 3.5625 19C3.5625 17.1445 6.20469 15.7344 6.93203 13.9977C7.65938 12.2609 6.72422 9.45547 8.08984 8.08984C9.45547 6.72422 12.3203 7.62969 13.9977 6.93203C15.675 6.23438 17.1445 3.5625 19 3.5625C20.8555 3.5625 22.2656 6.20469 24.0023 6.93203C25.7391 7.65938 28.5445 6.72422 29.9102 8.08984C31.2758 9.45547 30.3703 12.3203 31.068 13.9977C31.7656 15.675 34.4375 17.1445 34.4375 19C34.4375 20.8555 31.7953 22.2656 31.068 24.0023C30.3406 25.7391 31.2758 28.5445 29.9102 29.9102C28.5445 31.2758 25.6797 30.3703 24.0023 31.068C22.325 31.7656 20.8555 34.4375 19 34.4375C17.1445 34.4375 15.7344 31.7953 13.9977 31.068C12.2609 30.3406 9.45547 31.2758 8.08984 29.9102Z" fill="currentColor"></path>
                                                <path id="Vector_2" d="M25.5312 15.4375L16.818 23.75L12.4687 19.5937M8.08984 29.9102C6.72422 28.5445 7.62969 25.6797 6.93203 24.0023C6.23437 22.325 3.5625 20.8555 3.5625 19C3.5625 17.1445 6.20469 15.7344 6.93203 13.9977C7.65937 12.2609 6.72422 9.45547 8.08984 8.08984C9.45547 6.72422 12.3203 7.62969 13.9977 6.93203C15.675 6.23437 17.1445 3.5625 19 3.5625C20.8555 3.5625 22.2656 6.20469 24.0023 6.93203C25.7391 7.65937 28.5445 6.72422 29.9102 8.08984C31.2758 9.45547 30.3703 12.3203 31.068 13.9977C31.7656 15.675 34.4375 17.1445 34.4375 19C34.4375 20.8555 31.7953 22.2656 31.068 24.0023C30.3406 25.7391 31.2758 28.5445 29.9102 29.9102C28.5445 31.2758 25.6797 30.3703 24.0023 31.068C22.325 31.7656 20.8555 34.4375 19 34.4375C17.1445 34.4375 15.7344 31.7953 13.9977 31.068C12.2609 30.3406 9.45547 31.2758 8.08984 29.9102Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                            </g>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <div class="content-right">
                                <p class="mb-0 fw-medium">Assessments</p>
                                <h4 class="text-warning mb-0"><?= $e($summary['assessments'] ?? 0) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-lg-end flex-shrink-0 align-self-lg-center">
                    <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="bi bi-<?= $query === '' ? 'funnel' : 'funnel-fill' ?> me-1"></i> Filter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Implementation Progress: how the pilot is rolling out -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h6 class="mb-0 fw-bold">Implementation Progress</h6>

        </div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="d-block text-muted small">Worksites Enrolled</span>
                            <h3 class="mb-0 fw-bold"><?= $e($summary['worksites_enrolled'] ?? 0) ?></h3>
                            <span class="small text-muted"><?= $e($summary['workers_participating'] ?? 0) ?> workers participating</span>
                        </div>
                        <div class="p-2 text-heading">
                            <i class="bi bi-diagram-3 fs-4 text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="d-block text-muted small">Assessments Completed</span>
                            <h3 class="mb-0 fw-bold"><?= $e($summary['assessments'] ?? 0) ?></h3>
                            <span class="small text-muted"><?= $e($summary['task_videos_uploaded'] ?? 0) ?> task videos uploaded</span>
                        </div>
                        <div class="p-2 text-heading">
                            <i class="bi bi-clipboard-pulse fs-4 text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="d-block text-muted small">Corrective Actions Completed</span>
                            <h3 class="mb-0 fw-bold"><?= $e($summary['corrective_actions_completed'] ?? 0) ?></h3>
                            <span class="small text-muted"><?= $e($summary['corrective_actions_assigned'] ?? 0) ?> assigned</span>
                        </div>
                        <div class="p-2 text-heading">
                            <i class="bi bi-check2-square fs-4 text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="d-block text-muted small">Corrective Actions Overdue</span>
                            <h3 class="mb-0 fw-bold"><?= $e($summary['corrective_actions_overdue'] ?? 0) ?></h3>
                            <span class="small text-muted">Avg. closure: <?= $num($summary['average_closure_days'] ?? 0, 1) ?> days</span>
                        </div>
                        <div class="p-2 text-heading">
                            <i class="bi bi-exclamation-triangle fs-4 text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Outcome & Impact: what the pilot is achieving -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h6 class="mb-0 fw-bold">Outcome &amp; Impact</h6>
        </div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="d-block text-muted small">High-Risk Tasks Identified</span>
                            <h3 class="mb-0 fw-bold"><?= $e($summary['high_risk_tasks_identified'] ?? 0) ?></h3>
                            <span class="small text-muted"><?= $e($summary['comparison_reports'] ?? 0) ?> before/after comparisons</span>
                        </div>
                        <div class="p-2 text-heading">
                            <i class="bi bi-exclamation-octagon fs-4 text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="d-block text-muted small">Average Risk Reduction</span>
                            <h3 class="mb-0 fw-bold"><?= $num($summary['average_risk_reduction_pct'] ?? 0, 1) ?>%</h3>
                            <span class="small text-muted">Before → after score change</span>
                        </div>
                        <div class="p-2 text-heading">
                            <i class="bi bi-graph-down-arrow fs-4 text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="d-block text-muted small">Reviewer Agreement</span>
                            <h3 class="mb-0 fw-bold"><?= $num($summary['reviewer_agreement_rate'] ?? 0, 1) ?>%</h3>
                            <span class="small text-muted"><?= $e($summary['reviewer_pair_count'] ?? 0) ?> review pairs</span>
                        </div>
                        <div class="p-2 text-heading">
                            <i class="bi bi-patch-check fs-4 text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <span class="d-block text-muted small">Self-Reported Discomfort</span>
                            <h3 class="mb-0 fw-bold"><?= $num($summary['average_discomfort'] ?? 0, 1) ?><span class="fs-6 text-muted">/5</span></h3>
                            <?php if ($discomfortTrend !== null): ?>
                                <span class="small text-<?= $discomfortTrend['class'] ?>"><i class="bi bi-<?= $discomfortTrend['icon'] ?> me-1"></i><?= $discomfortTrend['label'] ?> trend</span>
                            <?php else: ?>
                                <span class="small text-muted">Not enough data yet</span>
                            <?php endif; ?>
                        </div>
                        <div class="p-2 text-heading">
                            <i class="bi bi-activity fs-4 text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Full metric detail, grouped for evidence documentation -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Implementation Metrics — Full Detail</h5>
                    <p class="text-muted small mb-0">Every implementation figure, ready to cite in pilot documentation.</p>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted">Worksites enrolled</td>
                                <td class="text-end fw-semibold"><?= $e($summary['worksites_enrolled'] ?? 0) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Workers participating</td>
                                <td class="text-end fw-semibold"><?= $e($summary['workers_participating'] ?? 0) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Task videos uploaded</td>
                                <td class="text-end fw-semibold"><?= $e($summary['task_videos_uploaded'] ?? 0) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Assessments completed</td>
                                <td class="text-end fw-semibold"><?= $e($summary['assessments'] ?? 0) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Corrective actions assigned</td>
                                <td class="text-end fw-semibold"><?= $e($summary['corrective_actions_assigned'] ?? 0) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Corrective actions completed</td>
                                <td class="text-end fw-semibold"><?= $e($summary['corrective_actions_completed'] ?? 0) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Corrective actions overdue</td>
                                <td class="text-end fw-semibold"><?= $e($summary['corrective_actions_overdue'] ?? 0) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Average time to corrective action closure</td>
                                <td class="text-end fw-semibold"><?= $num($summary['average_closure_days'] ?? 0, 1) ?> days</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Outcome Metrics — Full Detail</h5>
                    <p class="text-muted small mb-0">Every outcome figure, ready to cite in pilot documentation.</p>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted">High-risk tasks identified</td>
                                <td class="text-end fw-semibold"><?= $e($summary['high_risk_tasks_identified'] ?? 0) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Before/after score change (avg. risk reduction)</td>
                                <td class="text-end fw-semibold"><?= $num($summary['average_risk_reduction_pct'] ?? 0, 1) ?>%</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Worker feedback submitted</td>
                                <td class="text-end fw-semibold"><?= $e($summary['worker_feedback_total'] ?? 0) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Supervisor feedback submitted</td>
                                <td class="text-end fw-semibold"><?= $e($summary['supervisor_feedback_total'] ?? 0) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Supervisor average severity</td>
                                <td class="text-end fw-semibold"><?= $num($summary['supervisor_average_severity'] ?? 0, 1) ?>/5</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Self-reported discomfort (avg.)</td>
                                <td class="text-end fw-semibold"><?= $num($summary['average_discomfort'] ?? 0, 1) ?>/5</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Self-reported 30-day pain (avg.)</td>
                                <td class="text-end fw-semibold"><?= $num($summary['average_pain_30'] ?? 0, 1) ?>/5</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Reviewer agreement rate</td>
                                <td class="text-end fw-semibold"><?= $num($summary['reviewer_agreement_rate'] ?? 0, 1) ?>%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <!-- Reviewer agreement & data quality -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Reviewer Agreement &amp; Data Quality</h5>
                    <p class="text-muted small mb-0">How consistently independent reviewers score the same assessments — supports the credibility of the risk and outcome figures above.</p>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-lg-8">
                            <?php
                            $agreementRows = [
                                ['label' => 'Overall Agreement', 'value' => (float) ($validationAgreement['overallAgreementRate'] ?? 0)],
                                ['label' => 'Risk Level Agreement', 'value' => (float) ($validationAgreement['riskLevelAgreementRate'] ?? 0)],
                                ['label' => 'Score Agreement', 'value' => (float) ($validationAgreement['scoreAgreementRate'] ?? 0)],
                                ['label' => 'Body Region Agreement', 'value' => (float) ($validationAgreement['bodyRegionAgreementRate'] ?? 0)],
                            ];
                            ?>
                            <?php foreach ($agreementRows as $row): ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="small text-muted"><?= $e($row['label']) ?></span>
                                        <span class="fw-semibold"><?= $num($row['value'], 1) ?>%</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar" style="width: <?= max(0, min(100, $row['value'])) ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="col-lg-4">
                            <div class="rounded-3 p-3 h-100 d-flex flex-column justify-content-center" style="background: #F8FAFC; border: 1px solid var(--we-border);">
                                <div class="small text-muted">Review Pairs Compared</div>
                                <div class="fw-bold fs-3"><?= $e($validationAgreement['pairCount'] ?? 0) ?></div>
                                <div class="small text-muted mt-1">Independent second reviews used to compute agreement rates.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- Self-reported discomfort trends -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Discomfort by Body Region</h5>
                    <p class="text-muted small mb-0">Most frequent worker-reported discomfort regions.</p>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Body Region</th>
                                <th>Responses</th>
                                <th class="text-end">Average Discomfort</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topBodyRegions as $item): ?>
                                <tr>
                                    <td class="fw-semibold"><?= $e($item['bodyRegion'] ?? 'Unknown') ?></td>
                                    <td><?= $e($item['responses'] ?? 0) ?></td>
                                    <td class="text-end"><?= $e($item['averageDiscomfort'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($topBodyRegions === []): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">No body region trend data available.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Discomfort by Task</h5>
                    <p class="text-muted small mb-0">Tasks attracting the highest volume of discomfort reporting.</p>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Responses</th>
                                <th class="text-end">Average Discomfort</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topTasks as $item): ?>
                                <tr>
                                    <td class="fw-semibold"><?= $e($item['taskName'] ?? 'Unlinked') ?></td>
                                    <td><?= $e($item['responses'] ?? 0) ?></td>
                                    <td class="text-end"><?= $e($item['averageDiscomfort'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($topTasks === []): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">No task trend data available.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Worker Feedback Trend</h5>
                    <p class="text-muted small mb-0">Date-based view of self-reported discomfort — the primary evidence trail for pilot outcomes.</p>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Responses</th>
                                <th class="text-end">Average Discomfort</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($timeline as $item): ?>
                                <tr>
                                    <td><?= $e($item['date'] ?? '') ?></td>
                                    <td><?= $e($item['responses'] ?? 0) ?></td>
                                    <td class="text-end"><?= $e($item['averageDiscomfort'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($timeline === []): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">No worker feedback timeline available.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-xl-6">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Supervisor Feedback Trend</h5>
                    <p class="text-muted small mb-0">Supervisor-observed severity and body-region trend signals.</p>
                </div>
                <div class="card-body pb-0">
                    <div class="table-responsive mb-4">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Body Region</th>
                                    <th>Observations</th>
                                    <th class="text-end">Average Severity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($supervisorTopBodyRegions as $item): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= $e($item['bodyRegion'] ?? 'Unspecified') ?></td>
                                        <td><?= $e($item['responses'] ?? 0) ?></td>
                                        <td class="text-end"><?= $e($item['averageSeverity'] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($supervisorTopBodyRegions === []): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">No supervisor body-region trends available.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Observations</th>
                                    <th class="text-end">Average Severity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($supervisorTimeline as $item): ?>
                                    <tr>
                                        <td><?= $e($item['date'] ?? '') ?></td>
                                        <td><?= $e($item['responses'] ?? 0) ?></td>
                                        <td class="text-end"><?= $e($item['averageSeverity'] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($supervisorTimeline === []): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">No supervisor timeline available.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- Filter Modal -->
    <div class="modal fade" id="filterModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-funnel me-2" style="color:var(--we-primary)"></i>Filter Pilot Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="get" action="/reporting/pilot-summary">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-medium" for="pilotWorksite">Worksite</label>
                                <select id="pilotWorksite" name="worksiteUuid" class="form-select" data-selected="<?= $e($filters['worksiteUuid'] ?? '') ?>">
                                    <option value="">All worksites</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium" for="pilotDepartment">Department</label>
                                <select id="pilotDepartment" name="departmentUuid" class="form-select" data-selected="<?= $e($filters['departmentUuid'] ?? '') ?>">
                                    <option value="">All departments</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium" for="pilotJobRole">Job Role</label>
                                <select id="pilotJobRole" name="jobRoleUuid" class="form-select" data-selected="<?= $e($filters['jobRoleUuid'] ?? '') ?>">
                                    <option value="">All job roles</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium" for="pilotIndustry">Industry</label>
                                <input id="pilotIndustry" type="text" name="industry" class="form-control" value="<?= $e($filters['industry'] ?? '') ?>" placeholder="e.g. Manufacturing">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium" for="pilotBodyRegion">Body Region</label>
                                <select id="pilotBodyRegion" name="bodyRegion" class="form-select" data-selected="<?= $e($filters['bodyRegion'] ?? '') ?>">
                                    <option value="">All body regions</option>
                                    <?php foreach ($bodyRegionFallback as $region): ?>
                                        <option value="<?= $e($region) ?>"><?= $e($region) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium" for="pilotRiskLevel">Risk Level</label>
                                <select id="pilotRiskLevel" name="riskLevel" class="form-select">
                                    <option value="">All risk levels</option>
                                    <option value="Low Risk" <?= ($filters['riskLevel'] ?? '') === 'Low Risk' ? 'selected' : '' ?>>Low Risk</option>
                                    <option value="Medium Risk" <?= ($filters['riskLevel'] ?? '') === 'Medium Risk' ? 'selected' : '' ?>>Medium Risk</option>
                                    <option value="High Risk" <?= ($filters['riskLevel'] ?? '') === 'High Risk' ? 'selected' : '' ?>>High Risk</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium" for="pilotFromDate">From Date</label>
                                <input id="pilotFromDate" type="date" name="fromDate" class="form-control" value="<?= $e($filters['fromDate'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium" for="pilotToDate">To Date</label>
                                <input id="pilotToDate" type="date" name="toDate" class="form-control" value="<?= $e($filters['toDate'] ?? '') ?>">
                            </div>
                        </div>
                        <p class="text-muted small mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Data stays scoped to your current organization. Applying filters reloads the dashboard and updates the PDF/CSV export links above.</p>
                    </div>
                    <div class="modal-footer">
                        <a href="/reporting/pilot-summary" class="btn btn-outline-secondary">Reset</a>
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>