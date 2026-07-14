<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Settings;

final class SettingDefinition
{
    private ?\Closure $validationFn;

    public function __construct(
        public readonly string $key,
        public readonly string $module,
        public readonly SettingType $type,
        public readonly mixed $default,
        public readonly string $label = '',
        public readonly string $description = '',
        ?callable $validation = null,
        public readonly bool $editable = true,
        public readonly bool $sensitive = false,
        public readonly bool $restartRequired = false,
        public readonly string $section = 'General',
    ) {
        $this->validationFn = $validation !== null ? \Closure::fromCallable($validation) : null;
    }

    public function qualifiedKey(): string
    {
        return $this->module . '.' . $this->key;
    }

    public function validate(mixed $value): ?string
    {
        $valid = match ($this->type) {
            SettingType::STRING => is_string($value),
            SettingType::INTEGER => is_int($value) || (is_string($value) && ctype_digit(ltrim($value, '-'))),
            SettingType::FLOAT => is_numeric($value),
            SettingType::BOOLEAN => is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true),
            SettingType::JSON => is_array($value) || (is_string($value) && json_decode($value) !== null),
        };

        if (!$valid) {
            return sprintf("Setting '%s' expects type '%s', got '%s'.", $this->qualifiedKey(), $this->type->value, get_debug_type($value));
        }

        if ($this->validationFn !== null) {
            $result = ($this->validationFn)($value);
            if ($result !== true) {
                return is_string($result) ? $result : sprintf("Setting '%s' failed custom validation.", $this->qualifiedKey());
            }
        }

        return null;
    }

    public function castFromStorage(string $raw): mixed
    {
        return $this->type->cast($raw);
    }

    public function serializeForStorage(mixed $value): string
    {
        return $this->type->serialize($value);
    }
}
