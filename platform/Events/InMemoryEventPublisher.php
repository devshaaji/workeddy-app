<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Events;

use WorkEddy\Shared\Support\UuidSupport;

final class InMemoryEventPublisher implements EventPublisherInterface
{
    /** @var list<EventEnvelope> */
    private array $published = [];

    public function publish(string $eventName, array $payload, string $idempotencyKey): void
    {
        $this->published[] = new EventEnvelope(
            eventId: UuidSupport::generate(),
            eventType: $eventName,
            schemaVersion: (int) ($payload['schema_version'] ?? 1),
            sourceModule: explode('.', $eventName)[0] ?: 'unknown',
            aggregateType: $payload['aggregate_type'] ?? $eventName,
            aggregateId: (string) ($payload['aggregate_id'] ?? $payload['principal_id'] ?? $payload['session_id'] ?? $eventName),
            idempotencyKey: $idempotencyKey,
            occurredAt: gmdate(DATE_ATOM),
            actorId: isset($payload['actor_id']) ? (string) $payload['actor_id'] : null,
            payload: $payload,
            metadata: [],
            actorType: isset($payload['actor_type']) ? (string) $payload['actor_type'] : null,
        );
    }

    /**
     * @return list<EventEnvelope>
     */
    public function published(): array
    {
        return $this->published;
    }
}
