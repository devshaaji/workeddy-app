<?php

declare(strict_types=1);

$subject = 'Invoice Generated';
ob_start();
?>
<h2 style="margin-top: 0; color: #566a7f; font-size: 22px; font-weight: 600; text-align: center; line-height: 1.3;">Invoice Generated</h2>

<div style="text-align: center; margin: 24px 0;">
    <!-- Invoice SVG -->
    <svg width="80" height="80" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: inline-block;">
        <rect x="6" y="4" width="20" height="24" rx="3" fill="#f0f1ff" stroke="#696cff" stroke-width="2" />
        <path d="M10 10H22M10 14H22M10 18H18" stroke="#696cff" stroke-width="2" stroke-linecap="round" />
        <circle cx="23" cy="23" r="5" fill="#71dd37" stroke="#ffffff" stroke-width="1.5" />
        <path d="M21 23L22.5 24.5L25.5 21.5" stroke="#ffffff" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</div>

<p style="font-size: 16px; color: #566a7f; margin-bottom: 16px;">Hello,</p>
<p style="color: #697a8d; margin-bottom: 24px; font-size: 15px; line-height: 1.6;">
    Your WorkEddy invoice has been successfully generated. Please find the summary of your invoice below:
</p>

<!-- Invoice Details Box -->
<div style="background-color: #f8f9fa; border: 1px solid #e4e6eb; border-radius: 6px; padding: 20px; margin-bottom: 24px;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; width: 140px; font-size: 14px;">Invoice Number:</td>
            <td style="padding: 6px 0; color: #697a8d; font-size: 14px; font-weight: 600;"><?= htmlspecialchars((string) ($invoice_number ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; font-size: 14px;">Amount:</td>
            <td style="padding: 6px 0; color: #697a8d; font-size: 14px; font-weight: 600;"><?= htmlspecialchars((string) ($currency ?? 'USD'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string) ($amount ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <?php if (!empty($due_date)): ?>
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; font-size: 14px;">Due Date:</td>
            <td style="padding: 6px 0; color: #ffab00; font-size: 14px; font-weight: 600;"><?= htmlspecialchars((string) $due_date, ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<!-- CTA Button -->
<div style="text-align: center; margin-bottom: 20px;">
    <a href="<?= htmlspecialchars(rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/') . '/billing/invoices') ?>" target="_blank" style="display: inline-block; background-color: #696cff; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; padding: 12px 28px; border-radius: 6px; box-shadow: 0 4px 10px rgba(105, 108, 255, 0.25);">
        PAY / VIEW INVOICE
    </a>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout/shell.php';
?>
