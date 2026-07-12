<?php

declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'WorkEddy',
    'runtime' => $_ENV['APP_RUNTIME'] ?? 'WorkEddy',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Africa/Lagos',
];
