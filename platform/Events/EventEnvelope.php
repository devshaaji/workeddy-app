<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Events;

final class EventEnvelope
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly int $schemaVersion,
        public readonly string $sourceModule,
        public readonly string $aggregateType,
        public readonly string $aggregateId,
        public readonly string $idempotencyKey,
        public readonly string $occurredAt,
        public readonly ?string $actorId,
        public readonly array $payload,
        public readonly array $metadata = [],
        public readonly ?string $actorType = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'schema_version' => $this->schemaVersion,
            'source_module' => $this->sourceModule,
            'aggregate_type' => $this->aggregateType,
            'aggregate_id' => $this->aggregateId,
            'idempotency_key' => $this->idempotencyKey,
            'occurred_at' => $this->occurredAt,
            'actor_id' => $this->actorId,
            'actor_type' => $this->actorType,
            'payload' => $this->payload,
            'metadata' => $this->metadata,
        ];
    }
}
