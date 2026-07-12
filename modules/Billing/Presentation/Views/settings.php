<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$settings = $settings ?? [];
$pageTitle = 'Settings';
$pagePurpose = 'Billing';
$pageScripts = ['js/billing.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="card" data-billing-settings>
    <form id="billing-settings-form" autocomplete="off">
        <div class="card-body">
            <div id="billing-settings-feedback" class="d-none mb-3" data-form-feedback></div>
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label">Default currency</label><input name="default_currency" maxlength="3" class="form-control text-uppercase" value="<?= htmlspecialchars((string) ($settings['default_currency'] ?? 'USD'), ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-md-3"><label class="form-label">Default tax rate (%)</label><input name="default_tax_rate" type="number" min="0" max="100" class="form-control" value="<?= htmlspecialchars((string) ($settings['default_tax_rate'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-md-3"><label class="form-label">Quotation expiry days</label><input name="quotation_expiry_days" type="number" min="1" max="365" class="form-control" value="<?= htmlspecialchars((string) ($settings['quotation_expiry_days'] ?? 30), ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-md-3"><label class="form-label">Invoice due days</label><input name="invoice_due_days" type="number" min="1" max="365" class="form-control" value="<?= htmlspecialchars((string) ($settings['invoice_due_days'] ?? 14), ENT_QUOTES, 'UTF-8') ?>"></div>
            </div>
            <hr class="my-4">
            <h4 class="mb-3">Organization Information</h4>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Organization name</label><input name="org_name" maxlength="255" class="form-control" value="<?= htmlspecialchars((string) ($settings['org_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-md-6"><label class="form-label">Tax ID</label><input name="org_tax_id" maxlength="64" class="form-control" value="<?= htmlspecialchars((string) ($settings['org_tax_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-md-6"><label class="form-label">Phone</label><input name="org_phone" maxlength="64" class="form-control" value="<?= htmlspecialchars((string) ($settings['org_phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-md-6"><label class="form-label">Email</label><input name="org_email" maxlength="255" class="form-control" value="<?= htmlspecialchars((string) ($settings['org_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-md-12"><label class="form-label">Address</label><textarea name="org_address" rows="3" class="form-control"><?= htmlspecialchars((string) ($settings['org_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea></div>
            </div>
        </div>
        <div class="card-footer d-flex">
            <a href="/billing/quotations" class="btn btn-outline-secondary">Back</a>
            <button type="submit" class="btn btn-primary ms-auto" id="billing-settings-submit">Save settings</button>
        </div>
    </form>
</div>

