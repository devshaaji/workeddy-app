<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Settings;

interface IModuleSettingsProvider
{
    public function getModuleName(): string;

    /**
     * @return list<SettingDefinition>
     */
    public function getDefinitions(): array;
}
