<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Application;

use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class StructureInput
{
    public static function requireName(string $name): string
    {
        $normalized = trim($name);
        if ($normalized === '') {
            throw new ValidationException(['name' => 'Name is required.']);
        }

        return $normalized;
    }

    public static function requireStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        if (!in_array($normalized, ['active', 'inactive'], true)) {
            throw new ValidationException(['status' => 'Status must be active or inactive.']);
        }

        return $normalized;
    }

    public static function optionalStatus(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        $normalized = trim($status);
        if ($normalized === '') {
            return null;
        }

        return self::requireStatus($normalized);
    }

    public static function optionalString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    public static function optionalUuid(?string $value, string $field): ?string
    {
        $normalized = self::optionalString($value);
        if ($normalized === null) {
            return null;
        }

        return UuidSupport::requireValid($normalized, $field);
    }
}
