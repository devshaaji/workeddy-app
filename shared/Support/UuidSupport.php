<?php

declare(strict_types=1);

namespace WorkEddy\Shared\Support;

use WorkEddy\Platform\Identity\NativeUuidGenerator;
use WorkEddy\Platform\Identity\UuidGeneratorContract;
use WorkEddy\Shared\Exceptions\ValidationException;

final class UuidSupport
{
    private static ?UuidGeneratorContract $generator = null;

    public static function useGenerator(UuidGeneratorContract $generator): void
    {
        self::$generator = $generator;
    }

    public static function resetGenerator(): void
    {
        self::$generator = null;
    }

    public static function generate(): string
    {
        return self::generator()->generate();
    }

    public static function isValid(string $value): bool
    {
        return self::generator()->isValid($value);
    }

    public static function requireValid(string $value, string $field = 'id'): string
    {
        if (!self::isValid($value)) {
            throw new ValidationException([
                $field => sprintf('The %s field must be a valid UUID.', $field),
            ]);
        }

        return $value;
    }

    private static function generator(): UuidGeneratorContract
    {
        if (self::$generator !== null) {
            return self::$generator;
        }

        self::$generator = new NativeUuidGenerator();

        return self::$generator;
    }
}
