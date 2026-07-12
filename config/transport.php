<?php

declare(strict_types=1);

return [
    'default_destination' => $_ENV['TRANSPORT_DEFAULT_DESTINATION'] ?? 'edge.primary',
    'retry' => [
        'default_max_attempts' => (int) ($_ENV['TRANSPORT_RETRY_DEFAULT_MAX_ATTEMPTS'] ?? 10),
        'backoff_seconds' => [30, 120, 300, 900],
    ],
    'capabilities' => [
        'runtime_id' => $_ENV['TRANSPORT_RUNTIME_ID'] ?? ($_ENV['APP_RUNTIME_ID'] ?? 'cloud.primary'),
        'runtime_type' => $_ENV['TRANSPORT_RUNTIME_TYPE'] ?? 'cloud',
        'recommended_inbound_mode' => $_ENV['TRANSPORT_RECOMMENDED_INBOUND_MODE'] ?? 'http_push',
        'fallback_modes' => ['polling'],
        'preferred_modes' => ['http_push', 'polling'],
        'remote_cache_ttl_seconds' => (int) ($_ENV['TRANSPORT_REMOTE_CACHE_TTL_SECONDS'] ?? 300),
        'endpoints' => [
            'http_push' => $_ENV['TRANSPORT_HTTP_PUSH_ENDPOINT'] ?? '/api/v1/transport/inbound',
            'polling' => $_ENV['TRANSPORT_POLLING_ENDPOINT'] ?? '/api/v1/transport/inbound/batch',
        ],
    ],
    'drivers' => [
        'http' => [
            'enabled' => filter_var($_ENV['TRANSPORT_DRIVER_HTTP_ENABLED'] ?? true, FILTER_VALIDATE_BOOL),
            'timeout_seconds' => (int) ($_ENV['TRANSPORT_DRIVER_HTTP_TIMEOUT_SECONDS'] ?? 15),
        ],
        'polling' => [
            'enabled' => filter_var($_ENV['TRANSPORT_DRIVER_POLLING_ENABLED'] ?? true, FILTER_VALIDATE_BOOL),
        ],
        'sse' => [
            'enabled' => filter_var($_ENV['TRANSPORT_DRIVER_SSE_ENABLED'] ?? false, FILTER_VALIDATE_BOOL),
        ],
        'websocket' => [
            'enabled' => filter_var($_ENV['TRANSPORT_DRIVER_WEBSOCKET_ENABLED'] ?? false, FILTER_VALIDATE_BOOL),
        ],
    ],
    'destinations' => [
        'edge.primary' => [
            'driver' => $_ENV['TRANSPORT_EDGE_DRIVER'] ?? 'http',
            'base_url' => $_ENV['TRANSPORT_EDGE_BASE_URL'] ?? null,
            'endpoint' => $_ENV['TRANSPORT_EDGE_ENDPOINT'] ?? '/api/v1/transport/inbound',
            'auth_type' => $_ENV['TRANSPORT_EDGE_AUTH_TYPE'] ?? 'bearer',
            'credentials_secret' => $_ENV['TRANSPORT_EDGE_SHARED_SECRET'] ?? null,
            'enabled' => filter_var($_ENV['TRANSPORT_EDGE_ENABLED'] ?? false, FILTER_VALIDATE_BOOL),
            'timeout_seconds' => (int) ($_ENV['TRANSPORT_EDGE_TIMEOUT_SECONDS'] ?? 15),
            'fallback_destinations' => [],
        ],
    ],
    'inbound_sources' => [
        'edge.primary' => [
            'type' => 'edge',
            'enabled' => filter_var($_ENV['TRANSPORT_EDGE_INBOUND_ENABLED'] ?? true, FILTER_VALIDATE_BOOL),
            'auth_type' => $_ENV['TRANSPORT_EDGE_AUTH_TYPE'] ?? 'bearer',
            'secret_hash' => isset($_ENV['TRANSPORT_EDGE_SHARED_SECRET']) && trim((string) $_ENV['TRANSPORT_EDGE_SHARED_SECRET']) !== ''
                ? 'plain:' . trim((string) $_ENV['TRANSPORT_EDGE_SHARED_SECRET'])
                : null,
            'allowed_topics' => ['transport.*', 'edge_admin.*', 'captive_portal.*', 'access.accounting.*'],
            'allowed_ip_ranges' => [],
            'require_signature' => false,
            'signature_header' => 'X-Transport-Signature',
            'timestamp_header' => 'X-Transport-Timestamp',
            'max_clock_skew_seconds' => 300,
        ],
    ],
];
