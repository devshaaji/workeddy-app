<?php

declare(strict_types=1);

$root = $_ENV['WorkEddy_LOG_ROOT'] ?? dirname(__DIR__) . '/storage/log';
$level = $_ENV['WorkEddy_LOG_LEVEL'] ?? $_ENV['LOG_LEVEL'] ?? 'info';

return [
    'default_channel' => 'app',
    'root' => $root,
    'level' => $level,
    'channels' => [
        'app' => ['path' => $root . '/app.log', 'level' => $level],
        'security' => ['path' => $root . '/security.log', 'level' => $_ENV['WorkEddy_SECURITY_LOG_LEVEL'] ?? $level],
        'integration' => ['path' => $root . '/integration.log', 'level' => $level],
        'system' => ['path' => $root . '/system.log', 'level' => $level],
    ],
];
