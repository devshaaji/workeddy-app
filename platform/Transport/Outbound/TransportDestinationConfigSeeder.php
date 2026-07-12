<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Outbound;

use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Config\ConfigLoader;
use WorkEddy\Platform\Transport\TransportDestination;
use WorkEddy\Platform\Transport\TransportStoreInterface;

final class TransportDestinationConfigSeeder
{
    public function __construct(
        private readonly TransportStoreInterface $store,
        private readonly ConfigLoader $config,
        private readonly IClock $clock,
    ) {}

    public function seed(): int
    {
        $destinations = $this->config->get('transport.destinations', []);
        if (!is_array($destinations)) {
            return 0;
        }

        $seeded = 0;
        foreach ($destinations as $name => $destinationConfig) {
            if (!is_string($name) || trim($name) === '' || !is_array($destinationConfig)) {
                continue;
            }

            $this->store->saveDestination($this->destinationFromConfig($name, $destinationConfig));
            $seeded++;
        }

        return $seeded;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function destinationFromConfig(string $name, array $config): TransportDestination
    {
        $existing = $this->store->findDestination($name);
        $now = $this->clock->now();
        $fallbacks = $config['fallback_destinations'] ?? [];
        $retryPolicy = $config['retry_policy'] ?? [];

        return new TransportDestination(
            name: $name,
            driver: strtolower(trim((string) ($config['driver'] ?? $existing?->driver ?? 'http'))) ?: 'http',
            baseUrl: $this->nullableString($config['base_url'] ?? $existing?->baseUrl),
            endpoint: $this->nullableString($config['endpoint'] ?? $existing?->endpoint),
            authType: strtolower(trim((string) ($config['auth_type'] ?? $existing?->authType ?? 'none'))) ?: 'none',
            credentialsSecret: $this->nullableString($config['credentials_secret'] ?? $existing?->credentialsSecret),
            enabled: (bool) ($config['enabled'] ?? $existing?->enabled ?? true),
            timeoutSeconds: max(1, (int) ($config['timeout_seconds'] ?? $existing?->timeoutSeconds ?? $this->config->get('transport.drivers.http.timeout_seconds', 15))),
            retryPolicy: is_array($retryPolicy) ? $retryPolicy : ($existing?->retryPolicy ?? []),
            fallbackDestinations: array_values(array_filter(array_map(
                static fn(mixed $value): string => trim((string) $value),
                is_array($fallbacks) ? $fallbacks : ($existing?->fallbackDestinations ?? []),
            ))),
            createdAt: $existing?->createdAt ?? $now,
            updatedAt: $now,
        );
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
