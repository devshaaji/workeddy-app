<?php

declare(strict_types=1);

namespace WorkEddy\Shared\Events;

use WorkEddy\Platform\Events\DomainEvent;
use WorkEddy\Platform\Events\EventEnvelope;
use WorkEddy\Shared\Support\UuidSupport;
use DateTimeImmutable;

final class IamUserCreatedEvent implements DomainEvent
{
    public function __construct(
        public readonly int|string $actorId,
        public readonly int|string $userId,
        public readonly string $userUuid,
        public readonly string $email,
        public readonly string $roleSlug,
    ) {}

    public function envelope(): EventEnvelope
    {
        return new EventEnvelope(
            eventId: UuidSupport::generate(),
            eventType: 'iam.user.created',
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
                'email' => $this->email,
                'role_slug' => $this->roleSlug,
            ],
            metadata: []
        );
    }
}
