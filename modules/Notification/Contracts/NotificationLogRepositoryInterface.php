<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Contracts;

use WorkEddy\Modules\Notification\Domain\NotificationDeliveryLog;
use WorkEddy\Modules\Notification\Domain\NotificationDeliveryAttempt;

interface NotificationLogRepositoryInterface
{
    public function save(NotificationDeliveryLog $log): void;

    public function findById(int $id): ?NotificationDeliveryLog;

    public function findByUuid(string $uuid): ?NotificationDeliveryLog;

    public function saveAttempt(NotificationDeliveryAttempt $attempt): void;

    /**
     * @return list<NotificationDeliveryAttempt>
     */
    public function findAttemptsByLogUuid(string $logUuid): array;

    /**
     * @return array{data: list<NotificationDeliveryLog>, total: int}
     */
    public function paginate(int $limit = 50, int $offset = 0): array;
}
