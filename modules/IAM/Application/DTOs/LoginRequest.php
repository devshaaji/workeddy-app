<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\DTOs;

/** Login request DTO. */
final class LoginRequest
{
    public function __construct(
        public string  $email,
        public string  $password,
        public ?string $ipAddress = null,
    ) {}
}
