<?php

declare(strict_types=1);

$v2Root = dirname(__DIR__, 4);
$invoice = is_array($invoice ?? null) ? $invoice : null;
$pageTitle = $invoice ? (string) $invoice['invoice_number'] : 'Invoice Not Found';
$pagePurpose = 'Review';
$pageActions = $invoice ? [
    ['label' => 'Download PDF', 'url' => '/api/v1/billing/invoices/' . rawurlencode((string) $invoice['uuid']) . '/pdf', 'class' => 'btn btn-outline-secondary'],
] : [];
$pageScripts = ['js/billing.js'];
require $v2Root . '/shared/Views/Partials/page_header.php';
?>

<?php if (!$invoice): ?>
    <div class="empty"><p class="empty-title">Invoice not found</p><p class="empty-subtitle text-secondary">The invoice may have been archived or the link is invalid.</p></div>
<?php else: ?>
<div class="row row-cards" data-billing-detail="invoice" data-uuid="<?= htmlspecialchars((string) $invoice['uuid'], ENT_QUOTES, 'UTF-8') ?>">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Items</h3></div>
            <div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Unit</th><th class="text-end">Amount</th></tr></thead><tbody>
            <?php foreach (($invoice['items'] ?? []) as $item): $qty = (float) ($item['quantity'] ?? 1); $unit = (float) ($item['unit_price'] ?? 0); ?>
                <tr><td><?= htmlspecialchars((string) ($item['description'] ?? $item['name'] ?? 'Line item'), ENT_QUOTES, 'UTF-8') ?></td><td class="text-end"><?= htmlspecialchars((string) $qty, ENT_QUOTES, 'UTF-8') ?></td><td class="text-end"><?= htmlspecialchars(number_format($unit, 2), ENT_QUOTES, 'UTF-8') ?></td><td class="text-end"><?= htmlspecialchars(number_format($qty * $unit, 2), ENT_QUOTES, 'UTF-8') ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <dl class="row mb-0"><dt class="col-5">Status</dt><dd class="col-7"><span class="badge bg-blue-lt"><?= htmlspecialchars((string) $invoice['status'], ENT_QUOTES, 'UTF-8') ?></span></dd><dt class="col-5">Organization</dt><dd class="col-7"><?= htmlspecialchars((string) ($invoice['organization_name'] ?? ('#' . $invoice['organization_id'])), ENT_QUOTES, 'UTF-8') ?></dd><dt class="col-5">Due</dt><dd class="col-7"><?= htmlspecialchars((string) ($invoice['due_date'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></dd><dt class="col-5">Total</dt><dd class="col-7"><?= htmlspecialchars((string) $invoice['currency'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars(number_format((float) $invoice['total'], 2), ENT_QUOTES, 'UTF-8') ?></dd><dt class="col-5">Paid</dt><dd class="col-7"><?= htmlspecialchars(number_format((float) $invoice['amount_paid'], 2), ENT_QUOTES, 'UTF-8') ?></dd><dt class="col-5">Balance</dt><dd class="col-7 fw-semibold"><?= htmlspecialchars(number_format((float) $invoice['balance'], 2), ENT_QUOTES, 'UTF-8') ?></dd></dl>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-outline-secondary" data-billing-detail-status="paid">Mark paid</button>
                <button class="btn btn-outline-secondary" data-billing-detail-status="cancelled">Cancel</button>
                <button class="btn btn-outline-danger ms-auto" data-billing-detail-archive>Archive</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
