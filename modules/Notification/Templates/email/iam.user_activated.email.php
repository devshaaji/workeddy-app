<?php

declare(strict_types=1);

$subject = 'Account Activated';
ob_start();
?>
<h2 style="margin-top: 0; color: #566a7f; font-size: 22px; font-weight: 600; text-align: center; line-height: 1.3;">Account Activated</h2>

<div style="text-align: center; margin: 24px 0;">
    <!-- Beautiful Inline SVG Envelope Icon with Checkmark Badge -->
    <svg width="80" height="80" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: inline-block;">
        <rect x="2" y="6" width="28" height="20" rx="3" fill="#f0f1ff" />
        <rect x="2" y="6" width="28" height="20" rx="3" stroke="#696cff" stroke-width="2" />
        <path d="M2 7L16 17L30 7" stroke="#696cff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        <path d="M2 25L12 16" stroke="#696cff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        <path d="M30 25L20 16" stroke="#696cff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        <circle cx="24" cy="22" r="7" fill="#71dd37" stroke="#ffffff" stroke-width="2" />
        <path d="M21 22L23 24L27 20" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</div>

<p style="text-align: center; font-size: 16px; font-weight: 600; color: #566a7f; margin-bottom: 16px;">Hello <?= htmlspecialchars((string) ($name ?? '')) ?>,</p>

<p style="text-align: center; color: #697a8d; margin-bottom: 24px; font-size: 15px; line-height: 1.6;">
    Good news! Your WorkEddy account (<strong><?= htmlspecialchars((string) ($username ?? '')) ?></strong>) has been successfully activated.<br>
    You can now log in to your workspace and access all platform features.
</p>

<!-- CTA Button -->
<div style="text-align: center; margin-bottom: 30px;">
    <a href="<?= htmlspecialchars(rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/') . '/login') ?>" target="_blank" style="display: inline-block; background-color: #696cff; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; padding: 12px 28px; border-radius: 6px; box-shadow: 0 4px 10px rgba(105, 108, 255, 0.25);">
        LOGIN TO YOUR ACCOUNT
    </a>
</div>

<p style="text-align: center; color: #a1b0cb; font-size: 13px; margin-top: 0; margin-bottom: 0;">
    Thank you for choosing WorkEddy as your operations partner.
</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout/shell.php';
?>
