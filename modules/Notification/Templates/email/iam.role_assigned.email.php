<?php

declare(strict_types=1);

$subject = 'Role Updated';
ob_start();
?>
<h2 style="margin-top: 0; color: #566a7f; font-size: 22px; font-weight: 600; text-align: center; line-height: 1.3;">Account Role Updated</h2>

<div style="text-align: center; margin: 24px 0;">
    <!-- Profile Card SVG -->
    <svg width="80" height="80" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: inline-block;">
        <circle cx="16" cy="10" r="5" fill="#f0f1ff" stroke="#696cff" stroke-width="2" />
        <path d="M6 24C6 19.5817 9.58172 16 14 16H18C22.4183 16 26 19.5817 26 24V26H6V24Z" fill="#f0f1ff" stroke="#696cff" stroke-width="2" />
        <rect x="12" y="2" width="8" height="3" rx="1.5" fill="#ffab00" />
    </svg>
</div>

<p style="font-size: 16px; font-weight: 600; color: #566a7f; margin-bottom: 16px; text-align: center;">Hello <?= htmlspecialchars((string) ($name ?? '')) ?>,</p>

<p style="text-align: center; color: #697a8d; margin-bottom: 24px; font-size: 15px; line-height: 1.6;">
    Your account permissions have been updated. You have been assigned the following new role:
</p>

<!-- Role Display -->
<div style="background-color: #f8f9fa; border: 1px solid #e4e6eb; border-radius: 6px; padding: 16px; text-align: center; margin-bottom: 24px; max-width: 320px; margin-left: auto; margin-right: auto;">
    <span style="font-size: 13px; text-transform: uppercase; color: #a1b0cb; font-weight: 600; display: block; margin-bottom: 4px;">Assigned Role</span>
    <span style="font-size: 18px; color: #696cff; font-weight: 700;"><?= htmlspecialchars((string) ($roleName ?? '')) ?></span>
</div>

<p style="text-align: center; color: #697a8d; font-size: 14px; line-height: 1.5; margin-bottom: 24px;">
    If you have any questions regarding your new access permissions, please reach out to your administrator.
</p>

<!-- CTA Button -->
<div style="text-align: center; margin-bottom: 20px;">
    <a href="<?= htmlspecialchars(rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/') . '/dashboard') ?>" target="_blank" style="display: inline-block; background-color: #696cff; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; padding: 12px 28px; border-radius: 6px; box-shadow: 0 4px 10px rgba(105, 108, 255, 0.25);">
        GO TO WORKSPACE
    </a>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout/shell.php';
?>
