<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\DTOs;

/** Login result DTO returned after successful authentication. */
final class LoginResult
{
    /**
     * @param string[] $privileges
     */
    public function __construct(
        public int    $userId,
        public string $userUuid,
        public string $email,
        public string $fullName,
        public int    $roleId,
        public string $roleSlug,
        public array  $privileges,
        public string $loginAt,
        public string $tenantId = 'platform',
        public ?int $organizationId = null,
        public ?string $organizationUuid = null,
        public ?int $membershipId = null,
        public ?string $membershipUuid = null,
        public int $platformRoleId = 0,
        public string $platformRoleSlug = '',
        public ?int $membershipRoleId = null,
        public ?string $membershipRoleSlug = null,
        public int $authzVersion = 1,
        public bool $authenticated = true,
        public bool $requiresOtp = false,
        public ?int $otpExpiresInMinutes = null,
        public ?string $otpCode = null,
    ) {}
}
