<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Settings;

interface SettingsStoreContract
{
    public function get(string $module, string $key): ?string;

    public function set(string $module, string $key, string $value, string $updatedBy): void;

    public function delete(string $module, string $key): void;
}
