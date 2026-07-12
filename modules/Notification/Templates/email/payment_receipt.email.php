<?php

declare(strict_types=1);

$subject = 'Payment Receipt';
ob_start();
?>
<h2 style="margin-top: 0; color: #71dd37; font-size: 22px; font-weight: 600; text-align: center; line-height: 1.3;">Payment Received</h2>

<div style="text-align: center; margin: 24px 0;">
    <!-- Receipt SVG -->
    <svg width="80" height="80" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: inline-block;">
        <rect x="6" y="4" width="20" height="24" rx="3" fill="#e8fadf" stroke="#71dd37" stroke-width="2" />
        <circle cx="16" cy="14" r="4" fill="#71dd37" />
        <path d="M11 22H21M11 25H17" stroke="#71dd37" stroke-width="2" stroke-linecap="round" />
    </svg>
</div>

<p style="font-size: 16px; color: #566a7f; margin-bottom: 16px;">Hello,</p>
<p style="color: #697a8d; margin-bottom: 24px; font-size: 15px; line-height: 1.6;">
    Thank you for your payment. Your transaction was processed successfully. Here are the receipt details:
</p>

<!-- Receipt Details Box -->
<div style="background-color: #f8f9fa; border: 1px solid #e4e6eb; border-radius: 6px; padding: 20px; margin-bottom: 24px;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; width: 140px; font-size: 14px;">Amount Paid:</td>
            <td style="padding: 6px 0; color: #71dd37; font-size: 14px; font-weight: 600;"><?= htmlspecialchars((string) ($currency ?? 'NGN'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string) ($amount ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; font-size: 14px;">Reference:</td>
            <td style="padding: 6px 0; color: #697a8d; font-size: 14px; font-family: monospace;"><?= htmlspecialchars((string) ($reference ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
    </table>
</div>

<!-- CTA Button -->
<div style="text-align: center; margin-bottom: 20px;">
    <a href="<?= htmlspecialchars(rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/') . '/billing/receipts') ?>" target="_blank" style="display: inline-block; background-color: #696cff; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; padding: 12px 28px; border-radius: 6px; box-shadow: 0 4px 10px rgba(105, 108, 255, 0.25);">
        VIEW BILLING HISTORY
    </a>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout/shell.php';
?>
