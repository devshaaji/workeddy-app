<?php

declare(strict_types=1);

$subject = 'Your One-Time Password';
ob_start();
?>
<h2 style="margin-top: 0; color: #566a7f; font-size: 22px; font-weight: 600; text-align: center; line-height: 1.3;">Your One-Time Password</h2>

<div style="text-align: center; margin: 24px 0;">
    <!-- Security Badge -->
    <svg width="80" height="80" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: inline-block;">
        <circle cx="16" cy="16" r="14" fill="#f0f1ff" stroke="#696cff" stroke-width="2" />
        <path d="M16 8C12.134 8 9 11.134 9 15C9 17.5858 10.4 19.8458 12.5 21.0526V25L14 26.5L15.5 25L16.5 26L18 24.5V21.0526C20.1 19.8458 21.5 17.5858 21.5 15C21.5 11.134 18.366 8 16 8ZM16 17C14.8954 17 14 16.1046 14 15C14 13.8954 14.8954 13 16 13C17.1046 13 18 13.8954 18 15C18 16.1046 17.1046 17 16 17Z" stroke="#696cff" stroke-width="2" stroke-linejoin="round" />
    </svg>
</div>

<p style="font-size: 16px; font-weight: 600; color: #566a7f; margin-bottom: 16px; text-align: center;">Hello,</p>
<p style="text-align: center; color: #697a8d; margin-bottom: 24px; font-size: 15px; line-height: 1.6;">
    Your one-time password (OTP) for accessing your account is:
</p>

<!-- OTP Display Box -->
<div style="background-color: #f5f5f9; border-radius: 6px; padding: 20px; text-align: center; font-size: 28px; font-weight: 700; letter-spacing: 6px; margin: 24px auto; width: 220px; border: 1px solid #e4e6eb; color: #696cff;">
    <?= htmlspecialchars((string) $otp) ?>
</div>

<p style="text-align: center; color: #697a8d; font-size: 14px; margin-bottom: 24px;">
    This code is valid for the next <strong><?= (int) $expiresInMinutes ?> minutes</strong>.
</p>

<p style="text-align: center; color: #a1b0cb; font-size: 12px; margin-top: 30px; border-top: 1px solid #f4f5f7; padding-top: 20px;">
    If you did not request this code, please ignore this email or contact support if you suspect unauthorized activity.
</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout/shell.php';
?>
