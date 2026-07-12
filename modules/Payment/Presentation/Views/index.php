<?php
/**
 * @var array $payments
 * @var string $pageTitle
 * @var array $moduleSettings
 * @var array $user
 */
?>
<div class="container-xl">
    <div class="page-header d-print-none">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="page-title">
                    <?= htmlspecialchars($pageTitle ?? 'Payments') ?>
                </h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    <a href="#" class="btn btn-primary d-none d-sm-inline-block">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>
                        Record Payment
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="page-body">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Invoice</th>
                            <th>Organization</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="w-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="8" class="text-center">No payments found.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= htmlspecialchars($payment['transaction_id']) ?></td>
                                <td><?= htmlspecialchars((string) ($payment['invoice_number'] ?? ('#' . $payment['invoice_id']))) ?></td>
                                <td><?= htmlspecialchars((string) ($payment['organization_name'] ?? ('#' . $payment['organization_id']))) ?></td>
                                <td><?= htmlspecialchars($payment['currency']) ?> <?= htmlspecialchars(number_format($payment['amount'], 2)) ?></td>
                                <td><?= htmlspecialchars(ucfirst($payment['method'])) ?></td>
                                <td><?= htmlspecialchars($payment['payment_date'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge bg-<?= $payment['status'] === 'completed' ? 'green' : ($payment['status'] === 'failed' ? 'red' : 'yellow') ?>">
                                        <?= htmlspecialchars(ucfirst($payment['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="#" class="btn btn-sm btn-outline-secondary">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
