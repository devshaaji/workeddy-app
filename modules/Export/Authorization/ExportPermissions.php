<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Export\Authorization;

final class ExportPermissions
{
    public const VIEW = 'export.research.view';
    public const PREVIEW = 'export.research.preview';
    public const GENERATE = 'export.research.generate';
    public const DOWNLOAD = 'export.research.download';
}
