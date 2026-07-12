<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Settings;

enum SettingType: string
{
    case STRING = 'string';
    case INTEGER = 'integer';
    case FLOAT = 'float';
    case BOOLEAN = 'boolean';
    case JSON = 'json';

    public function cast(string $raw): mixed
    {
        return match ($this) {
            self::STRING => $raw,
            self::INTEGER => (int) $raw,
            self::FLOAT => (float) $raw,
            self::BOOLEAN => in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true),
            self::JSON => json_decode($raw, true, 512, JSON_THROW_ON_ERROR),
        };
    }

    public function serialize(mixed $value): string
    {
        return match ($this) {
            self::STRING => (string) $value,
            self::INTEGER => (string) (int) $value,
            self::FLOAT => (string) (float) $value,
            self::BOOLEAN => $value ? '1' : '0',
            self::JSON => json_encode($value, JSON_THROW_ON_ERROR),
        };
    }
}
