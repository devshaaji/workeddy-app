<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Authorization;

use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Authorization\PermissionDefinition;

final class OrganizationPermissionDefinitionProvider implements IPermissionDefinitionProvider
{
    public function module(): string
    {
        return 'organization';
    }

    public function definitions(): array
    {
        $organizationAdmins = ['organization_admin', 'org_admin'];

        return [
            new PermissionDefinition(OrganizationPermissions::VIEW, 'View organizations', 'View organization structures and memberships.', 'organization', 'read', 'medium', [...$organizationAdmins, 'safety_manager', 'supervisor', 'external_reviewer']),
            new PermissionDefinition(OrganizationPermissions::MANAGE, 'Manage organizations', 'Create and update organization records.', 'organization', 'write', 'high', $organizationAdmins),
            new PermissionDefinition(OrganizationPermissions::MEMBERS_MANAGE, 'Manage organization members', 'Invite and update organization memberships.', 'organization', 'admin', 'high', [...$organizationAdmins, 'safety_manager']),
            new PermissionDefinition(OrganizationPermissions::STRUCTURE_MANAGE, 'Manage organization structure', 'Manage worksites, departments, and job roles.', 'organization', 'write', 'high', [...$organizationAdmins, 'safety_manager']),
        ];
    }
}
