<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Identity;

use Ramsey\Uuid\Uuid;

final class RamseyUuidGenerator implements UuidGeneratorContract
{
    public function generate(): string
    {
        return Uuid::uuid4()->toString();
    }

    public function isValid(string $value): bool
    {
        return Uuid::isValid($value);
    }
}
