<?php

declare(strict_types=1);

$appUrl = $_ENV['APP_URL'] ?? 'http://localhost';
$logoUrl = rtrim($appUrl, '/') . '/assets/img/workeddy.png';
?>
<div style="text-align: center; margin-bottom: 10px;">
    <a href="<?= htmlspecialchars($appUrl) ?>" target="_blank" style="text-decoration: none;">
        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="WorkEddy" style="width: 160px; max-width: 100%; height: auto; border: 0; display: inline-block;" />
    </a>
</div>
