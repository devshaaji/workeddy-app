<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\DTOs;

/**
 * User DTO — cross-module readable representation.
 *
 * Never expose domain entities across module boundaries.
 */
final class UserDTO
{
    /**
     * @param string[] $permissions
     */
    public function __construct(
        public string  $id,
        public string  $email,
        public string  $fullName,
        public string  $roleSlug,
        public string  $roleName,
        public string  $status,
        public ?string $phone,
        public array   $permissions,
        public ?string $lastLoginAt,
        public string  $createdAt,
        public ?string $organizationId = null,
        public ?string $organizationName = null,
        public ?string $membershipId = null,
        public ?string $worksiteId = null,
        public ?string $departmentId = null,
        public ?string $jobRoleId = null,
        public int     $authzVersion = 1,
    ) {}
}
