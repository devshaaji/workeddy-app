<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport\Inbound;

use WorkEddy\Platform\Transport\Shared\PayloadSerializer;
use Doctrine\DBAL\Connection;

final class DbalTransportInboxRepository implements TransportInboxRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly PayloadSerializer $serializer,
    ) {}

    public function create(TransportInboxMessage $message): TransportInboxMessage
    {
        $this->connection->insert('transport_inbox', $this->toRow($message));
        $id = (int) $this->connection->lastInsertId();

        return $this->withId($message, $id);
    }

    public function findDuplicate(string $source, ?string $idempotencyKey, ?string $remoteMessageId): ?TransportInboxMessage
    {
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $row = $this->connection->fetchAssociative(
                'SELECT * FROM transport_inbox WHERE source = :source AND idempotency_key = :idempotency_key ORDER BY id ASC LIMIT 1',
                ['source' => $source, 'idempotency_key' => $idempotencyKey],
            );
            if ($row !== false) {
                return $this->fromRow($row);
            }
        }

        if ($remoteMessageId !== null && $remoteMessageId !== '') {
            $row = $this->connection->fetchAssociative(
                'SELECT * FROM transport_inbox WHERE source = :source AND remote_message_id = :remote_message_id ORDER BY id ASC LIMIT 1',
                ['source' => $source, 'remote_message_id' => $remoteMessageId],
            );
            if ($row !== false) {
                return $this->fromRow($row);
            }
        }

        return null;
    }

    public function claimPending(int $limit, \DateTimeImmutable $now): array
    {
        return $this->connection->transactional(function () use ($limit, $now): array {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT * FROM transport_inbox
                 WHERE status IN (:received, :retrying)
                   AND (next_attempt_at IS NULL OR next_attempt_at <= :now)
                 ORDER BY received_at ASC, id ASC
                 LIMIT ' . max(1, min(500, $limit)),
                ['received' => TransportInboxMessage::STATUS_RECEIVED, 'retrying' => TransportInboxMessage::STATUS_RETRYING, 'now' => $this->format($now)],
            );

            $claimed = [];
            foreach ($rows as $row) {
                $affected = $this->connection->executeStatement(
                    'UPDATE transport_inbox
                     SET status = :processing, processing_started_at = :now, updated_at = :now
                     WHERE id = :id AND status IN (:received, :retrying)',
                    [
                        'processing' => TransportInboxMessage::STATUS_PROCESSING,
                        'now' => $this->format($now),
                        'id' => (int) $row['id'],
                        'received' => TransportInboxMessage::STATUS_RECEIVED,
                        'retrying' => TransportInboxMessage::STATUS_RETRYING,
                    ],
                );
                if ($affected === 1) {
                    $row['status'] = TransportInboxMessage::STATUS_PROCESSING;
                    $row['processing_started_at'] = $this->format($now);
                    $claimed[] = $this->fromRow($row);
                }
            }

            return $claimed;
        });
    }

    public function markProcessing(TransportInboxMessage $message, \DateTimeImmutable $now): TransportInboxMessage
    {
        $this->connection->update('transport_inbox', [
            'status' => TransportInboxMessage::STATUS_PROCESSING,
            'processing_started_at' => $this->format($now),
            'updated_at' => $this->format($now),
        ], ['id' => $message->id]);

        return $this->fromRow($this->connection->fetchAssociative('SELECT * FROM transport_inbox WHERE id = :id', ['id' => $message->id]) ?: []);
    }

    public function markProcessed(TransportInboxMessage $message, TransportProcessingResult $result, \DateTimeImmutable $now): void
    {
        $this->connection->update('transport_inbox', [
            'status' => TransportInboxMessage::STATUS_PROCESSED,
            'attempt_count' => $message->attemptCount + 1,
            'processed_at' => $this->format($now),
            'error_message' => null,
            'last_error_code' => null,
            'updated_at' => $this->format($now),
        ], ['id' => $message->id]);
    }

    public function scheduleRetry(TransportInboxMessage $message, TransportProcessingResult $result, \DateTimeImmutable $nextAttemptAt, \DateTimeImmutable $now): void
    {
        $this->connection->update('transport_inbox', [
            'status' => TransportInboxMessage::STATUS_RETRYING,
            'attempt_count' => $message->attemptCount + 1,
            'next_attempt_at' => $this->format($nextAttemptAt),
            'error_message' => $this->clip($result->errorMessage),
            'last_error_code' => $result->errorCode,
            'updated_at' => $this->format($now),
        ], ['id' => $message->id]);
    }

    public function markFailed(TransportInboxMessage $message, TransportProcessingResult $result, \DateTimeImmutable $now): void
    {
        $this->connection->update('transport_inbox', [
            'status' => TransportInboxMessage::STATUS_FAILED,
            'attempt_count' => $message->attemptCount + 1,
            'failed_at' => $this->format($now),
            'error_message' => $this->clip($result->errorMessage),
            'last_error_code' => $result->errorCode,
            'updated_at' => $this->format($now),
        ], ['id' => $message->id]);
    }

    public function markRejected(TransportInboxMessage $message, string $errorMessage, string $errorCode, \DateTimeImmutable $now): void
    {
        $this->connection->update('transport_inbox', [
            'status' => TransportInboxMessage::STATUS_REJECTED,
            'failed_at' => $this->format($now),
            'error_message' => $this->clip($errorMessage),
            'last_error_code' => $errorCode,
            'updated_at' => $this->format($now),
        ], ['id' => $message->id]);
    }

    public function markProcessedAckSent(TransportInboxMessage $message, \DateTimeImmutable $now): void
    {
        $this->connection->update('transport_inbox', [
            'processed_ack_sent_at' => $this->format($now),
            'updated_at' => $this->format($now),
        ], ['id' => $message->id]);
    }

    public function recordAttempt(TransportInboxMessage $message, int $attemptNumber, string $status, ?string $handler, \DateTimeImmutable $startedAt, \DateTimeImmutable $finishedAt, ?string $errorMessage, ?string $errorCode, bool $retryable): void
    {
        $this->connection->insert('transport_inbox_attempts', [
            'inbox_id' => $message->id,
            'attempt_number' => $attemptNumber,
            'status' => $status,
            'handler' => $handler,
            'started_at' => $this->format($startedAt),
            'finished_at' => $this->format($finishedAt),
            'error_message' => $this->clip($errorMessage),
            'error_code' => $errorCode,
            'retryable' => $retryable ? 1 : 0,
            'created_at' => $this->format($finishedAt),
        ]);
    }

    private function withId(TransportInboxMessage $message, int $id): TransportInboxMessage
    {
        return new TransportInboxMessage($id, $message->uuid, $message->source, $message->topic, $message->payload, $message->headers, $message->rawMessage, $message->status, $message->idempotencyKey, $message->correlationId, $message->remoteMessageId, $message->receivedAckRequired, $message->processedAckRequired, $message->receivedAckSentAt, $message->processedAckSentAt, $message->attemptCount, $message->maxAttempts, $message->nextAttemptAt, $message->receivedAt, $message->processingStartedAt, $message->processedAt, $message->failedAt, $message->errorMessage, $message->lastErrorCode, $message->createdAt, $message->updatedAt);
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(TransportInboxMessage $message): array
    {
        return [
            'uuid' => $message->uuid,
            'source' => $message->source,
            'topic' => $message->topic,
            'payload_json' => $this->serializer->encode($message->payload),
            'headers_json' => $this->serializer->encode($message->headers),
            'raw_message' => $message->rawMessage,
            'status' => $message->status,
            'idempotency_key' => $message->idempotencyKey,
            'correlation_id' => $message->correlationId,
            'remote_message_id' => $message->remoteMessageId,
            'received_ack_required' => $message->receivedAckRequired ? 1 : 0,
            'processed_ack_required' => $message->processedAckRequired ? 1 : 0,
            'received_ack_sent_at' => $message->receivedAckSentAt === null ? null : $this->format($message->receivedAckSentAt),
            'processed_ack_sent_at' => $message->processedAckSentAt === null ? null : $this->format($message->processedAckSentAt),
            'attempt_count' => $message->attemptCount,
            'max_attempts' => $message->maxAttempts,
            'next_attempt_at' => $message->nextAttemptAt === null ? null : $this->format($message->nextAttemptAt),
            'received_at' => $this->format($message->receivedAt),
            'processing_started_at' => $message->processingStartedAt === null ? null : $this->format($message->processingStartedAt),
            'processed_at' => $message->processedAt === null ? null : $this->format($message->processedAt),
            'failed_at' => $message->failedAt === null ? null : $this->format($message->failedAt),
            'error_message' => $message->errorMessage,
            'last_error_code' => $message->lastErrorCode,
            'created_at' => $this->format($message->createdAt),
            'updated_at' => $this->format($message->updatedAt),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function fromRow(array $row): TransportInboxMessage
    {
        return new TransportInboxMessage(
            isset($row['id']) ? (int) $row['id'] : null,
            (string) $row['uuid'],
            (string) $row['source'],
            (string) $row['topic'],
            $this->serializer->decode($row['payload_json'] ?? null),
            $this->serializer->decode($row['headers_json'] ?? null),
            $row['raw_message'] !== null ? (string) $row['raw_message'] : null,
            (string) $row['status'],
            $row['idempotency_key'] !== null ? (string) $row['idempotency_key'] : null,
            $row['correlation_id'] !== null ? (string) $row['correlation_id'] : null,
            $row['remote_message_id'] !== null ? (string) $row['remote_message_id'] : null,
            (bool) $row['received_ack_required'],
            (bool) $row['processed_ack_required'],
            $this->nullableDate($row['received_ack_sent_at'] ?? null),
            $this->nullableDate($row['processed_ack_sent_at'] ?? null),
            (int) $row['attempt_count'],
            (int) $row['max_attempts'],
            $this->nullableDate($row['next_attempt_at'] ?? null),
            new \DateTimeImmutable((string) $row['received_at']),
            $this->nullableDate($row['processing_started_at'] ?? null),
            $this->nullableDate($row['processed_at'] ?? null),
            $this->nullableDate($row['failed_at'] ?? null),
            $row['error_message'] !== null ? (string) $row['error_message'] : null,
            $row['last_error_code'] !== null ? (string) $row['last_error_code'] : null,
            new \DateTimeImmutable((string) $row['created_at']),
            new \DateTimeImmutable((string) $row['updated_at']),
        );
    }

    private function nullableDate(mixed $value): ?\DateTimeImmutable
    {
        return $value === null || $value === '' ? null : new \DateTimeImmutable((string) $value);
    }

    private function format(\DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    private function clip(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 2000);
    }
}
