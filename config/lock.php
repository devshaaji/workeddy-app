<?php

declare(strict_types=1);

return [
    'driver' => $_ENV['WorkEddy_LOCK_DRIVER'] ?? 'flock',
    'path' => $_ENV['WorkEddy_LOCK_PATH'] ?? dirname(__DIR__) . '/var/locks',
];
