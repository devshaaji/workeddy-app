<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Drivers;

use WorkEddy\Platform\Identity\UuidGeneratorContract;
use WorkEddy\Platform\Transport\TransportDestination;
use WorkEddy\Platform\Transport\TransportDriverInterface;
use WorkEddy\Platform\Transport\TransportInboxMessage;
use WorkEddy\Platform\Transport\TransportMessage;
use WorkEddy\Platform\Transport\TransportResult;
use WorkEddy\Platform\Transport\TransportStoreInterface;

final class DatabaseTransportDriver implements TransportDriverInterface
{
    public function __construct(
        private readonly TransportStoreInterface $store,
        private readonly UuidGeneratorContract $uuids,
    ) {}

    public function send(TransportMessage $message, TransportDestination $destination): TransportResult
    {
        $now = new \DateTimeImmutable();

        $this->store->createInbox(new TransportInboxMessage(
            null,
            $this->uuids->generate(),
            $destination->name,
            $message->topic,
            $message->payload,
            'received',
            $now,
            null,
            $message->idempotencyKey,
            $message->correlationId,
            null,
        ));

        return TransportResult::success(202, null, 'database-' . $message->uuid, $now);
    }

    public function isAvailable(TransportDestination $destination): bool
    {
        return $destination->enabled;
    }

    public function supports(string $driver): bool
    {
        return $driver === 'database' || $driver === 'local';
    }
}
