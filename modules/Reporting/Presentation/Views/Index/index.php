<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Reporting Dashboard';
$pagePurpose = 'System reporting';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Reports', 'url' => null],
    ['label' => 'Dashboard', 'url' => null],
];
$pageActions = [
    ['label' => 'Download PDF', 'url' => '/api/v1/reporting/dashboard/pdf', 'class' => 'btn btn-primary', 'icon' => 'file-earmark-pdf'],
    ['label' => 'Export CSV', 'url' => '/api/v1/reporting/dashboard/csv', 'class' => 'btn btn-outline-secondary', 'icon' => 'file-earmark-spreadsheet'],
];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="container-xxl flex-grow-1 pb-4">
    <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-4">
                <div>
                    <span class="badge bg-label-primary mb-2">System reporting</span>
                    <h5 class="mb-2">Unified view of customer footprint, finance totals, and operational load.</h5>
                    <p class="text-muted mb-0">This dashboard is reserved for system actors managing the platform-wide reporting surface and export artifacts.</p>
                </div>
                <div class="rounded-3 p-3 flex-shrink-0" style="background: #F8FAFC; border: 1px solid var(--we-border); max-width: 340px;">
                    <div class="small text-muted mb-1">Operational note</div>
                    <div class="fw-semibold">Use this page for global oversight, not organization-level reporting.</div>
                    <div class="text-muted small mt-1">Organization-facing summaries live under the report surface for pilot, assessment, corrective action, comparison, and audit trail reporting.</div>
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
                                <h4 class="mb-0"><?= (int) ($customer_summary['active_customers'] ?? 0) ?></h4>
                                <p class="mb-0">Active customers</p>
                            </div>
                            <div class="avatar me-sm-6">
                                <span class="avatar-initial rounded bg-label-primary text-heading"><i class="bi bi-buildings"></i></span>
                            </div>
                        </div>
                        <hr class="d-none d-sm-block d-lg-none me-6">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0">$<?= number_format((float) ($finance_summary['income_total'] ?? 0), 2) ?></h4>
                                <p class="mb-0">Income total</p>
                            </div>
                            <div class="avatar me-lg-6">
                                <span class="avatar-initial rounded bg-label-success text-heading"><i class="bi bi-cash-stack"></i></span>
                            </div>
                        </div>
                        <hr class="d-none d-sm-block d-lg-none">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0">$<?= number_format((float) ($finance_summary['expense_total'] ?? 0), 2) ?></h4>
                                <p class="mb-0">Expense total</p>
                            </div>
                            <div class="avatar me-sm-6">
                                <span class="avatar-initial rounded bg-label-danger text-heading"><i class="bi bi-receipt-cutoff"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0"><?= (int) ($staff_summary['active_employees'] ?? 0) ?></h4>
                                <p class="mb-0">Active staff</p>
                            </div>
                            <div class="avatar">
                                <span class="avatar-initial rounded bg-label-info text-heading"><i class="bi bi-people"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Customer Footprint</h5>
                    <p class="text-muted small mb-0">Platform-wide customer account posture.</p>
                </div>
                <div class="card-body">
                    <?php
                    $totalCustomers = (int) ($customer_summary['total_customers'] ?? 0);
                    $activeCustomers = (int) ($customer_summary['active_customers'] ?? 0);
                    $activeRate = $totalCustomers > 0 ? ($activeCustomers / $totalCustomers) * 100 : 0;
                    ?>
                    <div class="mb-4">
                        <div class="small text-muted mb-1">Total customers</div>
                        <div class="fw-bold fs-3"><?= $totalCustomers ?></div>
                    </div>
                    <div class="mb-2 d-flex justify-content-between">
                        <span class="small text-muted">Active rate</span>
                        <span class="fw-semibold"><?= number_format($activeRate, 1) ?>%</span>
                    </div>
                    <div class="progress mb-3" style="height: 8px;">
                        <div class="progress-bar" style="width: <?= max(0, min(100, $activeRate)) ?>%;"></div>
                    </div>
                    <div class="text-muted small"><?= $activeCustomers ?> active accounts are contributing to the live footprint.</div>
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Finance Snapshot</h5>
                    <p class="text-muted small mb-0">Top-level totals before drilling into finance reporting.</p>
                </div>
                <div class="card-body">
                    <?php $net = (float) ($finance_summary['income_total'] ?? 0) - (float) ($finance_summary['expense_total'] ?? 0); ?>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Income</span>
                        <strong>$<?= number_format((float) ($finance_summary['income_total'] ?? 0), 2) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Expenses</span>
                        <strong>$<?= number_format((float) ($finance_summary['expense_total'] ?? 0), 2) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <span class="text-muted">Net position</span>
                        <strong class="<?= $net >= 0 ? 'text-success' : 'text-danger' ?>">$<?= number_format($net, 2) ?></strong>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">System Report Surface</h5>
                    <p class="text-muted small mb-0">Primary platform-level reporting destinations available from this hub.</p>
                </div>
                <div class="list-group list-group-flush">
                    <a href="/reporting/finance" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span>Finance Reporting</span><i class="bi bi-chevron-right text-muted"></i>
                    </a>
                    <a href="/reporting/operations" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span>Operations Reporting</span><i class="bi bi-chevron-right text-muted"></i>
                    </a>
                    <a href="/reporting/dashboard" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span>System Dashboard</span><i class="bi bi-chevron-right text-muted"></i>
                    </a>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12">
            <section class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Organization Report Surface</h5>
                    <p class="text-muted small mb-0">These report types belong to customer-facing ergonomic reporting even when their source records are opened from assessment, corrective action, comparison, or audit workflows.</p>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6 col-xl-3">
                            <a href="/reporting/pilot-summary" class="card border shadow-none h-100 text-decoration-none">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <span class="avatar avatar-sm"><span class="avatar-initial rounded bg-label-primary"><i class="bi bi-bar-chart"></i></span></span>
                                        <i class="bi bi-chevron-right text-muted"></i>
                                    </div>
                                    <h6 class="mb-1 text-dark">Pilot Summary</h6>
                                    <p class="text-muted small mb-0">Organization-level implementation, discomfort, and reviewer agreement summary.</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <a href="/assessments" class="card border shadow-none h-100 text-decoration-none">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <span class="avatar avatar-sm"><span class="avatar-initial rounded bg-label-info"><i class="bi bi-clipboard-pulse"></i></span></span>
                                        <i class="bi bi-chevron-right text-muted"></i>
                                    </div>
                                    <h6 class="mb-1 text-dark">Assessment Reports</h6>
                                    <p class="text-muted small mb-0">Open reviewed assessments and drill into record-level reporting outputs.</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <a href="/corrective-actions" class="card border shadow-none h-100 text-decoration-none">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <span class="avatar avatar-sm"><span class="avatar-initial rounded bg-label-success"><i class="bi bi-check2-square"></i></span></span>
                                        <i class="bi bi-chevron-right text-muted"></i>
                                    </div>
                                    <h6 class="mb-1 text-dark">Corrective Action Reports</h6>
                                    <p class="text-muted small mb-0">Track remediation records and open report-ready corrective action details.</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <a href="/assessments/comparisons" class="card border shadow-none h-100 text-decoration-none">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <span class="avatar avatar-sm"><span class="avatar-initial rounded bg-label-warning"><i class="bi bi-sliders2"></i></span></span>
                                        <i class="bi bi-chevron-right text-muted"></i>
                                    </div>
                                    <h6 class="mb-1 text-dark">Comparison Reports</h6>
                                    <p class="text-muted small mb-0">Review before-and-after change evidence and baseline versus follow-up movement.</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <a href="/audit/logs" class="card border shadow-none h-100 text-decoration-none">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <span class="avatar avatar-sm"><span class="avatar-initial rounded bg-label-secondary"><i class="bi bi-journal-text"></i></span></span>
                                        <i class="bi bi-chevron-right text-muted"></i>
                                    </div>
                                    <h6 class="mb-1 text-dark">Audit Trail Reports</h6>
                                    <p class="text-muted small mb-0">Use audit records to access review history, approvals, locks, and evidence activity.</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <section class="card" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
        <div class="card-header">
            <h5 class="card-title mb-1">System Report Catalog</h5>
            <p class="text-muted small mb-0">Central access to the current platform-wide reporting outputs.</p>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Report</th>
                        <th>Description</th>
                        <th>Formats</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="fw-semibold">Dashboard</td>
                        <td class="text-muted small">Global customer, finance, and operational overview.</td>
                        <td><span class="badge bg-label-primary">PDF</span> <span class="badge bg-label-success">CSV</span></td>
                        <td class="text-end">
                            <a href="/api/v1/reporting/dashboard/pdf" class="btn btn-sm btn-outline-primary" target="_blank">PDF</a>
                            <a href="/api/v1/reporting/dashboard/csv" class="btn btn-sm btn-outline-secondary" target="_blank">CSV</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Finance Summary</td>
                        <td class="text-muted small">Income, expenses, categories, and payroll totals.</td>
                        <td><span class="badge bg-label-primary">PDF</span> <span class="badge bg-label-success">CSV</span></td>
                        <td class="text-end">
                            <a href="/api/v1/reporting/finance/pdf" class="btn btn-sm btn-outline-primary" target="_blank">PDF</a>
                            <a href="/api/v1/reporting/finance/csv" class="btn btn-sm btn-outline-secondary" target="_blank">CSV</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Operations</td>
                        <td class="text-muted small">Tickets, installations, inventory, and staffing roll-up.</td>
                        <td><span class="badge bg-label-primary">PDF</span> <span class="badge bg-label-success">CSV</span></td>
                        <td class="text-end">
                            <a href="/api/v1/reporting/operations/pdf" class="btn btn-sm btn-outline-primary" target="_blank">PDF</a>
                            <a href="/api/v1/reporting/operations/csv" class="btn btn-sm btn-outline-secondary" target="_blank">CSV</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Pilot Summary</td>
                        <td class="text-muted small">Organization-facing report available from the customer report surface.</td>
                        <td><span class="badge bg-label-primary">PDF</span> <span class="badge bg-label-success">CSV</span></td>
                        <td class="text-end">
                            <a href="/reporting/pilot-summary" class="btn btn-sm btn-outline-primary">Open</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>
