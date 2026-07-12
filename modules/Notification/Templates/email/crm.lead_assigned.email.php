<?php

declare(strict_types=1);

$subject = 'Lead Assigned';
ob_start();
?>
<h2 style="margin-top: 0; color: #566a7f; font-size: 22px; font-weight: 600; text-align: center; line-height: 1.3;">New Lead Assigned</h2>

<div style="text-align: center; margin: 24px 0;">
    <!-- Lead Icon -->
    <svg width="80" height="80" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: inline-block;">
        <circle cx="16" cy="10" r="6" fill="#f0f1ff" stroke="#696cff" stroke-width="2" />
        <path d="M6 26C6 20.4772 10.4772 16 16 16C21.5228 16 26 20.4772 26 26" stroke="#696cff" stroke-width="2" stroke-linecap="round" />
        <path d="M16 16V21M14 23H18L16 21" stroke="#696cff" stroke-width="2" stroke-linecap="round" />
    </svg>
</div>

<p style="font-size: 16px; color: #566a7f; margin-bottom: 16px;">Hello,</p>
<p style="color: #697a8d; margin-bottom: 24px; font-size: 15px; line-height: 1.6;">
    A new lead has been assigned to you. Here are the lead details:
</p>

<!-- Lead Details Card -->
<div style="background-color: #f8f9fa; border: 1px solid #e4e6eb; border-radius: 6px; padding: 20px; margin-bottom: 24px;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; width: 120px; font-size: 14px;">Name:</td>
            <td style="padding: 6px 0; color: #697a8d; font-size: 14px;"><?= htmlspecialchars((string) ($lead_name ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <?php if (!empty($company_name)): ?>
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; font-size: 14px;">Company:</td>
            <td style="padding: 6px 0; color: #697a8d; font-size: 14px;"><?= htmlspecialchars((string) $company_name, ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($lead_email)): ?>
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; font-size: 14px;">Email:</td>
            <td style="padding: 6px 0; color: #697a8d; font-size: 14px;"><a href="mailto:<?= htmlspecialchars((string) $lead_email) ?>" style="color: #696cff; text-decoration: none;"><?= htmlspecialchars((string) $lead_email, ENT_QUOTES, 'UTF-8') ?></a></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($lead_phone)): ?>
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; font-size: 14px;">Phone:</td>
            <td style="padding: 6px 0; color: #697a8d; font-size: 14px;"><?= htmlspecialchars((string) $lead_phone, ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td style="padding: 6px 0; font-weight: 600; color: #566a7f; font-size: 14px;">Stage:</td>
            <td style="padding: 6px 0; color: #697a8d; font-size: 14px;"><span style="background-color: #e7e7ff; color: #696cff; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; display: inline-block; text-transform: uppercase;"><?= htmlspecialchars((string) ($stage ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
        </tr>
    </table>
</div>

<!-- CTA Button -->
<div style="text-align: center; margin-bottom: 20px;">
    <a href="<?= htmlspecialchars(rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/') . '/crm') ?>" target="_blank" style="display: inline-block; background-color: #696cff; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; padding: 12px 28px; border-radius: 6px; box-shadow: 0 4px 10px rgba(105, 108, 255, 0.25);">
        VIEW LEAD IN CRM
    </a>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout/shell.php';
?>
