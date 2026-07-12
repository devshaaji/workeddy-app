<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Settings;

final class DbalSettingsStore implements SettingsStoreContract
{
    public function __construct(private readonly object $connection) {}

    public function get(string $module, string $key): ?string
    {
        $value = $this->connection->fetchOne(
            'SELECT setting_value FROM system_settings WHERE module = ? AND setting_key = ?',
            [$module, $key],
        );

        return $value === false || $value === null ? null : (string) $value;
    }

    public function set(string $module, string $key, string $value, string $updatedBy): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $exists = $this->connection->fetchOne(
            'SELECT 1 FROM system_settings WHERE module = ? AND setting_key = ?',
            [$module, $key],
        );

        if ($exists) {
            $this->connection->update('system_settings', [
                'setting_value' => $value,
                'updated_by' => $updatedBy,
                'updated_at' => $now,
            ], ['module' => $module, 'setting_key' => $key]);
        } else {
            $this->connection->insert('system_settings', [
                'module' => $module,
                'setting_key' => $key,
                'setting_value' => $value,
                'updated_by' => $updatedBy,
                'updated_at' => $now,
            ]);
        }
    }

    public function delete(string $module, string $key): void
    {
        $this->connection->delete('system_settings', ['module' => $module, 'setting_key' => $key]);
    }
}
