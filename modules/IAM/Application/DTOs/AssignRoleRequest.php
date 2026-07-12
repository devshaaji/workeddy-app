<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\DTOs;

/** Assign role request DTO. */
final class AssignRoleRequest
{
    public function __construct(
        public int    $userId,
        public string $roleSlug,
    ) {}
}
