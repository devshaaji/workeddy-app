<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Domain;

final class NotificationType
{
    public function __construct(
        public readonly string $value
    ) {}
}
