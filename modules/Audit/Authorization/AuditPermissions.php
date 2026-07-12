<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Audit\Authorization;

final class AuditPermissions
{
    public const VIEW = 'audit.view';
    public const EXPORT = 'audit.export';
    public const RECORD = 'audit.record';
    public const SETTINGS_MANAGE = 'audit.settings.manage';
    public const REPORT_VIEW = 'report.view';
}
