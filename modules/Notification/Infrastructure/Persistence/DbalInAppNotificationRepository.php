<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Infrastructure\Persistence;

use WorkEddy\Modules\Notification\Contracts\InAppNotificationRepositoryInterface;
use WorkEddy\Modules\Notification\Domain\InAppNotification;
use WorkEddy\Platform\Clock\IClock;
use Doctrine\DBAL\Connection;

final class DbalInAppNotificationRepository implements InAppNotificationRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly IClock $clock,
    ) {}

    public function save(InAppNotification $notification): InAppNotification
    {
        $data = [
            'uuid' => $notification->uuid,
            'recipient_type' => $notification->recipientType,
            'recipient_id' => $notification->recipientId,
            'notification_type' => $notification->notificationType,
            'subject' => $notification->subject,
            'body' => $notification->body,
            'metadata_json' => $notification->metadataJson !== null ? json_encode($notification->metadataJson) : null,
            'read_at' => $notification->readAt?->format('Y-m-d H:i:s'),
            'updated_at' => ($notification->updatedAt ?? $this->clock->now())->format('Y-m-d H:i:s'),
        ];

        if ($notification->id === null) {
            $data['created_at'] = ($notification->createdAt ?? $this->clock->now())->format('Y-m-d H:i:s');
            $this->connection->insert('notification_in_app_messages', $data);

            return new InAppNotification(
                uuid: $notification->uuid,
                recipientType: $notification->recipientType,
                recipientId: $notification->recipientId,
                notificationType: $notification->notificationType,
                subject: $notification->subject,
                body: $notification->body,
                metadataJson: $notification->metadataJson,
                readAt: $notification->readAt,
                createdAt: new \DateTimeImmutable($data['created_at']),
                updatedAt: new \DateTimeImmutable($data['updated_at']),
                id: (int) $this->connection->lastInsertId(),
            );
        }

        $this->connection->update(
            'notification_in_app_messages',
            $data,
            ['id' => $notification->id],
        );

        return $notification;
    }

    public function paginateForRecipient(string $recipientType, string $recipientId, int $limit, int $offset, bool $unreadOnly = false): array
    {
        $where = 'recipient_type = :type AND recipient_id = :id';
        if ($unreadOnly) {
            $where .= ' AND read_at IS NULL';
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM notification_in_app_messages WHERE ' . $where . ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset',
            ['type' => $recipientType, 'id' => $recipientId, 'limit' => $limit, 'offset' => $offset],
            ['limit' => \PDO::PARAM_INT, 'offset' => \PDO::PARAM_INT],
        );

        $total = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM notification_in_app_messages WHERE ' . $where,
            ['type' => $recipientType, 'id' => $recipientId],
        );

        return [
            'data' => array_map(fn(array $row): InAppNotification => $this->hydrate($row), $rows),
            'total' => $total,
        ];
    }

    public function markRead(string $uuid, string $recipientType, string $recipientId): ?InAppNotification
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM notification_in_app_messages WHERE uuid = :uuid AND recipient_type = :type AND recipient_id = :id LIMIT 1',
            ['uuid' => $uuid, 'type' => $recipientType, 'id' => $recipientId],
        );

        if ($row === false) {
            return null;
        }

        $readAt = $this->clock->now();
        $this->connection->update(
            'notification_in_app_messages',
            ['read_at' => $readAt->format('Y-m-d H:i:s'), 'updated_at' => $readAt->format('Y-m-d H:i:s')],
            ['id' => (int) $row['id']],
        );

        $row['read_at'] = $readAt->format('Y-m-d H:i:s');
        $row['updated_at'] = $readAt->format('Y-m-d H:i:s');

        return $this->hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): InAppNotification
    {
        return new InAppNotification(
            uuid: (string) $row['uuid'],
            recipientType: (string) $row['recipient_type'],
            recipientId: (string) $row['recipient_id'],
            notificationType: (string) $row['notification_type'],
            subject: (string) $row['subject'],
            body: (string) $row['body'],
            metadataJson: isset($row['metadata_json']) && $row['metadata_json'] !== null
                ? (json_decode((string) $row['metadata_json'], true) ?: [])
                : null,
            readAt: isset($row['read_at']) && $row['read_at'] !== null ? new \DateTimeImmutable((string) $row['read_at']) : null,
            createdAt: isset($row['created_at']) ? new \DateTimeImmutable((string) $row['created_at']) : null,
            updatedAt: isset($row['updated_at']) ? new \DateTimeImmutable((string) $row['updated_at']) : null,
            id: isset($row['id']) ? (int) $row['id'] : null,
        );
    }
}
