<?php

declare(strict_types=1);

$subject = 'Account Suspended';
ob_start();
?>
<h2 style="margin-top: 0; color: #ff3e1d; font-size: 22px; font-weight: 600; text-align: center; line-height: 1.3;">Account Suspended</h2>

<div style="text-align: center; margin: 24px 0;">
    <!-- Suspended User SVG -->
    <svg width="80" height="80" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: inline-block;">
        <circle cx="16" cy="10" r="5" fill="#fff2f0" stroke="#ff3e1d" stroke-width="2" />
        <path d="M6 24C6 19.5817 9.58172 16 14 16H18C22.4183 16 26 19.5817 26 24V26H6V24Z" fill="#fff2f0" stroke="#ff3e1d" stroke-width="2" />
        <path d="M16 19V22" stroke="#ff3e1d" stroke-width="2" stroke-linecap="round" />
        <circle cx="16" cy="24" r="1" fill="#ff3e1d" />
    </svg>
</div>

<p style="font-size: 16px; font-weight: 600; color: #566a7f; margin-bottom: 16px; text-align: center;">Hello <?= htmlspecialchars((string) ($name ?? '')) ?>,</p>

<p style="text-align: center; color: #697a8d; margin-bottom: 24px; font-size: 15px; line-height: 1.6;">
    Your WorkEddy account (<strong><?= htmlspecialchars((string) ($username ?? '')) ?></strong>) has been suspended by an administrator.
</p>

<!-- Warn Box -->
<div style="background-color: #fff2f0; border-left: 4px solid #ff3e1d; padding: 16px; border-radius: 4px; margin-bottom: 24px;">
    <p style="margin: 0; color: #ff3e1d; font-size: 14px; line-height: 1.5; font-weight: 500;">
        You will not be able to log in or access workspace services until your account is reactivated.
    </p>
</div>

<p style="text-align: center; color: #697a8d; font-size: 14px; line-height: 1.5; margin-bottom: 0;">
    If you believe this is an error, please reach out to your organization administrator or contact our support team.
</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout/shell.php';
?>
