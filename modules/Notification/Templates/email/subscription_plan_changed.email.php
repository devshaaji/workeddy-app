<?php

declare(strict_types=1);

$subject = 'Subscription Plan Changed';
ob_start();
?>
<h2 style="margin-top: 0; color: #696cff; font-size: 22px; font-weight: 600; text-align: center; line-height: 1.3;">Subscription Plan Changed</h2>

<p style="font-size: 16px; color: #566a7f; margin-bottom: 16px;">Hello,</p>
<p style="color: #697a8d; margin-bottom: 24px; font-size: 15px; line-height: 1.6;">
    Your subscription plan has been updated successfully.
</p>

<div style="background-color: #f4f5ff; border-left: 4px solid #696cff; padding: 16px; border-radius: 4px; margin-bottom: 24px;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding: 4px 0; font-weight: 600; color: #566a7f; width: 120px; font-size: 14px;">Previous Plan:</td>
            <td style="padding: 4px 0; color: #697a8d; font-size: 14px;"><?= htmlspecialchars((string) ($old_plan_code ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <td style="padding: 4px 0; font-weight: 600; color: #566a7f; font-size: 14px;">New Plan:</td>
            <td style="padding: 4px 0; color: #696cff; font-size: 14px; font-weight: 600;"><?= htmlspecialchars((string) ($new_plan_name ?? $new_plan_code ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
    </table>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout/shell.php';
?>
