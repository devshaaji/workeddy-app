<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Settings;

interface ISettingsPageProvider
{
    public function getSettingsPageMetadata(): SettingsPageMetadata;
}
