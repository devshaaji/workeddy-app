<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Infrastructure\Persistence;

use WorkEddy\Modules\Notification\Contracts\NotificationPreferenceRepositoryInterface;
use WorkEddy\Modules\Notification\Domain\NotificationPreference;
use WorkEddy\Platform\Clock\IClock;
use Doctrine\DBAL\Connection;

final class DbalNotificationPreferenceRepository implements NotificationPreferenceRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly IClock $clock,
    ) {}

    public function findForRecipient(string $recipientType, string $recipientId): NotificationPreference
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM notification_preferences WHERE recipient_type = :type AND recipient_id = :id LIMIT 1',
            ['type' => $recipientType, 'id' => $recipientId],
        );

        if ($row === false) {
            return NotificationPreference::defaults($recipientType, $recipientId);
        }

        return $this->hydrate($row);
    }

    public function saveForRecipient(string $recipientType, string $recipientId, array $channels): NotificationPreference
    {
        $existing = $this->connection->fetchAssociative(
            'SELECT * FROM notification_preferences WHERE recipient_type = :type AND recipient_id = :id LIMIT 1',
            ['type' => $recipientType, 'id' => $recipientId],
        );

        $data = [
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'in_app_enabled' => 1,
            'email_enabled' => !empty($channels['email']) ? 1 : 0,
            'sms_enabled' => !empty($channels['sms']) ? 1 : 0,
            'whatsapp_enabled' => !empty($channels['whatsapp']) ? 1 : 0,
            'updated_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ];

        if ($existing === false) {
            $data['created_at'] = $this->clock->now()->format('Y-m-d H:i:s');
            $this->connection->insert('notification_preferences', $data);
        } else {
            $this->connection->update(
                'notification_preferences',
                $data,
                ['id' => (int) $existing['id']],
            );
        }

        return $this->findForRecipient($recipientType, $recipientId);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): NotificationPreference
    {
        return new NotificationPreference(
            recipientType: (string) $row['recipient_type'],
            recipientId: (string) $row['recipient_id'],
            inAppEnabled: true,
            emailEnabled: (bool) $row['email_enabled'],
            smsEnabled: (bool) $row['sms_enabled'],
            whatsAppEnabled: (bool) $row['whatsapp_enabled'],
            createdAt: isset($row['created_at']) ? new \DateTimeImmutable((string) $row['created_at']) : null,
            updatedAt: isset($row['updated_at']) ? new \DateTimeImmutable((string) $row['updated_at']) : null,
            id: isset($row['id']) ? (int) $row['id'] : null,
        );
    }
}
