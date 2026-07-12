<?php

declare(strict_types=1);

$subject = 'Welcome to WorkEddy';
ob_start();
?>
<h2 style="margin-top: 0; color: #566a7f; font-size: 22px; font-weight: 600; text-align: center; line-height: 1.3;">Welcome to WorkEddy</h2>

<div style="text-align: center; margin: 24px 0;">
    <!-- Welcome Rocket SVG -->
    <svg width="80" height="80" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: inline-block;">
        <path d="M16 3C16 3 22 8 22 15C22 22 19 25 16 27C13 25 10 22 10 15C10 8 16 3 16 3Z" fill="#f0f1ff" stroke="#696cff" stroke-width="2" stroke-linejoin="round" />
        <circle cx="16" cy="12" r="2.5" fill="#696cff" />
        <path d="M10 17L7 20V23L11 21" stroke="#696cff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        <path d="M22 17L25 20V23L21 21" stroke="#696cff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        <path d="M14 27L16 30L18 27" stroke="#ff3e1d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</div>

<p style="font-size: 16px; font-weight: 600; color: #566a7f; margin-bottom: 16px; text-align: center;">Hello <?= htmlspecialchars((string) ($name ?? '')) ?>,</p>

<p style="text-align: center; color: #697a8d; margin-bottom: 24px; font-size: 15px; line-height: 1.6;">
    Your account has been successfully created! Your registered username is:
</p>

<!-- Username Card -->
<div style="background-color: #f8f9fa; border: 1px solid #e4e6eb; border-radius: 6px; padding: 16px; text-align: center; margin-bottom: 24px; max-width: 320px; margin-left: auto; margin-right: auto;">
    <span style="font-size: 13px; text-transform: uppercase; color: #a1b0cb; font-weight: 600; display: block; margin-bottom: 4px;">Username / Email</span>
    <span style="font-size: 16px; color: #566a7f; font-weight: 700;"><?= htmlspecialchars((string) ($username ?? '')) ?></span>
</div>

<p style="text-align: center; color: #697a8d; font-size: 14px; line-height: 1.5; margin-bottom: 24px;">
    You can now log in using the credentials provided during your registration.
</p>

<!-- CTA Button -->
<div style="text-align: center; margin-bottom: 20px;">
    <a href="<?= htmlspecialchars(rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/') . '/login') ?>" target="_blank" style="display: inline-block; background-color: #696cff; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; padding: 12px 28px; border-radius: 6px; box-shadow: 0 4px 10px rgba(105, 108, 255, 0.25);">
        LOGIN TO WORKSPACE
    </a>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout/shell.php';
?>
