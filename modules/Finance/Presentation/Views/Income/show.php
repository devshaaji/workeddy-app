<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 5);
$record = is_array($record ?? null) ? $record : [];
$pageTitle = 'Income Record';
$pagePurpose = (string) ($record['reference_number'] ?? 'Detail');
$pageActions = [
    ['label' => 'Back to income', 'url' => '/finance/income', 'class' => 'btn btn-white'],
    ['label' => 'Edit', 'url' => '/finance/income/' . rawurlencode((string) ($record['uuid'] ?? '')) . '/edit', 'class' => 'btn btn-primary'],
];
$pageScripts = ['js/finance.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<?php if ($record === []): ?>
    <div class="alert alert-danger">Income record not found.</div>
<?php else: ?>
<div class="card" data-finance-page="income-detail" data-uuid="<?= htmlspecialchars((string) $record['uuid'], ENT_QUOTES, 'UTF-8') ?>">
    <div class="card-header py-2">
        <h3 class="card-title d-flex align-items-center gap-2 mb-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-receipt text-secondary" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M5 21h14a2 2 0 0 0 2 -2v-14a2 2 0 0 0 -2 -2h-14a2 2 0 0 0 -2 2v14a2 2 0 0 0 2 2z" />
                <path d="M9 7l6 0" />
                <path d="M9 11l6 0" />
                <path d="M13 15l2 0" />
            </svg>
            Income Details
        </h3>
    </div>
    <div class="card-body py-1">
        <div class="divide-y">
            <div class="d-flex align-items-center py-2">
                <div class="text-secondary">Reference</div>
                <div class="ms-auto text-end text-reset fw-semibold"><?= htmlspecialchars((string) $record['reference_number'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="d-flex align-items-center py-2">
                <div class="text-secondary">Source</div>
                <div class="ms-auto text-end text-reset fw-medium"><?= htmlspecialchars((string) $record['source_type'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="d-flex align-items-center py-2">
                <div class="text-secondary">Category</div>
                <div class="ms-auto text-end text-reset fw-medium"><?= htmlspecialchars((string) $record['category'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="d-flex align-items-center py-2">
                <div class="text-secondary">Amount</div>
                <div class="ms-auto text-end text-reset fw-medium"><?= htmlspecialchars((string) $record['currency'] . ' ' . number_format((float) $record['amount'], 2), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="d-flex align-items-start py-2">
                <div class="text-secondary me-3">Description</div>
                <div class="ms-auto text-end text-reset fw-medium col-8"><?= nl2br(htmlspecialchars((string) $record['description'], ENT_QUOTES, 'UTF-8')) ?></div>
            </div>
            <div class="d-flex align-items-center py-2">
                <div class="text-secondary">Created</div>
                <div class="ms-auto text-end text-secondary"><?= htmlspecialchars((string) $record['created_at'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end py-2">
        <button class="btn btn-outline-danger" type="button" data-finance-archive="income" data-uuid="<?= htmlspecialchars((string) $record['uuid'], ENT_QUOTES, 'UTF-8') ?>">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-archive" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M3 4m0 2a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z" />
                <path d="M5 8v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-10" />
                <path d="M10 12l4 0" />
            </svg>
            Archive record
        </button>
    </div>
</div>
<?php endif; ?>
