<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Contracts;

use WorkEddy\Modules\Notification\Domain\NotificationPreference;

interface NotificationPreferenceRepositoryInterface
{
    public function findForRecipient(string $recipientType, string $recipientId): NotificationPreference;

    /**
     * @param array<string, bool> $channels
     */
    public function saveForRecipient(string $recipientType, string $recipientId, array $channels): NotificationPreference;
}
