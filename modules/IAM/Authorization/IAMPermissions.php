<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Authorization;

final class IAMPermissions
{
    public const USER_CREATE = 'iam.user.create';
    public const USER_VIEW = 'iam.user.view';
    public const USER_UPDATE = 'iam.user.update';
    public const USER_SUSPEND = 'iam.user.suspend';
    public const USER_ACTIVATE = 'iam.user.activate';
    public const USER_PASSWORD_CHANGE = 'iam.user.password.change';
    public const USER_PASSWORD_RESET = 'iam.user.password.reset';

    public const ROLE_ASSIGN = 'iam.role.assign';
    public const ROLE_MANAGE = 'iam.role.manage';
    public const PERMISSION_ASSIGN = 'iam.permission.assign';
    public const PERMISSION_SYNC = 'iam.permission.sync';

    public const SETTINGS_MANAGE = 'iam.settings.manage';
}
