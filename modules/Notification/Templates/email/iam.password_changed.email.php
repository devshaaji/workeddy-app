<?php

declare(strict_types=1);

$subject = 'Password Changed';
ob_start();
?>
<h2 style="margin-top: 0; color: #566a7f; font-size: 22px; font-weight: 600; text-align: center; line-height: 1.3;">Password Changed</h2>

<div style="text-align: center; margin: 24px 0;">
    <!-- Beautiful Lock SVG -->
    <svg width="80" height="80" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: inline-block;">
        <rect x="6" y="12" width="20" height="14" rx="3" fill="#f0f1ff" stroke="#696cff" stroke-width="2" />
        <path d="M10 12V8C10 4.68629 12.6863 2 16 2C19.3137 2 22 4.68629 22 8V12" stroke="#696cff" stroke-width="2" stroke-linecap="round" />
        <circle cx="16" cy="19" r="2" fill="#696cff" />
        <path d="M16 21V23" stroke="#696cff" stroke-width="2" stroke-linecap="round" />
    </svg>
</div>

<p style="text-align: center; font-size: 16px; font-weight: 600; color: #566a7f; margin-bottom: 16px;">Hello <?= htmlspecialchars((string) ($name ?? '')) ?>,</p>

<p style="text-align: center; color: #697a8d; margin-bottom: 24px; font-size: 15px; line-height: 1.6;">
    Your account password was recently changed.
</p>

<div style="background-color: #fff2e6; border-left: 4px solid #ffab00; padding: 16px; border-radius: 4px; margin-bottom: 24px;">
    <p style="margin: 0; color: #b27300; font-size: 14px; line-height: 1.5; font-weight: 500;">
        If you did not make this change, please contact support immediately to secure your account.
    </p>
</div>

<p style="text-align: center; color: #a1b0cb; font-size: 13px; margin-top: 0; margin-bottom: 0;">
    Thank you for helping us keep your account secure.
</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout/shell.php';
?>
