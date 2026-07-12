<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Task\Authorization;

use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Authorization\PermissionDefinition;

final class TaskPermissionDefinitionProvider implements IPermissionDefinitionProvider
{
    public function module(): string
    {
        return 'task';
    }

    public function definitions(): array
    {
        $organizationAdmins = ['organization_admin', 'org_admin'];

        return [
            new PermissionDefinition(TaskPermissions::VIEW, 'View tasks', 'View organization task definitions.', 'task', 'read', 'medium', [...$organizationAdmins, 'safety_manager', 'supervisor', 'worker', 'external_reviewer']),
            new PermissionDefinition(TaskPermissions::CREATE, 'Create tasks', 'Create task definitions within an organization.', 'task', 'write', 'high', [...$organizationAdmins, 'safety_manager']),
            new PermissionDefinition(TaskPermissions::UPDATE, 'Update tasks', 'Update task definitions within an organization.', 'task', 'write', 'high', [...$organizationAdmins, 'safety_manager', 'supervisor']),
        ];
    }
}
