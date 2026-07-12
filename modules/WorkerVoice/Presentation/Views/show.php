<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$feedback = is_array($feedback ?? null) ? $feedback : [];
$feedbackLabels = is_array($feedbackLabels ?? null) ? $feedbackLabels : [];
$display = static fn(mixed $value): string => htmlspecialchars((string) (($value ?? '') !== '' ? $value : '--'), ENT_QUOTES, 'UTF-8');
$textOrFallback = static fn(mixed $value, string $fallback): string => htmlspecialchars((string) (($value ?? '') !== '' ? $value : $fallback), ENT_QUOTES, 'UTF-8');
$formatDate = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '--';
    }

    $timestamp = strtotime($value);
    return $timestamp !== false ? date('F j, Y', $timestamp) : $value;
};
$pageTitle = 'Worker Feedback Detail';
$pagePurpose = 'Worker voice and discomfort reporting';
$pageActions = [
    ['label' => 'Register', 'url' => '/worker-voice', 'class' => 'btn btn-outline-secondary', 'icon' => 'list-ul'],
    ['label' => 'Trends', 'url' => '/worker-voice/trends', 'class' => 'btn btn-outline-secondary', 'icon' => 'bar-chart'],
];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Worker Voice', 'url' => '/worker-voice'],
    ['label' => 'Detail', 'url' => null],
];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="container-xxl flex-grow-1 py-4" id="workerVoiceShowPage">
    <div id="workerVoiceShowAlert"></div>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                        <div>
                            <div class="text-muted text-uppercase small fw-semibold">Body region</div>
                            <h3 class="mb-1 fw-bold"><?= $display($feedback['bodyRegion'] ?? null) ?></h3>
                            <div class="text-muted small">Created <?= htmlspecialchars($formatDate(isset($feedback['createdAt']) ? (string) $feedback['createdAt'] : null), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <?php if (!empty($feedback['anonymousStatus'])): ?>
                            <span class="badge bg-label-info">Anonymous protected</span>
                        <?php else: ?>
                            <span class="badge bg-label-secondary">Identified record</span>
                        <?php endif; ?>
                    </div>

                    <div class="border rounded-3 p-3 bg-lighter mb-3">
                        <div class="small text-muted mb-1">Suggested change</div>
                        <p class="mb-0"><?= $textOrFallback($feedback['suggestedChange'] ?? null, 'No suggested change captured.') ?></p>
                    </div>

                    <div class="vstack gap-3">
                        <div>
                            <div class="small text-muted">Task link</div>
                            <div class="fw-semibold text-break"><?= $display($feedback['taskUuid'] ?? null) ?></div>
                        </div>
                        <div>
                            <div class="small text-muted">Assessment link</div>
                            <div class="fw-semibold text-break"><?= $display($feedback['assessmentUuid'] ?? null) ?></div>
                        </div>
                        <div>
                            <div class="small text-muted">Submitted by</div>
                            <div class="fw-semibold"><?= !empty($feedback['submittedByUserId']) ? $display($feedback['submittedByUserId']) : 'Redacted' ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Discomfort profile</h5>
                    <p class="text-muted small mb-0">Primary severity indicators and reporting confidence captured from the worker submission.</p>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6 col-xl-4">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="small text-muted mb-2">Has discomfort</div>
                                <h4 class="mb-0"><?= !empty($feedback['hasDiscomfort']) ? 'Yes' : 'No' ?></h4>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-4">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="small text-muted mb-2">Discomfort level</div>
                                <h4 class="mb-0"><?= (int) ($feedback['discomfortLevel'] ?? 0) ?>/5</h4>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-4">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="small text-muted mb-2">Frequency level</div>
                                <h4 class="mb-0"><?= (int) ($feedback['frequencyLevel'] ?? 0) ?>/5</h4>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-4">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="small text-muted mb-2">Difficulty level</div>
                                <h4 class="mb-0"><?= (int) ($feedback['difficultyLevel'] ?? 0) ?>/5</h4>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-4">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="small text-muted mb-2">Reporting comfort</div>
                                <h4 class="mb-0"><?= (int) ($feedback['reportingComfortLevel'] ?? 0) ?>/5</h4>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-4">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="small text-muted mb-2">Pain in last 7 days</div>
                                <h4 class="mb-0"><?= (int) ($feedback['pain7DayLevel'] ?? 0) ?>/5</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Context and traceability</h5>
                    <p class="text-muted small mb-0">Operational linkage for this record and the stored worker-voice metadata footprint.</p>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="small text-muted mb-1">Worksite</div>
                                <div class="fw-semibold text-break"><?= $display($feedbackLabels['worksite'] ?? $feedback['worksiteUuid'] ?? null) ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="small text-muted mb-1">Department</div>
                                <div class="fw-semibold text-break"><?= $display($feedbackLabels['department'] ?? $feedback['departmentUuid'] ?? null) ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="small text-muted mb-1">Job role</div>
                                <div class="fw-semibold text-break"><?= $display($feedbackLabels['jobRole'] ?? $feedback['jobRoleUuid'] ?? null) ?></div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="small text-muted mb-2">Metadata snapshot</div>
                        <div class="border rounded-3 p-3 bg-lighter">
                            <pre class="mb-0 small text-wrap"><?= htmlspecialchars((string) (!empty($feedback['metadata']) ? json_encode($feedback['metadata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 'No metadata stored.'), ENT_QUOTES, 'UTF-8') ?></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
