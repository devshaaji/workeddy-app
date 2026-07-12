<?php declare(strict_types=1); ?>
<?php $pageTitle = 'Subscriptions'; ?>
<section>
    <h1>Subscriptions</h1>
    <p>Plans: <?= count($plans ?? []) ?> | Subscriptions: <?= count($subscriptions ?? []) ?></p>
    <table>
        <thead>
            <tr>
                <th>Organization</th>
                <th>Plan</th>
                <th>Status</th>
                <th>Billing Cycle</th>
                <th>Expiry</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (($subscriptions ?? []) as $subscription): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($subscription['organization_uuid'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($subscription['plan_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($subscription['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($subscription['billing_cycle'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($subscription['expiry_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
