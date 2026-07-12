<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Settings;

final class SettingsService
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(
        private readonly array $values = [],
        private readonly ?SettingsRegistry $registry = null,
        private readonly ?SettingsStoreContract $store = null,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }

        if ($this->store !== null && $this->registry !== null) {
            $definition = $this->registry->get($key);
            $stored = $this->store->get($definition->module, $definition->key);
            if ($stored !== null) {
                return $definition->castFromStorage($stored);
            }
        }

        if ($this->registry !== null) {
            try {
                return $this->registry->get($key)->default;
            } catch (\InvalidArgumentException) {
                return $default;
            }
        }

        return $default;
    }

    public function validate(string $key, mixed $value): void
    {
        if ($this->registry === null) {
            return;
        }

        $error = $this->registry->get($key)->validate($value);
        if ($error !== null) {
            throw new SettingsValidationException($error);
        }
    }

    public function set(string $key, mixed $value, string $actorId): void
    {
        if ($this->registry === null || $this->store === null) {
            throw new \LogicException('Settings registry and store are required for setting writes.');
        }

        $definition = $this->registry->get($key);
        $error = $definition->validate($value);
        if ($error !== null) {
            throw new SettingsValidationException($error);
        }

        $this->store->set($definition->module, $definition->key, $definition->serializeForStorage($value), $actorId);
    }

    /**
     * @param array<string, mixed> $values
     */
    public function setMany(string $module, array $values, string $actorId): void
    {
        if ($this->registry === null || $this->store === null) {
            throw new \LogicException('Settings registry and store are required for setting writes.');
        }

        foreach ($values as $key => $value) {
            $definition = $this->registry->get($module . '.' . $key);
            $error = $definition->validate($value);
            if ($error !== null) {
                throw new SettingsValidationException($error);
            }

            $this->store->set($definition->module, $definition->key, $definition->serializeForStorage($value), $actorId);
        }
    }

    public function reset(string $key, string $actorId): void
    {
        if ($this->registry === null || $this->store === null) {
            throw new \LogicException('Settings registry and store are required for setting writes.');
        }

        $definition = $this->registry->get($key);
        $this->store->delete($definition->module, $definition->key);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAllForModule(string $module): array
    {
        if ($this->registry === null) {
            return [];
        }

        $values = [];
        $definitions = $this->registry->getForModule($module);
        foreach ($definitions as $key => $definition) {
            $values[$definition->key] = $this->get($definition->qualifiedKey(), $definition->default);
        }

        return $values;
    }

    public function definitions(): array
    {
        if ($this->registry === null) {
            return [];
        }

        $items = [];
        foreach ($this->registry->all() as $key => $definition) {
            $value = $this->get($key, $definition->default);
            $items[$key] = [
                'key' => $key,
                'module' => $definition->module,
                'setting_key' => $definition->key,
                'type' => $definition->type->value,
                'value' => $definition->sensitive ? null : $value,
                'default' => $definition->sensitive ? null : $definition->default,
                'label' => $definition->label,
                'description' => $definition->description,
                'editable' => $definition->editable,
                'sensitive' => $definition->sensitive,
                'restart_required' => $definition->restartRequired,
            ];
        }

        return $items;
    }

    public function getRegistry(): ?SettingsRegistry
    {
        return $this->registry;
    }
}
