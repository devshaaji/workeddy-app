<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Authorization;

final class AssessmentPermissions
{
    public const VIEW = 'assessment.view';
    public const CREATE = 'assessment.create';
    public const UPDATE = 'assessment.update';
    public const REVIEW = 'assessment.review';
    public const LOCK = 'assessment.lock';
    public const VIDEO_UPLOAD = 'assessment.video.upload';
    public const VIEW_COMPARISON = 'assessment.comparison.view';
    public const GENERATE_COMPARISON = 'assessment.comparison.generate';
    public const LOCK_COMPARISON = 'assessment.comparison.lock';
}
