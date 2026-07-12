<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport;

interface TransportStoreInterface
{
    public function saveDestination(TransportDestination $destination): void;

    public function findDestination(string $name): ?TransportDestination;

    public function createOutbox(TransportMessage $message): TransportMessage;

    public function findOutboxByIdempotency(string $destination, string $topic, string $idempotencyKey): ?TransportMessage;

    /**
     * @return list<TransportMessage>
     */
    public function claimDue(int $limit, \DateTimeImmutable $now): array;

    public function markDelivered(TransportMessage $message, TransportDestination $destination, TransportResult $result, \DateTimeImmutable $now): void;

    public function markFailed(TransportMessage $message, TransportDestination $destination, TransportResult $result, \DateTimeImmutable $now): void;

    public function scheduleRetry(TransportMessage $message, TransportDestination $destination, TransportResult $result, \DateTimeImmutable $nextAttemptAt, \DateTimeImmutable $now): void;

    public function recordAttempt(TransportMessage $message, TransportDestination $destination, TransportResult $result, \DateTimeImmutable $attemptedAt): void;

    public function createInbox(TransportInboxMessage $message): TransportInboxMessage;

    public function findInboxByIdempotency(string $source, string $topic, string $idempotencyKey): ?TransportInboxMessage;
}
