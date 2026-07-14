<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Settings;

final class SettingsRegistry
{
    /** @var array<string, SettingDefinition> */
    private array $definitions = [];
    /** @var array<string, SettingsPageMetadata> */
    private array $pageMetadata = [];

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

            if ($provider instanceof ISettingsPageProvider) {
                $registry->registerPageMetadata($provider->getSettingsPageMetadata());
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

    public function registerPageMetadata(SettingsPageMetadata $metadata): void
    {
        if (isset($this->pageMetadata[$metadata->module])) {
            throw new \LogicException('Duplicate settings page metadata: ' . $metadata->module);
        }
        if ($this->getForModule($metadata->module) === []) {
            throw new \LogicException('Settings page metadata registered for unknown module: ' . $metadata->module);
        }

        $this->pageMetadata[$metadata->module] = $metadata;
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

    public function getPageMetadata(string $module): ?SettingsPageMetadata
    {
        return $this->pageMetadata[$module] ?? null;
    }

    /**
     * @return list<SettingsPageMetadata>
     */
    public function getPageMetadataEntries(): array
    {
        return array_values($this->pageMetadata);
    }
}
