<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\DTOs;

/** Change password request DTO. */
final class ChangePasswordRequest
{
    public function __construct(
        public int    $userId,
        public string $currentPassword,
        public string $newPassword,
    ) {}
}
