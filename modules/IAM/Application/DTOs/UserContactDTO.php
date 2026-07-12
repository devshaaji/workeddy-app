<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\DTOs;

final class UserContactDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly string $email,
        public readonly string $fullName,
        public readonly bool $isActive,
        public readonly ?string $phone = null,
    ) {}
}
