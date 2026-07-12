<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Finance\Authorization;

use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Authorization\PermissionDefinition;

final class FinancePermissionDefinitionProvider implements IPermissionDefinitionProvider
{
    public function definitions(): array
    {
        return [
            new PermissionDefinition(FinancePermissions::VIEW, 'View Finance', 'Can view finance dashboards and summaries.', 'finance', 'read', systemOnly: true),
            new PermissionDefinition(FinancePermissions::MANAGE, 'Manage Finance', 'Can manage finance operations.', 'finance', 'write', systemOnly: true),
            new PermissionDefinition(FinancePermissions::SETTINGS, 'Manage Finance Settings', 'Can manage finance settings.', 'finance', 'admin', systemOnly: true),
        ];
    }
}
