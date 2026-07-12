<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Authorization;

final class PermissionDefinition
{
    public readonly string $key;
    public readonly string $label;
    public readonly string $description;
    public readonly string $module;
    public readonly string $actionCategory;
    public readonly ?string $risk;
    /** @var list<string> */
    public readonly array $defaultAssignments;
    public readonly bool $systemOnly;

    /**
     * Supports both definition shapes used in the codebase:
     * - key, label, description, module
     * - module, key, label, description, action category, risk, defaults, system-only
     *
     * @param list<string> $defaultAssignments
     */
    public function __construct(
        string $key,
        string $label,
        string $description,
        string $module,
        string $actionCategory = 'read',
        ?string $risk = null,
        array $defaultAssignments = [],
        bool $systemOnly = false,
    ) {
        if (!str_contains($key, '.') && str_contains($label, '.')) {
            $this->module = self::normalizeKey($key);
            $this->key = self::normalizeKey($label);
            $this->label = $description;
            $this->description = $module;
        } else {
            $this->key = self::normalizeKey($key);
            $this->label = $label;
            $this->description = $description;
            $this->module = self::normalizeKey($module);
        }

        $this->actionCategory = self::normalizeKey($actionCategory);
        $this->risk = $risk !== null && trim($risk) !== '' ? self::normalizeKey($risk) : null;
        $this->defaultAssignments = array_values(array_unique(array_map(
            static fn(string $role): string => self::normalizeKey($role),
            $defaultAssignments,
        )));
        $this->systemOnly = $systemOnly;
    }

    public static function normalizeKey(string $key): string
    {
        return strtolower(trim(str_replace([' ', ':'], ['.', '.'], $key)));
    }
}
