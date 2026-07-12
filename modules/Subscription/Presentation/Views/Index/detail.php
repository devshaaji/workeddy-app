<?php declare(strict_types=1); ?>
<?php $pageTitle = 'Subscription Detail'; ?>
<section>
    <h1>Subscription Detail</h1>
    <p><?= htmlspecialchars((string) ($subscription['plan_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <dl>
        <dt>Organization</dt>
        <dd><?= htmlspecialchars((string) ($subscription['organization_uuid'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
        <dt>Status</dt>
        <dd><?= htmlspecialchars((string) ($subscription['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
        <dt>Billing Cycle</dt>
        <dd><?= htmlspecialchars((string) ($subscription['billing_cycle'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
        <dt>Auto Renew</dt>
        <dd><?= !empty($subscription['auto_renew']) ? 'Yes' : 'No' ?></dd>
        <dt>Expiry Date</dt>
        <dd><?= htmlspecialchars((string) ($subscription['expiry_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
    </dl>

    <?php if (!empty($plan)): ?>
        <h2>Plan Features</h2>
        <ul>
            <?php foreach (($plan['features'] ?? []) as $key => $value): ?>
                <li><?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars(is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
