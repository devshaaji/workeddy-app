<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Settings;

final class InMemorySettingsStore implements SettingsStoreContract
{
    /** @var array<string, string> */
    private array $values = [];

    public function get(string $module, string $key): ?string
    {
        return $this->values[$module . '.' . $key] ?? null;
    }

    public function set(string $module, string $key, string $value, string $updatedBy): void
    {
        $this->values[$module . '.' . $key] = $value;
    }

    public function delete(string $module, string $key): void
    {
        unset($this->values[$module . '.' . $key]);
    }
}
