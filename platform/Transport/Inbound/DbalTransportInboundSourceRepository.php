<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Inbound;

use WorkEddy\Platform\Transport\Shared\PayloadSerializer;
use Doctrine\DBAL\Connection;

final class DbalTransportInboundSourceRepository implements TransportInboundSourceRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly PayloadSerializer $serializer,
    ) {}

    public function save(TransportInboundSource $source): TransportInboundSource
    {
        $data = [
            'name' => $source->name,
            'type' => $source->type,
            'enabled' => $source->enabled ? 1 : 0,
            'auth_type' => $source->authType,
            'secret_hash' => $source->secretHash,
            'allowed_topics_json' => $this->serializer->encode(['topics' => $source->allowedTopics]),
            'allowed_ip_ranges_json' => $this->serializer->encode(['ranges' => $source->allowedIpRanges]),
            'require_signature' => $source->requireSignature ? 1 : 0,
            'signature_header' => $source->signatureHeader,
            'timestamp_header' => $source->timestampHeader,
            'max_clock_skew_seconds' => $source->maxClockSkewSeconds,
            'created_at' => $this->format($source->createdAt),
            'updated_at' => $this->format($source->updatedAt),
        ];

        $existing = $this->findByName($source->name);
        if ($existing === null) {
            $this->connection->insert('transport_inbound_sources', $data);
            $id = (int) $this->connection->lastInsertId();

            return new TransportInboundSource($id, $source->name, $source->type, $source->enabled, $source->authType, $source->secretHash, $source->allowedTopics, $source->allowedIpRanges, $source->requireSignature, $source->signatureHeader, $source->timestampHeader, $source->maxClockSkewSeconds, $source->createdAt, $source->updatedAt);
        }

        unset($data['created_at']);
        $this->connection->update('transport_inbound_sources', $data, ['name' => $source->name]);

        return $source;
    }

    public function findByName(string $name): ?TransportInboundSource
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM transport_inbound_sources WHERE name = :name', ['name' => $name]);

        return $row === false ? null : $this->fromRow($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function fromRow(array $row): TransportInboundSource
    {
        $topics = $this->serializer->decode($row['allowed_topics_json'] ?? null);
        $ranges = $this->serializer->decode($row['allowed_ip_ranges_json'] ?? null);

        return new TransportInboundSource(
            isset($row['id']) ? (int) $row['id'] : null,
            (string) $row['name'],
            (string) $row['type'],
            (bool) $row['enabled'],
            (string) $row['auth_type'],
            $row['secret_hash'] !== null ? (string) $row['secret_hash'] : null,
            array_values(array_map('strval', is_array($topics['topics'] ?? null) ? $topics['topics'] : [])),
            array_values(array_map('strval', is_array($ranges['ranges'] ?? null) ? $ranges['ranges'] : [])),
            (bool) $row['require_signature'],
            (string) $row['signature_header'],
            (string) $row['timestamp_header'],
            (int) $row['max_clock_skew_seconds'],
            new \DateTimeImmutable((string) $row['created_at']),
            new \DateTimeImmutable((string) $row['updated_at']),
        );
    }

    private function format(\DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
