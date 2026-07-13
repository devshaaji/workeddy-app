<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Infrastructure\Persistence;

use WorkEddy\Modules\Notification\Contracts\NotificationLogRepositoryInterface;
use WorkEddy\Modules\Notification\Domain\NotificationChannel;
use WorkEddy\Modules\Notification\Domain\NotificationDeliveryAttempt;
use WorkEddy\Modules\Notification\Domain\NotificationDeliveryLog;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Shared\Support\DateFormatter;
use Doctrine\DBAL\Connection;

final class DbalNotificationLogRepository implements NotificationLogRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly IClock $clock,
    ) {}

    public function save(NotificationDeliveryLog $log): void
    {
        $data = [
            'uuid' => $log->uuid,
            'notification_type' => $log->notificationType,
            'recipient_type' => $log->recipientType,
            'recipient_id' => $log->recipientId,
            'recipient_name' => $log->recipientName,
            'recipient_email' => $log->recipientEmail,
            'recipient_phone' => $log->recipientPhone,
            'channel' => $log->channel->value,
            'provider' => $log->provider,
            'subject' => $log->subject,
            'message_preview' => $log->messagePreview,
            'status' => $log->status,
            'attempt_count' => $log->attemptCount,
            'failure_reason' => $log->failureReason,
            'failure_type' => $log->failureType?->value,
            'provider_message_id' => $log->providerMessageId,
            'metadata_json' => json_encode($log->metadataJson),
            'queued_at' => $log->queuedAt?->format('Y-m-d H:i:s'),
            'sent_at' => $log->sentAt?->format('Y-m-d H:i:s'),
            'failed_at' => $log->failedAt?->format('Y-m-d H:i:s'),
            'updated_at' => ($this->clock->now())->format('Y-m-d H:i:s'),
        ];

        if ($log->id === null) {
            $data['created_at'] = ($this->clock->now())->format('Y-m-d H:i:s');
            $this->connection->insert('notification_logs', $data);
        } else {
            $this->connection->update('notification_logs', $data, ['id' => $log->id]);
        }
    }

    public function findById(int $id): ?NotificationDeliveryLog
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM notification_logs WHERE id = ?', [$id]);
        return $row ? $this->mapRowToLog($row) : null;
    }

    public function findByUuid(string $uuid): ?NotificationDeliveryLog
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM notification_logs WHERE uuid = ?', [$uuid]);
        return $row ? $this->mapRowToLog($row) : null;
    }

    public function saveAttempt(NotificationDeliveryAttempt $attempt): void
    {
        $data = [
            'uuid' => $attempt->uuid,
            'log_uuid' => $attempt->logUuid,
            'channel' => $attempt->channel->value,
            'provider_key' => $attempt->providerKey,
            'attempt_count' => $attempt->attemptCount,
            'status' => $attempt->status,
            'failure_reason' => $attempt->failureReason,
            'failure_type' => $attempt->failureType?->value,
            'provider_message_id' => $attempt->providerMessageId,
            'updated_at' => ($this->clock->now())->format('Y-m-d H:i:s'),
        ];

        if ($attempt->id === null) {
            $data['created_at'] = ($this->clock->now())->format('Y-m-d H:i:s');
            $this->connection->insert('notification_log_attempts', $data);
            return;
        }

        $this->connection->update('notification_log_attempts', $data, ['id' => $attempt->id]);
    }

    public function findAttemptsByLogUuid(string $logUuid): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('notification_log_attempts')
            ->where('log_uuid = :log_uuid')
            ->setParameter('log_uuid', $logUuid)
            ->orderBy('id', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map([$this, 'mapRowToAttempt'], $rows);
    }

    public function paginate(int $limit = 50, int $offset = 0): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from('notification_logs')
            ->orderBy('id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $rows = $qb->executeQuery()->fetchAllAssociative();
        $data = array_map([$this, 'mapRowToLog'], $rows);

        $totalQb = $this->connection->createQueryBuilder();
        $totalQb->select('COUNT(id)')
            ->from('notification_logs');
        $total = (int) $totalQb->executeQuery()->fetchOne();

        return [
            'data' => $data,
            'total' => $total,
        ];
    }

    private function mapRowToLog(array $row): NotificationDeliveryLog
    {
        return new NotificationDeliveryLog(
            uuid: $row['uuid'],
            notificationType: $row['notification_type'],
            recipientType: $row['recipient_type'],
            recipientId: $row['recipient_id'],
            channel: NotificationChannel::from($row['channel']),
            provider: $row['provider'],
            status: $row['status'],
            subject: $row['subject'],
            messagePreview: $row['message_preview'],
            recipientName: $row['recipient_name'],
            recipientEmail: $row['recipient_email'],
            recipientPhone: $row['recipient_phone'],
            attemptCount: (int) $row['attempt_count'],
            failureReason: $row['failure_reason'],
            failureType: isset($row['failure_type']) ? \WorkEddy\Modules\Notification\Infrastructure\Clients\FailureType::from($row['failure_type']) : null,
            providerMessageId: $row['provider_message_id'] ?? null,
            metadataJson: json_decode($row['metadata_json'] ?? '{}', true) ?: [],
            queuedAt: $row['queued_at'] ? DateFormatter::fromNaiveDbString($row['queued_at']) : null,
            sentAt: $row['sent_at'] ? DateFormatter::fromNaiveDbString($row['sent_at']) : null,
            failedAt: $row['failed_at'] ? DateFormatter::fromNaiveDbString($row['failed_at']) : null,
            id: (int) $row['id'],
            createdAt: DateFormatter::fromNaiveDbString($row['created_at'] ?? null),
            updatedAt: DateFormatter::fromNaiveDbString($row['updated_at'] ?? null)
        );
    }

    private function mapRowToAttempt(array $row): NotificationDeliveryAttempt
    {
        return new NotificationDeliveryAttempt(
            uuid: $row['uuid'],
            logUuid: $row['log_uuid'],
            channel: NotificationChannel::from($row['channel']),
            providerKey: $row['provider_key'],
            attemptCount: (int) $row['attempt_count'],
            status: $row['status'],
            failureReason: $row['failure_reason'],
            failureType: isset($row['failure_type']) ? \WorkEddy\Modules\Notification\Infrastructure\Clients\FailureType::from($row['failure_type']) : null,
            providerMessageId: $row['provider_message_id'] ?? null,
            createdAt: DateFormatter::fromNaiveDbString($row['created_at'] ?? null),
            id: (int) $row['id'],
        );
    }
}
