<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Domain;

enum NotificationChannel: string
{
    case IN_APP = 'inapp';
    case EMAIL = 'email';
    case SMS = 'sms';
    case WHATSAPP = 'whatsapp';
}
