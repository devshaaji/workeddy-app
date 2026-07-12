<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Contracts;

use WorkEddy\Modules\Notification\Domain\NotificationDispatchResult;
use WorkEddy\Modules\Notification\Domain\NotificationRequest;

interface NotificationServiceInterface
{
    public function send(NotificationRequest $request): NotificationDispatchResult;
}
