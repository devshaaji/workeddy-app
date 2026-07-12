<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Transport;

use Doctrine\DBAL\Connection;

final class DbalTransportStore implements TransportStoreInterface
{
    public function __construct(private readonly Connection $connection) {}

    public function saveDestination(TransportDestination $destination): void
    {
        $data = [
            'name' => $destination->name,
            'driver' => $destination->driver,
            'base_url' => $destination->baseUrl,
            'endpoint' => $destination->endpoint,
            'auth_type' => $destination->authType,
            'credentials_secret' => $destination->credentialsSecret,
            'enabled' => $destination->enabled ? 1 : 0,
            'timeout_seconds' => $destination->timeoutSeconds,
            'retry_policy_json' => TransportJson::encode($destination->retryPolicy),
            'fallback_destinations_json' => TransportJson::encode(['destinations' => $destination->fallbackDestinations]),
            'created_at' => $this->format($destination->createdAt),
            'updated_at' => $this->format($destination->updatedAt),
        ];

        if ($this->findDestination($destination->name) === null) {
            $this->connection->insert('transport_destinations', $data);
            return;
        }

        unset($data['created_at']);
        $this->connection->update('transport_destinations', $data, ['name' => $destination->name]);
    }

    public function findDestination(string $name): ?TransportDestination
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM transport_destinations WHERE name = :name', ['name' => $name]);

