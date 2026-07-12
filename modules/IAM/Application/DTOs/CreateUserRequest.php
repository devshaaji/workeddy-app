<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\DTOs;

/** Create user request DTO. */
final class CreateUserRequest
{
    public function __construct(
        public string  $email,
        public string  $fullName,
        public string  $password,
        public string  $roleSlug,
        public ?string $phone = null,
        public ?string $organizationUuid = null,
        public ?string $ipAddress = null,
    ) {}
}
