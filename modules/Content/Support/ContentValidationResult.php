<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Support;

final class ContentValidationResult
{
    /** @param array<string, string> $errors */
    public function __construct(private readonly array $errors = [])
    {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
