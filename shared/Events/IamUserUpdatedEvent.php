<?php

declare(strict_types=1);

namespace WorkEddy\Shared\Events;

use WorkEddy\Platform\Events\DomainEvent;
use WorkEddy\Platform\Events\EventEnvelope;
use WorkEddy\Shared\Support\UuidSupport;
use DateTimeImmutable;

final class IamUserUpdatedEvent implements DomainEvent
{
    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    public function __construct(
        public readonly int|string $actorId,
        public readonly int|string $userId,
        public readonly string $userUuid,
        public readonly array $before,
        public readonly array $after,
    ) {}

    public function envelope(): EventEnvelope
    {
        return new EventEnvelope(
            eventId: UuidSupport::generate(),
            eventType: 'iam.user.updated',
            schemaVersion: 1,
            sourceModule: 'IAM',
            aggregateType: 'User',
            aggregateId: $this->userUuid,
            idempotencyKey: UuidSupport::generate(),
            occurredAt: (new DateTimeImmutable())->format('Y-m-d\TH:i:s.u\Z'),
            actorId: (string) $this->actorId,
            payload: [
                'user_id' => $this->userId,
                'user_uuid' => $this->userUuid,
                'before' => $this->before,
                'after' => $this->after,
            ],
            metadata: []
        );
    }
}
