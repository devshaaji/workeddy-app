<?php

/**
 * PermissionService — concrete implementation of IPermissionService.
 *
 * The single authorization checkpoint.
 * All modules call requirePrivilege() through this contract.
 */

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\Services;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;

use WorkEddy\Platform\Authorization\PermissionDefinition;
use WorkEddy\Platform\Logging\ILoggerFactory;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\ForbiddenException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class PermissionService implements IPermissionService
{
    private LoggerInterface $logger;

    /** @var array<int, \WorkEddy\Modules\IAM\Domain\User|null> */
    private array $usersById = [];

    /** @var array<string, string[]> */
    private array $effectivePrivilegesByContext = [];

    public function __construct(
        private readonly ?IPermissionRepository $permissionRepository = null,
        private readonly ?IUserRepository $userRepository = null,

        ?ILoggerFactory $loggerFactory = null,
    ) {
        $this->logger = $loggerFactory?->channel('security') ?? new NullLogger();
    }

    /**
     * Assert that the user holds a specific privilege.
     *
     * UserContext carries a flat privilege list resolved at login time.
     * This is O(n) on array but privilege lists are small (~20-50 items).
     *
     * @throws ForbiddenException If privilege not held.
     */
    public function requirePrivilege(UserContext $ctx, string $privilege): void
    {
        $this->assertAccountIsActive($ctx);

        $required = $this->canonicalize($privilege);

        if (!$this->hasPrivilege($ctx, $required)) {
            $this->logger->warning('Authorization denied: missing privilege.', [
                'userId' => $ctx->userId,
                'roleType' => $ctx->roleType,
                'requiredPrivilege' => $required,
            ]);

            throw new ForbiddenException(
                "Insufficient privileges. Required: '{$required}'."
            );
        }
    }


    private function hasPrivilege(UserContext $ctx, string $required): bool
    {
        foreach ($this->resolveEffectivePrivileges($ctx) as $held) {
            if ($this->canonicalize($held) === $required) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    private function resolveEffectivePrivileges(UserContext $ctx): array
    {
        $roleId = $this->resolveRoleId($ctx);
        $cacheKey = $ctx->userId . ':' . $roleId;
        if (array_key_exists($cacheKey, $this->effectivePrivilegesByContext)) {
            return $this->effectivePrivilegesByContext[$cacheKey];
        }

        if ($this->permissionRepository === null) {
            return $this->effectivePrivilegesByContext[$cacheKey] = $ctx->privileges;
        }

        try {
            return $this->effectivePrivilegesByContext[$cacheKey] = $this->permissionRepository->resolveEffectivePermissions($ctx->userId, $roleId);
        } catch (\Throwable) {
            return $this->effectivePrivilegesByContext[$cacheKey] = $ctx->privileges;
        }
    }

    private function resolveRoleId(UserContext $ctx): int
    {
        if ($ctx->roleId > 0) {
            return $ctx->roleId;
        }

        if ($this->userRepository === null) {
            return 0;
        }

        $user = $this->userForContext($ctx);
        return $user !== null ? (int) $user->getEffectiveRoleId() : 0;
    }

    private function canonicalize(string $permission): string
    {
        return PermissionDefinition::normalizeKey($permission);
    }

    private function assertAccountIsActive(UserContext $ctx): void
    {
        if ($this->userRepository === null) {
            return;
        }

        $user = $this->userForContext($ctx);
        if ($user === null || !$user->isActive()) {
            $this->logger->warning('Authorization denied: account is not active.', [
                'userId' => $ctx->userId,
                'roleType' => $ctx->roleType,
                'accountFound' => $user !== null,
                'status' => $user?->getStatus()->value,
            ]);

            throw new ForbiddenException('Account is not active for authorization checks.');
        }
    }

    private function userForContext(UserContext $ctx): ?\WorkEddy\Modules\IAM\Domain\User
    {
        if ($this->userRepository === null) {
            return null;
        }

        if (!array_key_exists($ctx->userId, $this->usersById)) {
            $this->usersById[$ctx->userId] = $this->userRepository->findById($ctx->userId);
        }

        return $this->usersById[$ctx->userId];
    }
}
