<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\DTOs;

/** Role DTO for read access. */
final class RoleDTO
{
    /**
     * @param string[] $permissions
     */
    public function __construct(
        public string  $id,
        public string  $slug,
        public string  $name,
        public ?string $description,
        public bool    $isSystem,
        public array   $permissions,
    ) {}
}
