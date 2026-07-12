<?php

declare(strict_types=1);

$subject = 'Support Ticket Created';
ob_start();
?>
<h2 style="margin-top: 0; color: #566a7f; font-size: 22px; font-weight: 600; text-align: center; line-height: 1.3;">Support Ticket Created</h2>

<div style="text-align: center; margin: 24px 0;">
    <!-- Ticket SVG -->
    <svg width="80" height="80" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: inline-block;">
        <rect x="4" y="8" width="24" height="16" rx="2" fill="#f0f1ff" stroke="#696cff" stroke-width="2" />
        <path d="M4 16C6 16 7 15 7 14C7 13 6 12 4 12" stroke="#696cff" stroke-width="2" fill="none" />
        <path d="M28 16C26 16 25 15 25 14C25 13 26 12 28 12" stroke="#696cff" stroke-width="2" fill="none" />
        <path d="M11 12H21M11 16H18" stroke="#696cff" stroke-width="2" stroke-linecap="round" />
    </svg>
</div>

<p style="font-size: 16px; color: #566a7f; margin-bottom: 16px;">Hello,</p>
<p style="color: #697a8d; margin-bottom: 24px; font-size: 15px; line-height: 1.6;">
    Your support ticket has been successfully created. Our support representatives will review your request and reply shortly.
</p>

<!-- Ticket Details Box -->
<div style="background-color: #f8f9fa; border: 1px solid #e4e6eb; border-radius: 6px; padding: 20px; margin-bottom: 24px;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; width: 140px; font-size: 14px;">Ticket Number:</td>
            <td style="padding: 6px 0; color: #697a8d; font-size: 14px; font-weight: 600;"><?= htmlspecialchars((string) ($ticket_number ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; font-size: 14px;">Subject:</td>
            <td style="padding: 6px 0; color: #697a8d; font-size: 14px;"><?= htmlspecialchars((string) ($subject ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; font-size: 14px;">Status:</td>
            <td style="padding: 6px 0; color: #ffab00; font-size: 14px; font-weight: 600; text-transform: uppercase;"><?= htmlspecialchars((string) ($status ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
    </table>
</div>

<!-- CTA Button -->
<div style="text-align: center; margin-bottom: 20px;">
    <a href="<?= htmlspecialchars(rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/') . '/support') ?>" target="_blank" style="display: inline-block; background-color: #696cff; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; padding: 12px 28px; border-radius: 6px; box-shadow: 0 4px 10px rgba(105, 108, 255, 0.25);">
        VIEW TICKET STATUS
    </a>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout/shell.php';
?>
