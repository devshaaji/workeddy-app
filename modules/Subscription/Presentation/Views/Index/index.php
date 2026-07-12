<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Subscriptions';
$pagePurpose = 'Overview';

$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Subscriptions', 'url' => '/subscriptions'],
];

$pageActions = [];
// Only show settings button if user has permission to manage plans
if (!empty($defaults['allow_self_service_upgrade']) || true) {
    $pageActions[] = [
        'label' => 'Subscription Settings',
        'url' => '/subscriptions/settings',
        'class' => 'btn btn-outline-primary',
        'icon' => 'gear',
    ];
}

$pageScripts = ['js/modules/subscription.js'];

require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div id="subscriptionIndexPage">
    <!-- Top Summary Metrics Row for Administrators -->
    <div class="row g-6 mb-6">
        <div class="col-sm-6 col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-heading text-secondary">Active Subscriptions</span>
                            <div class="d-flex align-items-center my-1">
                                <h4 class="mb-0 me-2" id="stats-active-count">
                                    <?= count(array_filter($subscriptions ?? [], fn($s) => ($s['status'] ?? '') === 'active')) ?>
                                </h4>
                            </div>
                            <small class="mb-0 text-muted">Running SaaS contracts</small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-success">
                                <i class="bi bi-check-circle fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-heading text-secondary">Suspended Subscriptions</span>
                            <div class="d-flex align-items-center my-1">
                                <h4 class="mb-0 me-2" id="stats-suspended-count">
                                    <?= count(array_filter($subscriptions ?? [], fn($s) => ($s['status'] ?? '') === 'suspended')) ?>
                                </h4>
                            </div>
                            <small class="mb-0 text-muted">Overdue or manually suspended</small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-warning">
                                <i class="bi bi-exclamation-triangle fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-heading text-secondary">Monthly Estimated Revenue</span>
                            <div class="d-flex align-items-center my-1">
                                <h4 class="mb-0 me-2" id="stats-mrr-total">
                                    <?php
                                    $mrr = 0.0;
                                    foreach ($subscriptions ?? [] as $sub) {
                                        if (($sub['status'] ?? '') === 'active') {
                                            $mrr += (float) ($sub['price'] ?? 0.0);
                                        }
                                    }
                                    echo htmlspecialchars(number_format($mrr, 2) . ' ' . ($defaults['default_currency'] ?? 'USD'), ENT_QUOTES, 'UTF-8');
                                    ?>
                                </h4>
                            </div>
                            <small class="mb-0 text-muted">Estimated recurring stream</small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="bi bi-wallet2 fs-4"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Subscriptions List Table -->
    <div class="card mt-4" id="subscriptionsCard">
        <div class="card-header d-flex justify-content-between align-items-center border-bottom">
            <h5 class="card-title mb-0">Platform Subscriptions</h5>
            <div class="d-flex gap-2">
                <select id="filter-plan" class="form-select form-select-sm" style="width: 150px;">
                    <option value="">All Tiers</option>
                    <?php foreach ($plans ?? [] as $plan): ?>
                        <option value="<?= htmlspecialchars((string)$plan['code'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string)$plan['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select id="filter-status" class="form-select form-select-sm" style="width: 150px;">
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                    <option value="expired">Expired</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
        </div>
        <div class="card-datatable table-responsive">
            <table class="table card-table" id="subscriptionsTable">
                <thead>
                    <tr>
                        <th>Organization</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Billing Cycle</th>
                        <th>Auto-Renew</th>
                        <th>Expiry Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="subscriptionsBody">
                    <!-- Populated dynamically via subscription.js -->
                </tbody>
            </table>
        </div>
    </div>
</div>
