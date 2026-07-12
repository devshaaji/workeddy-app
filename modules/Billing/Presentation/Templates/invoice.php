<?php
/** @var array<string, mixed> $data */
$org = $data['org'] ?? [];
$customer = $data['customer'] ?? [];
$customerAddress = $customer['address'] ?? null;
$items = is_array($data['items'] ?? null) ? $data['items'] : [];
$currency = htmlspecialchars((string) ($data['currency'] ?? 'USD'));
$invoiceNumber = htmlspecialchars((string) ($data['invoice_number'] ?? ''));
$status = htmlspecialchars((string) ($data['status'] ?? ''));
$createdAt = htmlspecialchars((string) ($data['created_at'] ?? ''));
$dueDate = htmlspecialchars((string) ($data['due_date'] ?? '-'));
$subtotal = number_format((float) ($data['subtotal'] ?? 0), 2);
$tax = number_format((float) ($data['tax'] ?? 0), 2);
$total = number_format((float) ($data['total'] ?? 0), 2);
$balance = number_format((float) ($data['balance'] ?? 0), 2);
?>
<html>
<head>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 13px; color: #333; line-height: 1.5; }
        .container { padding: 30px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; padding-bottom: 20px; }
        .org { max-width: 50%; }
        .org h2 { margin: 0 0 6px 0; font-size: 22px; color: #222; }
        .org p { margin: 2px 0; font-size: 12px; color: #555; }
        .doc-meta { text-align: right; }
        .doc-meta h1 { margin: 0 0 8px 0; font-size: 26px; color: #1a1a1a; text-transform: uppercase; letter-spacing: 1px; }
        .doc-meta p { margin: 2px 0; font-size: 12px; color: #555; }
        .parties { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .party { width: 48%; }
        .party-label { font-size: 11px; text-transform: uppercase; color: #888; letter-spacing: 0.5px; margin-bottom: 4px; }
        .party-name { font-weight: bold; font-size: 14px; color: #222; margin-bottom: 4px; }
        .party-detail { font-size: 12px; color: #555; margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #f5f5f5; text-align: left; padding: 10px 8px; font-size: 11px; text-transform: uppercase; color: #666; border-bottom: 2px solid #ddd; }
        td { padding: 10px 8px; border-bottom: 1px solid #eee; font-size: 12px; vertical-align: top; }
        .text-right { text-align: right; }
        .totals { width: 280px; margin-left: auto; }
        .totals table { margin-bottom: 0; }
        .totals td { padding: 6px 8px; border: none; }
        .totals .total-row { font-weight: bold; font-size: 14px; border-top: 2px solid #ddd; }
        .balance-row { font-weight: bold; font-size: 14px; color: #c0392b; border-top: 2px solid #ddd; }
        .footer { margin-top: 40px; font-size: 11px; color: #888; text-align: center; border-top: 1px solid #eee; padding-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="org">
                <?php if ($org['name'] ?? ''): ?>
                    <h2><?= htmlspecialchars($org['name']) ?></h2>
                <?php endif; ?>
                <?php if ($org['address'] ?? ''): ?>
                    <p><?= nl2br(htmlspecialchars($org['address'])) ?></p>
                <?php endif; ?>
                <?php if ($org['phone'] ?? ''): ?><p>Phone: <?= htmlspecialchars($org['phone']) ?></p><?php endif; ?>
                <?php if ($org['email'] ?? ''): ?><p>Email: <?= htmlspecialchars($org['email']) ?></p><?php endif; ?>
                <?php if ($org['tax_id'] ?? ''): ?><p>Tax ID: <?= htmlspecialchars($org['tax_id']) ?></p><?php endif; ?>
            </div>
            <div class="doc-meta">
                <h1>Invoice</h1>
                <p><strong># <?= $invoiceNumber ?></strong></p>
                <p>Status: <?= $status ?></p>
                <p>Date: <?= $createdAt ?></p>
                <p>Due: <?= $dueDate ?></p>
            </div>
        </div>

        <div class="parties">
            <div class="party">
                <div class="party-label">Bill To</div>
                <?php if ($customer['name'] ?? ''): ?>
                    <div class="party-name"><?= htmlspecialchars($customer['name']) ?></div>
                <?php endif; ?>
                <?php if ($customer['email'] ?? ''): ?><div class="party-detail"><?= htmlspecialchars($customer['email']) ?></div><?php endif; ?>
                <?php if ($customer['phone'] ?? ''): ?><div class="party-detail"><?= htmlspecialchars($customer['phone']) ?></div><?php endif; ?>
                <?php if ($customerAddress): ?>
                    <div class="party-detail">
                        <?= htmlspecialchars($customerAddress['street'] ?? '') ?><br>
                        <?= htmlspecialchars(($customerAddress['city'] ?? '') . ', ' . ($customerAddress['state'] ?? '') . ' ' . ($customerAddress['postal_code'] ?? '')) ?><br>
                        <?= htmlspecialchars($customerAddress['country'] ?? '') ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <?php
                    $desc = htmlspecialchars((string) ($item['description'] ?? $item['name'] ?? 'Line item'));
                    $qty = (int) ($item['quantity'] ?? 1);
                    $unit = number_format((float) ($item['unit_price'] ?? 0), 2);
                    $lineTotal = number_format($qty * (float) ($item['unit_price'] ?? 0), 2);
                    ?>
                    <tr>
                        <td><?= $desc ?></td>
                        <td class="text-right"><?= $qty ?></td>
                        <td class="text-right"><?= $currency ?> <?= $unit ?></td>
                        <td class="text-right"><?= $currency ?> <?= $lineTotal ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <table>
                <tr>
                    <td class="text-right">Subtotal:</td>
                    <td class="text-right"><?= $currency ?> <?= $subtotal ?></td>
                </tr>
                <tr>
                    <td class="text-right">Tax:</td>
                    <td class="text-right"><?= $currency ?> <?= $tax ?></td>
                </tr>
                <tr class="total-row">
                    <td class="text-right">Total:</td>
                    <td class="text-right"><?= $currency ?> <?= $total ?></td>
                </tr>
                <tr class="balance-row">
                    <td class="text-right">Balance Due:</td>
                    <td class="text-right"><?= $currency ?> <?= $balance ?></td>
                </tr>
            </table>
        </div>

        <div class="footer">
            <?php if ($org['name'] ?? ''): ?>
                Thank you for your business. If you have any questions, please contact <?= htmlspecialchars($org['name']) ?>.
            <?php else: ?>
                Thank you for your business.
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
