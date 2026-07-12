<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Domain;

/**
 * Permission domain entity.
 *
 * Represents a granular capability (e.g., "invoice.create", "gazette.publish").
 * Permissions are assigned to roles, not directly to users.
 * User-level overrides are managed via the overrides table.
 */
final class Permission
{
    /**
     * @param string[] $defaultAssignments
     */
    public function __construct(
        public int|string|null $id,
        public string $uuid,
        public string $slug,
        public string $name,
        public string $module,
        public ?string $description = null,
        public string $actionCategory = 'read',
        public ?string $riskLevel = null,
        public array $defaultAssignments = [],
        public bool $systemOnly = false,
    ) {}
}
