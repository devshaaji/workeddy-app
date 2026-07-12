<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Subscription Settings';
$pagePurpose = 'Administration';

$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Subscriptions', 'url' => '/subscriptions'],
    ['label' => 'Settings', 'url' => '#'],
];

$pageActions = [];
$pageScripts = ['js/modules/subscription.js'];

require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div id="subscriptionSettingsPage">
    <div class="row">
        <!-- Sidebar Navigation Tabs -->
        <div class="col-md-4 col-lg-3 mb-4">
            <div class="nav-align-left">
                <ul class="nav nav-pills flex-column" role="tablist">
                    <li class="nav-item">
                        <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#tab-general" aria-controls="tab-general" aria-selected="true">
                            <i class="bi bi-gear me-2"></i> General Defaults
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-plans" aria-controls="tab-plans" aria-selected="false">
                            <i class="bi bi-tags me-2"></i> Plan Tiers
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="col-md-8 col-lg-9">
            <div class="tab-content p-0 border-0 bg-transparent">
                <!-- General Defaults Tab -->
                <div class="tab-pane fade show active" id="tab-general" role="tabpanel">
                    <div class="card">
                        <div class="card-header border-bottom">
                            <h5 class="card-title mb-0">Subscription Configuration</h5>
                            <small class="text-muted">Global default variables governing SaaS billing, trial limits, and suspensions.</small>
                        </div>
                        <div class="card-body pt-4">
                            <form id="form-subscription-settings" class="subscription-settings-form" action="/api/subscriptions/settings" method="POST">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label for="default_billing_cycle" class="form-label">Default Billing Cycle</label>
                                        <select id="default_billing_cycle" name="default_billing_cycle" class="form-select">
                                            <option value="monthly" <?= ($defaults['default_billing_cycle'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                            <option value="yearly" <?= ($defaults['default_billing_cycle'] ?? '') === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="default_currency" class="form-label">Default Currency</label>
                                        <select id="default_currency" name="default_currency" class="form-select">
                                            <option value="USD" <?= ($defaults['default_currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD ($)</option>
                                            <option value="EUR" <?= ($defaults['default_currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR (€)</option>
                                            <option value="GBP" <?= ($defaults['default_currency'] ?? '') === 'GBP' ? 'selected' : '' ?>>GBP (£)</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="trial_days" class="form-label">Trial Period (Days)</label>
                                        <input type="number" id="trial_days" name="trial_days" class="form-control" value="<?= (int) ($defaults['trial_days'] ?? 0) ?>" min="0" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="grace_period_days" class="form-label">Grace Period (Days)</label>
                                        <input type="number" id="grace_period_days" name="grace_period_days" class="form-control" value="<?= (int) ($defaults['grace_period_days'] ?? 0) ?>" min="0" required>
                                    </div>

                                    <div class="col-12 mt-4">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="auto_suspend_on_expiry" name="auto_suspend_on_expiry" value="1" <?= !empty($defaults['auto_suspend_on_expiry']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="auto_suspend_on_expiry">Auto-Suspend on Expiry</label>
                                            <div class="form-text">Automatically set subscription state to Suspended once trial or billing period ends.</div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="allow_self_service_upgrade" name="allow_self_service_upgrade" value="1" <?= !empty($defaults['allow_self_service_upgrade']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="allow_self_service_upgrade">Allow Tenant Self-Service Upgrade</label>
                                            <div class="form-text">If enabled, tenant administrators can change plans self-service via the client detail screen.</div>
                                        </div>
                                    </div>

                                    <div class="col-12 mt-4">
                                        <button type="submit" class="btn btn-primary" id="btn-save-settings">Save Settings</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Plan Tiers Tab -->
                <div class="tab-pane fade" id="tab-plans" role="tabpanel">
                    <div class="card">
                        <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-0">Subscription Plan Tiers</h5>
                                <small class="text-muted">Currently active tiers in the subscription catalog.</small>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Price</th>
                                        <th>Billing Cycle</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (($plans ?? []) as $plan): ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars((string) ($plan['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                                            <td><strong><?= htmlspecialchars((string) ($plan['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></td>
                                            <td><?= htmlspecialchars(number_format((float) ($plan['price'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string) ($plan['currency'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-capitalize"><?= htmlspecialchars((string) ($plan['billing_cycle'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <span class="badge bg-label-<?= !empty($plan['is_active']) ? 'success' : 'secondary' ?>">
                                                    <?= !empty($plan['is_active']) ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
