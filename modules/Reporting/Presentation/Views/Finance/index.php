<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Finance Reporting';
$pagePurpose = 'System reporting';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Reports', 'url' => null],
    ['label' => 'Finance', 'url' => null],
];
$pageActions = [
    ['label' => 'Download PDF', 'url' => '/api/v1/reporting/finance/pdf', 'class' => 'btn btn-primary', 'icon' => 'file-earmark-pdf'],
    ['label' => 'Export CSV', 'url' => '/api/v1/reporting/finance/csv', 'class' => 'btn btn-outline-secondary', 'icon' => 'file-earmark-spreadsheet'],
];
require $v2Root . '/shared/Views/Partials/page_header.php';

$incomeTotal = (float) ($finance_summary['income_total'] ?? 0);
$expenseTotal = (float) ($finance_summary['expense_total'] ?? 0);
$payrollGross = (float) ($finance_summary['payroll_gross_total'] ?? 0);
$net = $incomeTotal - $expenseTotal;
?>

<div class="container-xxl flex-grow-1 pb-4">
    <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-4">
                <div>
                    <span class="badge bg-label-success mb-2">Finance overview</span>
                    <h5 class="mb-2">Monitor platform income, expense mix, and payroll periods from one reporting surface.</h5>
                    <p class="text-muted mb-0">This page is optimized for system actors reviewing broad financial movement rather than customer-facing billing activity.</p>
                </div>
                <div class="rounded-3 p-3 flex-shrink-0" style="background: #F8FAFC; border: 1px solid var(--we-border); min-width: 240px;">
                    <div class="small text-muted mb-1">Net position</div>
                    <div class="fw-bold fs-3 <?= $net >= 0 ? 'text-success' : 'text-danger' ?>">$<?= number_format($net, 2) ?></div>
                    <div class="text-muted small mt-1">Calculated from total income minus total expenses.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-widget-separator-wrapper">
            <div class="card-body card-widget-separator">
                <div class="row gy-4 gy-sm-1">
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0">$<?= number_format($incomeTotal, 2) ?></h4>
                                <p class="mb-0">Income total</p>
                            </div>
                            <div class="avatar me-sm-6"><span class="avatar-initial rounded bg-label-success text-heading"><i class="bi bi-graph-up-arrow"></i></span></div>
                        </div>
                        <hr class="d-none d-sm-block d-lg-none me-6">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0">$<?= number_format($expenseTotal, 2) ?></h4>
                                <p class="mb-0">Expense total</p>
                            </div>
                            <div class="avatar me-lg-6"><span class="avatar-initial rounded bg-label-danger text-heading"><i class="bi bi-arrow-down-right-circle"></i></span></div>
                        </div>
                        <hr class="d-none d-sm-block d-lg-none">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0">$<?= number_format($payrollGross, 2) ?></h4>
                                <p class="mb-0">Payroll gross</p>
                            </div>
                            <div class="avatar me-sm-6"><span class="avatar-initial rounded bg-label-warning text-heading"><i class="bi bi-people-fill"></i></span></div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0"><?= count($payroll_periods ?? []) ?></h4>
                                <p class="mb-0">Payroll periods</p>
                            </div>
                            <div class="avatar"><span class="avatar-initial rounded bg-label-info text-heading"><i class="bi bi-calendar3"></i></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Income by Category</h5>
                    <p class="text-muted small mb-0">Where incoming value is concentrated across the platform.</p>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($income_by_category ?? []) as $row): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars((string) ($row['category'] ?? 'Uncategorized')) ?></td>
                                    <td class="text-end text-success">$<?= number_format((float) ($row['total'] ?? 0), 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (($income_by_category ?? []) === []): ?>
                                <tr><td colspan="2" class="text-center text-muted py-4">No income records available.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Expense by Category</h5>
                    <p class="text-muted small mb-0">Expense concentration by category across the reporting period.</p>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($expense_by_category ?? []) as $row): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars((string) ($row['category'] ?? 'Uncategorized')) ?></td>
                                    <td class="text-end text-danger">$<?= number_format((float) ($row['total'] ?? 0), 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (($expense_by_category ?? []) === []): ?>
                                <tr><td colspan="2" class="text-center text-muted py-4">No expense records available.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <section class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
        <div class="card-header">
            <h5 class="card-title mb-1">Payroll Period Summaries</h5>
            <p class="text-muted small mb-0">Gross, net, and headcount movement by payroll period.</p>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th class="text-end">Gross Amount</th>
                        <th class="text-end">Net Amount</th>
                        <th class="text-end">Employee Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($payroll_periods ?? []) as $row): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars((string) ($row['period_key'] ?? '')) ?></td>
                            <td class="text-end">$<?= number_format((float) ($row['gross_amount'] ?? 0), 2) ?></td>
                            <td class="text-end text-muted">$<?= number_format((float) ($row['net_amount'] ?? 0), 2) ?></td>
                            <td class="text-end"><?= (int) ($row['employee_count'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (($payroll_periods ?? []) === []): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No payroll summaries available.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
