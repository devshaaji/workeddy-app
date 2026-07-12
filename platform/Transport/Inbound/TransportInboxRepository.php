<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Inbound;

interface TransportInboxRepository
{
    public function create(TransportInboxMessage $message): TransportInboxMessage;

    public function findDuplicate(string $source, ?string $idempotencyKey, ?string $remoteMessageId): ?TransportInboxMessage;

    /**
     * @return list<TransportInboxMessage>
     */
    public function claimPending(int $limit, \DateTimeImmutable $now): array;

    public function markProcessing(TransportInboxMessage $message, \DateTimeImmutable $now): TransportInboxMessage;

    public function markProcessed(TransportInboxMessage $message, TransportProcessingResult $result, \DateTimeImmutable $now): void;

    public function scheduleRetry(TransportInboxMessage $message, TransportProcessingResult $result, \DateTimeImmutable $nextAttemptAt, \DateTimeImmutable $now): void;

    public function markFailed(TransportInboxMessage $message, TransportProcessingResult $result, \DateTimeImmutable $now): void;

    public function markRejected(TransportInboxMessage $message, string $errorMessage, string $errorCode, \DateTimeImmutable $now): void;

    public function markProcessedAckSent(TransportInboxMessage $message, \DateTimeImmutable $now): void;

    public function recordAttempt(TransportInboxMessage $message, int $attemptNumber, string $status, ?string $handler, \DateTimeImmutable $startedAt, \DateTimeImmutable $finishedAt, ?string $errorMessage, ?string $errorCode, bool $retryable): void;
}
