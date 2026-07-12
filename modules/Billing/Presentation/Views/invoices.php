<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Invoices';
$pagePurpose = 'Overview';

$pageActions = [
    ['label' => 'Quotations', 'url' => '/billing/quotations', 'class' => 'btn btn-outline-secondary'],
    ['label' => 'New invoice', 'url' => '/billing/invoices/new', 'class' => 'btn btn-primary', 'default' => true],
];
$pageScripts = ['js/billing.js'];
$iconSearch = '<i class="ti ti-search"></i>';
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="row row-cards mb-3" data-billing-summary="invoices">
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-blue text-white avatar">
                            <i class="ti ti-receipt fs-3"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="h2 font-weight-medium mb-0" id="billing-invoice-total">0</div>
                        <div class="text-secondary">Invoices</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-yellow text-white avatar">
                            <i class="ti ti-clock-dollar fs-3"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="h2 font-weight-medium mb-0" id="billing-invoice-unpaid">0</div>
                        <div class="text-secondary">Unpaid</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-green text-white avatar">
                            <i class="ti ti-circle-check fs-3"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="h2 font-weight-medium mb-0" id="billing-invoice-paid">0</div>
                        <div class="text-secondary">Paid</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-red text-white avatar">
                            <i class="ti ti-cash fs-3"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="h2 font-weight-medium mb-0" id="billing-invoice-balance">0</div>
                        <div class="text-secondary">Balance due</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card" id="billing-invoices-card" data-billing-screen="invoices">
    <div class="card-table">
        <div class="card-header">
            <div class="row w-full g-2 align-items-center">
                <div class="col">
                </div>
                <div class="col-md-auto">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <div class="input-group input-group-flat w-auto"><span class="input-group-text"><?= $iconSearch ?></span><input id="billing-invoice-search" type="search" class="form-control" placeholder="Search invoices"></div>
                        <select id="billing-invoice-status" class="form-select w-auto">
                            <option value="">All statuses</option>
                            <?php foreach (($invoiceStatuses ?? []) as $status): ?><option value="<?= htmlspecialchars($status['value'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($status['label'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                        </select>
                        <div id="billing-invoice-bulk-bar" class="d-none d-flex gap-2 align-items-center">
                            <span id="billing-invoice-selected-count" class="text-secondary small"></span>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" type="button">Bulk actions</button>
                                <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 14rem;">
                                    <label class="form-label small mb-1" for="billing-invoice-bulk-status">Set status</label>
                                    <select id="billing-invoice-bulk-status" class="form-select form-select-sm mb-2">
                                        <option value="">Choose status</option>
                                        <option value="paid">Paid</option>
                                        <option value="overdue">Overdue</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                    <button class="dropdown-item" type="button" id="billing-invoice-bulk-apply">Apply status</button>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item text-danger" type="button" id="billing-invoice-bulk-archive">Archive</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter table-selectable">
                <thead>
                    <tr>
                        <th class="w-1"><input class="form-check-input m-0 align-middle table-select-all" type="checkbox" aria-label="Select all invoices"></th>
                        <th><button class="table-sort" data-sort="invoice_number">Invoice</button></th>
                        <th><button class="table-sort" data-sort="organization_id">Organization</button></th>
                        <th><button class="table-sort" data-sort="status">Status</button></th>
                        <th><button class="table-sort" data-sort="total">Total</button></th>
                        <th><button class="table-sort" data-sort="balance">Balance</button></th>
                        <th><button class="table-sort" data-sort="due_date">Due</button></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="billing-invoice-body">
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-5"><span class="spinner-border spinner-border-sm me-2"></span>Loading invoices...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex align-items-center">
            <p class="m-0 text-secondary" id="billing-invoice-result-count">Invoice rows load from the Billing API.</p>
            <ul class="pagination m-0 ms-auto" id="billing-invoice-pagination"></ul>
        </div>
    </div>
</div>