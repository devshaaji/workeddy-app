<?php

declare(strict_types=1);

$subject = 'New Website Contact Submission';
ob_start();
?>
<h2 style="margin-top: 0; color: #566a7f; font-size: 22px; font-weight: 600; text-align: center; line-height: 1.3;">New Website Contact Submission</h2>

<div style="text-align: center; margin: 24px 0;">
    <!-- Inbox SVG -->
    <svg width="80" height="80" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: inline-block;">
        <rect x="4" y="6" width="24" height="20" rx="3" fill="#f0f1ff" stroke="#696cff" stroke-width="2" />
        <path d="M4 14C8 14 10 17 16 17C22 17 24 14 28 14" stroke="#696cff" stroke-width="2" fill="none" />
        <circle cx="16" cy="10" r="2" fill="#696cff" />
    </svg>
</div>

<p style="color: #697a8d; margin-bottom: 24px; font-size: 15px; line-height: 1.6; text-align: center;">
    You have received a new contact submission from the website. Details are listed below:
</p>

<!-- Submission Details Card -->
<div style="background-color: #f8f9fa; border: 1px solid #e4e6eb; border-radius: 6px; padding: 20px; margin-bottom: 24px;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; width: 140px; font-size: 14px;">Name:</td>
            <td style="padding: 6px 0; color: #697a8d; font-size: 14px; font-weight: 600;"><?= htmlspecialchars((string) ($name ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; font-size: 14px;">Organization:</td>
            <td style="padding: 6px 0; color: #697a8d; font-size: 14px;"><?= htmlspecialchars((string) ($organization ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; font-size: 14px;">Email Address:</td>
            <td style="padding: 6px 0; color: #697a8d; font-size: 14px;"><a href="mailto:<?= htmlspecialchars((string) ($email ?? '')) ?>" style="color: #696cff; text-decoration: none;"><?= htmlspecialchars((string) ($email ?? ''), ENT_QUOTES, 'UTF-8') ?></a></td>
        </tr>
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; font-size: 14px;">Role or Title:</td>
            <td style="padding: 6px 0; color: #697a8d; font-size: 14px;"><?= htmlspecialchars((string) ($role ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; font-size: 14px;">Industry:</td>
            <td style="padding: 6px 0; color: #697a8d; font-size: 14px;"><?= htmlspecialchars((string) ($industry ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; font-size: 14px;">Reason for Contact:</td>
            <td style="padding: 6px 0; color: #697a8d; font-size: 14px;"><?= htmlspecialchars((string) ($reason ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
    </table>
    
    <div style="border-top: 1px solid #e4e6eb; margin-top: 12px; padding-top: 12px;">
        <span style="font-weight: 600; color: #566a7f; font-size: 14px; display: block; margin-bottom: 8px;">Message:</span>
        <div style="color: #697a8d; font-size: 14px; line-height: 1.6; white-space: pre-wrap; font-style: italic; background-color: #ffffff; padding: 12px; border-radius: 4px; border: 1px dashed #e4e6eb;">
            <?= nl2br(htmlspecialchars((string) ($message ?? ''), ENT_QUOTES, 'UTF-8')) ?>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout/shell.php';
?>
