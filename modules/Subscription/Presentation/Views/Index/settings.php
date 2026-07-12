<?php declare(strict_types=1); ?>
<?php $pageTitle = 'Subscription Settings'; ?>
<section>
    <h1>Subscription Settings</h1>
    <p>Default billing cycle: <?= htmlspecialchars((string) ($defaults['default_billing_cycle'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <p>Default currency: <?= htmlspecialchars((string) ($defaults['default_currency'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <p>Trial days: <?= (int) ($defaults['trial_days'] ?? 0) ?></p>
    <p>Grace period days: <?= (int) ($defaults['grace_period_days'] ?? 0) ?></p>
    <p>Auto-suspend on expiry: <?= !empty($defaults['auto_suspend_on_expiry']) ? 'Enabled' : 'Disabled' ?></p>
    <p>Self-service upgrade: <?= !empty($defaults['allow_self_service_upgrade']) ? 'Enabled' : 'Disabled' ?></p>

    <h2>Plan Tiers</h2>
    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Price</th>
                <th>Billing Cycle</th>
                <th>Active</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (($plans ?? []) as $plan): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($plan['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($plan['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(number_format((float) ($plan['price'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string) ($plan['currency'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($plan['billing_cycle'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= !empty($plan['is_active']) ? 'Yes' : 'No' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
