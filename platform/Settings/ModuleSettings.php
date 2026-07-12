<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Settings;

abstract class ModuleSettings
{
    public function __construct(private readonly SettingsService $settings) {}

    abstract protected function moduleName(): string;

    protected function get(string $key): mixed
    {
        return $this->settings->get($this->qualify($key));
    }

    protected function getString(string $key): string
    {
        return (string) $this->get($key);
    }

    protected function getInt(string $key): int
    {
        return (int) $this->get($key);
    }

    protected function getFloat(string $key): float
    {
        return (float) $this->get($key);
    }

    protected function getBool(string $key): bool
    {
        $value = $this->get($key);
        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, [1, '1', 'true'], true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getJson(string $key): array
    {
        $value = $this->get($key);
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function qualify(string $key): string
    {
        return $this->moduleName() . '.' . $key;
    }
}
