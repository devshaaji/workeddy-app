<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Module;

use Psr\Container\ContainerInterface;

interface ModuleServiceProviderInterface
{
    public function getName(): string;

    /**
     * @return array<class-string|string, mixed>
     */
    public function getDefinitions(): array;

    public function getRouteFile(): ?string;

    /**
     * @return array<string, list<class-string>>
     */
    public function getEventListeners(): array;

    /**
     * @return array<string, class-string|\Closure|object>|list<class-string>
     */
    public function getJobHandlers(): array;

    public function getPermissionDefinitionProvider(): mixed;

    public function getSettingsProvider(): mixed;

    public function getConsoleCommandProvider(): mixed;

    public function boot(ContainerInterface $container): void;
}
