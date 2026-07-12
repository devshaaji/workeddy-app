<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Audit\Authorization;

use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Authorization\PermissionDefinition;

final class AuditPermissionDefinitionProvider implements IPermissionDefinitionProvider
{
    public function module(): string
    {
        return 'audit';
    }

    public function definitions(): array
    {
        $auditOperators = ['organization_admin', 'org_admin', 'safety_manager'];

        return [
            new PermissionDefinition('audit', AuditPermissions::VIEW, 'View audit logs', 'Query audit trail records.', 'read', 'high', ['super_admin', 'admin', ...$auditOperators]),
            new PermissionDefinition('audit', AuditPermissions::EXPORT, 'Export audit logs', 'Export audit trail records for compliance.', 'read', 'high', ['super_admin', 'admin', ...$auditOperators]),
            new PermissionDefinition('audit', AuditPermissions::RECORD, 'Record audit events', 'System-only permission for internal audit writes.', 'system', 'medium', ['super_admin'], true),
            new PermissionDefinition('audit', AuditPermissions::SETTINGS_MANAGE, 'Manage audit settings', 'Update audit retention and query policy settings.', 'admin', 'high', ['super_admin', 'admin', 'organization_admin', 'org_admin']),
            new PermissionDefinition('audit', AuditPermissions::REPORT_VIEW, 'View reports', 'View platform dashboard and system summary reports.', 'read', 'medium', ['super_admin', 'admin', 'viewer']),
        ];
    }
}
