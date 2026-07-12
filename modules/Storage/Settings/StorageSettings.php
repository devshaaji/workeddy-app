<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Storage\Settings;

use WorkEddy\Platform\Settings\ModuleSettings;

final class StorageSettings extends ModuleSettings
{
    protected function moduleName(): string
    {
        return 'storage';
    }

    public function defaultDisk(): string
    {
        return $this->getString('default_disk');
    }

    public function defaultVisibility(): string
    {
        return $this->getString('default_visibility');
    }

    public function localPrivateRoot(): string
    {
        return $this->getString('local_private_root');
    }

    public function maxUploadBytes(): int
    {
        return $this->getInt('max_upload_bytes');
    }

    /** @return string[] */
    public function allowedExtensions(): array
    {
        $value = $this->get('allowed_extensions');

        return is_array($value) ? array_values(array_map('strval', $value)) : [];
    }

    /** @return string[] */
    public function allowedMimeTypes(): array
    {
        $value = $this->get('allowed_mime_types');

        return is_array($value) ? array_values(array_map('strval', $value)) : [];
    }
}
