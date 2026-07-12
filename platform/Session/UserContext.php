<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Session;

final class UserContext
{
    public readonly string $tenantId;
    public readonly int|string $userId;
    public readonly int $roleId;
    public readonly ?int $organizationId;
    public readonly ?string $organizationUuid;
    public readonly ?int $membershipId;
    public readonly ?string $membershipUuid;
    public readonly int $platformRoleId;
    public readonly string $platformRoleType;
    public readonly ?int $membershipRoleId;
    public readonly ?string $membershipRoleType;
    /** @var list<string> */
    public readonly array $roles;
    /** @var list<string> */
    public readonly array $permissions;
    public readonly string $loginAt;
    public readonly ?string $sessionId;
    public readonly string $roleType;
    /** @var list<string> */
    public readonly array $privileges;
    public readonly int $authzVersion;

    /**
     * @param list<string> $permissions
     * @param list<string> $roles
     * @param list<string>|null $privileges
     */
    public function __construct(
        string $tenantId = 'platform',
        int|string $userId = '',
        int $roleId = 0,
        ?int $organizationId = null,
        ?string $organizationUuid = null,
        ?int $membershipId = null,
        ?string $membershipUuid = null,
        int $platformRoleId = 0,
        ?string $platformRoleType = null,
        ?int $membershipRoleId = null,
        ?string $membershipRoleType = null,
        array $roles = [],
        array $permissions = [],
        string $loginAt = '',
        ?string $sessionId = null,
        ?string $roleType = null,
        ?array $privileges = null,
        int $authzVersion = 1,
    ) {
        $resolvedPermissions = $permissions !== [] ? $permissions : ($privileges ?? []);
        $resolvedRoles = $roles !== [] ? $roles : ($roleType !== null && $roleType !== '' ? [$roleType] : []);

        $this->tenantId = $tenantId;
        $this->userId = $userId;
        $this->roleId = $roleId;
        $this->organizationId = $organizationId;
        $this->organizationUuid = $organizationUuid;
        $this->membershipId = $membershipId;
        $this->membershipUuid = $membershipUuid;
        $this->platformRoleId = $platformRoleId;
        $this->platformRoleType = $platformRoleType ?? '';
        $this->membershipRoleId = $membershipRoleId;
        $this->membershipRoleType = $membershipRoleType;
        $this->roles = array_values($resolvedRoles);
        $this->permissions = array_values($resolvedPermissions);
        $this->loginAt = $loginAt !== '' ? $loginAt : (new \DateTimeImmutable())->format('c');
        $this->sessionId = $sessionId;
        $this->roleType = $roleType ?? (string) ($this->roles[0] ?? '');
        $this->privileges = array_values($resolvedPermissions);
        $this->authzVersion = $authzVersion;
    }

    public function hasPrivilege(string $permission): bool
    {
        return in_array($permission, $this->privileges, true);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->hasPrivilege($permission);
    }
}
