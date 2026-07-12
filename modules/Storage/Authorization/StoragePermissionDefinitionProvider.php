<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Storage\Authorization;

use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Authorization\PermissionDefinition;

final class StoragePermissionDefinitionProvider implements IPermissionDefinitionProvider
{
    public function module(): string
    {
        return 'Storage';
    }

    /** @return PermissionDefinition[] */
    public function definitions(): array
    {
        return [
            $this->permission(StoragePermissions::FILE_VIEW, 'View files', 'View stored file metadata and inline-safe files.', 'view', 'medium', ['program_manager', 'support_staff']),
            $this->permission(StoragePermissions::FILE_UPLOAD, 'Upload files', 'Upload files into managed storage.', 'create', 'medium', ['program_manager']),
            $this->permission(StoragePermissions::FILE_DOWNLOAD, 'Download files', 'Download private stored files.', 'download', 'high', ['program_manager']),
            $this->permission(StoragePermissions::FILE_DELETE, 'Delete files', 'Delete stored files and metadata.', 'delete', 'high', ['program_manager']),
            $this->permission(StoragePermissions::SETTINGS_MANAGE, 'Manage storage settings', 'Manage Storage module settings.', 'manage', 'high', ['program_manager']),
        ];
    }

    /** @param string[] $defaultAssignments */
    private function permission(
        string $key,
        string $label,
        string $description,
        string $actionCategory,
        string $risk,
        array $defaultAssignments = [],
    ): PermissionDefinition {
        return new PermissionDefinition($this->module(), $key, $label, $description, $actionCategory, $risk, $defaultAssignments);
    }
}
