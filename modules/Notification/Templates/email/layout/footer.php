<?php

declare(strict_types=1);

$appUrl = $_ENV['APP_URL'] ?? 'http://localhost';
?>
<div style="margin-bottom: 20px;">
    <a href="<?= htmlspecialchars($appUrl) ?>" style="color: #696cff; text-decoration: underline;" target="_blank">View in browser</a>
</div>

<div style="margin-bottom: 20px; font-size: 12px; color: #a1b0cb;">
    © <?= date('Y') ?> WorkEddy. All rights reserved.<br>
    123 Innovation Way, Tech Suite 500, San Francisco, CA 94107
</div>

<!-- Social Links -->
<div style="margin-bottom: 24px;">
    <a href="https://linkedin.com" target="_blank" style="display: inline-block; margin: 0 8px; color: #a1b0cb; text-decoration: none;">
        <span style="display: inline-block; width: 28px; height: 28px; line-height: 28px; text-align: center; background-color: #e4e6eb; border-radius: 50%; font-size: 14px; font-weight: bold; color: #566a7f;">in</span>
    </a>
    <a href="https://twitter.com" target="_blank" style="display: inline-block; margin: 0 8px; color: #a1b0cb; text-decoration: none;">
        <span style="display: inline-block; width: 28px; height: 28px; line-height: 28px; text-align: center; background-color: #e4e6eb; border-radius: 50%; font-size: 14px; font-weight: bold; color: #566a7f;">x</span>
    </a>
    <a href="https://workeddy.com" target="_blank" style="display: inline-block; margin: 0 8px; color: #a1b0cb; text-decoration: none;">
        <span style="display: inline-block; width: 28px; height: 28px; line-height: 28px; text-align: center; background-color: #e4e6eb; border-radius: 50%; font-size: 14px; font-weight: bold; color: #566a7f;">w</span>
    </a>
</div>

<div style="font-size: 11px; color: #a1b0cb;">
    You received this email because you are a registered user of WorkEddy.<br>
    If you no longer wish to receive these emails, you can <a href="<?= htmlspecialchars($appUrl) ?>/settings/notifications" style="color: #696cff; text-decoration: underline;" target="_blank">unsubscribe</a>.
</div>
