<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$quotation = is_array($quotation ?? null) ? $quotation : null;
$pageTitle = $quotation ? (string) $quotation['quotation_number'] : 'Quotation Not Found';
$pagePurpose = 'Review';
$pageActions = $quotation ? [
    ['label' => 'Download PDF', 'url' => '/api/v1/billing/quotations/' . rawurlencode((string) $quotation['uuid']) . '/pdf', 'class' => 'btn btn-outline-secondary'],
] : [];
$pageScripts = ['js/billing.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<?php if (!$quotation): ?>
    <div class="empty"><p class="empty-title">Quotation not found</p><p class="empty-subtitle text-secondary">The quotation may have been archived or the link is invalid.</p></div>
<?php else: ?>
<div class="row row-cards" data-billing-detail="quotation" data-uuid="<?= htmlspecialchars((string) $quotation['uuid'], ENT_QUOTES, 'UTF-8') ?>">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Items</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table"><thead><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Unit</th><th class="text-end">Amount</th></tr></thead><tbody>
                <?php foreach (($quotation['items'] ?? []) as $item): $qty = (float) ($item['quantity'] ?? 1); $unit = (float) ($item['unit_price'] ?? 0); ?>
                    <tr><td><?= htmlspecialchars((string) ($item['description'] ?? $item['name'] ?? 'Line item'), ENT_QUOTES, 'UTF-8') ?></td><td class="text-end"><?= htmlspecialchars((string) $qty, ENT_QUOTES, 'UTF-8') ?></td><td class="text-end"><?= htmlspecialchars(number_format($unit, 2), ENT_QUOTES, 'UTF-8') ?></td><td class="text-end"><?= htmlspecialchars(number_format($qty * $unit, 2), ENT_QUOTES, 'UTF-8') ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <dl class="row mb-0"><dt class="col-5">Status</dt><dd class="col-7"><span class="badge bg-blue-lt"><?= htmlspecialchars((string) $quotation['status'], ENT_QUOTES, 'UTF-8') ?></span></dd><dt class="col-5">Organization</dt><dd class="col-7"><?= htmlspecialchars((string) ($quotation['organization_name'] ?? ('#' . $quotation['organization_id'])), ENT_QUOTES, 'UTF-8') ?></dd><dt class="col-5">Expires</dt><dd class="col-7"><?= htmlspecialchars((string) ($quotation['expires_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></dd><dt class="col-5">Subtotal</dt><dd class="col-7"><?= htmlspecialchars(number_format((float) $quotation['subtotal'], 2), ENT_QUOTES, 'UTF-8') ?></dd><dt class="col-5">Tax</dt><dd class="col-7"><?= htmlspecialchars(number_format((float) $quotation['tax'], 2), ENT_QUOTES, 'UTF-8') ?></dd><dt class="col-5">Total</dt><dd class="col-7 fw-semibold"><?= htmlspecialchars((string) $quotation['currency'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars(number_format((float) $quotation['total'], 2), ENT_QUOTES, 'UTF-8') ?></dd></dl>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-outline-secondary" data-billing-detail-status="accepted">Accept</button>
                <button class="btn btn-outline-secondary" data-billing-detail-status="rejected">Reject</button>
                <button class="btn btn-outline-danger ms-auto" data-billing-detail-archive>Archive</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
