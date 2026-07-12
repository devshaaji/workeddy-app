<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Operations Reporting';
$pagePurpose = 'System reporting';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Reports', 'url' => null],
    ['label' => 'Operations', 'url' => null],
];
$pageActions = [
    ['label' => 'Download PDF', 'url' => '/api/v1/reporting/operations/pdf', 'class' => 'btn btn-primary', 'icon' => 'file-earmark-pdf'],
    ['label' => 'Export CSV', 'url' => '/api/v1/reporting/operations/csv', 'class' => 'btn btn-outline-secondary', 'icon' => 'file-earmark-spreadsheet'],
];
require $v2Root . '/shared/Views/Partials/page_header.php';

$openTickets = (int) ($ticket_summary['open_tickets'] ?? 0);
$completedInstallations = (int) ($installation_summary['completed_installations'] ?? 0);
$lowStockItems = (int) ($inventory_summary['low_stock_items'] ?? 0);
$activeEmployees = (int) ($staff_summary['active_employees'] ?? 0);
$totalCustomers = (int) ($customer_summary['total_customers'] ?? 0);
$activeCustomers = (int) ($customer_summary['active_customers'] ?? 0);
$customerRate = $totalCustomers > 0 ? ($activeCustomers / $totalCustomers) * 100 : 0;
?>

<div class="container-xxl flex-grow-1 pb-4">
    <div class="card mb-4" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-4">
                <div>
                    <span class="badge bg-label-warning mb-2">Operations overview</span>
                    <h5 class="mb-2">Review support load, delivery throughput, stock pressure, and workforce readiness.</h5>
                    <p class="text-muted mb-0">This view is meant for system operators tracking platform execution health rather than organization-level ergonomic reporting.</p>
                </div>
                <div class="rounded-3 p-3 flex-shrink-0" style="background: #F8FAFC; border: 1px solid var(--we-border); min-width: 240px;">
                    <div class="small text-muted mb-1">Customer activity rate</div>
                    <div class="fw-bold fs-3"><?= number_format($customerRate, 1) ?>%</div>
                    <div class="text-muted small mt-1"><?= $activeCustomers ?> active out of <?= $totalCustomers ?> registered customers.</div>
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
                                <h4 class="mb-0"><?= $openTickets ?></h4>
                                <p class="mb-0">Open tickets</p>
                            </div>
                            <div class="avatar me-sm-6"><span class="avatar-initial rounded bg-label-warning text-heading"><i class="bi bi-life-preserver"></i></span></div>
                        </div>
                        <hr class="d-none d-sm-block d-lg-none me-6">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0"><?= $completedInstallations ?></h4>
                                <p class="mb-0">Completed installs</p>
                            </div>
                            <div class="avatar me-lg-6"><span class="avatar-initial rounded bg-label-success text-heading"><i class="bi bi-check2-square"></i></span></div>
                        </div>
                        <hr class="d-none d-sm-block d-lg-none">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0"><?= $lowStockItems ?></h4>
                                <p class="mb-0">Low stock items</p>
                            </div>
                            <div class="avatar me-sm-6"><span class="avatar-initial rounded bg-label-danger text-heading"><i class="bi bi-box-seam"></i></span></div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0"><?= $activeEmployees ?></h4>
                                <p class="mb-0">Active employees</p>
                            </div>
                            <div class="avatar"><span class="avatar-initial rounded bg-label-info text-heading"><i class="bi bi-people"></i></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Operations Status Board</h5>
                    <p class="text-muted small mb-0">Key operational indicators presented as a compact review surface.</p>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Area</th>
                                <th>Current Value</th>
                                <th>Interpretation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="fw-semibold">Support Queue</td>
                                <td><?= $openTickets ?> tickets</td>
                                <td class="text-muted small">Active support load requiring follow-up.</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Installations</td>
                                <td><?= $completedInstallations ?> completed</td>
                                <td class="text-muted small">All-time completed implementation volume.</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Inventory Pressure</td>
                                <td><?= $lowStockItems ?> low stock</td>
                                <td class="text-muted small">Items that may need reordering or replenishment.</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Workforce Capacity</td>
                                <td><?= $activeEmployees ?> active staff</td>
                                <td class="text-muted small">Current employed operational capacity.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-xl-5">
            <section class="card h-100" style="border-radius: var(--we-radius-lg); box-shadow: var(--we-shadow-sm);">
                <div class="card-header">
                    <h5 class="card-title mb-1">Customer Portfolio Overview</h5>
                    <p class="text-muted small mb-0">High-level customer account activity mix.</p>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Registered customers</span>
                        <strong><?= $totalCustomers ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Active customers</span>
                        <strong><?= $activeCustomers ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 mb-2">
                        <span class="text-muted">Active percentage</span>
                        <strong><?= number_format($customerRate, 1) ?>%</strong>
                    </div>
                    <div class="progress mb-3" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: <?= max(0, min(100, $customerRate)) ?>%;"></div>
                    </div>
                    <div class="text-muted small">This gives a quick sense of the currently engaged customer footprint across the platform.</div>
                </div>
            </section>
        </div>
    </div>
</div>
