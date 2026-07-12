<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\DTOs;

final class PublicRegisterRequest
{
    public function __construct(
        public string $email,
        public string $fullName,
        public string $password,
        public string $organizationName,
        public ?string $phone = null,
        public ?string $ipAddress = null,
    ) {}
}
