<?php

declare(strict_types=1);

return [
    'driver' => $_ENV['WorkEddy_CACHE_DRIVER'] ?? $_ENV['CACHE_DRIVER'] ?? 'filesystem',
    'namespace' => $_ENV['WorkEddy_CACHE_NAMESPACE'] ?? $_ENV['CACHE_NAMESPACE'] ?? 'WorkEddy',
    'path' => $_ENV['WorkEddy_CACHE_PATH'] ?? $_ENV['CACHE_PATH'] ?? dirname(__DIR__) . '/storage/cache',
    'default_ttl' => (int) ($_ENV['CACHE_DEFAULT_TTL'] ?? 300),
];
