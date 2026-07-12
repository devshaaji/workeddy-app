<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Privacy\Authorization;

use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Authorization\PermissionDefinition;

final class PrivacyPermissionDefinitionProvider implements IPermissionDefinitionProvider
{
    public function module(): string
    {
        return 'privacy';
    }

    public function definitions(): array
    {
        $organizationAdmins = ['organization_admin', 'org_admin'];

        return [
            new PermissionDefinition(PrivacyPermissions::CONSENT_RECORD, 'Record privacy consent', 'Record privacy and video consent decisions.', 'privacy', 'write', 'medium', [...$organizationAdmins, 'safety_manager', 'supervisor', 'worker']),
            new PermissionDefinition(PrivacyPermissions::VIDEO_ACCESS, 'Access privacy-controlled video', 'Access privacy-controlled video evidence.', 'privacy', 'read', 'high', [...$organizationAdmins, 'safety_manager']),
            new PermissionDefinition(PrivacyPermissions::RETENTION_MANAGE, 'Manage retention policies', 'Manage organization retention policies.', 'privacy', 'write', 'high', $organizationAdmins),
            new PermissionDefinition(PrivacyPermissions::RETENTION_ENFORCE, 'Enforce retention policies', 'Apply retention policies to stored evidence.', 'privacy', 'write', 'high', [...$organizationAdmins, 'safety_manager']),
            new PermissionDefinition(PrivacyPermissions::AUDIT_VIEW, 'View privacy audit logs', 'View privacy access logs and consent history.', 'privacy', 'read', 'medium', [...$organizationAdmins, 'safety_manager']),
        ];
    }
}
