<?php

declare(strict_types=1);

return [
    'url' => $_ENV['DATABASE_URL'] ?? null,
    'driver' => $_ENV['DB_DRIVER'] ?? 'pdo_mysql',
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
    'dbname' => $_ENV['DB_NAME'] ?? $_ENV['DB_DATABASE'] ?? 'WorkEddy',
    'user' => $_ENV['DB_USER'] ?? $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    'analytics' => [
        'url' => $_ENV['ANALYTICS_DATABASE_URL'] ?? null,
        'driver' => $_ENV['ANALYTICS_DB_DRIVER'] ?? ($_ENV['DB_DRIVER'] ?? 'pdo_mysql'),
        'host' => $_ENV['ANALYTICS_DB_HOST'] ?? null,
        'port' => isset($_ENV['ANALYTICS_DB_PORT']) ? (int) $_ENV['ANALYTICS_DB_PORT'] : null,
        'dbname' => $_ENV['ANALYTICS_DB_NAME'] ?? $_ENV['ANALYTICS_DB_DATABASE'] ?? null,
        'user' => $_ENV['ANALYTICS_DB_USER'] ?? $_ENV['ANALYTICS_DB_USERNAME'] ?? null,
        'password' => $_ENV['ANALYTICS_DB_PASSWORD'] ?? null,
        'charset' => $_ENV['ANALYTICS_DB_CHARSET'] ?? ($_ENV['DB_CHARSET'] ?? 'utf8mb4'),
        'query_timeout_ms' => (int) ($_ENV['ANALYTICS_QUERY_TIMEOUT_MS'] ?? 5000),
        'primary_fallback_lock_seconds' => (int) ($_ENV['ANALYTICS_PRIMARY_FALLBACK_LOCK_SECONDS'] ?? 60),
        'read_db_failure_lock_seconds' => (int) ($_ENV['ANALYTICS_READ_DB_FAILURE_LOCK_SECONDS'] ?? 30),
        'require_read_replica' => filter_var($_ENV['ANALYTICS_REQUIRE_READ_REPLICA'] ?? false, FILTER_VALIDATE_BOOL),
    ],
];
