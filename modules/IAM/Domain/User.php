<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Domain;

use WorkEddy\Shared\Exceptions\ConflictException;
use WorkEddy\Shared\Exceptions\ForbiddenException;

/**
 * User domain entity — aggregate root for IAM.
 *
 * Owns: authentication state, profile data, role assignment, account status.
 * Enforces domain invariants:
 *   - suspended users cannot login
 *   - password must be hashed before storage
 *   - status transitions follow legal pathways
 */
final class User
{
    private ?string $lastLoginAt;

    public function __construct(
        private int|string|null $id,
        private string          $uuid,
        private string          $email,
        private string          $fullName,
        private string          $passwordHash,
        private int|string      $roleId,
        private string          $roleSlug,
        private UserStatus      $status,
        private ?string         $phone = null,
        ?string                 $lastLoginAt = null,
        private int             $authzVersion = 1,
        private ?string         $createdAt = null,
        private ?string         $updatedAt = null,
        private ?int            $organizationId = null,
        private ?string         $organizationUuid = null,
        private ?string         $organizationName = null,
        private ?int            $membershipId = null,
        private ?string         $membershipUuid = null,
        private ?int            $membershipRoleId = null,
        private ?string         $membershipRoleSlug = null,
        private ?int            $worksiteId = null,
        private ?string         $worksiteUuid = null,
        private ?int            $departmentId = null,
        private ?string         $departmentUuid = null,
        private ?int            $jobRoleId = null,
        private ?string         $jobRoleUuid = null,
    ) {
        $this->lastLoginAt = $lastLoginAt;
    }

    // --- Getters ---
    public function getId(): int|null
    {
        return $this->id;
    }
    public function getUuid(): string
    {
        return $this->uuid;
    }
    public function getEmail(): string
    {
        return $this->email;
    }
    public function getFullName(): string
    {
        return $this->fullName;
    }
    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }
    public function getRoleId(): int|string
    {
        return $this->roleId;
    }
    public function getRoleSlug(): string
    {
        return $this->roleSlug;
    }
    public function getStatus(): UserStatus
    {
        return $this->status;
    }
    public function getPhone(): ?string
    {
        return $this->phone;
    }
    public function getLastLoginAt(): ?string
    {
        return $this->lastLoginAt;
    }
    public function getAuthzVersion(): int
    {
        return $this->authzVersion;
    }
    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }
    public function getOrganizationId(): ?int
    {
        return $this->organizationId;
    }
    public function getOrganizationUuid(): ?string
    {
        return $this->organizationUuid;
    }
    public function getOrganizationName(): ?string
    {
        return $this->organizationName;
    }
    public function getMembershipId(): ?int
    {
        return $this->membershipId;
    }
    public function getMembershipUuid(): ?string
    {
        return $this->membershipUuid;
    }
    public function getMembershipRoleId(): ?int
    {
        return $this->membershipRoleId;
    }
    public function getMembershipRoleSlug(): ?string
    {
        return $this->membershipRoleSlug;
    }
    public function getWorksiteId(): ?int
    {
        return $this->worksiteId;
    }
    public function getWorksiteUuid(): ?string
    {
        return $this->worksiteUuid;
    }
    public function getDepartmentId(): ?int
    {
        return $this->departmentId;
    }
    public function getDepartmentUuid(): ?string
    {
        return $this->departmentUuid;
    }
    public function getJobRoleId(): ?int
    {
        return $this->jobRoleId;
    }
    public function getJobRoleUuid(): ?string
    {
        return $this->jobRoleUuid;
    }

    // --- Domain Behaviors ---

    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }

    /**
     * Assert this user can authenticate.
     *
     * @throws ForbiddenException If account is not active.
     */
    public function assertCanLogin(): void
    {
        if ($this->status === UserStatus::SUSPENDED) {
            throw new ForbiddenException('Account has been suspended. Contact administrator.');
        }
        if ($this->status === UserStatus::PENDING) {
            throw new ForbiddenException('Account is pending activation.');
        }
        if ($this->status === UserStatus::DELETED) {
            throw new ForbiddenException('Account has been deleted. Contact administrator.');
        }
    }

    /**
     * Verify a plain-text password against the stored hash.
     */
    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->passwordHash);
    }

    /**
     * Change the password. Caller must supply a pre-hashed value.
     */
    public function changePassword(string $newHash): void
    {
        $this->passwordHash = $newHash;
    }

    /**
     * Record a successful login timestamp.
     */
    public function recordLogin(): void
    {
        $this->lastLoginAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    /**
     * Suspend this user account.
     *
     * @throws ConflictException If already suspended.
     */
    public function suspend(): void
    {
        if ($this->status === UserStatus::SUSPENDED) {
            throw new ConflictException("User '{$this->email}' is already suspended.");
        }
        if ($this->status !== UserStatus::ACTIVE) {
            throw new ConflictException("Only active users can be suspended.");
        }
        $this->status = UserStatus::SUSPENDED;
    }

    /**
     * Activate (or reactivate) this user account.
     *
     * @throws ConflictException If already active.
     */
    public function activate(): void
    {
        if ($this->status === UserStatus::ACTIVE) {
            throw new ConflictException("User '{$this->email}' is already active.");
        }
        if ($this->status === UserStatus::DELETED) {
            throw new ConflictException("Deleted users cannot be activated.");
        }
        $this->status = UserStatus::ACTIVE;
    }

    public function softDelete(): void
    {
        if ($this->status === UserStatus::DELETED) {
            throw new ConflictException("User '{$this->email}' is already deleted.");
        }
        $this->status = UserStatus::DELETED;
    }

    /**
     * Update profile fields.
     */
    public function updateProfile(string $fullName, string $email, ?string $phone): void
    {
        $this->fullName = $fullName;
        $this->email = $email;
        $this->phone = $phone;
    }

    /**
     * Assign a new role.
     */
    public function assignRole(int $roleId, string $roleSlug): void
    {
        $this->roleId = $roleId;
        $this->roleSlug = $roleSlug;
    }

    public function getEffectiveRoleId(): int|string
    {
        return $this->membershipRoleId ?? $this->roleId;
    }

    public function getEffectiveRoleSlug(): string
    {
        return $this->membershipRoleSlug ?? $this->roleSlug;
    }


    public function bumpAuthzVersion(): void
    {
        $this->authzVersion++;
    }
}
