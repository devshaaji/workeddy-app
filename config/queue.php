<?php

declare(strict_types=1);

$env = static function (string $key, mixed $default = null): mixed {
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }

    $value = getenv($key);

    return $value === false ? $default : $value;
};

return [
    'driver' => $env('WorkEddy_QUEUE_DRIVER', 'database'),
    'default_queue' => $env('WorkEddy_QUEUE_DEFAULT', 'default'),
    'default_max_attempts' => max(1, (int) $env('WorkEddy_QUEUE_MAX_ATTEMPTS', 3)),
    'retry_delay_seconds' => max(1, (int) $env('WorkEddy_QUEUE_RETRY_DELAY_SECONDS', 60)),
    'lock_seconds' => max(10, (int) $env('WorkEddy_QUEUE_LOCK_SECONDS', 120)),
    'worker_limit' => max(1, (int) $env('WorkEddy_QUEUE_WORKER_LIMIT', 25)),
    'maintenance_limit' => max(1, (int) $env('WorkEddy_QUEUE_MAINTENANCE_LIMIT', 100)),
];
