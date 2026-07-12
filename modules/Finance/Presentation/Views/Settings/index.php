<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 5);
$pageTitle = 'Finance Settings';
$pagePurpose = 'Module configuration';
$pageActions = [['label' => 'Dashboard', 'url' => '/finance/dashboard', 'class' => 'btn btn-white']];
$pageScripts = ['js/finance.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="card" data-finance-page="settings">
    <form id="finance-settings-form" data-endpoint="/api/v1/finance/settings">
        <div class="card-body">
            <div id="finance-settings-alert" class="d-none"></div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Default expense currency</label>
                    <input name="default_expense_currency" maxlength="10" class="form-control" value="<?= htmlspecialchars((string) ($defaults['default_expense_currency'] ?? 'USD'), ENT_QUOTES, 'UTF-8') ?>" required>
                    <div class="form-text">Used as the default currency for new manual finance records.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payroll summaries</label>
                    <label class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="payroll_summary_enabled" value="1" <?= !empty($defaults['payroll_summary_enabled']) ? 'checked' : '' ?>>
                        <span class="form-check-label">Enable payroll summary reporting</span>
                    </label>
                    <div class="form-text">Controls whether Finance surfaces payroll summary reporting.</div>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end"><button class="btn btn-primary" type="submit">Save settings</button></div>
    </form>
</div>
