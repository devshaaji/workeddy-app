<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Contracts;

use WorkEddy\Modules\Notification\Domain\NotificationDeliveryLog;

interface NotificationLogRepositoryInterface
{
    public function save(NotificationDeliveryLog $log): void;

    public function findById(int $id): ?NotificationDeliveryLog;

    public function findByUuid(string $uuid): ?NotificationDeliveryLog;

    /**
     * @return array{data: list<NotificationDeliveryLog>, total: int}
     */
    public function paginate(int $limit = 50, int $offset = 0): array;
}
