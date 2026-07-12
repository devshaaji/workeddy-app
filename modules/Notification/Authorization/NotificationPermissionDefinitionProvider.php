<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Authorization;

use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Authorization\PermissionDefinition;

final class NotificationPermissionDefinitionProvider implements IPermissionDefinitionProvider
{
    public function module(): string
    {
        return 'notification';
    }

    public function definitions(): array
    {
        return [
            new PermissionDefinition(NotificationPermissions::LOG_VIEW,          'View notification logs',      'View notification delivery logs and status history.',     'notification', 'read',   'low',      ['super_admin', 'admin']),
            new PermissionDefinition(NotificationPermissions::LOG_RETRY,         'Retry failed notifications',  'Trigger a re-send for failed notification deliveries.',   'notification', 'write',  'medium',   ['super_admin', 'admin']),
            new PermissionDefinition(NotificationPermissions::TEMPLATE_VIEW,     'View notification templates', 'View notification template content and configuration.',   'notification', 'read',   'low',      ['super_admin', 'admin']),
            new PermissionDefinition(NotificationPermissions::TEMPLATE_MANAGE,   'Manage notification templates', 'Create, edit, and delete notification templates.',       'notification', 'write',  'medium',   ['super_admin', 'admin']),
            new PermissionDefinition(NotificationPermissions::SETTINGS_MANAGE,   'Manage notification settings', 'Configure SMTP, sender identity, and queue behaviour.',   'notification', 'admin',  'high',     ['super_admin']),
        ];
    }
}
