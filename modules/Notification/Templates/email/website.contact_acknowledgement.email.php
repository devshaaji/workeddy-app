<?php

declare(strict_types=1);

$subject = 'We received your message';
ob_start();
?>
<h2 style="margin-top: 0; color: #566a7f; font-size: 22px; font-weight: 600; text-align: center; line-height: 1.3;">Message Received</h2>

<div style="text-align: center; margin: 24px 0;">
    <!-- Message SVG -->
    <svg width="80" height="80" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: inline-block;">
        <rect x="2" y="6" width="28" height="20" rx="3" fill="#f0f1ff" stroke="#696cff" stroke-width="2" />
        <path d="M2 7L16 17L30 7" stroke="#696cff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        <circle cx="24" cy="22" r="7" fill="#71dd37" stroke="#ffffff" stroke-width="2" />
        <path d="M21 22L23 24L27 20" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</div>

<p style="font-size: 16px; font-weight: 600; color: #566a7f; margin-bottom: 16px;">Hello <?= htmlspecialchars((string) ($name ?? ''), ENT_QUOTES, 'UTF-8') ?>,</p>

<p style="color: #697a8d; margin-bottom: 24px; font-size: 15px; line-height: 1.6;">
    Thank you for contacting WorkEddy. We have received your message regarding "<strong><?= htmlspecialchars((string) ($reason ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>" and our team will get back to you shortly.
</p>

<p style="font-weight: 600; color: #566a7f; margin-bottom: 12px; font-size: 14px;">For your records, here is a copy of the details you submitted:</p>

<!-- Submission Details Card -->
<div style="background-color: #f8f9fa; border: 1px solid #e4e6eb; border-radius: 6px; padding: 20px; margin-bottom: 24px;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; width: 140px; font-size: 14px;">Organization:</td>
            <td style="padding: 6px 0; color: #697a8d; font-size: 14px;"><?= htmlspecialchars((string) ($organization ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <?php if (!empty($role)): ?>
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; font-size: 14px;">Role or Title:</td>
            <td style="padding: 6px 0; color: #697a8d; font-size: 14px;"><?= htmlspecialchars((string) $role, ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($industry)): ?>
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; font-size: 14px;">Industry:</td>
            <td style="padding: 6px 0; color: #697a8d; font-size: 14px;"><?= htmlspecialchars((string) $industry, ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <?php endif; ?>
    </table>
    
    <div style="border-top: 1px solid #e4e6eb; margin-top: 12px; padding-top: 12px;">
        <span style="font-weight: 600; color: #566a7f; font-size: 14px; display: block; margin-bottom: 8px;">Message:</span>
        <div style="color: #697a8d; font-size: 14px; line-height: 1.6; white-space: pre-wrap; font-style: italic; background-color: #ffffff; padding: 12px; border-radius: 4px; border: 1px dashed #e4e6eb;">
            <?= nl2br(htmlspecialchars((string) ($message ?? ''), ENT_QUOTES, 'UTF-8')) ?>
        </div>
    </div>
</div>

<p style="color: #697a8d; font-size: 14px; line-height: 1.5; margin-bottom: 0;">
    Best regards,<br>
    <strong>The WorkEddy Team</strong>
</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout/shell.php';
?>
