<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Subscription Details';
$pagePurpose = 'Overview';

$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Subscriptions', 'url' => '/subscriptions'],
    ['label' => ($subscription['plan_name'] ?? 'Detail'), 'url' => '#'],
];

$pageActions = [];
if (($subscription['status'] ?? '') === 'active') {
    $pageActions[] = [
        'label' => 'Change Plan',
        'url' => '#changePlanModal',
        'class' => 'btn btn-primary',
        'icon' => 'arrow-left-right',
        'data' => [
            'bs-toggle' => 'modal',
            'bs-target' => '#changePlanModal'
        ]
    ];
}

$pageScripts = ['js/modules/subscription.js'];

require $v2Root . '/shared/Views/Partials/page_header.php';

// Helper to render progress meter safely
$renderProgress = function (string $title, string $metricKey, int $used, ?int $limit, string $unit = '') {
    $pct = 0;
    $limitLabel = 'Unlimited';
    $badgeClass = 'bg-label-success';
    $progressBarClass = 'bg-success';
    $helperText = 'Unlimited entitlement';

    if ($limit !== null && $limit > 0) {
        $pct = (int) round(($used / $limit) * 100);
        $limitLabel = (string) $limit . $unit;
        if ($pct >= 90) {
            $badgeClass = 'bg-label-danger';
            $progressBarClass = 'bg-danger';
        } elseif ($pct >= 75) {
            $badgeClass = 'bg-label-warning';
            $progressBarClass = 'bg-warning';
        }
    } else {
        $pct = 0;
        $progressBarClass = 'bg-info';
        $badgeClass = 'bg-label-info';
    }

    $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $escapedLimitLabel = htmlspecialchars($limitLabel, ENT_QUOTES, 'UTF-8');
    $escapedUsed = htmlspecialchars((string) $used, ENT_QUOTES, 'UTF-8') . $unit;
    $escapedPct = min(100, $pct);
    if ($limit !== null && $limit > 0) {
        $helperText = "{$escapedPct}% of entitlement consumed";
    }
    $escapedHelperText = htmlspecialchars($helperText, ENT_QUOTES, 'UTF-8');

    echo <<<HTML
    <div class="col-md-6 col-lg-4 mb-4 quota-progress" data-metric="{$metricKey}">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="card-title mb-0">{$escapedTitle}</h6>
                    <span class="badge {$badgeClass}">{$escapedUsed} / {$escapedLimitLabel}</span>
                </div>
                <div class="progress mb-1" style="height: 8px;">
                    <div class="progress-bar {$progressBarClass}" role="progressbar" style="width: {$escapedPct}%" aria-valuenow="{$escapedPct}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <small class="text-muted">{$escapedHelperText}</small>
            </div>
        </div>
    </div>
HTML;
};

$formatPlanLimit = static function (array $features, string $key, string $suffix = ''): string {
    $value = $features[$key] ?? null;
    if ($value === null || $value === '') {
        return 'Unlimited';
    }

    return (string) ((int) $value) . $suffix;
};
?>

