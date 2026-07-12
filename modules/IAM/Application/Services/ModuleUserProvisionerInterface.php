<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\Services;

use WorkEddy\Modules\IAM\Domain\User;

interface ModuleUserProvisionerInterface
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function provisionPendingUser(
        string $sourceModule,
        string $sourceType,
        string $sourceId,
        string $email,
        string $fullName,
        ?string $phone = null,
        ?string $roleSlug = null,
        ?string $actorId = null,
        array $metadata = [],
    ): User;

    /**
     * @param array<string, mixed> $metadata
     */
    public function provisionInvitedUser(
        string $sourceModule,
        string $sourceType,
        string $sourceId,
        string $email,
        string $fullName,
        ?string $phone,
        string $roleSlug,
        ?string $actorId = null,
        array $metadata = [],
        string $requiredRoleScope = 'customer',
        ?string $organizationUuid = null,
    ): User;
}
