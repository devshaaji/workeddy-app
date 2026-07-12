<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Contracts;

use WorkEddy\Modules\Notification\Domain\InAppNotification;

interface InAppNotificationRepositoryInterface
{
    public function save(InAppNotification $notification): InAppNotification;

    /**
     * @return array{data: list<InAppNotification>, total: int}
     */
    public function paginateForRecipient(string $recipientType, string $recipientId, int $limit, int $offset, bool $unreadOnly = false): array;

    public function markRead(string $uuid, string $recipientType, string $recipientId): ?InAppNotification;
}
