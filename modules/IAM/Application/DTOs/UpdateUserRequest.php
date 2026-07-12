<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\DTOs;

/** Update user profile request DTO. */
final class UpdateUserRequest
{
    public function __construct(
        public int     $userId,
        public string  $fullName,
        public string  $email,
        public ?string $phone = null,
    ) {}
}
