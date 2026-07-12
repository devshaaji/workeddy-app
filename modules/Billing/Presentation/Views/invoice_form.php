<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$settings = $moduleSettings ?? [];
$organizations = is_array($organizations ?? null) ? $organizations : [];
$quotations = is_array($quotations ?? null) ? $quotations : [];
$pageTitle = 'Invoice';
$pagePurpose = 'Create';
$pageScripts = ['js/billing.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="card" data-billing-form="invoice">
    <form id="billing-invoice-form" autocomplete="off">
        <div class="card-body">
            <div id="billing-invoice-form-feedback" class="d-none mb-3" data-form-feedback></div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Organization <span class="text-danger">*</span></label>
                    <select name="organization_id" class="form-select" required>
                        <option value="">Select organization</option>
                        <?php foreach ($organizations as $organization): ?>
                            <option value="<?= htmlspecialchars((string) ($organization['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($organization['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Accepted quotation</label>
                    <select name="quotation_uuid" class="form-select">
                        <option value="">None</option>
                        <?php foreach ($quotations as $quotation): ?>
                            <option value="<?= htmlspecialchars((string) ($quotation['uuid'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) (($quotation['quotation_number'] ?? 'Quotation') . ' - ' . ($quotation['organization_name'] ?? ('#' . ($quotation['organization_id'] ?? '')))), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2"><label class="form-label">Currency</label><input name="currency" maxlength="3" class="form-control text-uppercase" value="<?= htmlspecialchars((string) ($settings['defaultCurrency'] ?? 'USD'), ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-md-2"><label class="form-label">Due days</label><input name="days_until_due" type="number" min="1" class="form-control" value="<?= htmlspecialchars((string) ($settings['invoiceDueDays'] ?? 14), ENT_QUOTES, 'UTF-8') ?>"></div>
            </div>
            <hr>
            <div class="d-flex align-items-center mb-2">
                <h3 class="card-title mb-0">Line items</h3>
                <button type="button" class="btn btn-outline-secondary btn-sm ms-auto" data-billing-add-line>
                    <!-- svg plus icon  -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 4l0 16" />
                        <path d="M4 12l16 0" />
                    </svg>
                    Add item
                </button>
            </div>
            <div data-billing-line-items></div>
        </div>
        <div class="card-footer d-flex">
            <a href="/billing/invoices" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary ms-auto" id="billing-invoice-submit">Create invoice</button>
        </div>
    </form>
</div>
