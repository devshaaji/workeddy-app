<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Authorization;

final class OrganizationPermissions
{
    public const VIEW = 'organization.view';
    public const MANAGE = 'organization.manage';
    public const MEMBERS_MANAGE = 'organization.members.manage';
    public const STRUCTURE_MANAGE = 'organization.structure.manage';
}
