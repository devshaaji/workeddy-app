<?php

declare(strict_types=1);

$subject = 'Password Reset Request';
ob_start();
?>
<h2 style="margin-top: 0; color: #566a7f; font-size: 22px; font-weight: 600; text-align: center; line-height: 1.3;">Password Reset Request</h2>

<div style="text-align: center; margin: 24px 0;">
    <!-- Key SVG -->
    <svg width="80" height="80" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: inline-block;">
        <rect x="2" y="6" width="28" height="20" rx="3" fill="#f0f1ff" stroke="#696cff" stroke-width="2" />
        <path d="M9 16C9 13.7909 10.7909 12 13 12C15.2091 12 17 13.7909 17 16C17 18.2091 15.2091 20 13 20C10.7909 20 9 18.2091 9 16ZM17 16H23V18M21 16V18" stroke="#696cff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</div>

<p style="font-size: 16px; font-weight: 600; color: #566a7f; margin-bottom: 16px; text-align: center;">Hello,</p>
<p style="text-align: center; color: #697a8d; margin-bottom: 24px; font-size: 15px; line-height: 1.6;">
    We received a request to reset the password for your WorkEddy account. Click the button below to set a new password:
</p>

<!-- CTA Button -->
<div style="text-align: center; margin-bottom: 30px;">
    <a href="<?= htmlspecialchars((string) $resetUrl) ?>" target="_blank" style="display: inline-block; background-color: #696cff; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; padding: 12px 28px; border-radius: 6px; box-shadow: 0 4px 10px rgba(105, 108, 255, 0.25);">
        RESET PASSWORD
    </a>
</div>

<p style="text-align: center; color: #697a8d; font-size: 14px; margin-bottom: 24px;">
    This link will expire in <strong><?= (int) $expiresInMinutes ?> minutes</strong>.
</p>

<p style="text-align: center; color: #a1b0cb; font-size: 12px; margin-top: 30px; border-top: 1px solid #f4f5f7; padding-top: 20px;">
    If you did not request a password reset, you can safely ignore this email. Your password will remain unchanged.
</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout/shell.php';
?>
