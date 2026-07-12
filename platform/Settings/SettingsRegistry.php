<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Settings;

final class SettingsRegistry
{
    /** @var array<string, SettingDefinition> */
    private array $definitions = [];

    /**
     * @param list<IModuleSettingsProvider> $providers
     */
    public static function fromProviders(array $providers): self
    {
        $registry = new self();
        foreach ($providers as $provider) {
            foreach ($provider->getDefinitions() as $definition) {
                $registry->register($definition);
            }
        }

        return $registry;
    }

    public function register(SettingDefinition $definition): void
    {
        $key = $definition->qualifiedKey();
        if (isset($this->definitions[$key])) {
            throw new \LogicException("Duplicate setting definition: {$key}");
        }

        $this->definitions[$key] = $definition;
    }

    public function get(string $qualifiedKey): SettingDefinition
    {
        return $this->definitions[$qualifiedKey]
            ?? throw new \InvalidArgumentException("Unknown setting key: {$qualifiedKey}");
    }

    /**
     * @return array<string, SettingDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * @return list<string>
     */
    public function getRegisteredModules(): array
    {
        $modules = [];
        foreach ($this->definitions as $def) {
            $modules[$def->module] = true;
        }

        return array_values(array_keys($modules));
    }

    /**
     * @param string $module
     * @return array<string, SettingDefinition>
     */
    public function getForModule(string $module): array
    {
        $defs = [];
        foreach ($this->definitions as $key => $def) {
            if ($def->module === $module) {
                $defs[$key] = $def;
            }
        }

        return $defs;
    }
}
