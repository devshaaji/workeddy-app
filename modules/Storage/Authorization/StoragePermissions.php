<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Storage\Authorization;

final class StoragePermissions
{
    public const FILE_VIEW = 'storage.file.view';
    public const FILE_UPLOAD = 'storage.file.upload';
    public const FILE_DOWNLOAD = 'storage.file.download';
    public const FILE_DELETE = 'storage.file.delete';
    public const SETTINGS_MANAGE = 'storage.settings.manage';
}
