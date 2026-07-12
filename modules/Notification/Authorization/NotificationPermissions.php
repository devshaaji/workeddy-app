<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Authorization;

final class NotificationPermissions
{
    public const LOG_VIEW = 'notification.log.view';
    public const LOG_RETRY = 'notification.log.retry';
    public const TEMPLATE_VIEW = 'notification.template.view';
    public const TEMPLATE_MANAGE = 'notification.template.manage';
    public const SETTINGS_MANAGE = 'notification.settings.manage';
}
