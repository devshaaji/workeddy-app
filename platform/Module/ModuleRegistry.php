<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Module;

use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use Psr\Container\ContainerInterface;

final class ModuleRegistry
{
    /** @var list<ModuleServiceProviderInterface> */
    private array $providers = [];

    private bool $booted = false;

    /** @var array<string, bool> */
    private array $bootedModules = [];

    /**
     * @param list<class-string<ModuleServiceProviderInterface>|ModuleServiceProviderInterface> $providers
     */
    public function __construct(array $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[] = is_string($provider) ? new $provider() : $provider;
        }
    }

    /**
     * @return list<ModuleServiceProviderInterface>
     */
    public function providers(): array
    {
        return $this->providers;
    }

    /**
     * @return array<class-string|string, mixed>
     */
    public function definitions(): array
    {
        $definitions = [];
        foreach ($this->providers as $provider) {
            $definitions = array_replace($definitions, $provider->getDefinitions());
        }

        return $definitions;
    }

    /**
     * @return list<string>
     */
    public function routeFiles(): array
    {
        $routeFiles = [];
        foreach ($this->providers as $provider) {
            $routeFile = $provider->getRouteFile();
            if ($routeFile !== null) {
                $routeFiles[] = $routeFile;
            }
        }

        return $routeFiles;
    }

    /**
     * @return array<string, string|null>
     */
    public function declaredRouteFiles(): array
    {
        $routeFiles = [];
        foreach ($this->providers as $provider) {
            $routeFiles[$provider->getName()] = $provider->getRouteFile();
        }

        return $routeFiles;
    }

    /**
     * @return array<string, string|null>
     */
    public function getDeclaredRouteFiles(): array
    {
        return $this->declaredRouteFiles();
    }

    /**
     * @return list<class-string>
     */
    public function listenerClassesFor(string $contract): array
    {
        $listeners = [];
        foreach ($this->providers as $provider) {
            $declared = $provider->getEventListeners()[$contract] ?? [];
            foreach ($declared as $listener) {
                if (is_string($listener)) {
                    $listeners[] = $listener;
                }
            }
        }

        return $listeners;
    }

    /**
     * @return list<IPermissionDefinitionProvider>
     */
    public function permissionProviders(): array
    {
        $providers = [];
        foreach ($this->providers as $module) {
            $provider = $module->getPermissionDefinitionProvider();
            if ($provider instanceof IPermissionDefinitionProvider) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    /**
     * @return list<IModuleSettingsProvider>
     */
    public function settingsProviders(): array
    {
        $providers = [];
        foreach ($this->providers as $module) {
            $provider = $module->getSettingsProvider();
            if ($provider instanceof IModuleSettingsProvider) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    /**
     * @return list<\WorkEddy\Platform\Console\IConsoleCommandProvider>
     */
    public function consoleCommandProviders(): array
    {
        $providers = [];
        foreach ($this->providers as $module) {
            $provider = $module->getConsoleCommandProvider();
            if ($provider instanceof \WorkEddy\Platform\Console\IConsoleCommandProvider) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    public function boot(ContainerInterface $container): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->providers as $provider) {
            if (isset($this->bootedModules[$provider->getName()])) {
                continue;
            }

            $provider->boot($container);
            $this->bootedModules[$provider->getName()] = true;
        }

        $this->booted = true;
    }

    public function bootModule(string $name, ContainerInterface $container): void
    {
        if (isset($this->bootedModules[$name])) {
            return;
        }

        foreach ($this->providers as $provider) {
            if ($provider->getName() !== $name) {
                continue;
            }

            $provider->boot($container);
            $this->bootedModules[$name] = true;

            return;
        }
    }
}
