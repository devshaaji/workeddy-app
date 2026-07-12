<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Authorization;

use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Authorization\PermissionDefinition;

final class ReportingPermissionDefinitionProvider implements IPermissionDefinitionProvider
{
    public function definitions(): array
    {
        return [
            new PermissionDefinition(ReportingPermissions::VIEW, 'View Reports', 'Can view management reporting dashboards.', 'reporting', 'read'),
            new PermissionDefinition(ReportingPermissions::SYSTEM_VIEW, 'View System Reports', 'Can view system-wide reporting dashboards and operational summaries.', 'reporting', 'read', systemOnly: true),
            new PermissionDefinition(ReportingPermissions::SETTINGS, 'Manage Reporting Settings', 'Can manage reporting settings.', 'reporting', 'admin'),
        ];
    }
}
