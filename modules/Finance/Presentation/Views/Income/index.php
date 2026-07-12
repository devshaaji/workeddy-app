<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Finance Income';
$pagePurpose = 'Revenue records';
$pageActions = [
    ['label' => 'Dashboard', 'url' => '/finance/dashboard', 'class' => 'btn btn-white'],
    ['label' => 'Record income', 'url' => '/finance/income/new', 'class' => 'btn btn-primary', 'default' => true],
];
$pageScripts = ['js/finance.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';

$summary = is_array($summary ?? null) ? $summary : [];
$iconSearch = '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"/><path d="M21 21l-6 -6"/></svg>';
?>

<div class="row row-cards mb-3">
    <div class="col-sm-6 col-lg-4"><div class="card card-sm"><div class="card-body"><div class="subheader">Income total</div><div class="h1 mb-0"><?= htmlspecialchars(number_format((float) ($summary['income_total'] ?? 0), 2), ENT_QUOTES, 'UTF-8') ?></div></div></div></div>
    <div class="col-sm-6 col-lg-4"><div class="card card-sm"><div class="card-body"><div class="subheader">Records</div><div class="h1 mb-0"><?= count($income ?? []) ?></div></div></div></div>
    <div class="col-sm-6 col-lg-4"><div class="card card-sm"><div class="card-body"><div class="subheader">Default currency</div><div class="h1 mb-0"><?= htmlspecialchars((string) ($defaults['default_expense_currency'] ?? 'USD'), ENT_QUOTES, 'UTF-8') ?></div></div></div></div>
</div>

<div class="card" id="finance-income-card" data-finance-page="income" data-endpoint="/api/v1/finance/income-records">
    <div class="card-table">
        <div class="card-header">
            <div class="row w-full g-2 align-items-center">
                <div class="col"><h3 class="card-title mb-0">Income records</h3><p class="text-secondary m-0">Recorded revenue and adjustment entries.</p></div>
                <div class="col-md-auto"><div class="d-flex flex-wrap gap-2 align-items-center">
                    <div class="input-group input-group-flat w-auto"><span class="input-group-text"><?= $iconSearch ?></span><input id="finance-income-search" type="search" class="form-control" placeholder="Search income" autocomplete="off"></div>
                    <select id="finance-income-category" class="form-select w-auto"><option value="">All categories</option></select>
                    <div id="finance-income-bulk-bar" class="d-none d-flex gap-2 align-items-center"><span id="finance-income-selected-count" class="text-secondary small"></span><div class="dropdown"><button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" type="button">Bulk actions</button><div class="dropdown-menu dropdown-menu-end"><button class="dropdown-item text-danger" type="button" id="finance-income-bulk-archive">Archive selected</button></div></div></div>
                </div></div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter table-selectable">
                <thead><tr><th class="w-1"><input class="form-check-input m-0 align-middle table-select-all" type="checkbox" aria-label="Select all income records"></th><th><button class="table-sort d-flex justify-content-between" data-sort="reference_number">Reference</button></th><th><button class="table-sort d-flex justify-content-between" data-sort="source_type">Source</button></th><th><button class="table-sort d-flex justify-content-between" data-sort="category">Category</button></th><th><button class="table-sort d-flex justify-content-between" data-sort="amount">Amount</button></th><th><button class="table-sort d-flex justify-content-between" data-sort="created_at">Created</button></th><th></th></tr></thead>
                <tbody id="finance-income-body"><tr><td colspan="7" class="text-center text-secondary py-5"><span class="spinner-border spinner-border-sm me-2"></span>Loading income records...</td></tr></tbody>
            </table>
        </div>
        <div class="card-footer d-flex align-items-center"><p class="m-0 text-secondary" id="finance-income-result-count">Income records load from the Finance API.</p><ul class="pagination m-0 ms-auto" id="finance-income-pagination"></ul></div>
    </div>
</div>
