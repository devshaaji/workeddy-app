<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Contracts;

use WorkEddy\Modules\Notification\Domain\NotificationMessage;
use WorkEddy\Modules\Notification\Domain\NotificationProviderResult;

interface NotificationProviderInterface
{
    public function channel(): string;

    public function send(NotificationMessage $message): NotificationProviderResult;
}
