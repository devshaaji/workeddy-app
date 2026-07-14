<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Finance Dashboard';
$pagePurpose = 'Overview';
$pageActions = [
    ['label' => 'Income', 'url' => '/finance/income', 'class' => 'btn btn-white'],
    ['label' => 'Expenses', 'url' => '/finance/expenses', 'class' => 'btn btn-white'],
    ['label' => 'Settings', 'url' => '/settings/page?module=finance', 'class' => 'btn btn-outline-secondary'],
];
$pageScripts = ['js/finance.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';

$summary = is_array($summary ?? null) ? $summary : [];
$incomeTotal = (float) ($summary['income_total'] ?? 0);
$expenseTotal = (float) ($summary['expense_total'] ?? 0);
$netTotal = $incomeTotal - $expenseTotal;
?>

<div class="row row-cards mb-3">
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm"><div class="card-body"><div class="subheader">Income</div><div class="h1 mb-0"><?= htmlspecialchars(number_format($incomeTotal, 2), ENT_QUOTES, 'UTF-8') ?></div><div class="text-secondary">Recorded revenue</div></div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm"><div class="card-body"><div class="subheader">Expenses</div><div class="h1 mb-0"><?= htmlspecialchars(number_format($expenseTotal, 2), ENT_QUOTES, 'UTF-8') ?></div><div class="text-secondary">Operating costs</div></div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm"><div class="card-body"><div class="subheader">Net position</div><div class="h1 mb-0 <?= $netTotal < 0 ? 'text-danger' : 'text-success' ?>"><?= htmlspecialchars(number_format($netTotal, 2), ENT_QUOTES, 'UTF-8') ?></div><div class="text-secondary">Income less expenses</div></div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm"><div class="card-body"><div class="subheader">Payroll periods</div><div class="h1 mb-0"><?= (int) ($summary['payroll_periods'] ?? 0) ?></div><div class="text-secondary">Summaries refreshed</div></div></div>
    </div>
</div>

<div class="row row-cards">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Recent income</h3><div class="card-actions"><a href="/finance/income" class="btn btn-sm btn-white">View all</a></div></div>
            <div class="table-responsive">
                <table class="table table-vcenter">
                    <thead><tr><th>Reference</th><th>Category</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($income ?? [], 0, 5) as $record): ?>
                        <tr><td class="fw-semibold"><?= htmlspecialchars((string) ($record['reference_number'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td class="text-secondary"><?= htmlspecialchars((string) ($record['category'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td class="text-end"><?= htmlspecialchars((string) ($record['currency'] ?? '') . ' ' . number_format((float) ($record['amount'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (($income ?? []) === []): ?><tr><td colspan="3" class="text-center text-secondary py-4">No income records yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Recent expenses</h3><div class="card-actions"><a href="/finance/expenses" class="btn btn-sm btn-white">View all</a></div></div>
            <div class="table-responsive">
                <table class="table table-vcenter">
                    <thead><tr><th>Reference</th><th>Category</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($expenses ?? [], 0, 5) as $record): ?>
                        <tr><td class="fw-semibold"><?= htmlspecialchars((string) ($record['reference_number'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td class="text-secondary"><?= htmlspecialchars((string) ($record['category'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td class="text-end"><?= htmlspecialchars((string) ($record['currency'] ?? '') . ' ' . number_format((float) ($record['amount'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (($expenses ?? []) === []): ?><tr><td colspan="3" class="text-center text-secondary py-4">No expense records yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
