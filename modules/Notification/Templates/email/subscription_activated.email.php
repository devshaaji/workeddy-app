<?php

declare(strict_types=1);

$subject = 'Subscription Active';
ob_start();
?>
<h2 style="margin-top: 0; color: #71dd37; font-size: 22px; font-weight: 600; text-align: center; line-height: 1.3;">Subscription Activated</h2>

<div style="text-align: center; margin: 24px 0;">
    <!-- Active Ribbon SVG -->
    <svg width="80" height="80" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: inline-block;">
        <circle cx="16" cy="12" r="8" fill="#e8fadf" stroke="#71dd37" stroke-width="2" />
        <path d="M12 18L9 28L16 25L23 28L20 18" stroke="#71dd37" stroke-width="2" stroke-linejoin="round" />
        <path d="M13 12L15 14L19 10" stroke="#71dd37" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</div>

<p style="font-size: 16px; color: #566a7f; margin-bottom: 16px;">Hello,</p>
<p style="color: #697a8d; margin-bottom: 24px; font-size: 15px; line-height: 1.6;">
    Good news! Your WorkEddy SaaS subscription has been activated successfully. Here are the plan details:
</p>

<!-- Subscription Box -->
<div style="background-color: #f8f9fa; border: 1px solid #e4e6eb; border-radius: 6px; padding: 20px; margin-bottom: 24px;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; width: 120px; font-size: 14px;">Plan:</td>
            <td style="padding: 6px 0; color: #696cff; font-size: 14px; font-weight: 700;"><?= htmlspecialchars((string) ($plan_name ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <?php if (!empty($expiry_date)): ?>
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; font-size: 14px;">Expiry Date:</td>
            <td style="padding: 6px 0; color: #697a8d; font-size: 14px;"><?= htmlspecialchars((string) $expiry_date, ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<!-- CTA Button -->
<div style="text-align: center; margin-bottom: 20px;">
    <a href="<?= htmlspecialchars(rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/') . '/dashboard') ?>" target="_blank" style="display: inline-block; background-color: #696cff; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; padding: 12px 28px; border-radius: 6px; box-shadow: 0 4px 10px rgba(105, 108, 255, 0.25);">
        ACCESS YOUR WORKSPACE
    </a>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout/shell.php';
?>
