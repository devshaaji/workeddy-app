<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$settings = $moduleSettings ?? [];
$organizations = is_array($organizations ?? null) ? $organizations : [];
$pageTitle = 'Quotation';
$pagePurpose = 'Create';
$pageScripts = ['js/billing.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="card" data-billing-form="quotation">
    <form id="billing-quotation-form" autocomplete="off">
        <div class="card-body">
            <div id="billing-quotation-form-feedback" class="d-none mb-3" data-form-feedback></div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Organization <span class="text-danger">*</span></label>
                    <select name="organization_id" class="form-select" required>
                        <option value="">Select organization</option>
                        <?php foreach ($organizations as $organization): ?>
                            <option value="<?= htmlspecialchars((string) ($organization['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($organization['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2"><label class="form-label">Currency</label><input name="currency" maxlength="3" class="form-control text-uppercase" value="<?= htmlspecialchars((string) ($settings['defaultCurrency'] ?? 'USD'), ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-md-2"><label class="form-label">Expiry days</label><input name="days_until_expiry" type="number" min="1" class="form-control" value="<?= htmlspecialchars((string) ($settings['quotationExpiryDays'] ?? 30), ENT_QUOTES, 'UTF-8') ?>"></div>
            </div>
            <hr>
            <div class="d-flex align-items-center mb-2">
                <h3 class="card-title mb-0">Line items</h3>
                <button type="button" class="btn btn-outline-secondary btn-sm ms-auto" data-billing-add-line>Add item</button>
            </div>
            <div data-billing-line-items></div>
        </div>
        <div class="card-footer d-flex">
            <a href="/billing/quotations" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary ms-auto" id="billing-quotation-submit">Create quotation</button>
        </div>
    </form>
</div>
