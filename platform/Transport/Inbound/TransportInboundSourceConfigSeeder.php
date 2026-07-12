<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Inbound;

use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Config\ConfigLoader;

final class TransportInboundSourceConfigSeeder
{
    public function __construct(
        private readonly TransportInboundSourceRepository $repository,
        private readonly ConfigLoader $config,
        private readonly IClock $clock,
    ) {}

    public function seed(): int
    {
        $sources = $this->config->get('transport.inbound_sources', []);
        if (!is_array($sources)) {
            return 0;
        }

        $seeded = 0;
        $now = $this->clock->now();
        foreach ($sources as $name => $sourceConfig) {
            if (!is_string($name) || trim($name) === '' || !is_array($sourceConfig)) {
                continue;
            }

            $existing = $this->repository->findByName($name);
            $this->repository->save(new TransportInboundSource(
                $existing?->id,
                $name,
                (string) ($sourceConfig['type'] ?? 'runtime'),
                (bool) ($sourceConfig['enabled'] ?? true),
                (string) ($sourceConfig['auth_type'] ?? 'none'),
                $this->nullableString($sourceConfig['secret_hash'] ?? null),
                $this->stringList($sourceConfig['allowed_topics'] ?? ['*']),
                $this->stringList($sourceConfig['allowed_ip_ranges'] ?? []),
                (bool) ($sourceConfig['require_signature'] ?? false),
                (string) ($sourceConfig['signature_header'] ?? 'X-Transport-Signature'),
                (string) ($sourceConfig['timestamp_header'] ?? 'X-Transport-Timestamp'),
                max(0, (int) ($sourceConfig['max_clock_skew_seconds'] ?? 300)),
                $existing?->createdAt ?? $now,
                $now,
            ));
            $seeded++;
        }

        return $seeded;
    }

    private function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn(mixed $item): string => trim((string) $item),
            $value,
        ), static fn(string $item): bool => $item !== ''));
    }
}
