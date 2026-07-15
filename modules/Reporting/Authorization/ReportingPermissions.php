<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Authorization;

final class ReportingPermissions
{
    public const VIEW = 'reporting.view';
    public const SYSTEM_VIEW = 'reporting.system.view';
    public const SETTINGS = 'reporting.settings';
    public const NATIONAL_CONTEXT_MANAGE = 'reporting.national_context.manage';
}
