<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Domain;

/**
 * Role domain entity.
 *
 * Represents a named role with a slug, description, and set of permissions.
 * Roles can be attached directly to platform users or to tenant memberships.
 */
final class Role
{
    /** @var string[] */
    private array $permissions;

    /**
     * @param string[] $permissions Flat list of permission slugs.
     */
    public function __construct(
        private readonly int|string|null $id,
        private readonly string          $uuid,
        private readonly string          $slug,
        private string                   $name,
        private ?string                  $description,
        private readonly bool            $isSystem,
        private string                   $scope = 'staff',
        array                            $permissions = [],
    ) {
        $this->permissions = $permissions;
        $this->scope = strtolower(trim($this->scope)) ?: 'staff';
    }

    public function getId(): int|string|null
    {
        return $this->id;
    }
    public function getUuid(): string
    {
        return $this->uuid;
    }
    public function getSlug(): string
    {
        return $this->slug;
    }
    public function getName(): string
    {
        return $this->name;
    }
    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function isSystem(): bool
    {
        return $this->isSystem;
    }
    public function getScope(): string
    {
        return $this->scope;
    }
    /** @return string[] */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /** @param string[] $permissions */
    public function setPermissions(array $permissions): void
    {
        $this->permissions = $permissions;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function updateName(string $name): void
    {
        $this->name = $name;
    }
    public function updateDescription(?string $description): void
    {
        $this->description = $description;
    }
    public function updateScope(string $scope): void
    {
        $this->scope = strtolower(trim($scope)) ?: 'staff';
    }
}
