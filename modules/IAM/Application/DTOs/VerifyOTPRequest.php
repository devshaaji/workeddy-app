<?php

declare(strict_types=1);

namespace WorkEddy\Modules\IAM\Application\DTOs;

/** OTP verification request DTO. */
final class VerifyOTPRequest
{
    public function __construct(
        public int    $userId,
        public string $code,
        public ?string $ipAddress = null,
    ) {}
}
