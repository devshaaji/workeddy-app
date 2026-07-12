<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Contracts;

use WorkEddy\Modules\Notification\Domain\NotificationChannel;
use WorkEddy\Modules\Notification\Domain\NotificationType;

interface TemplateRendererInterface
{
    public function render(NotificationType $type, NotificationChannel $channel, array $data): string;

    public function getSubject(NotificationType $type, NotificationChannel $channel, array $data): ?string;
}