<div id="subscriptionDetailPage" data-uuid="<?= htmlspecialchars((string) $subscription['uuid'], ENT_QUOTES, 'UTF-8') ?>">
    <div class="row">
        <!-- Subscription Summary Card -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="avatar avatar-xl mx-auto mb-3">
                        <span class="avatar-initial rounded-circle bg-label-primary fs-2">
                            <i class="bi bi-rocket-takeoff"></i>
                        </span>
                    </div>
                    <h4><?= htmlspecialchars((string) ($subscription['plan_name'] ?? 'Unknown Plan'), ENT_QUOTES, 'UTF-8') ?></h4>
                    <span class="badge bg-label-<?= ($subscription['status'] ?? '') === 'active' ? 'success' : 'warning' ?> mb-3">
                        <?= htmlspecialchars(strtoupper((string) ($subscription['status'] ?? 'unknown')), ENT_QUOTES, 'UTF-8') ?>
                    </span>

                    <div class="border-top pt-3 text-start">
                        <dl class="row mb-0">
                            <dt class="col-6 text-muted">Organization</dt>
                            <dd class="col-6 text-end text-truncate"><?= htmlspecialchars((string) ($subscription['organization_uuid'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>

                            <dt class="col-6 text-muted">Price</dt>
                            <dd class="col-6 text-end"><?= htmlspecialchars(number_format((float) ($subscription['price'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string) ($subscription['currency'] ?? 'USD'), ENT_QUOTES, 'UTF-8') ?></dd>

                            <dt class="col-6 text-muted">Billing Cycle</dt>
                            <dd class="col-6 text-end text-capitalize"><?= htmlspecialchars((string) ($subscription['billing_cycle'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>

                            <dt class="col-6 text-muted">Expiry / Renewal</dt>
                            <dd class="col-6 text-end"><?= htmlspecialchars((string) ($subscription['expiry_date'] ?? 'Never'), ENT_QUOTES, 'UTF-8') ?></dd>

                            <dt class="col-6 text-muted">Auto-Renew</dt>
                            <dd class="col-6 text-end">
                                <span class="badge bg-label-<?= !empty($subscription['auto_renew']) ? 'success' : 'danger' ?>">
                                    <?= !empty($subscription['auto_renew']) ? 'ON' : 'OFF' ?>
                                </span>
                            </dd>
                        </dl>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <?php if (($subscription['status'] ?? '') === 'active'): ?>
                            <button class="btn btn-label-warning" id="btn-suspend-subscription">Suspend Subscription</button>
                            <button class="btn btn-label-danger" id="btn-cancel-subscription">Cancel Subscription</button>
                        <?php elseif (($subscription['status'] ?? '') === 'suspended'): ?>
                            <button class="btn btn-success" id="btn-reactivate-subscription">Reactivate Subscription</button>
                        <?php elseif (($subscription['status'] ?? '') === 'cancelled'): ?>
                            <button class="btn btn-primary" id="btn-reactivate-subscription">Reactivate Subscription</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- SaaS Quotas & Usage Dashboard -->
        <div class="col-lg-8">
            <div class="row">
                <?php
                // Fetch usage and limits
                $maxWorksites = $plan['features']['max_worksites'] ?? null;
                $usedWorksites = $usage['usage']['max_worksites'] ?? 0;
                $renderProgress('Worksites', 'max_worksites', (int) $usedWorksites, $maxWorksites ? (int) $maxWorksites : null);

                $maxUsers = $plan['features']['max_users'] ?? null;
                $usedUsers = $usage['usage']['max_users'] ?? 0;
                $renderProgress('Team Members', 'max_users', (int) $usedUsers, $maxUsers ? (int) $maxUsers : null);

                $maxAssessments = $plan['features']['max_assessments_per_month'] ?? null;
                $usedAssessments = $usage['usage']['max_assessments_per_month'] ?? 0;
                $renderProgress('Assessments (Monthly)', 'max_assessments_per_month', (int) $usedAssessments, $maxAssessments ? (int) $maxAssessments : null);

                $maxStorageGb = $plan['features']['video_storage_gb'] ?? null;
                $usedStorageMb = $usage['usage']['video_storage_used_mb'] ?? 0;
                $renderProgress('Video Storage', 'video_storage_used_mb', (int) $usedStorageMb, $maxStorageGb ? (int) ($maxStorageGb * 1024) : null, ' MB');

                $maxAiCredits = $plan['features']['ai_scoring_credits_per_month'] ?? null;
                $usedAiCredits = $usage['usage']['ai_scoring_credits_used'] ?? 0;
                $renderProgress('AI Credits', 'ai_scoring_credits_used', (int) $usedAiCredits, $maxAiCredits ? (int) $maxAiCredits : null);
                ?>
            </div>
        </div>
    </div>
</div>

<!-- Change Plan Modal -->
<div class="modal fade" id="changePlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title">Upgrade or Change Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-5">
                <div class="text-center mb-4">
                    <h3>Select a New Tier</h3>
                    <p class="text-muted">Choose the best fit for your team. Downgrades take effect at the end of the current billing cycle.</p>
                </div>

                <div class="row g-4">
                    <?php foreach (($plans ?? []) as $availablePlan): ?>
                        <?php
                        $planCode = (string) ($availablePlan['code'] ?? '');
                        $isCurrentPlan = $planCode === (string) ($subscription['plan_code'] ?? '');
                        $isFeaturedPlan = $planCode === 'professional';
                        $cardClass = $isFeaturedPlan
                            ? 'card border border-primary rounded p-3 text-center position-relative'
                            : 'card border rounded p-3 text-center';
                        $buttonClass = $isFeaturedPlan ? 'btn btn-primary w-100 btn-select-plan' : 'btn btn-outline-primary w-100 btn-select-plan';
                        $features = is_array($availablePlan['features'] ?? null) ? $availablePlan['features'] : [];
                        ?>
                        <div class="col-md-4">
                            <div class="<?= htmlspecialchars($cardClass, ENT_QUOTES, 'UTF-8') ?>">
                                <?php if ($isFeaturedPlan): ?>
                                    <span class="badge bg-primary position-absolute top-0 start-50 translate-middle">POPULAR</span>
                                <?php endif; ?>
                                <h5 class="mb-1"><?= htmlspecialchars((string) ($availablePlan['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h5>
                                <h2 class="text-primary mb-2">$<?= htmlspecialchars(number_format((float) ($availablePlan['price'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?></h2>
                                <p class="text-muted small"><?= htmlspecialchars((string) ($availablePlan['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                <hr>
                                <ul class="list-unstyled text-start small mb-4">
                                    <li><i class="bi bi-check text-success me-2"></i> <?= htmlspecialchars($formatPlanLimit($features, 'max_users') . ' Team Members', ENT_QUOTES, 'UTF-8') ?></li>
                                    <li><i class="bi bi-check text-success me-2"></i> <?= htmlspecialchars($formatPlanLimit($features, 'max_worksites') . ' Worksites', ENT_QUOTES, 'UTF-8') ?></li>
                                    <li><i class="bi bi-check text-success me-2"></i> <?= htmlspecialchars($formatPlanLimit($features, 'max_assessments_per_month') . ' Assessments/mo', ENT_QUOTES, 'UTF-8') ?></li>
                                </ul>
                                <button class="<?= htmlspecialchars($buttonClass, ENT_QUOTES, 'UTF-8') ?>" data-plan="<?= htmlspecialchars($planCode, ENT_QUOTES, 'UTF-8') ?>" <?= $isCurrentPlan ? 'disabled' : '' ?>>
                                    <?= $isCurrentPlan ? 'Current Plan' : 'Select Plan' ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
