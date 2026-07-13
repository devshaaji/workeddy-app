<?php

declare(strict_types=1);

$subject = 'Subscription Suspended';
ob_start();
?>
<h2 style="margin-top: 0; color: #ff3e1d; font-size: 22px; font-weight: 600; text-align: center; line-height: 1.3;">Subscription Suspended</h2>

<p style="font-size: 16px; color: #566a7f; margin-bottom: 16px;">Hello,</p>
<p style="color: #697a8d; margin-bottom: 24px; font-size: 15px; line-height: 1.6;">
    Your WorkEddy subscription has been suspended.
</p>

<div style="background-color: #fff2f0; border-left: 4px solid #ff3e1d; padding: 16px; border-radius: 4px; margin-bottom: 24px;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 4px 0; font-weight: 600; color: #566a7f; width: 120px; font-size: 14px;">Plan:</td>
            <td style="padding: 4px 0; color: #ff3e1d; font-size: 14px; font-weight: 600;"><?= htmlspecialchars((string) ($plan_name ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <td style="padding: 4px 0; font-weight: 600; color: #566a7f; font-size: 14px;">Reason:</td>
            <td style="padding: 4px 0; color: #697a8d; font-size: 14px;"><?= htmlspecialchars((string) ($reason ?? 'Subscription suspended'), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
    </table>
</div>

<p style="color: #697a8d; font-size: 14px; line-height: 1.5; margin-bottom: 24px;">
    Please review your billing and account settings to restore access if needed.
</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout/shell.php';
?>
