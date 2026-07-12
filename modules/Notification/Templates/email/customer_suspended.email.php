<?php

declare(strict_types=1);

$subject = 'Subscription Suspended';
ob_start();
?>
<h2 style="margin-top: 0; color: #ff3e1d; font-size: 22px; font-weight: 600; text-align: center; line-height: 1.3;">Subscription Suspended</h2>

<div style="text-align: center; margin: 24px 0;">
    <!-- Warning Icon -->
    <svg width="80" height="80" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: inline-block;">
        <path d="M16 3L30 27H2L16 3Z" fill="#fff2f0" stroke="#ff3e1d" stroke-width="2" stroke-linejoin="round" />
        <circle cx="16" cy="22" r="1.5" fill="#ff3e1d" />
        <path d="M16 11V18" stroke="#ff3e1d" stroke-width="2.5" stroke-linecap="round" />
    </svg>
</div>

<p style="font-size: 16px; font-weight: 600; color: #566a7f; margin-bottom: 16px;">Hello,</p>
<p style="color: #697a8d; margin-bottom: 24px; font-size: 15px; line-height: 1.6;">
    Please be informed that your WorkEddy subscription has been suspended.
</p>

<!-- Suspension Details -->
<div style="background-color: #fff2f0; border-left: 4px solid #ff3e1d; padding: 16px; border-radius: 4px; margin-bottom: 24px;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 4px 0; font-weight: 600; color: #566a7f; width: 100px; font-size: 14px;">Plan:</td>
            <td style="padding: 4px 0; color: #697a8d; font-size: 14px;"><?= htmlspecialchars((string) ($plan_name ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <td style="padding: 4px 0; font-weight: 600; color: #566a7f; font-size: 14px;">Reason:</td>
            <td style="padding: 4px 0; color: #697a8d; font-size: 14px;"><?= htmlspecialchars((string) ($reason ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
    </table>
</div>

<p style="color: #697a8d; font-size: 14px; line-height: 1.5; margin-bottom: 20px;">
    If you wish to restore your subscription or have questions regarding this suspension, please log in to your billing portal or contact support.
</p>

<!-- CTA Button -->
<div style="text-align: center; margin-bottom: 20px;">
    <a href="<?= htmlspecialchars(rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/') . '/billing') ?>" target="_blank" style="display: inline-block; background-color: #ff3e1d; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; padding: 12px 28px; border-radius: 6px; box-shadow: 0 4px 10px rgba(255, 62, 29, 0.25);">
        RESOLVE BILLING ISSUES
    </a>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout/shell.php';
?>
