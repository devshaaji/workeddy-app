<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Finance Payroll';
$pagePurpose = 'Payroll summary reporting';
$pageActions = [
    ['label' => 'Dashboard', 'url' => '/finance/dashboard', 'class' => 'btn btn-white'],
    ['label' => 'Settings', 'url' => '/finance/settings', 'class' => 'btn btn-outline-secondary'],
];
$pageScripts = ['js/finance.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';

$summary = is_array($summary ?? null) ? $summary : [];
$iconSearch = '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"/><path d="M21 21l-6 -6"/></svg>';
?>

<div class="row row-cards mb-3">
    <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Gross payroll</div><div class="h1 mb-0"><?= htmlspecialchars(number_format((float) ($summary['payroll_gross_total'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?></div></div></div></div>
    <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Net payroll</div><div class="h1 mb-0"><?= htmlspecialchars(number_format((float) ($summary['payroll_net_total'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?></div></div></div></div>
    <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Periods</div><div class="h1 mb-0"><?= count($payroll_summaries ?? []) ?></div></div></div></div>
    <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="subheader">Reporting</div><div class="h1 mb-0"><?= !empty($defaults['payroll_summary_enabled']) ? 'On' : 'Off' ?></div></div></div></div>
</div>

<div class="card mb-3" data-finance-page="payroll-refresh">
    <div class="card-body">
        <form id="finance-payroll-refresh-form" class="row g-2 align-items-end">
            <div id="finance-payroll-refresh-alert" class="d-none col-12"></div>
            <div class="col-md-4"><label class="form-label">Period key</label><input name="period_key" class="form-control" placeholder="2026-07" required></div>
            <div class="col-md-auto"><button class="btn btn-primary" type="submit">Refresh payroll summary</button></div>
            <div class="col text-secondary">Refresh uses HRM payroll data for the selected period when available.</div>
        </form>
    </div>
</div>

<div class="card" id="finance-payroll-card" data-finance-page="payroll" data-endpoint="/api/v1/finance/payroll-summaries">
    <div class="card-table">
        <div class="card-header"><div class="row w-full g-2 align-items-center"><div class="col"><h3 class="card-title mb-0">Payroll summaries</h3></div><div class="col-md-auto"><div class="input-group input-group-flat w-auto"><span class="input-group-text"><?= $iconSearch ?></span><input id="finance-payroll-search" type="search" class="form-control" placeholder="Search periods" autocomplete="off"></div></div></div></div>
        <div class="table-responsive"><table class="table table-vcenter"><thead><tr><th><button class="table-sort d-flex justify-content-between" data-sort="period_key">Period</button></th><th><button class="table-sort d-flex justify-content-between" data-sort="employee_count">Employees</button></th><th><button class="table-sort d-flex justify-content-between" data-sort="gross_amount">Gross</button></th><th><button class="table-sort d-flex justify-content-between" data-sort="net_amount">Net</button></th><th><button class="table-sort d-flex justify-content-between" data-sort="updated_at">Updated</button></th></tr></thead><tbody id="finance-payroll-body"><tr><td colspan="5" class="text-center text-secondary py-5"><span class="spinner-border spinner-border-sm me-2"></span>Loading payroll summaries...</td></tr></tbody></table></div>
        <div class="card-footer d-flex align-items-center"><p class="m-0 text-secondary" id="finance-payroll-result-count">Payroll summaries load from the Finance API.</p><ul class="pagination m-0 ms-auto" id="finance-payroll-pagination"></ul></div>
    </div>
</div>
