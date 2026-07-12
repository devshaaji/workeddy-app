<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Privacy\Domain;

final class SignedVideoAccess
{
    /** @param array<string, mixed> $claims */
    public function __construct(public readonly array $claims, public readonly string $token) {}
}