        return $row === false ? null : $this->destinationFromRow($row);
    }

    public function createOutbox(TransportMessage $message): TransportMessage
    {
        $this->connection->insert('transport_outbox', $this->messageToRow($message));
        $id = (int) $this->connection->lastInsertId();

        return new TransportMessage(
            $id,
            $message->uuid,
            $message->destination,
            $message->topic,
            $message->payload,
            $message->headers,
            $message->priority,
            $message->status,
            $message->attemptCount,
            $message->maxAttempts,
            $message->nextAttemptAt,
            $message->lastAttemptAt,
            $message->deliveredAt,
            $message->failedAt,
            $message->errorMessage,
            $message->idempotencyKey,
            $message->correlationId,
            $message->createdAt,
            $message->updatedAt,
        );
    }

    public function findOutboxByIdempotency(string $destination, string $topic, string $idempotencyKey): ?TransportMessage
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM transport_outbox WHERE destination = :destination AND topic = :topic AND idempotency_key = :idempotency_key ORDER BY id ASC LIMIT 1',
            ['destination' => $destination, 'topic' => $topic, 'idempotency_key' => $idempotencyKey],
        );

        return $row === false ? null : $this->messageFromRow($row);
    }

    public function claimDue(int $limit, \DateTimeImmutable $now): array
    {
        return $this->connection->transactional(function () use ($limit, $now): array {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT * FROM transport_outbox
                 WHERE status IN (:pending, :retrying)
                   AND (next_attempt_at IS NULL OR next_attempt_at <= :now)
                 ORDER BY priority DESC, created_at ASC
                 LIMIT ' . max(1, min(500, $limit)),
                ['pending' => TransportMessage::STATUS_PENDING, 'retrying' => TransportMessage::STATUS_RETRYING, 'now' => $this->format($now)],
            );

            $claimed = [];
            foreach ($rows as $row) {
                $affected = $this->connection->executeStatement(
                    'UPDATE transport_outbox
                     SET status = :processing, updated_at = :updated_at
                     WHERE id = :id AND status IN (:pending, :retrying)',
                    [
                        'processing' => TransportMessage::STATUS_PROCESSING,
                        'updated_at' => $this->format($now),
                        'id' => (int) $row['id'],
                        'pending' => TransportMessage::STATUS_PENDING,
                        'retrying' => TransportMessage::STATUS_RETRYING,
                    ],
                );
                if ($affected !== 1) {
                    continue;
                }

                $row['status'] = TransportMessage::STATUS_PROCESSING;
                $claimed[] = $this->messageFromRow($row);
            }

            return $claimed;
        });
    }

    public function markDelivered(TransportMessage $message, TransportDestination $destination, TransportResult $result, \DateTimeImmutable $now): void
    {
        $this->recordAttempt($message, $destination, $result, $now);
        $this->connection->update('transport_outbox', [
            'status' => TransportMessage::STATUS_DELIVERED,
            'attempt_count' => $message->attemptCount + 1,
            'last_attempt_at' => $this->format($now),
            'delivered_at' => $this->format($result->deliveredAt ?? $now),
            'error_message' => null,
            'updated_at' => $this->format($now),
        ], ['id' => $message->id]);
    }

    public function markFailed(TransportMessage $message, TransportDestination $destination, TransportResult $result, \DateTimeImmutable $now): void
    {
        $this->recordAttempt($message, $destination, $result, $now);
        $this->connection->update('transport_outbox', [
            'status' => TransportMessage::STATUS_FAILED,
            'attempt_count' => $message->attemptCount + 1,
            'last_attempt_at' => $this->format($now),
            'failed_at' => $this->format($now),
            'error_message' => $this->clip($result->errorMessage),
            'updated_at' => $this->format($now),
        ], ['id' => $message->id]);
    }

    public function scheduleRetry(TransportMessage $message, TransportDestination $destination, TransportResult $result, \DateTimeImmutable $nextAttemptAt, \DateTimeImmutable $now): void
    {
        $this->recordAttempt($message, $destination, $result, $now);
        $this->connection->update('transport_outbox', [
            'status' => TransportMessage::STATUS_RETRYING,
            'attempt_count' => $message->attemptCount + 1,
            'last_attempt_at' => $this->format($now),
            'next_attempt_at' => $this->format($nextAttemptAt),
            'error_message' => $this->clip($result->errorMessage),
            'updated_at' => $this->format($now),
        ], ['id' => $message->id]);
    }

    public function recordAttempt(TransportMessage $message, TransportDestination $destination, TransportResult $result, \DateTimeImmutable $attemptedAt): void
    {
        $this->connection->insert('transport_outbox_attempts', [
            'message_uuid' => $message->uuid,
            'destination' => $destination->name,
            'driver' => $destination->driver,
            'success' => $result->success ? 1 : 0,
            'status_code' => $result->statusCode,
            'response_body' => $this->clip($result->responseBody),
            'remote_message_id' => $result->remoteMessageId,
            'error_message' => $this->clip($result->errorMessage),
            'retryable' => $result->retryable ? 1 : 0,
            'attempted_at' => $this->format($attemptedAt),
        ]);
    }

    public function createInbox(TransportInboxMessage $message): TransportInboxMessage
    {
        $this->connection->insert('transport_inbox', [
            'uuid' => $message->uuid,
            'source' => $message->source,
            'topic' => $message->topic,
            'payload_json' => TransportJson::encode($message->payload),
            'headers_json' => TransportJson::encode([]),
            'raw_message' => null,
            'status' => $message->status,
            'idempotency_key' => $message->idempotencyKey,
            'correlation_id' => $message->correlationId,
            'remote_message_id' => null,
            'received_ack_required' => 0,
            'processed_ack_required' => 0,
            'received_ack_sent_at' => null,
            'processed_ack_sent_at' => null,
            'attempt_count' => 0,
            'max_attempts' => 10,
            'next_attempt_at' => null,
            'received_at' => $this->format($message->receivedAt),
            'processing_started_at' => null,
            'processed_at' => $message->processedAt === null ? null : $this->format($message->processedAt),
            'failed_at' => null,
            'error_message' => $message->errorMessage,
            'last_error_code' => null,
            'created_at' => $this->format($message->receivedAt),
            'updated_at' => $this->format($message->receivedAt),
        ]);

        return $this->inboxFromRow([
            'id' => (int) $this->connection->lastInsertId(),
            'uuid' => $message->uuid,
            'source' => $message->source,
            'topic' => $message->topic,
            'payload_json' => TransportJson::encode($message->payload),
            'headers_json' => TransportJson::encode([]),
            'raw_message' => null,
            'status' => $message->status,
            'idempotency_key' => $message->idempotencyKey,
            'correlation_id' => $message->correlationId,
            'remote_message_id' => null,
            'received_ack_required' => 0,
            'processed_ack_required' => 0,
            'received_ack_sent_at' => null,
            'processed_ack_sent_at' => null,
            'attempt_count' => 0,
            'max_attempts' => 10,
            'next_attempt_at' => null,
            'received_at' => $this->format($message->receivedAt),
            'processing_started_at' => null,
            'processed_at' => null,
            'failed_at' => null,
            'error_message' => $message->errorMessage,
            'last_error_code' => null,
            'created_at' => $this->format($message->receivedAt),
            'updated_at' => $this->format($message->receivedAt),
        ]);
    }

    public function findInboxByIdempotency(string $source, string $topic, string $idempotencyKey): ?TransportInboxMessage
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM transport_inbox WHERE source = :source AND topic = :topic AND idempotency_key = :idempotency_key ORDER BY id ASC LIMIT 1',
            ['source' => $source, 'topic' => $topic, 'idempotency_key' => $idempotencyKey],
        );

        return $row === false ? null : $this->inboxFromRow($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function destinationFromRow(array $row): TransportDestination
    {
        $fallbacks = TransportJson::decode($row['fallback_destinations_json'] ?? null);

        return new TransportDestination(
            (string) $row['name'],
            (string) $row['driver'],
            $row['base_url'] !== null ? (string) $row['base_url'] : null,
            $row['endpoint'] !== null ? (string) $row['endpoint'] : null,
            (string) $row['auth_type'],
            $row['credentials_secret'] !== null ? (string) $row['credentials_secret'] : null,
            (bool) $row['enabled'],
            (int) $row['timeout_seconds'],
            TransportJson::decode($row['retry_policy_json'] ?? null),
            array_values(array_map('strval', is_array($fallbacks['destinations'] ?? null) ? $fallbacks['destinations'] : [])),
            $this->date((string) $row['created_at']),
            $this->date((string) $row['updated_at']),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function messageFromRow(array $row): TransportMessage
    {
        return new TransportMessage(
            isset($row['id']) ? (int) $row['id'] : null,
            (string) $row['uuid'],
            (string) $row['destination'],
            (string) $row['topic'],
            TransportJson::decode($row['payload_json'] ?? null),
            TransportJson::decode($row['headers_json'] ?? null),
            (string) $row['priority'],
            (string) $row['status'],
            (int) $row['attempt_count'],
            (int) $row['max_attempts'],
            $this->nullableDate($row['next_attempt_at'] ?? null),
            $this->nullableDate($row['last_attempt_at'] ?? null),
            $this->nullableDate($row['delivered_at'] ?? null),
            $this->nullableDate($row['failed_at'] ?? null),
            $row['error_message'] !== null ? (string) $row['error_message'] : null,
            $row['idempotency_key'] !== null ? (string) $row['idempotency_key'] : null,
            $row['correlation_id'] !== null ? (string) $row['correlation_id'] : null,
            $this->date((string) $row['created_at']),
            $this->date((string) $row['updated_at']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function messageToRow(TransportMessage $message): array
    {
        return [
            'uuid' => $message->uuid,
            'destination' => $message->destination,
            'topic' => $message->topic,
            'payload_json' => TransportJson::encode($message->payload),
            'headers_json' => TransportJson::encode($message->headers),
            'priority' => $message->priority,
            'status' => $message->status,
            'attempt_count' => $message->attemptCount,
            'max_attempts' => $message->maxAttempts,
            'next_attempt_at' => $message->nextAttemptAt === null ? null : $this->format($message->nextAttemptAt),
            'last_attempt_at' => $message->lastAttemptAt === null ? null : $this->format($message->lastAttemptAt),
            'delivered_at' => $message->deliveredAt === null ? null : $this->format($message->deliveredAt),
            'failed_at' => $message->failedAt === null ? null : $this->format($message->failedAt),
            'error_message' => $message->errorMessage,
            'idempotency_key' => $message->idempotencyKey,
            'correlation_id' => $message->correlationId,
            'created_at' => $this->format($message->createdAt),
            'updated_at' => $this->format($message->updatedAt),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function inboxFromRow(array $row): TransportInboxMessage
    {
        return new TransportInboxMessage(
            isset($row['id']) ? (int) $row['id'] : null,
            (string) $row['uuid'],
            (string) $row['source'],
            (string) $row['topic'],
            TransportJson::decode($row['payload_json'] ?? null),
            (string) $row['status'],
            $this->date((string) $row['received_at']),
            $this->nullableDate($row['processed_at'] ?? null),
            $row['idempotency_key'] !== null ? (string) $row['idempotency_key'] : null,
            $row['correlation_id'] !== null ? (string) $row['correlation_id'] : null,
            $row['error_message'] !== null ? (string) $row['error_message'] : null,
        );
    }

    private function format(\DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    private function date(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value);
    }

    private function nullableDate(mixed $value): ?\DateTimeImmutable
    {
        return $value === null || $value === '' ? null : new \DateTimeImmutable((string) $value);
    }

    private function clip(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 2000);
    }
}
