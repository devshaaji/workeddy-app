<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport;

final class TransportDestination
{
    /**
     * @param array<string, mixed> $retryPolicy
     * @param list<string> $fallbackDestinations
     */
    public function __construct(
        public readonly string $name,
        public readonly string $driver,
        public readonly ?string $baseUrl,
        public readonly ?string $endpoint,
        public readonly string $authType,
        public readonly ?string $credentialsSecret,
        public readonly bool $enabled,
        public readonly int $timeoutSeconds,
        public readonly array $retryPolicy,
        public readonly array $fallbackDestinations,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {}
}
