<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$pageTitle = 'Quotations';
$pagePurpose = 'Overview';

$pageActions = [
    ['label' => 'Invoices', 'url' => '/billing/invoices', 'class' => 'btn btn-outline-secondary'],
    ['label' => 'New quotation', 'url' => '/billing/quotations/new', 'class' => 'btn btn-primary', 'default' => true],
];
$pageScripts = ['js/billing.js'];
$iconSearch = '<i class="ti ti-search"></i>';
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="row row-cards mb-3" data-billing-summary="quotations">
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-blue text-white avatar">
                            <i class="ti ti-file-invoice fs-3"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="h2 font-weight-medium mb-0" id="billing-quotation-total">0</div>
                        <div class="text-secondary">Quotations</div>
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
                        <span class="bg-secondary text-white avatar">
                            <i class="ti ti-pencil fs-3"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="h2 font-weight-medium mb-0" id="billing-quotation-draft">0</div>
                        <div class="text-secondary">Draft</div>
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
                        <div class="h2 font-weight-medium mb-0" id="billing-quotation-accepted">0</div>
                        <div class="text-secondary">Accepted</div>
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
                        <span class="bg-teal text-white avatar">
                            <i class="ti ti-currency-dollar fs-3"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="h2 font-weight-medium mb-0" id="billing-quotation-value">0</div>
                        <div class="text-secondary">Total value</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card" id="billing-quotations-card" data-billing-screen="quotations">
    <div class="card-table">
        <div class="card-header">
            <div class="row w-full g-2 align-items-center">
                <div class="col">
                </div>
                <div class="col-md-auto">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <div class="input-group input-group-flat w-auto"><span class="input-group-text"><?= $iconSearch ?></span><input id="billing-quotation-search" type="search" class="form-control" placeholder="Search quotations"></div>
                        <select id="billing-quotation-status" class="form-select w-auto">
                            <option value="">All statuses</option>
                            <?php foreach (($quotationStatuses ?? []) as $status): ?><option value="<?= htmlspecialchars($status['value'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($status['label'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                        </select>
                        <div id="billing-quotation-bulk-bar" class="d-none d-flex gap-2 align-items-center">
                            <span id="billing-quotation-selected-count" class="text-secondary small"></span>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" type="button">Bulk actions</button>
                                <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 14rem;">
                                    <label class="form-label small mb-1" for="billing-quotation-bulk-status">Set status</label>
                                    <select id="billing-quotation-bulk-status" class="form-select form-select-sm mb-2">
                                        <option value="">Choose status</option>
                                        <option value="sent">Sent</option>
                                        <option value="accepted">Accepted</option>
                                        <option value="rejected">Rejected</option>
                                        <option value="expired">Expired</option>
                                    </select>
                                    <button class="dropdown-item" type="button" id="billing-quotation-bulk-apply">Apply status</button>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item text-danger" type="button" id="billing-quotation-bulk-archive">Archive</button>
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
                        <th class="w-1"><input class="form-check-input m-0 align-middle table-select-all" type="checkbox" aria-label="Select all quotations"></th>
                        <th><button class="table-sort" data-sort="quotation_number">Quotation</button></th>
                        <th><button class="table-sort" data-sort="organization_id">Organization</button></th>
                        <th><button class="table-sort" data-sort="status">Status</button></th>
                        <th><button class="table-sort" data-sort="total">Total</button></th>
                        <th><button class="table-sort" data-sort="expires_at">Expires</button></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="billing-quotation-body">
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-5"><span class="spinner-border spinner-border-sm me-2"></span>Loading quotations...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex align-items-center">
            <p class="m-0 text-secondary" id="billing-quotation-result-count">Quotation rows load from the Billing API.</p>
            <ul class="pagination m-0 ms-auto" id="billing-quotation-pagination"></ul>
        </div>
    </div>
</div>