<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Identity;

interface UuidGeneratorContract
{
    public function generate(): string;

    public function isValid(string $value): bool;
}
