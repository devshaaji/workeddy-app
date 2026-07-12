<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Privacy\Authorization;

final class PrivacyPermissions
{
    public const CONSENT_RECORD = 'privacy.consent.record';
    public const VIDEO_ACCESS = 'privacy.video.access';
    public const RETENTION_MANAGE = 'privacy.retention.manage';
    public const RETENTION_ENFORCE = 'privacy.retention.enforce';
    public const AUDIT_VIEW = 'privacy.audit.view';
}
