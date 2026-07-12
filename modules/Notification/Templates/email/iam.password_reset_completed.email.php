<?php

declare(strict_types=1);

$subject = 'Password Reset Successful';
ob_start();
?>
<h2 style="margin-top: 0; color: #71dd37; font-size: 22px; font-weight: 600; text-align: center; line-height: 1.3;">Password Reset Successful</h2>

<div style="text-align: center; margin: 24px 0;">
    <!-- Shield Checkmark SVG -->
    <svg width="80" height="80" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: inline-block;">
        <path d="M16 3L26 7V15C26 21.0526 21.9054 26.5413 16 29C10.0946 26.5413 6 21.0526 6 15V7L16 3Z" fill="#e8fadf" stroke="#71dd37" stroke-width="2" stroke-linejoin="round" />
        <path d="M11 15.5L14 18.5L21 11.5" stroke="#71dd37" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</div>

<p style="font-size: 16px; font-weight: 600; color: #566a7f; margin-bottom: 16px; text-align: center;">Hello,</p>

<p style="text-align: center; color: #697a8d; margin-bottom: 24px; font-size: 15px; line-height: 1.6;">
    This email is to confirm that your WorkEddy account password has been successfully reset.
</p>

<div style="background-color: #fff2e6; border-left: 4px solid #ffab00; padding: 16px; border-radius: 4px; margin-bottom: 24px;">
    <p style="margin: 0; color: #b27300; font-size: 14px; line-height: 1.5; font-weight: 500;">
        If you did not perform this action, please contact our support team immediately to secure your account.
    </p>
</div>

<p style="text-align: center; color: #a1b0cb; font-size: 13px; margin-top: 0; margin-bottom: 0;">
    Thank you for helping us keep your account secure.
</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout/shell.php';
?>
