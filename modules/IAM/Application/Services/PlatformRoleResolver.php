<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\Services;

use WorkEddy\Modules\IAM\Domain\Contracts\IRoleRepository;
use WorkEddy\Modules\IAM\Domain\Role;

final class PlatformRoleResolver
{
    private const PREFERRED_PLATFORM_ROLE_SLUGS = ['member', 'staff', 'operator'];

    public function __construct(private readonly IRoleRepository $roles)
    {
    }

    public function resolveBaseRole(Role $assignedRole): Role
    {
        if (strtolower($assignedRole->getScope()) !== 'customer') {
            return $assignedRole;
        }

        foreach (self::PREFERRED_PLATFORM_ROLE_SLUGS as $slug) {
            $role = $this->roles->findBySlug($slug);
            if ($role !== null && strtolower($role->getScope()) !== 'customer') {
                return $role;
            }
        }

        return $assignedRole;
    }
}
