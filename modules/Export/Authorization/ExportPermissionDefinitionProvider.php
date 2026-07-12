<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Export\Authorization;

use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Authorization\PermissionDefinition;

final class ExportPermissionDefinitionProvider implements IPermissionDefinitionProvider
{
    public function module(): string
    {
        return 'export';
    }

    public function definitions(): array
    {
        $orgAdmins = ['organization_admin', 'org_admin'];

        return [
            new PermissionDefinition(ExportPermissions::VIEW, 'View research exports', 'View de-identified research export registers and history.', 'export', 'read', 'high', [...$orgAdmins, 'safety_manager', 'external_reviewer']),
            new PermissionDefinition(ExportPermissions::PREVIEW, 'Preview research export fields', 'Preview de-identified research export columns before generation.', 'export', 'read', 'high', [...$orgAdmins, 'safety_manager', 'external_reviewer']),
            new PermissionDefinition(ExportPermissions::GENERATE, 'Generate research exports', 'Generate de-identified research export datasets.', 'export', 'write', 'critical', [...$orgAdmins, 'safety_manager']),
            new PermissionDefinition(ExportPermissions::DOWNLOAD, 'Download research exports', 'Download signed de-identified research export files.', 'export', 'read', 'critical', [...$orgAdmins, 'safety_manager', 'external_reviewer']),
        ];
    }
}
