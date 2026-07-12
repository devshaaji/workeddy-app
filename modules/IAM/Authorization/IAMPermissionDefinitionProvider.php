<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Authorization;

use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Authorization\PermissionDefinition;

final class IAMPermissionDefinitionProvider implements IPermissionDefinitionProvider
{
    public function module(): string
    {
        return 'iam';
    }

    public function definitions(): array
    {
        return [
            new PermissionDefinition(IAMPermissions::USER_CREATE, 'Create users', 'Create IAM user accounts.', 'iam', 'write', 'high'),
            new PermissionDefinition(IAMPermissions::USER_VIEW, 'View users', 'View IAM user profiles and effective permissions.', 'iam', 'read', 'medium'),
            new PermissionDefinition(IAMPermissions::USER_UPDATE, 'Update users', 'Update IAM user profile details.', 'iam', 'write', 'high'),
            new PermissionDefinition(IAMPermissions::USER_SUSPEND, 'Suspend users', 'Suspend user access to all protected actions.', 'iam', 'admin', 'critical'),
            new PermissionDefinition(IAMPermissions::USER_ACTIVATE, 'Activate users', 'Activate suspended or pending user accounts.', 'iam', 'admin', 'high'),
            new PermissionDefinition(IAMPermissions::USER_PASSWORD_CHANGE, 'Change own password', 'Change password for currently authenticated user.', 'iam', 'write', 'medium'),
            new PermissionDefinition(IAMPermissions::USER_PASSWORD_RESET, 'Reset user password', 'Reset password for another user account.', 'iam', 'admin', 'critical'),
            new PermissionDefinition(IAMPermissions::ROLE_ASSIGN, 'Assign roles', 'Assign role to IAM users.', 'iam', 'admin', 'critical'),
            new PermissionDefinition(IAMPermissions::ROLE_MANAGE, 'Manage roles', 'Create, edit, and delete roles.', 'iam', 'admin', 'critical'),
            new PermissionDefinition(IAMPermissions::PERMISSION_ASSIGN, 'Assign permissions', 'Assign permissions to roles.', 'iam', 'admin', 'critical'),
            new PermissionDefinition(IAMPermissions::PERMISSION_SYNC, 'Sync permission catalog', 'Synchronize code-owned module permissions into IAM persistence.', 'iam', 'system', 'high', [], true),
            new PermissionDefinition(IAMPermissions::SETTINGS_MANAGE, 'Manage platform settings', 'Update cross-module runtime settings.', 'iam', 'admin', 'critical'),
        ];
    }
}
