<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Export\Settings;

use WorkEddy\Platform\Settings\ModuleSettings;

final class ExportSettings extends ModuleSettings
{
    public const ALLOWED_FORMATS = 'allowed_formats';
    public const DEFAULT_FORMAT = 'default_format';
    public const SIGNED_LINK_TTL_MINUTES = 'signed_link_ttl_minutes';
    public const MAX_EXPORT_ROWS = 'max_export_rows';
    public const DEIDENTIFICATION_PROFILE = 'deidentification_profile';

    protected function moduleName(): string
    {
        return 'export';
    }

    /** @return list<string> */
    public function allowedFormats(): array
    {
        return array_values(array_map('strval', $this->getJson(self::ALLOWED_FORMATS)));
    }

    public function defaultFormat(): string
    {
        return (string) $this->get(self::DEFAULT_FORMAT);
    }

    public function signedLinkTtlMinutes(): int
    {
        return $this->getInt(self::SIGNED_LINK_TTL_MINUTES);
    }

    public function maxExportRows(): int
    {
        return $this->getInt(self::MAX_EXPORT_ROWS);
    }

    public function deidentificationProfile(): string
    {
        return (string) $this->get(self::DEIDENTIFICATION_PROFILE);
    }
}
