<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 5);
$isEdit = ($mode ?? 'create') === 'edit';
$record = is_array($record ?? null) ? $record : [];
$pageTitle = $isEdit ? 'Edit Expense Record' : 'Record Expense';
$pagePurpose = $isEdit ? 'Update expense entry' : 'Create expense entry';
$pageActions = [['label' => 'Back to expenses', 'url' => '/finance/expenses', 'class' => 'btn btn-white']];
$pageScripts = ['js/finance.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<div class="card" data-finance-page="expense-form" data-mode="<?= $isEdit ? 'edit' : 'create' ?>" data-uuid="<?= htmlspecialchars((string) ($record['uuid'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <form id="finance-expense-form" data-endpoint="<?= $isEdit ? '/api/v1/finance/expense-records/' . rawurlencode((string) ($record['uuid'] ?? '')) : '/api/v1/finance/expense-records' ?>">
        <div class="card-body">
            <div id="finance-expense-form-alert" class="d-none"></div>
            <?php if ($isEdit && $record === []): ?><div class="alert alert-danger">Expense record not found.</div><?php endif; ?>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Reference number</label><input name="reference_number" class="form-control" required value="<?= htmlspecialchars((string) ($record['reference_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-md-6"><label class="form-label">Category</label><input name="category" class="form-control" required value="<?= htmlspecialchars((string) ($record['category'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-md-6"><label class="form-label">Amount</label><input name="amount" type="number" step="0.01" min="0" class="form-control" required value="<?= htmlspecialchars((string) ($record['amount'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-md-6"><label class="form-label">Currency</label><input name="currency" maxlength="10" class="form-control" required value="<?= htmlspecialchars((string) ($record['currency'] ?? $defaults['default_expense_currency'] ?? 'USD'), ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars((string) ($record['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea></div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2"><a href="/finance/expenses" class="btn btn-white">Cancel</a><button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save changes' : 'Record expense' ?></button></div>
    </form>
</div>
